<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

$form = [
    'title' => '',
    'slug' => '',
    'event_type' => 'single_day',
    'short_description' => '',
    'long_description' => '',
    'youtube_video_url' => '',
    'image' => '',
    'location' => '',
    'event_date' => '',
    'registration_start' => '',
    'registration_end' => '',
    'status' => 'Active',
    'send_whatsapp_notifications' => '1',
];
$multiDateRows = [];
$rangeStart = '';
$rangeEnd = '';
$rangeSeatLimit = '';
$rangeStatus = 'Active';
$singleSeatLimit = '';
$singleBookedSeats = 0;
$rangeBookedSeatsMax = 0;
$multiDateMigrationMapByOldDate = [];
$existingDateBookedByDate = [];
$existingDateRowsWithBookings = [];
$formFields = [];

if ($editId > 0) {
    $eventStmt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
    $eventStmt->execute([$editId]);
    $existing = $eventStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $form['title'] = (string)$existing['title'];
        $form['slug'] = (string)$existing['slug'];
        $form['event_type'] = in_array((string)$existing['event_type'], ['single_day', 'multi_select_dates', 'date_range'], true) ? (string)$existing['event_type'] : 'single_day';
        $form['short_description'] = (string)($existing['short_description'] ?? $existing['description'] ?? '');
        $form['long_description'] = (string)($existing['long_description'] ?? $existing['description'] ?? '');
        $form['youtube_video_url'] = (string)($existing['youtube_video_url'] ?? '');
        $form['image'] = (string)$existing['image'];
        $form['location'] = (string)$existing['location'];
        $form['event_date'] = (string)$existing['event_date'];
        $form['registration_start'] = (string)$existing['registration_start'];
        $form['registration_end'] = (string)$existing['registration_end'];
        $form['status'] = (string)$existing['status'];
        $form['send_whatsapp_notifications'] = ((int)($existing['send_whatsapp_notifications'] ?? 1) === 1) ? '1' : '0';

        $dateRows = vs_event_fetch_event_dates_with_booked_seats($pdo, $editId);
        foreach ($dateRows as $row) {
            $bookedSeats = max((int)($row['booked_seats'] ?? 0), 0);
            $multiDateRows[] = [
                'event_date' => (string)$row['event_date'],
                'seat_limit' => ((int)($row['seat_limit'] ?? 0) > 0) ? (string)(int)$row['seat_limit'] : '',
                'status' => ((string)($row['status'] ?? 'Active') === 'Inactive') ? 'Inactive' : 'Active',
                'booked_seats' => $bookedSeats,
                'original_event_date' => (string)$row['event_date'],
                'migrate_target' => '',
            ];
            $existingDateBookedByDate[(string)$row['event_date']] = $bookedSeats;
            $existingDateRowsWithBookings[] = $row;
            if ($bookedSeats > $rangeBookedSeatsMax) {
                $rangeBookedSeatsMax = $bookedSeats;
            }
        }
        if (!empty($dateRows)) {
            $singleSeatLimit = ((int)($dateRows[0]['seat_limit'] ?? 0) > 0) ? (string)(int)$dateRows[0]['seat_limit'] : '';
            $singleBookedSeats = max((int)($dateRows[0]['booked_seats'] ?? 0), 0);
            $rangeStart = (string)$dateRows[0]['event_date'];
            $rangeEnd = (string)$dateRows[count($dateRows) - 1]['event_date'];
            $rangeSeatLimit = $singleSeatLimit;
            $rangeStatus = ((string)($dateRows[0]['status'] ?? 'Active') === 'Inactive') ? 'Inactive' : 'Active';
        }

        $fieldStmt = $pdo->prepare("SELECT field_name, field_type, field_options, field_placeholder, required FROM event_form_fields WHERE event_id = ? ORDER BY id ASC");
        $fieldStmt->execute([$editId]);
        $formFields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $editId = 0;
        $errors[] = 'Event not found.';
    }
}

