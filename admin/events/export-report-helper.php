<?php

if (!function_exists('vs_event_export_normalize_date')) {
    function vs_event_export_normalize_date(string $rawDate): string
    {
        $rawDate = trim($rawDate);
        if ($rawDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            return '';
        }

        return $rawDate;
    }
}

if (!function_exists('vs_event_export_collect_filters')) {
    function vs_event_export_collect_filters(array $source): array
    {
        $filters = [
            'event_id' => isset($source['event_id']) ? max((int)$source['event_id'], 0) : 0,
            'payment_status' => trim((string)($source['payment_status'] ?? '')),
            'verification_status' => trim((string)($source['verification_status'] ?? '')),
            'from_date' => vs_event_export_normalize_date((string)($source['from_date'] ?? '')),
            'to_date' => vs_event_export_normalize_date((string)($source['to_date'] ?? '')),
        ];

        if ($filters['from_date'] !== '' && $filters['to_date'] !== '' && $filters['from_date'] > $filters['to_date']) {
            $tmp = $filters['from_date'];
            $filters['from_date'] = $filters['to_date'];
            $filters['to_date'] = $tmp;
        }

        return $filters;
    }
}

if (!function_exists('vs_event_export_field_label')) {
    function vs_event_export_field_label(string $fieldName): string
    {
        $fieldName = trim(preg_replace('/\s+/', ' ', $fieldName));
        if ($fieldName === '') {
            return '';
        }

        if (strpos($fieldName, '_') !== false && strpos($fieldName, ' ') === false) {
            $fieldName = ucwords(str_replace('_', ' ', $fieldName));
        }

        return $fieldName;
    }
}

if (!function_exists('vs_event_export_field_value')) {
    function vs_event_export_field_value($value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if ($value !== '' && ($value[0] === '[' || $value[0] === '{')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    $parts = [];
                    $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($decoded));
                    foreach ($iterator as $item) {
                        $item = trim((string)$item);
                        if ($item !== '') {
                            $parts[] = $item;
                        }
                    }
                    if (!empty($parts)) {
                        $value = implode(', ', array_values(array_unique($parts)));
                    }
                } elseif (is_scalar($decoded)) {
                    $value = trim((string)$decoded);
                }
            }
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = array_map(static function (string $line): string {
            return trim(preg_replace('/[ \t]+/', ' ', $line));
        }, explode("\n", $value));
        $lines = array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));

        return implode("\n", $lines);
    }
}

if (!function_exists('vs_event_export_compute_amounts')) {
    function vs_event_export_compute_amounts(array $row): array
    {
        $persons = max((int)($row['persons'] ?? 1), 1);
        $total = round((float)($row['package_price_total'] ?? 0) * $persons, 2);
        $paid = round(max((float)($row['amount_paid'] ?? 0), 0), 2);
        $status = strtolower(trim((string)($row['payment_status'] ?? '')));

        if ($status === 'paid' && $paid <= 0) {
            $paid = $total;
        }
        if ($paid > $total && $total > 0) {
            $paid = $total;
        }

        $remaining = round((float)($row['remaining_amount'] ?? 0), 2);
        if ($remaining <= 0 && $status !== 'cancelled') {
            $remaining = round(max($total - $paid, 0), 2);
        }
        if ($status === 'paid') {
            $remaining = 0.0;
        }

        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
        ];
    }
}

if (!function_exists('vs_event_export_compute_display_amounts')) {
    function vs_event_export_compute_display_amounts(array $row, array $baseAmounts): array
    {
        $total = round(max((float)($baseAmounts['total'] ?? 0), 0), 2);
        $paid = round(max((float)($baseAmounts['paid'] ?? 0), 0), 2);
        if ($paid > $total && $total > 0) {
            $paid = $total;
        }

        $remaining = round(max((float)($baseAmounts['remaining'] ?? 0), 0), 2);
        $paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
        $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
        $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));
        $submittedAmount = round(max((float)($row['payment_amount'] ?? 0), 0), 2);

        $isManualOrCash = in_array($paymentMethod, ['manual upi', 'cash'], true);
        $isPendingVerification = $isManualOrCash && (
            in_array($paymentStatus, ['pending', 'pending verification'], true) ||
            in_array($verificationStatus, ['pending', 'pending verification'], true) ||
            in_array($paymentRecordStatus, ['pending', 'pending verification'], true)
        );
        $isRejectedVerification = $isManualOrCash && (
            in_array($verificationStatus, ['rejected'], true) ||
            in_array($paymentRecordStatus, ['rejected'], true) ||
            in_array($paymentStatus, ['failed', 'rejected'], true)
        );

        $displayPaid = $paid;
        $displayRemaining = $remaining;
        $note = '';

        if ($isPendingVerification && $submittedAmount > 0) {
            $displayPaid = round(min($total, $paid + $submittedAmount), 2);
            $displayRemaining = round(max($total - $displayPaid, 0), 2);
            $note = 'Submitted payment is pending verification.';
        } elseif ($isRejectedVerification) {
            $displayRemaining = round(max($total - $displayPaid, 0), 2);
            $note = ($displayPaid <= 0)
                ? 'Previous payment was rejected. Paid amount is Rs 0.'
                : 'Previous payment was rejected. Only verified amounts are counted.';
        }

        return [
            'paid' => $displayPaid,
            'remaining' => $displayRemaining,
            'submitted_amount' => $submittedAmount,
            'is_pending_submission' => ($isPendingVerification && $submittedAmount > 0),
            'is_rejected_submission' => $isRejectedVerification,
            'note' => $note,
        ];
    }
}

