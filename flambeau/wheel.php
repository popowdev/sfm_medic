<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json; charset=utf-8');

$FILE = __DIR__ . '/wheel.json';

function defaults() {
  return array(
    array('label'=>'Jelly Belly','color'=>'#ffffff'),
    array('label'=>'NERFF','color'=>'#f5b800'),
    array('label'=>'Claque doigts','color'=>'#8ef0c8'),
    array('label'=>'Enfumage','color'=>'#d9d9d9'),
    array('label'=>'Panini','color'=>'#8a2be2'),
    array('label'=>'Piment','color'=>'#ff2b2b'),
  );
}
function load_state($FILE) {
  if (is_file($FILE)) {
    $j = json_decode(@file_get_contents($FILE), true);
    if (is_array($j) && isset($j['config']) && is_array($j['config'])) return $j;
  }
  return array('config'=>defaults(), 'spin'=>array('id'=>0,'index'=>0,'ts'=>0));
}
function store_state($FILE, $s) { @file_put_contents($FILE, json_encode($s), LOCK_EX); }

$state = load_state($FILE);
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

if ($action === 'spin') {
  $n = count($state['config']);
  if ($n < 1) { echo json_encode(array('ok'=>false)); exit; }
  $idx = isset($_GET['index']) ? intval($_GET['index']) : random_int(0, $n - 1);
  if ($idx < 0 || $idx >= $n) $idx = random_int(0, $n - 1);
  $id = (isset($state['spin']['id']) ? intval($state['spin']['id']) : 0) + 1;
  $state['spin'] = array('id'=>$id, 'index'=>$idx, 'ts'=>time());
  store_state($FILE, $state);
  echo json_encode(array('ok'=>true, 'spin'=>$state['spin'], 'label'=>$state['config'][$idx]['label']));
  exit;
}

if ($action === 'config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $cfg = json_decode($raw, true);
  $clean = array();
  if (is_array($cfg)) {
    foreach ($cfg as $seg) {
      if (!is_array($seg)) continue;
      $label = isset($seg['label']) ? trim($seg['label']) : '';
      $label = mb_substr($label, 0, 40);
      $color = isset($seg['color']) ? trim($seg['color']) : '#cccccc';
      if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#cccccc';
      if ($label !== '') $clean[] = array('label'=>$label, 'color'=>$color);
    }
  }
  if (count($clean) >= 2) {
    $state['config'] = $clean;
    store_state($FILE, $state);
    echo json_encode(array('ok'=>true, 'config'=>$clean));
  } else {
    echo json_encode(array('ok'=>false, 'error'=>'need at least 2 segments'));
  }
  exit;
}

echo json_encode($state);
