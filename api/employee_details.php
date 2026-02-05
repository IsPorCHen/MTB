<?php
/**
 * API для получения детальной информации о сотруднике
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$employee_id = $_GET['id'] ?? null;

if (!$employee_id) {
    echo json_encode(['success' => false, 'error' => 'ID сотрудника не указан']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
    exit;
}

// Получаем информацию о сотруднике
$employee_query = "SELECT 
    emp.id,
    emp.full_name,
    emp.position,
    emp.department,
    emp.phone,
    emp.email,
    emp.hire_date,
    emp.is_active,
    emp.created_at,
    emp.updated_at
FROM employees emp
WHERE emp.id = ?";

$stmt = sqlsrv_query($conn, $employee_query, array($employee_id));

if ($stmt === false) {
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
    exit;
}

$employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$employee) {
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'error' => 'Сотрудник не найден']);
    exit;
}

// Конвертация DateTime
if (isset($employee['hire_date']) && $employee['hire_date'] instanceof DateTime) {
    $employee['hire_date'] = $employee['hire_date']->format('Y-m-d');
}
if (isset($employee['created_at']) && $employee['created_at'] instanceof DateTime) {
    $employee['created_at'] = $employee['created_at']->format('Y-m-d H:i:s');
}
if (isset($employee['updated_at']) && $employee['updated_at'] instanceof DateTime) {
    $employee['updated_at'] = $employee['updated_at']->format('Y-m-d H:i:s');
}

// Получаем список оборудования за которое отвечает сотрудник
$equipment_query = "SELECT 
    e.id,
    e.inventory_number,
    e.name,
    c.name AS category,
    cs.name AS `condition`,
    e.current_value AS price,
    e.manufacturer,
    e.model,
    CONCAT(p.building, ', ', p.room_number) AS location,
    e.purchase_date,
    e.warranty_until,
    e.is_active
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
LEFT JOIN condition_status cs ON e.condition_id = cs.id
LEFT JOIN premises p ON e.premise_id = p.id
WHERE e.responsible_id = ? AND e.is_active = 1
ORDER BY c.name, e.name";

$eq_stmt = sqlsrv_query($conn, $equipment_query, array($employee_id));

$equipment = array();
if ($eq_stmt !== false) {
    while ($row = sqlsrv_fetch_array($eq_stmt, SQLSRV_FETCH_ASSOC)) {
        // Конвертация DateTime
        if (isset($row['purchase_date']) && $row['purchase_date'] instanceof DateTime) {
            $row['purchase_date'] = $row['purchase_date']->format('Y-m-d');
        }
        if (isset($row['warranty_until']) && $row['warranty_until'] instanceof DateTime) {
            $row['warranty_until'] = $row['warranty_until']->format('Y-m-d');
        }
        $equipment[] = $row;
    }
}

// Получаем список помещений за которые отвечает сотрудник
$premises_query = "SELECT 
    p.id,
    p.room_number,
    p.building,
    p.floor,
    p.room_type,
    p.area,
    p.capacity,
    p.status,
    (SELECT COUNT(*) FROM equipment e WHERE e.premise_id = p.id AND e.is_active = 1) AS equipment_count
FROM premises p
WHERE p.responsible_id = ?
ORDER BY p.building, p.room_number";

$pr_stmt = sqlsrv_query($conn, $premises_query, array($employee_id));

$premises = array();
if ($pr_stmt !== false) {
    while ($row = sqlsrv_fetch_array($pr_stmt, SQLSRV_FETCH_ASSOC)) {
        $premises[] = $row;
    }
}

// Статистика по категориям оборудования
$stats_query = "SELECT 
    c.name AS category,
    COUNT(e.id) AS count,
    COALESCE(SUM(e.current_value), 0) AS total_value
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
WHERE e.responsible_id = ? AND e.is_active = 1
GROUP BY c.name
ORDER BY count DESC";

$stats_stmt = sqlsrv_query($conn, $stats_query, array($employee_id));

$category_stats = array();
if ($stats_stmt !== false) {
    while ($row = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC)) {
        $category_stats[] = $row;
    }
}

// Статистика по состоянию оборудования
$condition_query = "SELECT 
    cs.name AS `condition`,
    COUNT(e.id) AS count
FROM equipment e
LEFT JOIN condition_status cs ON e.condition_id = cs.id
WHERE e.responsible_id = ? AND e.is_active = 1
GROUP BY cs.name
ORDER BY count DESC";

$cond_stmt = sqlsrv_query($conn, $condition_query, array($employee_id));

$condition_stats = array();
if ($cond_stmt !== false) {
    while ($row = sqlsrv_fetch_array($cond_stmt, SQLSRV_FETCH_ASSOC)) {
        $condition_stats[] = $row;
    }
}

// Общая статистика
$total_query = "SELECT 
    COUNT(id) AS equipment_count,
    COALESCE(SUM(current_value), 0) AS total_value
FROM equipment 
WHERE responsible_id = ? AND is_active = 1";

$total_stmt = sqlsrv_query($conn, $total_query, array($employee_id));
$totals = sqlsrv_fetch_array($total_stmt, SQLSRV_FETCH_ASSOC);

// Последние действия с оборудованием сотрудника
$history_query = "SELECT 
    h.id,
    h.change_type,
    h.change_date,
    h.reason,
    e.inventory_number,
    e.name AS equipment_name
FROM equipment_history h
JOIN equipment e ON h.equipment_id = e.id
WHERE e.responsible_id = ?
ORDER BY h.change_date DESC
LIMIT 10";

$hist_stmt = sqlsrv_query($conn, $history_query, array($employee_id));

$recent_history = array();
if ($hist_stmt !== false) {
    while ($row = sqlsrv_fetch_array($hist_stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['change_date']) && $row['change_date'] instanceof DateTime) {
            $row['change_date'] = $row['change_date']->format('Y-m-d H:i:s');
        }
        $recent_history[] = $row;
    }
}

closeDBConnection($conn);

echo json_encode([
    'success' => true,
    'data' => [
        'employee' => $employee,
        'equipment' => $equipment,
        'premises' => $premises,
        'recent_history' => $recent_history,
        'statistics' => [
            'equipment_count' => intval($totals['equipment_count'] ?? 0),
            'total_value' => floatval($totals['total_value'] ?? 0),
            'premises_count' => count($premises),
            'by_category' => $category_stats,
            'by_condition' => $condition_stats
        ]
    ]
]);
?>
