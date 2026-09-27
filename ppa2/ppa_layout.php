<?php

function ppa_layout_head(string $title): void { ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= ppa_h($title) ?> — SAMR PPA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
  <style>
:root {
  --bg:       #080c10;
  --panel:    #0d1219;
  --panel2:   #0a0f15;
  --line:     #182030;
  --line2:    #1e2d40;
  --text:     #dce8f5;
  --muted:    #5a7a96;
  --accent:   #3b9eff;
  --accent2:  #00d4aa;
  --danger:   #ff4f4f;
  --font-head:'Syne', sans-serif;
  --font-body:'DM Sans', sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);font-size:14px;line-height:1.6;background:var(--bg);color:var(--text);min-height:100vh}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 900px 600px at 15% 0%,rgba(59,158,255,.07) 0%,transparent 65%),radial-gradient(ellipse 600px 500px at 85% 100%,rgba(0,212,170,.05) 0%,transparent 60%);pointer-events:none;z-index:0}
a{color:var(--accent);text-decoration:none}
.wrap{max-width:740px;margin:0 auto;padding:32px 20px;position:relative;z-index:1}
.card{background:linear-gradient(160deg,rgba(13,18,25,.95),rgba(10,15,21,.98));border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:14px}
.muted{color:var(--muted)}
.pill{display:inline-block;padding:2px 10px;border:1px solid var(--line2);border-radius:999px;font-size:11px;font-weight:700}
.pill-green{border-color:rgba(0,212,170,.3);color:var(--accent2);background:rgba(0,212,170,.06)}
.pill-blue{border-color:rgba(59,158,255,.3);color:var(--accent);background:rgba(59,158,255,.06)}
.pill-red{border-color:rgba(255,79,79,.3);color:#ff7a7a;background:rgba(255,79,79,.06)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;border:1px solid rgba(59,158,255,.25);background:rgba(59,158,255,.1);color:var(--text);font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s}
.btn:hover{background:rgba(59,158,255,.18);border-color:rgba(59,158,255,.35)}
.btn.success{background:rgba(0,212,170,.12);border-color:rgba(0,212,170,.3);color:var(--accent2)}
.btn.success:hover{background:rgba(0,212,170,.2)}
.btn.danger{background:rgba(255,79,79,.1);border-color:rgba(255,79,79,.3);color:#ff7a7a}
.btn.primary{background:linear-gradient(135deg,rgba(59,158,255,.25),rgba(0,212,170,.15));border-color:rgba(59,158,255,.4);font-weight:700}
.btn:disabled{opacity:.4;cursor:not-allowed}
.inp{width:100%;padding:9px 13px;border-radius:10px;border:1px solid var(--line2);background:rgba(255,255,255,.03);color:var(--text);font-family:var(--font-body);font-size:13px;transition:border-color .15s}
.inp:focus{outline:none;border-color:rgba(59,158,255,.4);background:rgba(59,158,255,.04)}
select.inp{cursor:pointer}

/* Header SAMR */
.ppa-header{display:flex;align-items:center;gap:14px;padding:16px 20px;background:linear-gradient(160deg,rgba(13,18,25,.98),rgba(8,12,16,.98));border:1px solid var(--line);border-radius:14px;margin-bottom:24px}
.ppa-header-logo{width:44px;height:44px;border-radius:11px;background:linear-gradient(135deg,rgba(59,158,255,.2),rgba(0,212,170,.15));border:1px solid rgba(59,158,255,.3);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.ppa-header-title{font-family:var(--font-head);font-size:16px;font-weight:800}
.ppa-header-sub{font-size:12px;color:var(--muted);margin-top:2px}

/* Flash */
.flash{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:14px}
.flash.ok{background:rgba(0,212,170,.08);border:1px solid rgba(0,212,170,.3);color:var(--accent2)}
.flash.err{background:rgba(255,79,79,.08);border:1px solid rgba(255,79,79,.3);color:#ff7a7a}
.flash.warn{background:rgba(255,180,50,.08);border:1px solid rgba(255,180,50,.3);color:#ffb060}

/* Question */
.question-card{border-left:3px solid var(--line2);transition:border-color .15s}
.question-card.answered{border-left-color:var(--accent2)}
.question-number{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:8px}
.question-text{font-size:14px;font-weight:600;line-height:1.6;margin-bottom:14px}
.choice-label{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:10px;border:1px solid var(--line2);background:rgba(255,255,255,.02);margin-bottom:6px;cursor:pointer;transition:all .12s;user-select:none}
.choice-label:hover{background:rgba(59,158,255,.05);border-color:rgba(59,158,255,.2)}
.choice-label.selected{background:rgba(59,158,255,.08);border-color:rgba(59,158,255,.4)}
.choice-label input[type=radio]{display:none}
.choice-dot{width:16px;height:16px;border-radius:50%;border:2px solid var(--line2);flex-shrink:0;margin-top:2px;transition:all .12s}
.choice-label.selected .choice-dot{border-color:var(--accent);background:var(--accent)}
.choice-text{font-size:13px;line-height:1.5}

/* Barre de progression */
.progress-bar{height:4px;border-radius:2px;background:var(--line);margin-bottom:20px;overflow:hidden}
.progress-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--accent),var(--accent2));transition:width .3s}

/* Timer */
.timer{font-family:var(--font-head);font-size:24px;font-weight:800;color:var(--accent2)}
.timer.warning{color:#ffb060}
.timer.danger{color:#ff7a7a}

/* Résultat */
.result-score{font-family:var(--font-head);font-size:48px;font-weight:800;line-height:1}
.result-label{font-size:14px;color:var(--muted);margin-top:6px}
.result-ok{color:var(--accent2)}
.result-fail{color:#ff7a7a}
  </style>
</head>
<body>
<div class="wrap">
  <div class="ppa-header">
    <div class="ppa-header-logo">🔫</div>
    <div>
      <div class="ppa-header-title">SAMR — Test PPA</div>
      <div class="ppa-header-sub">San Andreas Medical Response · Permis de Port d'Arme</div>
    </div>
  </div>
<?php }

function ppa_layout_foot(): void { ?>
</div>
</body>
</html>
<?php }
