<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

// Utility function for time formatting
if (!function_exists('minsToTime')) {
    function minsToTime($mins) {
        $h = floor($mins / 60);
        $m = $mins % 60;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 == 0) $h12 = 12;
        return sprintf('%02d:%02d %s', $h12, $m, $ampm);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        // Fetch current booking details
        $stmt = $pdo->prepare("SELECT token_no, token_date, location, name, mobile FROM token_bookings WHERE id = ?");
        $stmt->execute([$id]);
        $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($currentBooking) {
            // Mark as completed
            $updateStmt = $pdo->prepare("UPDATE token_bookings SET status = 'completed' WHERE id = ?");
            $updateStmt->execute([$id]);
            
            // Send reminder to token +5 (only for Solapur)
            if (strtolower(trim($currentBooking['location'])) === 'solapur') {
                $nextTokenNo = (int)$currentBooking['token_no'] + 5;
                
                // Find the +5 token
                $nextStmt = $pdo->prepare("SELECT id, token_no, name, mobile, token_date FROM token_bookings WHERE token_no = ? AND token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?))");
                $nextStmt->execute([$nextTokenNo, $currentBooking['token_date'], $currentBooking['location']]);
                $nextBooking = $nextStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($nextBooking && $nextBooking['mobile']) {
                    // Get slot info for time calculation
                    $slotStmt = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?))");
                    $slotStmt->execute([$currentBooking['token_date'], $currentBooking['location']]);
                    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $revisedSlot = '-';
                    if ($slot && $slot['from_time'] && $slot['to_time'] && (int)$slot['total_tokens'] > 0) {
                        $fromParts = explode(':', $slot['from_time']);
                        $toParts = explode(':', $slot['to_time']);
                        if (count($fromParts) >= 2 && count($toParts) >= 2) {
                            $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
                            $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
                            $diffMins = $toMins - $fromMins;
                            if ($diffMins > 0) {
                                $perMinsCalc = floor($diffMins / (int)$slot['total_tokens']);
                                if ($perMinsCalc > 0) {
                                    // Calculate revised time from current time
                                    $nowMins = ((int)date('G')) * 60 + (int)date('i');
                                    $rowIndex = $nextTokenNo - 1;
                                    $revStart = $nowMins + ($rowIndex * $perMinsCalc);
                                    $revEnd = $nowMins + (($rowIndex + 1) * $perMinsCalc);
                                    $revisedSlot = minsToTime($revStart) . ' - ' . minsToTime($revEnd);
                                }
                            }
                        }
                    }
                    
                    // Get current token being served (last completed)
                    $currentTokenStmt = $pdo->prepare("SELECT MAX(token_no) as current_token FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND status = 'completed'");
                    $currentTokenStmt->execute([$currentBooking['token_date'], $currentBooking['location']]);
                    $currentTokenResult = $currentTokenStmt->fetch(PDO::FETCH_ASSOC);
                    $currentToken = $currentTokenResult['current_token'] ?? 0;
                    
                    // Format date
                    $dateObj = DateTime::createFromFormat('Y-m-d', $currentBooking['token_date']);
                    $formattedDate = $dateObj ? $dateObj->format('d-M-Y') : $currentBooking['token_date'];
                    $dateTimeSlot = $formattedDate . ' | ' . $revisedSlot;
                    
                    // Send WhatsApp reminder using Marathi template
                    require_once __DIR__ . '/../../helpers/send_whatsapp.php';
                    try {
                        sendWhatsAppMessage(
                            $nextBooking['mobile'],
                            'token_update_marathi',
                            [
                                'name' => $nextBooking['name'],
                                'token_no' => $nextTokenNo,
                                'revised_slot' => $dateTimeSlot,
                                'current_token' => $currentToken
                            ]
                        );
                    } catch (Throwable $e) {
                        error_log('WhatsApp token reminder failed: ' . $e->getMessage());
                    }
                }
            }
            
            echo json_encode(['success' => true]);
            exit;
        }
    }
}
echo json_encode(['success' => false]);
exit;
