<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<h2>Missing request ID.</h2>';
    exit;
}

// Fetch service request
$stmt = $pdo->prepare('SELECT * FROM service_requests WHERE id = ?');
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    echo '<h2>Service request not found.</h2>';
    exit;
}

// AJAX: Send note notification via Appointment Message template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_note_notification') {
    header('Content-Type: application/json');
    $noteText = trim($_POST['note_text'] ?? '');
    $mobile = $request['mobile'] ?? '';
    $customerName = $request['customer_name'] ?? 'Customer';
    
    if ($noteText === '') {
        echo json_encode(['success' => false, 'message' => 'Note text is required']);
        exit;
    }
    if (!$mobile) {
        echo json_encode(['success' => false, 'message' => 'Mobile number not available']);
        exit;
    }
    try {
        $result = sendWhatsAppNotification(
            'admin_custom_message',
            [
                'mobile' => $mobile,
                'name' => $customerName,
                'message' => $noteText
            ]
        );
        if ($result['success']) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to send']);
        }
    } catch (Throwable $e) {
        error_log('Send note notification failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Exception while sending']);
    }
    exit;
}

$statusOptions = ['Received', 'In Progress', 'Completed'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // Fetch latest service record BEFORE update
    $stmt = $pdo->prepare('SELECT service_status, customer_name, mobile, tracking_id, category_slug, selected_products FROM service_requests WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        error_log('Admin status update: Service request not found for ID ' . $id);
    } else {
        $oldStatus = $row['service_status'];
        $customerName = $row['customer_name'];
        $mobile = $row['mobile'];
        $trackingId = $row['tracking_id'];
        $categorySlug = $row['category_slug'];
        $selectedProducts = $row['selected_products'] ?? '[]';
        $newStatus = $_POST['service_status'];
        error_log("Admin status update: oldStatus=$oldStatus, newStatus=$newStatus, trackingId=$trackingId");
        if (in_array($newStatus, $statusOptions)) {
            // Update service_requests
            $stmt = $pdo->prepare('UPDATE service_requests SET service_status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $id]);
            // Update tracking
            $stmt2 = $pdo->prepare('UPDATE tracking SET service_status = ? WHERE tracking_id = ?');
            $stmt2->execute([$newStatus, $trackingId]);
            // Refresh data
            $request['service_status'] = $newStatus;
            $successMsg = 'Service status updated.';

            // WhatsApp notification only if status changed
            if ($oldStatus !== $newStatus) {
                require_once __DIR__ . '/../../helpers/send_whatsapp.php';
                $categoryTitles = [
                    'birth-child' => 'Birth & Child Services',
                    'marriage-matching' => 'Marriage & Matching',
                    'astrology-consultation' => 'Astrology Consultation',
                    'muhurat-event' => 'Muhurat & Event Guidance',
                    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
                ];
                $category = $categoryTitles[$categorySlug] ?? ucfirst(str_replace('-', ' ', $categorySlug));
                $trackingLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                    "://" . $_SERVER['HTTP_HOST'] . dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . "/track.php?tracking_id=" . urlencode($trackingId);
                error_log("Admin status update: Triggering WhatsApp for $mobile, status=$newStatus");
                
                // Special handling for "Completed" status
                if ($newStatus === 'Completed') {
                    try {
                        // Parse products list for message
                        $productsArray = json_decode($selectedProducts, true) ?: [];
                        $productsList = '';
                        if (is_array($productsArray) && count($productsArray) > 0) {
                            foreach ($productsArray as $prod) {
                                $qty = isset($prod['qty']) ? $prod['qty'] : 1;
                                $pstmt = $pdo->prepare("SELECT product_name FROM products WHERE id = ?");
                                $pstmt->execute([$prod['id'] ?? 0]);
                                $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
                                $prodName = $prow ? $prow['product_name'] : 'Service';
                                $productsList .= ($productsList ? ', ' : '') . $prodName;
                            }
                        } else {
                            $productsList = 'Service Completed';
                        }
                        
                        sendWhatsAppMessage(
                            $mobile,
                            'SERVICE_REQUEST_COMPLETED',
                            [
                                'name' => $customerName,
                                'tracking_id' => $trackingId,
                                'category' => $category,
                                'products_list' => $productsList,
                                'tracking_id' => $trackingId
                            ]
                        );
                        error_log("Service completed WhatsApp sent to $mobile for tracking ID $trackingId");
                    } catch (Throwable $e) {
                        error_log('Service completed WhatsApp failed: ' . $e->getMessage());
                    }
                } else {
                    try {
                        sendWhatsAppMessage(
                            $mobile,
                            'service_status_update',
                            'en',
                            [
                                'name' => $customerName,
                                'tracking_id' => $trackingId,
                                'category' => $category,
                                'status' => $newStatus,
                                'tracking_link' => $trackingLink
                            ]
                        );
                        error_log("Admin status update: WhatsApp message sent (or logged in test mode)");
                    } catch (Throwable $e) {
                        error_log('WhatsApp status update failed: ' . $e->getMessage());
                    }
                }
            } else {
                error_log("Admin status update: Status unchanged, WhatsApp not triggered");
            }
        }
    }
}
// Handle file upload
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files']) && !empty($_FILES['service_files']['name'][0])) {
    $trackingId = $request['tracking_id'];
    $uploadDir = __DIR__ . '/../../uploads/services/' . $trackingId . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $meta = [];
    foreach ($_FILES['service_files']['name'] as $i => $name) {
        $tmpName = $_FILES['service_files']['tmp_name'][$i];
        $type = $_FILES['service_files']['type'][$i];
        $error = $_FILES['service_files']['error'][$i];
        if ($error === UPLOAD_ERR_OK && in_array($type, $allowedTypes)) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $safeName = uniqid('file_') . '.' . $ext;
            $dest = $uploadDir . $safeName;
            if (move_uploaded_file($tmpName, $dest)) {
                $meta[] = [
                    'name' => $name,
                    'file' => $safeName,
                    'date' => date('Y-m-d H:i:s'),
                    'type' => $type
                ];
            }
        }
    }
    // Append to existing uploaded_files JSON
    $existing = [];
    if (!empty($request['uploaded_files'])) {
        $existing = json_decode($request['uploaded_files'], true) ?: [];
    }
    $allFiles = array_merge($existing, $meta);
    $stmt = $pdo->prepare('UPDATE service_requests SET uploaded_files = ? WHERE id = ?');
    $stmt->execute([json_encode($allFiles), $id]);
    $request['uploaded_files'] = json_encode($allFiles);
    $uploadMsg = count($meta) . ' file(s) uploaded.';

    // WhatsApp notification after successful upload
    if (count($meta) > 0) {
        // Fetch latest service record for WhatsApp data
        $stmt = $pdo->prepare('SELECT customer_name, mobile, tracking_id, category_slug, selected_products FROM service_requests WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $customerName = $row['customer_name'];
            $mobile = $row['mobile'];
            $trackingId = $row['tracking_id'];
            $categorySlug = $row['category_slug'];
            $selectedProducts = $row['selected_products'] ?? '[]';
            $categoryTitles = [
                'birth-child' => 'Birth & Child Services',
                'marriage-matching' => 'Marriage & Matching',
                'astrology-consultation' => 'Astrology Consultation',
                'muhurat-event' => 'Muhurat & Event Guidance',
                'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
            ];
            $category = $categoryTitles[$categorySlug] ?? ucfirst(str_replace('-', ' ', $categorySlug));
            
            // Parse products list for message
            $productsArray = json_decode($selectedProducts, true) ?: [];
            $productsList = '';
            if (is_array($productsArray) && count($productsArray) > 0) {
                foreach ($productsArray as $prod) {
                    $pstmt = $pdo->prepare("SELECT product_name FROM products WHERE id = ?");
                    $pstmt->execute([$prod['id'] ?? 0]);
                    $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
                    $prodName = $prow ? $prow['product_name'] : 'Service';
                    $productsList .= ($productsList ? ', ' : '') . $prodName;
                }
            } else {
                $productsList = 'Service Files';
            }
            
            // Get first uploaded file path for download button
            $firstFile = $meta[0] ?? null;
            $filePath = '';
            if ($firstFile) {
                $filePath = $trackingId . '/' . $firstFile['file'];
            }
            
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            try {
                sendWhatsAppMessage(
                    $mobile,
                    'SERVICE_REQUEST_FILE_UPLOADED',
                    [
                        'name' => $customerName,
                        'tracking_id' => $trackingId,
                        'category' => $category,
                        'products_list' => $productsList,
                        'file_path' => $filePath
                    ]
                );
                error_log("File upload WhatsApp sent to $mobile for tracking ID $trackingId, file: $filePath");
            } catch (Throwable $e) {
                error_log('WhatsApp file upload failed: ' . $e->getMessage());
            }
        } else {
            error_log('Admin file upload: Service record not found for WhatsApp notification');
        }
    }
}
// Decode form data
$formData = [];
if (!empty($request['form_data'])) {
    $formData = json_decode($request['form_data'], true);
}

