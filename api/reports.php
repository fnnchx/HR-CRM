<?php

require_once 'config.php';

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'json';

if (empty($type)) {
    echo json_encode([
        'success' => false,
        'error' => 'Не указан тип отчета',
        'available_types' => [
            'attendance' => 'Отчет о посещаемости',
            'monthly_attendance' => 'Месячная статистика посещаемости',
            'staff_count_by_period' => 'Количество персонала за период',
            'expiring' => 'Истекающие договоры',
            'by_agency' => 'Статистика по агентствам',
            'monthly' => 'Статистика по месяцам',
            'contracts' => 'Все договоры'
        ]
    ]);
    exit;
}

try {
    switch($type) {
        case 'attendance':
            $organization_id = $_GET['organization_id'] ?? 0;
            $agency_id = $_GET['agency_id'] ?? 0;
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            
            $sql = "
                SELECT 
                    ws.id,
                    ws.schedule_date,
                    ws.planned_start,
                    ws.planned_end,
                    ws.actual_start,
                    ws.actual_end,
                    ws.status,
                    ws.confirmed_at,
                    es.id as staff_id,
                    es.full_name,
                    es.position,
                    es.staff_number,
                    a.id as agency_id,
                    a.name as agency_name,
                    o.id as organization_id,
                    o.name as organization_name
                FROM work_schedules ws
                JOIN external_staff es ON ws.external_staff_id = es.id
                LEFT JOIN agencies a ON es.agency_id = a.id
                LEFT JOIN organizations o ON es.organization_id = o.id
                WHERE ws.schedule_date BETWEEN :date_from AND :date_to
            ";
            
            $params = [
                ':date_from' => $date_from,
                ':date_to' => $date_to
            ];
            
            if ($organization_id > 0) {
                $sql .= " AND es.organization_id = :org_id";
                $params[':org_id'] = $organization_id;
            }
            
            if ($agency_id > 0) {
                $sql .= " AND es.agency_id = :agency_id";
                $params[':agency_id'] = $agency_id;
            }
            
            $sql .= " ORDER BY ws.schedule_date DESC, es.full_name";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                exportAttendanceCSV($result);
            } else {
                echo json_encode($result);
            }
            break;
        case 'monthly_attendance':
            $organization_id = $_GET['organization_id'] ?? 0;
            $year = $_GET['year'] ?? date('Y');
            
            $sql = "
                SELECT 
                    DATE_FORMAT(ws.schedule_date, '%Y-%m') as month,
                    MONTH(ws.schedule_date) as month_num,
                    COUNT(ws.id) as total_days,
                    SUM(CASE WHEN ws.status = 'confirmed' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN ws.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    ROUND(
                        SUM(CASE WHEN ws.status = 'confirmed' THEN 1 ELSE 0 END) / COUNT(ws.id) * 100, 2
                    ) as attendance_percent
                FROM work_schedules ws
                JOIN external_staff es ON ws.external_staff_id = es.id
                WHERE YEAR(ws.schedule_date) = :year
            ";
            
            $params = [':year' => $year];
            
            if ($organization_id > 0) {
                $sql .= " AND es.organization_id = :org_id";
                $params[':org_id'] = $organization_id;
            }
            
            $sql .= " GROUP BY month, month_num ORDER BY month_num";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 
                       'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
            
            foreach ($result as &$row) {
                $row['month_name'] = $months[($row['month_num'] ?? 1) - 1];
            }
            
            echo json_encode($result);
            break;
        case 'staff_count_by_period':
            $organization_id = $_GET['organization_id'] ?? 0;
            $agency_id = $_GET['agency_id'] ?? 0;
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            $group_by = $_GET['group_by'] ?? 'day';
            
            $sql = "
                SELECT 
                    DATE(ws.schedule_date) as date,
                    COUNT(DISTINCT ws.external_staff_id) as unique_staff_count,
                    COUNT(ws.id) as total_shifts,
                    SUM(CASE WHEN ws.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_shifts
                FROM work_schedules ws
                JOIN external_staff es ON ws.external_staff_id = es.id
                WHERE ws.schedule_date BETWEEN :date_from AND :date_to
            ";
            
            $params = [
                ':date_from' => $date_from,
                ':date_to' => $date_to
            ];
            
            if ($organization_id > 0) {
                $sql .= " AND es.organization_id = :org_id";
                $params[':org_id'] = $organization_id;
            }
            
            if ($agency_id > 0) {
                $sql .= " AND es.agency_id = :agency_id";
                $params[':agency_id'] = $agency_id;
            }
            
            if ($group_by === 'week') {
                $sql .= " GROUP BY YEARWEEK(ws.schedule_date) ORDER BY date";
            } elseif ($group_by === 'month') {
                $sql .= " GROUP BY YEAR(ws.schedule_date), MONTH(ws.schedule_date) ORDER BY date";
            } else {
                $sql .= " GROUP BY date ORDER BY date";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'expiring':
            $days = $_GET['days'] ?? 30;
            $sql = "
                SELECT 
                    c.id,
                    c.number,
                    c.expiration_date,
                    c.total_amount,
                    c.status,
                    a.name as agency_name,
                    DATEDIFF(c.expiration_date, CURDATE()) as days_left
                FROM contracts c
                JOIN agencies a ON c.agency_id = a.id
                WHERE c.status = 'active'
                  AND c.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY)
                ORDER BY c.expiration_date
            ";
            $stmt = $conn->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'by_agency':
            $sql = "
                SELECT 
                    a.id,
                    a.name,
                    a.email,
                    a.phone,
                    COUNT(c.id) as total_contracts,
                    SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active_contracts,
                    COALESCE(SUM(c.total_amount), 0) as total_amount
                FROM agencies a
                LEFT JOIN contracts c ON a.id = c.agency_id
                GROUP BY a.id, a.name, a.email, a.phone
                ORDER BY a.name
            ";
            $stmt = $conn->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'monthly':
            $year = $_GET['year'] ?? date('Y');
            $sql = "
                SELECT 
                    MONTH(signing_date) as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    COALESCE(SUM(total_amount), 0) as amount
                FROM contracts
                WHERE YEAR(signing_date) = :year
                GROUP BY MONTH(signing_date)
                ORDER BY month
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':year' => $year]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'contracts':
            $sql = "
                SELECT 
                    c.id,
                    c.number,
                    c.signing_date,
                    c.expiration_date,
                    c.status,
                    c.total_amount,
                    a.name as agency_name
                FROM contracts c
                JOIN agencies a ON c.agency_id = a.id
                ORDER BY c.created_at DESC
            ";
            $stmt = $conn->query($sql);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        default:
            echo json_encode([
                'success' => false,
                'error' => "Неизвестный тип отчета: '$type'",
                'available_types' => [
                    'attendance' => 'Отчет о посещаемости',
                    'monthly_attendance' => 'Месячная статистика посещаемости',
                    'staff_count_by_period' => 'Количество персонала за период',
                    'expiring' => 'Истекающие договоры',
                    'by_agency' => 'Статистика по агентствам',
                    'monthly' => 'Статистика по месяцам',
                    'contracts' => 'Все договоры'
                ]
            ]);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}

function exportAttendanceCSV($data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Дата', 'Сотрудник', 'Должность', 'Агентство', 'План прихода', 'План ухода', 'Факт прихода', 'Факт ухода', 'Статус'], ';');
    
    $statusText = [
        'confirmed' => 'Присутствовал',
        'absent' => 'Отсутствовал',
        'pending' => 'Не отмечен',
        'partial' => 'Частично'
    ];
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['schedule_date'] ?? '',
            $row['full_name'] ?? '',
            $row['position'] ?? '',
            $row['agency_name'] ?? '',
            $row['planned_start'] ?? '',
            $row['planned_end'] ?? '',
            $row['actual_start'] ?? '',
            $row['actual_end'] ?? '',
            $statusText[$row['status']] ?? $row['status'] ?? ''
        ], ';');
    }
    
    fclose($output);
    exit;
}
?>