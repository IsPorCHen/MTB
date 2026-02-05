<?php
/**
 * API для генерации документов
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'inventory_act':
        generateInventoryAct();
        break;
    case 'equipment_list':
        generateEquipmentList();
        break;
    case 'premise_passport':
        generatePremisePassport();
        break;
    case 'responsible_report':
        generateResponsibleReport();
        break;
    case 'writeoff_act':
        generateWriteoffAct();
        break;
    case 'transfer_act':
        generateTransferAct();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестный тип документа']);
}

function generateInventoryAct() {
    $input = json_decode(file_get_contents('php://input'), true);
    $inventoryId = $input['inventory_id'] ?? $_GET['inventory_id'] ?? null;
    
    if (!$inventoryId) {
        echo json_encode(['success' => false, 'error' => 'ID инвентаризации не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Получаем данные инвентаризации
    $invStmt = sqlsrv_query($conn, 
        "SELECT i.*, emp.full_name AS responsible_name 
         FROM inventories i 
         LEFT JOIN employees emp ON i.responsible_id = emp.id 
         WHERE i.id = ?", 
        [$inventoryId]
    );
    $inventory = sqlsrv_fetch_array($invStmt, SQLSRV_FETCH_ASSOC);
    
    if (!$inventory) {
        echo json_encode(['success' => false, 'error' => 'Инвентаризация не найдена']);
        return;
    }
    
    // Получаем позиции
    $itemsStmt = sqlsrv_query($conn,
        "SELECT ii.*, e.inventory_number, e.name, e.current_value,
                CONCAT(ep.building, ', ', ep.room_number) AS expected_location,
                CONCAT(ap.building, ', ', ap.room_number) AS actual_location,
                ecs.name AS expected_condition, acs.name AS actual_condition
         FROM inventory_items ii
         LEFT JOIN equipment e ON ii.equipment_id = e.id
         LEFT JOIN premises ep ON ii.expected_location_id = ep.id
         LEFT JOIN premises ap ON ii.actual_location_id = ap.id
         LEFT JOIN condition_status ecs ON ii.expected_condition_id = ecs.id
         LEFT JOIN condition_status acs ON ii.actual_condition_id = acs.id
         WHERE ii.inventory_id = ?
         ORDER BY e.inventory_number",
        [$inventoryId]
    );
    
    $items = [];
    while ($row = sqlsrv_fetch_array($itemsStmt, SQLSRV_FETCH_ASSOC)) {
        $items[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'inventory' => $inventory,
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'matched' => count(array_filter($items, fn($i) => $i['status'] === 'совпадает')),
                'discrepancy' => count(array_filter($items, fn($i) => $i['status'] === 'расхождение')),
                'not_found' => count(array_filter($items, fn($i) => $i['status'] === 'не найдено'))
            ]
        ]
    ]);
}

function generateEquipmentList() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $categoryId = $input['category_id'] ?? null;
    $premiseId = $input['premise_id'] ?? null;
    $responsibleId = $input['responsible_id'] ?? null;
    
    $query = "SELECT 
        e.id, e.inventory_number, e.name, c.name AS category,
        CONCAT(p.building, ', ', p.room_number) AS location,
        cs.name AS `condition`, e.current_value AS price,
        e.purchase_date, emp.full_name AS responsible
    FROM equipment e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN premises p ON e.premise_id = p.id
    LEFT JOIN condition_status cs ON e.condition_id = cs.id
    LEFT JOIN employees emp ON e.responsible_id = emp.id
    WHERE e.is_active = 1";
    
    $params = [];
    
    if ($categoryId) {
        $query .= " AND e.category_id = ?";
        $params[] = $categoryId;
    }
    if ($premiseId) {
        $query .= " AND e.premise_id = ?";
        $params[] = $premiseId;
    }
    if ($responsibleId) {
        $query .= " AND e.responsible_id = ?";
        $params[] = $responsibleId;
    }
    
    $query .= " ORDER BY e.inventory_number";
    
    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $equipment = [];
    $totalValue = 0;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['purchase_date']) && $row['purchase_date'] instanceof DateTime) {
            $row['purchase_date'] = $row['purchase_date']->format('Y-m-d');
        }
        $totalValue += floatval($row['price'] ?? 0);
        $equipment[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $equipment,
        'summary' => [
            'count' => count($equipment),
            'total_value' => $totalValue
        ]
    ]);
}

function generatePremisePassport() {
    $input = json_decode(file_get_contents('php://input'), true);
    $premiseId = $input['premise_id'] ?? $_GET['premise_id'] ?? null;
    
    if (!$premiseId) {
        echo json_encode(['success' => false, 'error' => 'ID помещения не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Получаем данные помещения
    $premiseStmt = sqlsrv_query($conn,
        "SELECT p.*, emp.full_name AS responsible_name, emp.phone AS responsible_phone
         FROM premises p
         LEFT JOIN employees emp ON p.responsible_id = emp.id
         WHERE p.id = ?",
        [$premiseId]
    );
    $premise = sqlsrv_fetch_array($premiseStmt, SQLSRV_FETCH_ASSOC);
    
    if (!$premise) {
        echo json_encode(['success' => false, 'error' => 'Помещение не найдено']);
        return;
    }
    
    // Получаем оборудование
    $eqStmt = sqlsrv_query($conn,
        "SELECT e.inventory_number, e.name, c.name AS category, 
                cs.name AS `condition`, e.current_value AS price, emp.full_name AS responsible
         FROM equipment e
         LEFT JOIN categories c ON e.category_id = c.id
         LEFT JOIN condition_status cs ON e.condition_id = cs.id
         LEFT JOIN employees emp ON e.responsible_id = emp.id
         WHERE e.premise_id = ? AND e.is_active = 1
         ORDER BY c.name, e.name",
        [$premiseId]
    );
    
    $equipment = [];
    $totalValue = 0;
    while ($row = sqlsrv_fetch_array($eqStmt, SQLSRV_FETCH_ASSOC)) {
        $totalValue += floatval($row['price'] ?? 0);
        $equipment[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'premise' => $premise,
            'equipment' => $equipment,
            'summary' => [
                'equipment_count' => count($equipment),
                'total_value' => $totalValue
            ]
        ]
    ]);
}

function generateResponsibleReport() {
    $input = json_decode(file_get_contents('php://input'), true);
    $responsibleId = $input['responsible_id'] ?? $_GET['responsible_id'] ?? null;
    
    if (!$responsibleId) {
        echo json_encode(['success' => false, 'error' => 'ID ответственного не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Получаем данные сотрудника
    $empStmt = sqlsrv_query($conn, "SELECT * FROM employees WHERE id = ?", [$responsibleId]);
    $employee = sqlsrv_fetch_array($empStmt, SQLSRV_FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'error' => 'Сотрудник не найден']);
        return;
    }
    
    // Получаем оборудование за сотрудником
    $eqStmt = sqlsrv_query($conn,
        "SELECT e.inventory_number, e.name, c.name AS category,
                CONCAT(p.building, ', ', p.room_number) AS location,
                cs.name AS `condition`, e.current_value AS price
         FROM equipment e
         LEFT JOIN categories c ON e.category_id = c.id
         LEFT JOIN premises p ON e.premise_id = p.id
         LEFT JOIN condition_status cs ON e.condition_id = cs.id
         WHERE e.responsible_id = ? AND e.is_active = 1
         ORDER BY c.name, e.name",
        [$responsibleId]
    );
    
    $equipment = [];
    $totalValue = 0;
    while ($row = sqlsrv_fetch_array($eqStmt, SQLSRV_FETCH_ASSOC)) {
        $totalValue += floatval($row['price'] ?? 0);
        $equipment[] = $row;
    }
    
    // Получаем помещения за сотрудником
    $premisesStmt = sqlsrv_query($conn,
        "SELECT room_number, building, room_type, area, capacity, status
         FROM premises WHERE responsible_id = ?
         ORDER BY building, room_number",
        [$responsibleId]
    );
    
    $premises = [];
    while ($row = sqlsrv_fetch_array($premisesStmt, SQLSRV_FETCH_ASSOC)) {
        $premises[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'employee' => $employee,
            'equipment' => $equipment,
            'premises' => $premises,
            'summary' => [
                'equipment_count' => count($equipment),
                'premises_count' => count($premises),
                'total_value' => $totalValue
            ]
        ]
    ]);
}

function generateWriteoffAct() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $equipmentIds = $input['equipment_ids'] ?? null;
    
    $query = "SELECT e.inventory_number, e.name, c.name AS category,
              e.decommissioning_date, e.decommissioning_reason, e.current_value,
              w.write_off_date, w.reason, w.document_number, w.residual_value,
              emp.full_name AS approved_by_name
       FROM equipment e
       LEFT JOIN categories c ON e.category_id = c.id
       LEFT JOIN write_offs w ON e.id = w.equipment_id
       LEFT JOIN employees emp ON w.approved_by = emp.id
       WHERE e.is_active = 0";
    
    $params = [];
    if ($equipmentIds && is_array($equipmentIds)) {
        $placeholders = implode(',', array_fill(0, count($equipmentIds), '?'));
        $query .= " AND e.id IN ($placeholders)";
        $params = $equipmentIds;
    }
    
    $query .= " ORDER BY COALESCE(w.write_off_date, e.decommissioning_date) DESC";
    
    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $writeoffs = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach (['decommissioning_date', 'write_off_date'] as $field) {
            if (isset($row[$field]) && $row[$field] instanceof DateTime) {
                $row[$field] = $row[$field]->format('Y-m-d');
            }
        }
        $writeoffs[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $writeoffs,
        'count' => count($writeoffs)
    ]);
}

function generateTransferAct() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'message' => 'Функция генерации акта приёма-передачи',
            'params' => $input
        ]
    ]);
}
?>
