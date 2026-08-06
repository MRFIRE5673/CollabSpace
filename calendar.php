<?php
// ─── Calendar ────────────────────────────────────────────────
$page_title = 'Calendar';
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$uid  = $user['id'];
$db   = getDB();

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
$month = max(1, min(12, $month));

$prev_m = $month - 1; $prev_y = $year;
if ($prev_m < 1)  { $prev_m = 12; $prev_y--; }
$next_m = $month + 1; $next_y = $year;
if ($next_m > 12) { $next_m = 1;  $next_y++; }

$first_day = mktime(0,0,0,$month,1,$year);
$days_in_month = (int)date('t', $first_day);
$start_dow = (int)date('w', $first_day); // 0=Sun

// Fetch tasks with due dates in this month
$start_date = date('Y-m-01', $first_day);
$end_date   = date('Y-m-t',  $first_day);

$task_events = $db->prepare("
    SELECT t.id, t.title, t.priority, t.status, t.due_date,
           p.name AS project_name, p.id AS project_id
    FROM tasks t JOIN projects p ON p.id=t.project_id
    WHERE t.due_date BETWEEN ? AND ?
    ORDER BY t.due_date, t.priority DESC
");
$task_events->execute([$start_date, $end_date]);
$task_events = $task_events->fetchAll();

// Group by day
$events_by_day = [];
foreach ($task_events as $e) {
    $d = (int)date('j', strtotime($e['due_date']));
    $events_by_day[$d][] = $e;
}

// Priority colors
$prio_colors = ['critical'=>'#dc2626','high'=>'#d97706','medium'=>'#4f46e5','low'=>'#059669'];

include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<?php include __DIR__ . '/includes/navbar.php'; ?>


  <div class="app-content-header py-3 px-4 border-bottom">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h2 class="fw-bold mb-0 fs-5"><i class="bi bi-calendar3 me-2 text-primary"></i>Calendar</h2>
        <p class="text-muted small mb-0">Task deadlines and project milestones</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="?month=<?= $prev_m ?>&year=<?= $prev_y ?>" class="btn btn-sm btn-outline-primary" id="cal-prev"><i class="bi bi-chevron-left"></i></a>
        <span class="fw-semibold"><?= date('F Y', $first_day) ?></span>
        <a href="?month=<?= $next_m ?>&year=<?= $next_y ?>" class="btn btn-sm btn-outline-primary" id="cal-next"><i class="bi bi-chevron-right"></i></a>
        <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-sm btn-outline-secondary" id="cal-today">Today</a>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="row g-4">
      <!-- Calendar Grid -->
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body p-3">
            <!-- Day headers -->
            <div class="cal-grid mb-2">
              <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day): ?>
              <div class="text-center x-small text-muted fw-semibold py-2"><?= $day ?></div>
              <?php endforeach; ?>
            </div>

            <!-- Calendar cells -->
            <div class="cal-grid" id="calendar-grid">
              <?php
                // Blank cells before first day
                for ($i = 0; $i < $start_dow; $i++): ?>
              <div class="cal-cell other-month"></div>
              <?php endfor; ?>

              <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
              <?php
                $is_today = ($day == date('j') && $month == date('n') && $year == date('Y'));
                $day_events = $events_by_day[$day] ?? [];
              ?>
              <div class="cal-cell <?= $is_today?'today':'' ?>" id="cal-day-<?= $day ?>">
                <div class="cal-day-num <?= $is_today?'text-primary fw-bold':'' ?>"><?= $day ?></div>
                <?php foreach (array_slice($day_events,0,3) as $e): ?>
                <?php $ec = $prio_colors[$e['priority']] ?? '#4f46e5'; ?>
                <div class="cal-event" style="background:<?= $ec ?>;" title="<?= htmlspecialchars($e['title']) ?> · <?= htmlspecialchars($e['project_name']) ?>" onclick="showEventDetail(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['title'])) ?>', '<?= htmlspecialchars(addslashes($e['project_name'])) ?>', '<?= $e['status'] ?>', '<?= $e['priority'] ?>', '<?= $e['due_date'] ?>', <?= $e['project_id'] ?>)" id="cal-event-<?= $e['id'] ?>">
                  <?= htmlspecialchars($e['title']) ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($day_events) > 3): ?>
                <div class="x-small text-muted mt-1">+<?= count($day_events)-3 ?> more</div>
                <?php endif; ?>
              </div>
              <?php endfor; ?>

              <?php
                // Trailing blank cells
                $total_cells = $start_dow + $days_in_month;
                $trailing = (7 - ($total_cells % 7)) % 7;
                for ($i = 0; $i < $trailing; $i++): ?>
              <div class="cal-cell other-month"></div>
              <?php endfor; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar: Upcoming deadlines -->
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header bg-transparent py-3">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-alarm me-2 text-primary"></i>This Month's Deadlines</h3>
          </div>
          <div class="card-body p-0">
            <?php if (empty($task_events)): ?>
            <div class="text-center py-4 text-muted small">No deadlines this month 🎉</div>
            <?php else: ?>
            <?php foreach ($task_events as $e): ?>
            <?php $ec = $prio_colors[$e['priority']] ?? '#4f46e5'; ?>
            <div class="d-flex align-items-start gap-3 px-3 py-2 border-bottom">
              <div class="text-center flex-shrink-0" style="width:36px;">
                <div class="fw-bold" style="color:<?= $ec ?>;font-size:1.1rem;"><?= date('j', strtotime($e['due_date'])) ?></div>
                <div class="x-small text-muted"><?= date('M', strtotime($e['due_date'])) ?></div>
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold small text-truncate"><?= htmlspecialchars($e['title']) ?></div>
                <div class="x-small text-muted"><?= htmlspecialchars($e['project_name']) ?></div>
                <div class="d-flex gap-1 mt-1">
                  <?= priority_badge($e['priority']) ?>
                  <?= status_badge($e['status']) ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Legend -->
        <div class="card">
          <div class="card-header bg-transparent py-3">
            <h3 class="h6 fw-bold mb-0"><i class="bi bi-info-circle me-2 text-muted"></i>Priority Legend</h3>
          </div>
          <div class="card-body py-2">
            <?php foreach ($prio_colors as $p => $c): ?>
            <div class="d-flex align-items-center gap-2 py-1">
              <div style="width:16px;height:16px;border-radius:4px;background:<?= $c ?>;"></div>
              <span class="small"><?= ucfirst($p) ?> Priority</span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Event Detail Modal -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title h6 fw-bold" id="event-modal-title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-1"><i class="bi bi-kanban me-2"></i><span id="event-modal-project"></span></p>
        <p class="small text-muted mb-1"><i class="bi bi-calendar3 me-2"></i><span id="event-modal-date"></span></p>
        <div class="d-flex gap-2 mt-2">
          <span id="event-modal-priority"></span>
          <span id="event-modal-status"></span>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <a href="#" class="btn btn-sm btn-primary" id="event-modal-link">View Project</a>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'JS'
<script>
function showEventDetail(id, title, project, status, priority, date, projectId) {
  document.getElementById('event-modal-title').textContent = title;
  document.getElementById('event-modal-project').textContent = project;
  document.getElementById('event-modal-date').textContent = new Date(date+'T00:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
  document.getElementById('event-modal-priority').innerHTML = '<span class="badge bg-' + {critical:'danger',high:'warning',medium:'info',low:'success'}[priority] + '">' + priority.charAt(0).toUpperCase() + priority.slice(1) + '</span>';
  const statusMap = {todo:'To Do',in_progress:'In Progress',in_review:'In Review',done:'Done'};
  const statusColorMap = {todo:'secondary',in_progress:'primary',in_review:'warning',done:'success'};
  document.getElementById('event-modal-status').innerHTML = '<span class="badge bg-' + (statusColorMap[status]||'secondary') + '">' + (statusMap[status]||status) + '</span>';
  document.getElementById('event-modal-link').href = 'project_details.php?id=' + projectId;
  new bootstrap.Modal(document.getElementById('eventDetailModal')).show();
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
