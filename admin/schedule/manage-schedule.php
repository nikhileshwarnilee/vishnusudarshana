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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <link rel="stylesheet" href="schedule-style.css">
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1>Schedule Management</h1>
        <div class="subtext">Admin & Pandit availability management</div>
    </div>
    <!-- Main Split Layout: Sidebar + Calendar -->
    <div class="schedule-main-wrapper">
        <!-- Left Sidebar: Schedule Form -->
        <div class="schedule-sidebar">
            <div class="sidebar-header">
                <h3 style="color:#800000;">Schedule Entry</h3>
            </div>
            <div class="sidebar-content">
                <div class="sidebar-info" id="sidebarInfo"></div>
                <form id="scheduleFormSidebar">
                    <div class="form-section">
                        <div class="form-group">
                            <label for="sidebarTitle">Title <span style="color:#ef4444">*</span></label>
                            <input type="text" id="sidebarTitle" name="title" required placeholder="Enter event title">
                        </div>
                        <div class="form-group">
                            <label for="sidebarDescription">Description</label>
                            <textarea id="sidebarDescription" name="description" placeholder="Add details (optional)"></textarea>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-group">
                            <label for="sidebarStartDate">Start Date <span style="color:#ef4444">*</span></label>
                            <input type="date" id="sidebarStartDate" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="sidebarEndDate">End Date</label>
                            <input type="date" id="sidebarEndDate" name="end_date">
                        </div>
                        <div class="form-group">
                            <label for="sidebarStartTime">Start Time <span style="color:#ef4444">*</span></label>
                            <input type="time" id="sidebarStartTime" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label for="sidebarEndTime">End Time <span style="color:#ef4444">*</span></label>
                            <input type="time" id="sidebarEndTime" name="end_time" required>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-group">
                            <label for="sidebarStatus">Status</label>
                            <select id="sidebarStatus" name="status">
                                <option value="tentative">Tentative</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sidebarUser">Assigned User <span style="color:#ef4444">*</span></label>
                            <select id="sidebarUser" name="assigned_user_id" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= ($user['id'] == $currentUserId ? 'selected' : '') ?>><?= htmlspecialchars($user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="sidebarEventId" name="event_id" value="">
                </form>
            </div>
            <div class="sidebar-actions">
                <button class="btn btn-maroon btn-save" id="btnSave">Save</button>
                <button class="btn btn-maroon-light btn-reset" id="btnReset">Clear</button>
                <button class="btn btn-maroon-dark btn-delete" id="btnDelete" style="display:none;">Delete</button>
            </div>
        </div>
        
        <!-- Right Calendar Area -->
        <div class="schedule-calendar-area">
            <!-- Compact User Controls in Calendar Header -->
            <div style="padding: 12px 16px; background: #f9fafb; border-bottom: 1px solid #e8ecf1; display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="userSelect" style="font-weight: 600; font-size: 0.9em; color: #374151; white-space: nowrap;">User:</label>
                    <select id="userSelect" name="user_id" class="form-control" style="max-width: 180px; min-width: 140px; padding: 6px 10px; border-radius: 5px; border: 1px solid #d1d5db; font-size: 0.9em;" <?= $isAdmin ? '' : 'disabled' ?>>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= ($user['id'] == $currentUserId ? 'selected' : '') ?>><?= htmlspecialchars($user['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="timeSlotSelect" style="font-weight: 600; font-size: 0.9em; color: #374151; white-space: nowrap;">Time Slot:</label>
                    <select id="timeSlotSelect" name="time_slot" class="form-control" style="max-width: 160px; min-width: 140px; padding: 6px 10px; border-radius: 5px; border: 1px solid #d1d5db; font-size: 0.9em;" <?= $isAdmin ? '' : 'disabled' ?>>
                        <option value="10" selected>10 Minutes</option>
                        <option value="30">30 Minutes</option>
                        <option value="60">1 Hour</option>
                    </select>
                </div>
                <?php if ($isAdmin): ?>
                <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
                    <input type="checkbox" id="allUsersView" style="width: 16px; height: 16px; cursor: pointer; accent-color: #3b82f6;"> 
                    <label for="allUsersView" style="cursor: pointer; margin: 0; font-weight: 500; font-size: 0.9em; color: #374151;">All Users</label>
                </div>
                <?php endif; ?>
            </div>
            <div id="calendarWrapper">
                <div id="calendarBody">
                    <div id="fcCalendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
        var isAdmin = <?= json_encode($isAdmin) ?>;
        var users = <?= json_encode($users) ?>;
        var currentUserId = <?= json_encode($currentUserId) ?>;
        var calendar;

        function getSlotConfig() {
            var val = ($('#timeSlotSelect').val() || '10');
            if (val === '10') {
                return { slotDuration: '00:10:00', slotLabelInterval: '00:10:00' };
            }
            if (val === '30') {
                return { slotDuration: '00:30:00', slotLabelInterval: '00:30:00' };
            }
            return { slotDuration: '01:00:00', slotLabelInterval: '01:00:00' };
        }

        function renderCalendar() {
            var prevView = calendar ? calendar.view.type : null;
            var prevDate = calendar ? calendar.getDate() : null;
            if (calendar) {
                calendar.destroy();
            }
            var $calDiv = $('#fcCalendar');
            $calDiv.empty();
            var allUsersView = isAdmin && $('#allUsersView').is(':checked');
            var slot = getSlotConfig();
            calendar = new FullCalendar.Calendar($calDiv[0], {
                                slotLabelContent: function(arg) {
                                    // Only for timeGrid views (day/week)
                                    if (arg.view.type === 'timeGridDay' || arg.view.type === 'timeGridWeek') {
                                        var start = arg.date;
                                        var slotVal = ($('#timeSlotSelect').val() || '10');
                                        var slotMinutes = parseInt(slotVal, 10);
                                        var end = new Date(start.getTime() + slotMinutes * 60000);
                                        function formatTime(dt) {
                                            var h = dt.getHours();
                                            var m = dt.getMinutes();
                                            var ampm = h >= 12 ? 'pm' : 'am';
                                            h = h % 12; if (h === 0) h = 12;
                                            return h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
                                        }
                                        return formatTime(start) + ' to ' + formatTime(end);
                                    }
                                    // Default for other views
                                    return arg.text;
                                },
                initialView: prevView || 'timeGridWeek',
                initialDate: prevDate || undefined,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                slotMinTime: '00:00:00',
                slotMaxTime: '24:00:00',
                slotDuration: slot.slotDuration,
                slotLabelInterval: slot.slotLabelInterval,
                allDaySlot: false,
                views: {
                    dayGridMonth: {
                        allDaySlot: true
                    }
                },
                selectable: isAdmin && !allUsersView,
                editable: false,
                scrollTime: '06:00:00',
                eventDidMount: function(info) {
                    // Add custom title attribute for tooltip
                    var title = info.event.title;
                    var desc = info.event.extendedProps.description;
                    var status = info.event.extendedProps.status;
                    var tooltip = title;
                    if (desc) tooltip += '\n' + desc;
                    if (status) tooltip += '\n(' + status.charAt(0).toUpperCase() + status.slice(1) + ')';
                    info.el.setAttribute('title', tooltip);
                    info.el.setAttribute('data-bs-toggle', 'tooltip');
                    info.el.setAttribute('data-bs-placement', 'top');
                    // Restore and enhance event color for month view
                    var color = info.event.backgroundColor || info.event._def.ui.backgroundColor || info.event.extendedProps.color || info.event._def.extendedProps?.color;
                    if (!color && info.event._def && info.event._def.extendedProps && info.event._def.extendedProps.color) {
                        color = info.event._def.extendedProps.color;
                    }
                    if (color) {
                        info.el.style.backgroundColor = color;
                        info.el.style.borderColor = color;
                        // Set text color for contrast
                        var dark = false;
                        if (color.startsWith('#')) {
                            var hex = color.replace('#', '');
                            if (hex.length === 3) hex = hex.split('').map(function(x){return x+x;}).join('');
                            var r = parseInt(hex.substr(0,2),16), g = parseInt(hex.substr(2,2),16), b = parseInt(hex.substr(4,2),16);
                            var luminance = (0.299*r + 0.587*g + 0.114*b)/255;
                            dark = luminance < 0.5;
                        }
                        info.el.style.color = dark ? '#fff' : '#222';
                    }
                    info.el.style.wordWrap = 'break-word';
                    info.el.style.overflow = 'hidden';
                },
                events: function(fetchInfo, successCallback, failureCallback) {
                    if (allUsersView) {
                        $.ajax({
                            url: 'ajax_schedule.php',
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'fetch',
                                all_users: 1,
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
                    } else {
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
                    }
                },
                eventClick: function(info) {
                    if (isAdmin && !allUsersView) {
                        // Load event into sidebar for editing
                        var eventId = info.event.id;
                        var title = info.event.title;
                        var desc = info.event.extendedProps.description;
                        var status = info.event.extendedProps.status;
                        
                        // Format dates using local timezone (not UTC)
                        function formatLocalDate(date) {
                            var d = new Date(date);
                            var month = String(d.getMonth() + 1).padStart(2, '0');
                            var day = String(d.getDate()).padStart(2, '0');
                            return d.getFullYear() + '-' + month + '-' + day;
                        }
                        
                        var startDate = formatLocalDate(info.event.start);
                        var endDate = formatLocalDate(info.event.end);
                        var startTime = info.event.start.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit', hour12: false}).replace(':', ':');
                        var endTime = info.event.end.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit', hour12: false}).replace(':', ':');
                        var userId = info.event.extendedProps.assigned_user_id;
                        
                        // Parse title to remove status suffix
                        var titleText = title.split(' (')[0];
                        
                        $('#sidebarEventId').val(eventId);
                        $('#sidebarTitle').val(titleText);
                        $('#sidebarDescription').val(desc);
                        $('#sidebarStartDate').val(startDate);
                        $('#sidebarEndDate').val(endDate);
                        $('#sidebarStartTime').val(startTime);
                        $('#sidebarEndTime').val(endTime);
                        $('#sidebarStatus').val(status);
                        $('#sidebarUser').val(userId);
                        
                        $('#btnDelete').show();
                        $('#sidebarInfo').addClass('show').text('Editing existing schedule');
                    }
                },
                select: function(info) {
                    if (!isAdmin || allUsersView) return;
                    var assigned_user_id = $('#userSelect').val();
                    var viewType = calendar.view.type;
                    var startDate = info.startStr.split('T')[0];
                    var endDateRaw = info.endStr.split('T')[0];
                    var endTimeRaw = info.endStr.split('T')[1] || '';
                    var endDateObj = new Date(endDateRaw);
                    var startDateObj = new Date(startDate);
                    var endDate;
                    if (startDate === endDateRaw || startDateObj.getTime() === endDateObj.getTime()) {
                        endDate = startDate;
                    } else if (endTimeRaw === '' || endTimeRaw === '00:00:00') {
                        // FullCalendar's endStr is exclusive for all-day selections, so subtract a day
                        endDateObj.setDate(endDateObj.getDate() - 1);
                        endDate = endDateObj.toISOString().split('T')[0];
                    } else {
                        // If time is not midnight, use the end date as is
                        endDate = endDateRaw;
                    }
                    var startTime = info.startStr.split('T')[1] || '09:00:00';
                    var endTime = info.endStr.split('T')[1] || '';

                    // Month view: only dates, no times
                    if (viewType === 'dayGridMonth') {
                        $('#sidebarEventId').val('');
                        $('#sidebarTitle').val('');
                        $('#sidebarDescription').val('');
                        $('#sidebarStartDate').val(startDate);
                        $('#sidebarEndDate').val(endDate);
                        $('#sidebarStartTime').val('');
                        $('#sidebarEndTime').val('');
                        $('#sidebarStatus').val('tentative');
                        $('#sidebarUser').val(assigned_user_id);
                        $('#btnDelete').hide();
                        $('#sidebarInfo').removeClass('show');
                        $('#sidebarTitle').focus();
                        return;
                    }

                    // Day/Week view: times and dates
                    if (startTime.length > 5) startTime = startTime.substring(0,5);
                    if (endTime.length > 5) endTime = endTime.substring(0,5);

                    // If endTime is not set (single slot), use slot duration
                    if (!endTime) {
                        var slotVal = ($('#timeSlotSelect').val() || '10');
                        var slotMinutes = parseInt(slotVal, 10);
                        var startParts = startTime.split(':');
                        var endDateObj = new Date(startDate + 'T' + startTime + ':00');
                        endDateObj.setMinutes(endDateObj.getMinutes() + slotMinutes);
                        var endHour = endDateObj.getHours().toString().padStart(2, '0');
                        var endMin = endDateObj.getMinutes().toString().padStart(2, '0');
                        endTime = endHour + ':' + endMin;
                    }

                    // Clear form and populate with selected range
                    $('#sidebarEventId').val('');
                    $('#sidebarTitle').val('');
                    $('#sidebarDescription').val('');
                    $('#sidebarStartDate').val(startDate);
                    $('#sidebarEndDate').val(endDate);
                    $('#sidebarStartTime').val(startTime);
                    $('#sidebarEndTime').val(endTime);
                    $('#sidebarStatus').val('tentative');
                    $('#sidebarUser').val(assigned_user_id);

                    $('#btnDelete').hide();
                    $('#sidebarInfo').removeClass('show');
                    $('#sidebarTitle').focus();
                },
                dateClick: function(info) {
                    if (!isAdmin || allUsersView) return;
                    var assigned_user_id = $('#userSelect').val();
                    var startDate = info.dateStr.split('T')[0];
                    var startTime = info.dateStr.split('T')[1] || '09:00:00';

                    // Ensure startTime is in HH:MM format
                    if (startTime.length > 5) startTime = startTime.substring(0,5);

                    // Get slot duration from selector
                    var slotVal = ($('#timeSlotSelect').val() || '10');
                    var slotMinutes = parseInt(slotVal, 10);
                    var startParts = startTime.split(':');
                    var startHour = parseInt(startParts[0], 10);
                    var startMin = parseInt(startParts[1], 10);
                    var endDateObj = new Date(startDate + 'T' + startTime + ':00');
                    endDateObj.setMinutes(endDateObj.getMinutes() + slotMinutes);
                    var endHour = endDateObj.getHours().toString().padStart(2, '0');
                    var endMin = endDateObj.getMinutes().toString().padStart(2, '0');
                    var endTime = endHour + ':' + endMin;

                    // Clear form and populate with clicked date and slot times
                    $('#sidebarEventId').val('');
                    $('#sidebarTitle').val('');
                    $('#sidebarDescription').val('');
                    // Set both start and end date fields
                    $('#sidebarStartDate').val(startDate);
                    $('#sidebarEndDate').val(startDate);
                    $('#sidebarStartTime').val(startTime);
                    $('#sidebarEndTime').val(endTime);
                    $('#sidebarStatus').val('tentative');
                    $('#sidebarUser').val(assigned_user_id);

                    $('#btnDelete').hide();
                    $('#sidebarInfo').removeClass('show');
                    $('#sidebarTitle').focus();
                },
            });
            calendar.render();
        }
        
        // Initialize sidebar form handlers
        function resetSidebarForm() {
            $('#scheduleFormSidebar')[0].reset();
            $('#sidebarEventId').val('');
            $('#btnDelete').hide();
            $('#sidebarInfo').removeClass('show');
        }
        
        $('#btnReset').on('click', function(e) {
            e.preventDefault();
            resetSidebarForm();
        });
        
        $('#btnSave').on('click', function(e) {
            e.preventDefault();
            var $form = $('#scheduleFormSidebar');
            var eventId = $('#sidebarEventId').val();
            var formData = $form.serializeArray();
            var data = { action: eventId ? 'update' : 'create' };

            // Include all fields; event_id is required for updates and harmless for creates
            formData.forEach(function(f) {
                data[f.name] = f.value;
            });

            $.post('ajax_schedule.php', data, function(resp) {
                if (resp.success) {
                    resetSidebarForm();
                    calendar.refetchEvents();
                    $('#sidebarInfo').addClass('show').text(resp.msg || 'Schedule saved successfully!');
                    setTimeout(function() {
                        $('#sidebarInfo').removeClass('show');
                    }, 3000);
                } else if (resp.status === 'conflict') {
                    var msg = resp.message || 'This pandit is already booked for this time range.';
                    $('#sidebarInfo').addClass('show').css('background', '#fee').css('color', '#c33').text(msg);
                } else {
                    $('#sidebarInfo').addClass('show').css('background', '#fee').css('color', '#c33').text(resp.msg || 'Error saving schedule');
                }
            }, 'json');
        });
        
        $('#btnDelete').on('click', function(e) {
            e.preventDefault();
            var eventId = $('#sidebarEventId').val();
            if (!eventId || !confirm('Are you sure you want to delete this schedule?')) return;
            
            $.post('ajax_schedule.php', {action: 'delete', event_id: eventId}, function(resp) {
                if (resp.success) {
                    resetSidebarForm();
                    calendar.refetchEvents();
                    $('#sidebarInfo').addClass('show').text('Schedule deleted successfully!');
                    setTimeout(function() {
                        $('#sidebarInfo').removeClass('show');
                    }, 3000);
                } else {
                    $('#sidebarInfo').addClass('show').css('background', '#fee').css('color', '#c33').text(resp.msg || 'Error deleting schedule');
                }
            }, 'json');
        });
        
        renderCalendar();
        $('#userSelect').on('change', function() {
            if (isAdmin) $('#allUsersView').prop('checked', false);
            resetSidebarForm();
            renderCalendar();
        });
        $('#timeSlotSelect').on('change', function() {
            resetSidebarForm();
            renderCalendar();
        });
        if (isAdmin) {
            $('#allUsersView').on('change', function() {
                resetSidebarForm();
                renderCalendar();
            });
        }
});
</script>
</body>
</html>
