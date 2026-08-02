<?php
// ─── File Sharing Manager ─────────────────────────────────────
$page_title = 'File Manager';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fid = (int)$_POST['delete_file'];
    $f = $db->prepare("SELECT * FROM files WHERE id=?")->execute([$fid]) ?
        $db->query("SELECT * FROM files WHERE id=$fid")->fetch() : null;
    if ($f && (is_admin() || $f['uploaded_by'] == $uid)) {
        @unlink(UPLOAD_DIR . $f['file_name']);
        $db->prepare("DELETE FROM files WHERE id=?")->execute([$fid]);
    }
    header('Location: files.php');
    exit;
}

$project_filter = (int)($_GET['project_id'] ?? 0);
$type_filter    = $_GET['type'] ?? '';
$search         = trim($_GET['q'] ?? '');

$where  = '1=1';
$params = [];
if ($project_filter) { $where .= ' AND f.project_id=?'; $params[] = $project_filter; }
if ($search) { $where .= ' AND f.original_name LIKE ?'; $params[] = "%$search%"; }
if ($type_filter) {
    $type_exts = [
        'image' => ['jpg','jpeg','png','gif'],
        'document' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt'],
        'archive' => ['zip','rar'],
    ];
    if (isset($type_exts[$type_filter])) {
        $placeholders = implode(',', array_fill(0, count($type_exts[$type_filter]), '?'));
        $where .= " AND f.file_type IN ($placeholders)";
        $params = array_merge($params, $type_exts[$type_filter]);
    }
}

