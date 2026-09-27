<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function rdv_h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function rdv_categories(): array {
  return [
    'Visite médicale' => '📄 Visites médicales d\'Entreprise',
  ];
}

function rdv_statuses(): array {
  return [
    'pending' => 'En attente',
    'need_info' => 'Besoin d\'infos',
    'accepted' => 'Accepté',
    'refused' => 'Refusé',
    'done' => 'Terminé',
  ];
}

function rdv_valid_color(?string $color): string {
  $color = trim((string)$color);
  return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#3b9eff';
}

function rdv_current_staff(): array {
  $u = current_user() ?: [];
  return [
    'id' => isset($u['id']) ? (int)$u['id'] : null,
    'name' => trim((string)($u['username'] ?? 'SAMR')) ?: 'SAMR',
    'color' => rdv_valid_color((string)($u['calendar_color'] ?? '#3b9eff')),
  ];
}

function rdv_column_exists(PDO $pdo, string $table, string $column): bool {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
  $stmt->execute([$table, $column]);
  return (int)$stmt->fetchColumn() > 0;
}

function rdv_add_column(PDO $pdo, string $table, string $column, string $definition): void {
  if (!rdv_column_exists($pdo, $table, $column)) {
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
  }
}

function rdv_ensure_schema(): void {
  static $done = false;
  if ($done) return;
  $done = true;

  $pdo = db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS `rdv_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category` VARCHAR(120) NOT NULL,
    `rp_name` VARCHAR(120) NOT NULL,
    `discord_name` VARCHAR(120) NOT NULL,
    `rp_phone` VARCHAR(60) DEFAULT NULL,
    `availability` TEXT DEFAULT NULL,
    `message` TEXT NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_created` (`status`, `created_at`),
    KEY `idx_category` (`category`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  rdv_add_column($pdo, 'rdv_requests', 'assigned_user_id', 'INT UNSIGNED DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'assigned_name', 'VARCHAR(120) DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'assigned_color', 'VARCHAR(7) DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'status_updated_by_id', 'INT UNSIGNED DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'status_updated_by_name', 'VARCHAR(120) DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'status_updated_by_color', 'VARCHAR(7) DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'status_updated_at', 'DATETIME DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'appointment_id', 'INT UNSIGNED DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'scheduled_start_at', 'DATETIME DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'scheduled_end_at', 'DATETIME DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'scheduled_by_id', 'INT UNSIGNED DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'scheduled_by_name', 'VARCHAR(120) DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_requests', 'scheduled_by_color', 'VARCHAR(7) DEFAULT NULL');

  $pdo->exec("CREATE TABLE IF NOT EXISTS `rdv_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` INT UNSIGNED NOT NULL,
    `author_type` ENUM('player','staff') NOT NULL,
    `author_name` VARCHAR(120) NOT NULL,
    `body` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_request_created` (`request_id`, `created_at`),
    CONSTRAINT `fk_rdv_messages_request` FOREIGN KEY (`request_id`) REFERENCES `rdv_requests` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  rdv_add_column($pdo, 'rdv_messages', 'author_user_id', 'INT UNSIGNED DEFAULT NULL');
  rdv_add_column($pdo, 'rdv_messages', 'author_color', 'VARCHAR(7) DEFAULT NULL');

  $pdo->exec("CREATE TABLE IF NOT EXISTS `rdv_actions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` INT UNSIGNED NOT NULL,
    `actor_type` ENUM('player','staff','system') NOT NULL DEFAULT 'staff',
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `actor_name` VARCHAR(120) NOT NULL,
    `actor_color` VARCHAR(7) DEFAULT NULL,
    `action` VARCHAR(60) NOT NULL,
    `old_value` VARCHAR(120) DEFAULT NULL,
    `new_value` VARCHAR(120) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_request_created` (`request_id`, `created_at`),
    CONSTRAINT `fk_rdv_actions_request` FOREIGN KEY (`request_id`) REFERENCES `rdv_requests` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function rdv_url(int $id): string {
  return '/rdv/' . $id;
}

function rdv_status_label(string $status): string {
  $statuses = rdv_statuses();
  return $statuses[$status] ?? $status;
}

function rdv_action_label(string $action, ?string $oldValue, ?string $newValue): string {
  if ($action === 'status') {
    return 'Statut : ' . rdv_status_label((string)$oldValue) . ' → ' . rdv_status_label((string)$newValue);
  }
  if ($action === 'reply') return 'Réponse envoyée';
  if ($action === 'schedule') return 'RDV planifié dans le calendrier';
  if ($action === 'delete') return 'Demande supprimée';
  if ($action === 'cleanup') return 'Demande supprimée automatiquement après le rendez-vous';
  if ($action === 'create') return 'Demande créée';
  return $action;
}

function rdv_find(int $id): ?array {
  rdv_ensure_schema();
  $stmt = db()->prepare('SELECT * FROM rdv_requests WHERE id = ? LIMIT 1');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function rdv_messages(int $id): array {
  rdv_ensure_schema();
  $stmt = db()->prepare('SELECT * FROM rdv_messages WHERE request_id = ? ORDER BY created_at ASC, id ASC');
  $stmt->execute([$id]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function rdv_actions(int $id): array {
  rdv_ensure_schema();
  $stmt = db()->prepare('SELECT * FROM rdv_actions WHERE request_id = ? ORDER BY created_at ASC, id ASC');
  $stmt->execute([$id]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function rdv_log_action(int $requestId, string $action, ?string $oldValue, ?string $newValue, ?string $note = null, ?array $staff = null, string $actorType = 'staff'): void {
  rdv_ensure_schema();
  $staff = $staff ?: rdv_current_staff();
  $stmt = db()->prepare('INSERT INTO rdv_actions (request_id, actor_type, actor_user_id, actor_name, actor_color, action, old_value, new_value, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
  $stmt->execute([
    $requestId,
    $actorType,
    $staff['id'] ?? null,
    (string)($staff['name'] ?? 'SAMR'),
    rdv_valid_color((string)($staff['color'] ?? '#3b9eff')),
    $action,
    $oldValue,
    $newValue,
    $note,
  ]);
}

function rdv_request_color(array $r): string {
  return rdv_valid_color((string)($r['assigned_color'] ?: ($r['status_updated_by_color'] ?: '#3b9eff')));
}

function rdv_cleanup_expired(): void {
  rdv_ensure_schema();
  $pdo = db();

  $stmt = $pdo->prepare("DELETE FROM rdv_requests WHERE status = 'accepted' AND scheduled_start_at IS NOT NULL AND scheduled_start_at < NOW()");
  $stmt->execute();
}

function rdv_datetime_local_to_mysql(string $value): string {
  $value = trim($value);
  if ($value === '') throw new RuntimeException('Date et heure requises.');
  try {
    $dt = new DateTimeImmutable($value, new DateTimeZone('Europe/Paris'));
  } catch (Throwable $e) {
    throw new RuntimeException('Date et heure invalides.');
  }
  return $dt->format('Y-m-d H:i:s');
}

function rdv_mysql_to_datetime_local(?string $value): string {
  if (!$value) return '';
  try {
    $dt = new DateTimeImmutable($value, new DateTimeZone('Europe/Paris'));
  } catch (Throwable $e) {
    return '';
  }
  return $dt->format('Y-m-d\TH:i');
}

function rdv_create_calendar_appointment(array $request, string $startMysql, int $durationMinutes, array $staff): int {
  rdv_ensure_schema();
  $pdo = db();

  $durationMinutes = max(15, min(240, $durationMinutes));
  $start = new DateTimeImmutable($startMysql, new DateTimeZone('Europe/Paris'));
  $end = $start->modify('+' . $durationMinutes . ' minutes');
  $endMysql = $end->format('Y-m-d H:i:s');

  $patient = trim((string)$request['rp_name'] . ' ' . (string)$request['discord_name']);
  $category = (string)$request['category'];
  $reason = trim($category . ' — ' . (string)$request['message']);
  $creatorName = trim((string)($staff['name'] ?? 'SAMR')) ?: 'SAMR';
  $creatorColor = rdv_valid_color((string)($staff['color'] ?? '#3b9eff'));
  $uid = (int)($staff['id'] ?? 0);

  $notes = json_encode([
    'patient_name' => $patient,
    'reason' => $reason,
    'rdv_request_id' => (int)$request['id'],
    'telephone' => (string)($request['rp_phone'] ?? ''),
    'availability' => (string)($request['availability'] ?? ''),
  ], JSON_UNESCAPED_UNICODE);

  $stmt = $pdo->prepare("INSERT INTO appointments (user_id, title, start_at, end_at, created_by, created_name, created_color, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$uid, $creatorName, $startMysql, $endMysql, $uid, $creatorName, $creatorColor, $notes]);

  return (int)$pdo->lastInsertId();
}
