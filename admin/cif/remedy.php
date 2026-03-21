<?php
require_once (is_file(__DIR__ . '/../includes/permissions.php') ? __DIR__ . '/../includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/remedy_module.php';

vs_remedy_ensure_tables($pdo);

if (!function_exists('vs_remedy_json_response')) {
    function vs_remedy_json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('vs_remedy_admin_identity')) {
    function vs_remedy_admin_identity(PDO $pdo): array
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $userName = trim((string) ($_SESSION['user_name'] ?? ''));

        if ($userId > 0 && $userName === '') {
            try {
                $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $userName = trim((string) $stmt->fetchColumn());
            } catch (Throwable $throwable) {
                unset($throwable);
            }
        }

        if ($userName === '') {
            $userName = 'Admin';
        }

        return [
            'id' => $userId > 0 ? $userId : null,
            'name' => $userName,
        ];
    }
}

if (!function_exists('vs_remedy_parse_rows_payload')) {
    function vs_remedy_parse_rows_payload(array $source): array
    {
        $rowsJson = trim((string) ($source['rows_json'] ?? ''));
        if ($rowsJson === '') {
            return [];
        }

        $rows = json_decode($rowsJson, true);
        if (!is_array($rows)) {
            throw new InvalidArgumentException('Unable to read the perform entries. Please try again.');
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = [
                'instructions' => trim((string) ($row['instructions'] ?? $row['note'] ?? '')),
            ];
        }

        return $normalized;
    }
}

if (!function_exists('vs_remedy_build_state')) {
    function vs_remedy_build_state(PDO $pdo, int $clientId): array
    {
        $client = vs_remedy_fetch_client($pdo, $clientId);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $remedies = vs_remedy_fetch_for_client($pdo, $clientId);
        $rowsByRemedy = vs_remedy_fetch_rows_for_client($pdo, $clientId);
        $logs = vs_remedy_fetch_logs_for_client($pdo, $clientId, 18);

        $stateRemedies = [];
        $totalEntries = 0;
        $completedEntries = 0;

        foreach ($remedies as $remedy) {
            $remedyId = (int) ($remedy['id'] ?? 0);
            $rows = $rowsByRemedy[$remedyId] ?? [];
            $formattedRows = [];
            $completedRows = 0;
            $nextPendingLabel = '';

            foreach ($rows as $row) {
                $rowStatus = vs_remedy_row_due_status($row);
                if ((int) ($row['is_completed'] ?? 0) === 1) {
                    $completedRows++;
                } elseif ($nextPendingLabel === '') {
                    $nextPendingLabel = vs_remedy_row_schedule_label($row);
                }

                $formattedRows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'sequence_no' => (int) ($row['sequence_no'] ?? 0),
                    'label' => vs_remedy_row_schedule_label($row),
                    'instructions' => (string) ($row['instructions'] ?? ''),
                    'is_completed' => (int) ($row['is_completed'] ?? 0) === 1,
                    'completed_date' => (string) ($row['completed_date'] ?? ''),
                    'status' => $rowStatus,
                ];
            }

            $totalRows = count($formattedRows);
            $pendingRows = max(0, $totalRows - $completedRows);
            $totalEntries += $totalRows;
            $completedEntries += $completedRows;

            $stateRemedies[] = [
                'id' => $remedyId,
                'title' => (string) ($remedy['title'] ?? ''),
                'description' => (string) ($remedy['description'] ?? ''),
                'next_due_date' => (string) ($remedy['next_due_date'] ?? ''),
                'last_completed_date' => (string) ($remedy['last_completed_date'] ?? ''),
                'total_rows' => $totalRows,
                'completed_rows' => $completedRows,
                'pending_rows' => $pendingRows,
                'status' => vs_remedy_due_status(
                    (string) ($remedy['next_due_date'] ?? ''),
                    $totalRows > 0 && $pendingRows === 0
                ),
                'next_action_label' => $pendingRows > 0
                    ? ('Next: ' . ($nextPendingLabel !== '' ? $nextPendingLabel : 'Pending Entry'))
                    : 'All entries completed',
                'rows' => $formattedRows,
            ];
        }

        $formattedLogs = [];
        foreach ($logs as $log) {
            $completedBy = trim((string) ($log['created_by_user_name'] ?? ''));
            if ($completedBy === '' && (int) ($log['created_by_user_id'] ?? 0) > 0) {
                $completedBy = 'User #' . (int) $log['created_by_user_id'];
            }
            if ($completedBy === '') {
                $completedBy = 'Admin';
            }

            $formattedLogs[] = [
                'id' => (int) ($log['id'] ?? 0),
                'remedy_id' => (int) ($log['remedy_id'] ?? 0),
                'schedule_label' => trim((string) ($log['schedule_label'] ?? '')) !== ''
                    ? (string) $log['schedule_label']
                    : 'Perform Entry',
                'remedy_title' => (string) ($log['remedy_title'] ?? ''),
                'completed_date' => (string) ($log['completed_date'] ?? ''),
                'completed_by' => $completedBy,
            ];
        }

        return [
            'client' => [
                'id' => (int) ($client['id'] ?? 0),
                'name' => (string) ($client['name'] ?? ''),
                'mobile' => (string) ($client['mobile'] ?? ''),
                'address' => (string) ($client['address'] ?? ''),
                'dob' => (string) ($client['dob'] ?? ''),
                'birth_time' => (string) ($client['birth_time'] ?? ''),
                'birth_place' => (string) ($client['birth_place'] ?? ''),
            ],
            'stats' => [
                'total_remedies' => count($stateRemedies),
                'total_entries' => $totalEntries,
                'completed_entries' => $completedEntries,
                'pending_entries' => max(0, $totalEntries - $completedEntries),
            ],
            'remedies' => $stateRemedies,
            'recent_logs' => $formattedLogs,
        ];
    }
}