if (empty($multiDateRows)) {
    $multiDateRows[] = [
        'event_date' => $form['event_date'],
        'seat_limit' => '',
        'status' => 'Active',
        'booked_seats' => 0,
        'original_event_date' => '',
        'migrate_target' => '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = (int)($_POST['event_id'] ?? 0);
    $existingDateRowsWithBookings = [];
    $existingDateBookedByDate = [];
    $singleBookedSeats = 0;
    $rangeBookedSeatsMax = 0;
    $lockedEventType = '';
    if ($editId > 0) {
        $eventStmt = $pdo->prepare("SELECT event_type FROM events WHERE id = ? LIMIT 1");
        $eventStmt->execute([$editId]);
        $lockedEventTypeRaw = $eventStmt->fetchColumn();
        if ($lockedEventTypeRaw === false) {
            $errors[] = 'Event not found.';
        } else {
            $lockedEventType = vs_event_normalize_event_type((string)$lockedEventTypeRaw);
            $existingDateRowsWithBookings = vs_event_fetch_event_dates_with_booked_seats($pdo, $editId);
            foreach ($existingDateRowsWithBookings as $existingDateRow) {
                $existingDate = (string)($existingDateRow['event_date'] ?? '');
                if ($existingDate === '') {
                    continue;
                }
                $bookedSeats = max((int)($existingDateRow['booked_seats'] ?? 0), 0);
                $existingDateBookedByDate[$existingDate] = $bookedSeats;
                if ($bookedSeats > $rangeBookedSeatsMax) {
                    $rangeBookedSeatsMax = $bookedSeats;
                }
            }
            if (!empty($existingDateRowsWithBookings)) {
                $singleBookedSeats = max((int)($existingDateRowsWithBookings[0]['booked_seats'] ?? 0), 0);
            }
        }
    }

    $form['title'] = trim((string)($_POST['title'] ?? ''));
    $slugInput = trim((string)($_POST['slug'] ?? ''));
    $form['slug'] = vs_event_slugify($slugInput !== '' ? $slugInput : $form['title']);
    $form['event_type'] = trim((string)($_POST['event_type'] ?? 'single_day'));
    if (!in_array($form['event_type'], ['single_day', 'multi_select_dates', 'date_range'], true)) {
        $form['event_type'] = 'single_day';
    }
    if ($editId > 0 && $lockedEventType !== '') {
        if ($form['event_type'] !== $lockedEventType) {
            $errors[] = 'Event type cannot be changed once the event is created.';
        }
        $form['event_type'] = $lockedEventType;
    }
    $form['short_description'] = trim((string)($_POST['short_description'] ?? ''));
    $form['long_description'] = trim((string)($_POST['long_description'] ?? ''));
    $form['youtube_video_url'] = trim((string)($_POST['youtube_video_url'] ?? ''));
    $form['location'] = trim((string)($_POST['location'] ?? ''));
    $form['event_date'] = trim((string)($_POST['event_date'] ?? ''));
    $form['registration_start'] = trim((string)($_POST['registration_start'] ?? ''));
    $form['registration_end'] = trim((string)($_POST['registration_end'] ?? ''));
    $form['status'] = trim((string)($_POST['status'] ?? 'Active'));
    $form['send_whatsapp_notifications'] = ((int)($_POST['send_whatsapp_notifications'] ?? 1) === 1) ? '1' : '0';

    $singleSeatLimit = trim((string)($_POST['single_seat_limit'] ?? ''));
    $rangeStart = trim((string)($_POST['range_start'] ?? ''));
    $rangeEnd = trim((string)($_POST['range_end'] ?? ''));
    $rangeSeatLimit = trim((string)($_POST['range_seat_limit'] ?? ''));
    $rangeStatus = trim((string)($_POST['range_status'] ?? 'Active'));
    if ($rangeStatus !== 'Inactive') {
        $rangeStatus = 'Active';
    }

    if ($form['title'] === '') {
        $errors[] = 'Event title is required.';
    }
    if ($form['slug'] === '') {
        $errors[] = 'Event slug is required.';
    }
    if ($form['location'] === '') {
        $errors[] = 'Event location is required.';
    }
    if ($form['registration_start'] === '' || $form['registration_end'] === '') {
        $errors[] = 'Registration dates are required.';
    } elseif ($form['registration_end'] < $form['registration_start']) {
        $errors[] = 'Registration end date must be after start date.';
    }
    if (!in_array($form['status'], ['Active', 'Closed'], true)) {
        $errors[] = 'Invalid event status.';
    }

    $normalizeDateValue = static function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    };

    $multiDateMigrationMapByOldDate = [];
    $scheduleRows = [];
    if ($form['event_type'] === 'single_day') {
        if ($form['event_date'] === '') {
            $errors[] = 'Event date is required for single day events.';
        } else {
            $seat = null;
            if ($singleSeatLimit !== '') {
                $seat = (int)$singleSeatLimit;
                if ($seat <= 0) {
                    $errors[] = 'Single-day seat limit must be blank or greater than zero.';
                    $seat = null;
                }
            }
            $scheduleRows[] = ['event_date' => $form['event_date'], 'seat_limit' => $seat, 'status' => 'Active'];
        }
    } elseif ($form['event_type'] === 'multi_select_dates') {
        $multiDateRows = [];
        $postedDates = isset($_POST['multi_event_date']) && is_array($_POST['multi_event_date']) ? $_POST['multi_event_date'] : [];
        $postedSeats = isset($_POST['multi_seat_limit']) && is_array($_POST['multi_seat_limit']) ? $_POST['multi_seat_limit'] : [];
        $postedStatus = isset($_POST['multi_status']) && is_array($_POST['multi_status']) ? $_POST['multi_status'] : [];
        $postedOriginalDates = isset($_POST['multi_original_event_date']) && is_array($_POST['multi_original_event_date']) ? $_POST['multi_original_event_date'] : [];
        $postedMigrateTargets = isset($_POST['multi_migrate_target']) && is_array($_POST['multi_migrate_target']) ? $_POST['multi_migrate_target'] : [];
        $count = max(count($postedDates), count($postedSeats), count($postedStatus), count($postedOriginalDates), count($postedMigrateTargets));
        for ($i = 0; $i < $count; $i++) {
            $d = trim((string)($postedDates[$i] ?? ''));
            $seatRaw = trim((string)($postedSeats[$i] ?? ''));
            $st = trim((string)($postedStatus[$i] ?? 'Active'));
            $originalDateRaw = trim((string)($postedOriginalDates[$i] ?? ''));
            $migrateTargetRaw = trim((string)($postedMigrateTargets[$i] ?? ''));
            if ($st !== 'Inactive') {
                $st = 'Active';
            }
            $normalizedOriginalDate = $normalizeDateValue($originalDateRaw);
            $bookedSeatLookupDate = $normalizedOriginalDate !== '' ? $normalizedOriginalDate : $normalizeDateValue($d);
            $bookedSeats = ($bookedSeatLookupDate !== '' && isset($existingDateBookedByDate[$bookedSeatLookupDate])) ? (int)$existingDateBookedByDate[$bookedSeatLookupDate] : 0;
            $multiDateRows[] = [
                'event_date' => $d,
                'seat_limit' => $seatRaw,
                'status' => $st,
                'booked_seats' => $bookedSeats,
                'original_event_date' => $originalDateRaw,
                'migrate_target' => $migrateTargetRaw,
            ];

            $normalizedNewDate = $normalizeDateValue($d);
            $normalizedMigrateTarget = $normalizeDateValue($migrateTargetRaw);
            if ($normalizedOriginalDate !== '') {
                if ($normalizedMigrateTarget !== '') {
                    $multiDateMigrationMapByOldDate[$normalizedOriginalDate] = $normalizedMigrateTarget;
                } elseif ($normalizedNewDate !== '' && $normalizedNewDate !== $normalizedOriginalDate) {
                    // If a booked date row is directly edited to another date, use it as migration intent.
                    $multiDateMigrationMapByOldDate[$normalizedOriginalDate] = $normalizedNewDate;
                }
            }
            if ($d === '') {
                continue;
            }
            $seat = null;
            if ($seatRaw !== '') {
                $seat = (int)$seatRaw;
                if ($seat <= 0) {
                    $errors[] = 'Multi-date seat limits must be blank or greater than zero.';
                    $seat = null;
                }
            }
            $scheduleRows[] = ['event_date' => $d, 'seat_limit' => $seat, 'status' => $st];
        }
        if (empty($scheduleRows)) {
            $errors[] = 'Please add at least one event date.';
        }
    } else {
        if ($rangeStart === '' || $rangeEnd === '') {
            $errors[] = 'Range start and end dates are required.';
        } elseif ($rangeEnd < $rangeStart) {
            $errors[] = 'Range end date must be after start date.';
        } else {
            $seat = null;
            if ($rangeSeatLimit !== '') {
                $seat = (int)$rangeSeatLimit;
                if ($seat <= 0) {
                    $errors[] = 'Range seat limit must be blank or greater than zero.';
                    $seat = null;
                }
            }
            $scheduleRows = vs_event_normalize_dates_from_range($rangeStart, $rangeEnd, $seat, $rangeStatus);
        }
    }

    if (!empty($scheduleRows)) {
        usort($scheduleRows, static function (array $a, array $b): int {
            return strcmp((string)$a['event_date'], (string)$b['event_date']);
        });
        $form['event_date'] = (string)$scheduleRows[0]['event_date'];
    }

    if ($editId > 0 && !empty($existingDateRowsWithBookings) && !empty($scheduleRows)) {
        $newScheduleByDate = [];
        foreach ($scheduleRows as $scheduleRow) {
            $normalizedDate = $normalizeDateValue((string)($scheduleRow['event_date'] ?? ''));
            if ($normalizedDate === '') {
                continue;
            }
            $seatLimit = null;
            $seatLimitRaw = (string)($scheduleRow['seat_limit'] ?? '');
            if ($seatLimitRaw !== '') {
                $candidateSeat = (int)$seatLimitRaw;
                if ($candidateSeat > 0) {
                    $seatLimit = $candidateSeat;
                }
            }
            $newScheduleByDate[$normalizedDate] = $seatLimit;
        }

        $primaryNewDate = '';
        if (!empty($newScheduleByDate)) {
            $primaryNewDate = (string)array_key_first($newScheduleByDate);
        }

        $incomingMigratedByTargetDate = [];
        $validatedMigrationMapByOldDate = [];
        foreach ($existingDateRowsWithBookings as $existingDateRow) {
            $existingDate = $normalizeDateValue((string)($existingDateRow['event_date'] ?? ''));
            $bookedSeats = max((int)($existingDateRow['booked_seats'] ?? 0), 0);
            if ($existingDate === '' || $bookedSeats <= 0) {
                continue;
            }

            if (array_key_exists($existingDate, $newScheduleByDate)) {
                $newSeatLimit = $newScheduleByDate[$existingDate];
                if ($newSeatLimit !== null && $newSeatLimit < $bookedSeats) {
                    $errors[] = 'Seat limit for ' . $existingDate . ' cannot be less than current bookings (' . $bookedSeats . '). Cancel registrations first, then reduce seats.';
                }
                continue;
            }

            $targetDate = '';
            if ($form['event_type'] === 'multi_select_dates') {
                $targetDate = $normalizeDateValue((string)($multiDateMigrationMapByOldDate[$existingDate] ?? ''));
                if ($targetDate === '') {
                    $errors[] = 'Date ' . $existingDate . ' has ' . $bookedSeats . ' booking(s). Please choose a migration date before removing/changing it.';
                    continue;
                }
                if (!array_key_exists($targetDate, $newScheduleByDate)) {
                    $errors[] = 'Migration target date ' . $targetDate . ' is not present in updated schedule for booked date ' . $existingDate . '.';
                    continue;
                }
            } else {
                $targetDate = $primaryNewDate;
                if ($targetDate === '') {
                    $errors[] = 'At least one event date is required to migrate existing bookings.';
                    continue;
                }
            }

            $incomingMigratedByTargetDate[$targetDate] = (int)($incomingMigratedByTargetDate[$targetDate] ?? 0) + $bookedSeats;
            $validatedMigrationMapByOldDate[$existingDate] = $targetDate;
        }

        foreach ($incomingMigratedByTargetDate as $targetDate => $incomingSeats) {
            $targetSeatLimit = $newScheduleByDate[$targetDate] ?? null;
            if ($targetSeatLimit === null) {
                continue;
            }
            $targetExistingBookedSeats = max((int)($existingDateBookedByDate[$targetDate] ?? 0), 0);
            $requiredSeats = $targetExistingBookedSeats + (int)$incomingSeats;
            if ($targetSeatLimit < $requiredSeats) {
                $errors[] = 'Seat limit for migration target date ' . $targetDate . ' is too low. Required at least ' . $requiredSeats . ' seat(s) including migrated bookings.';
            }
        }

        $multiDateMigrationMapByOldDate = $validatedMigrationMapByOldDate;
    }

    $slugStmt = $pdo->prepare('SELECT id FROM events WHERE slug = ?' . ($editId > 0 ? ' AND id != ?' : '') . ' LIMIT 1');
    $slugStmt->execute($editId > 0 ? [$form['slug'], $editId] : [$form['slug']]);
    if ($slugStmt->fetch()) {
        $errors[] = 'This slug is already in use.';
    }

    $uploadedImage = null;
    if (isset($_FILES['image']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadedImage = vs_event_store_upload($_FILES['image'], 'images', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($uploadedImage === null) {
            $errors[] = 'Invalid image upload.';
        }
    }

    $fieldNames = isset($_POST['field_name']) && is_array($_POST['field_name']) ? $_POST['field_name'] : [];
    $fieldTypes = isset($_POST['field_type']) && is_array($_POST['field_type']) ? $_POST['field_type'] : [];
    $fieldOptions = isset($_POST['field_options']) && is_array($_POST['field_options']) ? $_POST['field_options'] : [];
    $fieldPlaceholders = isset($_POST['field_placeholder']) && is_array($_POST['field_placeholder']) ? $_POST['field_placeholder'] : [];
    $fieldRequired = isset($_POST['field_required']) && is_array($_POST['field_required']) ? $_POST['field_required'] : [];
    $allowedTypes = ['text', 'phone', 'number', 'textarea', 'select', 'file', 'date'];
    $formFields = [];
    $fCount = max(count($fieldNames), count($fieldTypes));
    for ($i = 0; $i < $fCount; $i++) {
        $name = trim((string)($fieldNames[$i] ?? ''));
        $type = trim((string)($fieldTypes[$i] ?? 'text'));
        if ($name === '') {
            continue;
        }
        if (!in_array($type, $allowedTypes, true)) {
            $errors[] = 'Invalid form field type.';
            break;
        }
        $formFields[] = [
            'field_name' => $name,
            'field_type' => $type,
            'field_options' => trim((string)($fieldOptions[$i] ?? '')),
            'field_placeholder' => trim((string)($fieldPlaceholders[$i] ?? '')),
            'required' => ((int)($fieldRequired[$i] ?? 0) === 1) ? 1 : 0,
        ];
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $descriptionCompat = trim(strip_tags($form['long_description']));
            if ($descriptionCompat === '') {
                $descriptionCompat = $form['short_description'];
            }

            if ($editId > 0) {
                $oldImageStmt = $pdo->prepare("SELECT image FROM events WHERE id = ? LIMIT 1");
                $oldImageStmt->execute([$editId]);
                $oldImage = (string)$oldImageStmt->fetchColumn();
                $newImage = $uploadedImage ?: $oldImage;

                $updateStmt = $pdo->prepare("UPDATE events
                    SET title = ?, slug = ?, event_type = ?, short_description = ?, long_description = ?, youtube_video_url = ?, description = ?, image = ?, location = ?, event_date = ?, registration_start = ?, registration_end = ?, status = ?, send_whatsapp_notifications = ?
                    WHERE id = ?");
                $updateStmt->execute([
                    $form['title'], $form['slug'], $form['event_type'], $form['short_description'], $form['long_description'], $form['youtube_video_url'], $descriptionCompat,
                    $newImage, $form['location'], $form['event_date'], $form['registration_start'], $form['registration_end'], $form['status'], (int)$form['send_whatsapp_notifications'], $editId,
                ]);

                if ($uploadedImage && $oldImage !== '' && $oldImage !== $uploadedImage) {
                    $oldPath = __DIR__ . '/../../' . ltrim($oldImage, '/');
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $eventId = $editId;
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO events
                    (title, slug, event_type, short_description, long_description, youtube_video_url, description, image, location, event_date, registration_start, registration_end, status, send_whatsapp_notifications)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $form['title'], $form['slug'], $form['event_type'], $form['short_description'], $form['long_description'], $form['youtube_video_url'], $descriptionCompat,
                    (string)$uploadedImage, $form['location'], $form['event_date'], $form['registration_start'], $form['registration_end'], $form['status'], (int)$form['send_whatsapp_notifications'],
                ]);
                $eventId = (int)$pdo->lastInsertId();
            }

            vs_event_replace_event_dates($pdo, $eventId, $scheduleRows, $multiDateMigrationMapByOldDate);
            $primaryDate = vs_event_get_primary_event_date($pdo, $eventId, $form['event_date']);
            if ($primaryDate !== '') {
                $pdo->prepare("UPDATE events SET event_date = ? WHERE id = ?")->execute([$primaryDate, $eventId]);
            }
            if (in_array($form['event_type'], ['single_day', 'date_range'], true)) {
                $primaryDateIdStmt = $pdo->prepare("SELECT id
                    FROM event_dates
                    WHERE event_id = ?
                    ORDER BY CASE WHEN status = 'Active' THEN 0 ELSE 1 END, event_date ASC, id ASC
                    LIMIT 1");
                $primaryDateIdStmt->execute([$eventId]);
                $primaryDateId = (int)$primaryDateIdStmt->fetchColumn();
                if ($primaryDateId > 0) {
                    $pdo->prepare("UPDATE event_registrations SET event_date_id = ? WHERE event_id = ?")
                        ->execute([$primaryDateId, $eventId]);
                    $pdo->prepare("UPDATE event_waitlist SET event_date_id = ? WHERE event_id = ?")
                        ->execute([$primaryDateId, $eventId]);
                }
            }
            vs_event_sync_selected_event_date_values($pdo, $eventId, $form['event_type']);

            $pdo->prepare("DELETE FROM event_form_fields WHERE event_id = ?")->execute([$eventId]);
            if (!empty($formFields)) {
                $insField = $pdo->prepare("INSERT INTO event_form_fields (event_id, field_name, field_type, field_options, field_placeholder, required) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($formFields as $f) {
                    $insField->execute([$eventId, $f['field_name'], $f['field_type'], $f['field_options'], $f['field_placeholder'], $f['required']]);
                }
            }

            $pdo->commit();
            header('Location: all-events.php?saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                $errors[] = $e->getMessage();
            } else {
                $errors[] = 'Failed to save event.';
            }
            error_log('Event save failed: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editId > 0 ? 'Edit Event' : 'Add Event'; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f7f7fa;margin:0}.admin-container{max-width:1100px;margin:0 auto;padding:24px 12px}.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px #e0bebe22;padding:18px;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px}.form-group{display:flex;flex-direction:column;gap:6px}label{color:#800000;font-weight:700;font-size:.92em}input,select,textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e0bebe;border-radius:8px;font-size:.95em;background:#fff}textarea{min-height:100px}.field-table{width:100%;border-collapse:collapse;margin-top:10px}.field-table th,.field-table td{border:1px solid #f1d6d6;padding:8px;vertical-align:top}.field-table th{background:#f9eaea;color:#800000}.btn-main{display:inline-block;padding:10px 14px;border-radius:8px;background:#800000;color:#fff;text-decoration:none;border:none;font-weight:700;cursor:pointer}.btn-alt{background:#6c757d}.btn-row{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}.add-field{background:#17a2b8;color:#fff;border:none;border-radius:8px;padding:8px 12px;font-weight:700;cursor:pointer}.remove-field{background:#dc3545;color:#fff;border:none;border-radius:6px;padding:7px 10px;font-weight:700;cursor:pointer}.notice{margin:10px 0;padding:10px 12px;border-radius:8px;font-weight:600;background:#ffeaea;color:#b00020}.event-type-block{margin-top:12px;border:1px solid #f1d6d6;border-radius:10px;padding:12px;background:#fffaf8}.hidden{display:none}</style>
    <style>.editor-wrapper{border:1px solid #e0bebe;border-radius:12px;overflow:hidden;background:#fff;box-shadow:inset 0 1px 0 #f7f0f0}.editor-area{min-height:260px;padding:14px;outline:none;font-size:1em;line-height:1.6}</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1 style="color:#800000;"><?php echo $editId > 0 ? 'Edit Event' : 'Add Event'; ?></h1>
    <?php if (!empty($errors)): ?><div class="notice"><?php foreach ($errors as $err) { echo '<div>' . htmlspecialchars($err) . '</div>'; } ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="off" id="eventForm">
        <input type="hidden" name="event_id" value="<?php echo (int)$editId; ?>">
        <div class="card">
            <div class="grid">
                <div class="form-group"><label>Event Title</label><input type="text" name="title" id="event_title" value="<?php echo htmlspecialchars($form['title']); ?>" required></div>
                <div class="form-group"><label>Event Slug</label><input type="text" name="slug" id="event_slug" value="<?php echo htmlspecialchars($form['slug']); ?>" required></div>
                <div class="form-group">
                    <label>Event Type</label>
                    <?php if ($editId > 0): ?>
                        <input type="hidden" name="event_type" value="<?php echo htmlspecialchars((string)$form['event_type']); ?>">
                    <?php endif; ?>
                    <select name="event_type" id="event_type" <?php echo ($editId > 0) ? 'disabled' : ''; ?>>
                        <option value="single_day" <?php echo ($form['event_type'] === 'single_day') ? 'selected' : ''; ?>>Single Day</option>
                        <option value="multi_select_dates" <?php echo ($form['event_type'] === 'multi_select_dates') ? 'selected' : ''; ?>>Multi Select Dates</option>
                        <option value="date_range" <?php echo ($form['event_type'] === 'date_range') ? 'selected' : ''; ?>>Date Range</option>
                    </select>
                    <?php if ($editId > 0): ?><span style="font-size:12px;color:#666;">Event type is locked after creation.</span><?php endif; ?>
                </div>
                <div class="form-group"><label>Location</label><input type="text" name="location" value="<?php echo htmlspecialchars($form['location']); ?>" required></div>
                <div class="form-group"><label>Registration Start</label><input type="date" name="registration_start" value="<?php echo htmlspecialchars($form['registration_start']); ?>" required></div>
                <div class="form-group"><label>Registration End</label><input type="date" name="registration_end" value="<?php echo htmlspecialchars($form['registration_end']); ?>" required></div>
                <div class="form-group"><label>Status</label><select name="status"><option value="Active" <?php echo ($form['status'] === 'Active') ? 'selected' : ''; ?>>Active</option><option value="Closed" <?php echo ($form['status'] === 'Closed') ? 'selected' : ''; ?>>Closed</option></select></div>
                <div class="form-group"><label>Send WhatsApp Notifications</label><select name="send_whatsapp_notifications"><option value="1" <?php echo ($form['send_whatsapp_notifications'] === '1') ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo ($form['send_whatsapp_notifications'] === '0') ? 'selected' : ''; ?>>No</option></select></div>
                <div class="form-group"><label>Image</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp"></div>
            </div>
            <div id="single_day_block" class="event-type-block">
                <div class="grid">
                    <div class="form-group">
                        <label>Event Date</label>
                        <input type="date" name="event_date" value="<?php echo htmlspecialchars($form['event_date']); ?>">
                        <?php if ($editId > 0): ?><span style="font-size:12px;color:#666;">If changed, existing bookings will be migrated to the new date automatically.</span><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Seat Limit</label>
                        <input type="number" min="<?php echo (int)max(1, $singleBookedSeats); ?>" name="single_seat_limit" value="<?php echo htmlspecialchars($singleSeatLimit); ?>">
                        <?php if ($singleBookedSeats > 0): ?><span style="font-size:12px;color:#666;">Current bookings: <?php echo (int)$singleBookedSeats; ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div id="multi_dates_block" class="event-type-block">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                    <strong style="color:#800000">Event Dates</strong>
                    <button type="button" class="add-field" id="addDateRowBtn">+ Add Date</button>
                </div>
                <table class="field-table">
                    <thead>
                        <tr><th>Date</th><th>Seat Limit</th><th>Booked</th><th>Migrate Bookings To</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody id="multiDateRows">
                    <?php foreach ($multiDateRows as $row): ?>
                        <?php $bookedSeats = max((int)($row['booked_seats'] ?? 0), 0); ?>
                        <tr>
                            <td>
                                <input type="hidden" name="multi_original_event_date[]" value="<?php echo htmlspecialchars((string)($row['original_event_date'] ?? '')); ?>">
                                <input type="date" name="multi_event_date[]" value="<?php echo htmlspecialchars((string)$row['event_date']); ?>">
                            </td>
                            <td><input type="number" min="<?php echo (int)max(1, $bookedSeats); ?>" name="multi_seat_limit[]" value="<?php echo htmlspecialchars((string)$row['seat_limit']); ?>"></td>
                            <td><?php echo $bookedSeats; ?></td>
                            <td>
                                <input type="date" name="multi_migrate_target[]" value="<?php echo htmlspecialchars((string)($row['migrate_target'] ?? '')); ?>">
                                <?php if ($bookedSeats > 0): ?><span style="font-size:12px;color:#666;">Required if this booked date is removed/changed.</span><?php endif; ?>
                            </td>
                            <td><select name="multi_status[]"><option value="Active" <?php echo ($row['status'] === 'Active') ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo ($row['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option></select></td>
                            <td>
                                <?php if ($bookedSeats > 0): ?>
                                    <button type="button" class="remove-field" disabled title="Booked dates cannot be removed directly. Change date and set migration target.">X</button>
                                    <span style="font-size:12px;color:#666;display:block;margin-top:4px;">Booked date: change Date to migrate.</span>
                                <?php else: ?>
                                    <button type="button" class="remove-field">X</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="date_range_block" class="event-type-block">
                <div class="grid">
                    <div class="form-group"><label>Range Start</label><input type="date" name="range_start" value="<?php echo htmlspecialchars($rangeStart); ?>"></div>
                    <div class="form-group"><label>Range End</label><input type="date" name="range_end" value="<?php echo htmlspecialchars($rangeEnd); ?>"></div>
                    <div class="form-group">
                        <label>Seat Limit</label>
                        <input type="number" min="<?php echo (int)max(1, $rangeBookedSeatsMax); ?>" name="range_seat_limit" value="<?php echo htmlspecialchars($rangeSeatLimit); ?>">
                        <?php if ($rangeBookedSeatsMax > 0): ?><span style="font-size:12px;color:#666;">Highest current date bookings: <?php echo (int)$rangeBookedSeatsMax; ?></span><?php endif; ?>
                    </div>
                    <div class="form-group"><label>Status</label><select name="range_status"><option value="Active" <?php echo ($rangeStatus !== 'Inactive') ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo ($rangeStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option></select></div>
                </div>
                <?php if ($editId > 0): ?><span style="font-size:12px;color:#666;">Date-range updates will migrate all existing bookings to the new primary range date automatically.</span><?php endif; ?>
            </div>
            <div class="form-group" style="margin-top:12px;"><label>Short Description</label><textarea name="short_description"><?php echo htmlspecialchars($form['short_description']); ?></textarea></div>
            <div class="form-group" style="margin-top:12px;">
                <label>Long Description</label>
                <div class="editor-wrapper">
                    <textarea id="bodyEditor" class="editor-area" aria-label="Event content editor"></textarea>
                </div>
                <textarea name="long_description" id="body" style="display:none;"></textarea>
            </div>
            <div class="form-group" style="margin-top:12px;"><label>YouTube Video URL</label><input type="text" name="youtube_video_url" value="<?php echo htmlspecialchars($form['youtube_video_url']); ?>"></div>
        </div>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between"><h3 style="margin:0;color:#800000">Dynamic Registration Fields</h3><button type="button" class="add-field" id="addFieldBtn">+ Add Field</button></div>
            <table class="field-table"><thead><tr><th>Field Name</th><th>Type</th><th>Options</th><th>Placeholder</th><th>Required</th><th>Action</th></tr></thead><tbody id="fieldRows"><?php foreach ($formFields as $f): ?><tr><td><input type="text" name="field_name[]" value="<?php echo htmlspecialchars((string)$f['field_name']); ?>"></td><td><select name="field_type[]"><?php foreach (['text','phone','number','textarea','select','file','date'] as $t): ?><option value="<?php echo $t; ?>" <?php echo ((string)$f['field_type'] === $t) ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option><?php endforeach; ?></select></td><td><input type="text" name="field_options[]" value="<?php echo htmlspecialchars((string)$f['field_options']); ?>"></td><td><input type="text" name="field_placeholder[]" value="<?php echo htmlspecialchars((string)($f['field_placeholder'] ?? '')); ?>"></td><td><select name="field_required[]"><option value="1" <?php echo ((int)$f['required'] === 1) ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo ((int)$f['required'] === 0) ? 'selected' : ''; ?>>No</option></select></td><td><button type="button" class="remove-field">X</button></td></tr><?php endforeach; ?></tbody></table>
        </div>
        <div class="btn-row"><button type="submit" class="btn-main"><?php echo $editId > 0 ? 'Update Event' : 'Create Event'; ?></button><a href="all-events.php" class="btn-main btn-alt">Back to All Events</a></div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    const title = document.getElementById('event_title');
    const slug = document.getElementById('event_slug');
    const typeSel = document.getElementById('event_type');
    const singleDayBlock = document.getElementById('single_day_block');
    const multiDatesBlock = document.getElementById('multi_dates_block');
    const dateRangeBlock = document.getElementById('date_range_block');
    const rows = document.getElementById('fieldRows');
    const dateRows = document.getElementById('multiDateRows');
    const eventForm = document.getElementById('eventForm');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const addDateRowBtn = document.getElementById('addDateRowBtn');
    const bodyTextarea = document.getElementById('body');
    const bodyEditorEl = document.getElementById('bodyEditor');
    const initialEditorHtml = <?= json_encode($form['long_description'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function slugify(value) {
        return (value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    if (title && slug) {
        title.addEventListener('input', function () {
            if (!slug.dataset.touched) {
                slug.value = slugify(title.value);
            }
        });
        slug.addEventListener('input', function () {
            slug.dataset.touched = '1';
        });
    }

    function bindRemoveButtons(root) {
        root.querySelectorAll('.remove-field').forEach(function (btn) {
            btn.onclick = function () {
                const tr = btn.closest('tr');
                if (tr) {
                    tr.remove();
                }
            };
        });
    }

    function toggleTypeBlocks() {
        const type = typeSel.value;
        singleDayBlock.classList.toggle('hidden', type !== 'single_day');
        multiDatesBlock.classList.toggle('hidden', type !== 'multi_select_dates');
        dateRangeBlock.classList.toggle('hidden', type !== 'date_range');
    }

    if (addFieldBtn && rows) {
        addFieldBtn.addEventListener('click', function () {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td><input type=\"text\" name=\"field_name[]\"></td><td><select name=\"field_type[]\"><option value=\"text\">Text</option><option value=\"phone\">Phone</option><option value=\"number\">Number</option><option value=\"textarea\">Textarea</option><option value=\"select\">Select</option><option value=\"file\">File</option><option value=\"date\">Date</option></select></td><td><input type=\"text\" name=\"field_options[]\"></td><td><input type=\"text\" name=\"field_placeholder[]\"></td><td><select name=\"field_required[]\"><option value=\"1\">Yes</option><option value=\"0\" selected>No</option></select></td><td><button type=\"button\" class=\"remove-field\">X</button></td>';
            rows.appendChild(tr);
            bindRemoveButtons(tr);
        });
    }

    if (addDateRowBtn && dateRows) {
        addDateRowBtn.addEventListener('click', function () {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td><input type=\"hidden\" name=\"multi_original_event_date[]\" value=\"\"><input type=\"date\" name=\"multi_event_date[]\"></td><td><input type=\"number\" min=\"1\" name=\"multi_seat_limit[]\"></td><td>0</td><td><input type=\"date\" name=\"multi_migrate_target[]\"></td><td><select name=\"multi_status[]\"><option value=\"Active\" selected>Active</option><option value=\"Inactive\">Inactive</option></select></td><td><button type=\"button\" class=\"remove-field\">X</button></td>';
            dateRows.appendChild(tr);
            bindRemoveButtons(tr);
        });
    }

    bindRemoveButtons(document);
    if (typeSel) {
        typeSel.addEventListener('change', toggleTypeBlocks);
        toggleTypeBlocks();
    }

    if (bodyEditorEl) {
        bodyEditorEl.value = initialEditorHtml || '';
    }

    const uploadEditorFile = file => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '../website/upload-editor-image.php', true);
        xhr.responseType = 'json';
        xhr.onerror = () => reject('Upload failed');
        xhr.onload = () => {
            const response = xhr.response || {};
            if (!response.url) {
                reject(response.error || 'Upload failed');
                return;
            }
            resolve(response.url);
        };
        const data = new FormData();
        data.append('upload', file);
        xhr.send(data);
    });

    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#bodyEditor',
            height: 560,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image media table code fullscreen preview searchreplace visualblocks wordcount charmap emoticons codesample autoresize anchor',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table blockquote codesample | removeformat | preview code fullscreen',
            toolbar_mode: 'sliding',
            branding: false,
            promotion: false,
            content_style: 'body { font-family:Segoe UI,Tahoma,Verdana,sans-serif; font-size:16px } img { max-width:100%; height:auto; }',
            object_resizing: 'img,table,iframe,video',
            image_advtab: true,
            image_caption: true,
            image_dimensions: true,
            media_dimensions: true,
            media_live_embeds: true,
            link_default_target: '_blank',
            extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen],video[*],source[*]',
            setup: function (editor) {
                editor.on('init', function () {
                    editor.setContent(initialEditorHtml || '');
                    if (bodyTextarea) {
                        bodyTextarea.value = editor.getContent();
                    }
                });
                editor.on('change input undo redo', function () {
                    if (bodyTextarea) {
                        bodyTextarea.value = editor.getContent();
                    }
                });
            },
            images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
                const file = blobInfo.blob();
                uploadEditorFile(file).then(resolve).catch(reject);
            }),
            file_picker_types: 'image media',
            file_picker_callback: (callback, value, meta) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = meta.filetype === 'media'
                    ? 'video/*,audio/*'
                    : 'image/*';
                input.onchange = async () => {
                    const file = input.files && input.files[0];
                    if (!file) {
                        return;
                    }
                    try {
                        const url = await uploadEditorFile(file);
                        callback(url, { title: file.name });
                    } catch (err) {
                        alert(typeof err === 'string' ? err : 'Upload failed');
                    }
                };
                input.click();
            }
        });
    } else {
        alert('Failed to load rich text editor. Please refresh and try again.');
    }

    if (eventForm) {
        eventForm.addEventListener('submit', function () {
            if (window.tinymce && window.tinymce.get('bodyEditor')) {
                if (bodyTextarea) {
                    bodyTextarea.value = window.tinymce.get('bodyEditor').getContent();
                }
            } else if (bodyTextarea && bodyEditorEl) {
                bodyTextarea.value = bodyEditorEl.value || '';
            }
        });
    }
})();
</script>
</body>
</html>