if (!function_exists('vs_event_export_filter_chips')) {
    function vs_event_export_filter_chips(array $filters, ?array $selectedEvent = null): array
    {
        $chips = [];

        if ($selectedEvent !== null) {
            $chips[] = [
                'label' => 'Event',
                'value' => (string)($selectedEvent['title'] ?? ('Event #' . (int)($filters['event_id'] ?? 0))),
            ];
        } elseif ((int)($filters['event_id'] ?? 0) > 0) {
            $chips[] = [
                'label' => 'Event',
                'value' => 'Event #' . (int)$filters['event_id'],
            ];
        } else {
            $chips[] = [
                'label' => 'Event',
                'value' => 'All Events',
            ];
        }

        $chips[] = [
            'label' => 'Payment',
            'value' => ($filters['payment_status'] ?? '') !== '' ? (string)$filters['payment_status'] : 'All',
        ];
        $chips[] = [
            'label' => 'Verification',
            'value' => ($filters['verification_status'] ?? '') !== '' ? (string)$filters['verification_status'] : 'All',
        ];

        if (($filters['from_date'] ?? '') !== '' || ($filters['to_date'] ?? '') !== '') {
            $chips[] = [
                'label' => 'Booking Window',
                'value' => (($filters['from_date'] ?? '') !== '' ? (string)$filters['from_date'] : 'Start')
                    . ' to '
                    . (($filters['to_date'] ?? '') !== '' ? (string)$filters['to_date'] : 'Now'),
            ];
        } else {
            $chips[] = [
                'label' => 'Booking Window',
                'value' => 'All Dates',
            ];
        }

        return $chips;
    }
}

