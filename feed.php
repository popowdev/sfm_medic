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

samr_layout_start('SAMR — Notes internes');
?>

<style>
.feed-wrap{
  max-width: 720px;
  margin: 0 auto;
}
/* Composer */
.composer{
  background: linear-gradient(160deg, rgba(13,18,25,.95), rgba(10,15,21,.98));
  border: 1px solid var(--line2);
  border-radius: 16px;
  padding: 16px;
  margin-bottom: 20px;
}
.composer-row{
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.avatar{
  width: 38px; height: 38px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800; color: #fff;
  flex-shrink: 0;
}
.avatar.sm{ width:30px; height:30px; border-radius:8px; font-size:11px; }
.composer-input{
  flex: 1;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--line2);
  border-radius: 12px;
  padding: 11px 14px;
  color: var(--text);
  font-family: var(--font-body);
  font-size: 14px;
  resize: none;
  min-height: 70px;
  transition: border-color .15s;
  width: 100%;
}
.composer-input:focus{
  outline: none;
  border-color: rgba(59,158,255,.4);
  background: rgba(59,158,255,.03);
}
.composer-actions{
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  padding-left: 50px;
}
.char-count{ font-size: 11px; color: var(--muted); }
.char-count.warn{ color: #ffb060; }
.char-count.over{ color: #ff7a7a; }

/* Posts */
.post{
  background: linear-gradient(160deg, rgba(13,18,25,.92), rgba(10,15,21,.96));
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 16px;
  margin-bottom: 14px;
  transition: border-color .15s;
}
.post.pinned{
  border-color: rgba(255,180,50,.3);
  background: linear-gradient(160deg, rgba(255,180,50,.04), rgba(10,15,21,.96));
}
.post-head{
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}
.post-meta{ flex: 1; min-width: 0; }
.post-author{
  font-weight: 700;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.post-grade{
  font-size: 10px;
  color: var(--muted);
  border: 1px solid var(--line2);
  border-radius: 999px;
  padding: 2px 8px;
  font-weight: 500;
}
.post-time{ font-size: 11px; color: var(--muted); margin-top: 2px; }
.post-pin-badge{
  font-size: 11px;
  color: #ffb060;
  background: rgba(255,180,50,.1);
  border: 1px solid rgba(255,180,50,.25);
  border-radius: 999px;
  padding: 2px 9px;
}
.post-body{
  font-size: 14px;
  line-height: 1.65;
  white-space: pre-wrap;
  word-break: break-word;
  margin-bottom: 12px;
}
.post-body a{ color: var(--accent); }

/* Actions bar */
.post-actions{
  display: flex;
  align-items: center;
  gap: 6px;
  padding-top: 10px;
  border-top: 1px solid rgba(255,255,255,.04);
  flex-wrap: wrap;
}
.action-btn{
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  font-family: var(--font-body);
  cursor: pointer;
  transition: all .12s;
}
.action-btn:hover{ background: rgba(255,255,255,.05); color: var(--text); border-color: var(--line); }
.action-btn.heart.active{ color: #ff6b8a; border-color: rgba(255,80,120,.3); background: rgba(255,80,120,.08); }
.action-btn.heart:hover{ color: #ff6b8a; }
.post-menu{ margin-left: auto; position: relative; }
.post-menu-btn{ background:none; border:none; color:var(--muted); cursor:pointer; padding:4px 8px; border-radius:7px; font-size:16px; }
.post-menu-btn:hover{ background:rgba(255,255,255,.06); color:var(--text); }
.post-menu-dropdown{
  display: none;
  position: absolute;
  right: 0; top: 100%;
  background: #0d1219;
  border: 1px solid var(--line2);
  border-radius: 10px;
  padding: 4px;
  min-width: 140px;
  z-index: 100;
  box-shadow: 0 8px 24px rgba(0,0,0,.4);
}
.post-menu-dropdown.open{ display: block; }
.menu-item{
  display: flex; align-items: center; gap: 8px;
  padding: 8px 10px;
  border-radius: 7px;
  font-size: 13px;
  cursor: pointer;
  color: var(--text);
  border: none; background: none; width: 100%; text-align: left;
  font-family: var(--font-body);
}
.menu-item:hover{ background: rgba(255,255,255,.06); }
.menu-item.danger{ color: #ff7a7a; }
.menu-item.danger:hover{ background: rgba(255,79,79,.1); }

/* Edit mode */
.post-edit-area{
  display: none;
  margin-bottom: 12px;
}
.post-edit-area textarea{
  width: 100%;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--accent);
  border-radius: 10px;
  padding: 10px 12px;
  color: var(--text);
  font-family: var(--font-body);
  font-size: 14px;
  resize: none;
  min-height: 80px;
}
.post-edit-actions{ display:flex; gap:8px; justify-content:flex-end; margin-top:8px; }

/* Comments */
.comments-section{
  display: none;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(255,255,255,.04);
}
.comments-section.open{ display: block; }
.comment{
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}
.comment-bubble{
  flex: 1;
  background: rgba(255,255,255,.03);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 8px 12px;
}
.comment-author{ font-size: 12px; font-weight: 700; margin-bottom: 3px; }
.comment-time{ font-size: 10px; color: var(--muted); float: right; }
.comment-body{ font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break:break-word; }
.comment-del{
  background: none; border: none; color: var(--muted);
  cursor: pointer; font-size: 12px; padding: 2px 6px;
  border-radius: 6px; opacity: 0; transition: opacity .15s;
}
.comment:hover .comment-del{ opacity: 1; }
.comment-del:hover{ color: #ff7a7a; background: rgba(255,79,79,.1); }
.comment-composer{
  display: flex;
  gap: 8px;
  align-items: flex-end;
  margin-top: 10px;
}
.comment-input{
  flex: 1;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--line2);
  border-radius: 10px;
  padding: 8px 12px;
  color: var(--text);
  font-family: var(--font-body);
  font-size: 13px;
  resize: none;
  min-height: 38px;
  max-height: 120px;
}
.comment-input:focus{ outline:none; border-color:rgba(59,158,255,.4); }

/* Load more */
.load-more-btn{
  display: block;
  width: 100%;
  padding: 12px;
  border-radius: 12px;
  border: 1px dashed var(--line2);
  background: transparent;
  color: var(--muted);
  font-family: var(--font-body);
  font-size: 13px;
  cursor: pointer;
  margin-top: 4px;
  transition: all .15s;
}
.load-more-btn:hover{ border-color:var(--accent); color:var(--accent); background:rgba(59,158,255,.04); }

/* Empty state */
.feed-empty{
  text-align: center;
  padding: 48px 0;
  color: var(--muted);
}
.feed-empty .icon{ font-size: 40px; margin-bottom: 12px; }
</style>

<div class="feed-wrap">

  <!-- En-tête -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div>
      <h1 style="font-family:var(--font-head);font-size:20px;font-weight:800;margin:0 0 3px;">💬 Notes internes</h1>
      <div style="font-size:13px;color:var(--muted);">Partagez des infos, notes de service ou messages à l'équipe.</div>
    </div>
  </div>

  <!-- Composer -->
  <div class="composer">
    <div class="composer-row">
      <div class="avatar"><?= h($initials ?: '?') ?></div>
      <textarea class="composer-input" id="newPostBody" placeholder="Quoi de neuf ? Écrivez une note, une info opérationnelle..." maxlength="2000"></textarea>
    </div>
    <div class="composer-actions">
      <span class="char-count" id="charCount">0 / 2000</span>
      <button class="btn success" id="btnPost" style="padding:8px 18px;">Publier</button>
    </div>
  </div>

  <!-- Feed -->
  <div id="feed"></div>
  <button class="load-more-btn" id="btnLoadMore" style="display:none;">Charger plus…</button>

</div>

<script>
(function(){
  const ME      = <?= json_encode($uid) ?>;
  const IS_ADMIN= <?= json_encode($isAdmin) ?>;
  const UNAME   = <?= json_encode($username, JSON_UNESCAPED_UNICODE) ?>;
  const INITIALS= <?= json_encode($initials ?: '?', JSON_UNESCAPED_UNICODE) ?>;
  const CSRF    = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;

  const feedEl  = document.getElementById('feed');
  const btnMore = document.getElementById('btnLoadMore');
  let lastId    = 0;
  let loading   = false;

  async function api(payload) {
    const r = await fetch('/api/feed.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, csrf: CSRF })
    });
    const d = await r.json().catch(() => null);
    if (!r.ok) throw new Error(d?.error || 'Erreur API');
    return d;
  }

  function timeAgo(dateStr) {
    const d    = new Date(dateStr);
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60)    return 'À l\'instant';
    if (diff < 3600)  return Math.floor(diff/60) + ' min';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return d.toLocaleDateString('fr-FR', { day:'2-digit', month:'short' });
  }

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function getInitials(name) {
    const parts = String(name||'').split(/[\s|]+/).filter(Boolean);
    return parts.slice(0,2).map(p=>p[0]?.toUpperCase()||'').join('');
  }

  function renderPost(p) {
    const el = document.createElement('div');
    el.className = 'post' + (p.pinned ? ' pinned' : '');
    el.dataset.postId = p.id;

    const ini = getInitials(p.username);
    const canEdit = p.can_edit;

    el.innerHTML = `
      <div class="post-head">
        <div class="avatar">${esc(ini||'?')}</div>
        <div class="post-meta">
          <div class="post-author">
            ${esc(p.username)}
            ${p.grade ? `<span class="post-grade">${esc(p.grade)}</span>` : ''}
            ${p.pinned ? '<span class="post-pin-badge">📌 Épinglé</span>' : ''}
          </div>
          <div class="post-time">${timeAgo(p.created_at)}${p.updated_at ? ' · modifié' : ''}</div>
        </div>
        ${canEdit ? `
        <div class="post-menu">
          <button class="post-menu-btn" data-menu="${p.id}">⋯</button>
          <div class="post-menu-dropdown" id="menu-${p.id}">
            <button class="menu-item" data-action="edit" data-id="${p.id}">✏️ Modifier</button>
            ${IS_ADMIN ? `<button class="menu-item" data-action="pin" data-id="${p.id}">${p.pinned?'📌 Dépingler':'📌 Épingler'}</button>` : ''}
            <button class="menu-item danger" data-action="delete" data-id="${p.id}">🗑 Supprimer</button>
          </div>
        </div>` : (IS_ADMIN ? `
        <div class="post-menu">
          <button class="post-menu-btn" data-menu="${p.id}">⋯</button>
          <div class="post-menu-dropdown" id="menu-${p.id}">
            <button class="menu-item" data-action="pin" data-id="${p.id}">${p.pinned?'📌 Dépingler':'📌 Épingler'}</button>
            <button class="menu-item danger" data-action="delete" data-id="${p.id}">🗑 Supprimer</button>
          </div>
        </div>` : '')}
      </div>

      <div class="post-body" id="body-${p.id}">${esc(p.body)}</div>

      <div class="post-edit-area" id="edit-${p.id}">
        <textarea id="edit-ta-${p.id}">${esc(p.body)}</textarea>
        <div class="post-edit-actions">
          <button class="btn secondary" style="font-size:12px;" data-cancel-edit="${p.id}">Annuler</button>
          <button class="btn success" style="font-size:12px;" data-save-edit="${p.id}">Enregistrer</button>
        </div>
      </div>

      <div class="post-actions">
        <button class="action-btn heart ${p.my_reaction?'active':''}" data-heart="${p.id}">
          ❤️ <span id="hearts-${p.id}">${p.hearts}</span>
        </button>
        <button class="action-btn" data-toggle-comments="${p.id}">
          💬 <span id="ccount-${p.id}">${p.comment_count}</span>
        </button>
      </div>

      <div class="comments-section" id="comments-${p.id}">
        <div id="comments-list-${p.id}">
          ${(p.comments||[]).map(c => renderCommentHTML(c)).join('')}
        </div>
        <div class="comment-composer">
          <div class="avatar sm">${esc(INITIALS)}</div>
          <textarea class="comment-input" id="ci-${p.id}" placeholder="Ajouter un commentaire…" rows="1"></textarea>
          <button class="btn" style="padding:7px 12px;font-size:12px;" data-send-comment="${p.id}">↩</button>
        </div>
      </div>
    `;

    const menuBtn = el.querySelector(`[data-menu="${p.id}"]`);
    const dropdown = el.querySelector(`#menu-${p.id}`);
    menuBtn?.addEventListener('click', e => {
      e.stopPropagation();
      document.querySelectorAll('.post-menu-dropdown.open').forEach(d => { if(d!==dropdown) d.classList.remove('open'); });
      dropdown?.classList.toggle('open');
    });

    el.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', async () => {
        dropdown?.classList.remove('open');
        const act = btn.dataset.action;
        const id  = parseInt(btn.dataset.id);
        if (act === 'edit')   openEdit(el, id, p.body);
        if (act === 'delete') await deletePost(el, id);
        if (act === 'pin')    await togglePin(el, id, p.pinned);
      });
    });

    el.querySelector(`[data-cancel-edit="${p.id}"]`)?.addEventListener('click', () => closeEdit(el, p.id));

    el.querySelector(`[data-save-edit="${p.id}"]`)?.addEventListener('click', async () => {
      const ta = el.querySelector(`#edit-ta-${p.id}`);
      const newBody = ta?.value.trim() || '';
      if (!newBody) return;
      try {
        await api({ action: 'edit_post', post_id: p.id, body: newBody });
        el.querySelector(`#body-${p.id}`).textContent = newBody;
        p.body = newBody;
        closeEdit(el, p.id);
        el.querySelector('.post-time').textContent += ' · modifié';
      } catch(err) { alert(err.message); }
    });

    el.querySelector(`[data-heart="${p.id}"]`)?.addEventListener('click', async function() {
      try {
        const d = await api({ action: 'toggle_reaction', post_id: p.id });
        this.classList.toggle('active', d.active);
        el.querySelector(`#hearts-${p.id}`).textContent = d.count;
      } catch(err) { alert(err.message); }
    });

    el.querySelector(`[data-toggle-comments="${p.id}"]`)?.addEventListener('click', () => {
      el.querySelector(`#comments-${p.id}`).classList.toggle('open');
    });

    el.querySelector(`[data-send-comment="${p.id}"]`)?.addEventListener('click', async () => {
      await sendComment(el, p.id);
    });

    el.querySelector(`#ci-${p.id}`)?.addEventListener('keydown', async e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        await sendComment(el, p.id);
      }
    });

    el.querySelectorAll('[data-del-comment]').forEach(btn => {
      btn.addEventListener('click', () => deleteComment(el, p.id, parseInt(btn.dataset.delComment)));
    });

    return el;
  }

  function renderCommentHTML(c) {
    const canDel = (c.user_id == ME) || IS_ADMIN;
    return `
      <div class="comment" id="comment-${c.id}">
        <div class="avatar sm">${esc(getInitials(c.username)||'?')}</div>
        <div class="comment-bubble">
          <div class="comment-author">
            ${esc(c.username)}
            <span class="comment-time">${timeAgo(c.created_at)}</span>
          </div>
          <div class="comment-body">${esc(c.body)}</div>
        </div>
        ${canDel ? `<button class="comment-del" data-del-comment="${c.id}" title="Supprimer">✕</button>` : ''}
      </div>
    `;
  }

  function openEdit(el, id, currentBody) {
    el.querySelector(`#body-${id}`).style.display = 'none';
    const editArea = el.querySelector(`#edit-${id}`);
    editArea.style.display = 'block';
    const ta = el.querySelector(`#edit-ta-${id}`);
    ta.value = currentBody;
    ta.focus();
  }
  function closeEdit(el, id) {
    el.querySelector(`#body-${id}`).style.display = '';
    el.querySelector(`#edit-${id}`).style.display = 'none';
  }

  async function deletePost(el, id) {
    if (!confirm('Supprimer ce message ?')) return;
    try {
      await api({ action: 'delete_post', post_id: id });
      el.style.opacity = '0';
      el.style.transition = 'opacity .3s';
      setTimeout(() => el.remove(), 300);
    } catch(err) { alert(err.message); }
  }

  async function togglePin(el, id, wasPinned) {
    try {
      await api({ action: 'toggle_pin', post_id: id });
      feedEl.innerHTML = '';
      lastId = 0;
      await loadPosts();
    } catch(err) { alert(err.message); }
  }

  async function sendComment(el, postId) {
    const inp = el.querySelector(`#ci-${postId}`);
    const body = inp?.value.trim() || '';
    if (!body) return;

    try {
      const d = await api({ action: 'add_comment', post_id: postId, body });
      const list = el.querySelector(`#comments-list-${postId}`);
      const div  = document.createElement('div');
      div.innerHTML = renderCommentHTML(d.comment);
      const commentEl = div.firstElementChild;
      commentEl.querySelector('[data-del-comment]')?.addEventListener('click', () => {
        deleteComment(el, postId, d.comment.id);
      });
      list.appendChild(commentEl);
      inp.value = '';

      const cnt = el.querySelector(`#ccount-${postId}`);
      if (cnt) cnt.textContent = parseInt(cnt.textContent||'0') + 1;

      el.querySelector(`#comments-${postId}`).classList.add('open');
    } catch(err) { alert(err.message); }
  }

  async function deleteComment(el, postId, commentId) {
    try {
      await api({ action: 'delete_comment', comment_id: commentId });
      document.getElementById(`comment-${commentId}`)?.remove();
      const cnt = el.querySelector(`#ccount-${postId}`);
      if (cnt) cnt.textContent = Math.max(0, parseInt(cnt.textContent||'0') - 1);
    } catch(err) { alert(err.message); }
  }

  async function loadPosts(append = false) {
    if (loading) return;
    loading = true;
    btnMore.style.display = 'none';

    try {
      const url = '/api/feed.php' + (lastId ? `?before=${lastId}` : '');
      const r   = await fetch(url, { credentials: 'same-origin' });
      const d   = await r.json();
      if (!d.ok) throw new Error(d.error || 'Erreur');

      if (!append) feedEl.innerHTML = '';

      if (!d.posts.length && !append) {
        feedEl.innerHTML = '<div class="feed-empty"><div class="icon">💬</div><div>Aucune note pour l\'instant.<br>Soyez le premier à écrire quelque chose !</div></div>';
        return;
      }

      for (const p of d.posts) {
        feedEl.appendChild(renderPost(p));
        lastId = p.id;
      }

      if (d.has_more) btnMore.style.display = 'block';

    } catch(err) {
      feedEl.innerHTML = `<div class="muted" style="padding:20px;text-align:center;">Impossible de charger le feed : ${esc(err.message)}</div>`;
    } finally {
      loading = false;
    }
  }

  const bodyInput = document.getElementById('newPostBody');
  const charCount = document.getElementById('charCount');
  const btnPost   = document.getElementById('btnPost');

  bodyInput.addEventListener('input', () => {
    const len = bodyInput.value.length;
    charCount.textContent = len + ' / 2000';
    charCount.className = 'char-count' + (len > 1800 ? ' warn' : '') + (len > 2000 ? ' over' : '');
  });

  bodyInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      btnPost.click();
    }
  });

  btnPost.addEventListener('click', async () => {
    const body = bodyInput.value.trim();
    if (!body) return;
    if (body.length > 2000) return;

    btnPost.disabled = true;
    btnPost.textContent = 'Publication…';
    try {
      const d = await api({ action: 'create_post', body });
      bodyInput.value = '';
      charCount.textContent = '0 / 2000';

      const emptyState = feedEl.querySelector('.feed-empty');
      if (emptyState) feedEl.innerHTML = '';

      const newEl = renderPost(d.post);
      newEl.style.opacity = '0';
      newEl.style.transform = 'translateY(-10px)';
      newEl.style.transition = 'opacity .3s, transform .3s';
      feedEl.insertBefore(newEl, feedEl.firstChild);
      requestAnimationFrame(() => {
        newEl.style.opacity = '1';
        newEl.style.transform = 'translateY(0)';
      });
    } catch(err) {
      alert(err.message);
    } finally {
      btnPost.disabled = false;
      btnPost.textContent = 'Publier';
    }
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.post-menu-dropdown.open').forEach(d => d.classList.remove('open'));
  });

  btnMore.addEventListener('click', () => loadPosts(true));

  loadPosts();
})();
</script>

<?php samr_layout_end(); ?>
