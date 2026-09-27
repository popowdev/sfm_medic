<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

header('Content-Type: application/json; charset=utf-8');

function je(array $d): void {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function trauma_csrf_ok(string $t): bool {
    start_session();
    if ($t === '' || !isset($_SESSION['csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf'], $t);
}

const ZONE_PENALTIES = [

    'tete'            => 5,
    'thorax'          => 5,
    'epaule_gauche'   => 2,
    'epaule_droite'   => 2,
    'bras_gauche'     => 2,
    'bras_droit'      => 2,
    'jambe_gauche'    => 2,
    'jambe_droite'    => 2,
    'balles_gpb'      => 2,
    'avp'             => 2,
];

const SEVERITY_PENALTIES = [
    'Légère'  => 0,
    'Moyenne' => 5,
    'Grave'   => 10,
];

const SURVIVAL_MIN     = 25.0;
const RECOVERY_PER_DAY = 1.5;

start_session();
require_login();

$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = (($u['role'] ?? '') === 'admin');

try {
    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `trauma_patients` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `last_name` VARCHAR(80) NOT NULL,
            `first_name` VARCHAR(80) NOT NULL,
            `birth_date` DATE DEFAULT NULL,
            `photo_path` VARCHAR(255) DEFAULT NULL,
            `survival` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
            `last_injury_at` DATETIME DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_by` INT(11) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `is_deceased` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `trauma_injuries` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `patient_id` INT(11) NOT NULL,
            `zones` VARCHAR(500) NOT NULL,
            `penalty` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `survival_before` DECIMAL(5,2) NOT NULL,
            `survival_after` DECIMAL(5,2) NOT NULL,
            `note` VARCHAR(500) DEFAULT NULL,
            `created_by` INT(11) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `trauma_type` VARCHAR(80) NOT NULL DEFAULT 'Blessure par balle',
            `severity` VARCHAR(20) NOT NULL DEFAULT 'Moyenne',
            `injury_details` LONGTEXT DEFAULT NULL,
            `report_text` MEDIUMTEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_patient` (`patient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    try {
        $pdo->exec("ALTER TABLE `trauma_patients` ADD COLUMN IF NOT EXISTS `is_deceased` TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) {
    }

    try {
        $pdo->exec("ALTER TABLE `trauma_injuries` ADD COLUMN IF NOT EXISTS `trauma_type` VARCHAR(80) NOT NULL DEFAULT 'Blessure par balle'");
    } catch (Throwable $e) {
    }

    try {
        $pdo->exec("ALTER TABLE `trauma_injuries` ADD COLUMN IF NOT EXISTS `severity` VARCHAR(20) NOT NULL DEFAULT 'Moyenne'");
        $pdo->exec("ALTER TABLE `trauma_injuries` ADD COLUMN IF NOT EXISTS `injury_details` LONGTEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE `trauma_injuries` ADD COLUMN IF NOT EXISTS `report_text` MEDIUMTEXT DEFAULT NULL");
    } catch (Throwable $e) {
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $action = (string)($_GET['action'] ?? 'list');

        if ($action === 'list') {
            $q = trim((string)($_GET['q'] ?? ''));
            $letter = strtoupper(trim((string)($_GET['letter'] ?? 'A')));
            $includeDeceased = ((string)($_GET['include_deceased'] ?? '0') === '1');
            $params = [];
            $whereParts = [];

            if (!$includeDeceased) {
                $whereParts[] = "is_deceased = 0";
            }

            if (!preg_match('/^[A-Z]$/', $letter)) {
                $letter = 'A';
            }

            $whereParts[] = "UPPER(last_name) LIKE ?";
            $params[] = $letter . '%';

            if ($q !== '') {
                $whereParts[] = "(CONCAT(last_name, ' ', first_name) LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }

            $where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

            $stmt = $pdo->prepare("SELECT * FROM trauma_patients {$where} ORDER BY last_name ASC, first_name ASC");
            $stmt->execute($params);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($patients as &$patient) {
                $patient = refresh_patient_survival($pdo, $patient);
            }
            unset($patient);

            je([
                'ok' => true,
                'patients' => $patients,
            ]);
        }

        if ($action === 'get') {
            $id = (int)($_GET['id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM trauma_patients WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                je(['ok' => false, 'error' => 'Patient introuvable.']);
            }

            $patient = refresh_patient_survival($pdo, $patient);

            $stmt = $pdo->prepare("
                SELECT i.*, u.username AS created_by_name
                FROM trauma_injuries i
                LEFT JOIN users u ON u.id = i.created_by
                WHERE i.patient_id = ?
                ORDER BY i.created_at DESC, i.id DESC
            ");
            $stmt->execute([$id]);
            $injuries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            je([
                'ok' => true,
                'patient' => $patient,
                'injuries' => $injuries,
            ]);
        }

        je(['ok' => false, 'error' => 'Action inconnue.']);
    }

    if ($method !== 'POST') {
        je(['ok' => false, 'error' => 'Méthode non autorisée.']);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrf = (string)($data['csrf'] ?? '');

    if (!trauma_csrf_ok($csrf)) {
        je(['ok' => false, 'error' => 'CSRF invalide.']);
    }

    $action = (string)($data['action'] ?? '');

    if ($action === 'create_patient') {
        $lastName  = trim((string)($data['last_name'] ?? ''));
        $firstName = trim((string)($data['first_name'] ?? ''));
        $birthDate = trim((string)($data['birth_date'] ?? ''));
        $notes     = trim((string)($data['notes'] ?? ''));

        if ($lastName === '' || $firstName === '') {
            je(['ok' => false, 'error' => 'Nom et prénom requis.']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO trauma_patients (last_name, first_name, birth_date, notes, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $lastName,
            $firstName,
            $birthDate !== '' ? $birthDate : null,
            $notes !== '' ? $notes : null,
            $uid
        ]);

        je([
            'ok' => true,
            'id' => (int)$pdo->lastInsertId(),
        ]);
    }

    if ($action === 'update_patient') {
        $id        = (int)($data['id'] ?? 0);
        $lastName  = trim((string)($data['last_name'] ?? ''));
        $firstName = trim((string)($data['first_name'] ?? ''));
        $birthDate = trim((string)($data['birth_date'] ?? ''));
        $notes     = trim((string)($data['notes'] ?? ''));

        if ($id <= 0 || $lastName === '' || $firstName === '') {
            je(['ok' => false, 'error' => 'Données invalides.']);
        }

        $stmt = $pdo->prepare("
            UPDATE trauma_patients
            SET last_name = ?, first_name = ?, birth_date = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $lastName,
            $firstName,
            $birthDate !== '' ? $birthDate : null,
            $notes !== '' ? $notes : null,
            $id
        ]);

        je(['ok' => true]);
    }

    if ($action === 'delete_patient') {
        if (!$isAdmin) {
            je(['ok' => false, 'error' => 'Accès refusé.']);
        }

        $id = (int)($data['id'] ?? 0);

        $pdo->prepare("DELETE FROM trauma_injuries WHERE patient_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM trauma_patients WHERE id = ?")->execute([$id]);

        je(['ok' => true]);
    }

    if ($action === 'search_patients') {
        $q = trim((string)($data['q'] ?? ''));
        if (mb_strlen($q, 'UTF-8') < 2) {
            je(['ok' => true, 'patients' => []]);
        }

        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("
            SELECT id, last_name, first_name, survival, is_deceased, last_injury_at
            FROM trauma_patients
            WHERE last_name LIKE ?
               OR first_name LIKE ?
               OR CONCAT(last_name, ' ', first_name) LIKE ?
               OR CONCAT(first_name, ' ', last_name) LIKE ?
            ORDER BY updated_at DESC, id DESC
            LIMIT 12
        ");
        $stmt->execute([$like, $like, $like, $like]);

        je([
            'ok' => true,
            'patients' => array_map(static function(array $p): array {
                return [
                    'id' => (int)$p['id'],
                    'last_name' => (string)$p['last_name'],
                    'first_name' => (string)$p['first_name'],
                    'survival' => isset($p['survival']) ? (float)$p['survival'] : null,
                    'is_deceased' => (int)($p['is_deceased'] ?? 0),
                    'last_injury_at' => $p['last_injury_at'] ?? null,
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC))
        ]);
    }

    if ($action === 'add_injury') {
        $patientId  = (int)($data['patient_id'] ?? 0);
        $zonesRaw   = (array)($data['zones'] ?? []);
        $traumaType = trim((string)($data['trauma_type'] ?? 'Blessure par balle'));
        $severity   = normalize_severity((string)($data['severity'] ?? 'Moyenne'));
        $note       = trim((string)($data['note'] ?? ''));
        $injuryAt   = normalize_injury_datetime((string)($data['injury_date'] ?? ''));

        if ($patientId <= 0 || empty($zonesRaw)) {
            je(['ok' => false, 'error' => 'Patient et zones requis.']);
        }

        $stmt = $pdo->prepare("SELECT * FROM trauma_patients WHERE id = ? LIMIT 1");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            je(['ok' => false, 'error' => 'Patient introuvable.']);
        }

        $validZones = [];
        foreach ($zonesRaw as $zone) {
            $zone = (string)$zone;
            if (isset(ZONE_PENALTIES[$zone])) {
                $validZones[] = $zone;
            }
        }

        if (!$validZones) {
            je(['ok' => false, 'error' => 'Zones invalides.']);
        }

        $penalty = compute_penalty_from_zones_and_severity(implode(',', $validZones), $severity);

        $stmt = $pdo->prepare("
            INSERT INTO trauma_injuries
            (patient_id, zones, penalty, survival_before, survival_after, note, created_by, created_at, trauma_type, severity, injury_details)
            VALUES (?, ?, ?, 100.00, 100.00, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $patientId,
            implode(',', $validZones),
            $penalty,
            $note !== '' ? $note : null,
            $uid,
            $injuryAt,
            $traumaType !== '' ? $traumaType : 'Blessure par balle',
            $severity,
            json_encode(['source' => 'trauma_page', 'severity' => $severity, 'zones' => $validZones], JSON_UNESCAPED_UNICODE),
        ]);

        $insertedInjuryId = (int)$pdo->lastInsertId();

        $result = recalculate_survival($pdo, $patientId);

        $stmt = $pdo->prepare("
            SELECT survival_before, survival_after, penalty
            FROM trauma_injuries
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$insertedInjuryId]);
        $injuryRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'survival_before' => null,
            'survival_after' => null,
            'penalty' => $penalty,
        ];

        je([
            'ok' => true,
            'survival_before' => isset($injuryRow['survival_before']) ? (float)$injuryRow['survival_before'] : null,
            'survival_after'  => isset($injuryRow['survival_after']) ? (float)$injuryRow['survival_after'] : null,
            'penalty'         => isset($injuryRow['penalty']) ? (float)$injuryRow['penalty'] : $penalty,
            'injury_date'     => $injuryAt,
            'patient_survival'=> $result['survival'],
        ]);
    }

    if ($action === 'import_medical_report') {
        $lastName   = trim((string)($data['last_name'] ?? ''));
        $firstName  = trim((string)($data['first_name'] ?? ''));
        $injuriesIn = (array)($data['injuries'] ?? []);
        $note       = trim((string)($data['note'] ?? ''));
        $reportText = trim((string)($data['report_text'] ?? ''));
        $injuryAt   = normalize_injury_datetime((string)($data['injury_date'] ?? ''));

        if ($lastName === '' || $firstName === '') {
            je(['ok' => false, 'error' => 'Nom et prénom requis.']);
        }
        if (empty($injuriesIn)) {
            je(['ok' => false, 'error' => 'Aucune blessure à enregistrer.']);
        }

        $pdo->beginTransaction();

        $requestedPatientId = (int)($data['patient_id'] ?? 0);
        $patientId = 0;

        if ($requestedPatientId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM trauma_patients WHERE id = ? LIMIT 1");
            $stmt->execute([$requestedPatientId]);
            $patientId = (int)($stmt->fetchColumn() ?: 0);
        }

        if ($patientId <= 0) {
            $existingPatient = find_patient_by_identity($pdo, $lastName, $firstName);
            if ($existingPatient) {
                $patientId = (int)$existingPatient['id'];
            }
        }

        if ($patientId <= 0) {
            $stmt = $pdo->prepare("
                INSERT INTO trauma_patients (last_name, first_name, notes, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $lastName,
                $firstName,
                $note !== '' ? $note : null,
                $uid
            ]);
            $patientId = (int)$pdo->lastInsertId();
        }

        $inserted = 0;
        $totalPenalty = 0.0;

        foreach ($injuriesIn as $inj) {
            if (!is_array($inj)) {
                continue;
            }

            $part       = trim((string)($inj['part'] ?? ''));
            $zone       = normalize_generator_zone($part);
            $traumaType = trim((string)($inj['type'] ?? 'Blessure'));
            $severity   = normalize_severity((string)($inj['severity'] ?? 'Moyenne'));

            if ($zone === '' || !isset(ZONE_PENALTIES[$zone])) {
                continue;
            }

            $penalty = compute_penalty_from_zones_and_severity($zone, $severity);
            $totalPenalty += $penalty;

            $detail = [
                'source' => 'generateur_rapport_medical',
                'part' => $part,
                'zone' => $zone,
                'type' => $traumaType,
                'severity' => $severity,
                'zone_penalty' => ZONE_PENALTIES[$zone],
                'severity_penalty' => SEVERITY_PENALTIES[$severity] ?? 5,
                'total_penalty' => $penalty,
            ];

            $rowNote = trim(($part !== '' ? $part . ' — ' : '') . $severity . ($note !== '' ? ' | ' . $note : ''));

            $stmt = $pdo->prepare("
                INSERT INTO trauma_injuries
                (patient_id, zones, penalty, survival_before, survival_after, note, created_by, created_at, trauma_type, severity, injury_details, report_text)
                VALUES (?, ?, ?, 100.00, 100.00, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $patientId,
                $zone,
                $penalty,
                $rowNote !== '' ? $rowNote : null,
                $uid,
                $injuryAt,
                $traumaType !== '' ? $traumaType : 'Blessure',
                $severity,
                json_encode($detail, JSON_UNESCAPED_UNICODE),
                $reportText !== '' ? $reportText : null,
            ]);

            $inserted++;
        }

        if ($inserted <= 0) {
            $pdo->rollBack();
            je(['ok' => false, 'error' => 'Aucune blessure valide à enregistrer.']);
        }

        $result = recalculate_survival($pdo, $patientId);
        $pdo->commit();

        je([
            'ok' => true,
            'patient_id' => $patientId,
            'inserted' => $inserted,
            'total_penalty' => round($totalPenalty, 2),
            'patient_survival' => $result['survival'],
        ]);
    }

    if ($action === 'delete_injury') {
        if (!$isAdmin) {
            je(['ok' => false, 'error' => 'Accès refusé.']);
        }

        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            je(['ok' => false, 'error' => 'ID manquant.']);
        }

        $stmt = $pdo->prepare("SELECT patient_id FROM trauma_injuries WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $patientId = (int)($stmt->fetchColumn() ?: 0);

        $pdo->prepare("DELETE FROM trauma_injuries WHERE id = ?")->execute([$id]);

        if ($patientId > 0) {
            recalculate_survival($pdo, $patientId);
        }

        je(['ok' => true]);
    }

    if ($action === 'declare_deceased') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            je(['ok' => false, 'error' => 'ID manquant.']);
        }

        $stmt = $pdo->prepare("SELECT id FROM trauma_patients WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            je(['ok' => false, 'error' => 'Patient introuvable.']);
        }

        $pdo->prepare("UPDATE trauma_patients SET is_deceased = 1, updated_at = NOW() WHERE id = ?")
            ->execute([$id]);

        je(['ok' => true]);
    }

    je(['ok' => false, 'error' => 'Action inconnue.']);
} catch (Throwable $e) {
    je(['ok' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}

function refresh_patient_survival(PDO $pdo, array $patient): array {
    $result = recalculate_survival($pdo, (int)$patient['id']);

    $patient['survival'] = $result['survival'];
    $patient['last_injury_at'] = $result['last_injury_at'];

    return $patient;
}

function recalculate_survival(PDO $pdo, int $patientId): array {
    $stmt = $pdo->prepare("
        SELECT id, patient_id, zones, penalty, created_at, severity
        FROM trauma_injuries
        WHERE patient_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$patientId]);
    $injuries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $survival = 100.0;
    $lastInjuryAt = null;
    $previousInjuryTs = null;

    foreach ($injuries as $injury) {
        $injuryTs = strtotime((string)$injury['created_at']);
        if ($injuryTs === false) {
            continue;
        }

        if ($previousInjuryTs !== null) {
            $daysRecovered = recovery_days_between($previousInjuryTs, $injuryTs);
            if ($daysRecovered > 0) {
                $survival = min(100.0, $survival + ($daysRecovered * RECOVERY_PER_DAY));
            }
        }

        $before = $survival;

        $penalty = (float)$injury['penalty'];
        if ($penalty <= 0) {
            $penalty = compute_penalty_from_zones_and_severity((string)$injury['zones'], normalize_severity((string)($injury['severity'] ?? 'Moyenne')));
        }

        $after = max(SURVIVAL_MIN, $before - $penalty);

        $stmtUpdate = $pdo->prepare("
            UPDATE trauma_injuries
            SET penalty = ?, survival_before = ?, survival_after = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            round($penalty, 2),
            round($before, 2),
            round($after, 2),
            (int)$injury['id']
        ]);

        $survival = $after;
        $lastInjuryAt = (string)$injury['created_at'];
        $previousInjuryTs = $injuryTs;
    }

    if ($previousInjuryTs !== null) {
        $daysRecovered = recovery_days_between($previousInjuryTs, time());
        if ($daysRecovered > 0) {
            $survival = min(100.0, $survival + ($daysRecovered * RECOVERY_PER_DAY));
        }
    }

    $stmtPatient = $pdo->prepare("
        UPDATE trauma_patients
        SET survival = ?, last_injury_at = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmtPatient->execute([
        round($survival, 2),
        $lastInjuryAt,
        $patientId
    ]);

    return [
        'survival' => round($survival, 2),
        'last_injury_at' => $lastInjuryAt,
    ];
}

function recovery_days_between(int $fromTs, int $toTs): int {
    if ($toTs <= $fromTs) {
        return 0;
    }

    $elapsed = $toTs - $fromTs;

    if ($elapsed < 86400) {
        return 0;
    }

    return (int)floor($elapsed / 86400);
}

function compute_penalty_from_zones(string $zones): float {
    return compute_penalty_from_zones_and_severity($zones, 'Moyenne');
}

function compute_penalty_from_zones_and_severity(string $zones, string $severity): float {
    $penalty = 0.0;
    $severity = normalize_severity($severity);
    $severityPenalty = (float)(SEVERITY_PENALTIES[$severity] ?? 5);

    foreach (explode(',', $zones) as $zone) {
        $zone = trim($zone);
        if (isset(ZONE_PENALTIES[$zone])) {
            $penalty += (float)ZONE_PENALTIES[$zone] + $severityPenalty;
        }
    }

    return $penalty;
}

function normalize_severity(string $severity): string {
    $severity = trim($severity);
    $severity = str_replace(['Legere', 'Légere', 'legere', 'légère'], 'Légère', $severity);
    $severity = str_replace(['Moyen', 'moyenne', 'moyen'], 'Moyenne', $severity);
    $severity = str_replace(['grave'], 'Grave', $severity);

    if (!array_key_exists($severity, SEVERITY_PENALTIES)) {
        return 'Moyenne';
    }
    return $severity;
}

function normalize_generator_zone(string $part): string {
    $p = mb_strtolower(trim($part), 'UTF-8');
    $p = str_replace(['é','è','ê','ë','à','â','î','ï','ô','ù','û','ç'], ['e','e','e','e','a','a','i','i','o','u','u','c'], $p);

    if (str_contains($p, 'tete') || str_contains($p, 'cou')) return 'tete';
    if (str_contains($p, 'thorax') || str_contains($p, 'torse') || str_contains($p, 'abdomen')) return 'thorax';

    if (str_contains($p, 'epaule gauche')) return 'epaule_gauche';
    if (str_contains($p, 'epaule droite')) return 'epaule_droite';

    if (str_contains($p, 'bras gauche') || str_contains($p, 'avant-bras gauche') || str_contains($p, 'main gauche')) return 'bras_gauche';
    if (str_contains($p, 'bras droit') || str_contains($p, 'avant-bras droit') || str_contains($p, 'main droite')) return 'bras_droit';

    if (str_contains($p, 'cuisse gauche') || str_contains($p, 'jambe gauche')) return 'jambe_gauche';
    if (str_contains($p, 'cuisse droite') || str_contains($p, 'jambe droite')) return 'jambe_droite';

    return '';
}

function normalize_patient_token(string $value): string {
    $value = trim($value);
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    $value = str_replace(
        ['à','á','â','ä','ã','å','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','ö','õ','ù','ú','û','ü','ý','ÿ'],
        ['a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','y'],
        $value
    );

    return $value;
}

function find_patient_by_identity(PDO $pdo, string $lastName, string $firstName): ?array {
    $lastKey = normalize_patient_token($lastName);
    $firstKey = normalize_patient_token($firstName);

    $stmt = $pdo->prepare("
        SELECT *
        FROM trauma_patients
        WHERE LOWER(TRIM(last_name)) = LOWER(TRIM(?))
          AND LOWER(TRIM(first_name)) = LOWER(TRIM(?))
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$lastName, $firstName]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($patient) {
        return $patient;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM trauma_patients
        WHERE LOWER(last_name) LIKE ?
          AND LOWER(first_name) LIKE ?
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmt->execute([
        '%' . mb_strtolower(mb_substr(trim($lastName), 0, 3, 'UTF-8'), 'UTF-8') . '%',
        '%' . mb_strtolower(mb_substr(trim($firstName), 0, 3, 'UTF-8'), 'UTF-8') . '%'
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (
            normalize_patient_token((string)$row['last_name']) === $lastKey &&
            normalize_patient_token((string)$row['first_name']) === $firstKey
        ) {
            return $row;
        }
    }

    return null;
}

function normalize_injury_datetime(string $raw): string {
    $raw = trim($raw);

    if ($raw === '') {
        return date('Y-m-d H:i:s');
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return date('Y-m-d H:i:s');
    }

    if ($ts > time()) {
        $ts = time();
    }

    return date('Y-m-d H:i:s', $ts);
}