if (!function_exists('vs_event_export_fetch_dataset')) {
    function vs_event_export_fetch_dataset(PDO $pdo, array $sourceFilters): array
    {
        $filters = vs_event_export_collect_filters($sourceFilters);
        $selectedEvent = null;

        if ($filters['event_id'] > 0) {
            $eventStmt = $pdo->prepare("SELECT id, title, event_date, event_type, location, status
                FROM events
                WHERE id = ?
                LIMIT 1");
            $eventStmt->execute([$filters['event_id']]);
            $selectedEvent = $eventStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedEvent !== null) {
                $selectedEvent['event_date_display'] = vs_event_get_event_date_display(
                    $pdo,
                    (int)$selectedEvent['id'],
                    (string)($selectedEvent['event_date'] ?? ''),
                    (string)($selectedEvent['event_type'] ?? 'single_day')
                );
            }
        }

        $where = [];
        $params = [];

        if ($filters['event_id'] > 0) {
            $where[] = 'r.event_id = ?';
            $params[] = $filters['event_id'];
        }
        if ($filters['payment_status'] !== '') {
            $where[] = 'r.payment_status = ?';
            $params[] = $filters['payment_status'];
        }
        if ($filters['verification_status'] !== '') {
            $where[] = 'r.verification_status = ?';
            $params[] = $filters['verification_status'];
        }
        if ($filters['from_date'] !== '') {
            $where[] = 'DATE(r.created_at) >= ?';
            $params[] = $filters['from_date'];
        }
        if ($filters['to_date'] !== '') {
            $where[] = 'DATE(r.created_at) <= ?';
            $params[] = $filters['to_date'];
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT
                r.id,
                r.event_id,
                r.event_date_id,
                r.booking_reference,
                r.name,
                r.phone,
                r.persons,
                r.payment_status,
                r.verification_status,
                r.checkin_status,
                r.checkin_time,
                r.created_at,
                e.title AS event_title,
                e.event_type,
                e.location AS event_location,
                e.status AS event_status,
                COALESCE(d.event_date, e.event_date) AS selected_event_date,
                p.package_name,
                p.is_paid,
                COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price, 0) AS package_price_total,
                COALESCE(ep.payment_method, '') AS payment_method,
                COALESCE(ep.payment_type, '') AS payment_type,
                COALESCE(ep.transaction_id, '') AS transaction_id,
                COALESCE(ep.status, '') AS payment_record_status,
                COALESCE(ep.amount, 0) AS payment_amount,
                COALESCE(ep.amount_paid, 0) AS amount_paid,
                COALESCE(ep.remaining_amount, 0) AS remaining_amount,
                COALESCE(ep.remarks, '') AS payment_remarks
            FROM event_registrations r
            INNER JOIN events e ON e.id = r.event_id
            LEFT JOIN event_dates d ON d.id = r.event_date_id
            INNER JOIN event_packages p ON p.id = r.package_id
            LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
            LEFT JOIN event_payments ep ON ep.registration_id = r.id
            $whereSql
            ORDER BY e.event_date DESC, e.id DESC, r.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summary = [
            'total_registrations' => 0,
            'total_persons' => 0,
            'active_persons' => 0,
            'paid_registrations' => 0,
            'partial_paid_registrations' => 0,
            'unpaid_registrations' => 0,
            'pending_verification_registrations' => 0,
            'failed_registrations' => 0,
            'waitlisted_registrations' => 0,
            'cancelled_registrations' => 0,
            'checked_in_registrations' => 0,
            'expected_amount' => 0.0,
            'confirmed_paid_amount' => 0.0,
            'report_paid_amount' => 0.0,
            'pending_due_amount' => 0.0,
        ];

        $registrationIds = [];

        foreach ($rows as $index => $row) {
            $registrationId = (int)($row['id'] ?? 0);
            if ($registrationId <= 0) {
                continue;
            }

            $bookingReference = trim((string)($row['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
                $rows[$index]['booking_reference'] = $bookingReference;
            }

            $eventDateDisplay = vs_event_get_registration_date_display(
                $pdo,
                $row,
                (string)($row['selected_event_date'] ?? '')
            );

            $baseAmounts = vs_event_export_compute_amounts($row);
            $displayAmounts = vs_event_export_compute_display_amounts($row, $baseAmounts);
            $isWaitlisted = function_exists('vs_event_is_waitlisted_registration')
                ? vs_event_is_waitlisted_registration($row)
                : in_array(strtolower(trim((string)($row['payment_status'] ?? ''))), ['waitlisted'], true);
            $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
            $verificationStatusLower = strtolower(trim((string)($row['verification_status'] ?? '')));
            $isCancelled = (!$isWaitlisted && ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled'));
            $isCheckedIn = ((int)($row['checkin_status'] ?? 0) === 1);
            $persons = max((int)($row['persons'] ?? 1), 1);

            $reportPaidAmount = (float)$displayAmounts['paid'];
            $pendingDueAmount = (float)$displayAmounts['remaining'];
            $paymentNote = (string)($displayAmounts['note'] ?? '');

            if ($isWaitlisted) {
                $reportPaidAmount = 0.0;
                $pendingDueAmount = 0.0;
                $paymentNote = 'Payment opens after waitlist confirmation.';
            } elseif ($isCancelled) {
                $pendingDueAmount = 0.0;
                if ($paymentNote === '') {
                    $paymentNote = 'Booking cancelled.';
                }
            }

            $summary['total_registrations']++;
            $summary['total_persons'] += $persons;
            $summary['confirmed_paid_amount'] += (float)$baseAmounts['paid'];
            $summary['report_paid_amount'] += $reportPaidAmount;
            $summary['checked_in_registrations'] += $isCheckedIn ? 1 : 0;

            if ($isWaitlisted) {
                $summary['waitlisted_registrations']++;
            } elseif ($isCancelled) {
                $summary['cancelled_registrations']++;
            } else {
                $summary['active_persons'] += $persons;
                $summary['expected_amount'] += (float)$baseAmounts['total'];
                $summary['pending_due_amount'] += $pendingDueAmount;
            }

            if ($paymentStatusLower === 'paid') {
                $summary['paid_registrations']++;
            } elseif ($paymentStatusLower === 'partial paid') {
                $summary['partial_paid_registrations']++;
            } elseif ($paymentStatusLower === 'pending verification') {
                $summary['pending_verification_registrations']++;
            } elseif ($paymentStatusLower === 'failed') {
                $summary['failed_registrations']++;
            } elseif ($paymentStatusLower === 'unpaid') {
                $summary['unpaid_registrations']++;
            }

            $rows[$index]['event_date_display'] = $eventDateDisplay;
            $rows[$index]['is_waitlisted'] = $isWaitlisted;
            $rows[$index]['is_cancelled'] = $isCancelled;
            $rows[$index]['is_checked_in'] = $isCheckedIn;
            $rows[$index]['total_amount'] = round((float)$baseAmounts['total'], 2);
            $rows[$index]['confirmed_paid_amount'] = round((float)$baseAmounts['paid'], 2);
            $rows[$index]['report_paid_amount'] = round($reportPaidAmount, 2);
            $rows[$index]['pending_due_amount'] = round($pendingDueAmount, 2);
            $rows[$index]['submitted_amount'] = round((float)($displayAmounts['submitted_amount'] ?? 0), 2);
            $rows[$index]['payment_note'] = $paymentNote;
            $rows[$index]['checkin_status_text'] = $isCheckedIn ? 'Checked In' : 'Not Checked In';
            $rows[$index]['payment_type_text'] = trim((string)($row['payment_type'] ?? '')) !== ''
                ? ucfirst((string)$row['payment_type'])
                : '-';
            $rows[$index]['phone_display'] = function_exists('vs_format_mobile_for_display')
                ? vs_format_mobile_for_display((string)($row['phone'] ?? ''))
                : (string)($row['phone'] ?? '');
            $rows[$index]['dynamic_fields'] = [];
            $rows[$index]['dynamic_summary'] = '';

            $registrationIds[] = $registrationId;
        }

        $dynamicHeaders = [];
        $dynamicValuesByRegistration = [];
        $skipDynamicHeaders = [
            'name' => true,
            'phone' => true,
            'persons' => true,
            'booking reference' => true,
            'selected event date' => true,
        ];

        if (!empty($registrationIds)) {
            foreach (array_chunk($registrationIds, 300) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $fieldSql = "SELECT registration_id, field_name, value
                    FROM event_registration_data
                    WHERE registration_id IN ($placeholders)
                    ORDER BY registration_id ASC, id ASC";
                $fieldStmt = $pdo->prepare($fieldSql);
                $fieldStmt->execute($chunk);
                $fieldRows = $fieldStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($fieldRows as $fieldRow) {
                    $fieldName = trim((string)($fieldRow['field_name'] ?? ''));
                    if ($fieldName === '' || strpos($fieldName, '__event_reminder_') === 0) {
                        continue;
                    }

                    $fieldLabel = vs_event_export_field_label($fieldName);
                    if ($fieldLabel === '') {
                        continue;
                    }
                    if (isset($skipDynamicHeaders[strtolower($fieldLabel)])) {
                        continue;
                    }

                    $fieldValue = vs_event_export_field_value((string)($fieldRow['value'] ?? ''));
                    if (!array_key_exists($fieldLabel, $dynamicHeaders)) {
                        $dynamicHeaders[$fieldLabel] = $fieldLabel;
                    }

                    $fieldRegistrationId = (int)($fieldRow['registration_id'] ?? 0);
                    if (!isset($dynamicValuesByRegistration[$fieldRegistrationId])) {
                        $dynamicValuesByRegistration[$fieldRegistrationId] = [];
                    }

                    if (!isset($dynamicValuesByRegistration[$fieldRegistrationId][$fieldLabel]) || $dynamicValuesByRegistration[$fieldRegistrationId][$fieldLabel] === '') {
                        $dynamicValuesByRegistration[$fieldRegistrationId][$fieldLabel] = $fieldValue;
                    } elseif ($fieldValue !== '' && strpos($dynamicValuesByRegistration[$fieldRegistrationId][$fieldLabel], $fieldValue) === false) {
                        $dynamicValuesByRegistration[$fieldRegistrationId][$fieldLabel] .= "\n" . $fieldValue;
                    }
                }
            }
        }

        $dynamicHeaders = array_values($dynamicHeaders);

        foreach ($rows as $index => $row) {
            $registrationId = (int)($row['id'] ?? 0);
            $rowDynamicValues = $dynamicValuesByRegistration[$registrationId] ?? [];
            $summaryLines = [];

            foreach ($dynamicHeaders as $dynamicHeader) {
                $value = (string)($rowDynamicValues[$dynamicHeader] ?? '');
                if ($value !== '') {
                    $summaryLines[] = $dynamicHeader . ': ' . str_replace("\n", ', ', $value);
                }
            }

            $rows[$index]['dynamic_fields'] = $rowDynamicValues;
            $rows[$index]['dynamic_summary'] = implode("\n", $summaryLines);
        }

        foreach (['expected_amount', 'confirmed_paid_amount', 'report_paid_amount', 'pending_due_amount'] as $amountKey) {
            $summary[$amountKey] = round((float)$summary[$amountKey], 2);
        }

        return [
            'filters' => $filters,
            'selected_event' => $selectedEvent,
            'filter_chips' => vs_event_export_filter_chips($filters, $selectedEvent),
            'rows' => $rows,
            'dynamic_headers' => $dynamicHeaders,
            'summary' => $summary,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
