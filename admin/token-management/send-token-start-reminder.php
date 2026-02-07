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
    $date = $_POST['date'] ?? '';
    $location = $_POST['location'] ?? '';
    
    if (!$date || !$location) {
        echo json_encode(['success' => false, 'message' => 'Missing date or location']);
        exit;
    }
    
    // Debug: log the location received
    error_log('send-token-start-reminder: Date=' . $date . ', Location=' . $location . ' (trimmed: ' . strtolower(trim($location)) . ')');
    
    // Only allow for today's date
    $today = date('Y-m-d');
    if ($date !== $today) {
        echo json_encode(['success' => false, 'message' => 'Can only send reminders for today']);
        exit;
    }
    
    // Get slot info
    $slotStmt = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?))");
    $slotStmt->execute([$date, $location]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$slot) {
        echo json_encode(['success' => false, 'message' => 'Slot not found']);
        exit;
    }
    
    $perMinsCalc = 0;
    if ($slot && $slot['from_time'] && $slot['to_time'] && (int)$slot['total_tokens'] > 0) {
        $fromParts = explode(':', $slot['from_time']);
        $toParts = explode(':', $slot['to_time']);
        if (count($fromParts) >= 2 && count($toParts) >= 2) {
            $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
            $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
            $diffMins = $toMins - $fromMins;
            if ($diffMins > 0) {
                $perMinsCalc = floor($diffMins / (int)$slot['total_tokens']);
            }
        }
    }
    
    if ($perMinsCalc <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid slot time calculation']);
        exit;
    }
    
    // Get tokens 1-5 for this date and location
    $tokensStmt = $pdo->prepare("SELECT id, token_no, name, mobile FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND token_no IN (1, 2, 3, 4, 5) ORDER BY token_no ASC");
    $tokensStmt->execute([$date, $location]);
    $tokens = $tokensStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('send-token-start-reminder: Found ' . count($tokens) . ' tokens for date ' . $date . ' and location ' . $location);
    
    if (empty($tokens)) {
        error_log('send-token-start-reminder: No tokens 1-5 found for date ' . $date . ' and location ' . $location);
        echo json_encode(['success' => false, 'message' => 'No tokens 1-5 found']);
        exit;
    }
    
    // Format date
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    $formattedDate = $dateObj ? $dateObj->format('d-M-Y') : $date;
    
    // Get current token being served (last completed or current)
    $currentTokenStmt = $pdo->prepare("SELECT MAX(token_no) as current_token FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND status = 'completed'");
    $currentTokenStmt->execute([$date, $location]);
    $currentTokenResult = $currentTokenStmt->fetch(PDO::FETCH_ASSOC);
    $currentToken = $currentTokenResult['current_token'] ?? 0;
    
    // Send messages to tokens 1-5
    $sentCount = 0;
    $failedCount = 0;
    $failedTokens = [];
    require_once __DIR__ . '/../../helpers/send_whatsapp.php';
    
    foreach ($tokens as $t) {
        $tokenNo = (int)$t['token_no'];
        $rowIndex = $tokenNo - 1;
        
        // Calculate revised time from current time
        $nowMins = ((int)date('G')) * 60 + (int)date('i');
        $revStart = $nowMins + ($rowIndex * $perMinsCalc);
        $revEnd = $nowMins + (($rowIndex + 1) * $perMinsCalc);
        $revisedSlot = minsToTime($revStart) . ' - ' . minsToTime($revEnd);
        $dateTimeSlot = $formattedDate . ' | ' . $revisedSlot;
        
        if ($t['mobile']) {
            try {
                // Use location-specific template
                $template = 'token_update_marathi'; // Default for Solapur
                if (strtolower(trim($location)) === 'hyderabad') {
                    $template = 'token_update_telugu';
                }
                
                error_log('Token ' . $tokenNo . ': Sending via template=' . $template . ' to ' . $t['mobile']);
                
                $result = sendWhatsAppMessage(
                    $t['mobile'],
                    $template,
                    [
                        'name' => $t['name'],
                        'token_no' => $tokenNo,
                        'revised_slot' => $dateTimeSlot,
                        'current_token' => $currentToken
                    ]
                );
                
                if ($result['success']) {
                    $sentCount++;
                    error_log('Token ' . $tokenNo . ': SUCCESS');
                } else {
                    $failedCount++;
                    $failedTokens[] = $tokenNo;
                    error_log('Token ' . $tokenNo . ': FAILED - ' . $result['message']);
                }
            } catch (Throwable $e) {
                $failedCount++;
                $failedTokens[] = $tokenNo;
                error_log('Token ' . $tokenNo . ': Exception - ' . $e->getMessage());
            }
        }
    }
    
    echo json_encode([
        'success' => $sentCount > 0,
        'message' => 'Reminders sent to ' . $sentCount . ' token(s)' . ($failedCount > 0 ? ', Failed: ' . $failedCount : ''),
        'sent_count' => $sentCount,
        'failed_count' => $failedCount,
        'failed_tokens' => $failedTokens
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
