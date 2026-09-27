<?php
declare(strict_types=1);
require_once __DIR__ . '/layout.php';
samr_layout_start('Ophtalmologie — SAMR');
?>

<style>
/* ── Ophthalmologie page styles ── */
.opht-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--line);
  padding-bottom: 0;
}
.opht-tab {
  padding: 9px 18px;
  border-radius: 9px 9px 0 0;
  border: 1px solid transparent;
  border-bottom: none;
  font-family: var(--font-body);
  font-size: 13px;
  font-weight: 500;
  color: var(--muted);
  background: transparent;
  cursor: pointer;
  transition: all .15s;
  position: relative;
  bottom: -1px;
}
.opht-tab:hover { color: var(--text); background: rgba(255,255,255,.03); }
.opht-tab.active {
  color: var(--accent);
  border-color: var(--line) var(--line) var(--panel);
  background: var(--panel);
  background: linear-gradient(160deg, rgba(59,158,255,.07) 0%, rgba(10,15,21,.95) 100%);
}
.opht-panel { display: none; }
.opht-panel.active { display: block; }

/* Search */
.opht-search-wrap { position: relative; margin-bottom: 20px; }
.opht-search-wrap svg {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--muted); pointer-events: none;
}
.opht-search { padding-left: 38px !important; }

/* Disease grid */
.disease-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 14px;
}
.disease-card {
  background: linear-gradient(160deg, rgba(13,18,25,.9) 0%, rgba(10,15,21,.95) 100%);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  overflow: hidden;
  cursor: pointer;
  transition: border-color .15s, transform .15s;
}
.disease-card:hover { border-color: var(--line2); transform: translateY(-1px); }
.disease-card.expanded { border-color: rgba(59,158,255,.25); }
.disease-card-head {
  display: flex; align-items: center; gap: 12px;
  padding: 16px;
}
.disease-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.disease-icon.blue  { background: rgba(59,158,255,.12); border: 1px solid rgba(59,158,255,.2); }
.disease-icon.green { background: rgba(0,212,170,.1);   border: 1px solid rgba(0,212,170,.2);  }
.disease-icon.red   { background: rgba(255,79,79,.1);   border: 1px solid rgba(255,79,79,.2);  }
.disease-icon.amber { background: rgba(255,180,50,.1);  border: 1px solid rgba(255,180,50,.2); }
.disease-icon.purple{ background: rgba(160,100,255,.1); border: 1px solid rgba(160,100,255,.2);}
.disease-name { font-family: var(--font-head); font-size: 14px; font-weight: 700; }
.disease-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }
.disease-chevron { margin-left: auto; color: var(--muted); transition: transform .2s; font-size: 11px; }
.disease-card.expanded .disease-chevron { transform: rotate(180deg); }

.disease-body {
  display: none;
  border-top: 1px solid var(--line);
  padding: 16px;
}
.disease-card.expanded .disease-body { display: block; }

.disease-section { margin-bottom: 14px; }
.disease-section:last-child { margin-bottom: 0; }
.disease-section-title {
  font-size: 10px; font-weight: 700; letter-spacing: .8px;
  text-transform: uppercase; color: var(--muted); margin-bottom: 8px;
}
.symptom-list { display: flex; flex-wrap: wrap; gap: 6px; }

.urgency-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 700; padding: 3px 9px;
  border-radius: 999px;
}
.urgency-1 { background: rgba(255,79,79,.15); border: 1px solid rgba(255,79,79,.3); color: #ff7a7a; }
.urgency-2 { background: rgba(255,180,50,.12); border: 1px solid rgba(255,180,50,.3); color: #ffb060; }
.urgency-3 { background: rgba(0,212,170,.1); border: 1px solid rgba(0,212,170,.25); color: var(--accent2); }

/* Exams table section */
.exam-matrix {
  background: linear-gradient(160deg, rgba(13,18,25,.9), rgba(10,15,21,.95));
  border: 1px solid var(--line);
  border-radius: var(--radius);
  overflow: hidden;
  margin-bottom: 16px;
}
.exam-matrix-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid var(--line);
}
.exam-matrix-title { font-family: var(--font-head); font-size: 14px; font-weight: 700; }

.exam-row {
  display: grid;
  grid-template-columns: 200px 1fr 80px 120px;
  gap: 0;
  border-bottom: 1px solid rgba(24,32,48,.6);
  transition: background .12s;
}
.exam-row:last-child { border-bottom: none; }
.exam-row:hover { background: rgba(255,255,255,.02); }
.exam-row.header { background: rgba(255,255,255,.02); }
.exam-cell {
  padding: 11px 14px;
  font-size: 12px;
  display: flex; align-items: center;
  border-right: 1px solid rgba(24,32,48,.5);
}
.exam-cell:last-child { border-right: none; }
.exam-cell.header-cell {
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px; color: var(--muted);
}

/* Routine exams */
.routine-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px;
}
.routine-card {
  background: linear-gradient(160deg, rgba(13,18,25,.9), rgba(10,15,21,.95));
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 18px;
}
.routine-card-head {
  display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px;
}
.routine-badge {
  width: 36px; height: 36px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; flex-shrink: 0;
}
.routine-title { font-family: var(--font-head); font-size: 13px; font-weight: 700; margin-bottom: 3px; }
.routine-freq  { font-size: 11px; }

.steps-list {
  display: flex; flex-direction: column; gap: 6px;
}
.step-item {
  display: flex; gap: 10px; align-items: flex-start;
}
.step-num {
  width: 20px; height: 20px; border-radius: 50%;
  background: rgba(59,158,255,.12); border: 1px solid rgba(59,158,255,.2);
  color: var(--accent); font-size: 10px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 1px;
}
.step-text { font-size: 12px; color: var(--text); line-height: 1.5; }

