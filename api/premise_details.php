<?php
/**
 * API для получения детальной информации о помещении
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$premise_id = $_GET['id'] ?? null;

if (!$premise_id) {
    echo json_encode(['success' => false, 'error' => 'ID помещения не указан']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
    exit;
}

// Получаем информацию о помещении
$premise_query = "SELECT 
    p.id,
    p.room_number,
    p.building,
    p.floor,
    p.room_type,
    p.area,
    p.capacity,
    p.status,
    p.description,
    p.responsible_id,
    p.created_at,
    p.updated_at,
    emp.full_name AS responsible_name,
    emp.position AS responsible_position,
    emp.phone AS responsible_phone,
    emp.email AS responsible_email
FROM premises p
LEFT JOIN employees emp ON p.responsible_id = emp.id
WHERE p.id = ?";

$stmt = sqlsrv_query($conn, $premise_query, array($premise_id));

if ($stmt === false) {
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
    exit;
}

$premise = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$premise) {
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'error' => 'Помещение не найдено']);
    exit;
}

// Конвертация DateTime
if (isset($premise['created_at']) && $premise['created_at'] instanceof DateTime) {
    $premise['created_at'] = $premise['created_at']->format('Y-m-d H:i:s');
}
if (isset($premise['updated_at']) && $premise['updated_at'] instanceof DateTime) {
    $premise['updated_at'] = $premise['updated_at']->format('Y-m-d H:i:s');
}

// Получаем список оборудования в помещении
$equipment_query = "SELECT 
    e.id,
    e.inventory_number,
    e.name,
    c.name AS category,
    cs.name AS `condition`,
    e.current_value AS price,
    e.manufacturer,
    e.model,
    e.serial_number,
    e.purchase_date,
    e.warranty_until,
    emp.full_name AS responsible_name,
    e.is_active
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
LEFT JOIN condition_status cs ON e.condition_id = cs.id
LEFT JOIN employees emp ON e.responsible_id = emp.id
WHERE e.premise_id = ? AND e.is_active = 1
ORDER BY c.name, e.name";

$eq_stmt = sqlsrv_query($conn, $equipment_query, array($premise_id));

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

// Статистика по категориям
$stats_query = "SELECT 
    c.name AS category,
    COUNT(e.id) AS count,
    COALESCE(SUM(e.current_value), 0) AS total_value
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
WHERE e.premise_id = ? AND e.is_active = 1
GROUP BY c.name
ORDER BY count DESC";

$stats_stmt = sqlsrv_query($conn, $stats_query, array($premise_id));

$category_stats = array();
if ($stats_stmt !== false) {
    while ($row = sqlsrv_fetch_array($stats_stmt, SQLSRV_FETCH_ASSOC)) {
        $category_stats[] = $row;
    }
}

// Статистика по состоянию
$condition_query = "SELECT 
    cs.name AS `condition`,
    COUNT(e.id) AS count
FROM equipment e
LEFT JOIN condition_status cs ON e.condition_id = cs.id
WHERE e.premise_id = ? AND e.is_active = 1
GROUP BY cs.name
ORDER BY count DESC";

$cond_stmt = sqlsrv_query($conn, $condition_query, array($premise_id));

$condition_stats = array();
if ($cond_stmt !== false) {
    while ($row = sqlsrv_fetch_array($cond_stmt, SQLSRV_FETCH_ASSOC)) {
        $condition_stats[] = $row;
    }
}

// Общая стоимость оборудования
$total_query = "SELECT 
    COUNT(id) AS equipment_count,
    COALESCE(SUM(current_value), 0) AS total_value
FROM equipment 
WHERE premise_id = ? AND is_active = 1";

$total_stmt = sqlsrv_query($conn, $total_query, array($premise_id));
$totals = sqlsrv_fetch_array($total_stmt, SQLSRV_FETCH_ASSOC);

closeDBConnection($conn);

echo json_encode([
    'success' => true,
    'data' => [
        'premise' => $premise,
        'equipment' => $equipment,
        'statistics' => [
            'equipment_count' => intval($totals['equipment_count'] ?? 0),
            'total_value' => floatval($totals['total_value'] ?? 0),
            'by_category' => $category_stats,
            'by_condition' => $condition_stats
        ]
    ]
]);
?>
