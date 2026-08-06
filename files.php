<?php
// ─── File Sharing & Storage Center ───────────────────────────
$page_title = 'File Sharing Manager';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

// Handle Delete via Form fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fid = (int)$_POST['delete_file'];
    $stmt = $db->prepare("SELECT * FROM files WHERE id=?");
    $stmt->execute([$fid]);
    $f = $stmt->fetch();
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
if (!is_admin()) {
    $where .= ' AND (f.uploaded_by=? OR f.project_id IN (SELECT id FROM projects WHERE manager_id=? OR created_by=? OR id IN (SELECT project_id FROM project_members WHERE user_id=?)))';
    $params[] = $uid; $params[] = $uid; $params[] = $uid; $params[] = $uid;
}
if ($project_filter) { $where .= ' AND f.project_id=?'; $params[] = $project_filter; }
if ($search) { $where .= ' AND f.original_name LIKE ?'; $params[] = "%$search%"; }

if ($type_filter) {
    $type_exts = [
        'image'    => ['jpg','jpeg','png','gif','webp','svg'],
        'document' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt'],
        'archive'  => ['zip','rar','7z','tar','gz'],
        'media'    => ['mp4','mp3','wav','avi','mkv'],
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

// Storage statistics
$total_size = (float)($db->query("SELECT COALESCE(SUM(file_size),0) FROM files")->fetchColumn());
$total_files = (int)($db->query("SELECT COUNT(*) FROM files")->fetchColumn());
$max_storage = 500 * 1024 * 1024; // 500 MB limit
$storage_pct = $max_storage > 0 ? min(100, round(($total_size / $max_storage) * 100)) : 0;

function format_file_size($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function get_file_icon_class($ext) {
    return match(strtolower($ext)) {
        'pdf'               => ['icon' => 'bi-file-earmark-pdf-fill', 'color' => 'text-danger'],
        'doc', 'docx'       => ['icon' => 'bi-file-earmark-word-fill', 'color' => 'text-primary'],
        'xls', 'xlsx'       => ['icon' => 'bi-file-earmark-excel-fill', 'color' => 'text-success'],
        'ppt', 'pptx'       => ['icon' => 'bi-file-earmark-ppt-fill', 'color' => 'text-warning'],
        'jpg','jpeg','png','gif','webp','svg' => ['icon' => 'bi-file-earmark-image-fill', 'color' => 'text-info'],
        'zip','rar','7z'    => ['icon' => 'bi-file-earmark-zip-fill', 'color' => 'text-secondary'],
        'mp4','avi','mkv'   => ['icon' => 'bi-file-earmark-play-fill', 'color' => 'text-danger'],
        'mp3','wav'         => ['icon' => 'bi-file-earmark-music-fill', 'color' => 'text-info'],
        default             => ['icon' => 'bi-file-earmark-text-fill', 'color' => 'text-muted'],
    };
}

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="app-content-header py-3 px-4 border-bottom">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h2 class="fw-bold mb-0 fs-4" style="font-family:'Outfit',sans-serif;">
        <i class="bi bi-folder2-open me-2 text-primary"></i>File Sharing & Storage Hub
      </h2>
      <p class="text-muted small mb-0">Upload, share direct links, and collaborate on files securely.</p>
    </div>
    <button class="btn btn-primary btn-sm px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal" id="upload-file-main-btn">
      <i class="bi bi-cloud-upload-fill me-1"></i> Upload New File
    </button>
  </div>
</div>

<div class="app-content p-4">

  <!-- Storage Capacity & Quick Overview Banner -->
  <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;background:var(--cs-surface);">
    <div class="card-body p-4">
      <div class="row align-items-center g-4">
        <div class="col-md-5">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary">
              <i class="bi bi-hdd-network-fill fs-3"></i>
            </div>
            <div>
              <div class="fw-bold fs-5" style="font-family:'Outfit';"><?= format_file_size($total_size) ?> Used</div>
              <div class="small text-muted"><?= $total_files ?> file<?= $total_files!=1?'s':'' ?> stored total (500 MB Limit)</div>
            </div>
          </div>
          <div class="progress mt-3" style="height: 8px; border-radius: 99px;">
            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $storage_pct ?>%;" aria-valuenow="<?= $storage_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>

        <div class="col-md-7">
          <div class="d-flex flex-wrap gap-2 justify-content-md-end">
            <a href="files.php" class="btn btn-sm <?= !$type_filter?'btn-primary':'btn-outline-secondary' ?> rounded-pill px-3">All Files</a>
            <a href="files.php?type=document" class="btn btn-sm <?= $type_filter==='document'?'btn-primary':'btn-outline-secondary' ?> rounded-pill px-3"><i class="bi bi-file-earmark-text me-1"></i>Documents</a>
            <a href="files.php?type=image" class="btn btn-sm <?= $type_filter==='image'?'btn-primary':'btn-outline-secondary' ?> rounded-pill px-3"><i class="bi bi-image me-1"></i>Images</a>
            <a href="files.php?type=archive" class="btn btn-sm <?= $type_filter==='archive'?'btn-primary':'btn-outline-secondary' ?> rounded-pill px-3"><i class="bi bi-file-earmark-zip me-1"></i>Archives</a>
            <a href="files.php?type=media" class="btn btn-sm <?= $type_filter==='media'?'btn-primary':'btn-outline-secondary' ?> rounded-pill px-3"><i class="bi bi-film me-1"></i>Media</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Drag-and-Drop Dropzone Upload Bar -->
  <div class="card border-dashed mb-4 text-center p-4" id="dropzone-area" style="border:2px dashed var(--cs-border);border-radius:16px;background:var(--cs-surface-2);cursor:pointer;" onclick="document.getElementById('file-input-direct').click()">
    <input type="file" id="file-input-direct" class="d-none" onchange="uploadDirectFile(this)">
    <i class="bi bi-cloud-arrow-up-fill text-primary fs-1 mb-2 opacity-75"></i>
    <h6 class="fw-bold mb-1">Drag & Drop files here or click to upload</h6>
    <p class="small text-muted mb-0">Supports Images, PDFs, Word, Excel, Archives, and Media up to 20MB</p>
    <div id="upload-progress-container" class="mt-3 d-none">
      <div class="progress" style="height:6px;">
        <div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%"></div>
      </div>
      <div class="x-small text-muted mt-1" id="upload-status-text">Uploading...</div>
    </div>
  </div>

  <!-- Filter & Search Bar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center flex-grow-1" id="files-filter-form">
      <?php if ($type_filter): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>"><?php endif; ?>
      <div class="input-group style-none" style="max-width:320px;">
        <span class="input-group-text border-0 bg-body-secondary"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control border-0 bg-body-secondary" placeholder="Search file name…" value="<?= htmlspecialchars($search) ?>">
      </div>

      <select name="project_id" class="form-select border-0 bg-body-secondary" style="max-width:200px;" onchange="this.form.submit()">
        <option value="">All Projects</option>
        <?php foreach ($projects_list as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $project_filter==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-secondary btn-sm px-3">Filter</button>
      <?php if ($search || $project_filter || $type_filter): ?>
      <a href="files.php" class="btn btn-outline-secondary btn-sm px-3">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Files Display Grid -->
  <?php if (empty($files)): ?>
  <div class="card border-0 shadow-sm text-center py-5" style="border-radius:16px;">
    <div class="card-body">
      <i class="bi bi-folder-x fs-1 text-muted opacity-50 d-block mb-3"></i>
      <h5 class="fw-bold mb-1">No files found</h5>
      <p class="small text-muted mb-3">Upload a file or adjust your filters to view stored files.</p>
      <button class="btn btn-primary btn-sm px-4" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-cloud-upload me-1"></i>Upload File Now
      </button>
    </div>
  </div>
  <?php else: ?>

  <div class="row g-3" id="files-grid">
    <?php foreach ($files as $f):
      $iconInfo = get_file_icon_class($f['file_type']);
      $fileUrl = 'uploads/' . htmlspecialchars($f['file_name']);
      $isImage = in_array(strtolower($f['file_type']), ['jpg','jpeg','png','gif','webp']);
    ?>
    <div class="col-sm-6 col-md-4 col-xl-3" id="file-card-<?= $f['id'] ?>">
      <div class="card h-100 border-0 shadow-sm p-3 position-relative" style="border-radius:16px;background:var(--cs-surface);transition:all .2s ease;">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="rounded-3 p-3 bg-body-tertiary d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
            <i class="bi <?= $iconInfo['icon'] ?> <?= $iconInfo['color'] ?> fs-2"></i>
          </div>

          <div class="dropdown">
            <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical fs-5"></i></button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;">
              <li><a class="dropdown-item py-2" href="view_file.php?id=<?= $f['id'] ?>"><i class="bi bi-eye me-2 text-primary"></i>Open in Browser</a></li>
              <li><a class="dropdown-item py-2" href="<?= $fileUrl ?>" download="<?= htmlspecialchars($f['original_name']) ?>"><i class="bi bi-download me-2 text-success"></i>Download File</a></li>
              <li><button class="dropdown-item py-2" onclick="copyShareLink('<?= $fileUrl ?>')"><i class="bi bi-link-45deg me-2 text-info"></i>Copy Share Link</button></li>
              <?php if (is_admin() || $f['uploaded_by'] == $uid): ?>
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item py-2 text-danger" onclick="deleteFileAjax(<?= $f['id'] ?>)"><i class="bi bi-trash me-2"></i>Delete File</button></li>
              <?php endif; ?>
            </ul>
          </div>
        </div>

        <a href="view_file.php?id=<?= $f['id'] ?>" class="fw-bold text-decoration-none text-body text-truncate mb-1 d-block" title="<?= htmlspecialchars($f['original_name']) ?>">
          <?= htmlspecialchars($f['original_name']) ?>
        </a>

        <div class="x-small text-muted mb-3 d-flex align-items-center justify-content-between">
          <span><?= format_file_size($f['file_size']) ?></span>
          <span><?= time_ago($f['uploaded_at']) ?></span>
        </div>

        <?php if ($f['project_name']): ?>
        <div class="mb-3">
          <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold" style="font-size:.68rem;">
            <i class="bi bi-kanban me-1"></i><?= htmlspecialchars($f['project_name']) ?>
          </span>
        </div>
        <?php endif; ?>

        <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between x-small text-muted">
          <span>By <?= htmlspecialchars($f['uploader_name']) ?></span>
          <a href="view_file.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 style-none" style="font-size:.7rem;">
            <i class="bi bi-eye me-1"></i>Open
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>

<!-- Modal: Upload File -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" style="font-family:'Outfit';">
          <i class="bi bi-cloud-upload-fill me-2 text-primary"></i>Upload File to Storage
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="upload-modal-form" onsubmit="return handleModalUpload(event)" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label small text-muted">Select File:</label>
            <input type="file" name="file" id="modal-file-input" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label small text-muted">Attach to Project (Optional):</label>
            <select name="project_id" class="form-select">
              <option value="">No Project (General Storage)</option>
              <?php foreach ($projects_list as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $project_filter==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="modal-upload-status" class="mb-3"></div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="bi bi-cloud-upload me-1"></i> Upload File
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Image Preview Lightbox -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg bg-dark text-white" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h6 class="modal-title fw-bold" id="preview-filename-title">Image Preview</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <img id="preview-img-target" src="" class="img-fluid rounded-3 shadow" style="max-height:75vh;object-fit:contain;">
      </div>
    </div>
  </div>
</div>

<!-- Copy Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
  <div id="copyToast" class="toast align-items-center text-bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body small">
        <i class="bi bi-check-circle-fill me-2"></i>Share link copied to clipboard!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
// Copy share link helper
function copyShareLink(path) {
  const fullUrl = window.location.origin + window.location.pathname.replace('files.php', '') + path;
  navigator.clipboard.writeText(fullUrl).then(() => {
    const toastEl = document.getElementById('copyToast');
    if (toastEl) {
      const toast = new bootstrap.Toast(toastEl);
      toast.show();
    }
  });
}

// Image Lightbox Preview
function previewImage(url, name) {
  document.getElementById('preview-img-target').src = url;
  document.getElementById('preview-filename-title').textContent = name;
  const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
  modal.show();
}

// Drag & Drop Direct Upload
function uploadDirectFile(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const formData = new FormData();
  formData.append('file', file);

  const container = document.getElementById('upload-progress-container');
  const bar = document.getElementById('upload-progress-bar');
  const status = document.getElementById('upload-status-text');

  container.classList.remove('d-none');
  bar.style.width = '20%';
  status.textContent = 'Uploading ' + file.name + '...';

  fetch('api/files.php?action=upload', {
    method: 'POST',
    body: formData
  }).then(r => r.json()).then(data => {
    if (data.success) {
      bar.style.width = '100%';
      status.textContent = 'Upload complete! Reloading files...';
      setTimeout(() => location.reload(), 600);
    } else {
      alert(data.message || 'Upload failed.');
      container.classList.add('d-none');
    }
  }).catch(() => {
    alert('Upload error.');
    container.classList.add('d-none');
  });
}

// Modal Upload Handler
async function handleModalUpload(e) {
  e.preventDefault();
  const form = e.target;
  const status = document.getElementById('modal-upload-status');
  status.innerHTML = `<div class="alert alert-info py-2 small mb-0"><div class="spinner-border spinner-border-sm me-2"></div>Uploading file...</div>`;

  try {
    const formData = new FormData(form);
    const res = await fetch('api/files.php?action=upload', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      status.innerHTML = `<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle-fill me-1"></i>File uploaded successfully!</div>`;
      setTimeout(() => location.reload(), 600);
    } else {
      status.innerHTML = `<div class="alert alert-danger py-2 small mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>${data.message || 'Upload failed.'}</div>`;
    }
  } catch (err) {
    status.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Upload error occurred.</div>`;
  }
  return false;
}

// Single-click AJAX File Delete
async function deleteFileAjax(id) {
  if (!confirm('Are you sure you want to delete this file?')) return;
  const card = document.getElementById('file-card-' + id);

  try {
    const formData = new FormData();
    formData.append('file_id', id);
    const res = await fetch('api/files.php?action=delete', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
      if (card) {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.8)';
        setTimeout(() => card.remove(), 250);
      }
    } else {
      alert(data.message || 'Delete failed.');
    }
  } catch (e) {
    alert('Delete request error.');
  }
}

// Drag over animation for dropzone
const dropzone = document.getElementById('dropzone-area');
if (dropzone) {
  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.style.background = 'rgba(92,73,224,0.12)';
  });
  dropzone.addEventListener('dragleave', () => {
    dropzone.style.background = 'var(--cs-surface-2)';
  });
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.style.background = 'var(--cs-surface-2)';
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      document.getElementById('file-input-direct').files = e.dataTransfer.files;
      uploadDirectFile(document.getElementById('file-input-direct'));
    }
  });
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
