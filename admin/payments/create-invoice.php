<script>
document.addEventListener('DOMContentLoaded', function() {
	var productsBtn = document.getElementById('productsBtn');
	if (productsBtn) {
		productsBtn.addEventListener('click', function() {
			window.open('products.php', 'ProductsPopup', 'width=900,height=600,scrollbars=yes,resizable=yes');
		});
	}
});
</script>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';
// Fetch products for dropdown
$productStmt = $pdo->prepare("SELECT title FROM letterpad_titles WHERE source = 'product' ORDER BY title ASC");
$productStmt->execute();
$productList = $productStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Create Invoice</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
				   #addNewCustomerBtn {
					   background: linear-gradient(90deg, #007bff 60%, #0056b3 100%);
					   color: #fff;
					   border: none;
					   border-radius: 6px;
					   padding: 7px 16px;
					   font-size: 0.98em;
					   font-weight: 600;
					   cursor: pointer;
					   box-shadow: 0 2px 8px rgba(0,123,255,0.08);
					   transition: background 0.2s, box-shadow 0.2s;
					   margin-left: 4px;
					   white-space: nowrap;
				   }
	body {
			margin: 0;
			background: #f4f6fa;
		}
		.admin-container {
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: flex-start;
			padding-top: 40px;
		}
		.form-box {
			background: #fff;
			border-radius: 14px;
			box-shadow: 0 4px 24px rgba(128,0,0,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
			padding: 38px 36px 30px 36px;
			width: 100%;
			max-width: 1000px;
			margin-bottom: 32px;
			border: 1px solid #ececec;
		}
		.form-box h1 {
			color: #800000;
			font-size: 2.1em;
			font-weight: 800;
			margin-bottom: 18px;
			letter-spacing: 0.5px;
		}
		label {
			font-weight: 600;
			color: #333;
			margin-bottom: 4px;
			display: block;
		}
		input[type="text"], input[type="number"], input[type="date"], textarea {
			width: 100%;
			padding: 11px 12px;
			border: 1px solid #d2d2d2;
			border-radius: 6px;
			font-size: 1em;
			margin-top: 6px;
			margin-bottom: 14px;
			background: #fafbfc;
			transition: border 0.2s;
		}
		input[type="text"]:focus, input[type="number"]:focus, input[type="date"]:focus, textarea:focus {
			border: 1.5px solid #800000;
			outline: none;
			background: #fff;
		}
		.product-row {
			background: #f8f8fa;
			border-radius: 7px;
			padding: 10px 8px 10px 8px;
			margin-bottom: 8px;
			box-shadow: 0 1px 3px rgba(128,0,0,0.03);
		}
		.addProductRowBtn {
			background: linear-gradient(90deg, #28a745 60%, #43c06d 100%);
			color: #fff;
			border: none;
			border-radius: 4px;
			padding: 8px 16px;
			font-size: 1.3em;
			font-weight: 700;
			cursor: pointer;
			box-shadow: 0 1px 2px rgba(40,167,69,0.08);
			transition: background 0.2s;
		}
		.addProductRowBtn:hover {
			background: linear-gradient(90deg, #43c06d 60%, #28a745 100%);
		}
		.removeProductRowBtn {
			background: linear-gradient(90deg, #dc3545 60%, #e35d6a 100%);
			color: #fff;
			border: none;
			border-radius: 4px;
			padding: 8px 16px;
			font-size: 1.3em;
			font-weight: 700;
			cursor: pointer;
			margin-left: 2px;
			box-shadow: 0 1px 2px rgba(220,53,69,0.08);
			transition: background 0.2s;
		}
		.removeProductRowBtn:hover {
			background: linear-gradient(90deg, #e35d6a 60%, #dc3545 100%);
		}
		#submitInvoiceBtn {
			background: linear-gradient(90deg, #800000 60%, #a83232 100%);
			color: #fff;
			border: none;
			border-radius: 6px;
			padding: 13px 38px;
			font-size: 1.13em;
			font-weight: 800;
			cursor: pointer;
			box-shadow: 0 2px 8px rgba(128,0,0,0.08);
			margin-top: 10px;
			transition: background 0.2s;
		}
		#submitInvoiceBtn:hover {
			background: linear-gradient(90deg, #a83232 60%, #800000 100%);
		}
		#productTotals {
			margin-top: 18px;
			font-weight: 700;
			color: #333;
			font-size: 1.08em;
			background: #f6f6f6;
			border-radius: 6px;
			padding: 10px 0 10px 0;
			text-align: right;
		}
		#selectedCustomerBox {
			background: #f6f6f6;
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			color: #333;
			margin-top: 10px;
			padding: 13px 18px;
			font-size: 1.04em;
		}
		.form-section {
			margin-bottom: 28px;
		}
		.form-sections-row {
			display: flex;
			gap: 32px;
			flex-wrap: wrap;
		}
		@media (max-width: 1200px) {
			.form-box { padding: 28px 24px 20px 24px; }
			.form-box h1 { font-size: 1.8em; }
			.addProductRowBtn, .removeProductRowBtn { padding: 6px 12px; font-size: 1.2em; }
			#addNewCustomerBtn { padding: 6px 12px; font-size: 0.95em; }
		}
		@media (max-width: 768px) {
			.admin-container { padding: 20px 10px !important; }
			.form-box { padding: 20px 16px 14px 16px; border-radius: 10px; }
			.form-box h1 { font-size: 1.4em; margin-bottom: 14px; }
			label { font-weight: 600; margin-bottom: 3px; }
			input[type="text"], input[type="number"], input[type="date"], textarea {
				padding: 9px 10px; margin-top: 4px; margin-bottom: 12px; font-size: 0.95em;
			}
			.product-row { padding: 10px 6px; margin-bottom: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
			.product-row input { flex: 1; min-width: 100px; padding: 8px 8px; }
			.addProductRowBtn, .removeProductRowBtn { padding: 6px 10px; font-size: 1.1em; margin: 0; }
			#submitInvoiceBtn { padding: 10px 24px; font-size: 1em; }
			#addNewCustomerBtn { padding: 6px 10px; font-size: 0.9em; margin-left: 2px; }
			.form-sections-row { gap: 16px; }
			.form-section { min-width: 100% !important; }
		}
		@media (max-width: 600px) {
			.admin-container { padding: 14px 8px !important; }
			.form-box { padding: 16px 12px 10px 12px; }
			.form-box h1 { font-size: 1.2em; margin-bottom: 12px; }
			input[type="text"], input[type="number"], input[type="date"], textarea {
				padding: 8px 8px; margin-top: 3px; margin-bottom: 10px; font-size: 0.9em;
			}
			#customerSearch { width: 100%; margin-bottom: 8px; }
			#addNewCustomerBtn { display: block; width: 100%; padding: 8px 0; margin: 8px 0 0 0; }
			.product-row { flex-direction: column; gap: 6px; }
			.product-row input { width: 100% !important; min-width: auto; }
			.addProductRowBtn, .removeProductRowBtn { width: 100%; padding: 8px 0; font-size: 1em; margin: 0; }
			#submitInvoiceBtn { width: 100%; padding: 10px 0; font-size: 0.95em; }
			#productTotals { font-size: 0.95em; padding: 8px 0; }
		}
		@media (max-width: 400px) {
			.form-box { padding: 12px 10px 8px 10px; }
			.form-box h1 { font-size: 1.1em; }
			input[type="text"], input[type="number"], input[type="date"], textarea {
				font-size: 0.85em; padding: 7px 6px; margin-bottom: 8px;
			}
		}
	</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container" style="display:flex; flex-direction:column; align-items:center; min-height:100vh; justify-content:flex-start; padding-top:32px;">
	<div class="form-box" style="width:100%; max-width:1000px; margin-bottom:32px; box-shadow:0 2px 12px rgba(128,0,0,0.06);">
		<div style="text-align:center; margin-bottom:18px; display:flex; flex-direction:column; align-items:center; gap:10px;">
			   <h1 style="margin:0; font-size:1.7em; color:#800000; font-weight:700; letter-spacing:0.5px;">Create Invoice</h1>
		</div>
		<form id="invoiceForm" method="post" autocomplete="off">
			<div class="form-sections-row" style="display:flex; gap:32px; flex-wrap:wrap;">
				<div class="form-section" style="flex:1; min-width:320px;">
					<label for="customerSelect">Select Customer</label>
					<div style="display:flex; gap:10px; align-items:center;">
						<input type="text" id="customerSearch" placeholder="Search customer by name or mobile..." autocomplete="off">
						<button type="button" id="addNewCustomerBtn">Add Customer</button>
					</div>
					<div id="customerDropdown" style="position:relative; background:#fff; border:1px solid #ccc; border-radius:4px; display:none; max-height:180px; overflow-y:auto; z-index:10;"></div>
					<input type="hidden" name="customer_id" id="customer_id">
					<div id="selectedCustomerBox" style="display:none;"></div>
				</div>
				<div class="form-section" style="flex:1; min-width:320px;">
					<label for="invoiceDate">Invoice Date</label>
					<input type="date" id="invoiceDate" name="invoice_date" value="<?php echo date('Y-m-d'); ?>">
					<label for="invoiceNote">Invoice Note</label>
					<textarea id="invoiceNote" name="invoice_note" rows="3" placeholder="Enter any note for this invoice..."></textarea>
				</div>
			</div>
			<div class="form-section" style="border-top:1px solid #eee; padding-top:18px; margin-top:24px;">
				   <div style="display:flex; align-items:center; gap:16px; margin-bottom:8px;">
					   <label style="margin-bottom:0;">Add Product/Service</label>
					   <button type="button" id="productsBtn" style="background:linear-gradient(90deg,#ffc107 60%,#ff9800 100%);color:#333;border:none;border-radius:6px;padding:7px 18px;font-size:1em;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(255,193,7,0.08);transition:background 0.2s;">Products</button>
				   </div>
				<div id="productRows">
					   <div class="product-row" style="display:flex; gap:10px; align-items:center; flex-wrap:nowrap;">
						   <select class="product-dropdown" style="min-width:140px;">
							   <option value="">Select Product</option>
							   <?php foreach ($productList as $prod): ?>
								   <option value="<?= htmlspecialchars($prod) ?>"><?= htmlspecialchars($prod) ?></option>
							   <?php endforeach; ?>
						   </select>
						   <input type="text" name="product_name[]" placeholder="Product/Service Name" style="flex:2; min-width:180px;">
						   <input type="number" name="product_qty[]" class="product-qty" placeholder="Qty" min="1" value="1" style="width:80px;">
						   <input type="number" name="product_amount[]" class="product-amount" placeholder="Amount" min="0" step="0.01" style="width:120px;">
						   <button type="button" class="addProductRowBtn">+</button>
						   <!-- X button for deleting extra rows, hidden for first row by default -->
						   <button type="button" class="removeProductRowBtn xRemoveBtn" title="Delete Row" style="display:none; background:#dc3545; color:#fff; border:none; border-radius:4px; padding:8px 14px; font-size:1.2em; font-weight:700; cursor:pointer;">×</button>
					   </div>
				</div>
				<div id="productTotals">
					Total Qty: <span id="totalQty">1</span> &nbsp; | &nbsp; Total Amount: ₹<span id="totalAmount">0.00</span>
				</div>
			</div>
			<div style="margin-top:32px; display:flex; align-items:flex-end; gap:24px; justify-content:flex-end;">
	<!-- Collect Payment section removed. Payment will be handled after invoice creation. -->
    <button type="submit" id="submitInvoiceBtn">Submit Invoice</button>
    <span id="invoiceSubmitMsg" style="margin-left:18px; font-weight:600;"></span>
	</div>


	<!-- Dynamic Tables Section -->
	<div class="form-box" style="width:100%; max-width:1000px; margin-bottom:32px; box-shadow:0 2px 12px rgba(128,0,0,0.06);">
		<h2 style="margin:0 0 18px 0; font-size:1.25em; color:#800000; font-weight:700; letter-spacing:0.5px;">Invoices</h2>
		<div style="overflow-x:auto;">
		<table id="customerInvoicesTable" style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px; box-shadow:0 1px 4px #80000011;">
			<thead style="background:#f6f6f6;">
				<tr>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:left; color:#800000;">Invoice #</th>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:left; color:#800000;">Date</th>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:right; color:#800000;">Amount (₹)</th>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:right; color:#800000;">Status</th>
				</tr>
			</thead>
			<tbody id="customerInvoicesBody">
				<!-- Dynamic rows -->
			</tbody>
		</table>
		</div>
	</div>

	<div class="form-box" style="width:100%; max-width:1000px; margin-bottom:32px; box-shadow:0 2px 12px rgba(128,0,0,0.06);">
		<h2 style="margin:0 0 18px 0; font-size:1.25em; color:#800000; font-weight:700; letter-spacing:0.5px;">Payments</h2>
		<div style="overflow-x:auto;">
		<table id="customerPaymentsTable" style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px; box-shadow:0 1px 4px #80000011;">
			<thead style="background:#f6f6f6;">
				<tr>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:left; color:#800000;">Date</th>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:right; color:#800000;">Amount (₹)</th>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:left; color:#800000;">Method</th>
					<th style="padding:10px 8px; border-bottom:2px solid #ececec; text-align:left; color:#800000;">Ref/Note</th>
				</tr>
			</thead>
			<tbody id="customerPaymentsBody">
				<!-- Dynamic rows -->
			</tbody>
		</table>
		</div>
	</div>

		</form>
	</div>


<!-- Add Customer Modal (outside form, after main content) -->
<div id="addCustomerModalBg">
	<div id="invoiceLoading" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);z-index:9999;align-items:center;justify-content:center;font-size:1.3em;color:#800000;font-weight:600;">
		Saving invoice, please wait...
	</div>
	<div id="addCustomerLoading" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);z-index:9999;align-items:center;justify-content:center;font-size:1.3em;color:#800000;font-weight:600;">
		Saving customer, please wait...
	</div>
	<div id="addCustomerModal">
		<h2 style="margin-top:0; color:#800000; font-size:1.2em;">Add New Customer</h2>
		<form id="addCustomerForm" autocomplete="off">
			<label for="newCustomerName">Name</label>
			<input type="text" id="newCustomerName" name="name" required>
			<label for="newCustomerMobile">Mobile</label>
			<input type="text" id="newCustomerMobile" name="mobile" required pattern="[0-9]{10,15}">
			<label for="newCustomerAddress">Address</label>
			<textarea id="newCustomerAddress" name="address" rows="2"></textarea>
			<div class="modal-actions">
				<button type="button" id="cancelAddCustomer" style="background:#ccc; color:#333; border:none; border-radius:4px; padding:8px 16px;">Cancel</button>
				<button type="submit" style="background:#28a745; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600;">Save</button>
			</div>
			<div id="addCustomerMsg" style="margin-top:8px; color:#800000; font-size:0.98em;"></div>
		</form>
	</div>
</div>

<style>
#addCustomerModalBg {
	position: fixed; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.18); z-index: 1000; display: none; align-items: center; justify-content: center;
}
#addCustomerModal {
	background: #fff; border-radius: 8px; box-shadow: 0 2px 16px rgba(0,0,0,0.13); padding: 32px 28px 22px 28px; min-width: 320px; max-width: 95vw; width: 370px;
}
#addCustomerModal input, #addCustomerModal textarea {
	width: 100%; margin-bottom: 14px; padding: 9px; border: 1px solid #ccc; border-radius: 4px;
}
#addCustomerModal label { font-weight: 600; color: #333; }
#addCustomerModal .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
</style>

