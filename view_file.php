<?php
// ─── Universal In-Browser Document & File Viewer ──────────────
$page_title = 'File Preview';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$fid   = (int)($_GET['id'] ?? 0);
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

$file_name     = $file_rec['file_name'];
$original_name = $file_rec['original_name'];
$file_path     = UPLOAD_DIR . $file_name;
$ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

// Build Public Raw URL
$host_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$current_host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$raw_file_url  = $host_protocol . $current_host . '/raw_file.php?id=' . $file_rec['id'];
$direct_url    = $host_protocol . $current_host . '/uploads/' . rawurlencode($file_name);

$page_title = 'Preview: ' . $original_name;
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- Client-side Document Engines -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" crossorigin="anonymous"></script>

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
      <button onclick="navigator.clipboard.writeText('<?= $raw_file_url ?>'); alert('Direct link copied!');" class="btn btn-sm btn-outline-info rounded-pill px-3">
        <i class="bi bi-link-45deg me-1"></i> Share Link
      </button>
      <a href="raw_file.php?id=<?= $file_rec['id'] ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
        <i class="bi bi-download me-1"></i> Download File
      </a>
    </div>
  </div>
</div>

<div class="app-content p-0 d-flex flex-column" style="height: calc(100vh - 128px); background: var(--cs-bg);">

  <?php if ($ext === 'docx'): ?>
    <!-- ── High-Speed In-Browser Word (.docx) Renderer (Mammoth.js) ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-lg mx-auto" style="max-width:900px;border-radius:20px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4" style="border-radius:20px 20px 0 0;">
          <div class="fw-bold"><i class="bi bi-file-earmark-word-fill text-primary me-2 fs-5"></i>Word Document Reader</div>
          <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle-fill me-1"></i>Client Rendered</span>
        </div>
        <div class="card-body p-4 p-md-5">
          <div id="docx-output" class="document-render-area">
            <div class="text-center py-5 text-muted">
              <div class="spinner-border text-primary spinner-border-sm mb-2"></div>
              <div>Rendering Word Document...</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      fetch('raw_file.php?id=<?= $file_rec['id'] ?>')
        .then(r => r.arrayBuffer())
        .then(arrayBuffer => mammoth.convertToHtml({ arrayBuffer: arrayBuffer }))
        .then(result => {
          document.getElementById('docx-output').innerHTML = result.value || '<div class="text-muted text-center py-4">Document contains no text or formatted content.</div>';
        })
        .catch(err => {
          console.error(err);
          document.getElementById('docx-output').innerHTML = `
            <div class="alert alert-warning text-center">
              <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
              Direct rendering failed. <a href="raw_file.php?id=<?= $file_rec['id'] ?>" class="btn btn-sm btn-primary mt-2">Download Word File</a>
            </div>`;
        });
    </script>

  <?php elseif (in_array($ext, ['xlsx', 'xls'])): ?>
    <!-- ── High-Speed In-Browser Excel (.xlsx) Table Viewer (SheetJS) ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-lg mx-auto" style="max-width:1100px;border-radius:20px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4" style="border-radius:20px 20px 0 0;">
          <div class="fw-bold"><i class="bi bi-file-earmark-excel-fill text-success me-2 fs-5"></i>Excel Spreadsheet Reader</div>
          <div id="excel-sheet-tabs" class="btn-group btn-group-sm"></div>
        </div>
        <div class="card-body p-4 overflow-auto">
          <div id="excel-output">
            <div class="text-center py-5 text-muted">
              <div class="spinner-border text-success spinner-border-sm mb-2"></div>
              <div>Parsing Spreadsheet Data...</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      fetch('raw_file.php?id=<?= $file_rec['id'] ?>')
        .then(r => r.arrayBuffer())
        .then(arrayBuffer => {
          const workbook = XLSX.read(arrayBuffer, { type: 'array' });
          const output = document.getElementById('excel-output');
          const tabs = document.getElementById('excel-sheet-tabs');
          
          if (!workbook.SheetNames.length) {
            output.innerHTML = '<div class="text-muted text-center py-4">No sheets found in spreadsheet.</div>';
            return;
          }

          function renderSheet(name) {
            const worksheet = workbook.Sheets[name];
            const html = XLSX.utils.sheet_to_html(worksheet, { header: '', footer: '' });
            output.innerHTML = html;
            const table = output.querySelector('table');
            if (table) {
              table.className = 'table table-bordered table-striped table-hover small m-0';
            }
          }

          tabs.innerHTML = workbook.SheetNames.map((name, i) => `
            <button class="btn btn-outline-success btn-sm \${i===0?'active':''}" onclick="renderSheet('\${name}'); document.querySelectorAll('#excel-sheet-tabs .btn').forEach(b=>b.classList.remove('active')); this.classList.add('active');">\${name}</button>
          `).join('');

          renderSheet(workbook.SheetNames[0]);
        })
        .catch(err => {
          document.getElementById('excel-output').innerHTML = `<div class="alert alert-warning text-center">Unable to parse spreadsheet.</div>`;
        });
    </script>

  <?php elseif ($ext === 'pdf'): ?>
    <!-- ── High Definition Native PDF Viewer ── -->
    <iframe src="raw_file.php?id=<?= $file_rec['id'] ?>" class="w-100 h-100 border-0"></iframe>

  <?php elseif (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])): ?>
    <!-- ── Image Viewer ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 overflow-auto">
      <img src="raw_file.php?id=<?= $file_rec['id'] ?>" alt="<?= htmlspecialchars($original_name) ?>" class="img-fluid rounded-4 shadow-lg" style="max-height: 80vh; object-fit: contain;">
    </div>

  <?php elseif (in_array($ext, ['doc','ppt','pptx'])): ?>
    <!-- ── Office & Google Docs Viewer Fallback for Legacy Formats ── -->
    <div class="flex-grow-1 d-flex flex-column h-100">
      <div class="bg-body-tertiary px-4 py-2 border-bottom d-flex align-items-center justify-content-between">
        <span class="small text-muted"><i class="bi bi-file-earmark-slides me-1"></i>Document Viewer Service</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary active" onclick="document.getElementById('viewer-iframe').src='https://docs.google.com/viewer?url=<?= urlencode($raw_file_url) ?>&embedded=true'">Google Reader</button>
          <button class="btn btn-outline-primary" onclick="document.getElementById('viewer-iframe').src='https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($raw_file_url) ?>'">Office Reader</button>
        </div>
      </div>
      <iframe id="viewer-iframe" src="https://docs.google.com/viewer?url=<?= urlencode($raw_file_url) ?>&embedded=true" class="w-100 h-100 border-0"></iframe>
    </div>

  <?php elseif (in_array($ext, ['txt','json','csv','md','html','css','js','php','sql','xml','log'])): ?>
    <!-- ── Text / Code Viewer ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-sm mx-auto" style="max-width:1000px;border-radius:16px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary fw-bold small py-3 px-4">
          <i class="bi bi-file-earmark-code me-2 text-primary"></i>Source Code / Text Content
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
        <source src="raw_file.php?id=<?= $file_rec['id'] ?>" type="video/<?= $ext ?>">
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
          <source src="raw_file.php?id=<?= $file_rec['id'] ?>" type="audio/<?= $ext ?>">
          Your browser does not support HTML5 audio player.
        </audio>
      </div>
    </div>

  <?php else: ?>
    <!-- ── Fallback Download ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 text-center">
      <div class="card border-0 shadow p-5" style="border-radius:20px;max-width:500px;">
        <i class="bi bi-file-earmark-arrow-down-fill text-primary fs-1 mb-3"></i>
        <h5 class="fw-bold mb-2"><?= htmlspecialchars($original_name) ?></h5>
        <p class="small text-muted mb-4">This file type (<?= strtoupper($ext) ?>) can be downloaded directly.</p>
        <a href="raw_file.php?id=<?= $file_rec['id'] ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-primary py-2 px-4 rounded-pill">
          <i class="bi bi-download me-1"></i> Download File
        </a>
      </div>
    </div>
  <?php endif; ?>

</div>

<style>
.document-render-area {
  font-family: 'Inter', system-ui, sans-serif;
  line-height: 1.7;
  color: var(--cs-text);
  font-size: 0.95rem;
}
.document-render-area h1, .document-render-area h2, .document-render-area h3 {
  font-family: 'Outfit', sans-serif;
  font-weight: 700;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}
.document-render-area table {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
}
.document-render-area th, .document-render-area td {
  border: 1px solid var(--cs-border);
  padding: 8px 12px;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