$clientId = isset($_REQUEST['client_id']) ? (int) $_REQUEST['client_id'] : 0;
$isAjax = isset($_REQUEST['ajax']) && (string) $_REQUEST['ajax'] !== '0';

if ($isAjax) {
    if ($clientId <= 0) {
        vs_remedy_json_response([
            'success' => false,
            'message' => 'Please select a client first.',
        ], 400);
    }

    try {
        $client = vs_remedy_fetch_client($pdo, $clientId);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            vs_remedy_json_response([
                'success' => true,
                'state' => vs_remedy_build_state($pdo, $clientId),
            ]);
        }

        $action = trim((string) ($_POST['action'] ?? ''));
        $adminIdentity = vs_remedy_admin_identity($pdo);
        $focusRemedyId = 0;
        $message = 'Changes saved.';

        switch ($action) {
            case 'add_remedy':
                $rows = vs_remedy_parse_rows_payload($_POST);
                $repeatQty = max(1, (int) ($_POST['repeat_qty'] ?? count($rows) ?: 1));
                $focusRemedyId = vs_remedy_create($pdo, $clientId, [
                    'title' => trim((string) ($_POST['title'] ?? '')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'repeat_qty' => $repeatQty,
                ], $rows);
                $message = 'Remedy added successfully.';
                break;

            case 'update_remedy':
                $focusRemedyId = (int) ($_POST['remedy_id'] ?? 0);
                vs_remedy_update($pdo, $focusRemedyId, $clientId, [
                    'title' => trim((string) ($_POST['title'] ?? '')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                ]);
                $message = 'Remedy details updated.';
                break;

            case 'add_row':
                $focusRemedyId = (int) ($_POST['remedy_id'] ?? 0);
                vs_remedy_add_row($pdo, $focusRemedyId, $clientId, [
                    'instructions' => trim((string) ($_POST['instructions'] ?? '')),
                    'is_completed' => trim((string) ($_POST['is_completed'] ?? '0')),
                    'completed_date' => trim((string) ($_POST['completed_date'] ?? '')),
                ]);
                $message = 'New perform entry added.';
                break;

            case 'save_row':
                $rowId = (int) ($_POST['row_id'] ?? 0);
                $updatedRow = vs_remedy_update_row(
                    $pdo,
                    $rowId,
                    $clientId,
                    [
                        'instructions' => trim((string) ($_POST['instructions'] ?? '')),
                        'is_completed' => trim((string) ($_POST['is_completed'] ?? '0')),
                        'completed_date' => trim((string) ($_POST['completed_date'] ?? '')),
                    ],
                    $adminIdentity['id'],
                    $adminIdentity['name']
                );
                $focusRemedyId = (int) ($updatedRow['remedy_id'] ?? 0);
                $message = 'Perform entry updated.';
                break;

            case 'delete_row':
                $rowId = (int) ($_POST['row_id'] ?? 0);
                $deletedRow = vs_remedy_delete_row($pdo, $rowId, $clientId);
                $focusRemedyId = (int) ($deletedRow['remedy_id'] ?? 0);
                $message = 'Perform entry deleted.';
                break;

            case 'delete_remedy':
                $focusRemedyId = (int) ($_POST['remedy_id'] ?? 0);
                vs_remedy_delete($pdo, $focusRemedyId, $clientId);
                $focusRemedyId = 0;
                $message = 'Remedy deleted.';
                break;

            default:
                throw new InvalidArgumentException('Unsupported remedy action.');
        }

        vs_remedy_json_response([
            'success' => true,
            'message' => $message,
            'focus_remedy_id' => $focusRemedyId,
            'state' => vs_remedy_build_state($pdo, $clientId),
        ]);
    } catch (Throwable $throwable) {
        vs_remedy_json_response([
            'success' => false,
            'message' => $throwable->getMessage(),
        ], 422);
    }
}

$selectedClient = $clientId > 0 ? vs_remedy_fetch_client($pdo, $clientId) : null;
$initialState = $selectedClient ? vs_remedy_build_state($pdo, $clientId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Remedy Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body {
    margin: 0;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(180deg, #faf6f6 0%, #f4f1f1 100%);
    color: #2b2b2b;
}

.remedy-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 26px 14px 40px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: flex-start;
    margin-bottom: 22px;
}

.page-header h1 {
    margin: 10px 0 8px;
    color: #7a1010;
    font-size: 2rem;
}

.page-header p {
    margin: 0;
    max-width: 720px;
    color: #675b5b;
    line-height: 1.6;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #7a1010;
    text-decoration: none;
    font-weight: 600;
}

.back-link:hover {
    text-decoration: underline;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.panel-card {
    background: #ffffff;
    border: 1px solid #eddada;
    border-radius: 18px;
    box-shadow: 0 10px 24px rgba(128, 0, 0, 0.06);
}

.client-summary {
    padding: 22px;
    margin-bottom: 18px;
    background: linear-gradient(135deg, #fff6f4 0%, #ffffff 60%);
}

.client-summary-top {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: flex-start;
    margin-bottom: 18px;
}

.client-summary-top h2 {
    margin: 0 0 6px;
    color: #7a1010;
    font-size: 1.5rem;
}

.client-summary-top p {
    margin: 0;
    color: #6f6666;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.info-chip {
    background: #fffaf9;
    border: 1px solid #efdddd;
    border-radius: 14px;
    padding: 14px 16px;
}

.info-chip-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #9a7b7b;
}

.info-chip-value {
    display: block;
    color: #332929;
    font-weight: 600;
    line-height: 1.4;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.stat-card {
    padding: 18px;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: #7a1010;
    margin-bottom: 4px;
}

.stat-card-label {
    color: #5e5353;
    font-weight: 600;
}

.layout-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(290px, 0.95fr);
    gap: 18px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: flex-start;
    padding: 22px 22px 0;
}

.panel-head h2,
.panel-head h3 {
    margin: 0 0 6px;
    color: #7a1010;
}

.panel-head p {
    margin: 0;
    color: #726666;
    line-height: 1.5;
}

.panel-head-tight {
    padding-bottom: 18px;
}

.remedy-list {
    padding: 18px 22px 22px;
}

.remedy-card {
    border: 1px solid #eedede;
    border-radius: 18px;
    padding: 18px;
    background: #fffdfd;
}

.remedy-card + .remedy-card {
    margin-top: 16px;
}

.remedy-card-top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 12px;
}

.remedy-card-title h3 {
    margin: 0 0 6px;
    color: #401f1f;
    font-size: 1.2rem;
}

.remedy-card-title p {
    margin: 0;
    color: #6d6262;
    line-height: 1.55;
}

.remedy-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 14px 0 16px;
}

.meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 999px;
    background: #fbf5f5;
    border: 1px solid #ead7d7;
    color: #5f5252;
    font-size: 0.92rem;
    font-weight: 600;
}

