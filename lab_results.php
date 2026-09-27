<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'upload_lab_png')) {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $dataUrl = (string)($_POST['image'] ?? '');
        $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string)($_POST['filename'] ?? 'bilan_laboratoire.png'));
        if ($filename === '' || !str_ends_with(strtolower($filename), '.png')) {
            $filename = 'bilan_laboratoire.png';
        }

        if (!preg_match('#^data:image/png;base64,#', $dataUrl)) {
            throw new RuntimeException('Image PNG invalide.');
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false) {
            throw new RuntimeException('Décodage PNG impossible.');
        }

        $uploadDir = dirname(__DIR__) . '/www/uploads/Labo';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Création du dossier impossible.');
        }

        $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($target, $binary) === false) {
            throw new RuntimeException('Écriture du fichier impossible.');
        }

        $scheme = 'https';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
            $scheme = strtolower((string)$_SERVER['REQUEST_SCHEME']);
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        } elseif ((int)($_SERVER['SERVER_PORT'] ?? 80) === 80) {
            $scheme = 'http';
        }

        $host = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $host = (string)$_SERVER['HTTP_HOST'];
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $host = (string)$_SERVER['SERVER_NAME'];
        }
        if (
            $host === ''
            || str_contains($host, 'localhost')
            || preg_match('/^(127\.0\.0\.1|0\.0\.0\.0|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)
        ) {
            $host = 'exemple.tld';
            $scheme = 'https';
        }

        $publicPath = '/uploads/Labo/' . rawurlencode($filename);
        $publicUrl = $scheme . '://' . $host . $publicPath;

        echo json_encode([
            'ok' => true,
            'file' => $filename,
            'url' => $publicUrl,
            'path' => $publicPath,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

require_once __DIR__ . '/layout.php';

samr_layout_start('Générateur de laboratoire');

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<style>
.lab-shell { display:grid; grid-template-columns: 380px minmax(0,1fr); gap:16px; }
.lab-sticky { position: sticky; top: 24px; align-self: start; }
.lab-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); gap:12px; }
.lab-section-title {
  font-family: var(--font-head); font-size: 14px; font-weight: 800; letter-spacing: .4px;
  margin-bottom: 12px; display:flex; align-items:center; gap:8px;
}
.lab-subgrid { display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
.lab-field label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); margin-bottom:5px; }
.lab-actions { display:flex; flex-wrap:wrap; gap:8px; }
.lab-checks { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:6px 10px; }
.lab-checks label { font-size:13px; color:var(--text); display:flex; align-items:center; gap:8px; }
.lab-checks input { accent-color: var(--accent); }
.lab-chip-row { display:flex; flex-wrap:wrap; gap:8px; }
.lab-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--line2); background:rgba(255,255,255,.03); font-size:12px; }
.lab-chip.active { border-color:rgba(59,158,255,.35); background:rgba(59,158,255,.09); color:var(--accent); }
.lab-report-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px; }
.lab-report-title { font-family: var(--font-head); font-size: 20px; font-weight:800; }
.lab-meta { display:grid; grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); gap:10px; margin-bottom:16px; }
.lab-meta-item { padding:11px 12px; border-radius:10px; border:1px solid var(--line); background:rgba(255,255,255,.025); }
.lab-meta-item .k { font-size:10px; text-transform:uppercase; color:var(--muted); letter-spacing:.8px; }
.lab-meta-item .v { margin-top:4px; font-size:13px; font-weight:600; }
.lab-table-wrap { overflow:auto; }
.lab-table { width:100%; border-collapse:collapse; }
.lab-table th, .lab-table td { padding:10px 12px; border-bottom:1px solid rgba(24,32,48,.85); font-size:13px; vertical-align:middle; }
.lab-table th { text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); }
.lab-table tr:hover td { background:rgba(255,255,255,.02); }
.lab-value { font-weight:700; color:var(--text); }
.lab-low { color:#ffd166; }
.lab-high { color:#ff8a8a; }
.lab-normal { color:var(--accent2); }
.lab-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 9px; border-radius:999px; font-size:11px; border:1px solid var(--line2); }
.lab-badge.low { color:#ffd166; border-color:rgba(255,209,102,.35); background:rgba(255,209,102,.08); }
.lab-badge.high { color:#ff8a8a; border-color:rgba(255,138,138,.35); background:rgba(255,138,138,.08); }
.lab-badge.normal { color:var(--accent2); border-color:rgba(0,212,170,.3); background:rgba(0,212,170,.08); }
.lab-panel { margin-bottom:14px; }
.lab-summary { display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:16px; }
.lab-summary .stat-card .s-val { font-size:18px; }
.lab-muted-box { padding:12px; border:1px dashed var(--line2); border-radius:12px; color:var(--muted); font-size:13px; }
.lab-notes { min-height:92px; white-space:pre-wrap; }
.print-only { display:none; }
/* Panel settings */
.settings-tabs { display:flex; gap:4px; margin-bottom:14px; border-bottom:1px solid var(--line2); padding-bottom:10px; }
.settings-tab { padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:1px solid transparent; color:var(--muted); transition:all .15s; }
.settings-tab:hover { color:var(--text); background:rgba(255,255,255,.04); }
.settings-tab.active { color:var(--accent); border-color:rgba(59,158,255,.3); background:rgba(59,158,255,.08); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
.panel-settings-block { margin-bottom:14px; border:1px solid var(--line2); border-radius:10px; overflow:hidden; }
.panel-settings-header { display:flex; align-items:center; gap:10px; padding:10px 13px; background:rgba(255,255,255,.03); cursor:pointer; user-select:none; }
.panel-settings-header:hover { background:rgba(255,255,255,.06); }
.panel-settings-icon { font-size:15px; }
.panel-settings-label { flex:1; font-size:13px; font-weight:700; }
.panel-settings-toggle { position:relative; display:inline-block; width:34px; height:19px; flex-shrink:0; }
.panel-settings-toggle input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; border-radius:999px; background:rgba(255,255,255,.12); transition:.2s; cursor:pointer; }
.toggle-slider::before { content:''; position:absolute; width:13px; height:13px; border-radius:50%; left:3px; top:3px; background:#fff; transition:.2s; }
.panel-settings-toggle input:checked + .toggle-slider { background:var(--accent); }
.panel-settings-toggle input:checked + .toggle-slider::before { left:18px; }
.panel-settings-chevron { font-size:11px; color:var(--muted); transition:.2s; }
.panel-settings-block.open .panel-settings-chevron { transform:rotate(180deg); }
.panel-settings-items { display:none; padding:10px 13px; border-top:1px solid var(--line2); gap:6px; flex-direction:column; }
.panel-settings-block.open .panel-settings-items { display:flex; }
.panel-settings-desc { font-size:11px; color:var(--muted); margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid var(--line2); line-height:1.5; }
.panel-item-check { display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--text); padding:3px 0; }
.panel-item-check input { accent-color:var(--accent); width:13px; height:13px; }
.panel-item-check span { flex:1; }
.panel-item-unit { font-size:11px; color:var(--muted); }
.panel-disabled-overlay { opacity:.38; pointer-events:none; }
.settings-hint { font-size:11px; color:var(--muted); padding:8px 10px; background:rgba(255,255,255,.02); border-radius:8px; border:1px solid var(--line2); margin-bottom:12px; line-height:1.5; }
@media (max-width: 1080px) {
  .lab-shell { grid-template-columns: 1fr; }
  .lab-sticky { position: static; }
}
@media print {
  body::before, .sidebar, .page-header, .lab-left, .no-print { display:none !important; }
  .main { margin:0 !important; padding:0 !important; }
  .card { border:none !important; box-shadow:none !important; padding:0 !important; background:#fff !important; color:#000 !important; }
  body, .main { background:#fff !important; color:#000 !important; }
  .print-only { display:block; }
}

.export-feedback { margin-top:10px; padding:10px 12px; border-radius:10px; border:1px solid var(--line2); font-size:12px; display:none; }
.export-feedback.show { display:block; }
.export-feedback.ok { border-color:rgba(0,212,170,.28); background:rgba(0,212,170,.08); color:#baf6e9; }
.export-feedback.err { border-color:rgba(255,138,138,.28); background:rgba(255,138,138,.08); color:#ffd1d1; }
.export-feedback a { color:#9fd0ff; text-decoration:underline; }
#exportStage { position:fixed; left:-10000px; top:0; width:1920px; height:1080px; overflow:hidden; background:#06101b; z-index:-1; pointer-events:none; }
.export-page { width:1920px; height:1080px; background:linear-gradient(180deg,#07111d 0%,#081521 100%); color:#eaf2ff; padding:28px; box-sizing:border-box; font-family:Inter, Arial, sans-serif; }
.export-page.scale-mode .export-inner { transform-origin:top left; }
.export-inner { width:1864px; min-height:1024px; }
.export-top { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; margin-bottom:14px; }
.export-title { font-size:28px; font-weight:900; line-height:1.05; }
.export-sub { margin-top:4px; font-size:12px; color:#9fb2c8; }
.export-pill { padding:8px 14px; border-radius:999px; border:1px solid rgba(90,160,255,.35); background:rgba(41,99,165,.18); font-size:12px; font-weight:700; white-space:nowrap; }
.export-layout { display:grid; grid-template-columns:360px minmax(0,1fr); gap:14px; min-height:960px; }
.export-sidebar { display:grid; gap:10px; align-content:start; }
.export-card { border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.03); border-radius:16px; padding:12px; box-sizing:border-box; }
.export-card-title { font-size:13px; font-weight:900; margin-bottom:10px; color:#f4f8ff; }
.export-meta-list, .export-summary-list { display:grid; gap:8px; }
.export-mini { border:1px solid rgba(255,255,255,.06); background:rgba(255,255,255,.025); border-radius:12px; padding:9px 10px; }
.export-mini .k { font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:#8fa3b9; }
.export-mini .v { margin-top:4px; font-size:15px; font-weight:800; line-height:1.22; color:#f2f6ff; word-break:break-word; }
.export-mini .s { margin-top:4px; color:#aebed1; font-size:11px; line-height:1.2; }
.export-interpret .body { font-size:12px; line-height:1.35; color:#edf4ff; white-space:pre-wrap; }
.export-results { min-width:0; }
.export-panels { display:grid; gap:10px; align-content:start; }
.export-panels.cols-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
.export-panels.cols-3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
.export-panels.cols-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
.export-panel { border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.03); border-radius:16px; overflow:hidden; min-width:0; }
.export-panel-head { padding:9px 11px; border-bottom:1px solid rgba(255,255,255,.07); font-size:14px; font-weight:800; }
.export-rows { padding:8px; display:grid; gap:6px; }
.export-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; align-items:center; padding:7px 9px; border-radius:10px; background:rgba(255,255,255,.035); }
.export-row .label { font-size:12px; font-weight:800; color:#f0f5ff; line-height:1.12; }
.export-row .ref { margin-top:2px; font-size:9px; color:#98abc0; line-height:1.1; }
.export-row.minimal .ref { display:none; }
.export-row .value { text-align:right; font-size:14px; font-weight:900; line-height:1.1; white-space:nowrap; }
.export-row .status { margin-top:1px; text-align:right; font-size:10px; }
.export-row.high .value, .export-row.high .status { color:#ff9f9f; }
.export-row.low .value, .export-row.low .status { color:#ffd166; }
.export-row.normal .value, .export-row.normal .status { color:#7de7d3; }
.export-foot { margin-top:8px; font-size:10px; color:#8496aa; }


</style>

<div class="page-header">
  <h1>Générateur de résultats laboratoire</h1>
  <div class="sub">Page pensée pour un usage rapide en roleplay médical, avec génération automatique, ajustements manuels et rendu propre à imprimer/exporter.</div>
</div>

<div class="stats-grid">
  <div class="stat-card blue">
    <div class="s-label">Modes</div>
    <div class="s-val">9</div>
    <div class="s-sub">Normal + profils cliniques</div>
  </div>
  <div class="stat-card green">
    <div class="s-label">Panneaux</div>
    <div class="s-val">10</div>
    <div class="s-sub">Hémato, biochimie, gaz, urine, etc.</div>
  </div>
  <div class="stat-card">
    <div class="s-label">Utilisation</div>
    <div class="s-val">Simple</div>
    <div class="s-sub">Un clic pour générer, puis édition libre</div>
  </div>
</div>

<div class="lab-shell">
  <div class="lab-left">
    <div class="card card-accent lab-sticky">
      <div class="lab-section-title">⚙️ Paramètres</div>

      <div class="settings-tabs">
        <div class="settings-tab active" data-tab="main">📋 Général</div>
        <div class="settings-tab" data-tab="panels">🔬 Panneaux</div>
      </div>

      <div class="tab-pane active" id="tab-main">
      <div class="lab-subgrid" style="margin-bottom:12px;">
        <div class="lab-field">
          <label for="patientName">Patient</label>
          <input id="patientName" class="inp" placeholder="Nom / matricule">
        </div>
        <div class="lab-field">
          <label for="patientAge">Âge</label>
          <input id="patientAge" class="inp" placeholder="Ex: 34 ans">
        </div>
        <div class="lab-field">
          <label for="patientSex">Sexe</label>
          <select id="patientSex" class="inp">
            <option value="M">Homme</option>
            <option value="F">Femme</option>
            <option value="X">Autre / non précisé</option>
          </select>
        </div>
        <div class="lab-field">
          <label for="bloodGroupMode">Groupe sanguin</label>
          <select id="bloodGroupMode" class="inp">
            <option value="random">Aléatoire</option>
            <option value="manual">Manuel</option>
          </select>
        </div>
        <div class="lab-field">
          <label for="bloodGroup">ABO / Rh</label>
          <select id="bloodGroup" class="inp">
            <option value="O+">O+</option>
            <option value="O-">O−</option>
            <option value="A+">A+</option>
            <option value="A-">A−</option>
            <option value="B+">B+</option>
            <option value="B-">B−</option>
            <option value="AB+">AB+</option>
            <option value="AB-">AB−</option>
          </select>
        </div>
        <div class="lab-field">
          <label for="context">Contexte clinique</label>
          <input id="context" class="inp" placeholder="Ex: douleur thoracique, sepsis suspecté">
        </div>
        <div class="lab-field">
          <label for="preset">Profil</label>
          <select id="preset" class="inp">
            <option value="normal">Normal</option>
            <option value="infection">Infection</option>
            <option value="dehydration">Déshydratation</option>
            <option value="anemia">Anémie</option>
            <option value="renal_failure">Insuffisance rénale</option>
            <option value="sepsis">Sepsis / choc septique</option>
            <option value="diabetes">Hyperglycémie / diabète</option>
            <option value="liver_injury">Atteinte hépatique</option>
            <option value="trauma">Trauma / hémorragie</option>
          </select>
        </div>
        <div class="lab-field">
          <label for="severity">Intensité</label>
          <select id="severity" class="inp">
            <option value="1">Discrète</option>
            <option value="2" selected>Modérée</option>
            <option value="3">Marquée</option>
          </select>
        </div>
        <div class="lab-field">
          <label for="sampleDate">Date / heure du prélèvement</label>
          <input id="sampleDate" type="datetime-local" class="inp">
        </div>
        <div class="lab-field">
          <label for="doctorName">Validé par</label>
          <input id="doctorName" class="inp" placeholder="Nom du médecin / interne">
        </div>
      </div>

      <div class="lab-field" style="margin-bottom:12px;">
        <div class="lab-checks">
          <label><input type="checkbox" id="includePregnancy"> Inclure β-hCG</label>
          <label><input type="checkbox" id="includeTox"> Inclure toxicologie</label>
          <label><input type="checkbox" id="includeCulture" checked> Inclure microbiologie</label>
          <label><input type="checkbox" id="showOnlyAbnormal"> Afficher uniquement les anomalies dans le rapport</label>
        </div>
      </div>

      <div class="lab-field" style="margin-bottom:12px;">
        <label for="drugDeclared">Consommation de drogue déclarée</label>
        <select id="drugDeclared" class="inp">
          <option value="none">Aucune</option>
          <option value="casual">Oui, occasionnelle</option>
          <option value="regular">Oui, régulière</option>
          <option value="heavy">Oui, importante / récente</option>
        </select>
      </div>
      <div class="lab-field" style="margin-bottom:12px;">
        <label>Type(s) de produit</label>
        <div class="lab-chip-row" id="drugTypesRow">
          <label class="lab-chip"><input type="checkbox" value="cannabis"> Cannabis</label>
          <label class="lab-chip"><input type="checkbox" value="cocaine"> Cocaïne</label>
          <label class="lab-chip"><input type="checkbox" value="amphetamines"> Amphétamines / méth</label>
          <label class="lab-chip"><input type="checkbox" value="mdma"> MDMA / ecstasy</label>
          <label class="lab-chip"><input type="checkbox" value="opiates"> Opiacés</label>
          <label class="lab-chip"><input type="checkbox" value="benzodiazepines"> Benzodiazépines</label>
          <label class="lab-chip"><input type="checkbox" value="alcohol"> Alcool</label>
        </div>
      </div>

      <div class="lab-actions" style="margin-bottom:10px;">
        <button class="btn" id="generateBtn">🧪 Générer</button>
        <button class="btn secondary" id="randomBtn">🎲 Aléatoire</button>
        <button class="btn secondary" id="normalBtn">✅ Normal</button>
        <button class="btn success" id="copyBtn">📋 Copier</button>
        <button class="btn secondary" id="pngBtn">🖼️ Export PNG</button>
        <button class="btn secondary" id="printBtn">🖨️ Imprimer</button>
      </div>

      <div class="lab-muted-box">
        Le rapport se met à jour automatiquement. Chaque valeur peut être modifiée à la main avant impression ou copier-coller dans vos notes RP.
      </div>
      <div id="exportFeedback" class="export-feedback"></div>
      </div><!-- /tab-main -->

      <div class="tab-pane" id="tab-panels">
        <div class="settings-hint">
          Activez ou désactivez chaque panneau. Dépliez un panneau pour choisir individuellement les examens à inclure dans le rapport.
        </div>
        <div id="panelSettingsContainer"></div>
      </div><!-- /tab-panels -->

    </div>
  </div>

  <div class="lab-right">
    <div class="card" id="reportCard">
      <div class="print-only" style="margin-bottom:16px; font-weight:700;">SAMR — Compte rendu laboratoire</div>
      <div class="lab-report-head">
        <div>
          <div class="lab-report-title">Compte rendu de laboratoire</div>
          <div class="muted">Hématologie, biochimie, coagulation, gaz du sang, urine et examens complémentaires</div>
        </div>
        <div class="pill pill-blue" id="profileBadge">Profil : Normal</div>
      </div>

      <div class="lab-meta">
        <div class="lab-meta-item"><div class="k">Patient</div><div class="v" id="metaPatient">—</div></div>
        <div class="lab-meta-item"><div class="k">Âge / sexe</div><div class="v" id="metaAgeSex">—</div></div>
        <div class="lab-meta-item"><div class="k">Groupe sanguin</div><div class="v" id="metaBloodGroup">—</div></div>
        <div class="lab-meta-item"><div class="k">Prélèvement</div><div class="v" id="metaDate">—</div></div>
        <div class="lab-meta-item"><div class="k">Contexte</div><div class="v" id="metaContext">—</div></div>
        <div class="lab-meta-item"><div class="k">Consommation</div><div class="v" id="metaDrugUse">—</div></div>
        <div class="lab-meta-item"><div class="k">Validé par</div><div class="v" id="metaDoctor">—</div></div>
      </div>

      <div class="lab-summary" id="summaryStats"></div>

      <div class="lab-panel">
        <div class="lab-section-title">📝 Interprétation rapide</div>
        <div class="card" style="padding:14px; margin-bottom:0;">
          <div class="lab-notes" id="interpretationBox">Aucun résultat généré pour le moment.</div>
        </div>
      </div>

      <div id="resultsContainer"></div>
    </div>
  </div>
</div>

<div id="exportStage" aria-hidden="true"></div>

<script>
const LAB_DEFS = {
  cbc: {
    label: 'Hématologie', icon: '🩸',
    items: [
      { key:'wbc', label:'Leucocytes', unit:'G/L', min:4.0, max:10.0, dp:1 },
      { key:'rbc', label:'Hématies', unit:'T/L', min:4.2, max:5.8, dp:2 },
      { key:'hb', label:'Hémoglobine', unit:'g/dL', min:12.0, max:17.5, dp:1 },
      { key:'hct', label:'Hématocrite', unit:'%', min:37.0, max:52.0, dp:1 },
      { key:'plt', label:'Plaquettes', unit:'G/L', min:150, max:400, dp:0 },
      { key:'neut', label:'PNN', unit:'G/L', min:1.5, max:7.5, dp:1 },
      { key:'lymph', label:'Lymphocytes', unit:'G/L', min:1.0, max:4.0, dp:1 }
    ]
  },
  chemistry: {
    label:'Biochimie', icon:'🧫',
    items:[
      { key:'na', label:'Sodium', unit:'mmol/L', min:135, max:145, dp:0 },
      { key:'k', label:'Potassium', unit:'mmol/L', min:3.5, max:5.1, dp:1 },
      { key:'cl', label:'Chlore', unit:'mmol/L', min:98, max:107, dp:0 },
      { key:'urea', label:'Urée', unit:'mmol/L', min:2.5, max:7.5, dp:1 },
      { key:'creat', label:'Créatinine', unit:'µmol/L', min:60, max:110, dp:0 },
      { key:'glucose', label:'Glucose', unit:'mmol/L', min:3.9, max:6.1, dp:1 },
      { key:'calcium', label:'Calcium', unit:'mmol/L', min:2.15, max:2.55, dp:2 }
    ]
  },
  liver: {
    label:'Bilan hépatique', icon:'🫀',
    items:[
      { key:'ast', label:'ASAT', unit:'U/L', min:10, max:40, dp:0 },
      { key:'alt', label:'ALAT', unit:'U/L', min:10, max:45, dp:0 },
      { key:'alp', label:'PAL', unit:'U/L', min:40, max:130, dp:0 },
      { key:'ggt', label:'GGT', unit:'U/L', min:10, max:55, dp:0 },
      { key:'bili', label:'Bilirubine totale', unit:'µmol/L', min:3, max:20, dp:0 },
      { key:'albumin', label:'Albumine', unit:'g/L', min:35, max:50, dp:0 }
    ]
  },
  coag: {
    label:'Coagulation', icon:'⏱️',
    items:[
      { key:'pt', label:'TP', unit:'%', min:70, max:100, dp:0 },
      { key:'inr', label:'INR', unit:'', min:0.8, max:1.2, dp:2 },
      { key:'aptt', label:'TCA', unit:'sec', min:25, max:35, dp:0 },
      { key:'fibrinogen', label:'Fibrinogène', unit:'g/L', min:2.0, max:4.0, dp:1 }
    ]
  },
  inflammation: {
    label:'Inflammation / infection', icon:'🔥',
    items:[
      { key:'crp', label:'CRP', unit:'mg/L', min:0, max:5, dp:0 },
      { key:'pct', label:'Procalcitonine', unit:'ng/mL', min:0, max:0.1, dp:2 },
      { key:'lactate', label:'Lactate', unit:'mmol/L', min:0.5, max:2.0, dp:1 }
    ]
  },
  cardiac: {
    label:'Marqueurs cardiaques', icon:'❤️',
    items:[
      { key:'troponin', label:'Troponine', unit:'ng/L', min:0, max:14, dp:0 },
      { key:'ck', label:'CK', unit:'U/L', min:30, max:200, dp:0 },
      { key:'bnp', label:'BNP', unit:'pg/mL', min:0, max:100, dp:0 }
    ]
  },
  abg: {
    label:'Gaz du sang', icon:'🫁',
    items:[
      { key:'ph', label:'pH', unit:'', min:7.35, max:7.45, dp:2 },
      { key:'pco2', label:'pCO₂', unit:'mmHg', min:35, max:45, dp:0 },
      { key:'po2', label:'pO₂', unit:'mmHg', min:80, max:100, dp:0 },
      { key:'hco3', label:'HCO₃⁻', unit:'mmol/L', min:22, max:26, dp:0 },
      { key:'sao2', label:'SaO₂', unit:'%', min:95, max:100, dp:0 }
    ]
  },
  urine: {
    label:'Bandelette urinaire', icon:'🚽',
    items:[
      { key:'urine_leuko', label:'Leucocytes urinaires', kind:'text', values:['Négatif','Traces','+','++','+++'] },
      { key:'urine_nit', label:'Nitrites', kind:'text', values:['Négatif','Positif'] },
      { key:'urine_prot', label:'Protéines', kind:'text', values:['Négatif','Traces','+','++','+++'] },
      { key:'urine_glu', label:'Glucose urinaire', kind:'text', values:['Négatif','Traces','+','++','+++'] },
      { key:'urine_ket', label:'Cétones', kind:'text', values:['Négatif','Traces','+','++','+++'] },
      { key:'urine_blood', label:'Sang', kind:'text', values:['Négatif','Traces','+','++','+++'] },
      { key:'urine_ph', label:'pH urinaire', unit:'', min:5.0, max:8.0, dp:1 }
    ]
  },
  micro: {
    label:'Microbiologie', icon:'🦠',
    items:[
      { key:'blood_culture', label:'Hémocultures', kind:'text', values:['Négatives à ce stade','Positives cocci Gram +','Positives bacilles Gram -','En cours'] },
      { key:'urine_culture', label:'ECBU', kind:'text', values:['Stérile','Contamination probable','E. coli > 10^5 UFC/mL','Flore polymicrobienne'] },
      { key:'covid', label:'PCR virale', kind:'text', values:['Négative','Positive','Indéterminée'] }
    ]
  },
  special: {
    label:'Examens complémentaires', icon:'➕',
    items:[
      { key:'bhcg', label:'β-hCG', unit:'UI/L', min:0, max:5, dp:0, optional:'pregnancy' },
      { key:'ethanol', label:'Éthanol', unit:'g/L', min:0, max:0.1, dp:2, optional:'tox' },
      { key:'cocaine_screen', label:'Cocaïne', kind:'text', values:['Négatif','Positif'], optional:'tox' },
      { key:'opiates_screen', label:'Opiacés', kind:'text', values:['Négatif','Positif'], optional:'tox' },
      { key:'thc_screen', label:'Cannabis (THC)', kind:'text', values:['Négatif','Positif'], optional:'tox' },
      { key:'amphetamines_screen', label:'Amphétamines', kind:'text', values:['Négatif','Positif'], optional:'tox' },
      { key:'benzodiazepines_screen', label:'Benzodiazépines', kind:'text', values:['Négatif','Positif'], optional:'tox' }
    ]
  }
};

const PANEL_DESCRIPTIONS = {
  cbc:         "NFS complète : leucocytes, hématies, hémoglobine, hématocrite, plaquettes, polynucléaires, lymphocytes.",
  chemistry:   "Ionogramme sanguin, bilan rénal (urée, créatinine), glycémie et calcémie.",
  liver:       "Transaminases (ASAT/ALAT), PAL, GGT, bilirubine, albumine — évaluation de la cytolyse et de la cholestase.",
  coag:        "TP, INR, TCA, fibrinogène — exploration de l'hémostase et de la coagulation.",
  inflammation:"CRP, procalcitonine (PCT), lactate — marqueurs du syndrome inflammatoire et de l'infection bactérienne.",
  cardiac:     "Troponine, CK, BNP — biomarqueurs de souffrance myocardique et d'insuffisance cardiaque.",
  abg:         "pH, pCO₂, pO₂, HCO₃⁻, SaO₂ — analyse des gaz du sang artériel et des troubles acido-basiques.",
  urine:       "Bandelette réactive : leucocytes, nitrites, protéines, glucose, cétones, sang, pH urinaire.",
  micro:       "Hémocultures, ECBU, PCR virale — résultats microbiologiques qualitatifs.",
};

let panelConfig = {};

function initPanelConfig() {
  Object.entries(LAB_DEFS).forEach(([key, panel]) => {
    panelConfig[key] = {
      enabled: true,
      items: Object.fromEntries(panel.items.map(item => [item.key, true]))
    };
  });
}

function buildPanelSettings() {
  const container = document.getElementById('panelSettingsContainer');
  container.innerHTML = '';
  Object.entries(LAB_DEFS).forEach(([panelKey, panel]) => {
    const cfg = panelConfig[panelKey];
    const block = document.createElement('div');
    block.className = 'panel-settings-block';
    block.dataset.panel = panelKey;

    const itemRows = panel.items.map(item => `
      <label class="panel-item-check">
        <input type="checkbox" data-panel="${panelKey}" data-item="${item.key}" ${cfg.items[item.key] ? 'checked' : ''}>
        <span>${item.label}</span>
        <span class="panel-item-unit">${item.unit || (item.values ? 'qualitatif' : '')}</span>
      </label>
    `).join('');

    block.innerHTML = `
      <div class="panel-settings-header">
        <span class="panel-settings-icon">${panel.icon}</span>
        <span class="panel-settings-label">${panel.label}</span>
        <label class="panel-settings-toggle" title="Activer / désactiver ce panneau">
          <input type="checkbox" data-panel-toggle="${panelKey}" ${cfg.enabled ? 'checked' : ''}>
          <span class="toggle-slider"></span>
        </label>
        <span class="panel-settings-chevron">▼</span>
      </div>
      <div class="panel-settings-items ${!cfg.enabled ? 'panel-disabled-overlay' : ''}">
        <div class="panel-settings-desc">${PANEL_DESCRIPTIONS[panelKey] || ''}</div>
        ${itemRows}
      </div>
    `;
    container.appendChild(block);

    block.querySelector('.panel-settings-header').addEventListener('click', (e) => {
      if (e.target.closest('.panel-settings-toggle')) return;
      block.classList.toggle('open');
    });

    block.querySelector(`[data-panel-toggle="${panelKey}"]`).addEventListener('change', (e) => {
      panelConfig[panelKey].enabled = e.target.checked;
      const itemsDiv = block.querySelector('.panel-settings-items');
      itemsDiv.classList.toggle('panel-disabled-overlay', !e.target.checked);
      if (Object.keys(currentResults).length) generateReport(document.getElementById('preset').value);
    });
  });

  container.addEventListener('change', (e) => {
    const input = e.target;
    if (!input.dataset.item) return;
    panelConfig[input.dataset.panel].items[input.dataset.item] = input.checked;
    if (Object.keys(currentResults).length) generateReport(document.getElementById('preset').value);
  });
}

function selectAllItems(panelKey, checked) {
  Object.keys(panelConfig[panelKey].items).forEach(k => panelConfig[panelKey].items[k] = checked);
  buildPanelSettings();
  if (Object.keys(currentResults).length) generateReport(document.getElementById('preset').value);
}

const PRESETS = {
  normal: {},
  infection: {
    wbc:[11,18], neut:[8,15], crp:[40,180], pct:[0.2,2.5], lactate:[1.2,2.8], urine_leuko:['+','++','+++'], urine_nit:['Négatif','Positif'], blood_culture:['En cours','Négatives à ce stade']
  },
  dehydration: {
    na:[146,154], urea:[8,16], creat:[100,165], hb:[15,18], hct:[46,56], lactate:[1.5,3.0]
  },
  anemia: {
    hb:[6.5,10.5], hct:[20,34], rbc:[2.5,3.9], pt:[70,100], wbc:[4,10], plt:[120,400]
  },
  renal_failure: {
    creat:[180,650], urea:[12,35], k:[5.2,6.8], hco3:[15,21], ph:[7.20,7.34], na:[130,145]
  },
  sepsis: {
    wbc:[14,28], neut:[10,22], crp:[120,320], pct:[2,25], lactate:[2.5,8], pt:[45,75], inr:[1.3,2.4], fibrinogen:[1.2,5.5], creat:[120,280], blood_culture:['Positives cocci Gram +','Positives bacilles Gram -','En cours']
  },
  diabetes: {
    glucose:[12,32], urine_glu:['++','+++'], urine_ket:['Négatif','+','++','+++'], na:[128,138], k:[3.0,5.8], ph:[7.05,7.35], hco3:[8,22]
  },
  liver_injury: {
    ast:[90,850], alt:[110,950], alp:[110,300], ggt:[80,500], bili:[25,180], albumin:[24,38], pt:[40,80], inr:[1.2,2.2]
  },
  trauma: {
    hb:[6.8,13], hct:[21,38], plt:[80,250], lactate:[2,7], ck:[250,2200], troponin:[0,120], pt:[45,85], inr:[1.0,2.0], urine_blood:['+','++','+++']
  }
};

const BLOOD_GROUPS = ['O+','O−','A+','A−','B+','B−','AB+','AB−'];
const DRUG_LABELS = {
  cannabis:'Cannabis', cocaine:'Cocaïne', amphetamines:'Amphétamines / méth', mdma:'MDMA / ecstasy', opiates:'Opiacés', benzodiazepines:'Benzodiazépines', alcohol:'Alcool'
};
const DRUG_EFFECTS = {
  cannabis: { tox:['thc_screen'], text:{ thc_screen:'Positif' }, bump:{} },
  cocaine: { tox:['cocaine_screen'], text:{ cocaine_screen:'Positif' }, bump:{ troponin:[10,120], ck:[80,600], lactate:[0.4,2.2], glucose:[0.4,1.8], crp:[0,8] } },
  amphetamines: { tox:['amphetamines_screen'], text:{ amphetamines_screen:'Positif' }, bump:{ ck:[150,1200], lactate:[0.6,3.2], glucose:[0.6,2.5], troponin:[0,40], ast:[10,60], alt:[10,50] } },
  mdma: { tox:['amphetamines_screen'], text:{ amphetamines_screen:'Positif' }, bump:{ na:[-10,-2], ck:[200,1500], lactate:[0.8,3.0], glucose:[0.5,2.0], ast:[10,70], alt:[10,70] } },
  opiates: { tox:['opiates_screen'], text:{ opiates_screen:'Positif' }, bump:{ pco2:[5,20], ph:[-0.12,-0.02], po2:[-20,-5], hco3:[0,3] } },
  benzodiazepines: { tox:['benzodiazepines_screen'], text:{ benzodiazepines_screen:'Positif' }, bump:{ pco2:[2,10], ph:[-0.08,-0.01], po2:[-12,-2] } },
  alcohol: { tox:['ethanol'], bump:{ ethanol:[0.3,2.8], ggt:[20,180], ast:[10,120], alt:[5,70], glucose:[-1.0,0.5] } }
};

let currentResults = {};
let currentBloodGroup = 'O+';

function rand(min, max) { return Math.random() * (max - min) + min; }
function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
function roundTo(v, dp) { return Number(v.toFixed(dp)); }
function fmt(v, dp=0) { return typeof v === 'number' ? Number(v).toFixed(dp) : String(v); }

function setDefaultDatetime() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  document.getElementById('sampleDate').value = now.toISOString().slice(0,16);
}

function normalizeStatus(item, value) {
  if (item.kind === 'text') {
    const abnormalTerms = ['Positif','+','++','+++','Traces','Indéterminée','Contamination probable','E. coli > 10^5 UFC/mL','Positives cocci Gram +','Positives bacilles Gram -'];
    const status = abnormalTerms.includes(String(value)) ? 'high' : 'normal';
    return { status, range: item.values ? item.values.join(' / ') : '—' };
  }
  if (value < item.min) return { status:'low', range:`${fmt(item.min,item.dp)}–${fmt(item.max,item.dp)}` };
  if (value > item.max) return { status:'high', range:`${fmt(item.min,item.dp)}–${fmt(item.max,item.dp)}` };
  return { status:'normal', range:`${fmt(item.min,item.dp)}–${fmt(item.max,item.dp)}` };
}

function getSelectedDrugs() {
  return Array.from(document.querySelectorAll('#drugTypesRow input[type="checkbox"]:checked')).map(x => x.value);
}

function resolveBloodGroup() {
  const mode = document.getElementById('bloodGroupMode').value;
  if (mode === 'manual') {
    currentBloodGroup = document.getElementById('bloodGroup').value;
    return currentBloodGroup;
  }
  if (!currentBloodGroup) currentBloodGroup = pick(BLOOD_GROUPS);
  document.getElementById('bloodGroup').value = currentBloodGroup;
  return currentBloodGroup;
}

function generateValue(item, presetName, severity) {
  const preset = PRESETS[presetName] || {};
  const override = preset[item.key];

  if (item.kind === 'text') {
    if (Array.isArray(override)) return pick(override);
    return pick(item.values);
  }

  if (override && Array.isArray(override) && override.length === 2) {
    let [a,b] = override;
    let spread = (b - a) * 0.15;
    if (severity === 1) { a += spread; b -= spread; }
    if (severity === 3) {
      a -= spread * 0.3;
      b += spread * 0.3;
    }
    return roundTo(rand(a,b), item.dp);
  }

  const mid = (item.min + item.max) / 2;
  const half = (item.max - item.min) / 2;
  const margin = Math.max(half * 0.75, 0.001);
  return roundTo(clamp(rand(mid - margin, mid + margin), item.min, item.max), item.dp);
}

function shouldInclude(item) {
  if (item.optional === 'pregnancy' && !document.getElementById('includePregnancy').checked) return false;
  if (item.optional === 'tox' && !document.getElementById('includeTox').checked) return false;
  if (item.key === 'blood_culture' || item.key === 'urine_culture' || item.key === 'covid') {
    return document.getElementById('includeCulture').checked;
  }
  return true;
}

function applyDrugEffects(presetName) {
  const level = document.getElementById('drugDeclared').value;
  const drugs = getSelectedDrugs();
  if (level === 'none' || !drugs.length) return;

  const intensity = level === 'casual' ? 0.45 : level === 'regular' ? 0.8 : 1.15;
  const flat = Object.values(currentResults).flat();
  const byKey = Object.fromEntries(flat.map(x => [x.key, x]));

  if (drugs.length && !document.getElementById('includeTox').checked) {
    document.getElementById('includeTox').checked = true;
    Object.entries(LAB_DEFS).forEach(([panelKey, panel]) => {
      if (!currentResults[panelKey]) currentResults[panelKey] = [];
      panel.items.filter(shouldInclude).forEach(item => {
        if (!currentResults[panelKey].some(x => x.key === item.key)) {
          const value = generateValue(item, presetName, Number(document.getElementById('severity').value || 2));
          currentResults[panelKey].push({ ...item, value, ...normalizeStatus(item, value) });
        }
      });
    });
  }

  drugs.forEach(drug => {
    const fx = DRUG_EFFECTS[drug];
    if (!fx) return;
    Object.entries(fx.text || {}).forEach(([key, value]) => {
      if (byKey[key]) {
        byKey[key].value = value;
        Object.assign(byKey[key], normalizeStatus(byKey[key], byKey[key].value));
      }
    });
    Object.entries(fx.bump || {}).forEach(([key, range]) => {
      if (!byKey[key]) return;
      const item = byKey[key];
      const delta = rand(range[0], range[1]) * intensity;
      item.value = roundTo(Number(item.value) + delta, item.dp || 0);
      Object.assign(item, normalizeStatus(item, item.value));
    });
  });
}

function generateReport(forcePreset = null) {
  const presetName = forcePreset || document.getElementById('preset').value;
  const severity = Number(document.getElementById('severity').value || 2);
  if (document.getElementById('bloodGroupMode').value === 'random') currentBloodGroup = pick(BLOOD_GROUPS);
  else currentBloodGroup = document.getElementById('bloodGroup').value;
  currentResults = {};

  Object.entries(LAB_DEFS).forEach(([panelKey, panel]) => {
    if (!panelConfig[panelKey]?.enabled) return;
    currentResults[panelKey] = panel.items
      .filter(item => shouldInclude(item) && panelConfig[panelKey]?.items[item.key] !== false)
      .map(item => {
        const value = generateValue(item, presetName, severity);
        return { ...item, value, ...normalizeStatus(item, value) };
      });
  });

  applyDrugEffects(presetName);
  renderReport(presetName);
}

function collectMeta() {
  const sexRaw = document.getElementById('patientSex').value;
  const sex = sexRaw === 'M' ? 'Homme' : sexRaw === 'F' ? 'Femme' : 'Non précisé';
  const dateVal = document.getElementById('sampleDate').value;
  let dateText = '—';
  if (dateVal) {
    const d = new Date(dateVal);
    dateText = d.toLocaleString('fr-FR');
  }
  const declared = document.getElementById('drugDeclared').value;
  const drugs = getSelectedDrugs();
  const declaredText = declared === 'none'
    ? 'Aucune déclarée'
    : `${declared === 'casual' ? 'Occasionnelle' : declared === 'regular' ? 'Régulière' : 'Importante / récente'}${drugs.length ? ' — ' + drugs.map(d => DRUG_LABELS[d]).join(', ') : ''}`;
  return {
    patient: document.getElementById('patientName').value.trim() || '—',
    ageSex: `${document.getElementById('patientAge').value.trim() || 'Âge non précisé'} / ${sex}`,
    bloodGroup: resolveBloodGroup(),
    date: dateText,
    context: document.getElementById('context').value.trim() || 'Aucun contexte saisi',
    drugUse: declaredText,
    doctor: document.getElementById('doctorName').value.trim() || '—'
  };
}

function buildInterpretation(presetName) {
  const flat = Object.values(currentResults).flat();
  const highs = flat.filter(x => x.status === 'high');
  const lows = flat.filter(x => x.status === 'low');
  const notes = [];

  const byKey = Object.fromEntries(flat.map(x => [x.key, x]));

  if (byKey.wbc && byKey.wbc.status === 'high') notes.push('Hyperleucocytose compatible avec un contexte infectieux ou inflammatoire.');
  if (byKey.crp && Number(byKey.crp.value) > 50) notes.push('Syndrome inflammatoire biologique significatif.');
  if (byKey.pct && Number(byKey.pct.value) >= 0.5) notes.push('Procalcitonine élevée, orientation bactérienne possible selon le contexte clinique.');
  if (byKey.hb && byKey.hb.status === 'low') notes.push('Anémie biologique présente.');
  if (byKey.creat && byKey.creat.status === 'high') notes.push('Altération de la fonction rénale.');
  if (byKey.k && (byKey.k.status === 'high' || byKey.k.status === 'low')) notes.push('Trouble de la kaliémie à surveiller.');
  if (byKey.glucose && byKey.glucose.status === 'high') notes.push('Hyperglycémie.');
  if (byKey.ast && byKey.ast.status === 'high' || byKey.alt && byKey.alt.status === 'high') notes.push('Perturbation du bilan hépatique.');
  if (byKey.inr && byKey.inr.status === 'high') notes.push('Trouble de coagulation avec INR allongé.');
  if (byKey.lactate && Number(byKey.lactate.value) > 2) notes.push('Hyperlactatémie pouvant témoigner d’une hypoperfusion tissulaire.');
  if (byKey.troponin && Number(byKey.troponin.value) > 14) notes.push('Troponine élevée, corrélation clinique et ECG nécessaires.');
  if (byKey.urine_nit && String(byKey.urine_nit.value) === 'Positif') notes.push('Nitriturie positive, compatible avec une infection urinaire.');
  if (byKey.urine_ket && ['+','++','+++'].includes(String(byKey.urine_ket.value))) notes.push('Cétonurie présente.');
  if (byKey.bhcg && Number(byKey.bhcg.value) > 5) notes.push('β-hCG positive.');
  if (byKey.blood_culture && String(byKey.blood_culture.value).includes('Positives')) notes.push('Microbiologie positive sur hémocultures.');
  if (byKey.cocaine_screen && byKey.cocaine_screen.value === 'Positif') notes.push('Dépistage cocaïne positif.');
  if (byKey.thc_screen && byKey.thc_screen.value === 'Positif') notes.push('Dépistage cannabis positif.');
  if (byKey.amphetamines_screen && byKey.amphetamines_screen.value === 'Positif') notes.push('Dépistage amphétamines positif.');
  if (byKey.opiates_screen && byKey.opiates_screen.value === 'Positif') notes.push('Dépistage opiacés positif.');
  if (byKey.benzodiazepines_screen && byKey.benzodiazepines_screen.value === 'Positif') notes.push('Dépistage benzodiazépines positif.');
  if (byKey.ethanol && Number(byKey.ethanol.value) > 0.1) notes.push('Alcoolémie positive.');

  if (!notes.length) {
    if (presetName === 'normal') return 'Bilan globalement dans les limites attendues, sans anomalie biologique marquée.';
    return 'Anomalies biologiques présentes, à interpréter avec le tableau clinique et l’évolution du patient.';
  }

  return notes.join('\n');
}

function renderSummary() {
  const flat = Object.values(currentResults).flat();
  const abnormal = flat.filter(x => x.status !== 'normal').length;
  const high = flat.filter(x => x.status === 'high').length;
  const low = flat.filter(x => x.status === 'low').length;

  document.getElementById('summaryStats').innerHTML = `
    <div class="stat-card ${abnormal ? 'blue' : 'green'}">
      <div class="s-label">Anomalies</div>
      <div class="s-val">${abnormal}</div>
      <div class="s-sub">Valeurs hors intervalle</div>
    </div>
    <div class="stat-card">
      <div class="s-label">Élevées</div>
      <div class="s-val">${high}</div>
      <div class="s-sub">Résultats au-dessus</div>
    </div>
    <div class="stat-card">
      <div class="s-label">Basses</div>
      <div class="s-val">${low}</div>
      <div class="s-sub">Résultats au-dessous</div>
    </div>
  `;
}

function renderReport(presetName) {
  const meta = collectMeta();
  document.getElementById('metaPatient').textContent = meta.patient;
  document.getElementById('metaAgeSex').textContent = meta.ageSex;
  document.getElementById('metaBloodGroup').textContent = meta.bloodGroup;
  document.getElementById('metaDate').textContent = meta.date;
  document.getElementById('metaContext').textContent = meta.context;
  document.getElementById('metaDrugUse').textContent = meta.drugUse;
  document.getElementById('metaDoctor').textContent = meta.doctor;

  const badge = document.getElementById('profileBadge');
  const presetLabel = document.querySelector(`#preset option[value="${presetName}"]`)?.textContent || presetName;
  badge.textContent = `Profil : ${presetLabel}`;

  renderSummary();
  document.getElementById('interpretationBox').textContent = buildInterpretation(presetName);

  const onlyAbnormal = document.getElementById('showOnlyAbnormal').checked;
  const html = Object.entries(LAB_DEFS).map(([panelKey, panel]) => {
    const rows = (currentResults[panelKey] || []).filter(item => !onlyAbnormal || item.status !== 'normal');
    if (!rows.length) return '';
    return `
      <div class="lab-panel card" style="padding:0; overflow:hidden;">
        <div style="padding:14px 16px; border-bottom:1px solid var(--line); font-family:var(--font-head); font-weight:800;">${panel.icon} ${panel.label}</div>
        <div class="lab-table-wrap">
          <table class="lab-table">
            <thead>
              <tr>
                <th>Analyse</th>
                <th>Résultat</th>
                <th>Unité</th>
                <th>Référence</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              ${rows.map(item => `
                <tr>
                  <td>${item.label}</td>
                  <td class="lab-value ${item.status === 'high' ? 'lab-high' : item.status === 'low' ? 'lab-low' : 'lab-normal'}" contenteditable="true" data-panel="${panelKey}" data-key="${item.key}">${item.value}</td>
                  <td>${item.unit || '—'}</td>
                  <td>${item.range}</td>
                  <td><span class="lab-badge ${item.status}">${item.status === 'high' ? '↑ Élevé' : item.status === 'low' ? '↓ Bas' : '✓ Normal'}</span></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }).join('');

  document.getElementById('resultsContainer').innerHTML = html || '<div class="card">Aucun résultat à afficher avec les filtres actuels.</div>';
  attachEditableHandlers();
}

function attachEditableHandlers() {
  document.querySelectorAll('[contenteditable="true"]').forEach(cell => {
    cell.addEventListener('blur', () => {
      const panel = cell.dataset.panel;
      const key = cell.dataset.key;
      const entry = (currentResults[panel] || []).find(x => x.key === key);
      if (!entry) return;
      entry.value = entry.kind === 'text' ? cell.textContent.trim() : Number(String(cell.textContent).replace(',', '.'));
      const normalized = normalizeStatus(entry, entry.value);
      entry.status = normalized.status;
      entry.range = normalized.range;
      renderReport(document.getElementById('preset').value);
    });
  });
}

function copyReport() {
  const meta = collectMeta();
  const blocks = [];
  blocks.push('COMPTE RENDU LABORATOIRE');
  blocks.push(`Patient: ${meta.patient}`);
  blocks.push(`Âge / sexe: ${meta.ageSex}`);
  blocks.push(`Groupe sanguin: ${meta.bloodGroup}`);
  blocks.push(`Prélèvement: ${meta.date}`);
  blocks.push(`Contexte: ${meta.context}`);
  blocks.push(`Consommation déclarée: ${meta.drugUse}`);
  blocks.push(`Validé par: ${meta.doctor}`);
  blocks.push('');
  blocks.push('Interprétation:');
  blocks.push(document.getElementById('interpretationBox').textContent.trim());
  blocks.push('');

  Object.entries(LAB_DEFS).forEach(([panelKey, panel]) => {
    const rows = currentResults[panelKey] || [];
    if (!rows.length) return;
    blocks.push(`[${panel.label}]`);
    rows.forEach(item => {
      blocks.push(`- ${item.label}: ${item.value} ${item.unit || ''} (${item.status === 'normal' ? 'normal' : item.status === 'high' ? 'élevé' : 'bas'})`);
    });
    blocks.push('');
  });

  navigator.clipboard.writeText(blocks.join('\n')).then(() => {
    const btn = document.getElementById('copyBtn');
    const old = btn.innerHTML;
    btn.innerHTML = '✅ Copié';
    setTimeout(() => btn.innerHTML = old, 1600);
  });
}

document.getElementById('generateBtn').addEventListener('click', () => generateReport());
document.getElementById('randomBtn').addEventListener('click', () => {
  const presets = Object.keys(PRESETS).filter(x => x !== 'normal');
  const chosen = pick(presets);
  document.getElementById('preset').value = chosen;
  document.getElementById('severity').value = String(Math.ceil(rand(1,3.99)));
  generateReport(chosen);
});
document.getElementById('normalBtn').addEventListener('click', () => {
  document.getElementById('preset').value = 'normal';
  document.getElementById('severity').value = '1';
  generateReport('normal');
});

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function statusLabel(status) {
  if (status === 'high') return '↑ élevé';
  if (status === 'low') return '↓ bas';
  return '✓ normal';
}

function buildExportStage(presetName) {
  const stage = document.getElementById('exportStage');
  const meta = collectMeta();
  const presetLabel = document.querySelector(`#preset option[value="${presetName}"]`)?.textContent || presetName;
  const summaryCards = Array.from(document.querySelectorAll('#summaryStats .stat-card')).map(card => ({
    label: card.querySelector('.s-label')?.textContent?.trim() || '',
    value: card.querySelector('.s-val')?.textContent?.trim() || '',
    sub: card.querySelector('.s-sub')?.textContent?.trim() || ''
  }));

  const interpretationText = document.getElementById('interpretationBox').textContent.trim() || 'Aucune interprétation générée.';
  const resultPanels = [];

  Object.entries(LAB_DEFS).forEach(([panelKey, panel]) => {
    const rows = currentResults[panelKey] || [];
    if (!rows.length) return;
    resultPanels.push({ key: panelKey, panel, rows });
  });

  let resultCols = 2;
  if (resultPanels.length >= 5) resultCols = 3;
  if (resultPanels.length >= 8) resultCols = 4;

  const panelHtml = resultPanels.map(({ panel, rows }) => `
    <section class="export-panel">
      <div class="export-panel-head">${panel.icon} ${escapeHtml(panel.label)}</div>
      <div class="export-rows">
        ${rows.map(item => `
          <div class="export-row minimal ${item.status}">
            <div>
              <div class="label">${escapeHtml(item.label)}</div>
              <div class="ref">${escapeHtml(item.unit || '—')} · ${escapeHtml(item.range)}</div>
            </div>
            <div>
              <div class="value">${escapeHtml(item.value)}</div>
              <div class="status">${item.status === 'normal' ? '✓' : item.status === 'high' ? '↑' : '↓'} ${escapeHtml(statusLabel(item.status))}</div>
            </div>
          </div>
        `).join('')}
      </div>
    </section>
  `).join('');

  stage.innerHTML = `
    <div class="export-page scale-mode">
      <div class="export-inner">
        <div class="export-top">
          <div>
            <div class="export-title">Compte rendu de laboratoire</div>
            <div class="export-sub">Format unique 1920 × 1080 px</div>
          </div>
          <div class="export-pill">Profil : ${escapeHtml(presetLabel)}</div>
        </div>

        <div class="export-layout">
          <aside class="export-sidebar">
            <section class="export-card">
              <div class="export-card-title">Informations patient</div>
              <div class="export-meta-list">
                <div class="export-mini"><div class="k">Patient</div><div class="v">${escapeHtml(meta.patient)}</div></div>
                <div class="export-mini"><div class="k">Âge / sexe</div><div class="v">${escapeHtml(meta.ageSex)}</div></div>
                <div class="export-mini"><div class="k">Groupe sanguin</div><div class="v">${escapeHtml(meta.bloodGroup)}</div></div>
                <div class="export-mini"><div class="k">Prélèvement</div><div class="v">${escapeHtml(meta.date)}</div></div>
                <div class="export-mini"><div class="k">Contexte</div><div class="v">${escapeHtml(meta.context)}</div></div>
                <div class="export-mini"><div class="k">Consommation</div><div class="v">${escapeHtml(meta.drugUse)}</div></div>
                <div class="export-mini"><div class="k">Validé par</div><div class="v">${escapeHtml(meta.doctor)}</div></div>
                <div class="export-mini"><div class="k">Mode</div><div class="v">Résultats complets</div></div>
              </div>
            </section>

            <section class="export-card">
              <div class="export-card-title">Résumé</div>
              <div class="export-summary-list">
                ${summaryCards.map(card => `<div class="export-mini"><div class="k">${escapeHtml(card.label)}</div><div class="v">${escapeHtml(card.value)}</div><div class="s">${escapeHtml(card.sub)}</div></div>`).join('')}
              </div>
            </section>

            <section class="export-card export-interpret">
              <div class="export-card-title">Interprétation</div>
              <div class="body">${escapeHtml(interpretationText)}</div>
            </section>
          </aside>

          <main class="export-results">
            <div class="export-panels cols-${resultCols}">${panelHtml}</div>
          </main>
        </div>

        <div class="export-foot">SAMR — export laboratoire</div>
      </div>
    </div>
  `;

  const page = stage.querySelector('.export-page');
  const inner = stage.querySelector('.export-inner');
  inner.style.transform = 'scale(1)';
  inner.style.width = '1864px';

  const maxW = 1920 - 16;
  const maxH = 1080 - 16;
  const scale = Math.min(1, maxW / inner.scrollWidth, maxH / inner.scrollHeight);
  inner.style.transform = `scale(${scale})`;

  return page;
}

async function uploadPngDataUrl(dataUrl, filename) {
  const form = new FormData();
  form.append('action', 'upload_lab_png');
  form.append('image', dataUrl);
  form.append('filename', filename);

  const response = await fetch(window.location.href, {
    method: 'POST',
    body: form,
    credentials: 'same-origin'
  });

  const raw = await response.text();
  let json = null;
  try { json = JSON.parse(raw); } catch (e) { throw new Error(raw || 'Réponse serveur invalide'); }
  if (!response.ok || !json.ok) throw new Error(json?.error || 'Upload serveur impossible');
  return json;
}

function showExportFeedback(ok, message, url = '') {
  const box = document.getElementById('exportFeedback');
  box.className = 'export-feedback show ' + (ok ? 'ok' : 'err');
  box.innerHTML = ok
    ? `✅ Export envoyé : <a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(url)}</a>`
    : `⚠️ ${escapeHtml(message)}`;
}

async function exportPng() {
  const btn = document.getElementById('pngBtn');
  const old = btn.innerHTML;
  try {
    btn.innerHTML = '⏳ Export...';
    if (!window.html2canvas) throw new Error('html2canvas indisponible');
    const presetName = document.getElementById('preset').value;
    const page = buildExportStage(presetName);
    const canvas = await window.html2canvas(page, {
      backgroundColor: '#06101b',
      width: 1920,
      height: 1080,
      scale: 1,
      useCORS: true,
      logging: false
    });
    const patient = (document.getElementById('patientName').value.trim() || 'patient').replace(/[^a-z0-9-_]+/gi,'_');
    const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    const filename = `bilan_laboratoire_${patient}_${stamp}.png`;
    const dataUrl = canvas.toDataURL('image/png');
    const uploaded = await uploadPngDataUrl(dataUrl, filename);
    showExportFeedback(true, '', uploaded.url);
    try { window.prompt('Lien du fichier :', uploaded.url); } catch (_) {}
    btn.innerHTML = '✅ PNG uploadé';
  } catch (e) {
    showExportFeedback(false, e?.message || 'Export indisponible');
    btn.innerHTML = '⚠️ Export indisponible';
  }
  setTimeout(() => btn.innerHTML = old, 2200);
}

document.getElementById('copyBtn').addEventListener('click', copyReport);
document.getElementById('pngBtn').addEventListener('click', exportPng);
document.getElementById('printBtn').addEventListener('click', () => window.print());
['patientName','patientAge','patientSex','bloodGroupMode','bloodGroup','context','sampleDate','doctorName','preset','severity','includePregnancy','includeTox','includeCulture','showOnlyAbnormal','drugDeclared'].forEach(id => {
  document.getElementById(id).addEventListener('input', () => {
    if (Object.keys(currentResults).length) renderReport(document.getElementById('preset').value);
  });
  document.getElementById(id).addEventListener('change', () => {
    if (id.startsWith('include') || id === 'preset' || id === 'severity') generateReport(document.getElementById('preset').value);
    else if (Object.keys(currentResults).length) renderReport(document.getElementById('preset').value);
  });
});

document.querySelectorAll('#drugTypesRow input[type="checkbox"]').forEach(input => {
  input.addEventListener('change', () => {
    input.closest('.lab-chip')?.classList.toggle('active', input.checked);
    generateReport(document.getElementById('preset').value);
  });
});

document.querySelectorAll('.settings-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
  });
});

initPanelConfig();
buildPanelSettings();
setDefaultDatetime();
generateReport('normal');
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<?php samr_layout_end(); ?>
