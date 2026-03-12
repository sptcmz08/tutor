<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();
$type = $_GET['type'] ?? 'excel';
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

$records = getMonthlyRecords($month, $year);

// Group by tutor
$groupedByTutor = [];
foreach ($records as $r) {
    $key = $r['tutor_id'];
    if (!isset($groupedByTutor[$key])) {
        $groupedByTutor[$key] = [
            'name' => $r['first_name'] . ' ' . $r['last_name'],
            'nickname' => $r['nickname'],
            'records' => [],
            'total_fee' => 0
        ];
    }
    $groupedByTutor[$key]['records'][] = $r;
    $groupedByTutor[$key]['total_fee'] += ($r['teaching_fee'] ?? 0);
}

$monthName = THAI_MONTHS[$month];
$yearThai = $year + 543;
$filename = "teaching_report_{$monthName}_{$yearThai}";

if ($type === 'excel') {
    exportExcel($groupedByTutor, $monthName, $yearThai, $filename);
} else {
    exportPdf($groupedByTutor, $monthName, $yearThai, $filename);
}

function exportExcel($groupedByTutor, $monthName, $yearThai, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>รายงาน</x:Name>';
    echo '<x:WorksheetOptions><x:DisplayGridlines/><x:Pane><x:Number>3</x:Number><x:ActiveRow>0</x:ActiveRow><x:ActiveCol>0</x:ActiveCol></x:Pane></x:WorksheetOptions>';
    echo '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    // Thai font stack — TH SarabunPSK and Cordia New are standard Thai fonts in Windows
    echo 'body, table, th, td { font-family: "TH SarabunPSK", "Cordia New", "Tahoma", "Noto Sans Thai", sans-serif; mso-font-charset: 222; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #4338CA; color: #FFFFFF; font-weight: bold; font-size: 16px; padding: 10px 12px; border: 1px solid #3730A3; text-align: center; mso-font-charset: 222; }';
    echo 'td { padding: 8px 10px; border: 1px solid #CBD5E1; font-size: 15px; vertical-align: top; mso-font-charset: 222; }';
    echo '.title { font-size: 22px; font-weight: bold; color: #1E1B4B; text-align: center; padding: 15px; mso-font-charset: 222; }';
    echo '.subtitle { font-size: 14px; color: #64748B; text-align: center; padding-bottom: 15px; mso-font-charset: 222; }';
    echo '.tutor-header { background-color: #E0E7FF; font-weight: bold; font-size: 16px; color: #3730A3; padding: 10px 12px; border: 1px solid #A5B4FC; }';
    echo '.row-even { background-color: #F8FAFC; }';
    echo '.row-odd { background-color: #FFFFFF; }';
    echo '.total-row { background-color: #ECFDF5; font-weight: bold; color: #065F46; border-top: 2px solid #6EE7B7; }';
    echo '.grand-total { background-color: #FEF3C7; font-weight: bold; font-size: 18px; color: #92400E; border: 2px solid #F59E0B; }';
    echo '.fee { text-align: right; mso-number-format: "\#\,\#\#0"; }';
    echo '.col-no { text-align: center; width: 35px; color: #94A3B8; }';
    echo '.col-school { width: 120px; }';
    echo '.col-date { width: 160px; white-space: nowrap; }';
    echo '.col-time { width: 100px; white-space: nowrap; text-align: center; }';
    echo '.col-summary { width: 200px; }';
    echo '.col-notes { width: 150px; color: #64748B; }';
    echo '.col-fee { width: 90px; }';
    echo '</style></head><body>';

    echo '<table>';
    echo '<tr><td colspan="8" class="title">📊 รายงานสรุปการสอน</td></tr>';
    echo '<tr><td colspan="8" class="subtitle">' . $monthName . ' พ.ศ. ' . $yearThai . ' — พิมพ์เมื่อ ' . date('d/m/') . ($year = date('Y') + 543) . ' ' . date('H:i') . ' น.</td></tr>';
    echo '<tr><td colspan="8" style="padding:5px;"></td></tr>';

    echo '<tr>';
    echo '<th class="col-no">#</th>';
    echo '<th class="col-school">โรงเรียน</th>';
    echo '<th class="col-date">วันที่</th>';
    echo '<th class="col-time">เวลาเริ่ม</th>';
    echo '<th class="col-time">เวลาสิ้นสุด</th>';
    echo '<th class="col-summary">สรุปการสอน</th>';
    echo '<th class="col-notes">หมายเหตุ</th>';
    echo '<th class="col-fee">ค่าสอน (฿)</th>';
    echo '</tr>';

    $grandTotal = 0;
    foreach ($groupedByTutor as $data) {
        $displayName = $data['name'];
        if ($data['nickname']) $displayName .= ' (' . $data['nickname'] . ')';

        echo '<tr><td colspan="8" class="tutor-header">👤 ' . htmlspecialchars($displayName) . ' — สอนทั้งหมด ' . count($data['records']) . ' ครั้ง</td></tr>';

        foreach ($data['records'] as $i => $r) {
            $rowClass = ($i % 2 === 0) ? 'row-even' : 'row-odd';
            echo '<tr class="' . $rowClass . '">';
            echo '<td class="col-no">' . ($i + 1) . '</td>';
            echo '<td class="col-school">' . htmlspecialchars($r['school_name']) . '</td>';
            echo '<td class="col-date">' . formatThaiDate($r['teaching_date']) . '</td>';
            echo '<td class="col-time">' . formatTime($r['start_time']) . '</td>';
            echo '<td class="col-time">' . formatTime($r['end_time']) . '</td>';
            echo '<td class="col-summary">' . htmlspecialchars($r['teaching_summary'] ?? '-') . '</td>';
            echo '<td class="col-notes">' . htmlspecialchars($r['notes'] ?? '-') . '</td>';
            echo '<td class="fee">' . ($r['teaching_fee'] !== null ? number_format($r['teaching_fee'], 0) : '-') . '</td>';
            echo '</tr>';
        }

        echo '<tr class="total-row"><td colspan="7" style="text-align:right;padding-right:15px;">รวมค่าสอน ' . htmlspecialchars($data['name']) . '</td>';
        echo '<td class="fee" style="font-size:16px;">฿' . number_format($data['total_fee'], 0) . '</td></tr>';
        echo '<tr><td colspan="8" style="padding:3px;border:none;"></td></tr>';
        $grandTotal += $data['total_fee'];
    }

    echo '<tr class="grand-total"><td colspan="7" style="text-align:right;padding-right:15px;">💰 รวมค่าสอนทั้งหมด</td>';
    echo '<td class="fee" style="font-size:18px;">฿' . number_format($grandTotal, 0) . '</td></tr>';

    echo '</table>';
    echo '</body></html>';
}