.progress-track {
    height: 10px;
    border-radius: 999px;
    background: #f0e2e2;
    overflow: hidden;
    margin-bottom: 16px;
}

.progress-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #800000 0%, #c76338 100%);
}

.perform-list {
    display: grid;
    gap: 12px;
}

.perform-item {
    border: 1px solid #efe4e4;
    border-radius: 14px;
    padding: 14px;
    background: #fffafa;
}

.perform-item-top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    margin-bottom: 8px;
}

.perform-item-title {
    font-weight: 700;
    color: #412929;
}

.perform-item-meta {
    color: #7d6a6a;
    font-size: 0.92rem;
    margin-bottom: 6px;
}

.perform-item-note {
    color: #4a4040;
    line-height: 1.55;
    white-space: pre-line;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 102px;
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.status-pill.completed {
    background: #edf8ef;
    color: #1f7a33;
}

.status-pill.pending {
    background: #fff1ea;
    color: #b14418;
}

.primary-btn,
.ghost-btn,
.danger-btn,
.small-btn {
    appearance: none;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
}

.primary-btn {
    background: #800000;
    color: #ffffff;
    padding: 12px 18px;
    box-shadow: 0 10px 20px rgba(128, 0, 0, 0.16);
}

.ghost-btn {
    background: #f7eded;
    color: #7a1010;
    padding: 12px 18px;
}

.danger-btn {
    background: #fff0f0;
    color: #a72727;
    padding: 12px 18px;
}

.small-btn {
    padding: 10px 14px;
    background: #f7eded;
    color: #7a1010;
}

.primary-btn:hover,
.ghost-btn:hover,
.danger-btn:hover,
.small-btn:hover {
    transform: translateY(-1px);
}

.primary-btn:disabled,
.ghost-btn:disabled,
.danger-btn:disabled,
.small-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
}

.activity-panel {
    padding-bottom: 8px;
}

.activity-list {
    padding: 0 22px 22px;
}

.activity-item {
    display: block;
    border: 1px solid #efe3e3;
    border-radius: 14px;
    padding: 14px;
    background: #fffdfd;
}

.activity-item + .activity-item {
    margin-top: 12px;
}

.activity-item strong {
    display: block;
    color: #412929;
    margin-bottom: 4px;
}

.activity-item span {
    display: block;
    color: #7d6d6d;
    line-height: 1.5;
}

.empty-state {
    padding: 34px 24px;
    text-align: center;
    color: #6d6161;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #7a1010;
}

.empty-state p {
    margin: 0;
    line-height: 1.6;
}

.flash-message {
    position: sticky;
    top: 14px;
    z-index: 40;
    margin-bottom: 14px;
    padding: 14px 18px;
    border-radius: 14px;
    font-weight: 700;
    border: 1px solid transparent;
}

.flash-message.success {
    background: #eef9f0;
    border-color: #cde9d2;
    color: #216536;
}

.flash-message.error {
    background: #fff1f1;
    border-color: #f1d2d2;
    color: #9d2525;
}

.flash-message.info {
    background: #f8f2e8;
    border-color: #eadcc4;
    color: #7a4e14;
}

.drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(50, 17, 17, 0.44);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease;
    z-index: 80;
}

.drawer-backdrop.active {
    opacity: 1;
    visibility: visible;
}

.drawer-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: min(760px, 100%);
    height: 100vh;
    background: #fffdfd;
    box-shadow: -20px 0 40px rgba(34, 14, 14, 0.12);
    transform: translateX(104%);
    transition: transform 0.24s ease;
    z-index: 90;
    display: flex;
    flex-direction: column;
}

.drawer-panel.active {
    transform: translateX(0);
}

.drawer-header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    padding: 22px 22px 14px;
    border-bottom: 1px solid #efe1e1;
}

.drawer-header h2 {
    margin: 0 0 6px;
    color: #7a1010;
}

.drawer-header p {
    margin: 0;
    color: #6f6363;
    line-height: 1.5;
}

.drawer-close {
    border: none;
    background: #f7eded;
    color: #7a1010;
    border-radius: 12px;
    padding: 10px 14px;
    font-weight: 700;
    cursor: pointer;
}

.drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px 22px 28px;
}

.drawer-section + .drawer-section {
    margin-top: 22px;
}

.section-title {
    margin: 0 0 6px;
    color: #4a2c2c;
    font-size: 1.05rem;
}

.section-note {
    margin: 0 0 14px;
    color: #6d6161;
    line-height: 1.55;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.field-block {
    display: block;
}

.field-block-wide {
    grid-column: 1 / -1;
}

.field-label {
    display: block;
    margin-bottom: 8px;
    color: #553737;
    font-size: 0.94rem;
    font-weight: 700;
}

.field-input,
.field-textarea,
.field-select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #dfcaca;
    border-radius: 12px;
    background: #ffffff;
    padding: 12px 14px;
    color: #2f2b2b;
    font-size: 1rem;
    font-family: inherit;
}

