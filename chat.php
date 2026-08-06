<?php
// ─── Team Chat (Real-time SPA Chat with Contact Connection) ───
$page_title = 'Team Chat';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Fetch user's active project rooms
if (is_admin()) {
    $projects = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, name FROM projects WHERE created_by=? OR manager_id=? OR id IN (SELECT project_id FROM project_members WHERE user_id=?) ORDER BY name");
    $stmt->execute([$uid, $uid, $uid]);
    $projects = $stmt->fetchAll();
}

// Fetch user's added contacts (Direct Messages)
$stmt = $db->prepare("
    SELECT u.id, u.name, u.email, u.status, u.avatar
    FROM user_contacts uc
    JOIN users u ON u.id = uc.contact_id
    WHERE uc.user_id = ? AND u.is_active = 1
    ORDER BY u.status DESC, u.name ASC
");
$stmt->execute([$uid]);
$dm_contacts = $stmt->fetchAll();

// Active room setup
$room_type   = $_GET['type'] ?? 'project';
$room_id     = (int)($_GET['id'] ?? ($projects[0]['id'] ?? ($dm_contacts[0]['id'] ?? 0)));
$room_name   = 'General';

if ($room_type === 'project' && $room_id) {
    $r = $db->prepare("SELECT name FROM projects WHERE id=?"); $r->execute([$room_id]);
    $room_name = $r->fetchColumn() ?: 'Project Chat';
} elseif ($room_type === 'direct' && $room_id) {
    $r = $db->prepare("SELECT name FROM users WHERE id=?"); $r->execute([$room_id]);
    $room_name = $r->fetchColumn() ?: 'Direct Message';
}

// Initial Messages Fetch
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
$last_id = empty($messages) ? 0 : max(array_column($messages, 'id'));

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="app-content" style="padding:16px!important;">
  <div class="chat-wrapper">

    <!-- Left Sidebar: Chat Rooms & Added Contacts -->
    <div class="chat-rooms">
      
      <!-- Project Rooms Section -->
      <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
        <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Project Rooms</div>
        <span class="badge bg-primary rounded-pill px-2" style="font-size:.65rem;"><?= count($projects) ?></span>
      </div>
      
      <div class="overflow-auto p-2" style="max-height: 40%;">
        <?php if (empty($projects)): ?>
        <div class="text-center py-3 x-small text-muted">No project rooms yet.</div>
        <?php endif; ?>
        <?php foreach ($projects as $p): ?>
        <div onclick="switchRoom('project', <?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')"
             class="chat-room-item text-decoration-none text-body <?= $room_type==='project'&&$room_id==$p['id']?'active':'' ?>"
             id="room-project-<?= $p['id'] ?>">
          <div class="rounded-2 d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:36px;height:36px;font-size:.72rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);">
            <?= strtoupper(substr($p['name'],0,2)) ?>
          </div>
          <div class="overflow-hidden">
            <div class="fw-semibold small text-truncate"><?= htmlspecialchars($p['name']) ?></div>
            <div class="x-small text-muted">Project Chat</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Direct Messages (Friends/Contacts) Section -->
      <div class="p-3 border-top border-bottom d-flex align-items-center justify-content-between bg-body-tertiary">
        <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Direct Messages</div>
        <button class="btn btn-primary btn-sm py-0 px-2 style-none" style="font-size:.7rem;" data-bs-toggle="modal" data-bs-target="#connectUserModal">
          <i class="bi bi-person-plus-fill me-1"></i>Connect
        </button>
      </div>

      <div class="overflow-auto flex-grow-1 p-2" id="contacts-list-container">
        <?php if (empty($dm_contacts)): ?>
        <div class="text-center py-4 px-3" id="no-contacts-msg">
          <i class="bi bi-people-fill text-muted opacity-50 fs-3 d-block mb-2"></i>
          <div class="small fw-semibold text-muted mb-1">No contacts added</div>
          <div class="x-small text-muted mb-3">Click below to search & add a friend by username!</div>
          <button class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#connectUserModal">
            <i class="bi bi-search me-1"></i>Connect to People
          </button>
        </div>
        <?php endif; ?>

        <?php foreach ($dm_contacts as $u): ?>
        <div onclick="switchRoom('direct', <?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')"
             class="chat-room-item text-decoration-none text-body <?= $room_type==='direct'&&$room_id==$u['id']?'active':'' ?>"
             id="room-direct-<?= $u['id'] ?>">
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
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Center: Main Chat Messages Window -->
    <div class="chat-main">
      
      <!-- Header -->
      <div class="chat-header">
        <div id="room-avatar-box">
          <?php if ($room_type==='project'): ?>
          <div class="rounded-2 d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:38px;height:38px;font-size:.75rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);">
            <?= strtoupper(substr($room_name,0,2)) ?>
          </div>
          <?php else: ?>
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:38px;height:38px;font-size:.75rem;background:#7c3aed;">
            <?= strtoupper(substr($room_name,0,2)) ?>
          </div>
          <?php endif; ?>
        </div>

        <div>
          <div class="fw-bold small" id="room-title-display"><?= htmlspecialchars($room_name) ?></div>
          <div class="x-small text-muted" id="room-subtitle-display"><?= $room_type==='project'?'Project Room':'Direct Message' ?></div>
        </div>

        <button class="btn btn-outline-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#connectUserModal">
          <i class="bi bi-person-plus-fill me-1"></i>Connect to People
        </button>
      </div>

      <!-- Messages Scroll Area -->
      <div class="chat-messages" id="chat-messages-container">
        <?php if (empty($messages)): ?>
        <div class="text-center py-5 text-muted my-auto" id="chat-empty-state">
          <i class="bi bi-chat-heart-fill fs-1 text-primary opacity-50 d-block mb-3"></i>
          <h6 class="fw-bold mb-1">No messages in this chat yet</h6>
          <p class="small text-muted mb-0">Type a message below to start the conversation!</p>
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
            <div class="chat-attachment d-flex align-items-center gap-2 mt-1">
              <a href="raw_file.php?chat=1&file=<?= urlencode($m['file_path']) ?>" download="<?= htmlspecialchars($m['file_name'] ?? $m['file_path']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem;">
                <i class="bi bi-download me-1"></i><?= htmlspecialchars($m['file_name'] ?? 'Download') ?>
              </a>
              <a href="raw_file.php?chat=1&file=<?= urlencode($m['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem;">
                <i class="bi bi-eye me-1"></i>View
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Bottom Chat Input Bar -->
      <div class="chat-input-bar">
        <form id="chat-form" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="input-project-id" value="<?= $room_type==='project'?$room_id:'' ?>">
          <input type="hidden" name="room_type"  id="input-room-type"  value="<?= htmlspecialchars($room_type) ?>">
          <input type="hidden" name="receiver_id" id="input-receiver-id" value="<?= $room_type==='direct'?$room_id:'' ?>">

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

    <!-- Right Sidebar: Teammates Presence -->
    <div class="d-none d-xl-flex flex-column" style="width:200px;border-left:1px solid var(--cs-border);padding:16px;gap:12px;flex-shrink:0;background:var(--cs-surface-2);">
      <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Active Friends</div>
      <div id="online-users-list">
        <?php foreach ($dm_contacts as $u): ?>
        <div class="d-flex align-items-center gap-2 py-1 cursor-pointer" onclick="switchRoom('direct', <?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
          <span class="online-indicator <?= $u['status']==='online'?'':'offline-indicator' ?>"></span>
          <span class="small text-truncate"><?= htmlspecialchars($u['name']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="d-flex align-items-center gap-2 py-1 mt-2 border-top pt-2">
          <span class="online-indicator"></span>
          <span class="small fw-semibold"><?= htmlspecialchars($user['name']) ?> (you)</span>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal: Connect to People (Add Friend by Username/Email) -->
<div class="modal fade" id="connectUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" style="font-family:'Outfit';">
          <i class="bi bi-person-plus-fill me-2 text-primary"></i>Connect to Teammates
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <p class="small text-muted mb-3">Enter a username or email to search and add them as a friend for direct messaging:</p>
        
        <form id="connect-user-form" onsubmit="return handleAddContact(event)">
          <div class="input-group mb-3">
            <input type="text" id="contact-search-input" class="form-control" placeholder="Enter username or email…" required autocomplete="off">
            <button type="submit" class="btn btn-primary px-3">
              <i class="bi bi-person-plus-fill me-1"></i> Add Friend
            </button>
          </div>
        </form>

        <div id="connect-status-msg" class="mb-3"></div>

        <div class="small fw-semibold text-muted text-uppercase mb-2" style="font-size:.65rem;letter-spacing:.08em;">Suggested Users</div>
        <div id="suggested-users-list" class="list-group list-group-flush border rounded-3" style="max-height:220px;overflow-y:auto;">
          <div class="text-center py-3 text-muted x-small">Type above to search registered users...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$init_vars = "<script>let lastMsgId = " . (int)$last_id . "; let currentRoomType = " . json_encode($room_type) . "; let currentRoomId = " . (int)$room_id . ";</script>";
$page_scripts = $init_vars . <<<'JS'
<script>
let isSwitching = false; // Mutex: prevent polling race during room switch
const chatContainer = document.getElementById('chat-messages-container');

function scrollToBottom() {
  if (chatContainer) {
    chatContainer.scrollTop = chatContainer.scrollHeight;
  }
}
scrollToBottom();

// In-Page Dynamic SPA Room Switcher (No Page Reload)
async function switchRoom(type, id, name) {
  if (!id) return;
  if (isSwitching) return; // Prevent concurrent switches
  isSwitching = true;

  currentRoomType = type;
  currentRoomId = id;
  lastMsgId = 0;

  // Update URL without page reload
  window.history.pushState({}, '', `chat.php?type=${type}&id=${id}`);

  // Update active state in left sidebar
  document.querySelectorAll('.chat-room-item').forEach(el => el.classList.remove('active'));
  const activeItem = document.getElementById(`room-${type}-${id}`);
  if (activeItem) activeItem.classList.add('active');

  // Update chat header details
  document.getElementById('room-title-display').textContent = name;
  document.getElementById('room-subtitle-display').textContent = type === 'project' ? 'Project Room' : 'Direct Message';
  
  const avatarBox = document.getElementById('room-avatar-box');
  const initials = (name || 'U').substring(0, 2).toUpperCase();
  const radius = type === 'project' ? 'rounded-2' : 'rounded-circle';
  avatarBox.innerHTML = `<div class="${radius} d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:38px;height:38px;font-size:.75rem;background:linear-gradient(135deg,#4f46e5,#7c3aed);">${initials}</div>`;

  // Update hidden form fields
  document.getElementById('input-room-type').value = type;
  document.getElementById('input-project-id').value = type === 'project' ? id : '';
  document.getElementById('input-receiver-id').value = type === 'direct' ? id : '';

  // Show loading spinner in chat box
  chatContainer.innerHTML = `
    <div class="text-center py-5 text-muted my-auto">
      <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
      <div class="small">Loading conversation...</div>
    </div>
  `;

  // Fetch room messages via AJAX
  let url = `api/chat.php?action=fetch&after_id=0&room_type=${type}`;
  if (type === 'direct') url += `&receiver_id=${id}`;
  else url += `&project_id=${id}`;

  try {
    const res = await fetch(url);
    const messages = await res.json();
    
    chatContainer.innerHTML = '';
    if (Array.isArray(messages) && messages.length > 0) {
      messages.forEach(m => {
        appendMessageUI(m);
        if (m.id > lastMsgId) lastMsgId = m.id;
      });
    } else {
      chatContainer.innerHTML = `
        <div class="text-center py-5 text-muted my-auto" id="chat-empty-state">
          <i class="bi bi-chat-heart-fill fs-1 text-primary opacity-50 d-block mb-3"></i>
          <h6 class="fw-bold mb-1">No messages in this chat yet</h6>
          <p class="small text-muted mb-0">Type a message below to start the conversation!</p>
        </div>
      `;
    }
    scrollToBottom();
  } catch (err) {
    console.error('Error fetching room messages:', err);
  } finally {
    isSwitching = false; // Release mutex
  }
}

// Instant Send Message — uses synchronous e.preventDefault() FIRST, then async work
// (async functions return Promise which is truthy, so onsubmit="return asyncFn()" won't block navigation)
function sendChatMessage() {
  const form     = document.getElementById('chat-form');
  const input    = document.getElementById('chat-msg-input');
  const msgText  = input ? input.value.trim() : '';
  const fileInput = document.getElementById('chat-file-input');

  if (!msgText && (!fileInput || !fileInput.files.length)) return;

  const formData = new FormData(form);
  formData.set('room_type', currentRoomType);
  if (currentRoomType === 'direct') {
    formData.set('receiver_id', currentRoomId);
    formData.set('project_id', '');
  } else {
    formData.set('project_id', currentRoomId);
    formData.set('receiver_id', '');
  }

  if (input) input.value = '';
  const preview = document.getElementById('chat-file-preview');
  if (preview) preview.textContent = '';
  const emptyState = document.getElementById('chat-empty-state');
  if (emptyState) emptyState.remove();

  // Async send — page never navigates because the form submit is already prevented
  fetch('api/chat.php?action=send', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.message) {
        appendMessageUI(data.message);
        if (data.id && data.id > lastMsgId) lastMsgId = data.id;
      }
    })
    .catch(err => console.error('Failed to send message:', err));

  if (fileInput) fileInput.value = '';
}

