<?php
/**
 * API для списания оборудования
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            getWriteOffs();
        } else {
            getWriteOffs();
        }
        break;
    case 'POST':
        if ($action === 'restore') {
            restoreEquipment();
        } else {
            writeOffEquipment();
        }
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

/**
 * Получить список списанного оборудования
 */
function getWriteOffs() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT 
        e.id,
        e.inventory_number,
        e.name,
        c.name AS category,
        e.manufacturer,
        e.model,
        e.purchase_date,
        e.purchase_price,
        e.decommissioning_date,
        e.decommissioning_reason,
        w.write_off_date,
        w.reason,
        w.document_number,
        w.document_date,
        w.residual_value,
        w.notes,
        emp.full_name AS approved_by_name
    FROM equipment e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN write_offs w ON e.id = w.equipment_id
    LEFT JOIN employees emp ON w.approved_by = emp.id
    WHERE e.is_active = 0
    ORDER BY COALESCE(w.write_off_date, e.decommissioning_date) DESC";
    
    $stmt = sqlsrv_query($conn, $query);
    
    if ($stmt === false) {
        // Fallback без таблицы write_offs
        $fallback = "SELECT 
            e.id,
            e.inventory_number,
            e.name,
            c.name AS category,
            e.manufacturer,
            e.model,
            e.purchase_date,
            e.purchase_price,
            e.decommissioning_date,
            e.decommissioning_reason
        FROM equipment e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.is_active = 0
        ORDER BY e.decommissioning_date DESC";
        
        $stmt = sqlsrv_query($conn, $fallback);
        if ($stmt === false) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
            return;
        }
    }
    
    $writeoffs = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Конвертация DateTime
        foreach (['purchase_date', 'decommissioning_date', 'write_off_date', 'document_date'] as $field) {
            if (isset($row[$field]) && $row[$field] instanceof DateTime) {
                $row[$field] = $row[$field]->format('Y-m-d');
            }
        }
        $writeoffs[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'data' => $writeoffs,
        'count' => count($writeoffs)
    ]);
}

/**
 * Списать оборудование
 */
function writeOffEquipment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['equipment_id']) || empty($input['reason'])) {
        echo json_encode(['success' => false, 'error' => 'Не указаны обязательные поля']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Получаем текущее состояние оборудования
    $check_query = "SELECT id, name, inventory_number, premise_id, responsible_id, condition_id, current_value, is_active 
                    FROM equipment WHERE id = ?";
    $check_stmt = sqlsrv_query($conn, $check_query, array($input['equipment_id']));
    $equipment = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$equipment) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Оборудование не найдено']);
        return;
    }
    
    if ($equipment['is_active'] == 0) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Оборудование уже списано']);
        return;
    }
    
    $write_off_date = $input['write_off_date'] ?? date('Y-m-d');
    
    // Обновляем статус оборудования
    $update_query = "UPDATE equipment SET 
        is_active = 0, 
        condition_id = (SELECT id FROM condition_status WHERE name = 'Списано' LIMIT 1),
        decommissioning_date = ?,
        decommissioning_reason = ?
        WHERE id = ?";
    
    $update_stmt = sqlsrv_query($conn, $update_query, array(
        $write_off_date,
        $input['reason'],
        $input['equipment_id']
    ));
    
    if ($update_stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка обновления статуса']);
        return;
    }
    
    // Пробуем добавить запись в таблицу write_offs
    $writeoff_query = "INSERT INTO write_offs 
        (equipment_id, write_off_date, reason, document_number, document_date, residual_value, approved_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    sqlsrv_query($conn, $writeoff_query, array(
        $input['equipment_id'],
        $write_off_date,
        $input['reason'],
        $input['document_number'] ?? null,
        $input['document_date'] ?? null,
        $input['residual_value'] ?? $equipment['current_value'],
        $input['approved_by'] ?? null,
        $input['notes'] ?? null
    ));
    
    // Добавляем запись в историю
    $history_query = "INSERT INTO equipment_history 
        (equipment_id, change_type, old_value, new_value, old_premise_id, old_responsible_id, old_condition_id, new_condition_id, reason, notes)
        VALUES (?, 'списание', ?, ?, ?, ?, ?, (SELECT id FROM condition_status WHERE name = 'Списано' LIMIT 1), ?, ?)";
    
    sqlsrv_query($conn, $history_query, array(
        $input['equipment_id'],
        'Активное',
        'Списано',
        $equipment['premise_id'],
        $equipment['responsible_id'],
        $equipment['condition_id'],
        $input['reason'],
        $input['notes'] ?? null
    ));
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Оборудование успешно списано',
        'inventory_number' => $equipment['inventory_number'],
        'name' => $equipment['name']
    ]);
}

/**
 * Восстановить списанное оборудование
 */
function restoreEquipment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['equipment_id'])) {
        echo json_encode(['success' => false, 'error' => 'ID оборудования не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Получаем текущее состояние
    $check_query = "SELECT id, name, inventory_number, is_active FROM equipment WHERE id = ?";
    $check_stmt = sqlsrv_query($conn, $check_query, array($input['equipment_id']));
    $equipment = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$equipment) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Оборудование не найдено']);
        return;
    }
    
    if ($equipment['is_active'] == 1) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Оборудование не было списано']);
        return;
    }
    
    $new_condition = $input['new_condition_id'] ?? 3; // По умолчанию "Удовлетворительное"
    
    // Восстанавливаем оборудование
    $update_query = "UPDATE equipment SET 
        is_active = 1, 
        condition_id = ?,
        decommissioning_date = NULL,
        decommissioning_reason = NULL
        WHERE id = ?";
    
    $update_stmt = sqlsrv_query($conn, $update_query, array($new_condition, $input['equipment_id']));
    
    if ($update_stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка восстановления']);
        return;
    }
    
    // Удаляем запись из write_offs
    sqlsrv_query($conn, "DELETE FROM write_offs WHERE equipment_id = ?", array($input['equipment_id']));
    
    // Добавляем запись в историю
    $history_query = "INSERT INTO equipment_history 
        (equipment_id, change_type, old_value, new_value, new_condition_id, reason, notes)
        VALUES (?, 'восстановление', 'Списано', 'Активное', ?, ?, ?)";
    
    sqlsrv_query($conn, $history_query, array(
        $input['equipment_id'],
        $new_condition,
        $input['reason'] ?? 'Восстановление из списания',
        $input['notes'] ?? null
    ));
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Оборудование успешно восстановлено',
        'inventory_number' => $equipment['inventory_number'],
        'name' => $equipment['name']
    ]);
}
?>