$stmt = $db->prepare("
    SELECT f.*, u.name AS uploader_name, p.name AS project_name
    FROM files f
    JOIN users u ON u.id=f.uploaded_by
    LEFT JOIN projects p ON p.id=f.project_id
    WHERE $where
    ORDER BY f.uploaded_at DESC
");
$stmt->execute($params);
$files = $stmt->fetchAll();

$projects_list = $db->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();

$file_icons = [
    'pdf'=>['bi-file-earmark-pdf-fill','danger'],
    'doc'=>['bi-file-earmark-word-fill','primary'],'docx'=>['bi-file-earmark-word-fill','primary'],
    'xls'=>['bi-file-earmark-excel-fill','success'],'xlsx'=>['bi-file-earmark-excel-fill','success'],
    'ppt'=>['bi-file-earmark-ppt-fill','warning'],'pptx'=>['bi-file-earmark-ppt-fill','warning'],
    'jpg'=>['bi-file-earmark-image-fill','info'],'jpeg'=>['bi-file-earmark-image-fill','info'],
    'png'=>['bi-file-earmark-image-fill','info'],'gif'=>['bi-file-earmark-image-fill','info'],
    'zip'=>['bi-file-earmark-zip-fill','secondary'],'rar'=>['bi-file-earmark-zip-fill','secondary'],
    'txt'=>['bi-file-earmark-text-fill','secondary'],
    'mp4'=>['bi-file-earmark-play-fill','danger'],'mp3'=>['bi-file-earmark-music-fill','info'],
];

// Storage stats
$total_size = $db->query("SELECT COALESCE(SUM(file_size),0) FROM files")->fetchColumn();
$total_files = $db->query("SELECT COUNT(*) FROM files")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="app-main">
  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-folder2-open me-2 text-primary"></i>File Manager</h2>
        <p class="text-muted small mb-0"><?= $total_files ?> file<?= $total_files!=1?'s':'' ?> · <?= round($total_size/1048576,1) ?> MB used</p>
      </div>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal" id="upload-file-main-btn">
        <i class="bi bi-cloud-upload me-1"></i>Upload File
      </button>
    </div>
  </div>

  <div class="app-content">

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" id="files-filter-form">
          <div class="input-group input-group-sm" style="max-width:240px;">
            <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" class="form-control border-0 bg-body-secondary" placeholder="Search files…" value="<?= htmlspecialchars($search) ?>" id="files-search">
          </div>
          <select name="project_id" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:200px;" id="files-project-filter">
            <option value="">All Projects</option>
            <?php foreach ($projects_list as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $project_filter==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="type" class="form-select form-select-sm border-0 bg-body-secondary" style="max-width:160px;" id="files-type-filter">
            <option value="">All Types</option>
            <option value="image" <?= $type_filter==='image'?'selected':'' ?>>Images</option>
            <option value="document" <?= $type_filter==='document'?'selected':'' ?>>Documents</option>
            <option value="archive" <?= $type_filter==='archive'?'selected':'' ?>>Archives</option>
          </select>
          <button type="submit" class="btn btn-sm btn-primary" id="files-filter-btn">Filter</button>
          <?php if ($search || $project_filter || $type_filter): ?>
          <a href="files.php" class="btn btn-sm btn-outline-secondary" id="files-clear-filter">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- File Grid -->
    <?php if (empty($files)): ?>
    <div class="text-center py-5">
      <i class="bi bi-folder2-open fs-1 d-block mb-3 opacity-25"></i>
      <h5 class="text-muted">No files found</h5>
      <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload your first file</button>
    </div>
    <?php else: ?>

    <!-- List View -->
    <div class="card">
      <div class="card-header bg-transparent py-3">
        <div class="row align-items-center x-small text-muted text-uppercase fw-semibold" style="letter-spacing:.06em;">
          <div class="col-5">File</div>
          <div class="col-2 d-none d-md-block">Project</div>
          <div class="col-2 d-none d-lg-block">Uploaded By</div>
          <div class="col-2 d-none d-md-block">Date</div>
          <div class="col text-end">Actions</div>
        </div>
      </div>
      <div class="card-body p-0">
        <?php foreach ($files as $f): ?>
        <?php
          $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
          [$ficon, $fcol] = $file_icons[$ext] ?? ['bi-file-earmark-fill','secondary'];
          $fsize = $f['file_size'] > 1048576 ? round($f['file_size']/1048576,1).'MB' : round($f['file_size']/1024,1).'KB';
        ?>
        <div class="row align-items-center px-4 py-3 border-bottom hover-row" id="file-row-<?= $f['id'] ?>">
          <div class="col-5 d-flex align-items-center gap-3">
            <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0 bg-<?= $fcol ?> bg-opacity-10 text-<?= $fcol ?>" style="width:44px;height:44px;font-size:1.4rem;">
              <i class="bi <?= $ficon ?>"></i>
            </div>
            <div class="overflow-hidden">
              <div class="fw-semibold small text-truncate"><?= htmlspecialchars($f['original_name']) ?></div>
              <div class="x-small text-muted"><?= strtoupper($ext) ?> · <?= $fsize ?></div>
            </div>
          </div>
          <div class="col-2 d-none d-md-block">
            <span class="small text-muted"><?= htmlspecialchars($f['project_name'] ?? '—') ?></span>
          </div>
          <div class="col-2 d-none d-lg-block">
            <span class="small text-muted"><?= htmlspecialchars($f['uploader_name']) ?></span>
          </div>
          <div class="col-2 d-none d-md-block">
            <span class="x-small text-muted"><?= time_ago($f['uploaded_at']) ?></span>
          </div>
          <div class="col text-end d-flex align-items-center justify-content-end gap-1">
            <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="previewImage('uploads/<?= htmlspecialchars($f['file_path']) ?>','<?= htmlspecialchars($f['original_name']) ?>')" id="preview-<?= $f['id'] ?>"><i class="bi bi-eye"></i></button>
            <?php endif; ?>
            <a href="uploads/<?= htmlspecialchars($f['file_path']) ?>" download="<?= htmlspecialchars($f['original_name']) ?>" class="btn btn-sm btn-outline-primary" id="dl-<?= $f['id'] ?>"><i class="bi bi-download"></i></a>
            <?php if (is_admin() || $f['uploaded_by'] == $uid): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this file?')">
              <input type="hidden" name="delete_file" value="<?= $f['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" id="del-file-<?= $f['id'] ?>"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="api/files.php?action=upload" method="POST" enctype="multipart/form-data" id="main-upload-form">
        <div class="modal-header border-0 pb-0">
          <h4 class="modal-title h5 fw-bold"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload File</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Project (optional)</label>
            <select name="project_id" class="form-select" id="upload-project-select">
              <option value="">No specific project</option>
              <?php foreach ($projects_list as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="upload-zone" onclick="document.getElementById('main-file-input').click()" id="main-upload-zone">
            <i class="bi bi-cloud-upload-fill d-block mb-2"></i>
            <div class="fw-semibold mb-1">Click to upload or drag & drop</div>
            <div class="small text-muted">Any file up to 20MB</div>
          </div>
          <input type="file" name="file" id="main-file-input" class="d-none" onchange="updateUploadPreview(this)">
          <div id="upload-preview" class="mt-2 small text-muted"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="main-upload-submit"><i class="bi bi-upload me-1"></i>Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-transparent border-0">
      <div class="modal-header border-0">
        <h5 class="modal-title text-white" id="preview-filename"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-0">
        <img src="" id="preview-img" class="img-fluid rounded-3" style="max-height:80vh;" alt="Preview">
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<JS
<script>
function updateUploadPreview(input) {
  document.getElementById('upload-preview').textContent = input.files[0] ? '📎 ' + input.files[0].name : '';
}
function previewImage(src, name) {
  document.getElementById('preview-img').src = src;
  document.getElementById('preview-filename').textContent = name;
  new bootstrap.Modal(document.getElementById('imagePreviewModal')).show();
}
// Drag & drop
const dz2 = document.getElementById('main-upload-zone');
if (dz2) {
  dz2.addEventListener('dragover', e => { e.preventDefault(); dz2.classList.add('dragover'); });
  dz2.addEventListener('dragleave', () => dz2.classList.remove('dragover'));
  dz2.addEventListener('drop', e => {
    e.preventDefault(); dz2.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f) { const dt = new DataTransfer(); dt.items.add(f); document.getElementById('main-file-input').files = dt.files; updateUploadPreview(document.getElementById('main-file-input')); }
  });
}
// AJAX submit
const uf = document.getElementById('main-upload-form');
if (uf) uf.addEventListener('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const btn = document.getElementById('main-upload-submit');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';
  fetch(this.action, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) { showToast('File uploaded!', 'success'); setTimeout(() => location.reload(), 700); }
      else { showToast(res.message || 'Upload failed.', 'danger'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload me-1"></i>Upload'; }
    }).catch(() => { showToast('Network error.', 'danger'); btn.disabled = false; });
});
// Hover row style
document.querySelectorAll('.hover-row').forEach(row => {
  row.addEventListener('mouseenter', () => row.style.background = 'rgba(79,70,229,.03)');
  row.addEventListener('mouseleave', () => row.style.background = '');
});
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
