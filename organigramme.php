<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_login();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR — Organigramme');
?>

<style>
.org-header{
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:12px; margin-bottom:24px;
}
.org-header h1{ font-family:var(--font-head); font-size:20px; font-weight:800; margin:0; }

/* Recherche */
.search-inp{
  padding:7px 14px; border-radius:999px; border:1px solid var(--line2);
  background:rgba(255,255,255,.03); color:var(--text);
  font-family:var(--font-body); font-size:12px; width:220px;
  transition:border-color .15s;
}
.search-inp:focus{ outline:none; border-color:rgba(59,158,255,.4); }

/* ── Organigramme hiérarchique ── */
.org-tree{
  display:flex;
  flex-direction:column;
  gap:0;
}

/* Niveau hiérarchique */
.org-level{
  display:flex;
  flex-direction:column;
  gap:0;
}

.level-label{
  display:flex;
  align-items:center;
  gap:10px;
  padding:6px 0 6px 0;
  margin-bottom:8px;
  margin-top:20px;
}
.level-label:first-child{ margin-top:0; }

.level-line{
  flex:1; height:1px;
  background:linear-gradient(90deg, var(--line2) 0%, transparent 100%);
}
.level-title{
  font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:1px; white-space:nowrap; padding:0 4px;
}

/* Grille des cartes d'un même niveau */
.level-cards{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  padding-left:16px;
  margin-bottom:4px;
  position:relative;
}

/* Ligne verticale gauche par niveau */
.level-cards::before{
  content:'';
  position:absolute;
  left:0; top:0; bottom:0;
  width:2px;
  border-radius:2px;
}

/* ── Carte compacte ── */
.agent-chip{
  display:flex;
  align-items:center;
  gap:10px;
  padding:9px 14px;
  border-radius:12px;
  border:1px solid var(--line);
  background:rgba(13,18,25,.9);
  transition:border-color .15s, transform .12s;
  min-width:200px;
  max-width:280px;
  flex:0 0 auto;
}
.agent-chip:hover{
  border-color:rgba(59,158,255,.3);
  transform:translateY(-1px);
}
.agent-chip.hidden{ display:none; }

.chip-avatar{
  width:34px; height:34px;
  border-radius:9px;
  display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:800;
  flex-shrink:0;
  overflow:hidden;
}
.chip-avatar img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}

.chip-info{ min-width:0; flex:1; }

