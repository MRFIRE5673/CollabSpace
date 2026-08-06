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
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>


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

          <div class="px-2 py-2 mt-2">
            <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Direct Messages</div>
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
            <div class="fw-semibold"><?= htmlspecialchars($room_name) ?></div>
            <div class="x-small text-muted"><?= $room_type==='project'?'Project Room':'Direct Message' ?></div>
          </div>
          <?php if ($room_type === 'project' && $room_id): ?>
          <div class="ms-auto">
            <a href="project_details.php?id=<?= $room_id ?>" class="btn btn-sm btn-outline-primary" id="go-to-project-btn">
              <i class="bi bi-box-arrow-up-right me-1"></i>Project
            </a>
          </div>
          <?php endif; ?>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chat-messages-box"
             data-project-id="<?= $room_type==='project'?$room_id:'' ?>"
             data-room-type="<?= htmlspecialchars($room_type) ?>"
             data-receiver-id="<?= $room_type==='direct'?$room_id:'' ?>"
             data-last-id="<?= $last_id ?>">

          <?php if (empty($messages)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-chat-dots fs-1 d-block mb-2 opacity-25"></i>
            <p class="small">No messages yet.<br>Start the conversation! 👋</p>
          </div>
          <?php endif; ?>

          <?php foreach ($messages as $m): ?>
          <?php $isMine = (bool)$m['is_mine']; ?>
          <div class="d-flex gap-2 <?= $isMine?'flex-row-reverse':'' ?>" data-msg-id="<?= $m['id'] ?>">
            <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold" style="width:34px;height:34px;background:#4f46e5;font-size:.72rem;align-self:flex-end;">
              <?= strtoupper(substr($m['sender_name'],0,2)) ?>
            </div>
            <div class="msg-bubble <?= $isMine?'msg-self':'msg-other' ?>">
              <?php if (!$isMine): ?><div class="x-small text-muted mb-1"><?= htmlspecialchars($m['sender_name']) ?></div><?php endif; ?>
              <?php if ($m['message']): ?>
              <div class="msg-bubble-inner"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
              <?php endif; ?>
              <?php if ($m['file_path']): ?>
              <div class="mt-1"><a href="uploads/<?= htmlspecialchars($m['file_path']) ?>" class="btn btn-sm btn-outline-secondary" download><i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($m['file_name'] ?? 'File') ?></a></div>
              <?php endif; ?>
              <div class="msg-time"><?= time_ago($m['created_at']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Input -->
        <div class="chat-footer">
          <form id="chat-form" onsubmit="return sendChatMessage(this)" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="<?= $room_type==='project'?$room_id:'' ?>">
            <input type="hidden" name="room_type" value="<?= htmlspecialchars($room_type) ?>">
            <input type="hidden" name="receiver_id" value="<?= $room_type==='direct'?$room_id:'' ?>">
            <div class="d-flex gap-2">
              <textarea id="chat-input" name="message" class="form-control border-0 bg-body-secondary" rows="1" placeholder="Type a message… (Enter to send)" style="resize:none;border-radius:12px!important;"></textarea>
              <div class="d-flex flex-column gap-1">
                <label class="btn btn-outline-secondary btn-sm" for="chat-file-input" title="Attach file" id="chat-attach-label"><i class="bi bi-paperclip"></i></label>
                <input type="file" id="chat-file-input" name="chat_file" class="d-none">
                <button type="submit" class="btn btn-primary btn-sm" id="chat-send-btn"><i class="bi bi-send-fill"></i></button>
              </div>
            </div>
            <div id="chat-file-preview" class="mt-1 x-small text-muted"></div>
          </form>
        </div>
      </div>

      <!-- Online Sidebar -->
      <div class="d-none d-xl-flex flex-column" style="width:200px;border-left:1px solid rgba(0,0,0,.07);padding:16px;gap:8px;flex-shrink:0;">
        <div class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.08em;font-size:.65rem;">Online</div>
        <div id="online-users-list">
          <?php foreach ($dm_users as $u): if ($u['status']==='online'): ?>
          <div class="d-flex align-items-center gap-2 py-1">
            <span class="online-indicator"></span>
            <span class="small"><?= htmlspecialchars($u['name']) ?></span>
          </div>
          <?php endif; endforeach; ?>
          <div class="d-flex align-items-center gap-2 py-1">
            <span class="online-indicator"></span>
            <span class="small"><?= htmlspecialchars($user['name']) ?> (you)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php
$page_scripts = <<<JS
<script>
const chatFileInput = document.getElementById('chat-file-input');
if (chatFileInput) chatFileInput.addEventListener('change', function() {
  document.getElementById('chat-file-preview').textContent = this.files[0] ? '📎 ' + this.files[0].name : '';
});
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
