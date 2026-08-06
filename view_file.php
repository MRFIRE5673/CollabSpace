<?php
// ─── Universal In-Browser Document & File Viewer ──────────────
$page_title = 'File Preview';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$fid  = (int)($_GET['id'] ?? 0);
$fname = trim($_GET['file'] ?? '');

if ($fid) {
    $stmt = $db->prepare("SELECT f.*, u.name AS uploader_name, p.name AS project_name FROM files f JOIN users u ON u.id=f.uploaded_by LEFT JOIN projects p ON p.id=f.project_id WHERE f.id=?");
    $stmt->execute([$fid]);
    $file_rec = $stmt->fetch();
} elseif ($fname) {
    $stmt = $db->prepare("SELECT f.*, u.name AS uploader_name, p.name AS project_name FROM files f JOIN users u ON u.id=f.uploaded_by LEFT JOIN projects p ON p.id=f.project_id WHERE f.file_name=?");
    $stmt->execute([$fname]);
    $file_rec = $stmt->fetch();
} else {
    $file_rec = null;
}

if (!$file_rec) {
    header('Location: files.php');
    exit;
}

$file_name    = $file_rec['file_name'];
$original_name = $file_rec['original_name'];
$file_path    = UPLOAD_DIR . $file_name;
$ext          = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$is_raw       = isset($_GET['raw']) && $_GET['raw'] == 1;

// Helper MIME type mapper
function get_mime_type_for_ext($ext) {
    return match(strtolower($ext)) {
        'pdf'              => 'application/pdf',
        'jpg','jpeg'       => 'image/jpeg',
        'png'              => 'image/png',
        'gif'              => 'image/gif',
        'webp'             => 'image/webp',
        'svg'              => 'image/svg+xml',
        'txt','csv','log'  => 'text/plain',
        'json'             => 'application/json',
        'html'             => 'text/html',
        'mp4'              => 'video/mp4',
        'webm'             => 'video/webm',
        'mp3'              => 'audio/mpeg',
        'wav'              => 'audio/wav',
        'doc'              => 'application/msword',
        'docx'             => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'              => 'application/vnd.ms-excel',
        'xlsx'             => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'              => 'application/vnd.ms-powerpoint',
        'pptx'             => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        default            => 'application/octet-stream',
    };
}

// Stream raw inline file response if requested
if ($is_raw) {
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo 'File not found on disk.';
        exit;
    }
    $mime = get_mime_type_for_ext($ext);
    header("Content-Type: $mime");
    header('Content-Disposition: inline; filename="' . rawurlencode($original_name) . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

// Build Public URL for Office & Google Viewers
$host_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$current_host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$public_file_url = $host_protocol . $current_host . '/uploads/' . rawurlencode($file_name);

$page_title = 'Preview: ' . $original_name;
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="app-content-header py-3 px-4 border-bottom bg-body-tertiary">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
      <a href="files.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
        <i class="bi bi-arrow-left me-1"></i> Back to Files
      </a>
      <div>
        <h2 class="fw-bold mb-0 fs-5" style="font-family:'Outfit',sans-serif;"><?= htmlspecialchars($original_name) ?></h2>
        <div class="x-small text-muted">
          Uploaded by <?= htmlspecialchars($file_rec['uploader_name']) ?> · <?= time_ago($file_rec['uploaded_at']) ?>
          <?php if ($file_rec['project_name']): ?>
          · <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($file_rec['project_name']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button onclick="navigator.clipboard.writeText('<?= $public_file_url ?>'); alert('Direct link copied to clipboard!');" class="btn btn-sm btn-outline-info rounded-pill px-3">
        <i class="bi bi-link-45deg me-1"></i> Share Link
      </button>
      <a href="uploads/<?= htmlspecialchars($file_name) ?>" target="_blank" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
        <i class="bi bi-download me-1"></i> Download File
      </a>
    </div>
  </div>
</div>

<div class="app-content p-0 d-flex flex-column" style="height: calc(100vh - 128px); background: var(--cs-bg);">

  <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])): ?>
    <!-- ── Image Viewer ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 overflow-auto">
      <img src="uploads/<?= htmlspecialchars($file_name) ?>" alt="<?= htmlspecialchars($original_name) ?>" class="img-fluid rounded-4 shadow-lg" style="max-height: 80vh; object-fit: contain;">
    </div>

  <?php elseif ($ext === 'pdf'): ?>
    <!-- ── PDF Viewer ── -->
    <iframe src="view_file.php?raw=1&id=<?= $file_rec['id'] ?>" class="w-100 h-100 border-0"></iframe>

  <?php elseif (in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx'])): ?>
    <!-- ── Microsoft Office & Google Docs Online Viewer ── -->
    <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($public_file_url) ?>" class="w-100 h-100 border-0" id="office-iframe"></iframe>
    <script>
      // Fallback to Google Docs Viewer if Office viewer times out
      setTimeout(() => {
        const iframe = document.getElementById('office-iframe');
        if (iframe && !iframe.contentWindow) {
          iframe.src = "https://docs.google.com/viewer?url=<?= urlencode($public_file_url) ?>&embedded=true";
        }
      }, 4000);
    </script>

  <?php elseif (in_array($ext, ['txt','json','csv','md','html','css','js','php','sql','xml','log'])): ?>
    <!-- ── Code & Text Viewer ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-sm" style="border-radius:16px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary fw-bold small">
          <i class="bi bi-file-earmark-code me-2 text-primary"></i>Text File Content
        </div>
        <div class="card-body p-0">
          <pre class="p-4 m-0 font-monospace" style="font-size:.85rem;white-space:pre-wrap;word-break:break-word;color:var(--cs-text);"><?= htmlspecialchars(file_get_contents($file_path)) ?></pre>
        </div>
      </div>
    </div>

  <?php elseif (in_array($ext, ['mp4','webm'])): ?>
    <!-- ── Video Player ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4">
      <video controls class="w-100 rounded-4 shadow-lg" style="max-width:900px;max-height:75vh;">
        <source src="uploads/<?= htmlspecialchars($file_name) ?>" type="video/<?= $ext ?>">
        Your browser does not support HTML5 video player.
      </video>
    </div>

  <?php elseif (in_array($ext, ['mp3','wav','ogg'])): ?>
    <!-- ── Audio Player ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4">
      <div class="card border-0 shadow p-4 text-center" style="border-radius:20px;max-width:500px;width:100%;">
        <i class="bi bi-music-note-beamed text-primary fs-1 mb-3"></i>
        <h5 class="fw-bold mb-3"><?= htmlspecialchars($original_name) ?></h5>
        <audio controls class="w-100">
          <source src="uploads/<?= htmlspecialchars($file_name) ?>" type="audio/<?= $ext ?>">
          Your browser does not support HTML5 audio player.
        </audio>
      </div>
    </div>

  <?php else: ?>
    <!-- ── General File Fallback ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 text-center">
      <div class="card border-0 shadow p-5" style="border-radius:20px;max-width:500px;">
        <i class="bi bi-file-earmark-arrow-down-fill text-primary fs-1 mb-3"></i>
        <h5 class="fw-bold mb-2"><?= htmlspecialchars($original_name) ?></h5>
        <p class="small text-muted mb-4">This file type (<?= strtoupper($ext) ?>) can be downloaded directly to your device.</p>
        <a href="uploads/<?= htmlspecialchars($file_name) ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-primary py-2 px-4 rounded-pill">
          <i class="bi bi-download me-1"></i> Download File
        </a>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
