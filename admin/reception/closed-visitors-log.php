<?php
require_once __DIR__ . '/../includes/top-menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Closed Visitors Log</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
        .filter-bar input { min-width: 220px; padding: 7px 12px; border-radius: 6px; font-size: 1em; border: 1px solid #ddd; }
        .filter-bar .btn-main { padding: 8px 16px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .filter-bar .btn-main:hover { background: #600000; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 8px #e0bebe22; border-radius: 12px; overflow: hidden; }
        table th, table td { padding: 12px 10px; text-align: left; }
        table thead { background: #f9eaea; color: #800000; }
        table tbody tr:hover { background: #f1f1f1; }
        .page-link { display: inline-block; padding: 8px 14px; margin: 0 2px; border-radius: 6px; background: #f9eaea; color: #800000; font-weight: 600; text-decoration: none; }
        .page-link:hover { background: #800000; color: #fff; }
        .page-link.active { background: #800000; color: #fff; }
        .summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
        .summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
        .summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
        .summary-label { font-size: 1em; color: #444; }
        .alert-fixed { position: fixed; top: 80px; right: 30px; z-index: 9999; min-width: 250px; }
    </style>
    <style>
    .status-dropdown {
        min-width: 110px;
        font-size: 1em;
        margin: 0 0 0 0;
        transition: box-shadow 0.15s;
        box-shadow: 0 1px 4px #e0bebe22;
        cursor: pointer;
    }
    .status-dropdown:focus {
        box-shadow: 0 0 0 2px #80000033;
        border-color: #800000;
    }
    .btn-action-view {
        background: linear-gradient(90deg,#800000 60%,#b33c3c 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 7px 18px;
        font-size: 0.98em;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        box-shadow: 0 2px 8px #80000022;
        transition: background 0.15s, box-shadow 0.15s;
        display: inline-block;
        cursor: pointer;
        margin-right: 6px;
    }
    .btn-action-view:hover {
        background: linear-gradient(90deg,#b33c3c 60%,#800000 100%);
        color: #fff;
        box-shadow: 0 4px 16px #80000033;
    }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Closed Visitors Log</h1>
    <div class="filter-bar" style="margin-bottom:12px;">
        <input type="text" id="search-input" placeholder="Search by Name or Mobile" style="flex:1 1 260px;min-width:220px;" />
        <button type="button" class="btn-main" id="search-btn">Search</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Purpose</th>
                <th>Visit Type</th>
                <th>Priority</th>
                <th>In Time</th>
                <th>Out Time</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="visitors-log-body">
            <tr><td colspan="11" style="text-align:center;color:#888;">Loading...</td></tr>
        </tbody>
    </table>
    <div id="pagination-bar" style="margin-top:18px;text-align:center;"></div>
</div>
<script>
// Attach search bar event listeners ONCE
const searchBtn = document.getElementById('search-btn');
const searchInput = document.getElementById('search-input');
if (searchBtn) searchBtn.onclick = function() { loadVisitorsLog(); };
if (searchInput) searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { loadVisitorsLog(); } });

// Attach status dropdown change event
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('status-dropdown')) {
        let id = e.target.getAttribute('data-id');
        let newStatus = e.target.value;
        let prevStatus = e.target.getAttribute('data-prev-status') || 'closed';
        fetch('ajax_visitors_log.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=update_status&id=' + id + '&status=' + encodeURIComponent(newStatus)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert('Status updated.');
                loadVisitorsLog();
            } else {
                showAlert('Error updating status', 'danger');
            }
        });
    }
});

// Pagination click handler
document.addEventListener('click', function(e) {
    const pageLink = e.target.closest('.page-link');
    if (pageLink && pageLink.hasAttribute('data-page')) {
        e.preventDefault();
        const page = parseInt(pageLink.getAttribute('data-page'));
        if (!isNaN(page)) {
            loadVisitorsLog(page);
        }
        return;
    }
    // Allow default for other links (like View)
});

// Initial load
loadVisitorsLog();
function showAlert(msg, type = 'success', timeout = 2500) {
    const alert = document.createElement('div');
    alert.className = `alert-fixed alert alert-${type}`;
    alert.innerHTML = msg;
    document.getElementById('alert-area')?.appendChild(alert);
    setTimeout(() => { alert.remove(); }, timeout);
}

function loadVisitorsLog(page = 1) {
    const perPage = 10;
    let searchVal = '';
    const searchInput = document.getElementById('search-input');
    if (searchInput) searchVal = searchInput.value.trim();
    fetch('ajax_visitors_log.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=list&page=' + page + '&perPage=' + perPage + '&status=closed' + (searchVal ? ('&search=' + encodeURIComponent(searchVal)) : '')
    })
    .then(r => r.json())
    .then(data => {
        const tbody = document.getElementById('visitors-log-body');
        tbody.innerHTML = '';
        if (data.success && data.data.length) {
            data.data.forEach(row => {
                let statusOptions = ['open','closed'];
                let statusColors = {open:'#0056b3',closed:'#1a8917'};
                let statusDropdown = `<select class=\"status-dropdown\" data-id=\"${row.id}\" style=\"background:#f9eaea;color:${statusColors[row.status]};font-weight:600;border-radius:7px;padding:5px 12px;border:1px solid #e0bebe;outline:none;\" disabled>\n` +
                    statusOptions.map(opt => `<option value=\"${opt}\" ${row.status===opt?'selected':''} style='color:${statusColors[opt]};'>${opt.charAt(0).toUpperCase()+opt.slice(1)}</option>`).join('') +
                `</select>`;
                const urgentRowStyle = row.priority === 'urgent' ? 'background:#fff2f2;' : '';
                tbody.innerHTML += `<tr style=\"${urgentRowStyle}\">\n` +
                    `<td>${row.id}</td>\n` +
                    `<td>${row.visitor_name}</td>\n` +
                    `<td>${row.contact_number || ''}</td>\n` +
                    `<td>${row.address || ''}</td>\n` +
                    `<td>${row.purpose || ''}</td>\n` +
                    `<td>${row.visit_type === 'call' ? 'Call' : 'In Office'}</td>\n` +
                    `<td>${row.priority === 'urgent' ? '<span style=\\\"color:#a00;font-weight:600;\\\">Urgent</span>' : 'Normal'}</td>\n` +
                    `<td>${row.in_time}</td>\n` +
                    `<td>${row.out_time || ''}</td>\n` +
                    `<td>${statusDropdown}</td>\n` +
                    `<td>\n` +
                        `<a href=\"view-visitor.php?id=${row.id}\" class=\"btn-action-view\">View</a>\n` +
                    `</td>\n` +
                `</tr>`;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#888;">No records found.</td></tr>';
        }

        // Pagination bar
        const pagDiv = document.getElementById('pagination-bar');
        pagDiv.innerHTML = '';
        if (data.success && data.total > perPage) {
            const totalPages = Math.ceil(data.total / perPage);
            let pageLinks = '';
            let maxPagesToShow = 5;
            let startPage = Math.max(1, data.page - 2);
            let endPage = Math.min(totalPages, data.page + 2);
            if (endPage - startPage < maxPagesToShow - 1) {
                if (startPage === 1) {
                    endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
                } else {
                    startPage = Math.max(1, endPage - maxPagesToShow + 1);
                }
            }
            if (startPage > 1) {
                pageLinks += `<a class=\"page-link\" href=\"#\" data-page=\"1\">1</a>`;
                if (startPage > 2) pageLinks += '<span class=\"page-link\" style=\"background:none;color:#888;cursor:default;\">...</span>';
            }
            for (let i = startPage; i <= endPage; i++) {
                pageLinks += `<a class=\"page-link${i === data.page ? ' active' : ''}\" href=\"#\" data-page=\"${i}\">${i}</a>`;
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) pageLinks += '<span class=\"page-link\" style=\"background:none;color:#888;cursor:default;\">...</span>';
                pageLinks += `<a class=\"page-link\" href=\"#\" data-page=\"${totalPages}\">${totalPages}</a>`;
            }
            pagDiv.innerHTML = pageLinks;
        }
    });
}

document.addEventListener('click', function(e) {
    const pageLink = e.target.closest('.page-link');
    if (pageLink && pageLink.hasAttribute('data-page')) {
        e.preventDefault();
        const page = parseInt(pageLink.getAttribute('data-page'));
        if (!isNaN(page)) {
            loadVisitorsLog(page);
        }
        return;
    }
    // Allow default for other links (like View)
});

loadVisitorsLog();
</script>
</body>
</html>