// Attach submit handler with e.preventDefault() synchronously (not in async function)
document.getElementById('chat-form').addEventListener('submit', function(e) {
  e.preventDefault();
  e.stopPropagation();
  sendChatMessage();
});

function appendMessageUI(m) {
  const isMine = m.is_mine;
  const div = document.createElement('div');
  div.className = 'chat-msg ' + (isMine ? 'mine' : '');
  div.id = 'msg-' + m.id;
  
  let fileHtml = '';
  if (m.file_path) {
    const fileUrl = `raw_file.php?chat=1&file=${encodeURIComponent(m.file_path)}`;
    fileHtml = `<div class="chat-attachment d-flex align-items-center gap-2 mt-1">
      <a href="${fileUrl}" download="${escapeHtml(m.file_name || m.file_path)}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem;">
        <i class="bi bi-download me-1"></i>${escapeHtml(m.file_name || 'Download')}
      </a>
      <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem;">
        <i class="bi bi-eye me-1"></i>View
      </a>
    </div>`;
  }
  
  div.innerHTML = `
    <div class="chat-avatar">${(m.sender_name || 'U').substring(0,2).toUpperCase()}</div>
    <div class="chat-bubble">
      <div class="chat-meta">
        <span class="fw-semibold me-2">${escapeHtml(m.sender_name || 'User')}</span>
        <span>${m.time_ago || 'just now'}</span>
      </div>
      ${m.message ? `<div class="chat-text">${escapeHtml(m.message).replace(/\n/g, '<br>')}</div>` : ''}
      ${fileHtml}
    </div>
  `;
  
  if (chatContainer) {
    chatContainer.appendChild(div);
    scrollToBottom();
  }
}

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Seamless Background Live Message Polling (Every 1.5 Seconds)
async function pollNewMessages() {
  if (!currentRoomId) return;
  if (isSwitching) return; // Do not poll while switching rooms
  try {
    let url = `api/chat.php?action=fetch&after_id=${lastMsgId}&room_type=${currentRoomType}`;
    if (currentRoomType === 'direct') {
      url += `&receiver_id=${currentRoomId}`;
    } else {
      url += `&project_id=${currentRoomId}`;
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
    // Silent polling catch
  }
}

setInterval(pollNewMessages, 1500);

// Add Contact / Friend Handler
async function handleAddContact(e) {
  e.preventDefault();
  const input = document.getElementById('contact-search-input');
  const msgBox = document.getElementById('connect-status-msg');
  const query = input ? input.value.trim() : '';
  if (!query) return false;

  msgBox.innerHTML = `<div class="alert alert-info py-2 small mb-0"><div class="spinner-border spinner-border-sm me-2"></div>Searching teammate...</div>`;

  try {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('query', query);

    const res = await fetch('api/contacts.php?action=add', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success && data.contact) {
      const c = data.contact;
      msgBox.innerHTML = `<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle-fill me-1"></i>Connected with <strong>${escapeHtml(c.name)}</strong>!</div>`;
      input.value = '';

      // Remove no contacts placeholder
      const noContactsMsg = document.getElementById('no-contacts-msg');
      if (noContactsMsg) noContactsMsg.remove();

      // Append friend to Left Sidebar Direct Messages list if not exists
      const listContainer = document.getElementById('contacts-list-container');
      if (!document.getElementById(`room-direct-${c.id}`)) {
        const item = document.createElement('div');
        item.onclick = () => switchRoom('direct', c.id, c.name);
        item.className = 'chat-room-item text-decoration-none text-body';
        item.id = `room-direct-${c.id}`;
        item.innerHTML = `
          <div class="position-relative">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:36px;height:36px;font-size:.72rem;background:#7c3aed;">
              ${c.name.substring(0,2).toUpperCase()}
            </div>
            <span class="position-absolute bottom-0 end-0 online-indicator ${c.status === 'online' ? '' : 'offline-indicator'}"></span>
          </div>
          <div class="overflow-hidden">
            <div class="fw-semibold small text-truncate">${escapeHtml(c.name)}</div>
            <div class="x-small ${c.status === 'online' ? 'text-success' : 'text-muted'}">${c.status === 'online' ? 'Online' : 'Offline'}</div>
          </div>
        `;
        listContainer.appendChild(item);
      }

      // Automatically switch to chat with new friend after 800ms
      setTimeout(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('connectUserModal'));
        if (modal) modal.hide();
        switchRoom('direct', c.id, c.name);
      }, 800);

    } else {
      msgBox.innerHTML = `<div class="alert alert-danger py-2 small mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>${data.message || 'User not found.'}</div>`;
    }
  } catch (err) {
    msgBox.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Connection failed. Try again.</div>`;
  }
  return false;
}

// Live User Search in Connect Modal
const contactSearchInput = document.getElementById('contact-search-input');
if (contactSearchInput) {
  let searchTimeout = null;
  contactSearchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    const list = document.getElementById('suggested-users-list');
    if (!q) {
      list.innerHTML = `<div class="text-center py-3 text-muted x-small">Type above to search registered users...</div>`;
      return;
    }
    searchTimeout = setTimeout(async () => {
      try {
        const res = await fetch(`api/contacts.php?action=search&q=${encodeURIComponent(q)}`);
        const users = await res.json();
        if (Array.isArray(users) && users.length > 0) {
          list.innerHTML = users.map(u => `
            <div class="list-group-item d-flex align-items-center justify-content-between p-2">
              <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;font-size:.75rem;">
                  ${u.name.substring(0,2).toUpperCase()}
                </div>
                <div class="overflow-hidden">
                  <div class="fw-semibold small text-truncate">${escapeHtml(u.name)}</div>
                  <div class="x-small text-muted text-truncate">${escapeHtml(u.email)}</div>
                </div>
              </div>
              <button onclick="document.getElementById('contact-search-input').value='${escapeHtml(u.email)}'; handleAddContact(event);" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size:.72rem;">
                <i class="bi bi-person-plus me-1"></i>Add
              </button>
            </div>
          `).join('');
        } else {
          list.innerHTML = `<div class="text-center py-3 text-muted x-small">No users matching "${escapeHtml(q)}" found.</div>`;
        }
      } catch (err) {}
    }, 250);
  });
}

// File Attachment Preview
const chatFileInput = document.getElementById('chat-file-input');
if (chatFileInput) {
  chatFileInput.addEventListener('change', function() {
    document.getElementById('chat-file-preview').textContent = this.files[0] ? '📎 ' + this.files[0].name : '';
  });
}

// Enter Key Send — triggers the submit event (which is caught by addEventListener above)
const chatMsgInput = document.getElementById('chat-msg-input');
if (chatMsgInput) {
  chatMsgInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendChatMessage();
    }
  });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
