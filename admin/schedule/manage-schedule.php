<?php
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUserId = $_SESSION['user_id'] ?? 1;
$isAdmin = ($currentUserId == 1);
$users = [];
if ($isAdmin) {
    $users = $pdo->query('SELECT id, name FROM users ORDER BY name ASC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <title>Schedule Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1>Schedule Management</h1>
        <div class="subtext">Admin & Pandit availability management</div>
    </div>
    <!-- Toolbar: User Selection -->
    <div class="form-box" style="margin-bottom:24px;max-width:520px;">
        <div class="form-group" style="display:flex;align-items:center;gap:18px;">
            <label for="userSelect" style="font-weight:600;min-width:110px;">Select User</label>
            <select id="userSelect" name="user_id" class="form-control" style="flex:1;min-width:180px;max-width:320px;" <?= $isAdmin ? '' : 'disabled' ?>>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= ($user['id'] == $currentUserId ? 'selected' : '') ?>><?= htmlspecialchars($user['name']) ?><?= !empty($user['role']) ? ' (' . htmlspecialchars($user['role']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <!-- Calendar Layout Structure -->
    <div id="calendarWrapper" style="background:#fffbe7;border-radius:12px;padding:24px;">
        <div id="calendarHeader" style="font-size:1.15em;font-weight:600;margin-bottom:18px;">Calendar View (Day / Week / Month)</div>
        <div id="calendarBody" style="min-height:220px;display:flex;align-items:center;justify-content:center;font-size:1.15em;color:#888;">
            <div id="fcCalendar"></div>
        </div>
    </div>
</div>
<script>
$(function() {
        var isAdmin = <?= json_encode($isAdmin) ?>;
        var users = <?= json_encode($users) ?>;
        var currentUserId = <?= json_encode($currentUserId) ?>;
        var calendar;

        function renderCalendar() {
                if (calendar) {
                        calendar.destroy();
                }
                var $calDiv = $('#fcCalendar');
                $calDiv.empty();
                calendar = new FullCalendar.Calendar($calDiv[0], {
                        initialView: 'timeGridWeek',
                        headerToolbar: {
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        },
                        slotMinTime: '06:00:00',
                        slotMaxTime: '22:00:00',
                        allDaySlot: false,
                        selectable: isAdmin,
                        editable: false,
                        events: function(fetchInfo, successCallback, failureCallback) {
                                var assigned_user_id = $('#userSelect').val();
                                $.ajax({
                                        url: 'ajax_schedule.php',
                                        method: 'POST',
                                        dataType: 'json',
                                        data: {
                                                action: 'fetch',
                                                assigned_user_id: assigned_user_id,
                                                start: fetchInfo.startStr,
                                                end: fetchInfo.endStr
                                        },
                                        success: function(events) {
                                                successCallback(events);
                                        },
                                        error: function() {
                                                failureCallback([]);
                                        }
                                });
                        },
                        eventClick: function(info) {
                                var desc = info.event.extendedProps.description;
                                alert('Event: ' + info.event.title + (desc ? '\nDescription: ' + desc : ''));
                        },
                        dateClick: function(info) {
                                if (!isAdmin) return;
                                var assigned_user_id = $('#userSelect').val();
                                var startDate = info.dateStr.split('T')[0];
                                var startTime = info.dateStr.split('T')[1] || '09:00:00';
                                var modalHtml = `
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Create Schedule Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="scheduleForm">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="schedule_date" value="${startDate}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" class="form-control" name="start_time" value="${startTime}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">End Time</label>
                    <input type="time" class="form-control" name="end_time" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="tentative">Tentative</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Assigned User</label>
                    <select class="form-control" name="assigned_user_id">
                        ${users.map(u => `<option value="${u.id}" ${u.id == assigned_user_id ? 'selected' : ''}>${u.name}${u.role ? ' ('+u.role+')' : ''}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
            </form>
        </div>
    </div>
</div>`;
                                $('body').append(modalHtml);
                                var modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                                modal.show();
                                $('#scheduleModal').on('hidden.bs.modal', function() {
                                        $('#scheduleModal').remove();
                                });
                                $('#scheduleForm').on('submit', function(e) {
                                        e.preventDefault();
                                        var formData = $(this).serializeArray();
                                        var data = { action: 'create' };
                                        formData.forEach(function(f) { data[f.name] = f.value; });
                                        $.post('ajax_schedule.php', data, function(resp) {
                                                if (resp.success) {
                                                        modal.hide();
                                                        calendar.refetchEvents();
                                                } else {
                                                        alert(resp.msg);
                                                }
                                        }, 'json');
                                });
                        }
                });
                calendar.render();
        }
        renderCalendar();
        $('#userSelect').on('change', function() {
                renderCalendar();
        });
});
</script>
</body>
</html>
