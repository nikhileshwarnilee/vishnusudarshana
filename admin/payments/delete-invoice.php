<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
// delete-invoice.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) {
	$pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
	$pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
	header('Location: invoice-list.php?msg=deleted');
	exit;
} else {
	header('Location: invoice-list.php?msg=notfound');
	exit;
}