// Handle file removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_file']) && isset($_POST['file_name'])) {
    $fileToRemove = $_POST['file_name'];
    $trackingId = $request['tracking_id'];
    $uploadDir = __DIR__ . '/../../uploads/services/' . $trackingId . '/';
    $uploadedFiles = [];
    if (!empty($request['uploaded_files'])) {
        $uploadedFiles = json_decode($request['uploaded_files'], true) ?: [];
    }
    $updatedFiles = [];
    foreach ($uploadedFiles as $file) {
        if ($file['file'] === $fileToRemove) {
            $filePath = $uploadDir . $file['file'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            continue; // skip this file
        }
        $updatedFiles[] = $file;
    }
    // Update DB
    $stmt = $pdo->prepare('UPDATE service_requests SET uploaded_files = ? WHERE id = ?');
    $stmt->execute([json_encode($updatedFiles), $id]);
    // Redirect to reload updated data
    header('Location: view.php?id=' . urlencode($id) . '&msg=removed');
    exit;
}
// Decode uploaded files
$uploadedFiles = [];
if (!empty($request['uploaded_files'])) {
    $uploadedFiles = json_decode($request['uploaded_files'], true) ?: [];
}

// Fetch admin notes for this service
$stmt = $pdo->prepare('SELECT note_text, created_at FROM admin_notes WHERE service_request_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$adminNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Service Request</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 700px; margin: 0 auto; padding: 28px 12px; }
        h1 { font-size: 1.3em; margin-bottom: 18px; color: #800000; }
        .details-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .details-table th, .details-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; font-size: 1em; }
        .details-table th { background: #f9eaea; color: #800000; font-weight: 700; width: 180px; }
        .details-table tr:last-child td { border-bottom: none; }
        .status-badge { padding: 2px 12px; border-radius: 8px; font-weight: 600; font-size: 0.98em; background: #f7e7e7; color: #800000; display: inline-block; }
        .status-badge.status-paid { background: #e5ffe5; color: #1a8917; }
        .status-badge.status-received { background: #e5f0ff; color: #0056b3; }
        .status-badge.status-completed { background: #e5ffe5; color: #1a8917; }
        .status-badge.status-in\ progress { background: #fffbe5; color: #b36b00; }
        .form-bar { margin-bottom: 18px; }
        .form-bar label { font-weight: 600; margin-right: 8px; }
        .form-bar select { padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 1em; }
        .form-bar button { background: #800000; color: #fff; border: none; border-radius: 8px; padding: 8px 18px; font-size: 0.98em; font-weight: 600; text-align: center; text-decoration: none; box-shadow: 0 2px 8px #80000022; transition: background 0.15s; display: inline-block; cursor: pointer; margin-left: 10px; }
        .form-bar button:active { background: #5a0000; }
        .success-msg { color: #1a8917; font-weight: 600; margin-bottom: 12px; }
        @media (max-width: 700px) {
            .admin-container { padding: 12px 2px; }
            .details-table th, .details-table td { padding: 8px 4px; font-size: 0.97em; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>View Service Request</h1>
    <?php if (!empty($successMsg)): ?><div class="success-msg"><?php echo $successMsg; ?></div><?php endif; ?>
    <table class="details-table">
        <tr><th>Tracking ID</th><td><?php echo htmlspecialchars($request['tracking_id']); ?></td></tr>
        <tr><th>Customer Name</th><td><?php echo htmlspecialchars($request['customer_name']); ?></td></tr>
        <tr><th>Mobile</th><td><?php echo htmlspecialchars($request['mobile']); ?></td></tr>
        <tr><th>Email</th><td><?php echo htmlspecialchars($request['email']); ?></td></tr>
        <tr><th>Category</th><td><?php
            $categoryTitles = [
                'birth-child' => 'Birth & Child Services',
                'marriage-matching' => 'Marriage & Matching',
                'astrology-consultation' => 'Astrology Consultation',
                'muhurat-event' => 'Muhurat & Event Guidance',
                'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
            ];
            $cat = $request['category_slug'];
            echo isset($categoryTitles[$cat]) ? $categoryTitles[$cat] : htmlspecialchars($cat);
        ?></td></tr>
        <tr><th>Total Amount</th><td>₹<?php echo number_format($request['total_amount'], 2); ?></td></tr>
        <?php
        // Check actual payment status from payments table
        // Use payment_status field from service_requests for payment status
        $actualPaid = (isset($request['payment_status']) && strtolower($request['payment_status']) === 'paid');
        $discount = isset($request['discount']) ? (float)$request['discount'] : 0;
        $totalPaid = $request['total_amount'] - $discount;
        ?>
        <tr><th>Discount Given</th><td>₹<?php echo number_format($discount, 2); ?></td></tr>
        <tr><th>Total Paid</th><td style="color:#1a8917;font-weight:600;">₹<?php echo number_format($totalPaid, 2); ?></td></tr>
        <tr><th>Payment Status</th><td><span class="status-badge status-<?php echo $actualPaid ? 'paid' : 'unpaid'; ?>"><?php echo $actualPaid ? 'Paid' : 'Unpaid'; ?></span></td></tr>
        <tr><th>Service Status</th><td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['service_status'])); ?>"><?php echo htmlspecialchars($request['service_status']); ?></span></td></tr>
        <tr><th>Created Date</th><td><?php echo date('d-m-Y', strtotime($request['created_at'])); ?></td></tr>
    </table>
        <h2 style="font-size:1.1em;color:#800000;margin:18px 0 8px 0;">Customer Submitted Details</h2>
        <?php if ($formData): ?>
        <?php
        // Standard field order and icons
        $fieldOrder = [
            'dob' => ['label' => 'Date of Birth', 'icon' => '📅'],
            'time_of_birth' => ['label' => 'Time of Birth', 'icon' => '⏰'],
            'place_of_birth' => ['label' => 'Place of Birth', 'icon' => '📍'],
            'father_name' => ['label' => "Father's Name", 'icon' => '👨'],
            'mother_name' => ['label' => "Mother's Name", 'icon' => '👩'],
            'spouse_name' => ['label' => "Spouse's Name", 'icon' => '💍'],
            'child_name' => ['label' => "Child's Name", 'icon' => '👶'],
            'query' => ['label' => 'Query/Details', 'icon' => '📝'],
            'gender' => ['label' => 'Gender', 'icon' => '⚧'],
            'occupation' => ['label' => 'Occupation', 'icon' => '💼'],
            'address' => ['label' => 'Address', 'icon' => '🏠'],
            'state' => ['label' => 'State', 'icon' => '🏞️'],
            'country' => ['label' => 'Country', 'icon' => '🌏'],
            'notes' => ['label' => 'Notes', 'icon' => '🗒️'],
        ];
        $skipFields = ['product_ids', 'qty', 'mobile', 'email', 'category', 'category_slug', 'full_name', 'name', 'city'];
        $shownLabels = [];
        ?>
        <table class="details-table" style="margin-bottom:18px;">

        <?php
        // Show standard fields in order
        foreach ($fieldOrder as $key => $meta):
            if (isset($formData[$key]) && !in_array($key, $skipFields)) {
                $shownLabels[] = strtolower($key);
        ?>
            <tr>
                <th><?php echo $meta['icon'] . ' ' . htmlspecialchars($meta['label']); ?></th>
                <td><?php echo is_array($formData[$key]) ? implode(', ', array_map('htmlspecialchars', $formData[$key])) : htmlspecialchars($formData[$key]); ?></td>
            </tr>
        <?php
            }
        endforeach;
        // Show remaining fields
        foreach ($formData as $label => $value):
            $labelLower = strtolower($label);
            if (in_array($labelLower, $skipFields) || in_array($labelLower, $shownLabels)) continue;
        ?>
            <tr>
                <th><?php echo htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $label))); ?></th>
                <td><?php echo is_array($value) ? implode(', ', array_map('htmlspecialchars', $value)) : htmlspecialchars($value); ?></td>
            </tr>
        <?php endforeach; ?>
        </table>
        <?php else: ?>
            <div style="color:#888;font-size:0.98em;margin-bottom:18px;">No form data submitted.</div>
        <?php endif; ?>

            <!-- Products Chosen Section -->
            <h2 style="font-size:1.1em;color:#800000;margin:18px 0 8px 0;">Products Chosen</h2>
            <?php
            $products = [];
            if (!empty($request['selected_products'])) {
                $products = json_decode($request['selected_products'], true);
            }
            if ($products && is_array($products) && count($products) > 0): ?>
                <table class="details-table" style="margin-bottom:18px;">
                    <tr>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Price (₹)</th>
                        <th>Subtotal (₹)</th>
                    </tr>
                    <?php
                    $grandTotal = 0;
                    foreach ($products as $prod):
                        $name = '';
                        if (isset($prod['name']) && $prod['name']) {
                            $name = $prod['name'];
                        } elseif (isset($prod['product_name']) && $prod['product_name']) {
                            $name = $prod['product_name'];
                        } elseif (isset($prod['id'])) {
                            static $productNameCache = [];
                            $pid = (int)$prod['id'];
                            if (!isset($productNameCache[$pid])) {
                                $pstmt = $pdo->prepare('SELECT product_name FROM products WHERE id = ?');
                                $pstmt->execute([$pid]);
                                $prow = $pstmt->fetch();
                                $productNameCache[$pid] = $prow ? $prow['product_name'] : 'Product#'.$pid;
                            }
                            $name = $productNameCache[$pid];
                        }
                        $qty = isset($prod['qty']) ? (int)$prod['qty'] : 1;
                        $price = isset($prod['price']) ? (float)$prod['price'] : 0;
                        $subtotal = $qty * $price;
                        $grandTotal += $subtotal;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($name); ?></td>
                        <td><?php echo htmlspecialchars($qty); ?></td>
                        <td>₹<?php echo number_format($price, 2); ?></td>
                        <td>₹<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th colspan="3" style="text-align:right;">Total Amount</th>
                        <th>₹<?php echo number_format($grandTotal, 2); ?></th>
                    </tr>
                </table>
            <?php else: ?>
                <div style="color:#888;font-size:0.98em;margin-bottom:18px;">No products selected.</div>
            <?php endif; ?>

        <h2 style="font-size:1.1em;color:#800000;margin:18px 0 8px 0;">Upload Service Files</h2>
        <?php if (!empty($uploadMsg)): ?><div class="success-msg"><?php echo $uploadMsg; ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="margin-bottom:18px;">
            <input type="file" name="service_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="margin-bottom:8px;">
            <button type="submit" name="upload_files">Upload Files</button>
        </form>
        <h3 style="font-size:1em;color:#800000;margin:10px 0 6px 0;">Uploaded Files</h3>
            <?php if ($uploadedFiles): ?>
            <table class="details-table" style="margin-bottom:18px;">
                <tr><th>File Name</th><th>Download</th><th>Upload Date</th><th>Action</th></tr>
                <?php foreach ($uploadedFiles as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['name']); ?></td>
                        <td><a href="../../uploads/services/<?php echo htmlspecialchars($request['tracking_id']); ?>/<?php echo htmlspecialchars($file['file']); ?>" target="_blank" style="color:#800000;text-decoration:underline;">Download</a></td>
                        <td><?php echo date('d-m-Y H:i', strtotime($file['date'])); ?></td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this file?');">
                                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($file['file']); ?>">
                                <button type="submit" name="remove_file" style="background:#c00;color:#fff;border:none;border-radius:6px;padding:4px 12px;font-size:0.95em;cursor:pointer;">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
                <div style="color:#888;font-size:0.98em;margin-bottom:18px;">No files uploaded yet.</div>
            <?php endif; ?>

    <!-- Internal Admin Notes Section -->
    <h2 style="font-size:1.1em;color:#800000;margin:18px 0 8px 0;">Internal Admin Notes</h2>
    <div id="notes_container">
    <?php if ($adminNotes && count($adminNotes) > 0): ?>
        <div style="background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;margin-bottom:18px;">
            <?php foreach ($adminNotes as $note): ?>
                <div style="background:#fff;border-left:3px solid #800000;padding:10px;margin-bottom:10px;border-radius:4px;">
                    <div style="color:#333;font-size:0.98em;margin-bottom:6px;"><?php echo nl2br(htmlspecialchars($note['note_text'])); ?></div>
                    <div style="color:#999;font-size:0.85em;font-style:italic;"><?php echo date('d M Y, h:i A', strtotime($note['created_at'])); ?></div>
                        <?php $noteKey = 'note_' . md5($note['created_at'] . $note['note_text']); ?>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap;">
                            <button type="button" onclick="sendNoteNotification('<?php echo htmlspecialchars(addslashes($request['mobile'])); ?>', '<?php echo htmlspecialchars(addslashes($note['note_text'])); ?>', '<?php echo $noteKey; ?>')" style="background:#25D366;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:0.95em;font-weight:600;cursor:pointer;">Send Notification</button>
                            <span id="<?php echo $noteKey; ?>" class="note-status" style="color:#1a8917;font-size:0.9em;"></span>
                        </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="color:#888;font-size:0.98em;margin-bottom:18px;background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;">No internal notes yet.</div>
    <?php endif; ?>
    </div>

    <!-- Add Internal Note Form -->
    <div style="background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;margin-bottom:18px;">
        <textarea id="note_text" placeholder="Add internal note (admin only)" style="width:100%;padding:10px;border:1px solid #f3caca;border-radius:6px;font-size:0.98em;font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;min-height:80px;box-sizing:border-box;"></textarea>
        <button onclick="saveNote()" style="background:#800000;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:0.98em;font-weight:600;cursor:pointer;margin-top:8px;">Save Note</button>
        <span id="note_status" style="margin-left:10px;font-size:0.95em;"></span>
    </div>

    <script>
        function sendNoteNotification(mobile, noteText, statusId) {
            if (!mobile) {
                alert('Customer mobile number not available.');
                return;
            }
            if (statusId) {
                const el = document.getElementById(statusId);
                if (el) { el.style.color = '#888'; el.textContent = 'Sending...'; }
            }
            const payload = new FormData();
            payload.append('action', 'send_note_notification');
            payload.append('note_text', noteText);
            fetch(window.location.href, {
                method: 'POST',
                body: payload
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    if (statusId) {
                        const el = document.getElementById(statusId);
                        if (el) { el.style.color = '#1a8917'; el.textContent = 'Sent'; }
                    } else {
                        alert('Notification sent.');
                    }
                } else {
                    if (statusId) {
                        const el = document.getElementById(statusId);
                        if (el) { el.style.color = '#c00'; el.textContent = data.message || 'Failed'; }
                    } else {
                        alert(data.message || 'Failed to send notification');
                    }
                }
            }).catch(err => {
                console.error(err);
                if (statusId) {
                    const el = document.getElementById(statusId);
                    if (el) { el.style.color = '#c00'; el.textContent = 'Error'; }
                } else {
                    alert('Error sending notification');
                }
            });
        }
    function saveNote() {
        var noteText = document.getElementById('note_text').value.trim();
        var statusEl = document.getElementById('note_status');
        
        if (noteText === '') {
            statusEl.style.color = '#c00';
            statusEl.textContent = 'Note cannot be empty';
            return;
        }
        
        statusEl.style.color = '#888';
        statusEl.textContent = 'Saving...';
        
        var formData = new FormData();
        formData.append('service_request_id', '<?php echo $id; ?>');
        formData.append('note_text', noteText);
        
        fetch('ajax_add_note.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusEl.style.color = '#1a8917';
                statusEl.textContent = 'Note saved successfully!';
                document.getElementById('note_text').value = '';
                refreshNotes();
                setTimeout(() => {
                    statusEl.textContent = '';
                }, 3000);
            } else {
                statusEl.style.color = '#c00';
                statusEl.textContent = data.message || 'Failed to save note';
            }
        })
        .catch(error => {
            statusEl.style.color = '#c00';
            statusEl.textContent = 'Error saving note';
            console.error('Error:', error);
        });
    }
    
    function refreshNotes() {
        fetch('ajax_get_notes.php?service_request_id=<?php echo $id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notes) {
                var notesContainer = document.getElementById('notes_container');
                var mobile = <?php echo json_encode($request['mobile']); ?>;
                if (data.notes.length > 0) {
                    var html = '<div style="background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;margin-bottom:18px;">';
                    data.notes.forEach(note => {
                        var noteKey = 'note_' + Math.random().toString(36).substring(2, 10);
                        var mobileEscaped = escapeJs(mobile);
                        var noteTextEscaped = escapeJs(note.note_text);
                        var date = new Date(note.created_at);
                        var formattedDate = date.toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) + ', ' + 
                                           date.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: true});
                        html += '<div style="background:#fff;border-left:3px solid #800000;padding:10px;margin-bottom:10px;border-radius:4px;">';
                        html += '<div style="color:#333;font-size:0.98em;margin-bottom:6px;">' + escapeHtml(note.note_text).replace(/\n/g, '<br>') + '</div>';
                        html += '<div style="color:#999;font-size:0.85em;font-style:italic;">' + formattedDate + '</div>';
                        html += '<div style="display:flex;align-items:center;gap:10px;margin-top:8px;flex-wrap:wrap;">';
                        html += `<button type="button" onclick="sendNoteNotification('${mobileEscaped}', '${noteTextEscaped}', '${noteKey}')" style="background:#25D366;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:0.95em;font-weight:600;cursor:pointer;">Send Notification</button>`;
                        html += '<span id="' + noteKey + '" class="note-status" style="color:#1a8917;font-size:0.9em;"></span>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    notesContainer.innerHTML = html;
                } else {
                    notesContainer.innerHTML = '<div style="color:#888;font-size:0.98em;margin-bottom:18px;background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;">No internal notes yet.</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing notes:', error);
        });
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    function escapeJs(text) {
        if (!text) return '';
        return text.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '');
    }
    </script>

    <?php if ($request['category_slug'] === 'appointment'): ?>
        <div style="margin:18px 0 18px 0;font-weight:600;">
            Service Status: <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['service_status'])); ?>"><?php echo htmlspecialchars($request['service_status']); ?></span>
        </div>
    <?php else: ?>
        <form class="form-bar" method="post">
            <label for="service_status">Update Service Status:</label>
            <select name="service_status" id="service_status">
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php if ($request['service_status'] === $opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status">Update</button>
        </form>
        <a href="service-request-list.php" style="color:#800000;text-decoration:underline;font-size:0.98em;">&larr; Back to List</a>
    <?php endif; ?>
</div>
</body>
</html>
