<?php
// ─── Team Chat ────────────────────────────────────────────────
$page_title = 'Team Chat';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Fetch projects as chat rooms
if (is_admin()) {
    $projects = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, name FROM projects WHERE created_by=? OR manager_id=? OR id IN (SELECT project_id FROM project_members WHERE user_id=?) ORDER BY name");
    $stmt->execute([$uid, $uid, $uid]);
    $projects = $stmt->fetchAll();
}

// Direct message users
$dm_users = $db->query("SELECT id, name, status FROM users WHERE id != $uid AND is_active=1 ORDER BY name")->fetchAll();

// Active room
$room_type   = $_GET['type'] ?? 'project';
$room_id     = (int)($_GET['id'] ?? ($projects[0]['id'] ?? 0));
$room_name   = 'General';

if ($room_type === 'project' && $room_id) {
    $r = $db->prepare("SELECT name FROM projects WHERE id=?"); $r->execute([$room_id]);
    $room_name = $r->fetchColumn() ?: 'Project Chat';
} elseif ($room_type === 'direct' && $room_id) {
    $r = $db->prepare("SELECT name FROM users WHERE id=?"); $r->execute([$room_id]);
    $room_name = $r->fetchColumn() ?: 'Direct Message';
}

// Fetch messages
if ($room_type === 'direct' && $room_id) {
    $stmt = $db->prepare("
        SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar,
               (c.sender_id=?) AS is_mine
        FROM chats c JOIN users u ON u.id=c.sender_id
        WHERE c.room_type='direct'
          AND ((c.sender_id=? AND c.receiver_id=?) OR (c.sender_id=? AND c.receiver_id=?))
        ORDER BY c.created_at ASC LIMIT 100
    ");
    $stmt->execute([$uid, $uid, $room_id, $room_id, $uid]);
} else {
    $stmt = $db->prepare("
        SELECT c.*, u.name AS sender_name, u.avatar AS sender_avatar,
               (c.sender_id=?) AS is_mine
        FROM chats c JOIN users u ON u.id=c.sender_id
        WHERE c.project_id=? AND c.room_type='project'
        ORDER BY c.created_at ASC LIMIT 100
    ");
    $stmt->execute([$uid, $room_id]);
}
$messages = $stmt->fetchAll();
$last_id = empty($messages) ? 0 : max(array_column($messages,'id'));

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

  <div class="app-content" style="padding:16px!important;">
    <div class="chat-wrapper">

      <!-- Sidebar: Rooms -->
      <div class="chat-rooms">
        <div class="p-3 border-bottom">
          <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Project Rooms</div>
        </div>
        <div class="overflow-auto flex-grow-1 p-2">
          <?php if (empty($projects)): ?>
          <div class="text-center py-3 x-small text-muted">No projects yet.</div>
          <?php endif; ?>
          <?php foreach ($projects as $p): ?>
          <a href="chat.php?type=project&id=<?= $p['id'] ?>" class="chat-room-item text-decoration-none text-body <?= $room_type==='project'&&$room_id==$p['id']?'active':'' ?>" id="room-project-<?= $p['id'] ?>">
            <div class="rounded-2 d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:36px;height:36px;font-size:.72rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);">
              <?= strtoupper(substr($p['name'],0,2)) ?>
            </div>
            <div class="overflow-hidden">
              <div class="fw-semibold small text-truncate"><?= htmlspecialchars($p['name']) ?></div>
              <div class="x-small text-muted">Project room</div>
            </div>
          </a>
          <?php endforeach; ?>

          <div class="px-2 py-2 mt-2 d-flex align-items-center justify-content-between">
            <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Direct Messages</div>
            <button class="btn btn-link btn-sm p-0 text-primary" data-bs-toggle="modal" data-bs-target="#newDmModal" title="New Direct Message"><i class="bi bi-plus-circle-fill fs-6"></i></button>
          </div>
          <?php foreach ($dm_users as $u): ?>
          <a href="chat.php?type=direct&id=<?= $u['id'] ?>" class="chat-room-item text-decoration-none text-body <?= $room_type==='direct'&&$room_id==$u['id']?'active':'' ?>" id="room-dm-<?= $u['id'] ?>">
            <div class="position-relative">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:36px;height:36px;font-size:.72rem;background:#7c3aed;">
                <?= strtoupper(substr($u['name'],0,2)) ?>
              </div>
              <span class="position-absolute bottom-0 end-0 online-indicator <?= $u['status']==='online'?'':'offline-indicator' ?>"></span>
            </div>
            <div class="overflow-hidden">
              <div class="fw-semibold small text-truncate"><?= htmlspecialchars($u['name']) ?></div>
              <div class="x-small <?= $u['status']==='online'?'text-success':'text-muted' ?>"><?= $u['status']==='online'?'Online':'Offline' ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Main Chat Area -->
      <div class="chat-main">
        <!-- Chat Header -->
        <div class="chat-header">
          <?php if ($room_type==='project'): ?>
          <div class="rounded-2 d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:38px;height:38px;font-size:.75rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);">
            <?= strtoupper(substr($room_name,0,2)) ?>
          </div>
          <?php else: ?>
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:38px;height:38px;font-size:.75rem;background:#7c3aed;">
            <?= strtoupper(substr($room_name,0,2)) ?>
          </div>
          <?php endif; ?>
          <div>
            <div class="fw-bold small"><?= htmlspecialchars($room_name) ?></div>
            <div class="x-small text-muted"><?= $room_type==='project'?'Project Room':'Direct Message' ?></div>
          </div>
          <button class="btn btn-outline-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#newDmModal">
            <i class="bi bi-plus-lg me-1"></i>New DM
          </button>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chat-messages-container">
          <?php if (empty($messages)): ?>
          <div class="text-center py-5 text-muted" id="chat-empty-state">
            <i class="bi bi-chat-heart-fill fs-1 text-primary opacity-50 d-block mb-3"></i>
            <h6 class="fw-bold mb-1">No messages yet</h6>
            <p class="small text-muted mb-0">Start the conversation by sending a message below!</p>
          </div>
          <?php else: ?>
          <?php foreach ($messages as $m): ?>
          <div class="chat-msg <?= $m['is_mine']?'mine':'' ?>" id="msg-<?= $m['id'] ?>">
            <div class="chat-avatar"><?= strtoupper(substr($m['sender_name'],0,2)) ?></div>
            <div class="chat-bubble">
              <div class="chat-meta">
                <span class="fw-semibold me-2"><?= htmlspecialchars($m['sender_name']) ?></span>
                <span><?= time_ago($m['created_at']) ?></span>
              </div>
              <?php if ($m['message']): ?>
              <div class="chat-text"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
              <?php endif; ?>
              <?php if ($m['file_path']): ?>
              <a href="uploads/<?= htmlspecialchars($m['file_path']) ?>" class="chat-attachment" target="_blank">
                <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($m['file_name'] ?? 'File') ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-bar">
          <form id="chat-form" onsubmit="return sendChatMessage(this)" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="<?= $room_type==='project'?$room_id:'' ?>">
            <input type="hidden" name="room_type" value="<?= htmlspecialchars($room_type) ?>">
            <input type="hidden" name="receiver_id" value="<?= $room_type==='direct'?$room_id:'' ?>">
            <input type="hidden" id="last-msg-id" value="<?= $last_id ?>">

            <div class="d-flex align-items-center gap-2">
              <label class="btn btn-sm btn-outline-secondary mb-0 p-2 flex-shrink-0" title="Attach file">
                <i class="bi bi-paperclip fs-6"></i>
                <input type="file" name="chat_file" id="chat-file-input" class="d-none">
              </label>
              <input type="text" name="message" id="chat-msg-input"
                     class="form-control form-control-sm border-0 bg-body-secondary"
                     placeholder="Type a message… (Press Enter to send)" autocomplete="off">
              <button type="submit" class="btn btn-primary btn-sm px-3 flex-shrink-0" id="chat-send-btn">
                <i class="bi bi-send-fill me-1"></i> Send
              </button>
            </div>
            <div id="chat-file-preview" class="mt-1 x-small text-muted"></div>
          </form>
        </div>
      </div>

      <!-- Online Sidebar -->
      <div class="d-none d-xl-flex flex-column" style="width:200px;border-left:1px solid rgba(0,0,0,.07);padding:16px;gap:8px;flex-shrink:0;">
        <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Online Teammates</div>
        <div id="online-users-list">
          <?php foreach ($dm_users as $u): if ($u['status']==='online'): ?>
          <a href="chat.php?type=direct&id=<?= $u['id'] ?>" class="d-flex align-items-center gap-2 py-1 text-decoration-none text-body">
            <span class="online-indicator"></span>
            <span class="small text-truncate"><?= htmlspecialchars($u['name']) ?></span>
          </a>
          <?php endif; endforeach; ?>
          <div class="d-flex align-items-center gap-2 py-1">
            <span class="online-indicator"></span>
            <span class="small fw-semibold"><?= htmlspecialchars($user['name']) ?> (you)</span>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- New Direct Message Modal -->
<div class="modal fade" id="newDmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Start Direct Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small text-muted">Select Teammate to Chat With:</label>
          <div class="list-group">
            <?php foreach ($dm_users as $u): ?>
            <a href="chat.php?type=direct&id=<?= $u['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 border-0 rounded-3 mb-1 bg-body-secondary">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:.8rem;">
                  <?= strtoupper(substr($u['name'],0,2)) ?>
                </div>
                <div>
                  <div class="fw-semibold small"><?= htmlspecialchars($u['name']) ?></div>
                  <div class="x-small text-muted"><?= $u['status']==='online'?'<span class="text-success">● Online</span>':'Offline' ?></div>
                </div>
              </div>
              <span class="btn btn-sm btn-outline-primary rounded-pill px-3">Chat <i class="bi bi-chevron-right ms-1"></i></span>
            </a>
            <?php endforeach; ?>
            <?php if (empty($dm_users)): ?>
            <div class="text-center py-4 text-muted small">No other registered users found. Tell friends to sign up!</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<JS
<script>
let lastMsgId = {$last_id};
const roomType = "{$room_type}";
const roomId = {$room_id};
const chatContainer = document.getElementById('chat-messages-container');

function scrollToBottom() {
  if (chatContainer) {
    chatContainer.scrollTop = chatContainer.scrollHeight;
  }
}

scrollToBottom();

async function sendChatMessage(form) {
  const input = document.getElementById('chat-msg-input');
  const msgText = input ? input.value.trim() : '';
  const fileInput = document.getElementById('chat-file-input');
  
  if (!msgText && (!fileInput || !fileInput.files.length)) return false;

  const formData = new FormData(form);
  formData.append('room_type', roomType);
  if (roomType === 'direct') {
    formData.append('receiver_id', roomId);
  } else {
    formData.append('project_id', roomId);
  }

  if (input) input.value = '';
  const preview = document.getElementById('chat-file-preview');
  if (preview) preview.textContent = '';

  const emptyState = document.getElementById('chat-empty-state');
  if (emptyState) emptyState.remove();

  try {
    const res = await fetch('api/chat.php?action=send', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    
    if (data.success && data.message) {
      appendMessageUI(data.message);
      if (data.id && data.id > lastMsgId) {
        lastMsgId = data.id;
      }
    }
  } catch (err) {
    console.error('Failed to send message:', err);
  }

  if (fileInput) fileInput.value = '';
  return false;
}

function appendMessageUI(m) {
  const isMine = m.is_mine;
  const div = document.createElement('div');
  div.className = 'chat-msg ' + (isMine ? 'mine' : '');
  div.id = 'msg-' + m.id;
  
  let fileHtml = '';
  if (m.file_path) {
    fileHtml = `<a href="uploads/\${m.file_path}" class="chat-attachment" target="_blank">
      <i class="bi bi-paperclip me-1"></i>\${m.file_name || 'File'}
    </a>`;
  }
  
  div.innerHTML = `
    <div class="chat-avatar">\${(m.sender_name || 'U').substring(0,2).toUpperCase()}</div>
    <div class="chat-bubble">
      <div class="chat-meta">
        <span class="fw-semibold me-2">\${escapeHtml(m.sender_name || 'User')}</span>
        <span>\${m.time_ago || 'just now'}</span>
      </div>
      \${m.message ? `<div class="chat-text">\${escapeHtml(m.message).replace(/\\n/g, '<br>')}</div>` : ''}
      \${fileHtml}
    </div>
  `;
  
  if (chatContainer) {
    chatContainer.appendChild(div);
    scrollToBottom();
  }
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

async function pollNewMessages() {
  if (!roomId) return;
  try {
    let url = `api/chat.php?action=fetch&after_id=\${lastMsgId}&room_type=\${roomType}`;
    if (roomType === 'direct') {
      url += `&receiver_id=\${roomId}`;
    } else {
      url += `&project_id=\${roomId}`;
    }
    
    const res = await fetch(url);
    const messages = await res.json();
    
    if (Array.isArray(messages) && messages.length > 0) {
      const emptyState = document.getElementById('chat-empty-state');
      if (emptyState) emptyState.remove();

      messages.forEach(m => {
        if (!document.getElementById('msg-' + m.id)) {
          appendMessageUI(m);
          if (m.id > lastMsgId) lastMsgId = m.id;
        }
      });
    }
  } catch (e) {
    // Silent catch
  }
}

setInterval(pollNewMessages, 1500);

const chatFileInput = document.getElementById('chat-file-input');
if (chatFileInput) {
  chatFileInput.addEventListener('change', function() {
    document.getElementById('chat-file-preview').textContent = this.files[0] ? '📎 ' + this.files[0].name : '';
  });
}

const chatMsgInput = document.getElementById('chat-msg-input');
if (chatMsgInput) {
  chatMsgInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      document.getElementById('chat-form').requestSubmit();
    }
  });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