<style>
.removeProductRowBtn {
	background: #dc3545;
	color: #fff;
	border: none;
	border-radius: 4px;
	padding: 8px 14px;
	font-size: 1.2em;
	font-weight: 700;
	cursor: pointer;
}
</style>

<script>
// Product dropdown auto-fill logic
document.addEventListener('DOMContentLoaded', function() {
	function bindProductDropdowns() {
		document.querySelectorAll('.product-row').forEach(function(row, idx) {
			var dropdown = row.querySelector('.product-dropdown');
			var input = row.querySelector('input[name="product_name[]"]');
			if (dropdown && input) {
				dropdown.onchange = function() {
					if (this.value) input.value = this.value;
				};
			}
			// Show X button only for extra rows (not the first)
			var xBtn = row.querySelector('.xRemoveBtn');
			if (xBtn) xBtn.style.display = (idx === 0) ? 'none' : 'inline-block';
		});
	}
	bindProductDropdowns();
	var productList = <?php echo json_encode($productList); ?>;
	document.getElementById('productRows').addEventListener('click', function(e) {
		if (e.target.classList.contains('addProductRowBtn')) {
			var row = e.target.closest('.product-row');
			var newRow = row.cloneNode(true);
			// Clear input values
			newRow.querySelectorAll('input').forEach(function(inp) { inp.value = inp.type === 'number' ? (inp.name === 'product_qty[]' ? 1 : '') : ''; });
			// Rebuild dropdown
			var select = newRow.querySelector('.product-dropdown');
			if (select) {
				select.innerHTML = '<option value="">Select Product</option>' + productList.map(function(p) {
					return '<option value="'+p.replace(/"/g,'&quot;')+'">'+p.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</option>';
				}).join('');
				select.selectedIndex = 0;
			}
			// Remove any duplicate event listeners on the new row's + button
			var addBtn = newRow.querySelector('.addProductRowBtn');
			if (addBtn) addBtn.disabled = false;
			row.parentNode.insertBefore(newRow, row.nextSibling);
			bindProductDropdowns();
		}
		// Handle X button click for row removal
		if (e.target.classList.contains('xRemoveBtn')) {
			var row = e.target.closest('.product-row');
			if (row && row.parentNode.children.length > 1) {
				row.remove();
				bindProductDropdowns();
			}
		}
	});
});
// --- Dynamic Customer Search Dropdown ---

// --- Dynamic Invoices & Payments Table Logic ---
function clearCustomerTables() {
	document.getElementById('customerInvoicesBody').innerHTML = '';
	document.getElementById('customerPaymentsBody').innerHTML = '';
}

function renderInvoicesTable(invoices) {
	const tbody = document.getElementById('customerInvoicesBody');
	if (!Array.isArray(invoices) || invoices.length === 0) {
		tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#888; padding:18px;">No invoices found.</td></tr>';
		return;
	}
	tbody.innerHTML = invoices.map(inv => `
		<tr>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6;">${inv.invoice_no || inv.id}</td>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6;">${inv.date}</td>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6; text-align:right;">${parseFloat(inv.amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6; text-align:right; color:${inv.status === 'Paid' ? '#228B22' : '#b30000'}; font-weight:600;">${inv.status}</td>
		</tr>
	`).join('');
}

function renderPaymentsTable(payments) {
	const tbody = document.getElementById('customerPaymentsBody');
	if (!Array.isArray(payments) || payments.length === 0) {
		tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#888; padding:18px;">No payments found.</td></tr>';
		return;
	}
	tbody.innerHTML = payments.map(pay => `
		<tr>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6;">${pay.date}</td>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6; text-align:right;">${parseFloat(pay.amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6;">${pay.method}</td>
			<td style="padding:8px 8px; border-bottom:1px solid #f3e6e6;">${pay.ref || pay.note || ''}</td>
		</tr>
	`).join('');
}

function fetchAndShowCustomerTables(customerId) {
	if (!customerId) {
		clearCustomerTables();
		return;
	}
	// Fetch invoices
	fetch('fetch_customer_invoices.php?id=' + encodeURIComponent(customerId))
		.then(res => res.json())
		.then(data => {
			renderInvoicesTable(Array.isArray(data) ? data : []);
		})
		.catch(() => {
			renderInvoicesTable([]);
		});
	// Fetch payments
	fetch('fetch_customer_payments.php?id=' + encodeURIComponent(customerId))
		.then(res => res.json())
		.then(data => {
			renderPaymentsTable(Array.isArray(data) ? data : []);
		})
		.catch(() => {
			renderPaymentsTable([]);
		});
}

// On page load, keep tables blank
clearCustomerTables();
const searchInput = document.getElementById('customerSearch');
const dropdown = document.getElementById('customerDropdown');
const customerIdInput = document.getElementById('customer_id');
let customerTimeout = null;

searchInput.addEventListener('input', function() {
	const val = this.value.trim();
	if (customerTimeout) clearTimeout(customerTimeout);
	if (!val) {
		dropdown.style.display = 'none';
		customerIdInput.value = '';
		return;
	}
	customerTimeout = setTimeout(() => {
		fetch('fetch_customers.php?q=' + encodeURIComponent(val))
			.then(res => res.json())
			.then(data => {
				if (!Array.isArray(data) || data.length === 0) {
					dropdown.innerHTML = `
						<div style="padding:10px; color:#888;">No customer found</div>
						<button type="button" id="dropdownAddCustomerBtn" style="width:100%;padding:10px 0;background:#800000;color:#fff;border:none;border-radius:4px;margin-top:6px;cursor:pointer;">Add New Customer</button>
					`;
					dropdown.style.display = 'block';
					// Attach event to open modal
					setTimeout(() => {
						const btn = document.getElementById('dropdownAddCustomerBtn');
						if (btn) {
							btn.onclick = function() {
								addCustomerForm.reset();
								addCustomerMsg.textContent = '';
								// Autofill name field with last searched value
								const lastSearch = searchInput.value.trim();
								if (lastSearch) {
									document.getElementById('newCustomerName').value = lastSearch;
								}
								addCustomerModalBg.style.display = 'flex';
								dropdown.style.display = 'none';
							};
						}
					}, 10);
					return;
				}
				dropdown.innerHTML = data.map(c =>
					`<div class="customer-option" data-id="${c.id}" style="padding:10px 14px; cursor:pointer; border-bottom:1px solid #f3e6e6; display:flex; flex-direction:column;">
						<span style=\"font-weight:600; color:#333;\">${c.name}</span>
						<span style=\"font-size:0.97em; color:#888;\">${c.mobile}</span>
						<span style=\"font-size:0.97em; color:#888;\">${c.address ? c.address : ''}</span>
						<span style=\"font-size:0.97em; color:${c.dues > 0 ? '#b30000' : '#228B22'}; font-weight:600;\">Dues: ₹${c.dues.toFixed(2)}</span>
					</div>`
				).join('');
				dropdown.style.display = 'block';
			});
	}, 250);
});

dropdown.addEventListener('mousedown', function(e) {
    const opt = e.target.closest('.customer-option');
    if (opt) {
        // Get customer info from the option
        const id = opt.getAttribute('data-id');
        const name = opt.querySelector('span').textContent;
        const mobile = opt.querySelectorAll('span')[1].textContent;
        customerIdInput.value = id;
		// Show selected customer below
		const selectedBox = document.getElementById('selectedCustomerBox');
		selectedBox.innerHTML = `<b>Selected Customer:</b><br>Name: ${name}<br>Mobile: ${mobile}<br><span id='customerDuesInfo' style='color:#888;'>Loading dues...</span><div id='collectDueBtnBox'></div>`;
		selectedBox.style.display = 'block';
		// Show customer tables
		fetchAndShowCustomerTables(id);
		// Clear search field
		searchInput.value = '';
		dropdown.style.display = 'none';
		// Fetch and show dues
		fetch('get_customer_dues.php?id=' + encodeURIComponent(id))
			.then(res => res.json())
			.then(data => {
				if (data && !data.error) {
					document.getElementById('customerDuesInfo').innerHTML = `
						<div style='margin-top:6px;'>
							<b>Total Invoiced:</b> ₹${data.total_invoiced.toFixed(2)}<br>
							<b>Total Paid:</b> ₹${data.total_paid.toFixed(2)}<br>
							<b>Total Dues:</b> <span style='color:${data.total_dues > 0 ? '#b30000' : '#228B22'};'>₹${data.total_dues.toFixed(2)}</span>
						</div>
					`;
					// Add Collect button if dues > 0
					const collectBox = document.getElementById('collectDueBtnBox');
					if (collectBox) {
						if (data.total_dues > 0) {
							collectBox.innerHTML = `<button id='collectDueBtn' style='margin-top:10px;background:#1a8917;color:#fff;padding:7px 18px;border:none;border-radius:6px;font-weight:600;cursor:pointer;'>Collect</button>`;
							document.getElementById('collectDueBtn').onclick = function(e) {
								e.preventDefault();
								e.stopPropagation();
								// Open collect modal directly, skip all invoice/product validation
								showCollectPaymentBox(customerIdInput.value);
							};
						} else {
							collectBox.innerHTML = '';
						}
					}
				} else {
					document.getElementById('customerDuesInfo').textContent = 'Could not load dues.';
				}
			})
			.catch(() => {
				document.getElementById('customerDuesInfo').textContent = 'Could not load dues.';
			});
    }
});

document.addEventListener('click', function(e) {
		updateProductTotals();
	if (!dropdown.contains(e.target) && e.target !== searchInput) {
		dropdown.style.display = 'none';
	}
});

// --- Add New Customer Modal Logic ---

// When customer is cleared, blank the tables
customerIdInput.addEventListener('change', function() {
	if (!this.value) clearCustomerTables();
});
const addNewCustomerBtn = document.getElementById('addNewCustomerBtn');
const addCustomerModalBg = document.getElementById('addCustomerModalBg');
const addCustomerForm = document.getElementById('addCustomerForm');
const cancelAddCustomer = document.getElementById('cancelAddCustomer');
const addCustomerMsg = document.getElementById('addCustomerMsg');

addNewCustomerBtn.addEventListener('click', function() {
	addCustomerForm.reset();
	addCustomerMsg.textContent = '';
	addCustomerModalBg.style.display = 'flex';
});

cancelAddCustomer.addEventListener('click', function() {
	addCustomerModalBg.style.display = 'none';
});

addCustomerForm.addEventListener('submit', function(e) {
	e.preventDefault();
	addCustomerMsg.textContent = '';
	// Show loading overlay and disable save button
	document.getElementById('addCustomerLoading').style.display = 'flex';
	const saveBtn = addCustomerForm.querySelector('button[type="submit"]');
	if (saveBtn) saveBtn.disabled = true;
	const formData = new FormData(addCustomerForm);
	fetch('add_customer.php', {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(data => {
		document.getElementById('addCustomerLoading').style.display = 'none';
		if (saveBtn) saveBtn.disabled = false;
		if (data.success) {
			addCustomerMsg.style.color = '#28a745';
			addCustomerMsg.textContent = 'Customer added successfully!';
			setTimeout(() => {
				addCustomerModalBg.style.display = 'none';
				// Auto-select and show the new customer
				customerIdInput.value = data.customer.id;
				const selectedBox = document.getElementById('selectedCustomerBox');
				selectedBox.innerHTML = `<b>Selected Customer:</b><br>Name: ${data.customer.name}<br>Mobile: ${data.customer.mobile}`;
				selectedBox.style.display = 'block';
				searchInput.value = '';
				dropdown.style.display = 'none';
				// Show customer tables
				fetchAndShowCustomerTables(data.customer.id);
			}, 900);
		} else {
			addCustomerMsg.style.color = '#800000';
			addCustomerMsg.textContent = data.error || 'Failed to add customer.';
		}
	})
	.catch(() => {
		document.getElementById('addCustomerLoading').style.display = 'none';
		if (saveBtn) saveBtn.disabled = false;
		addCustomerMsg.style.color = '#800000';
		addCustomerMsg.textContent = 'Failed to add customer.';
	});
});

// --- Product/Service Row Remove Logic ---
document.addEventListener('click', function(e) {
	// Remove row (not first)
	if (e.target.classList.contains('removeProductRowBtn')) {
		updateProductTotals();
		const row = e.target.closest('.product-row');
		if (row && row.parentNode.children.length > 1) {
			row.remove();
		}
	}
});


// --- Product/Service Totals Calculation ---
function updateProductTotals() {
	let totalQty = 0;
	let totalAmount = 0;
	document.querySelectorAll('#productRows .product-row').forEach(function(row) {
		const qtyInput = row.querySelector('input[name="product_qty[]"]');
		const amtInput = row.querySelector('input[name="product_amount[]"]');
		let qty = parseFloat(qtyInput && qtyInput.value ? qtyInput.value : 0);
		let amt = parseFloat(amtInput && amtInput.value ? amtInput.value : 0);
		if (!isNaN(qty)) totalQty += qty;
		if (!isNaN(qty) && !isNaN(amt)) totalAmount += qty * amt;
	});
	document.getElementById('totalQty').textContent = totalQty;
	document.getElementById('totalAmount').textContent = totalAmount.toFixed(2);
}

// Listen for changes on qty/amount fields
document.getElementById('productRows').addEventListener('input', function(e) {
	if (e.target.name === 'product_qty[]' || e.target.name === 'product_amount[]') {
		updateProductTotals();
	}
});

// Initial calculation
updateProductTotals();

// Also update totals after adding/removing rows
const origAddProductRowBtn = document.querySelector('.addProductRowBtn');
if (origAddProductRowBtn) {
	origAddProductRowBtn.addEventListener('click', function() {
		setTimeout(updateProductTotals, 10);
	});
}
document.getElementById('productRows').addEventListener('click', function(e) {
	if (e.target.classList.contains('removeProductRowBtn')) {
		setTimeout(updateProductTotals, 10);
	}
});


// --- Invoice Form Submission ---
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
	e.preventDefault();
	const msg = document.getElementById('invoiceSubmitMsg');
	msg.textContent = '';
	msg.style.color = '#333';
	const form = e.target;
	const formData = new FormData(form);
	// Show loading overlay and disable submit button
	document.getElementById('invoiceLoading').style.display = 'flex';
	const submitBtn = document.getElementById('submitInvoiceBtn');
	if (submitBtn) submitBtn.disabled = true;
	// Get values for validation
	const customerId = document.getElementById('customer_id').value;
	const totalAmount = parseFloat(document.getElementById('totalAmount').textContent) || 0;
	const productNames = Array.from(form.querySelectorAll('input[name="product_name[]"]')).map(i => i.value.trim()).filter(Boolean);
	// Add total qty and total amount to formData
	formData.append('total_qty', document.getElementById('totalQty').textContent);
	formData.append('total_amount', document.getElementById('totalAmount').textContent);
	fetch('save_invoice.php', {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(data => {
		document.getElementById('invoiceLoading').style.display = 'none';
		if (submitBtn) submitBtn.disabled = false;
		if (data.post) {
			alert('DEBUG POST DATA:\n' + JSON.stringify(data.post, null, 2));
		}
		if (data.success) {
			msg.style.color = '#28a745';
			msg.textContent = 'Invoice created successfully!';
			form.reset();
			document.getElementById('selectedCustomerBox').style.display = 'none';
			clearCustomerTables();
			// Remove all product rows except first
			const productRows = document.getElementById('productRows');
			while (productRows.children.length > 1) productRows.lastChild.remove();
			updateProductTotals();
			// Show collect payment modal/section
			setTimeout(function() {
				showCollectPaymentBox(customerId);
			}, 300);
		} else if (data.error) {
			msg.style.color = '#800000';
			msg.textContent = data.error || 'Failed to create invoice.';
			alert('ERROR: ' + (data.error || 'Failed to create invoice.'));
		}
	})
	.catch((err) => {
		document.getElementById('invoiceLoading').style.display = 'none';
		if (submitBtn) submitBtn.disabled = false;
		msg.style.color = '#800000';
		msg.textContent = 'Failed to create invoice.';
		alert('AJAX ERROR: ' + err);
	});
});



</script>

<!-- Collect Payment Modal (Modern Design, Dues Page Style) -->
<div id="collectPaymentModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:2000; align-items:center; justify-content:center;">
	<div id="collectPaymentModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:32px 28px 18px 28px; min-width:340px; max-width:95vw; width:370px; text-align:left; position:relative;">
		<div style="font-size:1.18em;color:#800000;font-weight:700;margin-bottom:10px;">Collect Payment</div>
		<form id="collectPaymentForm" autocomplete="off">
			<input type="hidden" name="customer_id" id="collectCustomerId">
			<div style="margin-bottom:10px;color:#444;"><b>Customer:</b> <span id="collectCustomerName"></span></div>
			<div style="margin-bottom:10px;color:#444;"><b>Due Amount:</b> ₹<span id="collectDueAmount"></span></div>
			<div style="margin-bottom:10px;">Amount: <input type="number" name="amount" id="collectAmount" min="1" step="0.01" style="width:120px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required></div>
			<div style="margin-bottom:10px;">Method: 
				<select name="pay_method" id="collectPayMethod" style="padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required>
					<option value="Cash">Cash</option>
					<option value="UPI">UPI</option>
					<option value="Bank">Bank</option>
					<option value="Other">Other</option>
				</select>
			</div>
			<div style="margin-bottom:10px;">Date: <input type="date" name="pay_date" id="collectPayDate" value="" style="padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required></div>
			<div style="margin-bottom:10px;">Transaction/Ref: <input type="text" name="transaction_details" id="collectTransactionDetails" style="width:180px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;"></div>
			<div style="margin-bottom:10px;">Note: <input type="text" name="note" id="collectNote" style="width:180px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;"></div>
			<div style="margin-top:18px;text-align:center;">
				<button type="submit" style="background:#1a8917;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Collect & Mark Paid</button>
				&nbsp;
				<button type="button" id="cancelCollectPayment" style="background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
			</div>
			<div id="collectPaymentMsg" style="margin-top:10px; color:#c00; display:none;"></div>
		</form>
	</div>
</div>
<script>
// --- Collect Payment Modal Logic (Dues Page Style) ---
function showCollectPaymentBox(customerId) {
	const modalBg = document.getElementById('collectPaymentModalBg');
	const msgBox = document.getElementById('collectPaymentMsg');
	msgBox.style.display = 'none';
	msgBox.textContent = '';
	// Fetch latest dues and customer info
	fetch('get_customer_dues.php?id=' + encodeURIComponent(customerId))
		.then(res => res.json())
		.then(data => {
			if (data && !data.error) {
				document.getElementById('collectCustomerId').value = customerId;
				document.getElementById('collectCustomerName').textContent = data.name || '';
				document.getElementById('collectDueAmount').textContent = data.total_dues.toFixed(2);
					document.getElementById('collectAmount').value = '';
				document.getElementById('collectAmount').max = data.total_dues;
				document.getElementById('collectPayDate').value = (new Date()).toISOString().slice(0,10);
				document.getElementById('collectPayMethod').value = 'Cash';
				document.getElementById('collectTransactionDetails').value = '';
				document.getElementById('collectNote').value = '';
				modalBg.style.display = 'flex';
			} else {
				alert('Could not load dues.');
			}
		});
}
document.getElementById('cancelCollectPayment').onclick = function() {
	document.getElementById('collectPaymentModalBg').style.display = 'none';
};
document.getElementById('collectPaymentForm').onsubmit = function(e) {
	e.preventDefault();
	const msgBox = document.getElementById('collectPaymentMsg');
	msgBox.style.display = 'block';
	msgBox.textContent = '';
	const amount = parseFloat(document.getElementById('collectAmount').value);
	const maxDues = parseFloat(document.getElementById('collectAmount').max);
	if (isNaN(amount) || amount <= 0) {
		msgBox.textContent = 'Enter a valid amount.';
		return;
	}
	if (amount > maxDues) {
		msgBox.textContent = 'Amount cannot be more than current dues.';
		return;
	}
	const formData = new FormData(document.getElementById('collectPaymentForm'));
	fetch('collect-payment.php', {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(data => {
		if (data.success) {
			msgBox.style.color = '#28a745';
			msgBox.textContent = 'Payment collected!';
			setTimeout(() => {
				document.getElementById('collectPaymentModalBg').style.display = 'none';
				// Refresh selected customer box and dues
				const customerId = document.getElementById('collectCustomerId').value;
				if (customerId) {
					fetch('get_customer_dues.php?id=' + encodeURIComponent(customerId))
						.then(res => res.json())
						.then(data => {
							if (data && !data.error) {
								const selectedBox = document.getElementById('selectedCustomerBox');
								selectedBox.innerHTML = `<b>Selected Customer:</b><br>Name: ${data.name}<br><span id='customerDuesInfo' style='color:#888;'></span><div id='collectDueBtnBox'></div>`;
								// Update dues info
								document.getElementById('customerDuesInfo').innerHTML = `
									<div style='margin-top:6px;'>
										<b>Total Invoiced:</b> ₹${data.total_invoiced.toFixed(2)}<br>
										<b>Total Paid:</b> ₹${data.total_paid.toFixed(2)}<br>
										<b>Total Dues:</b> <span style='color:${data.total_dues > 0 ? '#b30000' : '#228B22'};'>₹${data.total_dues.toFixed(2)}</span>
									</div>
								`;
								// Add Collect button if dues > 0
								const collectBox = document.getElementById('collectDueBtnBox');
								if (collectBox) {
									if (data.total_dues > 0) {
										collectBox.innerHTML = `<button id='collectDueBtn' style='margin-top:10px;background:#1a8917;color:#fff;padding:7px 18px;border:none;border-radius:6px;font-weight:600;cursor:pointer;'>Collect</button>`;
										document.getElementById('collectDueBtn').onclick = function(e) {
											e.preventDefault();
											e.stopPropagation();
											showCollectPaymentBox(customerId);
										};
									} else {
										collectBox.innerHTML = '';
									}
								}
							}
						});
				}
			}, 900);
		} else {
			msgBox.style.color = '#800000';
		// Only validate products if invoice form is being submitted, not for collect modal
			msgBox.textContent = data.error || 'Failed to collect payment.';
		}
	})
	.catch(() => {
		msgBox.style.color = '#800000';
		msgBox.textContent = 'Failed to collect payment.';
	});
};
</script>


</body>
</html>
