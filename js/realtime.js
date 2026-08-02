// ============================================================
// realtime.js - Real-Time Polling Engine
// CollabSpace Collaboration Workspace
// ============================================================

(function () {
  'use strict';

  const POLL_INTERVAL_MS = 8000;  // 8 second polling
  const STATUS_PING_MS   = 15000; // 15 second presence ping

  // ─── Notification Polling ────────────────────────────────
  function pollNotifications() {
    fetch('api/notifications.php?action=count')
      .then(r => r.json())
      .then(data => {
        const badge = document.getElementById('notif-count');
        if (!badge) return;
        const count = parseInt(data.count) || 0;
        if (count > 0) {
          badge.textContent = count > 99 ? '99+' : count;
          badge.classList.remove('d-none');
        } else {
          badge.classList.add('d-none');
        }
      })
      .catch(() => {});
  }

  // ─── Online Status Ping ─────────────────────────────────
  function pingStatus() {
    fetch('api/events.php?action=ping').catch(() => {});
  }

  // ─── Chat Auto-Refresh ───────────────────────────────────
  let lastChatMsgId = 0;

  function pollChat() {
    const chatContainer = document.getElementById('chat-messages-box');
    if (!chatContainer) return;

    const projectId = chatContainer.dataset.projectId || '';
    const roomType  = chatContainer.dataset.roomType || 'project';
    const receiverId = chatContainer.dataset.receiverId || '';

    fetch(`api/chat.php?action=fetch&project_id=${projectId}&room_type=${roomType}&receiver_id=${receiverId}&after_id=${lastChatMsgId}`)
      .then(r => r.json())
      .then(messages => {
        if (!Array.isArray(messages) || messages.length === 0) return;
        messages.forEach(msg => {
          if (msg.id > lastChatMsgId) lastChatMsgId = msg.id;
          appendMessage(msg, chatContainer);
        });
        chatContainer.scrollTop = chatContainer.scrollHeight;

        // Update unread count in sidebar
        const chatBadge = document.getElementById('sidebar-chat-badge');
        if (chatBadge && messages.some(m => !m.is_mine)) {
          chatBadge.style.display = 'inline-block';
          const cur = parseInt(chatBadge.textContent) || 0;
          chatBadge.textContent = cur + messages.filter(m => !m.is_mine).length;
        }
      })
      .catch(() => {});
  }

  // ─── Render a Message ────────────────────────────────────
  function appendMessage(msg, container) {
    // Avoid duplicates
    if (document.querySelector(`[data-msg-id="${msg.id}"]`)) return;

    const div = document.createElement('div');
    const isMine = msg.is_mine || msg.is_self;
    div.className = `d-flex gap-2 ${isMine ? 'flex-row-reverse' : ''}`;
    div.dataset.msgId = msg.id;

    const avatarColor = stringToColor(msg.sender_name || 'User');
    const initials = (msg.sender_name || 'U').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase();

    const avatarHtml = msg.sender_avatar
      ? `<img src="uploads/${escHtml(msg.sender_avatar)}" class="rounded-circle flex-shrink-0" style="width:34px;height:34px;object-fit:cover;" alt="">`
      : `<div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold" style="width:34px;height:34px;background:${avatarColor};font-size:.75rem;">${initials}</div>`;

    let contentHtml = '';
    if (msg.message) {
      contentHtml = `<div class="msg-bubble-inner">${escHtml(msg.message).replace(/\n/g,'<br>')}</div>`;
    }
    if (msg.file_path) {
      contentHtml += `<div class="mt-1"><a href="uploads/${escHtml(msg.file_path)}" class="btn btn-sm btn-outline-secondary" download><i class="bi bi-paperclip me-1"></i>${escHtml(msg.file_name || 'File')}</a></div>`;
    }

    div.innerHTML = `
      ${!isMine ? avatarHtml : ''}
      <div class="msg-bubble ${isMine ? 'msg-self' : 'msg-other'}">
        ${!isMine ? `<div class="x-small text-muted mb-1">${escHtml(msg.sender_name)}</div>` : ''}
        ${contentHtml}
        <div class="msg-time">${msg.time_ago || ''}</div>
      </div>
      ${isMine ? avatarHtml : ''}
    `;
    container.appendChild(div);
  }

  // ─── Chat Send ───────────────────────────────────────────
  window.sendChatMessage = function (form) {
    const data = new FormData(form);
    const container = document.getElementById('chat-messages-box');

    fetch('api/chat.php?action=send', { method: 'POST', body: data })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const input = form.querySelector('textarea, input[name="message"]');
          if (input) input.value = '';
          lastChatMsgId = Math.max(lastChatMsgId, res.id || 0);
          if (res.message && container) {
            appendMessage({ ...res.message, is_mine: true }, container);
            container.scrollTop = container.scrollHeight;
          }
        }
      })
      .catch(() => {});
    return false;
  };

  // ─── Online User Status Refresh ──────────────────────────
  function refreshOnlineUsers() {
    const container = document.getElementById('online-users-list');
    if (!container) return;
    fetch('api/events.php?action=online_users')
      .then(r => r.json())
      .then(users => {
        if (!Array.isArray(users)) return;
        container.innerHTML = users.map(u => `
          <div class="d-flex align-items-center gap-2 py-1">
            <span class="online-indicator ${u.status === 'online' ? '' : 'offline-indicator'}"></span>
            <span class="small">${escHtml(u.name)}</span>
          </div>
        `).join('');
      }).catch(() => {});
  }

  // ─── Task Live Status ────────────────────────────────────
  window.updateTaskStatus = function (taskId, newStatus, callback) {
    const form = new FormData();
    form.append('task_id', taskId);
    form.append('status', newStatus);

    fetch('api/tasks.php?action=update_status', { method: 'POST', body: form })
      .then(r => r.json())
      .then(res => {
        if (callback) callback(res.success, res);
        if (res.success) showToast('Task status updated!', 'success');
        else showToast('Failed to update task.', 'danger');
      })
      .catch(() => showToast('Network error.', 'danger'));
  };

  // ─── Toast Notifications ─────────────────────────────────
  window.showToast = function (message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', info: 'bi-info-circle-fill', warning: 'bi-exclamation-triangle-fill' };
    toast.className = `alert alert-${type} d-flex align-items-center gap-2 shadow border-0 py-2 px-3`;
    toast.style.cssText = 'min-width:260px;border-radius:10px;font-size:.85rem;animation:fadeInUp .3s ease;';
    toast.innerHTML = `<i class="bi ${icons[type] || 'bi-info-circle-fill'}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.transition = 'opacity .3s'; toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3500);
  };

  // ─── Helpers ─────────────────────────────────────────────
  function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function stringToColor(str) {
    const colors = ['#4f46e5','#7c3aed','#0891b2','#059669','#d97706','#dc2626','#db2777'];
    let hash = 0; for (const c of str) hash = c.charCodeAt(0) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
  }

  // ─── Initialize ──────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    // Initialize scrollbar
    if (typeof OverlayScrollbars !== 'undefined') {
      document.querySelectorAll('[data-os-host]').forEach(el => {
        OverlayScrollbars(el, { scrollbars: { autoHide: 'scroll' } });
      });
    }

    // Scroll chat to bottom on load
    const chatBox = document.getElementById('chat-messages-box');
    if (chatBox) {
      chatBox.scrollTop = chatBox.scrollHeight;
      lastChatMsgId = parseInt(chatBox.dataset.lastId || '0');
      setInterval(pollChat, POLL_INTERVAL_MS);
    }

    // Start polling loops
    pollNotifications();
    setInterval(pollNotifications, POLL_INTERVAL_MS);

    refreshOnlineUsers();
    setInterval(refreshOnlineUsers, STATUS_PING_MS);

    // Presence ping
    pingStatus();
    setInterval(pingStatus, STATUS_PING_MS);

    // Chat form enter-to-send
    const chatInput = document.getElementById('chat-input');
    const chatForm = document.getElementById('chat-form');
    if (chatInput && chatForm) {
      chatInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          chatForm.dispatchEvent(new Event('submit'));
        }
      });
      chatForm.addEventListener('submit', e => {
        e.preventDefault();
        sendChatMessage(chatForm);
      });
    }
  });
})();
