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

  function playNotifSound() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15);
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.15);
    } catch(e) {}
  }

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
        let hasIncoming = false;
        messages.forEach(msg => {
          if (msg.id > lastChatMsgId) lastChatMsgId = msg.id;
          appendMessage(msg, chatContainer);
          if (!msg.is_mine) {
            hasIncoming = true;
            if (typeof showToast === 'function') {
              showToast(`💬 ${msg.sender_name}: ${msg.message || 'Attached a file'}`, 'info');
            }
          }
        });
        chatContainer.scrollTop = chatContainer.scrollHeight;

        if (hasIncoming) playNotifSound();

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
  // ─── Render a Message ────────────────────────────────────
  function appendMessage(msg, container) {
    if (!msg || !msg.id) return;
    // Avoid duplicates by checking both id and data-msg-id
    if (document.getElementById(`msg-${msg.id}`) || document.querySelector(`[data-msg-id="${msg.id}"]`)) return;

    const isMine = msg.is_mine || msg.is_self;
    const div = document.createElement('div');
    div.className = `chat-msg ${isMine ? 'mine' : ''}`;
    div.id = `msg-${msg.id}`;
    div.dataset.msgId = msg.id;

    const initials = (msg.sender_name || 'U').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase();

    let attachHtml = '';
    if (msg.file_path) {
      attachHtml = `
        <div class="chat-attachment d-flex align-items-center gap-2 mt-1">
          <a href="raw_file.php?chat=1&file=${encodeURIComponent(msg.file_path)}" download="${escHtml(msg.file_name || msg.file_path)}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem;">
            <i class="bi bi-download me-1"></i>${escHtml(msg.file_name || 'Download')}
          </a>
          <a href="raw_file.php?chat=1&file=${encodeURIComponent(msg.file_path)}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem;">
            <i class="bi bi-eye me-1"></i>View
          </a>
        </div>`;
    }

    div.innerHTML = `
      <div class="chat-avatar">${initials}</div>
      <div class="chat-bubble">
        <div class="chat-meta">
          <span class="fw-semibold me-2">${escHtml(msg.sender_name)}</span>
          <span>${escHtml(msg.time_ago || 'just now')}</span>
        </div>
        ${msg.message ? `<div class="chat-text">${escHtml(msg.message).replace(/\n/g,'<br>')}</div>` : ''}
        ${attachHtml}
      </div>
    `;
    container.appendChild(div);
  }

  // ─── Chat Send (with deduplication lock) ────────────────
  let isSendingChat = false;
  window.sendChatMessage = function (form) {
    if (!form) form = document.getElementById('chat-form');
    if (!form || isSendingChat) return false;

    const msgInput = form.querySelector('textarea, input[name="message"]');
    const fileInput = form.querySelector('input[type="file"]');
    const preview = document.getElementById('chat-file-preview');

    const msgVal = msgInput ? msgInput.value.trim() : '';
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    if (!msgVal && !hasFile) return false;

    isSendingChat = true;
    const data = new FormData(form);
    const container = document.getElementById('chat-messages-box') || document.getElementById('chat-messages-container');

    // Immediately clear inputs before fetch so user cannot double-submit
    if (msgInput) msgInput.value = '';
    if (fileInput) fileInput.value = '';
    if (preview) preview.innerHTML = '';

    fetch('api/chat.php?action=send', { method: 'POST', body: data })
      .then(r => r.json())
      .then(res => {
        isSendingChat = false;
        if (res.success && res.id) {
          lastChatMsgId = Math.max(lastChatMsgId, res.id || 0);
          if (res.message && container) {
            appendMessage({ ...res.message, is_mine: true }, container);
            container.scrollTop = container.scrollHeight;
          }
        } else if (res.message) {
          showToast(res.message, 'danger');
        }
      })
      .catch(() => {
        isSendingChat = false;
        showToast('Failed to send message.', 'danger');
      });
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

    // Chat form enter-to-send (Only attach if not handled on page)
    const chatInput = document.getElementById('chat-input');
    const chatForm = document.getElementById('chat-form');
    if (chatInput && chatForm && !chatForm.dataset.boundRealtime) {
      chatForm.dataset.boundRealtime = '1';
      chatInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendChatMessage(chatForm);
        }
      });
    }
  });
})();
