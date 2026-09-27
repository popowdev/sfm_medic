<?php

declare(strict_types=1);
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!is_logged_in()) {
  header('Location: /login.php');
  exit;
}

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pick(array $arr) { return $arr[array_rand($arr)]; }
function post_array(string $key): array {
  $v = $_POST[$key] ?? [];
  return is_array($v) ? $v : [];
}

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS gynecology_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  patient_name VARCHAR(190) NOT NULL,
  patient_identifier VARCHAR(190) DEFAULT NULL,
  exam_type VARCHAR(80) NOT NULL,
  payload LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_patient_name (patient_name),
  INDEX idx_exam_type (exam_type),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (isset($_POST['action']) && $_POST['action'] === 'save_png') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $dataUrl = (string)($_POST['png_data'] ?? '');
    if ($dataUrl === '' || !preg_match('#^data:image/png;base64,#', $dataUrl)) {
      throw new RuntimeException('Image PNG invalide.');
    }
    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bin === false) throw new RuntimeException('Décodage PNG impossible.');

    $baseDir = __DIR__ . '/uploads/gyneco';
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
      throw new RuntimeException('Impossible de créer le dossier /uploads/gyneco.');
    }
    if (!is_writable($baseDir)) {
      throw new RuntimeException('Le dossier /uploads/gyneco n’est pas accessible en écriture.');
    }

    $slug = preg_replace('/[^a-z0-9\-_]+/i', '-', strtolower((string)($_POST['patient_slug'] ?? 'patiente')));
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'patiente';
    $filename = 'gyneco-' . $slug . '-' . date('Ymd-His') . '.png';
    $path = $baseDir . '/' . $filename;
    if (file_put_contents($path, $bin) === false) {
      throw new RuntimeException('Enregistrement du PNG impossible.');
    }

    echo json_encode([
      'ok' => true,
      'file' => '/uploads/gyneco/' . $filename,
      'message' => 'PNG exporté dans /uploads/gyneco',
    ], JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

function build_blood_profile(array $in): array {
  $abo = (string)($in['abo'] ?? 'auto');
  $rh = (string)($in['rh'] ?? 'auto');
  $panel = (string)($in['blood_panel'] ?? 'prenatal');
  $profile = (string)($in['anomaly_profile'] ?? 'normal');
  $weeks = max(4, min(41, (int)($in['sa'] ?? 12)));

  if ($abo === 'auto') $abo = pick(['A','B','AB','O']);
  if ($rh === 'auto') $rh = pick(['Rh+','Rh+','Rh+','Rh-']);

  $vals = [
    'hb' => random_int(116, 132) / 10,
    'hct' => random_int(340, 395) / 10,
    'rbc' => random_int(360, 440) / 100,
    'mcv' => random_int(82, 95),
    'plt' => random_int(160, 300),
    'wbc' => random_int(55, 120) / 10,
    'ferritin' => random_int(18, 70),
    'folates' => random_int(6, 15),
    'b12' => random_int(230, 560),
    'vitd' => random_int(24, 46),
    'glucose' => random_int(70, 92),
    'hba1c' => random_int(47, 54) / 10,
    'proteinuria' => 'négative',
    'crp' => random_int(1, 8),
    'alt' => random_int(12, 28),
    'ast' => random_int(14, 28),
    'bile' => random_int(2, 9),
    'rai' => 'négative',
    'rubella' => 'immunisée',
    'toxoplasma' => pick(['négative','immunisée']),
    'hbv' => 'négatif',
    'hiv' => 'négatif',
    'syphilis' => 'négatif',
    'gbs' => ($weeks >= 35 ? pick(['négatif','négatif','positif']) : 'non réalisé'),
    'coombs_direct' => 'négatif',
    'bilirubin_cord' => random_int(8, 18),
    'kleihauer' => 'négatif',
  ];

  $issues = [];
  $solutions = [];
  $alerts = [];

  switch ($profile) {
    case 'iron_deficiency':
      $vals['hb'] = random_int(85, 105) / 10;
      $vals['hct'] = random_int(280, 330) / 10;
      $vals['mcv'] = random_int(68, 77);
      $vals['ferritin'] = random_int(4, 11);
      $issues[] = 'Anémie ferriprive compatible avec une carence martiale gravidique.';
      $solutions[] = 'Complémentation orale en fer, hygiène alimentaire enrichie en fer, contrôle NFS/ferritine dans 2 à 4 semaines.';
      $alerts[] = 'Fatigue, dyspnée d’effort, pâleur possibles.';
      break;
    case 'folate_deficiency':
      $vals['hb'] = random_int(92, 108) / 10;
      $vals['mcv'] = random_int(99, 108);
      $vals['folates'] = random_int(2, 4);
      $issues[] = 'Carence en folates probable avec tendance macrocytaire.';
      $solutions[] = 'Supplémentation en acide folique et réévaluation biologique au contrôle.';
      $alerts[] = 'Renforcer la prévention périconceptionnelle et le suivi nutritionnel.';
      break;
    case 'b12_deficiency':
      $vals['hb'] = random_int(88, 106) / 10;
      $vals['mcv'] = random_int(101, 114);
      $vals['b12'] = random_int(90, 165);
      $issues[] = 'Carence en vitamine B12 possible.';
      $solutions[] = 'Supplémentation en vitamine B12, recherche d’apports insuffisants ou facteur d’absorption.';
      $alerts[] = 'Paresthésies et fatigue.';
      break;
    case 'vitd_deficiency':
      $vals['vitd'] = random_int(8, 18);
      $issues[] = 'Carence en vitamine D.';
      $solutions[] = 'Supplémentation en vitamine D et surveillance clinique.';
      $alerts[] = 'Douleurs diffuses ou faiblesse musculaire possibles.';
      break;
    case 'gestational_diabetes':
      $vals['glucose'] = random_int(96, 124);
      $vals['hba1c'] = random_int(56, 65) / 10;
      $issues[] = 'Profil compatible avec un diabète gestationnel.';
      $solutions[] = 'Régime adapté, autosurveillance glycémique, avis spécialisé si besoin d’insulinothérapie.';
      $alerts[] = 'Contrôler croissance fœtale et liquide amniotique.';
      break;
    case 'preeclampsia':
      $vals['proteinuria'] = pick(['+','++','+++']);
      $vals['plt'] = random_int(95, 145);
      $vals['alt'] = random_int(35, 88);
      $vals['ast'] = random_int(38, 90);
      $vals['crp'] = random_int(6, 18);
      $issues[] = 'Anomalies compatibles avec une pré-éclampsie / forme débutante sévère selon le contexte clinique.';
      $solutions[] = 'Surveillance tensionnelle stricte, protéinurie, bilan répété, avis obstétrical urgent, repos et prise en charge hospitalière RP selon gravité.';
      $alerts[] = 'Céphalées, phosphènes, œdèmes, douleur épigastrique à surveiller.';
      break;
    case 'cholestasis':
      $vals['bile'] = random_int(18, 46);
      $vals['alt'] = random_int(45, 120);
      $vals['ast'] = random_int(40, 110);
      $issues[] = 'Bilan compatible avec une cholestase gravidique.';
      $solutions[] = 'Surveillance hépatique, traitement symptomatique, avis obstétrical et suivi rapproché.';
      $alerts[] = 'Prurit gravidique sans lésions associé possible.';
      break;
    case 'rh_incompatibility':
      $rh = 'Rh-';
      $vals['rai'] = pick(['positive faible','positive','positive']);
      $vals['coombs_direct'] = ($panel === 'natal' ? pick(['positif','positif faible']) : 'non réalisé');
      $issues[] = 'Contexte d’allo-immunisation / incompatibilité Rh à considérer.';
      $solutions[] = 'Surveillance immuno-hématologique, prévention anti-D selon contexte, suivi obstétrical renforcé.';
      $alerts[] = 'Rechercher antécédent transfusionnel ou obstétrical.';
      break;
    case 'infection':
      $vals['wbc'] = random_int(125, 190) / 10;
      $vals['crp'] = random_int(18, 90);
      $issues[] = 'Syndrome inflammatoire / infectieux biologique.';
      $solutions[] = 'Bilan clinique, ECBU/hémocultures selon contexte, antibiothérapie adaptée à la suspicion.';
      $alerts[] = 'Fièvre, douleurs pelviennes, brûlures urinaires ou pertes anormales à corréler.';
      break;
    case 'mixed':
      $vals['hb'] = random_int(88, 103) / 10;
      $vals['mcv'] = random_int(72, 79);
      $vals['ferritin'] = random_int(4, 10);
      $vals['vitd'] = random_int(8, 16);
      $vals['crp'] = random_int(8, 22);
      $issues[] = 'Profil mixte avec carence martiale, hypovitaminose D et inflammation modérée.';
      $solutions[] = 'Fer oral, vitamine D, adaptation alimentaire, contrôle biologique rapproché.';
      $alerts[] = 'Penser à associer surveillance obstétricale habituelle.';
      break;
    default:
      $issues[] = 'Bilan prénatal / natal sans anomalie biologique majeure sur cette simulation.';
      $solutions[] = 'Suivi obstétrical habituel et supplémentation standard selon le contexte.';
      break;
  }

  if ($panel === 'natal') {
    $issues[] = 'Volet natal inclus : sang de cordon, Coombs direct, bilirubine et Kleihauer si nécessaire.';
    if ($vals['coombs_direct'] === 'non réalisé') $vals['coombs_direct'] = pick(['négatif','négatif','positif faible']);
  }

  return compact('abo','rh','panel','profile','vals','issues','solutions','alerts');
}

function build_ultrasound_report(array $in): array {
  $sa = max(4, min(41, (int)($in['sa'] ?? 12)));
  $days = max(0, min(6, (int)($in['days'] ?? 0)));
  $exam = (string)($in['exam'] ?? 'obstetricale');
  $view = (string)($in['view'] ?? 'profil');
  $quality = (string)($in['quality'] ?? 'bonne');
  $fetuses = max(1, min(2, (int)($in['fetuses'] ?? 1)));
  $presentation = (string)($in['presentation'] ?? 'variable');
  $placenta = (string)($in['placenta'] ?? 'postérieur');
  $fluid = (string)($in['fluid'] ?? 'normal');
  $sex = (string)($in['sex'] ?? 'indéterminé');
  $anomalies = post_array('us_anomalies');

  $term = $sa + ($days / 7);
  $trimester = $term < 14 ? 1 : ($term < 28 ? 2 : 3);

  $bpd = null; $hc = null; $ac = null; $fl = null; $fhr = null; $efw = null; $crl = null; $gs = null;
  if ($term < 11) {
    $gs = (int)round(6 + $term * 2.5 + random_int(-2, 2));
    $crl = (int)round(max(2, ($term - 5) * 7 + random_int(-3, 3)));
    $fhr = $term >= 6 ? (int)round(115 + ($term - 6) * 6 + random_int(-8, 8)) : null;
  } elseif ($term < 15) {
    $crl = (int)round(45 + ($term - 11) * 11 + random_int(-5, 5));
    $bpd = (int)round(18 + ($term - 11) * 4 + random_int(-2, 2));
    $fhr = (int)round(150 + random_int(-10, 10));
  } else {
    $bpd = (int)round(24 + ($term - 15) * 2.15 + random_int(-3, 3));
    $hc  = (int)round(110 + ($term - 15) * 8.1 + random_int(-10, 10));
    $ac  = (int)round(90 + ($term - 15) * 9.2 + random_int(-12, 12));
    $fl  = (int)round(12 + ($term - 15) * 1.85 + random_int(-2, 2));
    $fhr = (int)round(($term > 30 ? 138 : 145) + random_int(-9, 9));
    $efw = (int)round(max(120, 100 + pow(max(1, $term - 13), 2.08) * 4.9 + random_int(-120, 120)));
  }

  $interpret = [];
  $advice = [];
  $warnings = [];

  $interpret[] = "Grossesse " . ($fetuses === 2 ? 'gémellaire' : 'singleton') . " estimée à {$sa} SA + {$days} j.";
  if ($fhr !== null) $interpret[] = "Activité cardiaque fœtale visualisée à {$fhr} bpm.";
  $interpret[] = "Placenta {$placenta}. Liquide amniotique {$fluid}.";
  if ($presentation !== 'variable' && $trimester >= 3) $interpret[] = "Présentation {$presentation}.";

  foreach ($anomalies as $a) {
    switch ($a) {
      case 'decollement':
        $warnings[] = 'Petit décollement sous-chorial visible.';
        $advice[] = 'Repos relatif, contrôle échographique rapproché, surveillance des métrorragies.';
        break;
      case 'placenta_bas':
        $warnings[] = 'Placenta bas inséré / prævia partiel évoqué.';
        $advice[] = 'Contrôle obstétrical, éviter les efforts intenses, consignes de consultation en cas de saignement.';
        break;
      case 'rciu':
        $warnings[] = 'Biométrie légèrement inférieure aux attentes pour le terme.';
        $advice[] = 'Doppler, surveillance croissance, contrôle rapproché.';
        if ($efw) $efw = (int)round($efw * 0.82);
        break;
      case 'oligoamnios':
        $warnings[] = 'Liquide amniotique diminué.';
        $advice[] = 'Hydratation, surveillance clinique et échographique, avis obstétrical.';
        $fluid = 'diminué';
        break;
      case 'polyhydramnios':
        $warnings[] = 'Liquide amniotique augmenté.';
        $advice[] = 'Surveillance obstétricale et recherche de contexte associé.';
        $fluid = 'augmenté';
        break;
      case 'absence_acf':
        $warnings[] = 'Absence d’activité cardiaque fœtale visualisée sur cet examen.';
        $advice[] = 'Recontrôle immédiat, confirmation spécialisée, prise en charge obstétricale.';
        $fhr = null;
        break;
      case 'hematome':
        $warnings[] = 'Image compatible avec hématome péri-gestationnel.';
        $advice[] = 'Surveillance clinique, repos relatif, contrôle ultérieur selon contexte.';
        break;
      case 'kyste_ovarien':
        $warnings[] = 'Kyste ovarien fonctionnel associé.';
        $advice[] = 'Surveillance simple si asymptomatique.';
        break;
    }
  }

  if (!$warnings) {
    $warnings[] = 'Pas d’anomalie évidente sur ce rendu.';
    $advice[] = 'Suivi obstétrical habituel.';
  }

  $desc = [];
  if ($trimester === 1) {
    $desc[] = 'Sac gestationnel et embryon/fœtus visualisés dans des limites compatibles avec un début de grossesse.';
  } elseif ($trimester === 2) {
    $desc[] = 'Morphologie fœtale identifiable avec coupes céphaliques et tronc visibles.';
  } else {
    $desc[] = 'Fœtus volumineux occupant la majeure partie du champ, vues segmentaires serrées et plus crédibles en fin de terme.';
  }
  if ($view === 'profil') $desc[] = 'Coupe de profil privilégiée.';
  if ($view === 'tete') $desc[] = 'Coupe céphalique prédominante.';
  if ($view === 'thorax') $desc[] = 'Vue thoraco-abdominale prédominante.';
  if ($view === 'bassin') $desc[] = 'Exploration pelvienne complémentaire.';

  $title = match($exam) {
    'morphologique' => 'Échographie morphologique obstétricale',
    'doppler' => 'Échographie obstétricale avec Doppler',
    'pelvienne' => 'Échographie pelvienne',
    'endovaginale' => 'Échographie endovaginale',
    default => 'Échographie obstétricale',
  };

  return compact('sa','days','exam','view','quality','fetuses','presentation','placenta','fluid','sex','anomalies','term','trimester','bpd','hc','ac','fl','fhr','efw','crl','gs','interpret','advice','warnings','desc','title');
}

function render_svg(array $r): string {
  $trimester  = $r['trimester'];
  $fetuses    = $r['fetuses'];
  $view       = $r['view'];
  $quality    = $r['quality'];
  $anomalies  = $r['anomalies'];
  $sa         = (int)$r['sa'];
  $days       = (int)$r['days'];

  $speckleFreq = match($quality) {
    'excellente' => '0.62 0.58',
    'moyenne'    => '1.05 0.98',
    'difficile'  => '1.4 1.3',
    default      => '0.82 0.76',
  };
  $speckleOp  = match($quality) { 'excellente'=>'0.55','moyenne'=>'0.80','difficile'=>'0.95', default=>'0.68' };
  $speckleAmp = match($quality) { 'excellente'=>'12',  'moyenne'=>'22',  'difficile'=>'32',   default=>'18'  };
  $blurStd   = match($quality) { 'excellente'=>'0.5', 'moyenne'=>'1.4', 'difficile'=>'2.2',  default=>'0.9'  };
  $noiseAmp  = match($quality) { 'excellente'=>'0.06','moyenne'=>'0.14','difficile'=>'0.20', default=>'0.10' };
  $edgeOp    = match($quality) { 'excellente'=>'0.92','moyenne'=>'0.72','difficile'=>'0.50', default=>'0.82' };

  $seed = ($sa * 7 + $days * 3 + $trimester * 11 + $fetuses * 5) % 9973;

  $apexX  = 450;
  $apexY  = 88;
  $fanR   = 500;
  $fanAng = 68;
  $rad    = M_PI / 180;
  $leftX  = (int)round($apexX + $fanR * sin(-$fanAng * $rad));
  $leftY  = (int)round($apexY + $fanR * cos(-$fanAng * $rad));
  $rightX = (int)round($apexX + $fanR * sin($fanAng * $rad));
  $rightY = (int)round($apexY + $fanR * cos($fanAng * $rad));

  $fanPath = "M {$apexX} {$apexY} L {$leftX} {$leftY} A {$fanR} {$fanR} 0 0 1 {$rightX} {$rightY} Z";

  $scanLines = '';
  for ($y = 100; $y < 590; $y += 3) {
    $op = (($y % 6 === 0) ? 0.035 : 0.012);
    $scanLines .= "<line x1='50' y1='{$y}' x2='850' y2='{$y}' stroke='#7abfdf' stroke-opacity='{$op}' stroke-width='0.8'/>";
  }

  $depthCm   = ($trimester === 1) ? 12 : (($trimester === 2) ? 16 : 20);
  $depthScale = 440 / $depthCm;
  $depthMarks = '';
  for ($cm = 1; $cm <= $depthCm; $cm++) {
    $dy = (int)round(105 + $cm * $depthScale);
    if ($dy > 570) break;
    $len  = ($cm % 5 === 0) ? 22 : (($cm % 2 === 0) ? 14 : 8);
    $depthMarks .= "<line x1='55' y1='{$dy}' x2='" . (55 + $len) . "' y2='{$dy}' stroke='#8ecfec' stroke-opacity='0.55' stroke-width='1'/>";
    if ($cm % 5 === 0) {
      $depthMarks .= "<text x='82' y='" . ($dy + 5) . "' fill='#8ecfec' opacity='0.65' font-size='13' font-family='monospace'>{$cm}cm</text>";
    }
  }

  $anatomy = '';

  if ($trimester === 1) {

    $anatomy .= '<path d="M230 160 C280 110, 620 108, 672 162 C730 230, 740 380, 690 460 C640 540, 320 544, 260 462 C198 376, 196 230, 230 160 Z" fill="url(#uterusWall)" opacity="0.45"/>';

    $anatomy .= '<path d="M260 175 C305 132, 600 130, 646 176 C700 240, 708 368, 662 438 C618 512, 302 516, 248 437 C200 362, 204 240, 260 175 Z" fill="#01020a" opacity="0.88"/>';

    $anatomy .= '<ellipse cx="445" cy="310" rx="148" ry="118" fill="url(#sacDark)" opacity="0.96"/>';

    $anatomy .= '<ellipse cx="445" cy="310" rx="148" ry="118" fill="none" stroke="url(#trophGrad)" stroke-width="10" opacity="0.82"/>';

    $anatomy .= '<circle cx="480" cy="275" r="14" fill="#000305" opacity="0.95"/>';
    $anatomy .= '<circle cx="480" cy="275" r="14" fill="none" stroke="#b8d4e8" stroke-width="3" opacity="0.72"/>';

    $anatomy .= '<path d="M415 290 C428 278, 450 274, 466 280 C480 285, 488 298, 488 312 C488 326, 480 338, 464 342 C444 347, 424 340, 413 326 C404 314, 403 302, 415 290 Z" fill="url(#embryoGrad)" opacity="0.90"/>';

    $anatomy .= '<path d="M428 293 C445 282, 462 285, 474 298 C480 306, 478 318, 468 326" stroke="#d8e8f2" stroke-width="2.5" stroke-linecap="round" opacity="0.70" fill="none"/>';

    $anatomy .= '<path d="M297 310 L200 590 L297 590 Z" fill="#000204" opacity="0.25"/>';
    $anatomy .= '<path d="M593 310 L700 590 L590 590 Z" fill="#000204" opacity="0.25"/>';

    $anatomy .= '<ellipse cx="445" cy="500" rx="100" ry="30" fill="#0a1a28" opacity="0.60"/>';

    $anatomy .= '<path d="M340 390 C385 405, 505 405, 550 390" stroke="#9bbdd4" stroke-width="6" stroke-linecap="round" opacity="0.38" fill="none"/>';

  } elseif ($trimester === 2) {
    if ($view === 'tete') {

      $anatomy .= '<ellipse cx="448" cy="300" rx="188" ry="162" fill="url(#boneRing)" opacity="0.78"/>';

      $anatomy .= '<ellipse cx="448" cy="300" rx="188" ry="162" fill="none" stroke="#c8dce8" stroke-width="7" opacity="' . $edgeOp . '"/>';

      $anatomy .= '<ellipse cx="448" cy="300" rx="177" ry="151" fill="#01030b" opacity="0.96"/>';

      $anatomy .= '<line x1="448" y1="155" x2="448" y2="445" stroke="#8db8d0" stroke-width="2.5" opacity="0.55"/>';

      $anatomy .= '<ellipse cx="414" cy="300" rx="40" ry="28" fill="#1e3a50" opacity="0.60"/>';
      $anatomy .= '<ellipse cx="482" cy="300" rx="40" ry="28" fill="#1e3a50" opacity="0.60"/>';

      $anatomy .= '<rect x="428" y="260" width="40" height="18" rx="4" fill="#0a2436" opacity="0.70"/>';
      $anatomy .= '<rect x="428" y="260" width="40" height="18" rx="4" fill="none" stroke="#5a9ab8" stroke-width="1.5" opacity="0.50"/>';

      $anatomy .= '<ellipse cx="390" cy="310" rx="22" ry="14" fill="#3a6882" opacity="0.55"/>';
      $anatomy .= '<ellipse cx="508" cy="310" rx="22" ry="14" fill="#3a6882" opacity="0.55"/>';

      $anatomy .= '<path d="M266 200 L190 420 L266 460 Z" fill="#000308" opacity="0.40"/>';
      $anatomy .= '<path d="M630 200 L710 420 L630 460 Z" fill="#000308" opacity="0.40"/>';

      $anatomy .= '<line x1="262" y1="300" x2="632" y2="300" stroke="#ffff00" stroke-width="1.2" stroke-dasharray="5 4" opacity="0.70"/>';
      $anatomy .= '<line x1="262" y1="290" x2="262" y2="310" stroke="#ffff00" stroke-width="2" opacity="0.80"/>';
      $anatomy .= '<line x1="632" y1="290" x2="632" y2="310" stroke="#ffff00" stroke-width="2" opacity="0.80"/>';
      $bpd = $r['bpd'] ?? 54;
      $anatomy .= '<text x="420" y="290" fill="#ffff00" opacity="0.85" font-size="15" font-family="monospace">BIP ' . (int)$bpd . 'mm</text>';

    } elseif ($view === 'thorax') {

      $anatomy .= '<ellipse cx="448" cy="295" rx="175" ry="150" fill="url(#tissueGrad)" opacity="0.55"/>';
      $anatomy .= '<ellipse cx="448" cy="295" rx="175" ry="150" fill="none" stroke="#b0cede" stroke-width="5" opacity="' . $edgeOp . '"/>';

      $anatomy .= '<ellipse cx="390" cy="285" rx="68" ry="56" fill="#1a3a52" opacity="0.70"/>';
      $anatomy .= '<ellipse cx="510" cy="285" rx="68" ry="56" fill="#1a3a52" opacity="0.70"/>';

      $anatomy .= '<ellipse cx="430" cy="300" rx="58" ry="50" fill="#010a14" opacity="0.92"/>';
      $anatomy .= '<ellipse cx="430" cy="300" rx="58" ry="50" fill="none" stroke="#5a9ab8" stroke-width="2" opacity="0.45"/>';

      $anatomy .= '<ellipse cx="415" cy="290" rx="22" ry="18" fill="#020d18" opacity="0.85"/>';
      $anatomy .= '<ellipse cx="448" cy="290" rx="22" ry="18" fill="#020d18" opacity="0.85"/>';
      $anatomy .= '<ellipse cx="415" cy="315" rx="22" ry="18" fill="#020d18" opacity="0.85"/>';
      $anatomy .= '<ellipse cx="448" cy="315" rx="22" ry="18" fill="#020d18" opacity="0.85"/>';

      $anatomy .= '<line x1="431" y1="272" x2="431" y2="333" stroke="#7abed8" stroke-width="2" opacity="0.55"/>';

      $anatomy .= '<ellipse cx="448" cy="380" rx="22" ry="14" fill="#c8dce8" opacity="0.70"/>';
      $anatomy .= '<path d="M426 394 L380 580 L516 580 L470 394 Z" fill="#000306" opacity="0.45"/>';

      $ac = $r['ac'] ?? 180;
      $anatomy .= '<ellipse cx="448" cy="430" rx="130" ry="108" fill="none" stroke="#ffff00" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.60"/>';
      $anatomy .= '<text x="350" y="520" fill="#ffff00" opacity="0.85" font-size="15" font-family="monospace">PA ' . (int)$ac . 'mm</text>';

    } else {

      $anatomy .= '<path d="M175 155 C240 105, 660 100, 728 158 C800 228, 808 430, 748 508 C680 594, 240 598, 168 510 C102 428, 104 226, 175 155 Z" fill="url(#uterusWall)" opacity="0.35"/>';

      $anatomy .= '<path d="M204 172 C262 124, 645 120, 706 175 C775 244, 780 418, 720 490 C655 572, 252 574, 188 490 C128 408, 128 240, 204 172 Z" fill="#010308" opacity="0.90"/>';

      $anatomy .= '<ellipse cx="448" cy="345" rx="235" ry="190" fill="#000507" opacity="0.88"/>';

      $anatomy .= '<path d="M188 280 C188 220, 200 185, 218 178 C238 170, 265 185, 275 235 C285 280, 280 360, 272 400 C264 436, 238 452, 215 442 C196 432, 188 396, 188 280 Z" fill="url(#placentaGrad)" opacity="0.78"/>';

      $anatomy .= '<ellipse cx="448" cy="215" rx="88" ry="78" fill="url(#boneRing)" opacity="0.75"/>';
      $anatomy .= '<ellipse cx="448" cy="215" rx="88" ry="78" fill="none" stroke="#c2d8e8" stroke-width="6" opacity="' . $edgeOp . '"/>';
      $anatomy .= '<ellipse cx="448" cy="215" rx="80" ry="70" fill="#010509" opacity="0.96"/>';

      $anatomy .= '<path d="M530 205 C540 215, 542 228, 535 240 C526 254, 508 262, 492 258" stroke="#8cbcd4" stroke-width="4" stroke-linecap="round" fill="none" opacity="0.55"/>';

      $anatomy .= '<path d="M374 248 C358 264, 350 292, 354 322 C358 350, 378 378, 400 398" stroke="#6aaac6" stroke-width="8" stroke-linecap="round" fill="none" opacity="0.55"/>';

      $anatomy .= '<path d="M392 298 C416 268, 490 265, 522 292 C554 320, 562 370, 544 408 C520 456, 458 472, 406 452 C354 432, 335 388, 342 350 C348 314, 370 324, 392 298 Z" fill="url(#tissueGrad)" opacity="0.82"/>';

      $anatomy .= '<path d="M545 318 C575 308, 604 318, 622 340 C638 360, 636 386, 618 398" stroke="#8abcd2" stroke-width="9" stroke-linecap="round" fill="none" opacity="0.45"/>';
      $anatomy .= '<path d="M428 460 C422 492, 420 524, 426 554" stroke="#6aaac4" stroke-width="10" stroke-linecap="round" fill="none" opacity="0.40"/>';
      $anatomy .= '<path d="M478 455 C485 488, 492 520, 498 548" stroke="#6aaac4" stroke-width="10" stroke-linecap="round" fill="none" opacity="0.40"/>';

      $anatomy .= '<path d="M450 390 C430 410, 420 428, 435 445 C450 462, 468 468, 458 488" stroke="#4a8ab2" stroke-width="4" fill="none" stroke-linecap="round" opacity="0.50"/>';

      $fl = $r['fl'] ?? 38;
      $anatomy .= '<line x1="345" y1="350" x2="345" y2="455" stroke="#ffff00" stroke-width="1.2" stroke-dasharray="4 3" opacity="0.65"/>';
      $anatomy .= '<text x="285" y="408" fill="#ffff00" opacity="0.85" font-size="14" font-family="monospace">LF ' . (int)$fl . 'mm</text>';
    }

  } else {

    if ($view === 'tete') {

      $anatomy .= '<ellipse cx="448" cy="300" rx="200" ry="178" fill="url(#boneRing)" opacity="0.82"/>';
      $anatomy .= '<ellipse cx="448" cy="300" rx="200" ry="178" fill="none" stroke="#d8e8f0" stroke-width="9" opacity="' . $edgeOp . '"/>';
      $anatomy .= '<ellipse cx="448" cy="300" rx="187" ry="165" fill="#010207" opacity="0.98"/>';

      $anatomy .= '<path d="M300 280 C330 256, 370 252, 400 270" stroke="#2a5872" stroke-width="5" fill="none" opacity="0.55"/>';
      $anatomy .= '<path d="M496 270 C526 252, 566 256, 596 280" stroke="#2a5872" stroke-width="5" fill="none" opacity="0.55"/>';
      $anatomy .= '<path d="M340 340 C370 322, 410 320, 440 338" stroke="#2a5872" stroke-width="4" fill="none" opacity="0.45"/>';

      $anatomy .= '<line x1="448" y1="128" x2="448" y2="472" stroke="#6aaac6" stroke-width="2" opacity="0.45"/>';

      $anatomy .= '<ellipse cx="406" cy="305" rx="48" ry="34" fill="#1a3a50" opacity="0.65"/>';
      $anatomy .= '<ellipse cx="490" cy="305" rx="48" ry="34" fill="#1a3a50" opacity="0.65"/>';

      $anatomy .= '<path d="M252 190 L152 470 L255 480 Z" fill="#000205" opacity="0.50"/>';
      $anatomy .= '<path d="M644 190 L748 470 L642 480 Z" fill="#000205" opacity="0.50"/>';

      $bpd = $r['bpd'] ?? 85;
      $anatomy .= '<line x1="250" y1="300" x2="646" y2="300" stroke="#ffff00" stroke-width="1.2" stroke-dasharray="6 4" opacity="0.65"/>';
      $anatomy .= '<line x1="250" y1="288" x2="250" y2="312" stroke="#ffff00" stroke-width="2.5" opacity="0.78"/>';
      $anatomy .= '<line x1="646" y1="288" x2="646" y2="312" stroke="#ffff00" stroke-width="2.5" opacity="0.78"/>';
      $anatomy .= '<text x="380" y="286" fill="#ffff00" opacity="0.85" font-size="15" font-family="monospace">BIP ' . (int)$bpd . 'mm</text>';

    } elseif ($view === 'thorax') {

      $anatomy .= '<ellipse cx="448" cy="300" rx="195" ry="168" fill="url(#tissueGrad)" opacity="0.58"/>';
      $anatomy .= '<ellipse cx="448" cy="300" rx="195" ry="168" fill="none" stroke="#b8d0e0" stroke-width="6" opacity="' . $edgeOp . '"/>';
      $anatomy .= '<ellipse cx="384" cy="285" rx="80" ry="68" fill="#1e4060" opacity="0.75"/>';
      $anatomy .= '<ellipse cx="516" cy="285" rx="80" ry="68" fill="#1e4060" opacity="0.75"/>';

      $anatomy .= '<ellipse cx="424" cy="302" rx="68" ry="58" fill="#010b18" opacity="0.92"/>';
      $anatomy .= '<ellipse cx="424" cy="302" rx="68" ry="58" fill="none" stroke="#4890b5" stroke-width="2.5" opacity="0.50"/>';

      $anatomy .= '<ellipse cx="520" cy="380" rx="32" ry="24" fill="#010610" opacity="0.90"/>';
      $anatomy .= '<ellipse cx="520" cy="380" rx="32" ry="24" fill="none" stroke="#4a8ab2" stroke-width="2" opacity="0.48"/>';

      $anatomy .= '<ellipse cx="448" cy="400" rx="26" ry="18" fill="#c4dce8" opacity="0.72"/>';
      $anatomy .= '<path d="M422 418 L380 600 L516 600 L474 418 Z" fill="#000306" opacity="0.48"/>';
      $ac = $r['ac'] ?? 280;
      $anatomy .= '<ellipse cx="448" cy="440" rx="155" ry="130" fill="none" stroke="#ffff00" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.55"/>';
      $anatomy .= '<text x="340" y="548" fill="#ffff00" opacity="0.85" font-size="15" font-family="monospace">PA ' . (int)$ac . 'mm</text>';

    } else {

      $anatomy .= '<rect x="50" y="100" width="800" height="480" rx="8" fill="url(#uterusWall)" opacity="0.28"/>';

      $anatomy .= '<path d="M180 175 C240 130, 420 118, 548 138 C660 156, 735 218, 748 310 C762 400, 714 480, 624 522 C520 568, 358 568, 248 528 C142 490, 104 414, 110 330 C116 248, 138 210, 180 175 Z" fill="url(#tissueGrad)" opacity="0.72"/>';

      $anatomy .= '<ellipse cx="420" cy="460" rx="148" ry="132" fill="url(#boneRing)" opacity="0.75"/>';
      $anatomy .= '<ellipse cx="420" cy="460" rx="148" ry="132" fill="none" stroke="#ccdde8" stroke-width="8" opacity="' . $edgeOp . '"/>';
      $anatomy .= '<ellipse cx="420" cy="460" rx="138" ry="122" fill="#010308" opacity="0.97"/>';

      $anatomy .= '<path d="M275 592 L190 610 L660 610 L568 592 Z" fill="#000408" opacity="0.50"/>';

      $anatomy .= '<ellipse cx="430" cy="270" rx="155" ry="130" fill="#020c18" opacity="0.70"/>';

      for ($v = 0; $v < 10; $v++) {
        $vx = 305 + $v * 26;
        $vy = 260 + sin($v * 0.6) * 18;
        $anatomy .= "<ellipse cx='{$vx}' cy='{$vy}' rx='9' ry='6' fill='#b8d4e4' opacity='0.60'/>";
        $anatomy .= "<path d='M" . ($vx - 9) . " " . ($vy + 8) . " L" . ($vx + 9) . " " . ($vy + 8) . "' stroke='#000306' stroke-width='5' opacity='0.40'/>";
      }

      $anatomy .= '<path d="M558 200 C595 182, 632 188, 655 216 C674 240, 668 274, 642 290" stroke="#8abcd2" stroke-width="12" stroke-linecap="round" fill="none" opacity="0.48"/>';

      $placentaPos = $r['placenta'] ?? 'postérieur';
      if ($placentaPos === 'antérieur') {
        $anatomy .= '<path d="M112 160 C118 130, 145 118, 175 122 C205 126, 226 150, 228 195 C230 250, 220 350, 210 410 C198 464, 172 486, 148 472 C128 460, 114 424, 112 160 Z" fill="url(#placentaGrad)" opacity="0.80"/>';
      } else {
        $anatomy .= '<path d="M722 162 C734 130, 758 118, 782 124 C806 130, 822 152, 822 200 C822 265, 812 370, 798 425 C782 476, 756 492, 732 476 C712 462, 715 420, 720 162 Z" fill="url(#placentaGrad)" opacity="0.78"/>';
      }

      $anatomy .= '<path d="M155 180 C162 155, 185 145, 208 158 L215 210 C195 215, 170 208, 155 180 Z" fill="#01060f" opacity="0.75"/>';
      $anatomy .= '<path d="M640 180 C648 155, 670 145, 694 158 L700 212 C678 217, 652 210, 640 180 Z" fill="#01060f" opacity="0.75"/>';

      $fl = $r['fl'] ?? 68;
      $anatomy .= '<line x1="340" y1="160" x2="340" y2="340" stroke="#ffff00" stroke-width="1.5" stroke-dasharray="5 4" opacity="0.58"/>';
      $anatomy .= '<line x1="328" y1="160" x2="352" y2="160" stroke="#ffff00" stroke-width="2" opacity="0.68"/>';
      $anatomy .= '<line x1="328" y1="340" x2="352" y2="340" stroke="#ffff00" stroke-width="2" opacity="0.68"/>';
      $anatomy .= '<text x="248" y="252" fill="#ffff00" opacity="0.80" font-size="14" font-family="monospace">LF ' . (int)$fl . 'mm</text>';
    }
  }

  if (in_array('decollement', $anomalies, true) || in_array('hematome', $anomalies, true)) {

    $anatomy .= '<path d="M578 152 C628 136, 678 154, 702 195 C722 230, 714 268, 680 286 C644 305, 592 298, 564 268 C528 230, 526 172, 578 152 Z" fill="url(#hemGrad)" opacity="0.60"/>';
    $anatomy .= '<path d="M578 152 C628 136, 678 154, 702 195 C722 230, 714 268, 680 286 C644 305, 592 298, 564 268 C528 230, 526 172, 578 152 Z" fill="none" stroke="#d0e4f0" stroke-width="2" opacity="0.30"/>';
  }
  if (in_array('placenta_bas', $anomalies, true)) {
    $anatomy .= '<path d="M226 478 C305 454, 398 452, 472 472 C510 483, 518 522, 484 538 C412 578, 284 570, 228 530 C206 512, 208 488, 226 478 Z" fill="url(#placentaGrad)" opacity="0.70"/>';
    $anatomy .= '<text x="260" y="510" fill="#ff9900" opacity="0.80" font-size="13" font-family="monospace">PLACENTA BAS</text>';
  }
  if (in_array('kyste_ovarien', $anomalies, true)) {
    $anatomy .= '<circle cx="680" cy="430" r="46" fill="#010810" opacity="0.92"/>';
    $anatomy .= '<circle cx="680" cy="430" r="46" fill="none" stroke="#7abcd4" stroke-width="3.5" opacity="0.60"/>';

    $anatomy .= '<path d="M650 476 L640 560 L720 560 L710 476 Z" fill="#04121e" opacity="0.55"/>';
    $anatomy .= '<text x="628" y="488" fill="#00ddff" opacity="0.75" font-size="13" font-family="monospace">KYSTE</text>';
  }
  if (in_array('rciu', $anomalies, true)) {
    $anatomy .= '<text x="68" y="148" fill="#ff5555" opacity="0.85" font-size="14" font-family="monospace">RCIU !</text>';
  }
  if (in_array('oligoamnios', $anomalies, true)) {
    $anatomy .= '<text x="68" y="168" fill="#ff9900" opacity="0.80" font-size="13" font-family="monospace">OLIGOAMNIOS</text>';
  }
  if (in_array('polyhydramnios', $anomalies, true)) {
    $anatomy .= '<text x="68" y="168" fill="#ff9900" opacity="0.80" font-size="13" font-family="monospace">HYDRAMNIOS</text>';
  }

  if ($fetuses === 2 && $trimester <= 2) {
    $anatomy .= '<ellipse cx="292" cy="252" rx="72" ry="62" fill="url(#boneRing)" opacity="0.58"/>';
    $anatomy .= '<ellipse cx="292" cy="252" rx="72" ry="62" fill="none" stroke="#a8c4d8" stroke-width="5" opacity="' . $edgeOp . '"/>';
    $anatomy .= '<ellipse cx="292" cy="252" rx="64" ry="54" fill="#010408" opacity="0.94"/>';
    $anatomy .= '<text x="248" y="194" fill="#00ddff" opacity="0.70" font-size="13" font-family="monospace">Twin B</text>';
    $anatomy .= '<text x="382" y="194" fill="#00ddff" opacity="0.70" font-size="13" font-family="monospace">Twin A</text>';

    $anatomy .= '<path d="M362 155 C360 260, 358 380, 362 490" stroke="#88bcd4" stroke-width="1.5" stroke-dasharray="4 3" opacity="0.40" fill="none"/>';
  }

  $examCode = strtoupper(substr($r['exam'], 0, 4));
  $qualCode = strtoupper(substr($quality, 0, 3));
  $uid = str_pad((string)(($seed * 1103) % 10000), 4, '0', STR_PAD_LEFT);
  $fhrStr = $r['fhr'] ? $r['fhr'] . ' bpm' : '---';

  $labelsLeft = [
    ['y'=>58,  't'=>'GE VOLUSON E10'],
    ['y'=>82,  't'=>'GA ' . $sa . 'w+' . $days . 'd'],
    ['y'=>106, 't'=>$qualCode . ' / ' . $examCode],
    ['y'=>130, 't'=>($trimester === 1 ? 'TV / TRANS-AB' : 'TRANS-AB')],
  ];
  $labelsRight = [
    ['y'=>58,  't'=>'ID #' . $uid],
    ['y'=>82,  't'=>'FHR ' . $fhrStr],
    ['y'=>106, 't'=>($fetuses === 2 ? 'GEMELLAIRE' : 'SINGLETON')],
    ['y'=>130, 't'=>'Fr ' . ($trimester === 1 ? '12' : ($trimester === 2 ? '6' : '4')) . 'MHz'],
  ];

  $labelSvg = '';
  foreach ($labelsLeft as $l) {
    $labelSvg .= '<text x="68" y="' . $l['y'] . '" fill="#78c8e8" opacity="0.88" font-size="16" font-family="monospace">' . htmlspecialchars($l['t'], ENT_QUOTES, 'UTF-8') . '</text>';
  }
  foreach ($labelsRight as $l) {
    $tx = 900 - 68 - strlen($l['t']) * 9.6;
    $labelSvg .= '<text x="' . (int)$tx . '" y="' . $l['y'] . '" fill="#78c8e8" opacity="0.88" font-size="16" font-family="monospace">' . htmlspecialchars($l['t'], ENT_QUOTES, 'UTF-8') . '</text>';
  }

  $labelSvg .= '<text x="68" y="604" fill="#5aaac8" opacity="0.72" font-size="13" font-family="monospace">Depth ' . $depthCm . '.0 cm</text>';
  $labelSvg .= '<text x="500" y="604" fill="#5aaac8" opacity="0.72" font-size="13" font-family="monospace">MI 0.' . (($seed % 4) + 6) . '  TIS 0.' . (($seed % 5) + 2) . '</text>';
  $labelSvg .= '<text x="740" y="604" fill="#5aaac8" opacity="0.72" font-size="13" font-family="monospace">' . date('H:i:s') . '</text>';

  if ($r['fhr']) {
    $labelSvg .= '<circle cx="812" cy="82" r="5" fill="#ff3a3a" opacity="0.90"/>';
    $labelSvg .= '<circle cx="812" cy="82" r="9" fill="none" stroke="#ff3a3a" stroke-width="1.5" opacity="0.40"/>';
  }

  return <<<SVG
<svg viewBox="0 0 900 650" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Échographie obstétricale">
  <defs>
    <!-- Fond écran échographe -->
    <linearGradient id="bgGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#0d1520"/>
      <stop offset="50%"  stop-color="#060c14"/>
      <stop offset="100%" stop-color="#020508"/>
    </linearGradient>
    <!-- Biseau latéral de l'écran -->
    <linearGradient id="screenVignette" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%"   stop-color="#000" stop-opacity="0.55"/>
      <stop offset="12%"  stop-color="#000" stop-opacity="0"/>
      <stop offset="88%"  stop-color="#000" stop-opacity="0"/>
      <stop offset="100%" stop-color="#000" stop-opacity="0.55"/>
    </linearGradient>
    <!-- Secteur ultrasonore (éventail) -->
    <radialGradient id="fanGrad" cx="50%" cy="0%" r="100%" fx="50%" fy="0%">
      <stop offset="0%"   stop-color="#1a3a58" stop-opacity="0.30"/>
      <stop offset="40%"  stop-color="#0e2238" stop-opacity="0.18"/>
      <stop offset="85%"  stop-color="#05111e" stop-opacity="0.10"/>
      <stop offset="100%" stop-color="#000"    stop-opacity="0"/>
    </radialGradient>
    <!-- Paroi utérine -->
    <radialGradient id="uterusWall" cx="50%" cy="50%" r="65%">
      <stop offset="0%"   stop-color="#4a7a96" stop-opacity="0.60"/>
      <stop offset="60%"  stop-color="#1e3a52" stop-opacity="0.40"/>
      <stop offset="100%" stop-color="#0a1828" stop-opacity="0.15"/>
    </radialGradient>
    <!-- Os / couronne hyperéchogène -->
    <radialGradient id="boneRing" cx="50%" cy="50%" r="70%">
      <stop offset="0%"   stop-color="#d8ecf8" stop-opacity="0.85"/>
      <stop offset="55%"  stop-color="#9ac8e0" stop-opacity="0.60"/>
      <stop offset="90%"  stop-color="#4a7a98" stop-opacity="0.25"/>
      <stop offset="100%" stop-color="#000"    stop-opacity="0"/>
    </radialGradient>
    <!-- Tissu mou fœtal -->
    <linearGradient id="tissueGrad" x1="0.2" y1="0" x2="0.8" y2="1">
      <stop offset="0%"   stop-color="#3a6e92" stop-opacity="0.80"/>
      <stop offset="100%" stop-color="#0c1e30" stop-opacity="0.40"/>
    </linearGradient>
    <!-- Embryon T1 -->
    <radialGradient id="embryoGrad" cx="45%" cy="40%" r="65%">
      <stop offset="0%"   stop-color="#c0d8e8" stop-opacity="0.95"/>
      <stop offset="55%"  stop-color="#7aafc8" stop-opacity="0.65"/>
      <stop offset="100%" stop-color="#2a5878" stop-opacity="0.20"/>
    </radialGradient>
    <!-- Sac gestationnel anéchoïque (légèrement bleuté pour contraster) -->
    <radialGradient id="sacDark" cx="40%" cy="35%" r="70%">
      <stop offset="0%"   stop-color="#010d1e" stop-opacity="1.0"/>
      <stop offset="60%"  stop-color="#000c18" stop-opacity="1.0"/>
      <stop offset="100%" stop-color="#000408" stop-opacity="1.0"/>
    </radialGradient>
    <!-- Couronne trophoblastique -->
    <linearGradient id="trophGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"   stop-color="#d0e4f0" stop-opacity="0.90"/>
      <stop offset="100%" stop-color="#88b8d0" stop-opacity="0.50"/>
    </linearGradient>
    <!-- Placenta granuleux -->
    <linearGradient id="placentaGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"   stop-color="#5a9aba" stop-opacity="0.88"/>
      <stop offset="40%"  stop-color="#3a6888" stop-opacity="0.70"/>
      <stop offset="100%" stop-color="#0e2030" stop-opacity="0.35"/>
    </linearGradient>
    <!-- Hématome -->
    <radialGradient id="hemGrad" cx="50%" cy="50%" r="65%">
      <stop offset="0%"   stop-color="#d8f0ff" stop-opacity="0.85"/>
      <stop offset="70%"  stop-color="#7ab0cc" stop-opacity="0.55"/>
      <stop offset="100%" stop-color="#3a6882" stop-opacity="0.20"/>
    </radialGradient>
    <!-- Speckle ultrasonore -->
    <filter id="speckle" x="0%" y="0%" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="{$speckleFreq}" numOctaves="3" seed="{$seed}" result="noise"/>
      <feColorMatrix type="saturate" values="0" in="noise" result="grayNoise"/>
      <feComponentTransfer in="grayNoise" result="alphaRamp">
        <feFuncA type="table" tableValues="0 0 0.04 0.10 0.14 0.08 0.03 0"/>
      </feComponentTransfer>
      <feFlood flood-color="#6ab8d8" flood-opacity="1" result="tint"/>
      <feComposite in="tint" in2="alphaRamp" operator="in" result="speckleLayer"/>
      <feMerge>
        <feMergeNode in="SourceGraphic"/>
        <feMergeNode in="speckleLayer"/>
      </feMerge>
    </filter>
    <!-- Flou logiciel selon qualité -->
    <filter id="softBlur"><feGaussianBlur stdDeviation="{$blurStd}"/></filter>
    <!-- Clip général écran -->
    <clipPath id="screenClip"><rect x="46" y="46" width="808" height="558" rx="12"/></clipPath>
    <!-- Clip anatomie = pleine zone écran (le fanClip ne doit pas rogner) -->
    <clipPath id="fanClip"><rect x="46" y="46" width="808" height="558"/></clipPath>
  </defs>

  <!-- ─── Boîtier écran ─────────────────────────────────────────── -->
  <rect x="0" y="0" width="900" height="650" fill="#070d14"/>
  <rect x="18" y="18" width="864" height="614" rx="16" fill="#0a1220" stroke="#2a3a4e" stroke-width="2.5"/>
  <rect x="30" y="30" width="840" height="590" rx="10" fill="url(#bgGrad)"/>

  <!-- ─── Zone image + clip ──────────────────────────────────────── -->
  <g clip-path="url(#screenClip)">

    <!-- Fond image : noir absolu -->
    <rect x="46" y="46" width="808" height="558" fill="#000204"/>

    <!-- Éventail acoustique (secteur de scan) -->
    <path d="{$fanPath}" fill="url(#fanGrad)" opacity="0.70"/>
    <path d="{$fanPath}" fill="none" stroke="#1e4a68" stroke-width="1.2" opacity="0.45"/>

    <!-- Lignes de scan SVG -->
    {$scanLines}

    <!-- Anatomie (structures) avec flou logiciel -->
    <g filter="url(#softBlur)">
      {$anatomy}
    </g>

    <!-- Speckle ultrasonore par-dessus -->
    <rect x="46" y="46" width="808" height="558" fill="#08121e" filter="url(#speckle)" opacity="{$speckleOp}"/>

    <!-- Vignette latérale (bords sombres) -->
    <rect x="46" y="46" width="808" height="558" fill="url(#screenVignette)"/>

    <!-- Graduation de profondeur -->
    {$depthMarks}

    <!-- Labels / textes machine -->
    {$labelSvg}

    <!-- Ligne de référence horizontale (mi-champ) -->
    <line x1="120" y1="330" x2="782" y2="330" stroke="#2a6888" stroke-opacity="0.20" stroke-width="1" stroke-dasharray="8 8"/>

    <!-- Bordure intérieure légère de l'écran -->
    <rect x="46" y="46" width="808" height="558" rx="12" fill="none" stroke="#1a3a58" stroke-width="1.5" opacity="0.40"/>
  </g>

  <!-- Reflet écran (overlay vitreux) -->
  <path d="M30 30 Q450 22 870 30 L870 180 Q620 120 30 180 Z" fill="white" opacity="0.025"/>
</svg>
SVG;
}

$defaults = [
  'patient_name' => '',
  'patient_id' => '',
  'sa' => 24,
  'days' => 3,
  'exam' => 'obstetricale',
  'view' => 'profil',
  'quality' => 'bonne',
  'fetuses' => 1,
  'presentation' => 'céphalique',
  'placenta' => 'antérieur',
  'fluid' => 'normal',
  'sex' => 'indéterminé',
  'blood_panel' => 'prenatal',
  'abo' => 'auto',
  'rh' => 'auto',
  'anomaly_profile' => 'normal',
  'us_anomalies' => [],
];

$data = $defaults;
$loadedRecordId = null;
$saveMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? 'generate');

  if ($action === 'delete' && isset($_POST['record_id'])) {
    $stmt = $pdo->prepare('DELETE FROM gynecology_records WHERE id = ?');
    $stmt->execute([(int)$_POST['record_id']]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  }

  if ($action === 'consult' && isset($_POST['record_id'])) {
    $stmt = $pdo->prepare('SELECT id, payload FROM gynecology_records WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_POST['record_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['payload'])) {
      $payload = json_decode((string)$row['payload'], true);
      if (is_array($payload) && isset($payload['inputs']) && is_array($payload['inputs'])) {
        $data = array_merge($defaults, $payload['inputs']);
        $loadedRecordId = (int)$row['id'];
      }
    }
  } elseif ($action === 'save') {
    $data = array_merge($defaults, $_POST);
    $recordPayload = [
      'inputs' => $data,
      'saved_at' => date('c'),
      'version' => 'v3',
    ];
    $stmt = $pdo->prepare('INSERT INTO gynecology_records (user_id, patient_name, patient_identifier, exam_type, payload) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
      (int)(current_user()['id'] ?? 0),
      trim((string)($data['patient_name'] ?? 'Patient inconnu')) ?: 'Patient inconnu',
      trim((string)($data['patient_id'] ?? '')) ?: null,
      'gynecology_v3',
      json_encode($recordPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $loadedRecordId = (int)$pdo->lastInsertId();
    $saveMessage = 'Consultation sauvegardée dans l’historique.';
  } else {
    $data = array_merge($defaults, $_POST);
  }
}

$blood = build_blood_profile($data);
$us = build_ultrasound_report($data);
$svg = render_svg($us);

$historyStmt = $pdo->query("SELECT id, patient_name, patient_identifier, exam_type, created_at, payload FROM gynecology_records WHERE exam_type = 'gynecology_v3' ORDER BY id DESC LIMIT 20");
$history = $historyStmt ? $historyStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$simpleSummary = [];
$simpleSummary[] = 'Grossesse estimée à ' . (int)$us['sa'] . ' SA + ' . (int)$us['days'] . ' jours.';
$simpleSummary[] = $us['fhr'] ? 'Le cœur du bébé est visible et bat à environ ' . (int)$us['fhr'] . ' bpm.' : 'L’activité cardiaque n’a pas été confirmée sur cet examen et doit être recontrôlée.';
$simpleSummary[] = ($blood['profile'] === 'normal')
  ? 'Les prises de sang ne montrent pas d’anomalie importante sur cette simulation.'
  : 'Les prises de sang montrent un point de vigilance : ' . strip_tags($blood['issues'][0]);
$simpleSummary[] = 'Conduite proposée : ' . strip_tags($blood['solutions'][0] ?? 'suivi obstétrical habituel.');

samr_layout_start('Gynécologie V3');
?>
<div class="page-header">
  <h1>Générateur gynécologie V3</h1>
  <div class="sub">Échographie plus crédible, prises de sang prénatales / natales, carences et compte-rendu simplifié à remettre au patient.</div>
</div>

<div class="card card-accent" style="margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
    <div>
      <div style="font-family:var(--font-head);font-weight:800;font-size:18px;margin-bottom:6px;">Informations</div>
      <div class="muted" style="max-width:980px;">
        Cette page combine un rendu échographique pour les rapports, un volet biologique prénatal / natal avec profils de carence ou complications, un résumé médical détaillé et un résumé simplifié pour le patient.
      </div>
    </div>
    <div class="pill pill-green">PNG local + serveur</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:minmax(340px,430px) 1fr;gap:16px;align-items:start;">
  <div>
    <form method="post" class="card" id="generatorForm">
      <div style="font-family:var(--font-head);font-size:17px;font-weight:800;margin-bottom:14px;">Paramètres</div>

      <details open style="margin-bottom:12px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        <summary style="cursor:pointer;list-style:none;padding:14px 16px;background:rgba(255,255,255,.03);font-weight:700;">Identité dossier</summary>
        <div style="padding:14px 16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label>Nom / prénom<input class="inp" type="text" name="patient_name" value="<?= h((string)$data['patient_name']) ?>" placeholder="Ex : Jane Doe"></label>
          <label>N° dossier<input class="inp" type="text" name="patient_id" value="<?= h((string)$data['patient_id']) ?>" placeholder="Ex : GYN-204"></label>
        </div>
      </details>

      <details open style="margin-bottom:12px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        <summary style="cursor:pointer;list-style:none;padding:14px 16px;background:rgba(255,255,255,.03);font-weight:700;">Échographie</summary>
        <div style="padding:14px 16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label>SA<input class="inp" type="number" name="sa" min="4" max="41" value="<?= h((string)$data['sa']) ?>"></label>
          <label>Jours<input class="inp" type="number" name="days" min="0" max="6" value="<?= h((string)$data['days']) ?>"></label>
          <label>Type d'examen
            <select class="inp" name="exam">
              <?php foreach (['obstetricale'=>'Obstétricale','morphologique'=>'Morphologique','doppler'=>'Doppler','pelvienne'=>'Pelvienne','endovaginale'=>'Endovaginale'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['exam']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Vue principale
            <select class="inp" name="view">
              <?php foreach (['standard'=>'Standard','profil'=>'Profil','tete'=>'Tête','thorax'=>'Thorax / abdomen','bassin'=>'Bassin'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['view']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Qualité
            <select class="inp" name="quality">
              <?php foreach (['excellente'=>'Excellente','bonne'=>'Bonne','moyenne'=>'Moyenne','difficile'=>'Difficile'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['quality']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Nombre de fœtus
            <select class="inp" name="fetuses">
              <option value="1" <?= (int)$data['fetuses']===1?'selected':'' ?>>1</option>
              <option value="2" <?= (int)$data['fetuses']===2?'selected':'' ?>>2</option>
            </select>
          </label>
          <label>Présentation
            <select class="inp" name="presentation">
              <?php foreach (['variable'=>'Variable','céphalique'=>'Céphalique','siège'=>'Siège','transverse'=>'Transverse'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['presentation']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Placenta
            <select class="inp" name="placenta">
              <?php foreach (['antérieur'=>'Antérieur','postérieur'=>'Postérieur','fundique'=>'Fundique','latéral'=>'Latéral'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['placenta']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Liquide amniotique
            <select class="inp" name="fluid">
              <?php foreach (['normal'=>'Normal','diminué'=>'Diminué','augmenté'=>'Augmenté'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['fluid']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Sexe estimé
            <select class="inp" name="sex">
              <?php foreach (['indéterminé'=>'Indéterminé','féminin'=>'Féminin','masculin'=>'Masculin'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['sex']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </details>

      <details style="margin-bottom:12px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        <summary style="cursor:pointer;list-style:none;padding:14px 16px;background:rgba(255,255,255,.03);font-weight:700;">Anomalies échographiques</summary>
        <div style="padding:14px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px 14px;">
          <?php $usOpts = ['decollement'=>'Décollement sous-chorial','hematome'=>'Hématome péri-gestationnel','placenta_bas'=>'Placenta bas','rciu'=>'RCIU / biométrie basse','oligoamnios'=>'Oligoamnios','polyhydramnios'=>'Hydramnios','absence_acf'=>'Absence ACF','kyste_ovarien'=>'Kyste ovarien'];
          foreach ($usOpts as $k=>$v): ?>
            <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" name="us_anomalies[]" value="<?= h($k) ?>" <?= in_array($k, post_array('us_anomalies'), true)?'checked':'' ?>><?= h($v) ?></label>
          <?php endforeach; ?>
        </div>
      </details>

      <details open style="margin-bottom:12px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        <summary style="cursor:pointer;list-style:none;padding:14px 16px;background:rgba(255,255,255,.03);font-weight:700;">Prises de sang prénatales / natales</summary>
        <div style="padding:14px 16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label>Type de bilan
            <select class="inp" name="blood_panel">
              <option value="prenatal" <?= $data['blood_panel']==='prenatal'?'selected':'' ?>>Prénatal</option>
              <option value="natal" <?= $data['blood_panel']==='natal'?'selected':'' ?>>Natal / sang de cordon</option>
            </select>
          </label>
          <label>Groupe ABO
            <select class="inp" name="abo">
              <?php foreach (['auto'=>'Auto','A'=>'A','B'=>'B','AB'=>'AB','O'=>'O'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['abo']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Rhésus
            <select class="inp" name="rh">
              <?php foreach (['auto'=>'Auto','Rh+'=>'Rh+','Rh-'=>'Rh-'] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['rh']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </details>

      <details open style="margin-bottom:14px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        <summary style="cursor:pointer;list-style:none;padding:14px 16px;background:rgba(255,255,255,.03);font-weight:700;">Profil d’anomalie biologique</summary>
        <div style="padding:14px 16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label style="grid-column:1/-1;">Profil
            <select class="inp" name="anomaly_profile">
              <?php foreach ([
                'normal'=>'Suivi simple / normal',
                'iron_deficiency'=>'Carence en fer',
                'folate_deficiency'=>'Carence en folates',
                'b12_deficiency'=>'Carence en vitamine B12',
                'vitd_deficiency'=>'Carence en vitamine D',
                'gestational_diabetes'=>'Diabète gestationnel',
                'preeclampsia'=>'Pré-éclampsie',
                'cholestasis'=>'Cholestase gravidique',
                'rh_incompatibility'=>'Incompatibilité Rh / allo-immunisation',
                'infection'=>'Syndrome infectieux',
                'mixed'=>'Profil mixte',
              ] as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $data['anomaly_profile']===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </details>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn" type="submit" name="action" value="generate">Générer le dossier</button>
        <button class="btn success" type="submit" name="action" value="save">Sauvegarder</button>
        <button class="btn secondary" type="button" id="downloadPngBtn">Extraire en PNG</button>
        <button class="btn success" type="button" id="serverPngBtn">Exporter dans /uploads/gyneco</button>
      </div>
      <div id="saveStatus" class="muted" style="margin-top:10px;font-size:12px;"><?= h((string)($saveMessage ?? '')) ?></div>
    </form>

    <div class="card" style="margin-top:16px;">
      <div style="font-family:var(--font-head);font-size:17px;font-weight:800;margin-bottom:10px;">Historique des consultations</div>
      <?php if (!$history): ?>
        <div class="muted">Aucune consultation sauvegardée.</div>
      <?php else: foreach ($history as $row): $payload = json_decode((string)$row['payload'], true) ?: []; $inputs = is_array($payload['inputs'] ?? null) ? $payload['inputs'] : []; ?>
        <div style="padding:12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.02);margin-bottom:10px;<?= $loadedRecordId === (int)$row['id'] ? 'border-color:rgba(59,158,255,.35);background:rgba(59,158,255,.05);' : '' ?>">
          <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
            <div>
              <div style="font-weight:700;"><?= h((string)$row['patient_name']) ?></div>
              <div class="muted" style="font-size:11px;"><?= h((string)$row['created_at']) ?><?= !empty($row['patient_identifier']) ? ' · '.h((string)$row['patient_identifier']) : '' ?></div>
              <div class="muted" style="font-size:12px;margin-top:4px;"><?= h((string)($inputs['sa'] ?? '—')) ?> SA<?= isset($inputs['days']) ? ' + '.h((string)$inputs['days']).' j' : '' ?> · <?= h((string)($inputs['anomaly_profile'] ?? 'normal')) ?> · <?= h((string)($inputs['exam'] ?? 'obstetricale')) ?></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <form method="post">
                <input type="hidden" name="record_id" value="<?= (int)$row['id'] ?>">
                <button class="btn secondary" type="submit" name="action" value="consult">Consulter</button>
              </form>
              <form method="post" onsubmit="return confirm('Supprimer cette consultation ?');">
                <input type="hidden" name="record_id" value="<?= (int)$row['id'] ?>">
                <button class="btn danger" type="submit" name="action" value="delete">Supprimer</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div>
    <div class="card" id="reportCard">
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px;">
        <div>
          <div style="font-family:var(--font-head);font-size:18px;font-weight:800;">Dossier gynécologie / obstétrique</div>
          <div class="muted">Patient : <?= h((string)$data['patient_name'] ?: 'Non renseigné') ?><?= $data['patient_id'] !== '' ? ' — Dossier '.h((string)$data['patient_id']) : '' ?></div>
        </div>
        <div class="pill pill-blue"><?= h($blood['abo']) ?> <?= h($blood['rh']) ?></div>
      </div>

      <div id="usImageWrap" style="border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#05070b;margin-bottom:16px;">
        <?= $svg ?>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;">
        <?php if ($us['gs']): ?><div class="stat-card"><div class="s-label">Sac gestationnel</div><div class="s-val"><?= (int)$us['gs'] ?><span style="font-size:12px;"> mm</span></div></div><?php endif; ?>
        <?php if ($us['crl']): ?><div class="stat-card"><div class="s-label">CRL</div><div class="s-val"><?= (int)$us['crl'] ?><span style="font-size:12px;"> mm</span></div></div><?php endif; ?>
        <?php if ($us['bpd']): ?><div class="stat-card"><div class="s-label">BIP</div><div class="s-val"><?= (int)$us['bpd'] ?><span style="font-size:12px;"> mm</span></div></div><?php endif; ?>
        <?php if ($us['hc']): ?><div class="stat-card"><div class="s-label">PC</div><div class="s-val"><?= (int)$us['hc'] ?><span style="font-size:12px;"> mm</span></div></div><?php endif; ?>
        <?php if ($us['ac']): ?><div class="stat-card"><div class="s-label">PA</div><div class="s-val"><?= (int)$us['ac'] ?><span style="font-size:12px;"> mm</span></div></div><?php endif; ?>
        <?php if ($us['fl']): ?><div class="stat-card"><div class="s-label">LF</div><div class="s-val"><?= (int)$us['fl'] ?><span style="font-size:12px;"> mm</span></div></div><?php endif; ?>
        <?php if ($us['fhr']): ?><div class="stat-card blue"><div class="s-label">ACF</div><div class="s-val"><?= (int)$us['fhr'] ?><span style="font-size:12px;"> bpm</span></div></div><?php endif; ?>
        <?php if ($us['efw']): ?><div class="stat-card green"><div class="s-label">Poids estimé</div><div class="s-val"><?= (int)$us['efw'] ?><span style="font-size:12px;"> g</span></div></div><?php endif; ?>
      </div>

      <div style="display:grid;grid-template-columns:1.05fr .95fr;gap:14px;margin-bottom:14px;">
        <div class="card" style="margin:0;padding:16px;">
          <div style="font-weight:800;margin-bottom:8px;">Compte-rendu échographie</div>
          <div style="font-size:13px;line-height:1.7;">
            <?php foreach ($us['desc'] as $p): ?><div style="margin-bottom:6px;"><?= h($p) ?></div><?php endforeach; ?>
            <?php foreach ($us['interpret'] as $p): ?><div style="margin-bottom:6px;"><?= h($p) ?></div><?php endforeach; ?>
          </div>
        </div>
        <div class="card" style="margin:0;padding:16px;">
          <div style="font-weight:800;margin-bottom:8px;">Anomalies & conduite échographie</div>
          <div style="font-size:13px;line-height:1.7;">
            <?php foreach ($us['warnings'] as $p): ?><div style="margin-bottom:6px;"><strong>•</strong> <?= h($p) ?></div><?php endforeach; ?>
            <div style="height:8px;"></div>
            <?php foreach ($us['advice'] as $p): ?><div style="margin-bottom:6px;"><span class="muted">→</span> <?= h($p) ?></div><?php endforeach; ?>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div class="card" style="margin:0;padding:16px;">
          <div style="font-weight:800;margin-bottom:10px;">Bilan sanguin <?= $blood['panel'] === 'natal' ? 'natal' : 'prénatal' ?></div>
          <table class="data-table">
            <tbody>
              <tr><td>Groupe sanguin</td><td><?= h($blood['abo']) ?> <?= h($blood['rh']) ?></td></tr>
              <tr><td>RAI</td><td><?= h($blood['vals']['rai']) ?></td></tr>
              <tr><td>Hb</td><td><?= h((string)$blood['vals']['hb']) ?> g/dL</td></tr>
              <tr><td>Hte</td><td><?= h((string)$blood['vals']['hct']) ?> %</td></tr>
              <tr><td>VGM</td><td><?= h((string)$blood['vals']['mcv']) ?> fL</td></tr>
              <tr><td>Plaquettes</td><td><?= h((string)$blood['vals']['plt']) ?> G/L</td></tr>
              <tr><td>Leucocytes</td><td><?= h((string)$blood['vals']['wbc']) ?> G/L</td></tr>
              <tr><td>Ferritine</td><td><?= h((string)$blood['vals']['ferritin']) ?> µg/L</td></tr>
              <tr><td>Folates</td><td><?= h((string)$blood['vals']['folates']) ?> ng/mL</td></tr>
              <tr><td>Vitamine B12</td><td><?= h((string)$blood['vals']['b12']) ?> pg/mL</td></tr>
              <tr><td>Vitamine D</td><td><?= h((string)$blood['vals']['vitd']) ?> ng/mL</td></tr>
              <tr><td>Glycémie</td><td><?= h((string)$blood['vals']['glucose']) ?> mg/dL</td></tr>
              <tr><td>HbA1c</td><td><?= h((string)$blood['vals']['hba1c']) ?> %</td></tr>
              <tr><td>CRP</td><td><?= h((string)$blood['vals']['crp']) ?> mg/L</td></tr>
              <tr><td>ALT / AST</td><td><?= h((string)$blood['vals']['alt']) ?> / <?= h((string)$blood['vals']['ast']) ?> U/L</td></tr>
              <tr><td>Acides biliaires</td><td><?= h((string)$blood['vals']['bile']) ?> µmol/L</td></tr>
              <tr><td>Protéinurie</td><td><?= h((string)$blood['vals']['proteinuria']) ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="card" style="margin:0;padding:16px;">
          <div style="font-weight:800;margin-bottom:10px;">Sérologies & volet natal</div>
          <table class="data-table">
            <tbody>
              <tr><td>Rubéole</td><td><?= h($blood['vals']['rubella']) ?></td></tr>
              <tr><td>Toxoplasmose</td><td><?= h($blood['vals']['toxoplasma']) ?></td></tr>
              <tr><td>Hépatite B</td><td><?= h($blood['vals']['hbv']) ?></td></tr>
              <tr><td>VIH</td><td><?= h($blood['vals']['hiv']) ?></td></tr>
              <tr><td>Syphilis</td><td><?= h($blood['vals']['syphilis']) ?></td></tr>
              <tr><td>Streptocoque B</td><td><?= h($blood['vals']['gbs']) ?></td></tr>
              <tr><td>Coombs direct</td><td><?= h($blood['vals']['coombs_direct']) ?></td></tr>
              <tr><td>Bilirubine cordon</td><td><?= h((string)$blood['vals']['bilirubin_cord']) ?> µmol/L</td></tr>
              <tr><td>Kleihauer</td><td><?= h($blood['vals']['kleihauer']) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div class="card" style="margin:0;padding:16px;">
          <div style="font-weight:800;margin-bottom:8px;">Interprétation biologique</div>
          <div style="font-size:13px;line-height:1.7;">
            <?php foreach ($blood['issues'] as $p): ?><div style="margin-bottom:6px;"><strong>•</strong> <?= h($p) ?></div><?php endforeach; ?>
            <?php foreach ($blood['alerts'] as $p): ?><div style="margin-bottom:6px;"><span class="muted">!</span> <?= h($p) ?></div><?php endforeach; ?>
          </div>
        </div>
        <div class="card" style="margin:0;padding:16px;">
          <div style="font-weight:800;margin-bottom:8px;">Solutions médicales proposées</div>
          <div style="font-size:13px;line-height:1.7;">
            <?php foreach ($blood['solutions'] as $p): ?><div style="margin-bottom:6px;"><span class="muted">→</span> <?= h($p) ?></div><?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card card-accent" style="margin:0;padding:16px;">
        <div style="font-weight:800;margin-bottom:8px;">Compte-rendu simplifié à remettre au patient</div>
        <div style="font-size:13px;line-height:1.7;">
          <?php foreach ($simpleSummary as $p): ?><div style="margin-bottom:6px;"><?= h($p) ?></div><?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
async function makeCanvas() {
  const node = document.getElementById('reportCard');
  return await html2canvas(node, {backgroundColor: '#080c10', scale: 2});
}

document.getElementById('downloadPngBtn').addEventListener('click', async function(){
  const canvas = await makeCanvas();
  const a = document.createElement('a');
  a.href = canvas.toDataURL('image/png');
  a.download = 'gyneco-rp.png';
  a.click();
});

document.getElementById('serverPngBtn').addEventListener('click', async function(){
  const status = document.getElementById('saveStatus');
  status.textContent = 'Export PNG en cours...';
  try {
    const canvas = await makeCanvas();
    const fd = new FormData();
    fd.append('action', 'save_png');
    fd.append('png_data', canvas.toDataURL('image/png'));
    fd.append('patient_slug', document.querySelector('input[name="patient_name"]').value || 'patiente');
    const res = await fetch(location.href, {method: 'POST', body: fd});
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.message || 'Export impossible');
    status.innerHTML = json.message + ' : <code>' + json.file + '</code>';
  } catch (err) {
    status.textContent = err.message || 'Erreur d’export.';
  }
});
</script>
<?php samr_layout_end(); ?>
