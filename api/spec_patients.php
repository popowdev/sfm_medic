<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../discord_roles.php';

header('Content-Type: application/json; charset=utf-8');

function je(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

start_session();
require_login();

$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

try {
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS `spec_patients` (
        `id`          INT(11)      NOT NULL AUTO_INCREMENT,
        `spec_key`    VARCHAR(40)  NOT NULL,
        `last_name`   VARCHAR(80)  NOT NULL,
        `first_name`  VARCHAR(80)  NOT NULL,
        `birth_date`  DATE         DEFAULT NULL,
        `phone`       VARCHAR(30)  DEFAULT NULL,
        `notes`       TEXT         DEFAULT NULL,
        `created_by`  INT(11)      DEFAULT NULL,
        `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_spec` (`spec_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `spec_reports` (
        `id`           INT(11)      NOT NULL AUTO_INCREMENT,
        `patient_id`   INT(11)      NOT NULL,
        `spec_key`     VARCHAR(40)  NOT NULL,
        `title`        VARCHAR(200) NOT NULL,
        `body`         MEDIUMTEXT   NOT NULL,
        `report_date`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_by`   INT(11)      DEFAULT NULL,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_patient` (`patient_id`),
        KEY `idx_spec` (`spec_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $action  = (string)($_GET['action']   ?? 'list');
        $specKey = trim((string)($_GET['spec'] ?? ''));

        if ($specKey === '' || !isset(DISCORD_ROLES[$specKey])) je(['ok'=>false,'error'=>'Spécialisation invalide.']);
        if (!user_has_specialisation($specKey) && !$isAdmin) je(['ok'=>false,'error'=>'Accès refusé.']);

        if ($action === 'list') {
            $q      = trim((string)($_GET['q'] ?? ''));
            $params = [$specKey];
            $where  = 'WHERE spec_key=?';
            if ($q !== '') { $where .= ' AND CONCAT(last_name," ",first_name) LIKE ?'; $params[] = "%{$q}%"; }
            $stmt = $pdo->prepare("SELECT * FROM spec_patients {$where} ORDER BY last_name ASC, first_name ASC");
            $stmt->execute($params);
            je(['ok'=>true, 'patients'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($action === 'get') {
            $id   = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM spec_patients WHERE id=? AND spec_key=? LIMIT 1");
            $stmt->execute([$id, $specKey]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) je(['ok'=>false,'error'=>'Patient introuvable.']);

            $stmt = $pdo->prepare("
                SELECT r.*, u.username AS author
                FROM spec_reports r
                LEFT JOIN users u ON u.id = r.created_by
                WHERE r.patient_id=? AND r.spec_key=?
                ORDER BY r.report_date DESC
            ");
            $stmt->execute([$id, $specKey]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            je(['ok'=>true, 'patient'=>$patient, 'reports'=>$reports]);
        }

        je(['ok'=>false,'error'=>'Action inconnue.']);
    }

    if ($method !== 'POST') je(['ok'=>false,'error'=>'Méthode non autorisée.']);

    $data    = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrf    = (string)($data['csrf'] ?? '');
    $specKey = trim((string)($data['spec'] ?? ''));

    if ($csrf === '' || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        je(['ok'=>false,'error'=>'CSRF invalide.']);
    }
    if ($specKey === '' || !isset(DISCORD_ROLES[$specKey])) je(['ok'=>false,'error'=>'Spécialisation invalide.']);
    if (!user_has_specialisation($specKey) && !$isAdmin) je(['ok'=>false,'error'=>'Accès refusé.']);

    $action = (string)($data['action'] ?? '');

    if ($action === 'create_patient') {
        $ln = trim((string)($data['last_name']  ?? ''));
        $fn = trim((string)($data['first_name'] ?? ''));
        if ($ln === '' || $fn === '') je(['ok'=>false,'error'=>'Nom et prénom requis.']);
        $pdo->prepare("INSERT INTO spec_patients (spec_key,last_name,first_name,birth_date,phone,notes,created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([$specKey,$ln,$fn,$data['birth_date']??null,$data['phone']??null,$data['notes']??null,$uid]);
        je(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
    }

    if ($action === 'update_patient') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) je(['ok'=>false,'error'=>'ID manquant.']);
        $pdo->prepare("UPDATE spec_patients SET last_name=?,first_name=?,birth_date=?,phone=?,notes=?,updated_at=NOW() WHERE id=? AND spec_key=?")
            ->execute([
                trim((string)($data['last_name']  ?? '')),
                trim((string)($data['first_name'] ?? '')),
                $data['birth_date'] ?? null,
                $data['phone']      ?? null,
                $data['notes']      ?? null,
                $id, $specKey
            ]);
        je(['ok'=>true]);
    }

    if ($action === 'delete_patient') {
        if (!$isAdmin) je(['ok'=>false,'error'=>'Accès refusé.']);
        $id = (int)($data['id'] ?? 0);
        $pdo->prepare("DELETE FROM spec_reports  WHERE patient_id=? AND spec_key=?")->execute([$id,$specKey]);
        $pdo->prepare("DELETE FROM spec_patients WHERE id=?          AND spec_key=?")->execute([$id,$specKey]);
        je(['ok'=>true]);
    }

    if ($action === 'add_report') {
        $pid   = (int)($data['patient_id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        $body  = trim((string)($data['body']  ?? ''));
        $date  = trim((string)($data['report_date'] ?? ''));
        if ($pid <= 0 || $title === '' || $body === '') je(['ok'=>false,'error'=>'Données incomplètes.']);
        $reportDate = ($date !== '' && strtotime($date)) ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO spec_reports (patient_id,spec_key,title,body,report_date,created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$pid,$specKey,$title,$body,$reportDate,$uid]);
        je(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
    }

    if ($action === 'update_report') {
        $id    = (int)($data['id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        $body  = trim((string)($data['body']  ?? ''));
        if ($id <= 0 || $title === '' || $body === '') je(['ok'=>false,'error'=>'Données incomplètes.']);
        $pdo->prepare("UPDATE spec_reports SET title=?,body=?,updated_at=NOW() WHERE id=? AND spec_key=?")
            ->execute([$title,$body,$id,$specKey]);
        je(['ok'=>true]);
    }

    if ($action === 'delete_report') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) je(['ok'=>false,'error'=>'ID manquant.']);
        $canDelete = $isAdmin;
        if (!$canDelete) {
            $stmt = $pdo->prepare("SELECT created_by FROM spec_reports WHERE id=? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $canDelete = $row && (int)$row['created_by'] === $uid;
        }
        if (!$canDelete) je(['ok'=>false,'error'=>'Accès refusé.']);
        $pdo->prepare("DELETE FROM spec_reports WHERE id=? AND spec_key=?")->execute([$id,$specKey]);
        je(['ok'=>true]);
    }

    je(['ok'=>false,'error'=>'Action inconnue.']);

} catch (Throwable $e) {
    je(['ok'=>false,'error'=>'Erreur serveur: '.$e->getMessage()]);
}
