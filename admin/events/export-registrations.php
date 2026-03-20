<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';
require_once __DIR__ . '/export-report-helper.php';

vs_event_ensure_tables($pdo);

$report = vs_event_export_fetch_dataset($pdo, $_GET);
$rows = $report['rows'];
$dynamicHeaders = $report['dynamic_headers'];
$summary = $report['summary'];
$selectedEvent = $report['selected_event'];
$filterChips = $report['filter_chips'];
$generatedAt = $report['generated_at'];

$standardHeaders = [
    'Registration ID',
    'Booking Reference',
    'Event',
    'Event Date',
    'Package',
    'Name',
    'Phone',
    'Persons',
    'Payment Status',
    'Verification Status',
    'Payment Method',
    'Payment Type',
    'Transaction ID',
    'Payment Record Status',
    'Total Amount',
    'Confirmed Paid',
    'Report Paid',
    'Pending Due',
    'Check-In Status',
    'Check-In Time',
    'Created At',
    'Payment Note',
];

$allHeaders = array_merge($standardHeaders, $dynamicHeaders);
$columnCount = max(count($allHeaders), 1);

$fileSlug = 'event_registrations_report';
if ($selectedEvent !== null) {
    $eventSlug = strtolower(trim((string)($selectedEvent['title'] ?? 'event')));
    $eventSlug = preg_replace('/[^a-z0-9]+/', '_', $eventSlug);
    $eventSlug = trim((string)$eventSlug, '_');
    if ($eventSlug !== '') {
        $fileSlug = $eventSlug . '_event_report';
    }
}
$filename = $fileSlug . '_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

$xml = static function ($value): string {
    $value = (string)$value;
    $value = str_replace("\0", '', $value);
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
};

$stringCell = static function ($value, string $styleId = 'cell') use ($xml): string {
    return '<Cell ss:StyleID="' . $styleId . '"><Data ss:Type="String">' . $xml((string)$value) . '</Data></Cell>';
};

$numberCell = static function ($value, string $styleId = 'number') use ($xml): string {
    if ($value === '' || $value === null) {
        return '<Cell ss:StyleID="' . $styleId . '"><Data ss:Type="String"></Data></Cell>';
    }
    $normalized = number_format((float)$value, 2, '.', '');
    return '<Cell ss:StyleID="' . $styleId . '"><Data ss:Type="Number">' . $xml($normalized) . '</Data></Cell>';
};

$integerCell = static function ($value, string $styleId = 'number-int') use ($xml): string {
    return '<Cell ss:StyleID="' . $styleId . '"><Data ss:Type="Number">' . $xml((string)(int)$value) . '</Data></Cell>';
};

$mergeRow = static function (string $value, string $styleId, int $columnCount) use ($xml): string {
    $mergeAcross = max($columnCount - 1, 0);
    return '<Row>'
        . '<Cell ss:StyleID="' . $styleId . '" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">' . $xml($value) . '</Data></Cell>'
        . '</Row>';
};

$filterSummary = [];
foreach ($filterChips as $chip) {
    $filterSummary[] = trim((string)($chip['label'] ?? '')) . ': ' . trim((string)($chip['value'] ?? ''));
}

