<?php
session_start();
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
    
    /* Category Filter Bar */
    .filter-bar { 
        display: flex; 
        gap: 12px; 
        margin-bottom: 20px; 
        align-items: center; 
        flex-wrap: wrap;
        background: #f9eaea;
        padding: 12px;
        border-radius: 8px;
    }
    .filter-bar label { font-weight: 600; color: #333; }
    .filter-bar select { 
        padding: 8px 12px; 
        border: 1px solid #ddd; 
        border-radius: 6px; 
        font-size: 1em; 
        background: #fff;
        min-width: 200px;
    }
    .filter-bar select:focus { outline: none; border-color: #800000; }
    
    /* Category Section Styles */
    .category-section { 
        margin-bottom: 28px; 
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 12px #e0bebe22;
    }
    .category-heading { 
        background: #f9eaea; 
        color: #800000; 
        padding: 14px 12px; 
        font-weight: 700; 
        font-size: 1.08em;
        border-bottom: 2px solid #e0bebe;
    }
    .category-heading .product-count {
        font-size: 0.9em;
        opacity: 0.8;
        margin-left: 6px;
    }
    
    .service-table { width: 100%; border-collapse: collapse; background: #fff; font-family: inherit; margin: 0; }
    .service-table th, .service-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; font-size: 1.04em; }
    .service-table th { background: #f9eaea; color: #800000; font-weight: 700; letter-spacing: 0.01em; }
    .service-table tr:last-child td { border-bottom: none; }
    .action-btn { background: #007bff; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-right: 6px; transition: background 0.15s; font-size: 0.95em; }
    .action-btn.delete { background: #c00; }
    .action-btn:hover { background: #0056b3; }
    .action-btn.delete:hover { background: #a00000; }
    .status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.98em; display: inline-block; min-width: 80px; text-align: center; }
    .status-completed { background: #e5ffe5; color: #1a8917; }
    .status-cancelled { background: #ffeaea; color: #c00; }
    
    .seq-input {
        width: 70px;
        padding: 6px;
        border: 2px solid #ddd;
        border-radius: 4px;
        font-size: 0.95em;
        transition: border-color 0.3s;
    }
    .seq-input:focus { outline: none; border-color: #800000; }
    .seq-input.success { border-color: #28a745; background: #e8f5e9; }
    
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
    
    .no-products {
        text-align: center;
        color: #999;
        padding: 24px;
        font-size: 1em;
    }
    
    @media (max-width: 700px) {
        .admin-container { padding: 12px 2px; }
        .service-table th, .service-table td { padding: 10px 6px; font-size: 0.97em; }
        .service-table { min-width: 600px; }
        .filter-bar { flex-direction: column; }
        .filter-bar select { min-width: 100%; }
    }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Product Management</h1>
    <a href="add.php" class="add-btn">+ Add Product</a>
    
    <!-- Category Filter -->
    <div class="filter-bar">
        <label for="categoryFilter">📁 Filter by Category:</label>
        <select id="categoryFilter">
            <option value="">All Categories</option>
            <option value="birth-child">Birth & Child Services</option>
            <option value="marriage-matching">Marriage & Matching</option>
            <option value="astrology-consultation">Astrology Consultation</option>
            <option value="muhurat-event">Muhurat & Event Guidance</option>
            <option value="pooja-vastu-enquiry">Pooja, Ritual & Vastu Enquiry</option>
            <option value="appointment">Appointment</option>
        </select>
    </div>
    
    <div style="overflow-x:auto;">
    <div id="categorySections">
    <!-- AJAX loaded content here -->
    </div>
    </div>
    
    <div id="pagination" class="pagination"></div>
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
    const categorySections = document.getElementById('categorySections');
    const paginationContainer = document.getElementById('pagination');
    let paginationState = { totalPages: 1, currentPage: 1 };

    function loadTable(page = 1) {
        const categoryFilter = document.getElementById('categoryFilter').value;
        let url = 'ajax_list.php?page=' + page;
        if (categoryFilter) {
            url += '&category=' + encodeURIComponent(categoryFilter);
        }
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const { rowsHtml, pagination } = parsePagination(html);
                categorySections.innerHTML = rowsHtml;
                if (pagination) {
                    paginationState = pagination;
                }
                renderPagination();
            })
            .catch(err => {
                console.error('Error loading products:', err);
                categorySections.innerHTML = '<div class="no-products">Error loading products</div>';
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
        
        // Hide pagination if category filter is active (all products shown grouped by category)
        const categoryFilter = document.getElementById('categoryFilter').value;
        if (categoryFilter || totalPages <= 1) {
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

    // Category filter change event
    document.getElementById('categoryFilter').addEventListener('change', function() {
        paginationState.currentPage = 1;
        loadTable(1);
    });

    // Initial load
    loadTable(1);
});

// Handle sequence input changes
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('seq-input')) {
        const id = e.target.getAttribute('data-id');
        const order = e.target.value;
        const category = e.target.getAttribute('data-category');
        
        // Remove success class
        e.target.classList.remove('success');
        
        // Show loading state
        e.target.style.opacity = '0.6';
        
        fetch('update_sequence.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + encodeURIComponent(id) + '&order=' + encodeURIComponent(order) + '&category=' + encodeURIComponent(category)
        })
        .then(r => r.json())
        .then(data => {
            e.target.style.opacity = '1';
            if (data.success) {
                e.target.classList.add('success');
                setTimeout(() => {
                    e.target.classList.remove('success');
                }, 1500);
            } else {
                alert('Error: ' + (data.error || 'Failed to update sequence'));
                e.target.style.borderColor = '#ff6b6b';
                setTimeout(() => {
                    e.target.style.borderColor = '#ddd';
                }, 1500);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            e.target.style.opacity = '1';
            alert('Error updating sequence');
        });
    }
}, true);

// Handle mandatory select changes
categorySections.addEventListener('change', function(e) {
    if (e.target.classList.contains('mandatory-select')) {
        const id = e.target.getAttribute('data-id');
        const value = e.target.value;
        e.target.style.opacity = '0.6';
        fetch('update_mandatory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) + '&is_mandatory=' + encodeURIComponent(value)
        })
        .then(r => r.json())
        .then(data => {
            e.target.style.opacity = '1';
            if (data.success) {
                e.target.style.borderColor = '#4caf50';
                setTimeout(() => { e.target.style.borderColor = '#ddd'; }, 1200);
            } else {
                alert('Error: ' + (data.error || 'Failed to update mandatory status'));
                e.target.style.borderColor = '#ff6b6b';
                setTimeout(() => { e.target.style.borderColor = '#ddd'; }, 1200);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            e.target.style.opacity = '1';
            alert('Error updating mandatory status');
        });
    }
}, true);

// Handle product delete
function handleDeleteProduct(id, row) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    // Show loading state
    row.style.opacity = '0.5';
    fetch('delete_product.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            row.remove();
        } else {
            alert('Error deleting product: ' + (data.error || 'Unknown error'));
            row.style.opacity = '1';
        }
    })
    .catch(() => {
        alert('Server error. Please try again.');
        row.style.opacity = '1';
    });
}

// Delegate delete button clicks
categorySections.addEventListener('click', function(e) {
    if (e.target.classList.contains('action-btn') && e.target.classList.contains('delete')) {
        e.preventDefault();
        var row = e.target.closest('tr');
        var id = e.target.getAttribute('data-id');
        if (id && row) {
            handleDeleteProduct(id, row);
        }
    }
});
</script>
</body>
</html>