.field-input:focus,
.field-textarea:focus,
.field-select:focus {
    outline: none;
    border-color: #b36363;
    box-shadow: 0 0 0 3px rgba(191, 114, 114, 0.14);
}

.field-textarea {
    min-height: 118px;
    resize: vertical;
}

.rows-builder,
.manage-rows-list {
    display: grid;
    gap: 14px;
}

.row-builder-card,
.manage-row-card,
.manage-summary {
    border: 1px solid #eedede;
    border-radius: 16px;
    background: #fffafa;
    padding: 16px;
}

.row-builder-card h4,
.manage-row-card h4,
.manage-summary h3 {
    margin: 0 0 10px;
    color: #452b2b;
}

.manage-summary-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
}

.drawer-actions,
.row-card-actions,
.summary-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}

.manage-row-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.manage-row-grid .field-block-wide {
    grid-column: 1 / -1;
}

.manage-row-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
}

.muted-text {
    color: #7b6e6e;
}

.helper-note {
    margin-top: 10px;
    color: #7d6d6d;
    font-size: 0.92rem;
    line-height: 1.5;
}

body.drawer-open {
    overflow: hidden;
}

@media (max-width: 1100px) {
    .layout-grid {
        grid-template-columns: 1fr;
    }

    .info-grid,
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .page-header,
    .client-summary-top,
    .panel-head,
    .remedy-card-top,
    .drawer-header,
    .manage-row-top {
        flex-direction: column;
        align-items: stretch;
    }

    .info-grid,
    .stats-grid,
    .form-grid,
    .manage-row-grid {
        grid-template-columns: 1fr;
    }

    .drawer-panel {
        width: 100%;
    }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="remedy-page">
    <div class="page-header">
        <div>
            <a class="back-link" href="index.php<?php echo $clientId > 0 ? '?client_id=' . (int) $clientId : ''; ?>">&larr; Back to CIF</a>
            <h1>Remedy Management</h1>
            <p>Review the client remedies first, then open a separate drawer to add or manage entries. Everything updates on this page without a reload.</p>
        </div>
        <?php if ($selectedClient): ?>
            <div class="header-actions">
                <button type="button" class="primary-btn open-add-drawer">Add Remedy</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($clientId <= 0): ?>
        <div class="panel-card empty-state">
            <h3>Select A Client First</h3>
            <p>Open a client from the CIF panel and use the Manage Remedies button there. This page works client-wise so the remedies stay linked to the correct person.</p>
        </div>
    <?php elseif (!$selectedClient): ?>
        <div class="panel-card empty-state">
            <h3>Client Not Found</h3>
            <p>The selected client could not be loaded. Please go back to CIF and open the client again.</p>
        </div>
    <?php else: ?>
        <div id="flashMessage" class="flash-message" hidden></div>

        <section id="clientSummary" class="client-summary panel-card"></section>
        <section id="remedyStats" class="stats-grid"></section>

        <div class="layout-grid">
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <h2>Client Remedies</h2>
                        <p>Each remedy card shows all perform entries for this client. Use Manage to edit notes, status, performed date, or add and delete entries.</p>
                    </div>
                    <button type="button" class="ghost-btn open-add-drawer">Add Remedy</button>
                </div>
                <div id="remedyList" class="remedy-list"></div>
            </section>

            <aside class="panel-card activity-panel">
                <div class="panel-head panel-head-tight">
                    <div>
                        <h2>Recent Updates</h2>
                        <p>Latest completed remedy entries for this client.</p>
                    </div>
                </div>
                <div id="remedyLogs" class="activity-list"></div>
            </aside>
        </div>

        <div id="drawerBackdrop" class="drawer-backdrop"></div>

        <aside id="addDrawer" class="drawer-panel" aria-hidden="true">
            <div class="drawer-header">
                <div>
                    <h2>Add Remedy</h2>
                    <p>Create the remedy first, set how many times it should be performed, and add simple notes for each perform entry.</p>
                </div>
                <button type="button" class="drawer-close" data-close-drawer="add">Close</button>
            </div>
            <div class="drawer-body">
                <form id="addRemedyForm">
                    <div class="drawer-section">
                        <div class="form-grid">
                            <label class="field-block">
                                <span class="field-label">Remedy Name</span>
                                <input type="text" id="addRemedyTitle" name="title" class="field-input" required>
                            </label>
                            <label class="field-block">
                                <span class="field-label">How Many Times To Perform</span>
                                <input type="number" id="addRepeatQty" name="repeat_qty" class="field-input" min="1" max="50" value="1" required>
                            </label>
                            <label class="field-block field-block-wide">
                                <span class="field-label">Remedy Notes</span>
                                <textarea id="addRemedyDescription" name="description" class="field-textarea" placeholder="Add any overall notes for this remedy."></textarea>
                            </label>
                        </div>
                    </div>

                    <div class="drawer-section">
                        <h3 class="section-title">Perform Entries</h3>
                        <p class="section-note">Each row below becomes one trackable perform entry for this client.</p>
                        <div id="addRowsContainer" class="rows-builder"></div>
                    </div>

                    <div class="drawer-actions">
                        <button type="submit" class="primary-btn">Save Remedy</button>
                        <button type="button" class="ghost-btn" data-close-drawer="add">Cancel</button>
                    </div>
                </form>
            </div>
        </aside>

        <aside id="manageDrawer" class="drawer-panel" aria-hidden="true">
            <div class="drawer-header">
                <div>
                    <h2 id="manageDrawerTitle">Manage Remedy</h2>
                    <p>Update remedy details, keep notes clear, and mark each perform entry as completed or not completed yet.</p>
                </div>
                <button type="button" class="drawer-close" data-close-drawer="manage">Close</button>
            </div>
            <div id="manageDrawerBody" class="drawer-body"></div>
        </aside>

        <noscript>
            <div class="panel-card empty-state" style="margin-top:18px;">
                <h3>JavaScript Required</h3>
                <p>This redesigned remedy page uses same-page saving and update drawers, so JavaScript needs to be enabled in the browser.</p>
            </div>
        </noscript>
    <?php endif; ?>
</div>

<?php if ($selectedClient): ?>
<script>
(() => {
    const clientId = <?php echo (int) $clientId; ?>;
    let state = <?php echo json_encode($initialState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    let addDraftRows = [{ instructions: '' }];
    let activeDrawer = null;
    let activeRemedyId = null;
    let isSubmitting = false;
    let flashTimer = null;

    const flashMessageEl = document.getElementById('flashMessage');
    const clientSummaryEl = document.getElementById('clientSummary');
    const remedyStatsEl = document.getElementById('remedyStats');
    const remedyListEl = document.getElementById('remedyList');
    const remedyLogsEl = document.getElementById('remedyLogs');
    const addDrawerEl = document.getElementById('addDrawer');
    const manageDrawerEl = document.getElementById('manageDrawer');
    const manageDrawerBodyEl = document.getElementById('manageDrawerBody');
    const manageDrawerTitleEl = document.getElementById('manageDrawerTitle');
    const drawerBackdropEl = document.getElementById('drawerBackdrop');
    const addRepeatQtyEl = document.getElementById('addRepeatQty');
    const addRowsContainerEl = document.getElementById('addRowsContainer');
    const addRemedyFormEl = document.getElementById('addRemedyForm');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMultiline(value) {
        const safeValue = escapeHtml(value);
        return safeValue === '' ? '<span class="muted-text">No notes added.</span>' : safeValue.replace(/\n/g, '<br>');
    }

    function formatDate(value) {
        if (!value) {
            return 'Not set';
        }

        const date = new Date(String(value) + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        }).format(date);
    }

    function todayIso() {
        return new Date().toISOString().slice(0, 10);
    }

    function clampRepeatQty(value) {
        const numericValue = parseInt(value, 10);
        if (Number.isNaN(numericValue) || numericValue < 1) {
            return 1;
        }
        if (numericValue > 50) {
            return 50;
        }
        return numericValue;
    }

    function showFlash(message, type = 'success') {
        if (!flashMessageEl) {
            return;
        }

        flashMessageEl.hidden = false;
        flashMessageEl.className = 'flash-message ' + type;
        flashMessageEl.textContent = message;

        if (flashTimer) {
            window.clearTimeout(flashTimer);
        }

        if (type !== 'info') {
            flashTimer = window.setTimeout(() => {
                flashMessageEl.hidden = true;
            }, 3200);
        }
    }

    function openDrawer(name) {
        activeDrawer = name;
        drawerBackdropEl.classList.add('active');
        document.body.classList.add('drawer-open');

        if (name === 'add') {
            addDrawerEl.classList.add('active');
            addDrawerEl.setAttribute('aria-hidden', 'false');
            manageDrawerEl.classList.remove('active');
            manageDrawerEl.setAttribute('aria-hidden', 'true');
        } else if (name === 'manage') {
            manageDrawerEl.classList.add('active');
            manageDrawerEl.setAttribute('aria-hidden', 'false');
            addDrawerEl.classList.remove('active');
            addDrawerEl.setAttribute('aria-hidden', 'true');
        }
    }

    function closeDrawer(name) {
        if (name === 'add') {
            addDrawerEl.classList.remove('active');
            addDrawerEl.setAttribute('aria-hidden', 'true');
        }

        if (name === 'manage') {
            manageDrawerEl.classList.remove('active');
            manageDrawerEl.setAttribute('aria-hidden', 'true');
        }

        if (!addDrawerEl.classList.contains('active') && !manageDrawerEl.classList.contains('active')) {
            drawerBackdropEl.classList.remove('active');
            document.body.classList.remove('drawer-open');
            activeDrawer = null;
        }
    }

    function closeAllDrawers() {
        closeDrawer('add');
        closeDrawer('manage');
    }

    function syncAddDraftRowsFromDom() {
        const noteFields = addRowsContainerEl.querySelectorAll('.add-row-note');
        if (!noteFields.length) {
            return;
        }

        addDraftRows = Array.from(noteFields).map((field) => ({
            instructions: field.value
        }));
    }

    function ensureAddDraftRowCount(count) {
        const nextCount = clampRepeatQty(count);
        syncAddDraftRowsFromDom();

        if (addDraftRows.length > nextCount) {
            addDraftRows = addDraftRows.slice(0, nextCount);
        }

        while (addDraftRows.length < nextCount) {
            addDraftRows.push({ instructions: '' });
        }

        addRepeatQtyEl.value = String(nextCount);
        renderAddRows();
    }

    function renderAddRows() {
        addRowsContainerEl.innerHTML = addDraftRows.map((row, index) => `
            <div class="row-builder-card">
                <h4>Perform ${index + 1}</h4>
                <label class="field-block">
                    <span class="field-label">Description / Instructions</span>
                    <textarea class="field-textarea add-row-note" data-row-index="${index}" placeholder="Add the note for this perform entry.">${escapeHtml(row.instructions || '')}</textarea>
                </label>
            </div>
        `).join('');
    }

    function resetAddForm() {
        addRemedyFormEl.reset();
        addDraftRows = [{ instructions: '' }];
        addRepeatQtyEl.value = '1';
        renderAddRows();
    }

    function findRemedy(remedyId) {
        return (state.remedies || []).find((remedy) => Number(remedy.id) === Number(remedyId)) || null;
    }

    function renderStatusPill(status) {
        const safeStatus = status || { label: 'Not Yet Done', class: 'pending' };
        return `<span class="status-pill ${escapeHtml(safeStatus.class || 'pending')}">${escapeHtml(safeStatus.label || 'Not Yet Done')}</span>`;
    }

    function renderClientSummary() {
        const client = state.client || {};

        clientSummaryEl.innerHTML = `
            <div class="client-summary-top">
                <div>
                    <h2>${escapeHtml(client.name || 'Client')}</h2>
                    <p>All remedy entries below belong only to this client, so updates stay easy to trace and follow.</p>
                </div>
                <button type="button" class="primary-btn open-add-drawer">Add Remedy</button>
            </div>
            <div class="info-grid">
                <div class="info-chip">
                    <span class="info-chip-label">Mobile</span>
                    <span class="info-chip-value">${escapeHtml(client.mobile || '-')}</span>
                </div>
                <div class="info-chip">
                    <span class="info-chip-label">Address</span>
                    <span class="info-chip-value">${escapeHtml(client.address || '-')}</span>
                </div>
                <div class="info-chip">
                    <span class="info-chip-label">Date Of Birth</span>
                    <span class="info-chip-value">${escapeHtml(client.dob || '-')}</span>
                </div>
                <div class="info-chip">
                    <span class="info-chip-label">Birth Details</span>
                    <span class="info-chip-value">${escapeHtml(client.birth_time || '-')} / ${escapeHtml(client.birth_place || '-')}</span>
                </div>
            </div>
        `;
    }

    function renderStats() {
        const stats = state.stats || {};
        const cards = [
            { label: 'Total Remedies', value: stats.total_remedies || 0 },
            { label: 'Total Perform Entries', value: stats.total_entries || 0 },
            { label: 'Completed Entries', value: stats.completed_entries || 0 },
            { label: 'Pending Entries', value: stats.pending_entries || 0 }
        ];

        remedyStatsEl.innerHTML = cards.map((card) => `
            <div class="panel-card stat-card">
                <div class="stat-card-value">${escapeHtml(card.value)}</div>
                <div class="stat-card-label">${escapeHtml(card.label)}</div>
            </div>
        `).join('');
    }

    function renderRemedyList() {
        const remedies = state.remedies || [];

        if (!remedies.length) {
            remedyListEl.innerHTML = `
                <div class="empty-state">
                    <h3>No Remedies Added Yet</h3>
                    <p>Use the Add Remedy button to create the first remedy for this client. Each remedy can then be managed in its own separate panel.</p>
                </div>
            `;
            return;
        }

        remedyListEl.innerHTML = remedies.map((remedy) => {
            const progressPercent = remedy.total_rows > 0
                ? Math.round((Number(remedy.completed_rows || 0) / Number(remedy.total_rows || 1)) * 100)
                : 0;

            return `
                <article class="remedy-card">
                    <div class="remedy-card-top">
                        <div class="remedy-card-title">
                            <h3>${escapeHtml(remedy.title || 'Untitled Remedy')}</h3>
                            <p>${formatMultiline(remedy.description || '')}</p>
                        </div>
                        <div>
                            <div style="margin-bottom:10px;">${renderStatusPill(remedy.status)}</div>
                            <button type="button" class="small-btn open-manage-drawer" data-remedy-id="${Number(remedy.id)}">Manage</button>
                        </div>
                    </div>

                    <div class="remedy-meta-row">
                        <span class="meta-pill">Entries: ${Number(remedy.total_rows || 0)}</span>
                        <span class="meta-pill">Completed: ${Number(remedy.completed_rows || 0)}</span>
                        <span class="meta-pill">Pending: ${Number(remedy.pending_rows || 0)}</span>
                        <span class="meta-pill">${escapeHtml(remedy.next_action_label || 'Next step available')}</span>
                    </div>

                    <div class="progress-track">
                        <span style="width:${progressPercent}%;"></span>
                    </div>

                    <div class="perform-list">
                        ${remedy.rows.map((row) => `
                            <div class="perform-item">
                                <div class="perform-item-top">
                                    <div class="perform-item-title">${escapeHtml(row.label || 'Perform Entry')}</div>
                                    ${renderStatusPill(row.status)}
                                </div>
                                <div class="perform-item-meta">Performed Date: ${escapeHtml(formatDate(row.completed_date || ''))}</div>
                                <div class="perform-item-note">${formatMultiline(row.instructions || '')}</div>
                            </div>
                        `).join('')}
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderLogs() {
        const logs = state.recent_logs || [];

        if (!logs.length) {
            remedyLogsEl.innerHTML = `
                <div class="empty-state">
                    <h3>No Updates Yet</h3>
                    <p>Completed entries will appear here so the team can quickly see what was performed and by whom.</p>
                </div>
            `;
            return;
        }

        remedyLogsEl.innerHTML = logs.map((log) => `
            <div class="activity-item">
                <strong>${escapeHtml(log.remedy_title || 'Remedy')}</strong>
                <span>${escapeHtml(log.schedule_label || 'Perform Entry')} completed on ${escapeHtml(formatDate(log.completed_date || ''))}</span>
                <span>Updated by ${escapeHtml(log.completed_by || 'Admin')}</span>
            </div>
        `).join('');
    }

    function renderManageDrawer() {
        const remedy = activeRemedyId ? findRemedy(activeRemedyId) : null;

        if (!remedy) {
            manageDrawerTitleEl.textContent = 'Manage Remedy';
            manageDrawerBodyEl.innerHTML = `
                <div class="empty-state">
                    <h3>Select A Remedy</h3>
                    <p>Choose Manage on any remedy card to edit its details, perform rows, status, or performed date.</p>
                </div>
            `;
            return;
        }

        manageDrawerTitleEl.textContent = remedy.title || 'Manage Remedy';

        manageDrawerBodyEl.innerHTML = `
            <div class="drawer-section manage-summary">
                <h3>${escapeHtml(remedy.title || 'Remedy')}</h3>
                <p class="section-note">${formatMultiline(remedy.description || '')}</p>
                <div class="manage-summary-meta">
                    <span class="meta-pill">Entries: ${Number(remedy.total_rows || 0)}</span>
                    <span class="meta-pill">Completed: ${Number(remedy.completed_rows || 0)}</span>
                    <span class="meta-pill">Pending: ${Number(remedy.pending_rows || 0)}</span>
                    <span class="meta-pill">${escapeHtml(remedy.next_action_label || 'Next step available')}</span>
                </div>
            </div>

            <div class="drawer-section">
                <h3 class="section-title">Remedy Details</h3>
                <p class="section-note">Keep the remedy name and overall notes clear so the team can understand the plan quickly.</p>
                <form id="manageRemedyForm" data-remedy-id="${Number(remedy.id)}">
                    <div class="form-grid">
                        <label class="field-block">
                            <span class="field-label">Remedy Name</span>
                            <input type="text" name="title" class="field-input" value="${escapeHtml(remedy.title || '')}" required>
                        </label>
                        <div class="field-block">
                            <span class="field-label">Current Status</span>
                            <div style="padding-top:4px;">${renderStatusPill(remedy.status)}</div>
                        </div>
                        <label class="field-block field-block-wide">
                            <span class="field-label">Remedy Notes</span>
                            <textarea name="description" class="field-textarea" placeholder="Add any overall notes for this remedy.">${escapeHtml(remedy.description || '')}</textarea>
                        </label>
                    </div>
                    <div class="summary-actions">
                        <button type="submit" class="primary-btn">Save Remedy Details</button>
                        <button type="button" id="manageAddRowBtn" class="ghost-btn" data-remedy-id="${Number(remedy.id)}">Add Perform Entry</button>
                        <button type="button" id="deleteRemedyBtn" class="danger-btn" data-remedy-id="${Number(remedy.id)}">Delete Remedy</button>
                    </div>
                </form>
            </div>

            <div class="drawer-section">
                <h3 class="section-title">Perform Entries</h3>
                <p class="section-note">Update each row whenever the remedy is completed or still pending. Performed date can be added, edited, or cleared here.</p>
                <div class="manage-rows-list">
                    ${remedy.rows.map((row) => `
                        <div class="manage-row-card" data-row-id="${Number(row.id)}">
                            <div class="manage-row-top">
                                <h4>${escapeHtml(row.label || 'Perform Entry')}</h4>
                                ${renderStatusPill(row.status)}
                            </div>
                            <div class="manage-row-grid">
                                <label class="field-block field-block-wide">
                                    <span class="field-label">Description / Instructions</span>
                                    <textarea class="field-textarea manage-row-notes" placeholder="Add the note for this perform entry.">${escapeHtml(row.instructions || '')}</textarea>
                                </label>
                                <label class="field-block">
                                    <span class="field-label">Status</span>
                                    <select class="field-select manage-row-status-select">
                                        <option value="0"${row.is_completed ? '' : ' selected'}>Not Completed Yet</option>
                                        <option value="1"${row.is_completed ? ' selected' : ''}>Completed</option>
                                    </select>
                                </label>
                                <label class="field-block">
                                    <span class="field-label">Performed Date</span>
                                    <input type="date" class="field-input manage-row-completed-date" value="${escapeHtml(row.completed_date || '')}" ${row.is_completed ? '' : 'disabled'}>
                                </label>
                                <div class="field-block">
                                    <span class="field-label">Entry Label</span>
                                    <div class="helper-note" style="margin-top:0;padding-top:12px;">${escapeHtml(row.label || 'Perform Entry')}</div>
                                </div>
                            </div>
                            <div class="row-card-actions">
                                <button type="button" class="primary-btn manage-save-row-btn">Save Entry</button>
                                <button type="button" class="danger-btn manage-delete-row-btn"${remedy.rows.length <= 1 ? ' disabled' : ''}>Delete Entry</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function refreshUi() {
        if (activeRemedyId && !findRemedy(activeRemedyId)) {
            activeRemedyId = null;
            closeDrawer('manage');
        }

        renderClientSummary();
        renderStats();
        renderRemedyList();
        renderLogs();
        renderManageDrawer();
    }

    async function postAction(action, payload = {}) {
        if (isSubmitting) {
            return null;
        }

        isSubmitting = true;
        showFlash('Saving changes...', 'info');

        const params = new URLSearchParams();
        params.set('action', action);

        Object.keys(payload).forEach((key) => {
            params.set(key, payload[key] == null ? '' : String(payload[key]));
        });

        try {
            const response = await fetch(`remedy.php?client_id=${clientId}&ajax=1`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params.toString()
            });

            const responseText = await response.text();
            let data = null;

            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('The remedy page returned an unexpected response.');
            }

            if (!response.ok || !data.success) {
                throw new Error(data && data.message ? data.message : 'Unable to save the remedy changes.');
            }

            state = data.state || state;
            refreshUi();
            return data;
        } catch (error) {
            showFlash(error.message || 'Unable to save the remedy changes.', 'error');
            return null;
        } finally {
            isSubmitting = false;
        }
    }

    document.addEventListener('click', async (event) => {
        const openAddButton = event.target.closest('.open-add-drawer');
        if (openAddButton) {
            resetAddForm();
            openDrawer('add');
            return;
        }

        if (event.target === drawerBackdropEl) {
            closeAllDrawers();
            return;
        }

        const closeButton = event.target.closest('[data-close-drawer]');
        if (closeButton) {
            closeDrawer(closeButton.getAttribute('data-close-drawer'));
            return;
        }

        const openManageButton = event.target.closest('.open-manage-drawer');
        if (openManageButton) {
            activeRemedyId = Number(openManageButton.getAttribute('data-remedy-id') || 0);
            renderManageDrawer();
            openDrawer('manage');
            return;
        }

        const addRowButton = event.target.closest('#manageAddRowBtn');
        if (addRowButton) {
            const remedyId = Number(addRowButton.getAttribute('data-remedy-id') || 0);
            const data = await postAction('add_row', {
                remedy_id: remedyId
            });
            if (data) {
                activeRemedyId = Number(data.focus_remedy_id || remedyId);
                renderManageDrawer();
                openDrawer('manage');
                showFlash(data.message || 'New perform entry added.', 'success');
            }
            return;
        }

        const saveRowButton = event.target.closest('.manage-save-row-btn');
        if (saveRowButton) {
            const rowCard = saveRowButton.closest('.manage-row-card');
            if (!rowCard) {
                return;
            }

            const rowId = Number(rowCard.getAttribute('data-row-id') || 0);
            const notesField = rowCard.querySelector('.manage-row-notes');
            const statusField = rowCard.querySelector('.manage-row-status-select');
            const dateField = rowCard.querySelector('.manage-row-completed-date');
            const isCompleted = statusField && statusField.value === '1';

            const data = await postAction('save_row', {
                row_id: rowId,
                instructions: notesField ? notesField.value : '',
                is_completed: isCompleted ? '1' : '0',
                completed_date: isCompleted && dateField ? dateField.value : ''
            });

            if (data) {
                activeRemedyId = Number(data.focus_remedy_id || activeRemedyId || 0);
                renderManageDrawer();
                openDrawer('manage');
                showFlash(data.message || 'Perform entry updated.', 'success');
            }
            return;
        }

        const deleteRowButton = event.target.closest('.manage-delete-row-btn');
        if (deleteRowButton) {
            if (deleteRowButton.disabled) {
                return;
            }

            const rowCard = deleteRowButton.closest('.manage-row-card');
            if (!rowCard) {
                return;
            }

            if (!window.confirm('Delete this perform entry?')) {
                return;
            }

            const rowId = Number(rowCard.getAttribute('data-row-id') || 0);
            const data = await postAction('delete_row', {
                row_id: rowId
            });

            if (data) {
                activeRemedyId = Number(data.focus_remedy_id || activeRemedyId || 0);
                renderManageDrawer();
                openDrawer('manage');
                showFlash(data.message || 'Perform entry deleted.', 'success');
            }
            return;
        }

        const deleteRemedyButton = event.target.closest('#deleteRemedyBtn');
        if (deleteRemedyButton) {
            const remedyId = Number(deleteRemedyButton.getAttribute('data-remedy-id') || 0);
            if (!window.confirm('Delete this remedy and all its perform entries?')) {
                return;
            }

            const data = await postAction('delete_remedy', {
                remedy_id: remedyId
            });

            if (data) {
                activeRemedyId = null;
                closeDrawer('manage');
                refreshUi();
                showFlash(data.message || 'Remedy deleted.', 'success');
            }
        }
    });

    document.addEventListener('submit', async (event) => {
        if (event.target === addRemedyFormEl) {
            event.preventDefault();
            syncAddDraftRowsFromDom();

            const titleField = document.getElementById('addRemedyTitle');
            const descriptionField = document.getElementById('addRemedyDescription');
            const repeatQty = clampRepeatQty(addRepeatQtyEl.value);

            const data = await postAction('add_remedy', {
                title: titleField ? titleField.value : '',
                description: descriptionField ? descriptionField.value : '',
                repeat_qty: repeatQty,
                rows_json: JSON.stringify(addDraftRows)
            });

            if (data) {
                resetAddForm();
                closeDrawer('add');
                if (Number(data.focus_remedy_id || 0) > 0) {
                    activeRemedyId = Number(data.focus_remedy_id);
                    renderManageDrawer();
                    openDrawer('manage');
                }
                showFlash(data.message || 'Remedy added successfully.', 'success');
            }
            return;
        }

        const manageForm = event.target.closest('#manageRemedyForm');
        if (manageForm) {
            event.preventDefault();
            const remedyId = Number(manageForm.getAttribute('data-remedy-id') || 0);
            const titleField = manageForm.querySelector('input[name="title"]');
            const descriptionField = manageForm.querySelector('textarea[name="description"]');

            const data = await postAction('update_remedy', {
                remedy_id: remedyId,
                title: titleField ? titleField.value : '',
                description: descriptionField ? descriptionField.value : ''
            });

            if (data) {
                activeRemedyId = Number(data.focus_remedy_id || remedyId);
                renderManageDrawer();
                openDrawer('manage');
                showFlash(data.message || 'Remedy details updated.', 'success');
            }
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target === addRepeatQtyEl) {
            ensureAddDraftRowCount(event.target.value);
            return;
        }

        if (event.target.classList.contains('add-row-note')) {
            const rowIndex = Number(event.target.getAttribute('data-row-index') || 0);
            if (!addDraftRows[rowIndex]) {
                addDraftRows[rowIndex] = { instructions: '' };
            }
            addDraftRows[rowIndex].instructions = event.target.value;
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target.classList.contains('manage-row-status-select')) {
            const rowCard = event.target.closest('.manage-row-card');
            if (!rowCard) {
                return;
            }

            const dateField = rowCard.querySelector('.manage-row-completed-date');
            if (!dateField) {
                return;
            }

            if (event.target.value === '1') {
                dateField.disabled = false;
                if (!dateField.value) {
                    dateField.value = todayIso();
                }
            } else {
                dateField.value = '';
                dateField.disabled = true;
            }
        }
    });

    refreshUi();
    renderAddRows();
})();
</script>
<?php endif; ?>
</body>
</html>