.chip-id{
  font-size:10px; color:var(--muted); font-weight:700;
  letter-spacing:.3px; line-height:1; margin-bottom:1px;
}
.chip-name{
  font-family:var(--font-head);
  font-size:13px; font-weight:700;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  line-height:1.2; margin-bottom:3px;
}
.chip-grade{
  font-size:10px; font-weight:600;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.chip-phone{
  font-size:11px; color:var(--muted);
  white-space:nowrap;
  flex-shrink:0;
}

/* Vide */
.org-empty{
  text-align:center; padding:48px; color:var(--muted);
}
</style>

<div class="org-header">
  <div>
    <h1>👥 Organigramme</h1>
    <div style="font-size:13px;color:var(--muted);">Personnel SAMR — <span id="orgCount">…</span></div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <input class="search-inp" id="orgSearch" placeholder="🔍 Rechercher…">
    <button class="btn secondary" id="btnRefresh" style="font-size:12px;">🔄 Actualiser</button>
  </div>
</div>

<div id="orgTree" class="org-tree">
  <div class="org-empty">Chargement…</div>
</div>

<script>
(function(){

  const HIERARCHY = [
    { grade:'Directeur',         color:'#ff4f4f', label:'Direction'             },
    { grade:'Co-Directeur',      color:'#ff8c00', label:'Co-Direction'          },
    { grade:'Chef d\'équipe',    color:'#ffb060', label:'Chefs d\'équipe'       },
    { grade:'Médecin en chef',   color:'#a78bfa', label:'Médecins en chef'      },
    { grade:'Médecin',           color:'#818cf8', label:'Médecins'              },
    { grade:'Infirmier en chef', color:'#3b9eff', label:'Infirmiers en chef'    },
    { grade:'Infirmier',         color:'#60c0ff', label:'Infirmiers'            },
    { grade:'Chef Ambulancier',  color:'#34d399', label:'Chefs ambulanciers'    },
    { grade:'Ambulancier',       color:'#00d4aa', label:'Ambulanciers'          },
  ];

  const GRADE_MAP = {};
  HIERARCHY.forEach(h => { GRADE_MAP[h.grade] = h; });

  function gradeInfo(grade) {
    return GRADE_MAP[grade] || { color:'#5a7a96', label:'Non classé' };
  }

  function getInitials(name) {
    return String(name||'').split(/[\s|]+/).filter(Boolean)
      .slice(0,2).map(p=>(p[0]||'').toUpperCase()).join('') || '?';
  }

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  let allPeople   = [];
  let searchQuery = '';

  async function load() {
    try {
      const r = await fetch('/api/board.php', { credentials:'same-origin' });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error);
      allPeople = d.people || [];
      render();
    } catch(e) {
      document.getElementById('orgTree').innerHTML =
        `<div class="org-empty">Erreur : ${esc(e.message)}</div>`;
    }
  }

  function render() {
    const tree = document.getElementById('orgTree');
    const q    = searchQuery.toLowerCase();

    const byGrade = {};
    let total = 0;

    for (const p of allPeople) {
      const g = p.grade || p.role || '';
      if (!byGrade[g]) byGrade[g] = [];
      byGrade[g].push(p);
    }

    for (const g of Object.keys(byGrade)) {
      byGrade[g].sort((a,b) => {
        const aM = parseInt(a.matricule || '9999');
        const bM = parseInt(b.matricule || '9999');
        if (aM !== bM) return aM - bM;
        return (a.name||'').localeCompare(b.name||'', 'fr');
      });
    }

    tree.innerHTML = '';

    const renderedGrades = new Set();

    for (const level of HIERARCHY) {
      const people = byGrade[level.grade] || [];
      renderedGrades.add(level.grade);

      const visible = q
        ? people.filter(p => [p.name, p.grade, p.matricule, p.phone, p.role]
            .join(' ').toLowerCase().includes(q))
        : people;

      if (!visible.length) continue;
      total += visible.length;

      const section = document.createElement('div');
      section.className = 'org-level';

      const labelEl = document.createElement('div');
      labelEl.className = 'level-label';
      labelEl.innerHTML = `
        <span class="level-title" style="color:${esc(level.color)}">${esc(level.label)}</span>
        <div class="level-line" style="background:linear-gradient(90deg,${esc(level.color)}44 0%,transparent 100%)"></div>
        <span style="font-size:11px;color:var(--muted);white-space:nowrap;">${visible.length}</span>
      `;

      const cardsEl = document.createElement('div');
      cardsEl.className = 'level-cards';
      cardsEl.style.setProperty('--level-color', level.color);
      cardsEl.style.cssText += `--level-color:${level.color}`;

      const pseudoStyle = document.createElement('style');
      pseudoStyle.textContent = '';
      cardsEl.style.borderLeft = `2px solid ${level.color}44`;

      for (const p of visible) {
        cardsEl.appendChild(renderChip(p, level));
      }

      section.appendChild(labelEl);
      section.appendChild(cardsEl);
      tree.appendChild(section);
    }

    const ungrouped = allPeople.filter(p => !renderedGrades.has(p.grade || '') && !renderedGrades.has(p.role || ''));
    const ungroupedVisible = q
      ? ungrouped.filter(p => [p.name, p.grade, p.matricule, p.phone, p.role]
          .join(' ').toLowerCase().includes(q))
      : ungrouped;

    if (ungroupedVisible.length) {
      total += ungroupedVisible.length;
      const section = document.createElement('div');
      section.className = 'org-level';
      section.innerHTML = `
        <div class="level-label">
          <span class="level-title" style="color:var(--muted)">Personnel</span>
          <div class="level-line"></div>
          <span style="font-size:11px;color:var(--muted)">${ungroupedVisible.length}</span>
        </div>
      `;
      const cardsEl = document.createElement('div');
      cardsEl.className = 'level-cards';
      cardsEl.style.borderLeft = '2px solid var(--line2)';
      for (const p of ungroupedVisible) {
        cardsEl.appendChild(renderChip(p, { color:'#5a7a96' }));
      }
      section.appendChild(cardsEl);
      tree.appendChild(section);
    }

    if (!tree.children.length) {
      tree.innerHTML = '<div class="org-empty">Aucun agent trouvé.</div>';
      total = 0;
    }

    document.getElementById('orgCount').textContent =
      `${total} agent${total > 1 ? 's' : ''}`;
  }

  function renderChip(p, level) {
    const color    = level.color;
    const initials = getInitials(p.name);

    const nameDisplay = p.name;
    const matriculeDisplay = p.matricule ? p.matricule : '';
    const photoUrl = (p.profile_photo_url || '').trim();

    const chip = document.createElement('div');
    chip.className = 'agent-chip';

    chip.innerHTML = `
      <div class="chip-avatar" style="background:${color}18;border:1px solid ${color}35;">
        ${photoUrl
          ? `<img src="${esc(photoUrl)}" alt="${esc(p.name)}">`
          : `<span style="color:${color};font-size:12px;font-weight:800;">${esc(initials)}</span>`}
      </div>
      <div class="chip-info">
        ${matriculeDisplay
          ? `<div class="chip-id">${esc(matriculeDisplay)}</div>`
          : ''}
        <div class="chip-name" title="${esc(p.name)}">${esc(nameDisplay)}</div>
        <div class="chip-grade" style="color:${color}cc;">${esc(p.grade || p.role || '—')}</div>
      </div>
      ${p.phone
        ? `<div class="chip-phone">📞 ${esc(p.phone)}</div>`
        : ''}
    `;

    return chip;
  }

  document.getElementById('orgSearch').addEventListener('input', function() {
    searchQuery = this.value.trim();
    render();
  });

  document.getElementById('btnRefresh').addEventListener('click', load);

  load();
})();
</script>

<?php samr_layout_end(); ?>
