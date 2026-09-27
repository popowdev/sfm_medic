<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

header('Content-Type: application/json; charset=utf-8');

function je(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

start_session();
require_login();

$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

const BANDAGE_UNIT_PRICE = 1000;
const BANDAGE_MAX_QTY    = 2;

try {
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS `bandage_sales` (
        `id`          INT(11)     NOT NULL AUTO_INCREMENT,
        `last_name`   VARCHAR(80) NOT NULL,
        `first_name`  VARCHAR(80) NOT NULL,
        `qty`         TINYINT(1)  NOT NULL DEFAULT 1,
        `unit_price`  INT(11)     NOT NULL DEFAULT 1000,
        `total`       INT(11)     NOT NULL DEFAULT 1000,
        `sale_date`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sold_by`     INT(11)     DEFAULT NULL,
        `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_buyer` (`last_name`,`first_name`),
        KEY `idx_date`  (`sale_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT b.*, u.username AS seller_name
            FROM bandage_sales b
            LEFT JOIN users u ON u.id = b.sold_by
            ORDER BY b.sale_date DESC, b.id DESC
        ");
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        je(['ok' => true, 'sales' => $sales]);
    }

    if ($method !== 'POST') je(['ok' => false, 'error' => 'Méthode non autorisée.']);

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrf = (string)($data['csrf'] ?? '');

    if ($csrf === '' || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
        je(['ok' => false, 'error' => 'CSRF invalide.']);
    }

    $action = (string)($data['action'] ?? '');

    if ($action === 'add_sale') {
        $ln  = trim((string)($data['last_name']  ?? ''));
        $fn  = trim((string)($data['first_name'] ?? ''));
        $qty = (int)($data['qty'] ?? 1);

        if ($ln === '' || $fn === '') je(['ok' => false, 'error' => 'Nom et prénom requis.']);
        if ($qty < 1 || $qty > BANDAGE_MAX_QTY) je(['ok' => false, 'error' => 'Quantité invalide (1 ou 2).']);

        $total = $qty * BANDAGE_UNIT_PRICE;

        $rawDate  = trim((string)($data['sale_date'] ?? ''));
        $saleDate = ($rawDate !== '' && strtotime($rawDate) !== false)
            ? date('Y-m-d H:i:s', strtotime($rawDate))
            : date('Y-m-d H:i:s');

        $pdo->prepare("INSERT INTO bandage_sales (last_name, first_name, qty, unit_price, total, sale_date, sold_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$ln, $fn, $qty, BANDAGE_UNIT_PRICE, $total, $saleDate, $uid]);

        je(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'total' => $total]);
    }

    if ($action === 'delete_sale') {
        if (!$isAdmin) je(['ok' => false, 'error' => 'Accès refusé.']);
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) je(['ok' => false, 'error' => 'ID manquant.']);
        $pdo->prepare("DELETE FROM bandage_sales WHERE id=?")->execute([$id]);
        je(['ok' => true]);
    }

    je(['ok' => false, 'error' => 'Action inconnue.']);

} catch (Throwable $e) {
    je(['ok' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}
