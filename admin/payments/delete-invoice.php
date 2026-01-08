<?php
// delete-invoice.php
session_start();
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
