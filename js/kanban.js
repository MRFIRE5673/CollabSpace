// ============================================================
// kanban.js - Drag-and-Drop Kanban Board
// CollabSpace Collaboration Workspace
// ============================================================

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initKanban();
  });

  function initKanban() {
    const columns = document.querySelectorAll('.kanban-cards');
    if (!columns.length || typeof dragula === 'undefined') return;

    const drake = dragula(Array.from(columns), {
      revertOnSpill: true,
      moves: function (el) { return el.classList.contains('task-card'); }
    });

    drake.on('drop', function (el, target, source) {
      const taskId = el.dataset.taskId;
      const newStatus = target.closest('.kanban-col').dataset.status;
      const oldStatus = source.closest('.kanban-col').dataset.status;

      if (!taskId || !newStatus || newStatus === oldStatus) return;

      // Visual feedback - add loading indicator
      el.style.opacity = '.6';
      el.style.pointerEvents = 'none';

      updateTaskStatus(taskId, newStatus, function (success, res) {
        el.style.opacity = '';
        el.style.pointerEvents = '';
        if (success) {
          // Update badge on card
          const badge = el.querySelector('.task-status-badge');
          if (badge) {
            const labels = { todo: 'To Do', in_progress: 'In Progress', in_review: 'In Review', done: 'Done' };
            const classes = { todo: 'secondary', in_progress: 'primary', in_review: 'warning', done: 'success' };
            badge.textContent = labels[newStatus] || newStatus;
            badge.className = `badge bg-${classes[newStatus] || 'secondary'} task-status-badge`;
          }
          // Update column counts
          updateColumnCounts();
          // Mark done tasks
          if (newStatus === 'done') {
            el.classList.add('opacity-75');
            const title = el.querySelector('.task-title');
            if (title) title.style.textDecoration = 'line-through';
          } else {
            el.classList.remove('opacity-75');
            const title = el.querySelector('.task-title');
            if (title) title.style.textDecoration = '';
          }
        } else {
          // Revert drag
          source.appendChild(el);
        }
      });
    });

    // Initialize done state
    document.querySelectorAll('.kanban-col[data-status="done"] .task-card').forEach(el => {
      const title = el.querySelector('.task-title');
      if (title) title.style.textDecoration = 'line-through';
      el.classList.add('opacity-75');
    });

    updateColumnCounts();
  }

  function updateColumnCounts() {
    document.querySelectorAll('.kanban-col').forEach(col => {
      const count = col.querySelectorAll('.task-card').length;
      const countEl = col.querySelector('.kanban-count');
      if (countEl) countEl.textContent = count;
    });
  }

  // ─── Task Modal ───────────────────────────────────────────
  window.openCreateTaskModal = function (status) {
    const statusSelect = document.getElementById('modal-task-status');
    if (statusSelect) statusSelect.value = status || 'todo';
    const modal = document.getElementById('createTaskModal');
    if (modal) {
      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    }
  };

  // ─── Delete Task ─────────────────────────────────────────
  window.deleteTask = function (taskId) {
    if (!confirm('Are you sure you want to delete this task?')) return;
    const form = new FormData();
    form.append('task_id', taskId);
    fetch('api/tasks.php?action=delete', { method: 'POST', body: form })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const card = document.querySelector(`[data-task-id="${taskId}"]`);
          if (card) { card.style.transition = 'opacity .3s'; card.style.opacity = '0'; setTimeout(() => { card.remove(); updateColumnCounts(); }, 300); }
          showToast('Task deleted.', 'success');
        } else {
          showToast('Failed to delete task.', 'danger');
        }
      });
  };

  // ─── Quick Add Task (inline button) ─────────────────────
  document.addEventListener('click', function (e) {
    if (e.target.closest('.kanban-add-btn')) {
      const col = e.target.closest('.kanban-col');
      const status = col ? col.dataset.status : 'todo';
      openCreateTaskModal(status);
    }
  });
})();
