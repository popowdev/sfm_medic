<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
function je(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

start_session();
require_login();

$patientId = (int)($_POST['patient_id'] ?? 0);
if ($patientId <= 0) je(['ok'=>false,'error'=>'patient_id manquant.']);

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    je(['ok'=>false,'error'=>'Fichier invalide.']);
}

$file = $_FILES['photo'];
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
    je(['ok'=>false,'error'=>'Format non autorisé (jpg/png/webp).']);
}

$ext     = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
$dir     = __DIR__ . '/../uploads/trauma/';
if (!is_dir($dir)) mkdir($dir, 0775, true);

$filename = 'patient_' . $patientId . '_' . time() . '.' . $ext;
$dest     = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    je(['ok'=>false,'error'=>'Erreur lors de la sauvegarde.']);
}

$path = '/uploads/trauma/' . $filename;
db()->prepare("UPDATE trauma_patients SET photo_path=?, updated_at=NOW() WHERE id=?")
    ->execute([$path, $patientId]);

je(['ok'=>true, 'path'=>$path]);
