<?php

require_once 'config.php';

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

try {
    switch($type) {
        case 'expiring':
            $days = $_GET['days'] ?? 30;
            $sql = "
                SELECT 
                    c.number as 'Номер договора',
                    a.name as 'Агентство',
                    c.expiration_date as 'Дата истечения',
                    DATEDIFF(c.expiration_date, CURDATE()) as 'Осталось дней',
                    c.total_amount as 'Сумма'
                FROM contracts c
                JOIN agencies a ON c.agency_id = a.id
                WHERE c.status = 'active'
                  AND c.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY)
                ORDER BY c.expiration_date
            ";
            $title = "Истекающие договоры (${days} дней)";
            $filename = "expiring_contracts_" . date('Y-m-d');
            break;
            
        case 'agencies':
            $sql = "
                SELECT 
                    a.name as 'Агентство',
                    a.email as 'Email',
                    a.phone as 'Телефон',
                    COUNT(c.id) as 'Всего договоров',
                    SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as 'Активных',
                    COALESCE(SUM(c.total_amount), 0) as 'Общая сумма'
                FROM agencies a
                LEFT JOIN contracts c ON a.id = c.agency_id
                GROUP BY a.id, a.name, a.email, a.phone
                ORDER BY a.name
            ";
            $title = "Статистика по агентствам";
            $filename = "agencies_stats_" . date('Y-m-d');
            break;
            
        case 'monthly':
            $year = 2026;
            $months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 
                       'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
            $sql = "
                SELECT 
                    MONTH(signing_date) as month_num,
                    COUNT(*) as 'Всего договоров',
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as 'Активных',
                    COALESCE(SUM(total_amount), 0) as 'Сумма'
                FROM contracts
                WHERE YEAR(signing_date) = $year
                GROUP BY MONTH(signing_date)
                ORDER BY MONTH(signing_date)
            ";
            $title = "Статистика по месяцам {$year} года";
            $filename = "monthly_stats_{$year}";
            break;
            
        case 'contracts':
            $sql = "
                SELECT 
                    c.number as 'Номер',
                    a.name as 'Агентство',
                    c.signing_date as 'Дата подписания',
                    c.expiration_date as 'Срок действия',
                    CASE 
                        WHEN c.status = 'active' THEN 'Активен'
                        WHEN c.status = 'draft' THEN 'Черновик'
                        WHEN c.status = 'expired' THEN 'Истек'
                        WHEN c.status = 'terminated' THEN 'Расторгнут'
                        ELSE c.status
                    END as 'Статус',
                    c.total_amount as 'Сумма'
                FROM contracts c
                JOIN agencies a ON c.agency_id = a.id
                ORDER BY c.created_at DESC
            ";
            $title = "Все договоры";
            $filename = "all_contracts_" . date('Y-m-d');
            break;
            
        default:
            die('Не указан тип отчета');
    }
    
    $stmt = $conn->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Для monthly отчета добавляем названия месяцев
    if ($type === 'monthly') {
        $newData = [];
        $months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 
                   'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
        foreach ($data as $row) {
            $row['Месяц'] = $months[($row['month_num'] ?? 1) - 1];
            unset($row['month_num']);
            $newData[] = $row;
        }
        $data = $newData;
    }
    
    if ($format === 'csv') {
        // CSV с автоподбором ширины в Excel (с использованием HTML тегов)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo generateExcelHTML($data, $title, $filename);
        
    } elseif ($format === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo $title; ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    margin: 2cm;
                    font-size: 12px;
                    background: white;
                }
                h1 {
                    color: #2e7d32;
                    text-align: center;
                    margin-bottom: 10px;
                    font-size: 20px;
                }
                .report-date {
                    text-align: center;
                    color: #666;
                    margin-bottom: 30px;
                    font-size: 11px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    font-size: 11px;
                }
                th {
                    background: #2e7d32;
                    color: white;
                    padding: 10px 8px;
                    text-align: left;
                    font-weight: bold;
                    border: 1px solid #1b5e20;
                }
                td {
                    padding: 8px;
                    border: 1px solid #c8e6c9;
                    vertical-align: top;
                }
                tr:nth-child(even) {
                    background: #f9f9f9;
                }
                .total-row {
                    background: #e8f5e9;
                    font-weight: bold;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 10px;
                    color: #999;
                    border-top: 1px solid #ddd;
                    padding-top: 15px;
                }
                @media print {
                    body { margin: 1cm; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <button onclick="window.print()" style="position: fixed; top: 10px; right: 10px; padding: 8px 16px; background: #2e7d32; color: white; border: none; border-radius: 5px; cursor: pointer;">🖨️ Печать</button>
            <button onclick="window.close()" style="position: fixed; top: 10px; right: 100px; padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">✖️ Закрыть</button>
            
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <div class="report-date">Дата формирования: <?php echo date('d.m.Y H:i:s'); ?></div>
            
            <table>
                <thead>
                    <tr>
                        <?php if (!empty($data)): ?>
                            <?php foreach (array_keys($data[0]) as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_sum = 0;
                    foreach ($data as $row): 
                        if (isset($row['Сумма'])) {
                            $total_sum += floatval($row['Сумма']);
                        }
                    ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($total_sum > 0): ?>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="<?php echo count(array_keys($data[0])) - 1; ?>" style="text-align: right;"><strong>ИТОГО:</strong></td>
                        <td><strong><?php echo number_format($total_sum, 2, ',', ' ') . ' Br'; ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            
            <div class="footer">
                Система управления кадровыми агентствами | HR CRM<br>
                Отчет сформирован автоматически <?php echo date('d.m.Y'); ?>
            </div>
        </body>
        </html>
        <?php
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}

// Функция генерации HTML для Excel с автоподбором ширины
function generateExcelHTML($data, $title, $filename) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            /* Стили для правильного отображения в Excel */
            .excel-table {
                border-collapse: collapse;
                width: 100%;
                font-family: "Segoe UI", Arial, sans-serif;
                font-size: 11pt;
            }
            .excel-table th {
                background-color: #2e7d32;
                color: white;
                padding: 8px;
                border: 1px solid #1b5e20;
                text-align: center;
                font-weight: bold;
            }
            .excel-table td {
                padding: 6px 8px;
                border: 1px solid #c8e6c9;
                vertical-align: top;
            }
            .excel-table tr:nth-child(even) {
                background-color: #f5f5f5;
            }
            .excel-table .total-row {
                background-color: #e8f5e9;
                font-weight: bold;
            }
            .header-info {
                margin-bottom: 20px;
            }
            .title {
                font-size: 18pt;
                font-weight: bold;
                color: #2e7d32;
                margin-bottom: 5px;
            }
            .date {
                font-size: 10pt;
                color: #666;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="header-info">
            <div class="title">' . htmlspecialchars($title) . '</div>
            <div class="date">Дата формирования: ' . date('d.m.Y H:i:s') . '</div>
        </div>
        
        <table class="excel-table" cellpadding="5" cellspacing="0">
            <thead>
                <tr>';
    
    if (!empty($data)) {
        foreach (array_keys($data[0]) as $header) {
            $width = getColumnWidth($header, $data);
            $html .= '<th style="width: ' . $width . 'px;">' . htmlspecialchars($header) . '</th>';
        }
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
    
    $total_sum = 0;
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $key => $cell) {
            if ($key === 'Сумма' || $key === 'Общая сумма') {
                $total_sum += floatval($cell);
                $cell = number_format(floatval($cell), 2, ',', ' ') . ' Br';
            }
            $html .= '<td>' . htmlspecialchars($cell ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    
    if ($total_sum > 0) {
        $colspan = count(array_keys($data[0])) - 1;
        $html .= '<tr class="total-row">
            <td colspan="' . $colspan . '" style="text-align: right;"><strong>ИТОГО:</strong></td>
            <td><strong>' . number_format($total_sum, 2, ',', ' ') . ' Br</strong></td>
        </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: center; font-size: 9pt; color: #999; border-top: 1px solid #ddd; padding-top: 15px;">
            Система управления кадровыми агентствами | HR CRM<br>
            Отчет сформирован автоматически ' . date('d.m.Y') . '
        </div>
    </body>
    </html>';
    
    return $html;
}

function getColumnWidth($header, $data) {
    $maxLength = strlen($header);
    
    foreach ($data as $row) {
        if (isset($row[$header])) {
            $length = strlen((string)$row[$header]);
            if ($length > $maxLength) {
                $maxLength = min($length, 50); 
            }
        }
    }
    
    $width = $maxLength * 8 + 20;
    return min(max($width, 80), 300); 
}
?>