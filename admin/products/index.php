<?php
// No DB logic here, all handled via AJAX
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Product Management</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .add-btn { display:inline-block; background:#800000; color:#fff; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:600; margin-bottom:18px; transition: background 0.15s; }
    .add-btn:hover { background: #a00000; }
    .service-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; font-family: inherit; }
    .service-table th, .service-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; font-size: 1.04em; }
    .service-table th { background: #f9eaea; color: #800000; font-weight: 700; letter-spacing: 0.01em; }
    .service-table tr:last-child td { border-bottom: none; }
    .action-btn { background: #007bff; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-right: 6px; transition: background 0.15s; }
    .action-btn.delete { background: #c00; }
    .action-btn:hover { background: #0056b3; }
    .action-btn.delete:hover { background: #a00000; }
    .status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.98em; display: inline-block; min-width: 80px; text-align: center; }
    .status-completed { background: #e5ffe5; color: #1a8917; }
    .status-cancelled { background: #ffeaea; color: #c00; }
    .pagination {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin: 18px 0;
        flex-wrap: wrap;
    }
    .pagination a,
    .pagination span {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: #fff;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        color: #333;
    }
    .pagination .page-link.current {
        background: #800000;
        color: #fff;
        border-color: #800000;
    }
    .pagination .page-link.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    @media (max-width: 700px) {
        .admin-container { padding: 12px 2px; }
        .service-table th, .service-table td { padding: 10px 6px; font-size: 0.97em; }
        .service-table { min-width: 600px; }
    }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Product Management</h1>
    <a href="add.php" class="add-btn">+ Add Product</a>
    <div style="overflow-x:auto;">
    <div id="productsAjaxResult">
    <table class="service-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Status</th>
                <th>Sequence</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="productTableBody">
        <!-- AJAX loaded rows -->
        </tbody>
    </table>
    <div id="pagination" class="pagination"></div>
    </div>
    </div>
</div>
<script>
// Debounce helper
function debounce(fn, delay) {
    let timer = null;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('productTableBody');
    const paginationContainer = document.getElementById('pagination');
    let paginationState = { totalPages: 1, currentPage: 1 };

    function loadTable(page = 1) {
        fetch('ajax_list.php?page=' + page)
            .then(r => r.text())
            .then(html => {
                const { rowsHtml, pagination } = parsePagination(html);
                tableBody.innerHTML = rowsHtml;
                if (pagination) {
                    paginationState = pagination;
                }
                renderPagination();
            });
    }

    function parsePagination(html) {
        let pagination = null;
        const scriptMatch = html.match(/<script[^>]*>[\s\S]*?<\/script>/i);
        if (scriptMatch) {
            const objMatch = scriptMatch[0].match(/window\.ajaxPagination\s*=\s*({[\s\S]*?})/);
            if (objMatch && objMatch[1]) {
                try {
                    pagination = Function('return ' + objMatch[1])();
                } catch (e) {}
            }
            html = html.replace(scriptMatch[0], '');
        }
        return { rowsHtml: html, pagination };
    }

    function renderPagination() {
        const totalPages = Math.max(1, paginationState.totalPages || 1);
        const currentPage = Math.max(1, Math.min(paginationState.currentPage || 1, totalPages));
        let html = '';
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        // Prev
        if (currentPage > 1) {
            html += '<a href="#" class="page-link" data-page="' + (currentPage - 1) + '">&laquo; Previous</a> ';
        } else {
            html += '<span class="page-link disabled">&laquo; Previous</span> ';
        }
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += '<span class="page-link current">' + i + '</span> ';
            } else {
                html += '<a href="#" class="page-link" data-page="' + i + '">' + i + '</a> ';
            }
        }
        // Next
        if (currentPage < totalPages) {
            html += '<a href="#" class="page-link" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
        } else {
            html += '<span class="page-link disabled">Next &raquo;</span>';
        }
        paginationContainer.innerHTML = html;
        paginationContainer.querySelectorAll('.page-link[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetPage = parseInt(link.getAttribute('data-page'), 10) || 1;
                paginationState.currentPage = targetPage;
                loadTable(targetPage);
            });
        });
    }

    // Initial load
    loadTable(1);
});

// Handle sequence input changes with debounce
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('seq-input')) {
        const id = e.target.getAttribute('data-id');
        const order = e.target.value;
        
        fetch('update_sequence.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + encodeURIComponent(id) + '&order=' + encodeURIComponent(order)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                e.target.style.borderColor = '#28a745';
                setTimeout(() => {
                    e.target.style.borderColor = '#ddd';
                }, 1500);
            } else {
                alert('Error: ' + (data.error || 'Failed to update sequence'));
            }
        })
        .catch(err => console.error('Error:', err));
    }
}, true);
</script>
</body>
</html>
