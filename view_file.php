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

$file_rec = null;

if ($fid) {
    $stmt = $db->prepare("SELECT f.*, u.name AS uploader_name, p.name AS project_name FROM files f JOIN users u ON u.id=f.uploaded_by LEFT JOIN projects p ON p.id=f.project_id WHERE f.id=?");
    $stmt->execute([$fid]);
    $file_rec = $stmt->fetch();
    if (!$file_rec) {
        $cstmt = $db->prepare("SELECT c.id AS chat_id, c.file_path AS file_name, c.file_name AS original_name, c.created_at AS uploaded_at, u.name AS uploader_name, p.name AS project_name FROM chats c JOIN users u ON u.id=c.sender_id LEFT JOIN projects p ON p.id=c.project_id WHERE c.id=?");
        $cstmt->execute([$fid]);
        $file_rec = $cstmt->fetch();
        if ($file_rec && empty($file_rec['original_name'])) {
            $file_rec['original_name'] = $file_rec['file_name'];
        }
    }
}

if (!$file_rec && $fname) {
    $stmt = $db->prepare("SELECT f.*, u.name AS uploader_name, p.name AS project_name FROM files f JOIN users u ON u.id=f.uploaded_by LEFT JOIN projects p ON p.id=f.project_id WHERE f.file_name=?");
    $stmt->execute([$fname]);
    $file_rec = $stmt->fetch();

    if (!$file_rec) {
        $cstmt = $db->prepare("SELECT c.id AS chat_id, c.file_path AS file_name, c.file_name AS original_name, c.created_at AS uploaded_at, u.name AS uploader_name, p.name AS project_name FROM chats c JOIN users u ON u.id=c.sender_id LEFT JOIN projects p ON p.id=c.project_id WHERE c.file_path=?");
        $cstmt->execute([$fname]);
        $chat_rec = $cstmt->fetch();

        if ($chat_rec) {
            $file_rec = $chat_rec;
            $file_rec['id'] = 0;
            if (empty($file_rec['original_name'])) $file_rec['original_name'] = $fname;
        } elseif (file_exists(UPLOAD_DIR . $fname)) {
            $file_rec = [
                'id' => 0,
                'file_name' => $fname,
                'original_name' => $fname,
                'uploader_name' => 'Attachment',
                'uploaded_at' => date('Y-m-d H:i:s'),
                'project_name' => null
            ];
        }
    }
}

if (!$file_rec) {
    header('Location: files.php');
    exit;
}

$file_name     = $file_rec['file_name'];
$original_name = !empty($file_rec['original_name']) ? $file_rec['original_name'] : $file_name;
$file_path     = UPLOAD_DIR . $file_name;
$ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

// Build Public Raw Stream URL
$raw_stream_src = 'raw_file.php?' . ($file_rec['id'] ? 'id=' . $file_rec['id'] : 'file=' . urlencode($file_name));

$host_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$current_host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$raw_file_url  = $host_protocol . $current_host . '/' . $raw_stream_src;

$page_title = 'Preview: ' . $original_name;
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- Client-side Document Engines -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js" crossorigin="anonymous"></script>