$columnWidths = [
    72, 118, 180, 92, 140, 132, 100, 58, 102, 116, 105,
    88, 128, 118, 86, 86, 86, 86, 92, 108, 118, 190,
];
foreach ($dynamicHeaders as $_unusedHeader) {
    $columnWidths[] = 150;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Codex</Author>
  <LastAuthor>Codex</LastAuthor>
  <Created><?php echo $xml(gmdate('Y-m-d\TH:i:s\Z')); ?></Created>
  <Version>16.00</Version>
 </DocumentProperties>
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <WindowHeight>10320</WindowHeight>
  <WindowWidth>20640</WindowWidth>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#2F2725"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="title">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#6F1D1B"/>
   <Interior ss:Color="#FFF1EC" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="subtitle">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#7C4A44"/>
   <Interior ss:Color="#FFF8F5" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="section">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#6F1D1B"/>
   <Interior ss:Color="#FDE7DE" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7C9BD"/>
   </Borders>
  </Style>
  <Style ss:ID="meta">
   <Alignment ss:Vertical="Center" ss:WrapText="1"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#5F4B47"/>
   <Interior ss:Color="#FFFDFC" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="summary-label">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#5F2E2A"/>
   <Interior ss:Color="#FDEDE6" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7CDC0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7CDC0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7CDC0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7CDC0"/>
   </Borders>
  </Style>
  <Style ss:ID="summary-value">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#194D33"/>
   <Interior ss:Color="#F6FFF8" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CDE3D4"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CDE3D4"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CDE3D4"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CDE3D4"/>
   </Borders>
  </Style>
  <Style ss:ID="header">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#7A1F1F" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CFA09C"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CFA09C"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CFA09C"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CFA09C"/>
   </Borders>
  </Style>
  <Style ss:ID="cell">
   <Alignment ss:Vertical="Top" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
   </Borders>
  </Style>
  <Style ss:ID="cell-center">
   <Alignment ss:Horizontal="Center" ss:Vertical="Top" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
   </Borders>
  </Style>
  <Style ss:ID="number">
   <Alignment ss:Horizontal="Right" ss:Vertical="Top"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
   </Borders>
   <NumberFormat ss:Format="0.00"/>
  </Style>
  <Style ss:ID="number-int">
   <Alignment ss:Horizontal="Center" ss:Vertical="Top"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E8D8D2"/>
   </Borders>
   <NumberFormat ss:Format="0"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Event Report">
  <Table x:FullColumns="1" x:FullRows="1" ss:DefaultRowHeight="18">
<?php foreach ($columnWidths as $columnWidth): ?>
   <Column ss:AutoFitWidth="0" ss:Width="<?php echo $xml((string)$columnWidth); ?>"/>
<?php endforeach; ?>
<?php
echo '   ' . $mergeRow('Vishnusudarshana Event Registrations Report', 'title', $columnCount) . "\n";
echo '   ' . $mergeRow(
    $selectedEvent !== null
        ? ((string)($selectedEvent['title'] ?? 'Selected Event') . ' | ' . (string)($selectedEvent['event_date_display'] ?? ($selectedEvent['event_date'] ?? '-')))
        : 'All events report export',
    'subtitle',
    $columnCount
) . "\n";
echo '   ' . $mergeRow('Generated on ' . $generatedAt, 'meta', $columnCount) . "\n";
echo '   ' . $mergeRow(implode(' | ', $filterSummary), 'meta', $columnCount) . "\n";
echo '   ' . $mergeRow('Summary', 'section', $columnCount) . "\n";
?>
   <Row>
    <?php echo $stringCell('Total Registrations', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['total_registrations'], 'summary-value'); ?>
    <?php echo $stringCell('Total Persons', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['total_persons'], 'summary-value'); ?>
    <?php echo $stringCell('Active Persons', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['active_persons'], 'summary-value'); ?>
    <?php echo $stringCell('Checked In', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['checked_in_registrations'], 'summary-value'); ?>
   </Row>
   <Row>
    <?php echo $stringCell('Paid', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['paid_registrations'], 'summary-value'); ?>
    <?php echo $stringCell('Pending Verification', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['pending_verification_registrations'], 'summary-value'); ?>
    <?php echo $stringCell('Waitlisted', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['waitlisted_registrations'], 'summary-value'); ?>
    <?php echo $stringCell('Cancelled', 'summary-label'); ?>
    <?php echo $integerCell((int)$summary['cancelled_registrations'], 'summary-value'); ?>
   </Row>
   <Row>
    <?php echo $stringCell('Expected Amount', 'summary-label'); ?>
    <?php echo $numberCell($summary['expected_amount'], 'summary-value'); ?>
    <?php echo $stringCell('Confirmed Paid', 'summary-label'); ?>
    <?php echo $numberCell($summary['confirmed_paid_amount'], 'summary-value'); ?>
    <?php echo $stringCell('Report Paid', 'summary-label'); ?>
    <?php echo $numberCell($summary['report_paid_amount'], 'summary-value'); ?>
    <?php echo $stringCell('Pending Due', 'summary-label'); ?>
    <?php echo $numberCell($summary['pending_due_amount'], 'summary-value'); ?>
   </Row>
<?php echo '   ' . $mergeRow('Detailed Registrations', 'section', $columnCount) . "\n"; ?>
   <Row>
<?php foreach ($allHeaders as $header): ?>
    <?php echo $stringCell($header, 'header'); ?>
<?php endforeach; ?>
   </Row>
<?php if (empty($rows)): ?>
   <Row>
    <Cell ss:StyleID="cell" ss:MergeAcross="<?php echo $xml((string)max($columnCount - 1, 0)); ?>"><Data ss:Type="String">No registrations found for the selected filters.</Data></Cell>
   </Row>
<?php else: ?>
<?php foreach ($rows as $row): ?>
   <Row>
    <?php echo $integerCell((int)$row['id']); ?>
    <?php echo $stringCell((string)($row['booking_reference'] ?? '')); ?>
    <?php echo $stringCell((string)($row['event_title'] ?? '')); ?>
    <?php echo $stringCell((string)($row['event_date_display'] ?? '')); ?>
    <?php echo $stringCell((string)($row['package_name'] ?? '')); ?>
    <?php echo $stringCell((string)($row['name'] ?? '')); ?>
    <?php echo $stringCell((string)($row['phone_display'] ?? $row['phone'] ?? '')); ?>
    <?php echo $integerCell((int)($row['persons'] ?? 0)); ?>
    <?php echo $stringCell((string)($row['payment_status'] ?? '')); ?>
    <?php echo $stringCell((string)($row['verification_status'] ?? '')); ?>
    <?php echo $stringCell((string)($row['payment_method'] ?? '')); ?>
    <?php echo $stringCell((string)($row['payment_type_text'] ?? '-')); ?>
    <?php echo $stringCell((string)($row['transaction_id'] ?? '')); ?>
    <?php echo $stringCell((string)($row['payment_record_status'] ?? '')); ?>
    <?php echo $numberCell((float)($row['total_amount'] ?? 0)); ?>
    <?php echo $numberCell((float)($row['confirmed_paid_amount'] ?? 0)); ?>
    <?php echo $numberCell((float)($row['report_paid_amount'] ?? 0)); ?>
    <?php echo $numberCell((float)($row['pending_due_amount'] ?? 0)); ?>
    <?php echo $stringCell((string)($row['checkin_status_text'] ?? '')); ?>
    <?php echo $stringCell((string)($row['checkin_time'] ?? '')); ?>
    <?php echo $stringCell((string)($row['created_at'] ?? '')); ?>
    <?php echo $stringCell((string)($row['payment_note'] ?? '')); ?>
<?php foreach ($dynamicHeaders as $dynamicHeader): ?>
    <?php echo $stringCell((string)($row['dynamic_fields'][$dynamicHeader] ?? '')); ?>
<?php endforeach; ?>
   </Row>
<?php endforeach; ?>
<?php endif; ?>
  </Table>
 </Worksheet>
</Workbook>
