<?php
/**
 * API для работы с оборудованием
 * Полная реализация CRUD с записью истории изменений
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getEquipment($id);
        } else {
            getAllEquipment();
        }
        break;
    case 'POST':
        if ($action === 'add') {
            addEquipment();
        } elseif ($id) {
            updateEquipment($id);
        } else {
            addEquipment();
        }
        break;
    case 'PUT':
        updateEquipment($id);
        break;
    case 'DELETE':
        deleteEquipment($id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

function getAllEquipment() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT 
        e.id, e.inventory_number, e.name, e.category_id, e.premise_id, e.responsible_id, e.condition_id,
        c.name AS category, 
        CONCAT(p.building, ', ', p.room_number) AS location,
        cs.name AS `condition`,
        e.current_value AS price,
        e.current_value, e.purchase_price, e.manufacturer, e.model, e.serial_number,
        e.purchase_date, e.warranty_until, e.commissioning_date,
        e.is_active, emp.full_name AS responsible, e.description,
        e.created_at, e.updated_at
    FROM equipment e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN premises p ON e.premise_id = p.id
    LEFT JOIN condition_status cs ON e.condition_id = cs.id
    LEFT JOIN employees emp ON e.responsible_id = emp.id
    WHERE e.is_active = 1
    ORDER BY e.created_at DESC";
    
    $stmt = sqlsrv_query($conn, $query);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $equipment = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach (['purchase_date', 'warranty_until', 'commissioning_date', 'created_at', 'updated_at'] as $field) {
            if (isset($row[$field]) && $row[$field] instanceof DateTime) {
                $row[$field] = $row[$field]->format($field === 'created_at' || $field === 'updated_at' ? 'Y-m-d H:i:s' : 'Y-m-d');
            }
        }
        $equipment[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $equipment, 'count' => count($equipment)]);
}

function getEquipment($id) {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT 
        e.*, c.name AS category, 
        CONCAT(p.building, ', ', p.room_number) AS location,
        cs.name AS `condition`,
        emp.full_name AS responsible
    FROM equipment e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN premises p ON e.premise_id = p.id
    LEFT JOIN condition_status cs ON e.condition_id = cs.id
    LEFT JOIN employees emp ON e.responsible_id = emp.id
    WHERE e.id = ?";
    
    $stmt = sqlsrv_query($conn, $query, [$id]);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $equipment = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$equipment) {
        echo json_encode(['success' => false, 'error' => 'Оборудование не найдено']);
        return;
    }
    
    foreach (['purchase_date', 'warranty_until', 'commissioning_date', 'decommissioning_date', 'created_at', 'updated_at'] as $field) {
        if (isset($equipment[$field]) && $equipment[$field] instanceof DateTime) {
            $equipment[$field] = $equipment[$field]->format($field === 'created_at' || $field === 'updated_at' ? 'Y-m-d H:i:s' : 'Y-m-d');
        }
    }
    
    echo json_encode(['success' => true, 'data' => $equipment]);
}

function addEquipment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['inventory_number']) || empty($input['name'])) {
        echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $checkStmt = sqlsrv_query($conn, "SELECT id FROM equipment WHERE inventory_number = ?", [$input['inventory_number']]);
    if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'error' => 'Оборудование с таким инвентарным номером уже существует']);
        return;
    }
    
    $query = "INSERT INTO equipment 
        (inventory_number, name, category_id, premise_id, responsible_id, 
         purchase_date, purchase_price, current_value, condition_id, 
         manufacturer, model, serial_number, description, warranty_until, commissioning_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $input['inventory_number'],
        $input['name'],
        $input['category_id'] ?? 1,
        !empty($input['premise_id']) ? $input['premise_id'] : null,
        !empty($input['responsible_id']) ? $input['responsible_id'] : null,
        !empty($input['purchase_date']) ? $input['purchase_date'] : null,
        !empty($input['purchase_price']) ? $input['purchase_price'] : null,
        !empty($input['current_value']) ? $input['current_value'] : (!empty($input['purchase_price']) ? $input['purchase_price'] : null),
        $input['condition_id'] ?? 1,
        $input['manufacturer'] ?? null,
        $input['model'] ?? null,
        $input['serial_number'] ?? null,
        $input['description'] ?? null,
        !empty($input['warranty_until']) ? $input['warranty_until'] : null,
        !empty($input['commissioning_date']) ? $input['commissioning_date'] : null
    ];
    
    $stmt = sqlsrv_query($conn, $query, $params);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка добавления оборудования']);
        return;
    }
    
    $newId = last_insert_id($conn);
    
    // Записываем в историю
    $historyQuery = "INSERT INTO equipment_history 
        (equipment_id, change_type, new_value, new_premise_id, new_responsible_id, new_condition_id, reason, notes)
        VALUES (?, 'создание', ?, ?, ?, ?, 'Добавление нового оборудования', ?)";
    
    sqlsrv_query($conn, $historyQuery, [
        $newId,
        $input['name'],
        !empty($input['premise_id']) ? $input['premise_id'] : null,
        !empty($input['responsible_id']) ? $input['responsible_id'] : null,
        $input['condition_id'] ?? 1,
        "Инв. номер: {$input['inventory_number']}"
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Оборудование успешно добавлено', 'id' => $newId]);
}

function updateEquipment($id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'ID не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $oldStmt = sqlsrv_query($conn, "SELECT * FROM equipment WHERE id = ?", [$id]);
    $oldData = sqlsrv_fetch_array($oldStmt, SQLSRV_FETCH_ASSOC);
    
    if (!$oldData) {
        echo json_encode(['success' => false, 'error' => 'Оборудование не найдено']);
        return;
    }
    
    $changeType = 'редактирование';
    $changes = [];
    
    $newPremiseId = !empty($input['premise_id']) ? intval($input['premise_id']) : null;
    $oldPremiseId = $oldData['premise_id'] ? intval($oldData['premise_id']) : null;
    if ($newPremiseId !== $oldPremiseId) {
        $changeType = 'перемещение';
        $changes[] = 'помещение';
    }
    
    $newResponsibleId = !empty($input['responsible_id']) ? intval($input['responsible_id']) : null;
    $oldResponsibleId = $oldData['responsible_id'] ? intval($oldData['responsible_id']) : null;
    if ($newResponsibleId !== $oldResponsibleId) {
        if ($changeType === 'редактирование') $changeType = 'смена_ответственного';
        $changes[] = 'ответственный';
    }
    
    $newConditionId = isset($input['condition_id']) ? intval($input['condition_id']) : null;
    $oldConditionId = $oldData['condition_id'] ? intval($oldData['condition_id']) : null;
    if ($newConditionId !== $oldConditionId) {
        if ($changeType === 'редактирование') $changeType = 'смена_состояния';
        $changes[] = 'состояние';
    }
    
    $query = "UPDATE equipment SET
        name = ?, category_id = ?, premise_id = ?, responsible_id = ?,
        purchase_date = ?, purchase_price = ?, current_value = ?, condition_id = ?,
        manufacturer = ?, model = ?, serial_number = ?, description = ?, 
        warranty_until = ?, commissioning_date = ?, updated_at = NOW()
        WHERE id = ?";
    
    $params = [
        $input['name'] ?? $oldData['name'],
        $input['category_id'] ?? $oldData['category_id'],
        $newPremiseId,
        $newResponsibleId,
        !empty($input['purchase_date']) ? $input['purchase_date'] : $oldData['purchase_date'],
        isset($input['purchase_price']) && $input['purchase_price'] !== '' ? $input['purchase_price'] : $oldData['purchase_price'],
        isset($input['current_value']) && $input['current_value'] !== '' ? $input['current_value'] : $oldData['current_value'],
        $newConditionId ?? $oldData['condition_id'],
        isset($input['manufacturer']) ? $input['manufacturer'] : $oldData['manufacturer'],
        isset($input['model']) ? $input['model'] : $oldData['model'],
        isset($input['serial_number']) ? $input['serial_number'] : $oldData['serial_number'],
        isset($input['description']) ? $input['description'] : $oldData['description'],
        !empty($input['warranty_until']) ? $input['warranty_until'] : $oldData['warranty_until'],
        !empty($input['commissioning_date']) ? $input['commissioning_date'] : $oldData['commissioning_date'],
        $id
    ];
    
    $stmt = sqlsrv_query($conn, $query, $params);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка обновления']);
        return;
    }
    
    if (!empty($changes)) {
        $historyQuery = "INSERT INTO equipment_history 
            (equipment_id, change_type, old_value, new_value, 
             old_premise_id, new_premise_id, old_responsible_id, new_responsible_id, 
             old_condition_id, new_condition_id, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        sqlsrv_query($conn, $historyQuery, [
            $id, $changeType, $oldData['name'], $input['name'] ?? $oldData['name'],
            $oldPremiseId, $newPremiseId, $oldResponsibleId, $newResponsibleId,
            $oldConditionId, $newConditionId, 'Изменено: ' . implode(', ', $changes)
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Оборудование успешно обновлено', 'changes' => $changes]);
}

function deleteEquipment($id) {
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'ID не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $infoStmt = sqlsrv_query($conn, "SELECT inventory_number, name FROM equipment WHERE id = ?", [$id]);
    $equipment = sqlsrv_fetch_array($infoStmt, SQLSRV_FETCH_ASSOC);
    
    if (!$equipment) {
        echo json_encode(['success' => false, 'error' => 'Оборудование не найдено']);
        return;
    }
    
    $stmt = sqlsrv_query($conn, "DELETE FROM equipment WHERE id = ?", [$id]);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка удаления']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Оборудование успешно удалено',
        'deleted' => ['id' => $id, 'inventory_number' => $equipment['inventory_number'], 'name' => $equipment['name']]
    ]);
}
?>
