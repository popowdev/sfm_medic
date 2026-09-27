<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';
$username= (string)($u['username'] ?? '');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$parts    = preg_split('/[\s|]+/', strip_tags($username));
$initials = '';
foreach (array_slice($parts, 0, 2) as $p) {
  if (trim($p)) $initials .= mb_strtoupper(mb_substr(trim($p), 0, 1));
}

samr_layout_start('SAMR — Notes de service');
?>

<style>
.sn-wrap{ max-width:720px; margin:0 auto; }

/* Header */
.sn-header{
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:12px; margin-bottom:20px;
}
.sn-header h1{ font-family:var(--font-head); font-size:20px; font-weight:800; margin:0; }

/* Badge "Note de service" */
.sn-badge{
  display:inline-flex; align-items:center; gap:6px;
  font-size:11px; font-weight:700;
  padding:4px 12px; border-radius:999px;
  background:rgba(255,180,50,.1); border:1px solid rgba(255,180,50,.3);
  color:#ffb060; letter-spacing:.3px;
}

/* Composer (admin uniquement) */
.composer{
  background:linear-gradient(160deg, rgba(13,18,25,.95), rgba(10,15,21,.98));
  border:1px solid rgba(255,180,50,.25);
  border-radius:16px; padding:16px; margin-bottom:20px;
}
.composer-label{
  font-size:11px; font-weight:700; text-transform:uppercase;
  letter-spacing:.6px; color:#ffb060; margin-bottom:10px;
  display:flex; align-items:center; gap:6px;
}
.composer-input{
  width:100%; background:rgba(255,255,255,.04);
  border:1px solid rgba(255,180,50,.2); border-radius:12px;
  padding:11px 14px; color:var(--text); font-family:var(--font-body);
  font-size:14px; resize:none; min-height:90px; transition:border-color .15s;
}
.composer-input:focus{ outline:none; border-color:rgba(255,180,50,.5); background:rgba(255,180,50,.03); }
.composer-footer{
  display:flex; justify-content:space-between; align-items:center;
  margin-top:10px; gap:10px;
}
.char-count{ font-size:11px; color:var(--muted); }
.char-count.warn{ color:#ffb060; }
.char-count.over{ color:#ff7a7a; }

/* Post de service */
.sn-post{
  border-radius:16px; margin-bottom:14px; overflow:hidden;
  border:1px solid rgba(255,180,50,.2);
  background:linear-gradient(160deg, rgba(20,16,10,.95), rgba(10,15,21,.98));
}
.sn-post.pinned{
  border-color:rgba(255,180,50,.5);
  background:linear-gradient(160deg, rgba(40,28,8,.95), rgba(10,15,21,.98));
}
.sn-post-head{
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 16px; gap:10px;
  background:rgba(255,180,50,.06);
  border-bottom:1px solid rgba(255,180,50,.12);
}
.sn-post-meta{ display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
.sn-avatar{
  width:34px; height:34px; border-radius:9px;
  background:rgba(255,180,50,.15); border:1px solid rgba(255,180,50,.3);
  display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:800; color:#ffb060; flex-shrink:0;
}
.sn-author{ font-weight:700; font-size:13px; }
.sn-time{ font-size:11px; color:var(--muted); }
.sn-pin{ font-size:11px; color:#ffb060; }

.sn-post-body{
  padding:16px;
  font-size:14px; line-height:1.7;
  white-space:pre-wrap; word-break:break-word;
}

.sn-post-footer{
  display:flex; align-items:center; gap:6px;
  padding:8px 14px;
  border-top:1px solid rgba(255,180,50,.08);
  flex-wrap:wrap;
}

/* Actions */
.sn-action-btn{
  display:inline-flex; align-items:center; gap:5px;
  padding:5px 10px; border-radius:8px; border:1px solid transparent;
  background:transparent; color:var(--muted); font-size:12px;
  font-family:var(--font-body); cursor:pointer; transition:all .12s;
}
.sn-action-btn:hover{ background:rgba(255,255,255,.05); color:var(--text); border-color:var(--line); }
.sn-action-btn.heart.active{ color:#ff6b8a; border-color:rgba(255,80,120,.3); background:rgba(255,80,120,.08); }

/* Menu */
.sn-menu{ margin-left:auto; position:relative; }
.sn-menu-btn{ background:none; border:none; color:var(--muted); cursor:pointer; padding:4px 8px; border-radius:7px; font-size:16px; }
.sn-menu-btn:hover{ background:rgba(255,255,255,.06); color:var(--text); }
.sn-menu-dropdown{
  display:none; position:absolute; right:0; top:100%;
  background:#0d1219; border:1px solid var(--line2);
  border-radius:10px; padding:4px; min-width:140px;
  z-index:100; box-shadow:0 8px 24px rgba(0,0,0,.4);
}
.sn-menu-dropdown.open{ display:block; }
.sn-menu-item{
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border-radius:7px; font-size:13px;
  cursor:pointer; color:var(--text); border:none;
  background:none; width:100%; text-align:left; font-family:var(--font-body);
}
.sn-menu-item:hover{ background:rgba(255,255,255,.06); }
.sn-menu-item.danger{ color:#ff7a7a; }
.sn-menu-item.danger:hover{ background:rgba(255,79,79,.1); }

/* Edit */
.sn-edit-area{ display:none; padding:12px 16px; border-top:1px solid rgba(255,180,50,.1); }
.sn-edit-area textarea{
  width:100%; background:rgba(255,255,255,.04);
  border:1px solid rgba(255,180,50,.4); border-radius:10px;
  padding:10px 12px; color:var(--text); font-family:var(--font-body);
  font-size:14px; resize:none; min-height:80px;
}
.sn-edit-actions{ display:flex; gap:8px; justify-content:flex-end; margin-top:8px; }

/* Vide / load more */
.sn-empty{ text-align:center; padding:48px; color:var(--muted); }
.sn-empty .icon{ font-size:40px; margin-bottom:12px; }
.load-more-btn{
  display:block; width:100%; padding:12px;
  border-radius:12px; border:1px dashed var(--line2);
  background:transparent; color:var(--muted); font-family:var(--font-body);
  font-size:13px; cursor:pointer; margin-top:4px; transition:all .15s;
}
.load-more-btn:hover{ border-color:rgba(255,180,50,.4); color:#ffb060; background:rgba(255,180,50,.04); }

/* Info non-admin */
.readonly-notice{
  padding:12px 16px; border-radius:12px; margin-bottom:20px;
  background:rgba(255,180,50,.06); border:1px solid rgba(255,180,50,.2);
  font-size:13px; color:#ffb060; display:flex; align-items:center; gap:8px;
}
</style>

<div class="sn-wrap">

  <div class="sn-header">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <h1>📋 Notes de service</h1>
        <span class="sn-badge">📢 Officiel</span>
      </div>
      <div style="font-size:13px;color:var(--muted);">Communications officielles et directives internes SAMR</div>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <!-- Composer admin -->
  <div class="composer">
    <div class="composer-label">✍️ Publier une note de service</div>
    <textarea class="composer-input" id="newPostBody"
      placeholder="Rédigez une note de service, directive ou information officielle…"
      maxlength="2000"></textarea>
    <div class="composer-footer">
      <span class="char-count" id="charCount">0 / 2000</span>
      <button class="btn" id="btnPost" style="background:rgba(255,180,50,.15);border-color:rgba(255,180,50,.35);color:#ffb060;">
        📢 Publier
      </button>
    </div>
  </div>
  <?php else: ?>
  <div class="readonly-notice">
    📢 Ce fil est réservé aux communications officielles de la direction SAMR.
  </div>
  <?php endif; ?>

  <!-- Feed -->
  <div id="snFeed"></div>
  <button class="load-more-btn" id="btnLoadMore" style="display:none;">Charger plus…</button>

</div>

<script>
(function(){
  const ME       = <?= json_encode($uid) ?>;
  const IS_ADMIN = <?= json_encode($isAdmin) ?>;
  const INITIALS = <?= json_encode($initials ?: '?', JSON_UNESCAPED_UNICODE) ?>;
  const CSRF     = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const FEED_TYPE= 'service_note';

  const feedEl  = document.getElementById('snFeed');
  const btnMore = document.getElementById('btnLoadMore');
  let lastId    = 0;
  let loading   = false;

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function timeAgo(dateStr) {
    const d    = new Date(dateStr);
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60)    return 'À l\'instant';
    if (diff < 3600)  return Math.floor(diff/60) + ' min';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return d.toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' });
  }
  function getInitials(name) {
    return String(name||'').split(/[\s|]+/).filter(Boolean)
      .slice(0,2).map(p=>(p[0]||'').toUpperCase()).join('') || '?';
  }
  async function api(payload) {
    const r = await fetch(`/api/feed.php?type=${FEED_TYPE}`, {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({...payload, csrf:CSRF, type:FEED_TYPE})
    });
    const d = await r.json().catch(()=>null);
    if (!r.ok) throw new Error(d?.error || 'Erreur API');
    return d;
  }

  async function loadPosts(append = false) {
    if (loading) return;
    loading = true;
    btnMore.style.display = 'none';

    try {
      const url = `/api/feed.php?type=${FEED_TYPE}` + (lastId ? `&before=${lastId}` : '');
      const r   = await fetch(url, { credentials:'same-origin' });
      const d   = await r.json();
      if (!d.ok) throw new Error(d.error || 'Erreur');

      if (!append) feedEl.innerHTML = '';

      if (!d.posts.length && !append) {
        feedEl.innerHTML = `<div class="sn-empty">
          <div class="icon">📋</div>
          <div>Aucune note de service pour le moment.</div>
        </div>`;
        return;
      }

      for (const p of d.posts) {
        feedEl.appendChild(renderPost(p));
        lastId = p.id;
      }
      if (d.has_more) btnMore.style.display = 'block';
    } catch(e) {
      feedEl.innerHTML = `<div class="sn-empty">Impossible de charger : ${esc(e.message)}</div>`;
    } finally { loading = false; }
  }

  function renderPost(p) {
    const el       = document.createElement('div');
    el.className   = 'sn-post' + (p.pinned ? ' pinned' : '');
    el.dataset.postId = p.id;

    const ini     = getInitials(p.username);
    const canEdit = p.can_edit;

    el.innerHTML = `
      <div class="sn-post-head">
        <div class="sn-post-meta">
          <div class="sn-avatar">${esc(ini)}</div>
          <div>
            <div class="sn-author">${esc(p.username)}${p.grade ? ` <span style="font-size:10px;color:var(--muted);font-weight:400;">— ${esc(p.grade)}</span>` : ''}</div>
            <div class="sn-time">${timeAgo(p.created_at)}${p.updated_at ? ' · modifié' : ''}${p.pinned ? ' · <span class="sn-pin">📌 Épinglé</span>' : ''}</div>
          </div>
        </div>
        ${canEdit ? `
        <div class="sn-menu">
          <button class="sn-menu-btn" data-menu="${p.id}">⋯</button>
          <div class="sn-menu-dropdown" id="snmenu-${p.id}">
            <button class="sn-menu-item" data-action="edit" data-id="${p.id}">✏️ Modifier</button>
            <button class="sn-menu-item" data-action="pin"  data-id="${p.id}">${p.pinned ? '📌 Dépingler' : '📌 Épingler'}</button>
            <button class="sn-menu-item danger" data-action="delete" data-id="${p.id}">🗑 Supprimer</button>
          </div>
        </div>` : ''}
      </div>

      <div class="sn-post-body" id="snbody-${p.id}">${esc(p.body)}</div>

      <div class="sn-edit-area" id="snedit-${p.id}">
        <textarea id="snedit-ta-${p.id}">${esc(p.body)}</textarea>
        <div class="sn-edit-actions">
          <button class="btn secondary" style="font-size:12px;" data-cancel-edit="${p.id}">Annuler</button>
          <button class="btn" style="font-size:12px;background:rgba(255,180,50,.15);border-color:rgba(255,180,50,.35);color:#ffb060;" data-save-edit="${p.id}">Enregistrer</button>
        </div>
      </div>

      <div class="sn-post-footer">
        <button class="sn-action-btn heart ${p.my_reaction ? 'active' : ''}" data-heart="${p.id}">
          ❤️ <span id="snhearts-${p.id}">${p.hearts}</span>
        </button>
      </div>
    `;

    const menuBtn  = el.querySelector(`[data-menu="${p.id}"]`);
    const dropdown = el.querySelector(`#snmenu-${p.id}`);
    menuBtn?.addEventListener('click', e => {
      e.stopPropagation();
      document.querySelectorAll('.sn-menu-dropdown.open').forEach(d => { if(d!==dropdown) d.classList.remove('open'); });
      dropdown?.classList.toggle('open');
    });

    el.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', async () => {
        dropdown?.classList.remove('open');
        const act = btn.dataset.action;
        const id  = parseInt(btn.dataset.id);
        if (act === 'edit')   openEdit(el, id, p.body);
        if (act === 'delete') await deletePost(el, id);
        if (act === 'pin')    await togglePin(id);
      });
    });

    el.querySelector(`[data-cancel-edit="${p.id}"]`)?.addEventListener('click', () => closeEdit(el, p.id));
    el.querySelector(`[data-save-edit="${p.id}"]`)?.addEventListener('click', async () => {
      const ta = el.querySelector(`#snedit-ta-${p.id}`);
      const newBody = ta?.value.trim() || '';
      if (!newBody) return;
      try {
        await api({ action:'edit_post', post_id:p.id, body:newBody });
        el.querySelector(`#snbody-${p.id}`).textContent = newBody;
        p.body = newBody;
        closeEdit(el, p.id);
        el.querySelector('.sn-time').textContent += ' · modifié';
      } catch(err) { alert(err.message); }
    });

    el.querySelector(`[data-heart="${p.id}"]`)?.addEventListener('click', async function() {
      try {
        const d = await api({ action:'toggle_reaction', post_id:p.id });
        this.classList.toggle('active', d.active);
        el.querySelector(`#snhearts-${p.id}`).textContent = d.count;
      } catch(err) { alert(err.message); }
    });

    return el;
  }

  function openEdit(el, id, body) {
    el.querySelector(`#snbody-${id}`).style.display = 'none';
    const area = el.querySelector(`#snedit-${id}`);
    area.style.display = 'block';
    el.querySelector(`#snedit-ta-${id}`).value = body;
    el.querySelector(`#snedit-ta-${id}`).focus();
  }
  function closeEdit(el, id) {
    el.querySelector(`#snbody-${id}`).style.display = '';
    el.querySelector(`#snedit-${id}`).style.display = 'none';
  }
  async function deletePost(el, id) {
    if (!confirm('Supprimer cette note de service ?')) return;
    try {
      await api({ action:'delete_post', post_id:id });
      el.style.opacity='0'; el.style.transition='opacity .3s';
      setTimeout(() => el.remove(), 300);
    } catch(e) { alert(e.message); }
  }
  async function togglePin(id) {
    try {
      await api({ action:'toggle_pin', post_id:id });
      feedEl.innerHTML=''; lastId=0;
      await loadPosts();
    } catch(e) { alert(e.message); }
  }

  const bodyInput = document.getElementById('newPostBody');
  const charCount = document.getElementById('charCount');
  const btnPost   = document.getElementById('btnPost');

  bodyInput?.addEventListener('input', () => {
    const len = bodyInput.value.length;
    charCount.textContent = len + ' / 2000';
    charCount.className = 'char-count' + (len > 1800 ? ' warn' : '') + (len > 2000 ? ' over' : '');
  });

  bodyInput?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); btnPost?.click(); }
  });

  btnPost?.addEventListener('click', async () => {
    const body = bodyInput.value.trim();
    if (!body || body.length > 2000) return;

    btnPost.disabled = true;
    btnPost.textContent = 'Publication…';
    try {
      const d = await api({ action:'create_post', body });
      bodyInput.value = '';
      charCount.textContent = '0 / 2000';

      const emptyState = feedEl.querySelector('.sn-empty');
      if (emptyState) feedEl.innerHTML = '';

      const newEl = renderPost(d.post);
      newEl.style.opacity = '0';
      newEl.style.transform = 'translateY(-10px)';
      newEl.style.transition = 'opacity .3s, transform .3s';
      feedEl.insertBefore(newEl, feedEl.firstChild);
      requestAnimationFrame(() => { newEl.style.opacity='1'; newEl.style.transform='translateY(0)'; });
    } catch(e) { alert(e.message); }
    finally { btnPost.disabled=false; btnPost.textContent='📢 Publier'; }
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.sn-menu-dropdown.open').forEach(d => d.classList.remove('open'));
  });

  btnMore.addEventListener('click', () => loadPosts(true));
  loadPosts();
})();
</script>

<?php samr_layout_end(); ?>