function exportPdf($groupedByTutor, $monthName, $yearThai, $filename) {
    // Generate HTML-based PDF via browser print
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <title>รายงานสรุปการสอน <?= $monthName ?> <?= $yearThai ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>
            * { font-family: 'Noto Sans Thai', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
            body { padding: 30px; background: white; color: #333; font-size: 12px; }
            h1 { text-align: center; font-size: 20px; margin-bottom: 5px; color: #4F46E5; }
            .subtitle { text-align: center; color: #666; margin-bottom: 25px; font-size: 13px; }
            .tutor-section { margin-bottom: 25px; page-break-inside: avoid; }
            .tutor-header { background: #E0E7FF; padding: 10px 15px; border-radius: 8px 8px 0 0; font-weight: 700; font-size: 14px; color: #3730A3; display: flex; justify-content: space-between; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
            th { background: #F8FAFC; padding: 8px 10px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #E2E8F0; font-size: 11px; }
            td { padding: 7px 10px; border-bottom: 1px solid #F1F5F9; font-size: 11px; }
            tr:hover { background: #F8FAFC; }
            .total-row td { font-weight: 700; background: #F0FDF4; border-top: 2px solid #86EFAC; color: #166534; }
            .fee { text-align: right; font-weight: 600; }
            .grand-total { background: #FEF3C7; padding: 12px 15px; border-radius: 8px; margin-top: 20px; display: flex; justify-content: space-between; font-weight: 700; font-size: 16px; color: #92400E; }
            .print-hint { text-align: center; color: #999; margin-top: 30px; font-size: 11px; }
            @media print {
                body { padding: 15px; }
                .print-hint { display: none; }
                .tutor-section { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <h1>📊 รายงานสรุปการสอน</h1>
        <p class="subtitle"><?= $monthName ?> <?= $yearThai ?></p>

        <?php
        $grandTotal = 0;
        foreach ($groupedByTutor as $data):
            $displayName = $data['name'];
            if ($data['nickname']) $displayName .= ' (' . $data['nickname'] . ')';
            $grandTotal += $data['total_fee'];
        ?>
        <div class="tutor-section">
            <div class="tutor-header">
                <span>👤 <?= htmlspecialchars($displayName) ?> — <?= count($data['records']) ?> ครั้ง</span>
                <span>ค่าสอน: ฿<?= number_format($data['total_fee'], 0) ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:30px">#</th>
                        <th>โรงเรียน</th>
                        <th>วันที่</th>
                        <th>เวลา</th>
                        <th>สรุปการสอน</th>
                        <th>หมายเหตุ</th>
                        <th style="width:80px;text-align:right">ค่าสอน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['records'] as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($r['school_name']) ?></td>
                        <td style="white-space:nowrap"><?= formatThaiDate($r['teaching_date']) ?></td>
                        <td style="white-space:nowrap"><?= formatTimeRange($r['start_time'], $r['end_time']) ?></td>
                        <td><?= htmlspecialchars($r['teaching_summary'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
                        <td class="fee"><?= $r['teaching_fee'] !== null ? number_format($r['teaching_fee'], 0) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="6" style="text-align:right">รวมค่าสอน <?= htmlspecialchars($data['name']) ?></td>
                        <td class="fee">฿<?= number_format($data['total_fee'], 0) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <div class="grand-total">
            <span>💰 รวมค่าสอนทั้งหมด</span>
            <span>฿<?= number_format($grandTotal, 0) ?></span>
        </div>

        <p class="print-hint">💡 กด <strong>Ctrl+P</strong> (หรือ ⌘+P) เพื่อบันทึกเป็น PDF หรือพิมพ์</p>

        <script>window.onload = function() { window.print(); };</script>
    </body>
    </html>
    <?php
}
