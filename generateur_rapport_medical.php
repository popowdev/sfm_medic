<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();

samr_layout_start('Générateur de rapport médical');
?>

<div class="page-header">
  <h1>Générateur de rapport médical </h1>
  <div class="sub">Rapport médical + compte rendu opératoire en un seul bloc, pensé pour les interventions rapides et le suivi .</div>
</div>

<style>
.medgen-grid{
  display:grid;
  grid-template-columns:minmax(360px,480px) 1fr;
  gap:16px;
}
.medgen-form label{
  display:block;
  margin:12px 0 6px;
  color:var(--muted);
  font-size:10px;
  text-transform:uppercase;
  letter-spacing:.8px;
  font-weight:700;
}
.medgen-row{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
}
.medgen-chips{
  display:flex;
  flex-wrap:wrap;
  gap:7px;
}
.medgen-chip{
  border:1px solid var(--line2);
  background:rgba(255,255,255,.03);
  color:var(--text);
  border-radius:999px;
  padding:8px 11px;
  cursor:pointer;
  font-family:var(--font-body);
  font-size:12px;
  transition:all .15s;
}
.medgen-chip:hover{border-color:rgba(59,158,255,.35);}
.medgen-chip.active{
  border-color:rgba(59,158,255,.55);
  color:var(--accent);
  background:rgba(59,158,255,.08);
}
.medgen-chip.sev.active[data-sev="Légère"]{
  border-color:rgba(0,212,170,.45);
  color:var(--accent2);
  background:rgba(0,212,170,.08);
}
.medgen-chip.sev.active[data-sev="Moyenne"]{
  border-color:rgba(255,209,102,.45);
  color:#ffd166;
  background:rgba(255,209,102,.08);
}
.medgen-chip.sev.active[data-sev="Grave"]{
  border-color:rgba(255,79,79,.45);
  color:#ff7a7a;
  background:rgba(255,79,79,.08);
}
.medgen-templates{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:8px;
}
.medgen-template{
  width:100%;
  border:1px solid var(--line2);
  background:rgba(59,158,255,.05);
  color:var(--text);
  border-radius:10px;
  padding:9px;
  text-align:left;
  cursor:pointer;
  font-family:var(--font-body);
  font-size:12px;
}
.medgen-template:hover{
  border-color:rgba(59,158,255,.35);
  background:rgba(59,158,255,.10);
}
.medgen-bodywrap{
  display:flex;
  justify-content:center;
  margin:10px 0;
  padding:8px;
  border:1px solid var(--line);
  border-radius:12px;
  background:rgba(255,255,255,.02);
}
.medgen-part{
  cursor:pointer;
  fill:#101b28;
  stroke:#2d6ca8;
  stroke-width:2;
}
.medgen-part:hover{fill:#14273d;}
.medgen-part.selL{stroke:var(--accent2);fill:#10281f;}
.medgen-part.selM{stroke:#ffd166;fill:#312611;}
.medgen-part.selG{stroke:#ff7a7a;fill:#301318;}
.medgen-small{color:var(--muted);font-size:12px;}
.medgen-injuries{
  display:flex;
  flex-direction:column;
  gap:7px;
}
.medgen-injury{
  display:grid;
  grid-template-columns:1fr auto;
  gap:8px;
  align-items:center;
  background:rgba(255,255,255,.03);
  border:1px solid var(--line);
  border-radius:10px;
  padding:8px;
  font-size:12px;
}
.medgen-badge{
  display:inline-block;
  margin-left:5px;
  padding:2px 7px;
  border-radius:999px;
  background:rgba(59,158,255,.08);
  color:#9dccff;
  font-size:10px;
}
.medgen-output{
  min-height:760px;
  line-height:1.45;
  white-space:pre-wrap;
}
.medgen-actions{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:12px;
}

.medgen-patient-search{
  position:relative;
}
.medgen-patient-results{
  display:none;
  position:absolute;
  z-index:20;
  top:100%;
  left:0;
  right:0;
  max-height:260px;
  overflow:auto;
  margin-top:5px;
  background:rgba(7,12,20,.98);
  border:1px solid var(--line2);
  border-radius:12px;
  box-shadow:0 18px 40px rgba(0,0,0,.35);
}
.medgen-patient-result{
  padding:10px;
  border-bottom:1px solid rgba(255,255,255,.06);
  cursor:pointer;
  font-size:12px;
}
.medgen-patient-result:hover{
  background:rgba(59,158,255,.10);
}
.medgen-patient-result strong{
  display:block;
  color:var(--text);
  font-size:13px;
}
.medgen-patient-result span{
  color:var(--muted);
}
.medgen-selected-patient{
  display:none;
  margin-top:8px;
  padding:8px 10px;
  border:1px solid rgba(0,212,170,.35);
  background:rgba(0,212,170,.07);
  border-radius:10px;
  color:var(--accent2);
  font-size:12px;
}
.medgen-selected-patient button{
  margin-left:8px;
}

.medgen-notice{
  border:1px dashed var(--line2);
  border-radius:10px;
  color:var(--muted);
  padding:10px;
  font-size:12px;
  margin-top:10px;
}
@media(max-width:1050px){
  .medgen-grid{grid-template-columns:1fr;}
  .medgen-output{min-height:520px;}
}
@media(max-width:560px){
  .medgen-row,.medgen-templates{grid-template-columns:1fr;}
}
</style>

<div class="medgen-grid">
  <section class="card card-accent medgen-form">
    <div class="medgen-row">
      <div class="medgen-patient-search">
        <label>Nom</label>
        <input class="inp" id="mg_nom" placeholder="Ex: Dupont" autocomplete="off">
        <div class="medgen-patient-results" id="mg_patient_results"></div>
      </div>
      <div>
        <label>Prénom</label>
        <input class="inp" id="mg_prenom" placeholder="Ex: Alex" autocomplete="off">
      </div>
    </div>
    <div class="medgen-selected-patient" id="mg_selected_patient"></div>

    <div class="medgen-row">
      <div>
        <label>Date</label>
        <input class="inp" id="mg_date" type="date">
      </div>
      <div>
        <label>Heure</label>
        <input class="inp" id="mg_heure" type="time">
      </div>
    </div>

    <label>Templates rapides</label>
    <div class="medgen-templates" id="mg_templates"></div>

    <label>Type de blessure</label>
    <div class="medgen-chips" id="mg_types"></div>

    <label>Gravité</label>
    <div class="medgen-chips" id="mg_severites"></div>

    <label>Schéma corporel</label>
    <div class="medgen-small">Choisis type + gravité, puis clique sur une zone.</div>

    <div class="medgen-bodywrap">
      <svg width="300" height="430" viewBox="0 0 300 430" aria-label="Schéma corporel">
        <circle class="medgen-part" data-part="tête" cx="150" cy="45" r="32"/>
        <rect class="medgen-part" data-part="cou" x="134" y="78" width="32" height="18" rx="6"/>
        <rect class="medgen-part" data-part="thorax" x="105" y="100" width="90" height="95" rx="10"/>
        <rect class="medgen-part" data-part="abdomen" x="110" y="202" width="80" height="58" rx="8"/>
        <circle class="medgen-part" data-part="épaule gauche" cx="83" cy="125" r="21"/>
        <circle class="medgen-part" data-part="épaule droite" cx="217" cy="125" r="21"/>
        <rect class="medgen-part" data-part="bras gauche" x="62" y="150" width="26" height="78" rx="8"/>
        <rect class="medgen-part" data-part="bras droit" x="212" y="150" width="26" height="78" rx="8"/>
        <rect class="medgen-part" data-part="avant-bras gauche" x="58" y="236" width="24" height="64" rx="8"/>
        <rect class="medgen-part" data-part="avant-bras droit" x="218" y="236" width="24" height="64" rx="8"/>
        <rect class="medgen-part" data-part="main gauche" x="51" y="305" width="28" height="31" rx="8"/>
        <rect class="medgen-part" data-part="main droite" x="221" y="305" width="28" height="31" rx="8"/>
        <rect class="medgen-part" data-part="cuisse gauche" x="111" y="268" width="35" height="92" rx="8"/>
        <rect class="medgen-part" data-part="cuisse droite" x="154" y="268" width="35" height="92" rx="8"/>
        <rect class="medgen-part" data-part="jambe gauche" x="114" y="368" width="32" height="62" rx="8"/>
        <rect class="medgen-part" data-part="jambe droite" x="154" y="368" width="32" height="62" rx="8"/>
      </svg>
    </div>

    <label>Zones ajoutées</label>
    <div class="medgen-injuries" id="mg_injuries"></div>

    <div class="medgen-row">
      <div>
        <label>État du patient</label>
        <select class="inp" id="mg_etat">
          <option>Stable</option>
          <option>Instable</option>
          <option>Inconscient</option>
          <option>En état de choc</option>
        </select>
      </div>
      <div>
        <label>Orientation</label>
        <select class="inp" id="mg_destination">
          <option>Surveillance</option>
          <option>Bloc opératoire</option>
          <option>Soins intensifs</option>
          <option>Retour domicile</option>
        </select>
      </div>
    </div>

    <label>Notes  / contexte</label>
    <input class="inp" id="mg_notes" placeholder="Ex: blessure ancienne réouverte, douleur persistante, refus de soin...">

    <div class="medgen-actions">
      <button type="button" class="btn success" id="mg_generate">Générer</button>
      <button type="button" class="btn" id="mg_save_trauma">Enregistrer dans trauma</button>
      <button type="button" class="btn secondary" id="mg_copy">Copier</button>
      <button type="button" class="btn danger" id="mg_reset">Nouveau patient</button>
      <div id="mg_save_status" class="medgen-small" style="width:100%;margin-top:4px;"></div>
    </div>
  </section>

  <section class="card">
    <label style="display:block;margin:0 0 8px;color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.8px;font-weight:700;">
      Rapport médical + compte rendu opératoire
    </label>
    <textarea class="inp medgen-output" id="mg_output"></textarea>
    <div class="medgen-notice">
      Document interne à usage médical.
    </div>
  </section>
</div>

<script>
(function(){
  const CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const types = ["Blessure par balle","Blessure au couteau","Contusion","Brûlure","Fracture","Éclats / shrapnel","Hémorragie","Traumatisme crânien","Intoxication / malaise"];
  const severites = ["Légère","Moyenne","Grave"];
  const templates = [
    {name:"Tir thorax grave", type:"Blessure par balle", sev:"Grave", part:"thorax", etat:"Instable", dest:"Bloc opératoire"},
    {name:"Tir jambe moyen", type:"Blessure par balle", sev:"Moyenne", part:"jambe droite", etat:"Stable", dest:"Surveillance"},
    {name:"Couteau abdomen", type:"Blessure au couteau", sev:"Grave", part:"abdomen", etat:"Instable", dest:"Bloc opératoire"},
    {name:"Fracture bras", type:"Fracture", sev:"Moyenne", part:"avant-bras gauche", etat:"Stable", dest:"Surveillance"},
    {name:"Trauma tête", type:"Traumatisme crânien", sev:"Grave", part:"tête", etat:"Inconscient", dest:"Soins intensifs"},
    {name:"Contusion légère", type:"Contusion", sev:"Légère", part:"thorax", etat:"Stable", dest:"Retour domicile"}
  ];

  let selectedType = types[0];
  let selectedSeverity = severites[1];
  let injuries = [];
  let selectedPatientId = null;
  let patientSearchTimer = null;

  const $ = s => document.querySelector(s);
  const now = new Date();

  $("#mg_date").value = now.toISOString().slice(0,10);
  $("#mg_heure").value = now.toTimeString().slice(0,5);

  function renderChips(){
    $("#mg_types").innerHTML = "";
    types.forEach(v=>{
      const b=document.createElement("button");
      b.type="button";
      b.textContent=v;
      b.className="medgen-chip"+(v===selectedType?" active":"");
      b.onclick=()=>{selectedType=v; renderChips();};
      $("#mg_types").appendChild(b);
    });

    $("#mg_severites").innerHTML = "";
    severites.forEach(v=>{
      const b=document.createElement("button");
      b.type="button";
      b.textContent=v;
      b.dataset.sev=v;
      b.className="medgen-chip sev"+(v===selectedSeverity?" active":"");
      b.onclick=()=>{selectedSeverity=v; renderChips();};
      $("#mg_severites").appendChild(b);
    });
  }

  function renderTemplates(){
    $("#mg_templates").innerHTML="";
    templates.forEach(t=>{
      const b=document.createElement("button");
      b.type="button";
      b.className="medgen-template";
      b.textContent=t.name;
      b.onclick=()=>{
        selectedType=t.type;
        selectedSeverity=t.sev;
        $("#mg_etat").value=t.etat;
        $("#mg_destination").value=t.dest;
        injuries.push({part:t.part,type:t.type,severity:t.sev});
        renderChips();
        renderInjuries();
        generate();
      };
      $("#mg_templates").appendChild(b);
    });
  }

  function severityClass(s){
    return s==="Légère" ? "selL" : s==="Moyenne" ? "selM" : "selG";
  }

  function renderInjuries(){
    document.querySelectorAll(".medgen-part").forEach(p=>p.classList.remove("selL","selM","selG"));
    injuries.forEach(i=>{
      const p=[...document.querySelectorAll(".medgen-part")].find(x=>x.dataset.part===i.part);
      if(p) p.classList.add(severityClass(i.severity));
    });

    const box=$("#mg_injuries");
    box.innerHTML="";
    if(injuries.length===0){
      box.innerHTML='<p class="medgen-small">Aucune zone sélectionnée.</p>';
      return;
    }

    injuries.forEach((i,idx)=>{
      const d=document.createElement("div");
      d.className="medgen-injury";
      d.innerHTML=`<span>${i.part}<span class="medgen-badge">${i.type}</span><span class="medgen-badge">${i.severity}</span></span>`;
      const btn=document.createElement("button");
      btn.type="button";
      btn.className="btn secondary";
      btn.style.padding="6px 10px";
      btn.textContent="Retirer";
      btn.onclick=()=>{injuries.splice(idx,1); renderInjuries(); generate();};
      d.appendChild(btn);
      box.appendChild(d);
    });
  }

  function woundName(t){
    return {
      "Blessure par balle":"plaie balistique",
      "Blessure au couteau":"plaie par arme blanche",
      "Contusion":"contusion",
      "Brûlure":"brûlure",
      "Fracture":"suspicion de fracture",
      "Éclats / shrapnel":"lésions par éclats",
      "Hémorragie":"saignement actif",
      "Traumatisme crânien":"traumatisme crânien",
      "Intoxication / malaise":"malaise / intoxication"
    }[t] || t;
  }

  function triage(){
    if(injuries.some(i=>i.severity==="Grave")) return "Rouge / urgence absolue";
    if(injuries.some(i=>i.severity==="Moyenne")) return "Jaune / urgence relative";
    return "Vert / léger";
  }

  function injuryLine(i){
    const follow = i.severity==="Légère" ? "surveillance simple" : i.severity==="Moyenne" ? "surveillance rapprochée" : "prise en charge prioritaire";
    return `- ${i.part} : ${woundName(i.type)}, gravité ${i.severity.toLowerCase()}, ${follow}.`;
  }

  function gesture(i){
    if(i.type==="Blessure par balle") return `exploration  de la plaie au niveau ${i.part}, contrôle hémorragique, nettoyage, extraction des fragments accessibles et pansement compressif`;
    if(i.type==="Blessure au couteau") return `nettoyage de la plaie au niveau ${i.part}, contrôle du saignement, suture  si nécessaire et pansement stérile`;
    if(i.type==="Fracture") return `immobilisation de ${i.part}, contrôle de la douleur et demande d’imagerie `;
    if(i.type==="Brûlure") return `nettoyage, refroidissement , protection stérile et surveillance cutanée`;
    if(i.type==="Traumatisme crânien") return `surveillance neurologique , contrôle des constantes et observation stricte`;
    if(i.type==="Hémorragie") return `compression, contrôle du saignement et stabilisation avant transfert`;
    return `soins locaux au niveau ${i.part}, nettoyage, contrôle de la douleur et pansement adapté`;
  }

  function followUp(){
    let out=[];
    if(injuries.some(i=>i.severity==="Grave")) out.push("- Douleur récurrente possible, fatigue à l’effort, reprise progressive conseillée.");
    if(injuries.some(i=>i.type==="Blessure par balle" || i.type==="Éclats / shrapnel")) out.push("- Gêne persistante possible liée à la cicatrisation ou à des fragments résiduels .");
    if(injuries.some(i=>i.type==="Fracture")) out.push("- Mobilité à surveiller, contrôle  conseillé avant reprise d’activité intense.");
    if(injuries.some(i=>i.type==="Traumatisme crânien")) out.push("- Surveillance des maux de tête, vertiges, troubles de mémoire ou malaise .");
    if(out.length===0) out.push("- Surveillance simple, nouveau contrôle si douleur persistante ou aggravation.");
    return out.join("\n");
  }

  function clearSelectedPatient(){
    selectedPatientId = null;
    const box = $("#mg_selected_patient");
    box.style.display = "none";
    box.innerHTML = "";
  }

  function setSelectedPatient(patient){
    selectedPatientId = Number(patient.id);
    $("#mg_nom").value = patient.last_name || "";
    $("#mg_prenom").value = patient.first_name || "";
    $("#mg_patient_results").style.display = "none";
    $("#mg_patient_results").innerHTML = "";

    const status = patient.is_deceased ? "Décédé" : "HP " + (patient.survival ?? "—");
    const last = patient.last_injury_at ? " — Dernière blessure : " + patient.last_injury_at : "";

    const box = $("#mg_selected_patient");
    box.style.display = "block";
    box.innerHTML = `Patient existant sélectionné : <strong>${escapeHtml(patient.last_name)} ${escapeHtml(patient.first_name)}</strong> — ${escapeHtml(status + last)} <button type="button" class="btn secondary" style="padding:4px 8px;font-size:11px;" id="mg_unselect_patient">Créer nouveau</button>`;
    $("#mg_unselect_patient").onclick = clearSelectedPatient;
    generate();
  }

  function escapeHtml(value){
    return String(value ?? "").replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  async function searchPatients(){
    const q = (($("#mg_nom").value || "") + " " + ($("#mg_prenom").value || "")).trim();
    const results = $("#mg_patient_results");

    if(q.length < 2){
      results.style.display = "none";
      results.innerHTML = "";
      return;
    }

    try{
      const res = await fetch('/api/trauma.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'search_patients', csrf:CSRF, q})
      });
      const data = await res.json();

      if(!data.ok || !Array.isArray(data.patients) || data.patients.length === 0){
        results.style.display = "none";
        results.innerHTML = "";
        return;
      }

      results.innerHTML = data.patients.map((p, idx)=>{
        const status = p.is_deceased ? "Décédé" : "HP " + (p.survival ?? "—");
        const last = p.last_injury_at ? " — Dernière blessure : " + p.last_injury_at : "";
        return `<div class="medgen-patient-result" data-idx="${idx}">
          <strong>${escapeHtml(p.last_name)} ${escapeHtml(p.first_name)}</strong>
          <span>${escapeHtml(status + last)}</span>
        </div>`;
      }).join("");
      results.style.display = "block";

      results.querySelectorAll("[data-idx]").forEach(el=>{
        el.onclick = () => setSelectedPatient(data.patients[Number(el.dataset.idx)]);
      });
    }catch(e){
      results.style.display = "none";
      results.innerHTML = "";
    }
  }

  function schedulePatientSearch(){
    clearSelectedPatient();
    clearTimeout(patientSearchTimer);
    patientSearchTimer = setTimeout(searchPatients, 220);
  }

  async function saveToTrauma(){
    generate();

    const nom = $("#mg_nom").value.trim();
    const prenom = $("#mg_prenom").value.trim();

    if(!nom || !prenom){
      alert("Nom et prénom requis pour enregistrer dans trauma.");
      return;
    }
    if(!injuries.length){
      alert("Ajoute au moins une blessure avant l'enregistrement trauma.");
      return;
    }

    const date = $("#mg_date").value || now.toISOString().slice(0,10);
    const heure = $("#mg_heure").value || now.toTimeString().slice(0,5);
    const notes = $("#mg_notes").value.trim();

    const status = $("#mg_save_status");
    status.textContent = "Enregistrement en cours…";

    try{
      const res = await fetch('/api/trauma.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          action:'import_medical_report',
          csrf:CSRF,
          last_name:nom,
          first_name:prenom,
          patient_id:selectedPatientId,
          injury_date:date + ' ' + heure,
          injuries:injuries,
          note:notes,
          report_text:$("#mg_output").value
        })
      });

      const data = await res.json().catch(()=>null);
      if(!res.ok || !data || !data.ok){
        throw new Error((data && data.error) ? data.error : "Erreur API");
      }

      status.textContent = "Enregistré dans trauma : " + data.inserted + " blessure(s), dégâts totaux −" + data.total_penalty + " HP. HP actuel : " + data.patient_survival + " HP.";
      status.style.color = "var(--accent2)";
    }catch(e){
      status.textContent = "Erreur : " + e.message;
      status.style.color = "#ff7a7a";
    }
  }

  function generate(){
    const nom=$("#mg_nom").value.trim() || "Non renseigné";
    const prenom=$("#mg_prenom").value.trim() || "Non renseigné";
    const date=$("#mg_date").value || now.toISOString().slice(0,10);
    const heure=$("#mg_heure").value || now.toTimeString().slice(0,5);
    const etat=$("#mg_etat").value;
    const dest=$("#mg_destination").value;
    const notes=$("#mg_notes").value.trim() || "Aucune note complémentaire.";
    const wounds = injuries.length ? injuries.map(injuryLine).join("\n") : "- Aucune blessure renseignée.";
    const gestures = injuries.length ? injuries.map(i=>`- ${gesture(i)}.`).join("\n") : "- Aucun geste renseigné.";
    const motif = injuries.length ? injuries.map(i=>`${woundName(i.type)} (${i.part})`).join(", ") : "évaluation médicale";

    $("#mg_output").value =
`RAPPORT MÉDICAL + COMPTE RENDU OPÉRATOIRE 

IDENTITÉ
Patient : ${prenom} ${nom}
Date / heure : ${date} à ${heure}
État à l’arrivée : ${etat}
Classification triage : ${triage()}
Orientation : ${dest}

MOTIF DE PRISE EN CHARGE
${motif}

CONSTAT INITIAL
Patient pris en charge en contexte d’intervention . Évaluation rapide effectuée afin de déterminer la priorité de soins et l’orientation.

BLESSURES CONSTATÉES
${wounds}

PRISE EN CHARGE IMMÉDIATE
- Mise en sécurité du patient.
- Évaluation rapide de l’état général.
- Contrôle visuel des lésions.
- Compression / pansement / immobilisation selon nécessité .
- Stabilisation avant orientation.

COMPTE RENDU OPÉRATOIRE 
Diagnostic préopératoire :
${wounds}

Gestes réalisés :
${gestures}

Observations peropératoires :
- Prise en charge réalisée dans un contexte d’urgence.
- Stabilisation effectuée selon gravité.
- Aucune complication majeure indiquée, sauf mention contraire dans les notes.

ÉTAT POST-OPÉRATOIRE
Patient orienté vers : ${dest}.
État à surveiller selon évolution .

SUIVI CONSEILLÉ / CONTEXTE POUR BLESSURE RÉCURRENTE
${followUp()}

NOTES 
${notes}

CONCLUSION
Rapport conservé pour suivi médical , continuité des soins, douleurs récurrentes, cicatrisation lente ou évolution ultérieure de la blessure.`;
  }

  document.querySelectorAll(".medgen-part").forEach(p=>{
    p.onclick=()=>{
      injuries.push({part:p.dataset.part,type:selectedType,severity:selectedSeverity});
      renderInjuries();
      generate();
    };
  });

  ["mg_nom","mg_prenom"].forEach(id=>{
    $("#"+id).addEventListener("input",()=>{
      schedulePatientSearch();
      generate();
    });
    $("#"+id).addEventListener("focus",searchPatients);
  });

  document.addEventListener("click",(e)=>{
    if(!e.target.closest(".medgen-patient-search") && e.target.id !== "mg_prenom"){
      $("#mg_patient_results").style.display = "none";
    }
  });

  ["mg_date","mg_heure","mg_etat","mg_destination","mg_notes"].forEach(id=>{
    $("#"+id).addEventListener("input",generate);
  });

  $("#mg_generate").onclick=generate;
  $("#mg_save_trauma").onclick=saveToTrauma;
  $("#mg_copy").onclick=()=>{
    navigator.clipboard.writeText($("#mg_output").value);
    $("#mg_copy").textContent="Copié ✓";
    setTimeout(()=>$("#mg_copy").textContent="Copier",1200);
  };
  $("#mg_reset").onclick=()=>{
    $("#mg_nom").value="";
    $("#mg_prenom").value="";
    $("#mg_date").value=now.toISOString().slice(0,10);
    $("#mg_heure").value=now.toTimeString().slice(0,5);
    $("#mg_etat").value="Stable";
    $("#mg_destination").value="Surveillance";
    $("#mg_notes").value="";
    $("#mg_save_status").textContent="";
    clearSelectedPatient();
    injuries=[];
    selectedType=types[0];
    selectedSeverity=severites[1];
    renderChips();
    renderInjuries();
    generate();
  };

  renderChips();
  renderTemplates();
  renderInjuries();
  generate();
})();
</script>

<?php
samr_layout_end();