/* Info boxes */
.info-box {
  border-radius: 10px; padding: 12px 14px;
  font-size: 12px; line-height: 1.6;
  margin-bottom: 10px;
}
.info-box.blue  { background: rgba(59,158,255,.06); border: 1px solid rgba(59,158,255,.15); }
.info-box.amber { background: rgba(255,180,50,.06); border: 1px solid rgba(255,180,50,.15); color: #d4a44a; }
.info-box.red   { background: rgba(255,79,79,.06);  border: 1px solid rgba(255,79,79,.15);  color: #e07070; }
.info-box.green { background: rgba(0,212,170,.05);  border: 1px solid rgba(0,212,170,.15);  color: var(--accent2); }

.info-box-title { font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 5px; }

/* No results */
.no-results { text-align: center; padding: 48px 20px; color: var(--muted); }
.no-results-icon { font-size: 36px; margin-bottom: 12px; }

@media (max-width: 700px) {
  .exam-row { grid-template-columns: 1fr 1fr; }
  .exam-cell:nth-child(3), .exam-cell:nth-child(4) { display: none; }
  .disease-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ── Page header ── -->
<div class="page-header">
  <h1>👁️ Ophtalmologie</h1>
  <div class="sub">Wiki clinique · Pathologies, examens diagnostiques & contrôles courants</div>
</div>

<!-- ── Stats rapides ── -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card blue">
    <div class="s-label">Pathologies</div>
    <div class="s-val">14</div>
    <div class="s-sub">répertoriées</div>
  </div>
  <div class="stat-card green">
    <div class="s-label">Examens</div>
    <div class="s-val">18</div>
    <div class="s-sub">protocoles disponibles</div>
  </div>
  <div class="stat-card">
    <div class="s-label">Urgences</div>
    <div class="s-val" style="color:#ff7a7a">5</div>
    <div class="s-sub">à traitement immédiat</div>
  </div>
  <div class="stat-card">
    <div class="s-label">Contrôles courants</div>
    <div class="s-val">8</div>
    <div class="s-sub">procédures standard</div>
  </div>
</div>

<!-- ── Tabs ── -->
<div class="opht-tabs">
  <button class="opht-tab active" onclick="switchTab('wiki')">📖 Wiki Pathologies</button>
  <button class="opht-tab" onclick="switchTab('examens')">🔬 Examens par pathologie</button>
  <button class="opht-tab" onclick="switchTab('routine')">📋 Examens courants</button>
</div>

<!-- ══════════════════════════════════════════
     TAB 1 — WIKI PATHOLOGIES
═══════════════════════════════════════════ -->
<div class="opht-panel active" id="panel-wiki">

  <div class="opht-search-wrap">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
    </svg>
    <input type="text" class="inp opht-search" id="wiki-search" placeholder="Rechercher une pathologie, un symptôme…" oninput="filterDiseases(this.value)">
  </div>

  <div class="disease-grid" id="disease-grid">

    <!-- ── Glaucome ── -->
    <div class="disease-card" data-tags="glaucome pression intraoculaire nerf optique champ visuel" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon blue">🫧</div>
        <div>
          <div class="disease-name">Glaucome</div>
          <div class="disease-sub">Neuropathie optique progressive</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-2">⚠ Modérée — Glaucome aigu = urgence absolue</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Pression intraoculaire élevée</span>
            <span class="pill">Perte du champ visuel périphérique</span>
            <span class="pill">Halos autour des lumières</span>
            <span class="pill">Douleur oculaire (forme aiguë)</span>
            <span class="pill">Nausées/vomissements (crise aiguë)</span>
            <span class="pill">Cornée trouble</span>
            <span class="pill">Œil rouge (crise)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            <div class="info-box-title">Critères diagnostiques</div>
            Mesure de la PIO (tonométrie) &gt; 21 mmHg, excavation papillaire au fond d'œil, périmétrie automatisée pour cartographier les déficits du champ visuel. Gonioscopie pour classifier le type (ouvert/fermé).
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Tonométrie</span>
            <span class="pill pill-blue">Fond d'œil</span>
            <span class="pill pill-blue">Périmétrie</span>
            <span class="pill pill-blue">OCT papille</span>
            <span class="pill pill-blue">Gonioscopie</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Traitement initial</div>
          <div class="info-box amber">⚠ Crise aiguë : acétazolamide IV + bêtabloquant local + myotique. Orientation ophtalmologue en urgence. Éviter le décubitus dorsal.
          </div>
        </div>
      </div>
    </div>

    <!-- ── DMLA ── -->
    <div class="disease-card" data-tags="dmla degenérescence maculaire vision centrale taches scotome drusen" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon amber">🌫️</div>
        <div>
          <div class="disease-name">DMLA</div>
          <div class="disease-sub">Dégénérescence maculaire liée à l'âge</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-2">⚠ DMLA humide = urgence (perte rapide)</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Perte de vision centrale</span>
            <span class="pill">Scotome central</span>
            <span class="pill">Métamorphopsies (lignes tordues)</span>
            <span class="pill">Drusen (forme sèche)</span>
            <span class="pill">Néovascularisation (forme humide)</span>
            <span class="pill">Baisse d'acuité progressive</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            <div class="info-box-title">Critères diagnostiques</div>
            Grille d'Amsler (distorsions/scotome), fond d'œil, OCT maculaire (gold standard). Angiographie si suspicion de néovascularisation.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Grille d'Amsler</span>
            <span class="pill pill-blue">OCT maculaire</span>
            <span class="pill pill-blue">Fond d'œil</span>
            <span class="pill pill-blue">Angiofluorographie</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Cataracte ── -->
    <div class="disease-card" data-tags="cataracte cristallin opacification vision floue éblouissement" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon green">🌀</div>
        <div>
          <div class="disease-name">Cataracte</div>
          <div class="disease-sub">Opacification du cristallin</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-3">✓ Non urgente — chirurgie programmée</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Vision floue progressive</span>
            <span class="pill">Éblouissement (halos)</span>
            <span class="pill">Diplopie monoculaire</span>
            <span class="pill">Modification de la réfraction</span>
            <span class="pill">Pupille blanchâtre (stade avancé)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            <div class="info-box-title">Critères diagnostiques</div>
            Examen à la lampe à fente (opacification du cristallin). Classification LOCS III (densité, couleur). Mesure de l'acuité visuelle avant/après correction.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Lampe à fente</span>
            <span class="pill pill-blue">Acuité visuelle</span>
            <span class="pill pill-blue">Fond d'œil (rétro-illumination)</span>
            <span class="pill pill-blue">Biométrie (pré-op)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Rétinopathie diabétique ── -->
    <div class="disease-card" data-tags="retinopathie diabétique microanévrysmes hémorragie rétine néovascularisation" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon red">🩸</div>
        <div>
          <div class="disease-name">Rétinopathie diabétique</div>
          <div class="disease-sub">Atteinte vasculaire rétinienne</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-1">🔴 Proliférante = urgence traitement laser</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Microanévrysmes rétiniens</span>
            <span class="pill">Hémorragies intrarétiniennes</span>
            <span class="pill">Exsudats durs</span>
            <span class="pill">Œdème maculaire</span>
            <span class="pill">Néovaisseaux (forme proliférante)</span>
            <span class="pill">Corps flottants (hémorragie vitrée)</span>
            <span class="pill">Baisse d'acuité soudaine</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box red">
            <div class="info-box-title">⚠ Dépistage systématique diabétique</div>
            Fond d'œil annuel obligatoire. OCT maculaire si œdème suspecté. Angiographie pour cartographier l'ischémie rétinienne. Bilan glycémique + HbA1c associé.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Fond d'œil dilaté</span>
            <span class="pill pill-blue">OCT maculaire</span>
            <span class="pill pill-blue">Angiofluorographie</span>
            <span class="pill pill-red">HbA1c (labo)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Décollement de rétine ── -->
    <div class="disease-card" data-tags="décollement rétine photopsies myodésopsies vision voile rideau urgence" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon red">⚡</div>
        <div>
          <div class="disease-name">Décollement de rétine</div>
          <div class="disease-sub">Urgence chirurgicale absolue</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-1">🔴 URGENCE ABSOLUE — chirurgie dans les 24h</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Photopsies (éclairs lumineux)</span>
            <span class="pill">Myodésopsies soudaines</span>
            <span class="pill">Voile/rideau noir dans le champ visuel</span>
            <span class="pill">Perte de vision progressive unilatérale</span>
            <span class="pill">Pas de douleur (DR rhegmatogène)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box red">
            <div class="info-box-title">⚠ Diagnostic en urgence</div>
            Fond d'œil en urgence (pupille dilatée). Échographie oculaire si opacités des milieux. Ne pas perdre de temps — transfert immédiat en chirurgie ophtalmologique.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-red">Fond d'œil URGENCE</span>
            <span class="pill pill-blue">Échographie oculaire B</span>
            <span class="pill pill-blue">Périmétrie (si stable)</span>
          </div>
        </div>
        <div class="info-box red" style="margin-top:10px">Décubitus strict, pas d'alimentation — préparer le bloc opératoire.</div>
      </div>
    </div>

    <!-- ── Kératocône ── -->
    <div class="disease-card" data-tags="kératocône cornée astigmatisme progressif myopie cônes cornéens" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon purple">🔺</div>
        <div>
          <div class="disease-name">Kératocône</div>
          <div class="disease-sub">Ectasie cornéenne progressive</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-3">✓ Non urgente sauf hydrops aigu</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Astigmatisme irrégulier progressif</span>
            <span class="pill">Myopie croissante</span>
            <span class="pill">Vision floue binoculaire</span>
            <span class="pill">Signe de Munson (déformation paupière)</span>
            <span class="pill">Stries de Vogt</span>
            <span class="pill">Anneaux de Fleischer</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            <div class="info-box-title">Critères diagnostiques</div>
            Topographie cornéenne (gold standard) — aspect en cône avec apex inférocentral. Pachymétrie (amincissement). Évaluation sur kératomètre (astigmatisme irrégulier).
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Topographie cornéenne</span>
            <span class="pill pill-blue">Pachymétrie</span>
            <span class="pill pill-blue">Lampe à fente</span>
            <span class="pill pill-blue">Réfraction sous cycloplégie</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Conjonctivite ── -->
    <div class="disease-card" data-tags="conjonctivite oeil rouge secretions allergique infectieuse virale bactérienne" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon green">👁️</div>
        <div>
          <div class="disease-name">Conjonctivite</div>
          <div class="disease-sub">Inflammation de la conjonctive</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-3">✓ Généralement bénigne</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Œil rouge</span>
            <span class="pill">Sécrétions purulentes (bactérienne)</span>
            <span class="pill">Sécrétions claires (virale/allergique)</span>
            <span class="pill">Prurit (allergique)</span>
            <span class="pill">Photophobie légère</span>
            <span class="pill">Chémosis</span>
            <span class="pill">Adénopathie prétragienne (virale)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            <div class="info-box-title">Différenciation étiologique</div>
            Bactérienne : sécrétions jaunes/vertes, unilatéral souvent. Virale : sécrétions claires, bilatéral, contexte épidémique. Allergique : prurit intense, bilatéral, terrain atopique. Écouvillonnage si doute ou formes sévères.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Lampe à fente</span>
            <span class="pill pill-blue">Prélèvement conjonctival</span>
            <span class="pill pill-blue">Test fluorescéine (kératite ?)</span>
          </div>
        </div>
        <div class="info-box amber" style="margin-top:10px">Éliminer une kératite herpétique avant toute corticothérapie locale.</div>
      </div>
    </div>

    <!-- ── Névrite optique ── -->
    <div class="disease-card" data-tags="névrite optique SEP sclérose plaque douleur mouvement vision baisse" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon red">⚡</div>
        <div>
          <div class="disease-name">Névrite optique rétrobulbaire</div>
          <div class="disease-sub">Inflammation du nerf optique</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-1">🔴 Urgence — bilan SEP à initier</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Baisse d'acuité unilatérale rapide</span>
            <span class="pill">Douleur à la mobilisation oculaire</span>
            <span class="pill">Dyschromatopsie (rouge terne)</span>
            <span class="pill">Déficit pupillaire afférent relatif</span>
            <span class="pill">Scotome central</span>
            <span class="pill">Signe d'Uhthoff (aggravation chaleur)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box red">
            <div class="info-box-title">Bilan neurologique obligatoire</div>
            IRM cérébrale et médullaire (lésions de démyélinisation ?). PEV (potentiels évoqués visuels). Fond d'œil normal dans la NORB. Bilan biologique : ECA, ANCA, sérologies.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-red">IRM cérébrale URGENCE</span>
            <span class="pill pill-blue">PEV</span>
            <span class="pill pill-blue">Fond d'œil</span>
            <span class="pill pill-blue">Périmétrie</span>
            <span class="pill pill-red">Bilan biologique</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Uvéite ── -->
    <div class="disease-card" data-tags="uvéite iritis cellules chambre antérieure précipités kératiques photophobie" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon amber">🔥</div>
        <div>
          <div class="disease-name">Uvéite</div>
          <div class="disease-sub">Inflammation de l'uvée</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-2">⚠ Traitement rapide pour éviter synéchies</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Œil rouge périkératique</span>
            <span class="pill">Photophobie intense</span>
            <span class="pill">Douleur profonde</span>
            <span class="pill">Baisse d'acuité</span>
            <span class="pill">Cells en chambre antérieure (Tyndall)</span>
            <span class="pill">Précipités kératiques</span>
            <span class="pill">Synéchies irido-cristalliniennes</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            <div class="info-box-title">Critères diagnostiques</div>
            Lampe à fente indispensable : Tyndall cellulaire et protéique, précipités kératiques, synéchies. Bilan étiologique : HLA-B27, spondyloarthropathie, sarcoïdose, toxoplasmose, HSV.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Lampe à fente</span>
            <span class="pill pill-blue">Fond d'œil</span>
            <span class="pill pill-blue">Tonométrie</span>
            <span class="pill pill-red">Bilan étiologique</span>
            <span class="pill pill-blue">OCT maculaire</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Occlusion artère centrale ── -->
    <div class="disease-card" data-tags="occlusion artère centrale rétine cécité blanche soudaine ischémie" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon red">🚨</div>
        <div>
          <div class="disease-name">Occlusion de l'artère centrale</div>
          <div class="disease-sub">Ischémie rétinienne aiguë</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-1">🔴 URGENCE EXTRÊME — fenêtre thérapeutique 4h</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Cécité monoculaire brutale indolore</span>
            <span class="pill">Acuité = perception lumineuse ou moins</span>
            <span class="pill">DPAR massif</span>
            <span class="pill">Rétine blanc laiteux (ischémie)</span>
            <span class="pill">Tache rouge cerise (macula)</span>
            <span class="pill">Artérioles grêles/atones</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box red">
            <div class="info-box-title">⚠ Diagnostic clinique immédiat</div>
            Fond d'œil en urgence absolue. Équivalent d'un AVC rétinien. ECG, Doppler carotidien, bilan cardiovasculaire en parallèle. IRM cérébrale de débrouillage.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-red">Fond d'œil IMMÉDIAT</span>
            <span class="pill pill-red">ECG</span>
            <span class="pill pill-red">Doppler carotidien</span>
            <span class="pill pill-red">IRM cérébrale</span>
            <span class="pill pill-red">Bilan cardio complet</span>
          </div>
        </div>
        <div class="info-box red" style="margin-top:10px">Massage oculaire digital, paracentèse de chambre antérieure, thrombolyse selon protocole. Hospitalisation immédiate.</div>
      </div>
    </div>

    <!-- ── Ptérygion ── -->
    <div class="disease-card" data-tags="ptérygion conjonctive nasal envahissement cornée soleil exposition" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon blue">🌱</div>
        <div>
          <div class="disease-name">Ptérygion</div>
          <div class="disease-sub">Prolifération conjonctivale cornéenne</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-3">✓ Non urgent — suivi régulier</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Membrane nasale triangulaire</span>
            <span class="pill">Envahissement progressif de la cornée</span>
            <span class="pill">Irritation/sécheresse</span>
            <span class="pill">Astigmatisme induit</span>
            <span class="pill">Diplopie (si avancé)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            Examen clinique à la lampe à fente. Classification selon la progression sur la cornée. Topographie cornéenne si distorsion visuelle.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Lampe à fente</span>
            <span class="pill pill-blue">Topographie cornéenne</span>
            <span class="pill pill-blue">Acuité visuelle</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Sécheresse oculaire ── -->
    <div class="disease-card" data-tags="sécheresse oculaire larmoiement picotements syndrome sec film lacrymal" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon blue">💧</div>
        <div>
          <div class="disease-name">Sécheresse oculaire</div>
          <div class="disease-sub">Dysfonction du film lacrymal</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-3">✓ Chronique — prise en charge au long cours</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Picotements / brûlures</span>
            <span class="pill">Larmoiement réflexe</span>
            <span class="pill">Vision fluctuante</span>
            <span class="pill">Sensation de corps étranger</span>
            <span class="pill">Photophobie</span>
            <span class="pill">Rougeur périlimbique</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            Test de Schirmer (production lacrymale), BUT (Break-Up Time) après fluorescéine, colorations vitales (Rose Bengale, lissamine verte). Questionnaire OSDI.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Test de Schirmer</span>
            <span class="pill pill-blue">BUT lacrymal</span>
            <span class="pill pill-blue">Coloration fluorescéine</span>
            <span class="pill pill-blue">Lampe à fente</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Strabisme ── -->
    <div class="disease-card" data-tags="strabisme déviation amblyopie convergent divergent enfant" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon amber">↔️</div>
        <div>
          <div class="disease-name">Strabisme</div>
          <div class="disease-sub">Désalignement oculomoteur</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-2">⚠ Strabisme acquis adulte = urgence neurologique</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Déviation oculaire visible</span>
            <span class="pill">Diplopie (adulte)</span>
            <span class="pill">Suppression visuelle (enfant)</span>
            <span class="pill">Amblyopie associée possible</span>
            <span class="pill">Torticolis compensateur</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box blue">
            Cover-test (déviation latente/manifeste), angle de strabisme (reflet cornéen de Hirschberg), étude de la motilité oculaire. Bilan de la vision binoculaire (Bagolini).
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Cover-test</span>
            <span class="pill pill-blue">Réfraction sous cycloplégie</span>
            <span class="pill pill-blue">Acuité visuelle chaque œil</span>
            <span class="pill pill-blue">Fond d'œil</span>
            <span class="pill pill-red">IRM (si paralytique)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Corps étrangers ── -->
    <div class="disease-card" data-tags="corps étranger cornée conjonctive projection métallique douleur urgence" onclick="toggleCard(this)">
      <div class="disease-card-head">
        <div class="disease-icon red">🔩</div>
        <div>
          <div class="disease-name">Corps étranger oculaire</div>
          <div class="disease-sub">Projection / impaction cornéo-conjonctivale</div>
        </div>
        <span class="disease-chevron">▼</span>
      </div>
      <div class="disease-body">
        <div class="disease-section">
          <div class="disease-section-title">Urgence</div>
          <span class="urgency-badge urgency-1">🔴 CE intra-oculaire = urgence chirurgicale</span>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Signes & Symptômes</div>
          <div class="symptom-list">
            <span class="pill">Douleur aiguë brutale</span>
            <span class="pill">Larmoiement important</span>
            <span class="pill">Blépharospasme</span>
            <span class="pill">Photophobie</span>
            <span class="pill">Sensation de gravier</span>
            <span class="pill">Hypopion (si perforant)</span>
            <span class="pill">Chambre antérieure plate (perforant)</span>
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Détection</div>
          <div class="info-box amber">
            <div class="info-box-title">⚠ Éliminer la perforation</div>
            Signe de Seidel (fluorescéine). TDM orbite si CE métallique suspecté en profondeur. NE JAMAIS extraire un CE pénétrant au cabinet.
          </div>
        </div>
        <div class="disease-section">
          <div class="disease-section-title">Examens prioritaires</div>
          <div class="symptom-list">
            <span class="pill pill-blue">Lampe à fente + fluorescéine</span>
            <span class="pill pill-blue">Signe de Seidel</span>
            <span class="pill pill-red">TDM orbite (si pénétrant)</span>
            <span class="pill pill-blue">Échographie (si opacités)</span>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /#disease-grid -->

  <div class="no-results" id="no-results" style="display:none">
    <div class="no-results-icon">🔍</div>
    <div style="font-size:15px;font-weight:600;margin-bottom:6px">Aucun résultat</div>
    <div style="color:var(--muted);font-size:13px">Essayez un autre terme de recherche</div>
  </div>

</div><!-- /#panel-wiki -->


<!-- ══════════════════════════════════════════
     TAB 2 — EXAMENS PAR PATHOLOGIE
═══════════════════════════════════════════ -->
<div class="opht-panel" id="panel-examens">

  <div class="info-box blue" style="margin-bottom:20px">
    <div class="info-box-title">Guide d'utilisation</div>
    Ce tableau indique les examens à réaliser en fonction de la pathologie suspectée, avec le niveau de priorité et les délais recommandés.
  </div>

  <!-- Urgences absolues -->
  <div class="exam-matrix" style="margin-bottom:24px">
    <div class="exam-matrix-head">
      <div class="exam-matrix-title">🔴 Urgences absolues</div>
      <span class="pill pill-red">Traitement dans les heures</span>
    </div>
    <div class="exam-row header">
      <div class="exam-cell header-cell">Pathologie</div>
      <div class="exam-cell header-cell">Examens à réaliser</div>
      <div class="exam-cell header-cell">Délai</div>
      <div class="exam-cell header-cell">Orientation</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Décollement de rétine</div>
      <div class="exam-cell">Fond d'œil dilaté, Échographie B, Périmétrie</div>
      <div class="exam-cell"><span class="urgency-badge urgency-1" style="font-size:10px">&lt; 24h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Chirurgie ophtalmologique</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Occlusion artère centrale</div>
      <div class="exam-cell">Fond d'œil, ECG, Doppler carotidien, IRM cérébrale</div>
      <div class="exam-cell"><span class="urgency-badge urgency-1" style="font-size:10px">&lt; 4h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Unité vasculaire / Thrombolyse</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Glaucome aigu par fermeture d'angle</div>
      <div class="exam-cell">Tonométrie, Lampe à fente, Gonioscopie</div>
      <div class="exam-cell"><span class="urgency-badge urgency-1" style="font-size:10px">&lt; 2h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Urgences ophtalmologiques</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">CE intra-oculaire perforant</div>
      <div class="exam-cell">Lampe à fente, Signe de Seidel, TDM orbite</div>
      <div class="exam-cell"><span class="urgency-badge urgency-1" style="font-size:10px">Immédiat</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Bloc opératoire</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Névrite optique</div>
      <div class="exam-cell">IRM cérébrale, PEV, Fond d'œil, Bilan bio</div>
      <div class="exam-cell"><span class="urgency-badge urgency-1" style="font-size:10px">&lt; 24h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Neurologie / Ophtalmologie</div>
    </div>
  </div>

  <!-- Semi-urgences -->
  <div class="exam-matrix" style="margin-bottom:24px">
    <div class="exam-matrix-head">
      <div class="exam-matrix-title">⚠️ Semi-urgences</div>
      <span class="pill pill-blue" style="border-color:rgba(255,180,50,.3);color:#ffb060;background:rgba(255,180,50,.08)">Dans la semaine</span>
    </div>
    <div class="exam-row header">
      <div class="exam-cell header-cell">Pathologie</div>
      <div class="exam-cell header-cell">Examens à réaliser</div>
      <div class="exam-cell header-cell">Délai</div>
      <div class="exam-cell header-cell">Orientation</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">DMLA humide</div>
      <div class="exam-cell">OCT maculaire, Angiofluorographie, Grille d'Amsler</div>
      <div class="exam-cell"><span class="urgency-badge urgency-2" style="font-size:10px">&lt; 48h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie (anti-VEGF)</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Uvéite</div>
      <div class="exam-cell">Lampe à fente, Fond d'œil, Tonométrie, Bilan étiologique</div>
      <div class="exam-cell"><span class="urgency-badge urgency-2" style="font-size:10px">&lt; 72h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Rétinopathie diabétique proliférante</div>
      <div class="exam-cell">Fond d'œil dilaté, OCT, Angiographie, HbA1c</div>
      <div class="exam-cell"><span class="urgency-badge urgency-2" style="font-size:10px">&lt; 1 semaine</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Laser / Injection intra-vitréenne</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Strabisme paralytique acquis</div>
      <div class="exam-cell">Cover-test, IRM cérébrale, Bilan motilité</div>
      <div class="exam-cell"><span class="urgency-badge urgency-2" style="font-size:10px">&lt; 72h</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Neurologie + Ophtalmologie</div>
    </div>
  </div>

  <!-- Programmé -->
  <div class="exam-matrix">
    <div class="exam-matrix-head">
      <div class="exam-matrix-title">✅ Programmé / Suivi</div>
      <span class="pill pill-green">Consultation planifiée</span>
    </div>
    <div class="exam-row header">
      <div class="exam-cell header-cell">Pathologie</div>
      <div class="exam-cell header-cell">Examens à réaliser</div>
      <div class="exam-cell header-cell">Fréquence</div>
      <div class="exam-cell header-cell">Orientation</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Glaucome chronique</div>
      <div class="exam-cell">Tonométrie, OCT papille, Périmétrie, Fond d'œil</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Tous les 6 mois</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Cataracte</div>
      <div class="exam-cell">Lampe à fente, Acuité visuelle, Biométrie pré-op</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Annuel ou si gêne</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Chirurgie programmée</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">DMLA sèche</div>
      <div class="exam-cell">OCT maculaire, Fond d'œil, Grille d'Amsler</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Tous les 6 mois</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Rétinopathie diabétique (non proliférante)</div>
      <div class="exam-cell">Fond d'œil, OCT maculaire, Glycémie</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Annuel</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie + Diabétologie</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Kératocône</div>
      <div class="exam-cell">Topographie cornéenne, Pachymétrie, Réfraction</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Tous les 6 mois</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie spécialisée</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Sécheresse oculaire</div>
      <div class="exam-cell">Test de Schirmer, BUT, Coloration fluorescéine</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Annuel ou symptômes</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Ophtalmologie</div>
    </div>
    <div class="exam-row">
      <div class="exam-cell" style="font-weight:600;color:var(--text)">Ptérygion</div>
      <div class="exam-cell">Lampe à fente, Topographie (si gêne visuelle)</div>
      <div class="exam-cell"><span class="pill" style="font-size:10px">Annuel</span></div>
      <div class="exam-cell" style="color:var(--accent2)">Chirurgie si progression</div>
    </div>
  </div>

</div><!-- /#panel-examens -->


<!-- ══════════════════════════════════════════
     TAB 3 — EXAMENS COURANTS / CONTRÔLE
═══════════════════════════════════════════ -->
<div class="opht-panel" id="panel-routine">

  <div class="info-box blue" style="margin-bottom:24px">
    <div class="info-box-title">Examens de contrôle standard</div>
    Protocoles utilisés en consultation ophtalmologique courante et lors des bilans de suivi. Chaque fiche décrit le déroulement étape par étape.
  </div>

  <div class="routine-grid">

    <!-- Acuité visuelle -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(59,158,255,.12);border:1px solid rgba(59,158,255,.2)">📏</div>
        <div>
          <div class="routine-title">Acuité visuelle</div>
          <div class="routine-freq" style="color:var(--accent2)">Systématique à chaque consultation</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Occlusion de l'œil controlatéral avec cache opaque</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Echelle de Monoyer ou ETDRS à 5m (ou 4m adapté)</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">AV de loin sans correction, puis avec correction habituelle</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">AV de près avec correction (Parinaud ou Rossano-Weiss)</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Si AV réduite : trou sténopéique (amélioration = cause réfractive)</div></div>
        <div class="step-item"><div class="step-num">6</div><div class="step-text">Notation : 10/10 – 1/10 ou notation décimale + Parinaud</div></div>
      </div>
      <div class="info-box green" style="margin-top:14px">Valeur normale : 10/10 Snellen ou 20/20 (notation US)</div>
    </div>

    <!-- Tonométrie -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(255,180,50,.1);border:1px solid rgba(255,180,50,.2)">💨</div>
        <div>
          <div class="routine-title">Tonométrie</div>
          <div class="routine-freq" style="color:var(--muted)">Contrôle annuel 40 ans+ / suivi glaucome</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Anesthésie locale (oxybuprocaïne) + fluorescéine si tonomètre à aplanation</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Tonomètre à aplanation Goldmann (gold standard) ou air pulsé</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">3 mesures successives par œil, noter la moyenne</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Corriger selon l'épaisseur cornéenne centrale (pachymétrie)</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Mesure idéalement à la même heure (variation nycthémérale ±3 mmHg)</div></div>
      </div>
      <div class="info-box green" style="margin-top:14px">Valeur normale : 10–21 mmHg · Au-delà : suspicion glaucome</div>
      <div class="info-box amber" style="margin-top:8px">Cornée fine (−500µm) → PIO sous-estimée. Corriger !</div>
    </div>

    <!-- Fond d'œil -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.2)">🔍</div>
        <div>
          <div class="routine-title">Fond d'œil</div>
          <div class="routine-freq" style="color:var(--muted)">Systématique si diabète, HTA, glaucome</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Instillation mydriatique (tropicamide 1%) — attendre 15–20 min</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Contre-indications : glaucome par fermeture d'angle, allergie</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">Examen ophtalmoscopique : papille (excavation, bords), macula, vaisseaux, périphérie</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Retinophotographie si suivi (rétinopathie, glaucome)</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Documenter : rapport cup/disc, lésions, hémorragies, exsudats</div></div>
      </div>
      <div class="info-box amber" style="margin-top:14px">⚠ Avertir le patient : vision trouble 2–4h, ne pas conduire</div>
    </div>

    <!-- OCT -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(160,100,255,.1);border:1px solid rgba(160,100,255,.2)">📡</div>
        <div>
          <div class="routine-title">OCT (Tomographie par cohérence optique)</div>
          <div class="routine-freq" style="color:var(--muted)">DMLA, glaucome, rétinopathie, œdème maculaire</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Pas d'anesthésie requise — examen non invasif</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Centrer sur la région d'intérêt : macula ou tête du nerf optique</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">Acquérir les coupes transversales (B-scan) et en face</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Analyser : épaisseur des couches rétiniennes, drusen, liquide sous-rétinien</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">OCT-A (angiographie) si néovascularisation suspectée</div></div>
      </div>
      <div class="info-box green" style="margin-top:14px">Résolution ~5–10µm · Gold standard pour le suivi maculaire</div>
    </div>

    <!-- Périmétrie -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(59,158,255,.12);border:1px solid rgba(59,158,255,.2)">🎯</div>
        <div>
          <div class="routine-title">Périmétrie automatisée</div>
          <div class="routine-freq" style="color:var(--muted)">Suivi glaucome, névrite optique, champ visuel</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Installation du patient : menton sur l'appui, correction optique portée</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Occlure l'œil non testé avec cache opaque</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">Expliquer la procédure : appuyer à chaque flash lumineux perçu</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Protocole SITA Standard (Humphrey) ou Full Threshold selon indication</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Analyser : scotomes, déviation moyenne (MD), déviation pattern (PSD)</div></div>
        <div class="step-item"><div class="step-num">6</div><div class="step-text">Fiabilité : fixation, faux positifs/négatifs &lt; 20%</div></div>
      </div>
    </div>

    <!-- Lampe à fente -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(255,79,79,.1);border:1px solid rgba(255,79,79,.2)">💡</div>
        <div>
          <div class="routine-title">Lampe à fente (biomicroscopie)</div>
          <div class="routine-freq" style="color:var(--accent2)">Systématique à chaque consultation</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Appui frontal et mentonnier stable, hauteur ajustée</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Examen segment antérieur : paupières, conjonctive, cornée, chambre antérieure</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">Fluorescéine si suspicion kératite, érosion, ulcération</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Examen cristallin (cataracte), iris, angle irido-cornéen</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Vitrée antérieure si mydriase induite</div></div>
        <div class="step-item"><div class="step-num">6</div><div class="step-text">Documenter cellules (Tyndall), précipités kératiques, néovaisseaux</div></div>
      </div>
    </div>

    <!-- Réfraction -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.2)">🔭</div>
        <div>
          <div class="routine-title">Réfraction</div>
          <div class="routine-freq" style="color:var(--muted)">Bilan visuel complet, prescription</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Auto-réfractomètre (mesure objective initiale)</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Réfraction subjective : ajustement par le patient (boîte à lentilles ou phoropter)</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">Cycloplégie (atropine/cyclopentolate) chez l'enfant ou en cas d'accommodation excessive</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Mesure de l'addition (presbytie) si besoin</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Kératoréfractométrie si chirurgie réfractive envisagée</div></div>
      </div>
      <div class="info-box blue" style="margin-top:14px">Délai cycloplégie atropine : 48–72h avant mesure</div>
    </div>

    <!-- Test de Schirmer -->
    <div class="routine-card">
      <div class="routine-card-head">
        <div class="routine-badge" style="background:rgba(59,158,255,.12);border:1px solid rgba(59,158,255,.2)">💧</div>
        <div>
          <div class="routine-title">Test de Schirmer</div>
          <div class="routine-freq" style="color:var(--muted)">Bilan sécheresse oculaire, Sjögren</div>
        </div>
      </div>
      <div class="steps-list">
        <div class="step-item"><div class="step-num">1</div><div class="step-text">Bandelettes standardisées (papier filtre Whatman 5×35mm)</div></div>
        <div class="step-item"><div class="step-num">2</div><div class="step-text">Schirmer I : sans anesthésie (sécrétion réflexe + basale)</div></div>
        <div class="step-item"><div class="step-num">3</div><div class="step-text">Placer dans le cul-de-sac temporal inférieur des deux yeux</div></div>
        <div class="step-item"><div class="step-num">4</div><div class="step-text">Yeux fermés pendant 5 minutes exactes</div></div>
        <div class="step-item"><div class="step-num">5</div><div class="step-text">Mesurer la longueur de papier humidifié en mm</div></div>
      </div>
      <div class="info-box green" style="margin-top:14px">Normale : &gt; 15mm/5min · Anormal : &lt; 5mm = sécheresse sévère</div>
    </div>

  </div><!-- /.routine-grid -->

  <!-- Tableau récapitulatif délais -->
  <div style="margin-top:28px">
    <div class="card">
      <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:14px">📅 Calendrier de contrôle recommandé</div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Population</th>
            <th>Examen</th>
            <th>Fréquence</th>
            <th>Remarques</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Adulte sain</td>
            <td>Bilan ophtalmologique complet</td>
            <td><span class="pill">Tous les 2 ans</span></td>
            <td style="color:var(--muted)">Acuité visuelle + fond d'œil + PIO</td>
          </tr>
          <tr>
            <td>Adulte &gt; 40 ans</td>
            <td>Dépistage glaucome (tonométrie + FO)</td>
            <td><span class="pill">Annuel</span></td>
            <td style="color:var(--muted)">Risque accru dès 40 ans</td>
          </tr>
          <tr>
            <td>Diabétique</td>
            <td>Fond d'œil dilaté</td>
            <td><span class="pill pill-red">Annuel obligatoire</span></td>
            <td style="color:var(--muted)">Dès le diagnostic du diabète</td>
          </tr>
          <tr>
            <td>Glaucome traité</td>
            <td>PIO + OCT papille + Périmétrie</td>
            <td><span class="pill pill-blue">Tous les 4–6 mois</span></td>
            <td style="color:var(--muted)">Adapter selon stabilité</td>
          </tr>
          <tr>
            <td>DMLA suivie</td>
            <td>OCT maculaire + FO</td>
            <td><span class="pill pill-blue">Tous les 6 mois</span></td>
            <td style="color:var(--muted)">Mensuel si anti-VEGF en cours</td>
          </tr>
          <tr>
            <td>Enfant</td>
            <td>Dépistage amblyopie / strabisme</td>
            <td><span class="pill">6–9 mois, 2–4 ans, 6 ans</span></td>
            <td style="color:var(--muted)">Réfraction sous cycloplégie si doute</td>
          </tr>
          <tr>
            <td>Myope fort (&gt; -6D)</td>
            <td>Fond d'œil périphérique</td>
            <td><span class="pill">Annuel</span></td>
            <td style="color:var(--muted)">Dépistage déhiscences rétiniennes</td>
          </tr>
          <tr>
            <td>Post-chirurgie réfractive</td>
            <td>Topographie + Acuité + PIO</td>
            <td><span class="pill">J1, S1, M1, M3, M6, M12</span></td>
            <td style="color:var(--muted)">Adapter selon résultat</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /#panel-routine -->

<script>
function switchTab(name) {
  document.querySelectorAll('.opht-tab').forEach((t, i) => {
    t.classList.toggle('active', ['wiki','examens','routine'][i] === name);
  });
  document.querySelectorAll('.opht-panel').forEach(p => {
    p.classList.toggle('active', p.id === 'panel-' + name);
  });
}

function toggleCard(card) {
  card.classList.toggle('expanded');
}

function filterDiseases(query) {
  const q = query.toLowerCase().trim();
  const cards = document.querySelectorAll('.disease-card');
  let visible = 0;
  cards.forEach(card => {
    const tags = (card.dataset.tags || '').toLowerCase();
    const name = card.querySelector('.disease-name').textContent.toLowerCase();
    const sub  = card.querySelector('.disease-sub').textContent.toLowerCase();
    const match = !q || tags.includes(q) || name.includes(q) || sub.includes(q);
    card.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  document.getElementById('no-results').style.display = visible === 0 ? 'block' : 'none';
}
</script>

<?php samr_layout_end(); ?>