<div class="app-content-header py-3 px-4 border-bottom bg-body-tertiary">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
      <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
        <i class="bi bi-arrow-left me-1"></i> Back
      </a>
      <div>
        <h2 class="fw-bold mb-0 fs-5" style="font-family:'Outfit',sans-serif;"><?= htmlspecialchars($original_name) ?></h2>
        <div class="x-small text-muted">
          Uploaded by <?= htmlspecialchars($file_rec['uploader_name'] ?? 'System') ?>
          <?php if (!empty($file_rec['uploaded_at'])): ?> · <?= time_ago($file_rec['uploaded_at']) ?><?php endif; ?>
          <?php if (!empty($file_rec['project_name'])): ?>
          · <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($file_rec['project_name']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button onclick="navigator.clipboard.writeText('<?= $raw_file_url ?>'); showToast('Direct link copied!', 'info');" class="btn btn-sm btn-outline-info rounded-pill px-3">
        <i class="bi bi-link-45deg me-1"></i> Share Link
      </button>
      <a href="<?= $raw_stream_src ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
        <i class="bi bi-download me-1"></i> Download File
      </a>
    </div>
  </div>
</div>

<?php
$is_editable = in_array($ext, ['txt','md','json','csv','html','css','js','php','py','sql','xml','log','env','yaml']);
?>

<?php if ($is_editable): ?>
<!-- Real-Time Collaboration Status Bar -->
<div class="bg-body-tertiary border-bottom px-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2" style="flex-shrink:0;">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-success bg-opacity-15 text-success d-inline-flex align-items-center gap-1" id="collab-status-badge">
      <span class="spinner-grow spinner-grow-sm me-1" style="width:8px;height:8px;"></span>
      <span>Live Sync Active</span>
    </span>
    <span class="x-small text-muted ms-2" id="sync-last-saved">Auto-saved</span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-outline-primary active" id="btn-mode-editor" onclick="toggleCollabView('editor')">
      <i class="bi bi-pencil-square me-1"></i>Real-Time Editor
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-mode-preview" onclick="toggleCollabView('preview')">
      <i class="bi bi-eye me-1"></i>Formatted Reader
    </button>
  </div>
</div>
<?php endif; ?>

<div class="app-content p-0 d-flex flex-column" style="height: calc(100vh - <?= $is_editable ? '170px' : '128px' ?>); background: var(--cs-bg);">

  <?php if ($is_editable): ?>
  <!-- Real-Time Collaborative Live Editor Container -->
  <div id="collab-editor-container" class="flex-grow-1 p-3 p-md-4 overflow-auto">
    <div class="card border-0 shadow-lg mx-auto" style="max-width:1000px;border-radius:20px;background:var(--cs-surface);">
      <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4">
        <div class="fw-bold d-flex align-items-center gap-2">
          <i class="bi bi-pencil-fill text-primary"></i>
          <span>Live Cross-Device Document Editor</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('live-doc-editor').value); showToast('Content copied to clipboard!', 'info');">
            <i class="bi bi-clipboard me-1"></i>Copy Text
          </button>
          <button class="btn btn-sm btn-primary px-3" onclick="saveDocumentContent(true)">
            <i class="bi bi-floppy me-1"></i> Save Document
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <textarea id="live-doc-editor" class="form-control border-0 p-4 font-monospace"
                  style="min-height:550px;font-size:.92rem;line-height:1.6;resize:vertical;background:transparent;color:var(--cs-text);"
                  placeholder="Start typing to collaborate in real-time cross-device..."><?= file_exists($file_path) ? htmlspecialchars(file_get_contents($file_path)) : '' ?></textarea>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Formatted Reader Container -->
  <div id="collab-preview-container" class="<?= $is_editable ? 'd-none' : 'd-flex' ?> flex-column flex-grow-1 h-100">
    <?php if (in_array($ext, ['docx', 'doc'])): ?>
    <!-- ── High Performance Word (.docx / .doc) Document Reader ── -->
    <div class="flex-grow-1 p-3 p-md-4 overflow-auto">
      <div class="card border-0 shadow-lg mx-auto" style="max-width:960px;border-radius:20px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4" style="border-radius:20px 20px 0 0;">
          <div class="fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-word-fill text-primary fs-5"></i>
            <span>Word Document Reader</span>
          </div>
          <div class="btn-group btn-group-sm" id="docx-engine-tabs">
            <button class="btn btn-outline-primary active" onclick="switchDocEngine('mammoth')">Native Reader</button>
            <button class="btn btn-outline-primary" onclick="switchDocEngine('office')">Office Online</button>
            <button class="btn btn-outline-primary" onclick="switchDocEngine('google')">Google Viewer</button>
          </div>
        </div>
        <div class="card-body p-0">
          <div id="docx-mammoth-view" class="p-4 p-md-5">
            <div id="docx-output" class="document-render-area">
              <div class="text-center py-5 text-muted">
                <div class="spinner-border text-primary spinner-border-sm mb-2"></div>
                <div>Rendering Word Document...</div>
              </div>
            </div>
          </div>
          <div id="docx-iframe-view" class="d-none" style="height:700px;">
            <iframe id="docx-frame" src="" class="w-100 h-100 border-0"></iframe>
          </div>
        </div>
      </div>
    </div>

    <script>
      function switchDocEngine(engine) {
        const mammothView = document.getElementById('docx-mammoth-view');
        const iframeView  = document.getElementById('docx-iframe-view');
        const frame       = document.getElementById('docx-frame');
        document.querySelectorAll('#docx-engine-tabs .btn').forEach(b => b.classList.remove('active'));

        if (engine === 'mammoth') {
          mammothView.classList.remove('d-none');
          iframeView.classList.add('d-none');
          event.target.classList.add('active');
        } else if (engine === 'office') {
          mammothView.classList.add('d-none');
          iframeView.classList.remove('d-none');
          frame.src = 'https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($raw_file_url) ?>';
          event.target.classList.add('active');
        } else if (engine === 'google') {
          mammothView.classList.add('d-none');
          iframeView.classList.remove('d-none');
          frame.src = 'https://docs.google.com/viewer?url=<?= urlencode($raw_file_url) ?>&embedded=true';
          event.target.classList.add('active');
        }
      }

      fetch('<?= $raw_stream_src ?>')
        .then(r => r.arrayBuffer())
        .then(arrayBuffer => {
          if (!arrayBuffer || arrayBuffer.byteLength === 0) {
            document.getElementById('docx-output').innerHTML = `
              <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-word fs-1 opacity-25 d-block mb-2"></i>
                <h6 class="fw-bold mb-1">Empty Document</h6>
                <p class="small text-muted mb-3">This Word file has 0 bytes of content.</p>
                <a href="<?= $raw_stream_src ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                  <i class="bi bi-download me-1"></i>Download File
                </a>
              </div>`;
            return;
          }
          return mammoth.convertToHtml({ arrayBuffer: arrayBuffer });
        })
        .then(result => {
          if (!result) return;
          if (result.value && result.value.trim().length > 0) {
            document.getElementById('docx-output').innerHTML = result.value;
          } else {
            // If empty text, automatically switch to Office iframe
            document.getElementById('docx-mammoth-view').classList.add('d-none');
            document.getElementById('docx-iframe-view').classList.remove('d-none');
            document.getElementById('docx-frame').src = 'https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($raw_file_url) ?>';
          }
        })
        .catch(err => {
          console.warn('Mammoth render fallback:', err);
          // Auto fallback to Office Online iframe on parse error
          document.getElementById('docx-mammoth-view').classList.add('d-none');
          document.getElementById('docx-iframe-view').classList.remove('d-none');
          document.getElementById('docx-frame').src = 'https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($raw_file_url) ?>';
        });
    </script>

  <?php elseif (in_array($ext, ['xlsx', 'xls', 'csv'])): ?>
    <!-- ── Excel & CSV Spreadsheet Viewer (SheetJS) ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-lg mx-auto" style="max-width:1100px;border-radius:20px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4" style="border-radius:20px 20px 0 0;">
          <div class="fw-bold"><i class="bi bi-file-earmark-excel-fill text-success me-2 fs-5"></i>Spreadsheet Reader</div>
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
      fetch('<?= $raw_stream_src ?>')
        .then(r => r.arrayBuffer())
        .then(arrayBuffer => {
          const workbook = XLSX.read(arrayBuffer, { type: 'array' });
          const output = document.getElementById('excel-output');
          const tabs = document.getElementById('excel-sheet-tabs');
          
          if (!workbook.SheetNames || !workbook.SheetNames.length) {
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
            <button class="btn btn-outline-success btn-sm ${i===0?'active':''}" onclick="renderSheet('${name}'); document.querySelectorAll('#excel-sheet-tabs .btn').forEach(b=>b.classList.remove('active')); this.classList.add('active');">${name}</button>
          `).join('');

          renderSheet(workbook.SheetNames[0]);
        })
        .catch(err => {
          document.getElementById('excel-output').innerHTML = `<div class="alert alert-warning text-center">Unable to parse spreadsheet.</div>`;
        });
    </script>

  <?php elseif ($ext === 'pdf'): ?>
    <!-- ── High Definition Native PDF Viewer ── -->
    <iframe src="<?= $raw_stream_src ?>" class="w-100 h-100 border-0"></iframe>

  <?php elseif ($ext === 'md'): ?>
    <!-- ── Formatted Markdown Reader (Marked.js) ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-lg mx-auto" style="max-width:900px;border-radius:20px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary py-3 px-4 fw-bold">
          <i class="bi bi-markdown-fill text-info me-2 fs-5"></i>Markdown Reader
        </div>
        <div class="card-body p-4 p-md-5" id="md-output">
          <div class="text-center py-5 text-muted">
            <div class="spinner-border text-info spinner-border-sm mb-2"></div>
            <div>Rendering Markdown...</div>
          </div>
        </div>
      </div>
    </div>
    <script>
      fetch('<?= $raw_stream_src ?>')
        .then(r => r.text())
        .then(text => {
          document.getElementById('md-output').innerHTML = marked.parse(text);
        })
        .catch(() => {
          document.getElementById('md-output').innerHTML = '<div class="alert alert-warning">Failed to load Markdown file.</div>';
        });
    </script>

  <?php elseif (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp','ico'])): ?>
    <!-- ── Image Viewer ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 overflow-auto bg-dark position-relative">
      <img src="<?= $raw_stream_src ?>" alt="<?= htmlspecialchars($original_name) ?>" class="img-fluid rounded-3 shadow-lg" style="max-height: 80vh; object-fit: contain;" onerror="this.classList.add('d-none'); document.getElementById('img-error-fallback').classList.remove('d-none');">
      <div id="img-error-fallback" class="card border-0 shadow-lg text-center p-5 d-none" style="max-width:440px;border-radius:20px;background:var(--cs-surface);">
        <div class="rounded-circle bg-warning bg-opacity-15 text-warning mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;">
          <i class="bi bi-image-fill fs-2"></i>
        </div>
        <h6 class="fw-bold mb-1">Image File Unavailable</h6>
        <p class="small text-muted mb-3">The image binary could not be loaded from disk storage.</p>
        <a href="<?= $raw_stream_src ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-sm btn-primary rounded-pill px-4 mx-auto">
          <i class="bi bi-download me-1"></i>Download File
        </a>
      </div>
    </div>

  <?php elseif (in_array($ext, ['doc','ppt','pptx'])): ?>
    <!-- ── Office & Google Docs Embedded Viewer ── -->
    <div class="flex-grow-1 d-flex flex-column h-100">
      <div class="bg-body-tertiary px-4 py-2 border-bottom d-flex align-items-center justify-content-between">
        <span class="small text-muted"><i class="bi bi-file-earmark-slides me-1"></i>Document Reader Service</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary active" onclick="document.getElementById('viewer-iframe').src='https://docs.google.com/viewer?url=<?= urlencode($raw_file_url) ?>&embedded=true'">Google Reader</button>
          <button class="btn btn-outline-primary" onclick="document.getElementById('viewer-iframe').src='https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($raw_file_url) ?>'">Office Reader</button>
        </div>
      </div>
      <iframe id="viewer-iframe" src="https://docs.google.com/viewer?url=<?= urlencode($raw_file_url) ?>&embedded=true" class="w-100 h-100 border-0"></iframe>
    </div>

  <?php elseif (in_array($ext, ['txt','json','html','css','js','php','sql','py','c','cpp','h','java','cs','sh','bat','env','yaml','yml','xml','log'])): ?>
    <!-- ── Text / Code Viewer ── -->
    <div class="flex-grow-1 p-4 overflow-auto">
      <div class="card border-0 shadow-sm mx-auto" style="max-width:1000px;border-radius:16px;background:var(--cs-surface);">
        <div class="card-header bg-body-tertiary fw-bold small py-3 px-4 d-flex align-items-center justify-content-between">
          <span><i class="bi bi-file-earmark-code me-2 text-primary"></i>Source Code / Text Content</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('code-content').textContent); showToast('Code copied to clipboard!', 'success');">
            <i class="bi bi-clipboard me-1"></i> Copy Code
          </button>
        </div>
        <div class="card-body p-0">
          <pre id="code-content" class="p-4 m-0 font-monospace" style="font-size:.85rem;white-space:pre-wrap;word-break:break-word;color:var(--cs-text);"><?= file_exists($file_path) ? htmlspecialchars(file_get_contents($file_path)) : 'File content unavailable.' ?></pre>
        </div>
      </div>
    </div>

  <?php elseif (in_array($ext, ['mp4','webm','ogv'])): ?>
    <!-- ── Video Player ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 bg-dark">
      <video controls class="w-100 rounded-4 shadow-lg" style="max-width:900px;max-height:75vh;">
        <source src="<?= $raw_stream_src ?>" type="video/<?= $ext ?>">
        Your browser does not support HTML5 video player.
      </video>
    </div>

  <?php elseif (in_array($ext, ['mp3','wav','ogg','m4a','aac','flac'])): ?>
    <!-- ── Audio Player ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4">
      <div class="card border-0 shadow-lg p-5 text-center" style="border-radius:24px;max-width:500px;width:100%;background:var(--cs-surface);">
        <div class="rounded-circle bg-primary bg-opacity-15 text-primary mx-auto mb-4 d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
          <i class="bi bi-music-note-beamed fs-2"></i>
        </div>
        <h5 class="fw-bold mb-3"><?= htmlspecialchars($original_name) ?></h5>
        <audio controls class="w-100 mt-2">
          <source src="<?= $raw_stream_src ?>" type="audio/<?= $ext ?>">
          Your browser does not support HTML5 audio player.
        </audio>
      </div>
    </div>

  <?php else: ?>
    <!-- ── Fallback Download ── -->
    <div class="flex-grow-1 d-flex align-items-center justify-content-center p-4 text-center">
      <div class="card border-0 shadow-lg p-5" style="border-radius:24px;max-width:500px;background:var(--cs-surface);">
        <div class="rounded-circle bg-primary bg-opacity-15 text-primary mx-auto mb-4 d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
          <i class="bi bi-file-earmark-arrow-down-fill fs-2"></i>
        </div>
        <h5 class="fw-bold mb-2"><?= htmlspecialchars($original_name) ?></h5>
        <p class="small text-muted mb-4">This file format (<strong><?= strtoupper($ext) ?></strong>) can be downloaded directly to view on your device.</p>
        <a href="<?= $raw_stream_src ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-primary py-2 px-4 rounded-pill fw-semibold">
          <i class="bi bi-download me-1"></i> Download File
        </a>
      </div>
    </div>
  <?php endif; ?>
  </div><!-- /#collab-preview-container -->

</div>

<script>
  let currentClientMtime = <?= file_exists($file_path) ? filemtime($file_path) : time() ?>;
  let isUserTyping = false;
  let autoSaveTimer = null;
  const fileName = <?= json_encode($file_name) ?>;

  function toggleCollabView(mode) {
    const editorWrap  = document.getElementById('collab-editor-container');
    const previewWrap = document.getElementById('collab-preview-container');
    const btnEdit     = document.getElementById('btn-mode-editor');
    const btnPrev     = document.getElementById('btn-mode-preview');

    if (mode === 'editor') {
      editorWrap.classList.remove('d-none');
      previewWrap.classList.add('d-none');
      previewWrap.classList.remove('d-flex');
      btnEdit.classList.add('active');
      btnPrev.classList.remove('active');
    } else {
      editorWrap.classList.add('d-none');
      previewWrap.classList.remove('d-none');
      previewWrap.classList.add('d-flex');
      btnEdit.classList.remove('active');
      btnPrev.classList.add('active');
    }
  }

  const liveEditor = document.getElementById('live-doc-editor');
  if (liveEditor) {
    liveEditor.addEventListener('input', function() {
      isUserTyping = true;
      updateSyncStatus('⚡ Typing changes...', 'warning');
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(() => {
        saveDocumentContent(false);
      }, 600);
    });

    liveEditor.addEventListener('blur', function() {
      isUserTyping = false;
    });
  }

  function updateSyncStatus(text, type='success') {
    const badge = document.getElementById('collab-status-badge');
    if (badge) {
      badge.className = `badge bg-${type} bg-opacity-15 text-${type} d-inline-flex align-items-center gap-1`;
      badge.innerHTML = `<span class="spinner-grow spinner-grow-sm text-${type} me-1" style="width:8px;height:8px;"></span><span>${text}</span>`;
    }
  }

  async function saveDocumentContent(isManual = false) {
    if (!liveEditor) return;
    const content = liveEditor.value;
    updateSyncStatus('Syncing to server...', 'info');

    const fd = new FormData();
    fd.append('file', fileName);
    fd.append('content', content);

    try {
      const res = await fetch('api/documents.php?action=save', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        currentClientMtime = data.last_modified;
        isUserTyping = false;
        updateSyncStatus('Live Sync Active', 'success');
        document.getElementById('sync-last-saved').textContent = 'Saved just now';
        if (isManual) showToast('Document saved!', 'success');
      } else {
        updateSyncStatus('Save failed', 'danger');
      }
    } catch (err) {
      updateSyncStatus('Offline / Retry', 'danger');
    }
  }

  async function pollDocumentSync() {
    if (!liveEditor || isUserTyping) return;
    try {
      const res = await fetch(`api/documents.php?action=poll&file=${encodeURIComponent(fileName)}&client_mtime=${currentClientMtime}`);
      const data = await res.json();

      if (data.has_changes && data.content !== undefined) {
        currentClientMtime = data.last_modified;
        if (liveEditor && liveEditor.value !== data.content) {
          const start = liveEditor.selectionStart;
          const end   = liveEditor.selectionEnd;
          liveEditor.value = data.content;
          liveEditor.setSelectionRange(start, end);
          updateSyncStatus('Synced remote edit!', 'info');
          setTimeout(() => updateSyncStatus('Live Sync Active', 'success'), 1200);
        }
      }
    } catch (err) {}
  }

  setInterval(pollDocumentSync, 1500);
</script>

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
