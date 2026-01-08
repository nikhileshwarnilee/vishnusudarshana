<?php
// edit-invoice.php

session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';

// Handle POST and redirect before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	$customer_id = (int)$_POST['customer_id'];
	$date = $_POST['invoice_date'];
	$note = $_POST['invoice_note'];
	$total_qty = isset($_POST['total_qty']) ? (int)$_POST['total_qty'] : 0;
	$total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
	$pdo->prepare("UPDATE invoices SET customer_id=?, invoice_date=?, notes=?, total_qty=?, total_amount=? WHERE id=?")
		->execute([$customer_id, $date, $note, $total_qty, $total_amount, $id]);
	$pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$id]);
	if (!empty($_POST['product_name'])) {
		for ($i=0; $i<count($_POST['product_name']); $i++) {
			$name = $_POST['product_name'][$i];
			$qty = (int)$_POST['product_qty'][$i];
			$amt = (float)$_POST['product_amount'][$i];
			if ($name && $qty > 0) {
				$pdo->prepare("INSERT INTO invoice_items (invoice_id, product_name, qty, amount) VALUES (?,?,?,?)")
					->execute([$id, $name, $qty, $amt]);
			}
		}
	}
	header('Location: invoice-list.php?msg=updated');
	exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { echo '<div style="padding:40px; color:#800000;">Invoice not found.</div>'; exit; }
$items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/top-menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Edit Invoice</title>
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.form-box { background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(128,0,0,0.10), 0 1.5px 6px rgba(0,0,0,0.04); padding:38px 36px 30px 36px; width:100%; max-width:700px; margin:32px auto; border:1px solid #ececec; }
		label { font-weight:600; color:#333; margin-bottom:4px; display:block; }
		input[type="text"], input[type="number"], input[type="date"], textarea { width:100%; padding:11px 12px; border:1px solid #d2d2d2; border-radius:6px; font-size:1em; margin-top:6px; margin-bottom:14px; background:#fafbfc; transition: border 0.2s; }
		input[type="text"]:focus, input[type="number"]:focus, input[type="date"]:focus, textarea:focus { border:1.5px solid #800000; outline:none; background:#fff; }
		.product-row { background:#f8f8fa; border-radius:7px; padding:10px 8px 10px 8px; margin-bottom:8px; box-shadow:0 1px 3px rgba(128,0,0,0.03); }
		.addProductRowBtn { background:linear-gradient(90deg, #28a745 60%, #43c06d 100%); color:#fff; border:none; border-radius:4px; padding:8px 16px; font-size:1.3em; font-weight:700; cursor:pointer; box-shadow:0 1px 2px rgba(40,167,69,0.08); transition:background 0.2s; }
		.addProductRowBtn:hover { background:linear-gradient(90deg, #43c06d 60%, #28a745 100%); }
		.removeProductRowBtn { background:linear-gradient(90deg, #dc3545 60%, #e35d6a 100%); color:#fff; border:none; border-radius:4px; padding:8px 16px; font-size:1.3em; font-weight:700; cursor:pointer; margin-left:2px; box-shadow:0 1px 2px rgba(220,53,69,0.08); transition:background 0.2s; }
		.removeProductRowBtn:hover { background:linear-gradient(90deg, #e35d6a 60%, #dc3545 100%); }
		#productTotals { margin-top:18px; font-weight:700; color:#333; font-size:1.08em; background:#f6f6f6; border-radius:6px; padding:10px 0 10px 0; text-align:right; }
	</style>
</head>
<body>
<div class="form-box">
	<h1 style="margin:0 0 18px 0; font-size:1.7em; color:#800000; font-weight:700; letter-spacing:0.5px;">Edit Invoice #<?= $inv['id'] ?></h1>
	<form method="post" id="editInvoiceForm" autocomplete="off">
		<label for="customer_id">Customer</label>
		<select name="customer_id" id="customer_id">
			<?php foreach ($customers as $c): ?>
				<option value="<?= $c['id'] ?>" <?= $inv['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
			<?php endforeach; ?>
		</select>
		<label for="invoice_date">Invoice Date</label>
		<input type="date" name="invoice_date" id="invoice_date" value="<?= htmlspecialchars($inv['invoice_date']) ?>">
		<label for="invoice_note">Invoice Note</label>
		<textarea name="invoice_note" id="invoice_note" rows="3"><?= htmlspecialchars($inv['notes']) ?></textarea>
		<label>Add Product/Service</label>
		<div id="productRows">
			<?php foreach ($items as $i => $item): ?>
			<div class="product-row" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:8px;">
				<input type="text" name="product_name[]" value="<?= htmlspecialchars($item['product_name']) ?>" placeholder="Product/Service Name">
				<input type="number" name="product_qty[]" class="product-qty" value="<?= $item['qty'] ?>" placeholder="Qty" min="1">
				<input type="number" name="product_amount[]" class="product-amount" value="<?= $item['amount'] ?>" placeholder="Amount" min="0" step="0.01">
				<?php if ($i == 0): ?>
					<button type="button" class="addProductRowBtn">+</button>
				<?php else: ?>
					<button type="button" class="removeProductRowBtn">-</button>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
		<div id="productTotals">
			Total Qty: <span id="totalQty">0</span> &nbsp; | &nbsp; Total Amount: ₹<span id="totalAmount">0.00</span>
		</div>
        <input type="hidden" name="total_qty" id="hiddenTotalQty">
        <input type="hidden" name="total_amount" id="hiddenTotalAmount">
		<div style="margin-top:32px; text-align:right;">
			<button type="submit" style="background:linear-gradient(90deg, #800000 60%, #a83232 100%); color:#fff; border:none; border-radius:6px; padding:13px 38px; font-size:1.13em; font-weight:800; cursor:pointer; box-shadow:0 2px 8px rgba(128,0,0,0.08);">Update Invoice</button>
		</div>
	</form>
</div>
<script>
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
document.getElementById('productRows').addEventListener('input', function(e) {
	if (e.target.name === 'product_qty[]' || e.target.name === 'product_amount[]') {
		updateProductTotals();
	}
});
document.addEventListener('click', function(e) {
	if (e.target.classList.contains('addProductRowBtn')) {
		const productRows = document.getElementById('productRows');
		const row = document.createElement('div');
		row.className = 'product-row';
		row.style.display = 'flex';
		row.style.gap = '10px';
		row.style.marginBottom = '8px';
		row.style.flexWrap = 'wrap';
		row.style.alignItems = 'center';
		row.innerHTML = `
			<input type="text" name="product_name[]" placeholder="Product/Service Name">
			<input type="number" name="product_qty[]" placeholder="Qty" min="1" value="1">
			<input type="number" name="product_amount[]" placeholder="Amount" min="0" step="0.01">
			<button type="button" class="removeProductRowBtn">-</button>
		`;
		productRows.appendChild(row);
		updateProductTotals();
	}
	if (e.target.classList.contains('removeProductRowBtn')) {
		const row = e.target.closest('.product-row');
		if (row && row.parentNode.children.length > 1) {
			row.remove();
			updateProductTotals();
		}
	}
});
updateProductTotals();
</script>
<script>
// Ensure hidden fields are set before submit
document.getElementById('editInvoiceForm').addEventListener('submit', function(e) {
	document.getElementById('hiddenTotalQty').value = document.getElementById('totalQty').textContent;
	document.getElementById('hiddenTotalAmount').value = document.getElementById('totalAmount').textContent;
});
</script>
</body>
</html>
