<?php
/**
 * API для работы с историей изменений оборудования
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$equipment_id = $_GET['equipment_id'] ?? null;

switch ($method) {
    case 'GET':
        if ($equipment_id) {
            getEquipmentHistory($equipment_id);
        } elseif ($action === 'recent') {
            getRecentHistory();
        } else {
            getAllHistory();
        }
        break;
    case 'POST':
        addHistoryRecord();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

/**
 * Получить историю конкретного оборудования
 */
function getEquipmentHistory($equipment_id) {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Попробуем использовать представление
    $query = "SELECT * FROM vw_equipment_history_details WHERE equipment_id = ? ORDER BY change_date DESC";
    $stmt = sqlsrv_query($conn, $query, array($equipment_id));
    
    // Fallback если представление не существует
    if ($stmt === false) {
        $fallback = "SELECT 
            h.id,
            h.equipment_id,
            h.change_type,
            h.change_date,
            h.old_value,
            h.new_value,
            h.reason,
            h.notes,
            e.inventory_number,
            e.name AS equipment_name,
            op.room_number AS old_premise_number,
            op.building AS old_premise_building,
            np.room_number AS new_premise_number,
            np.building AS new_premise_building,
            oe.full_name AS old_responsible_name,
            ne.full_name AS new_responsible_name,
            oc.name AS old_condition_name,
            nc.name AS new_condition_name,
            u.full_name AS performed_by_name
        FROM equipment_history h
        LEFT JOIN equipment e ON h.equipment_id = e.id
        LEFT JOIN premises op ON h.old_premise_id = op.id
        LEFT JOIN premises np ON h.new_premise_id = np.id
        LEFT JOIN employees oe ON h.old_responsible_id = oe.id
        LEFT JOIN employees ne ON h.new_responsible_id = ne.id
        LEFT JOIN condition_status oc ON h.old_condition_id = oc.id
        LEFT JOIN condition_status nc ON h.new_condition_id = nc.id
        LEFT JOIN users u ON h.performed_by = u.id
        WHERE h.equipment_id = ?
        ORDER BY h.change_date DESC";
        
        $stmt = sqlsrv_query($conn, $fallback, array($equipment_id));
        if ($stmt === false) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Ошибка запроса', 'details' => sqlsrv_errors()]);
            return;
        }
    }
    
    $history = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Конвертация DateTime
        if (isset($row['change_date']) && $row['change_date'] instanceof DateTime) {
            $row['change_date'] = $row['change_date']->format('Y-m-d H:i:s');
        }
        $history[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'data' => $history,
        'count' => count($history)
    ]);
}

/**
 * Получить последние изменения (для дашборда)
 */
function getRecentHistory() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    $query = "SELECT 
        h.id,
        h.equipment_id,
        h.change_type,
        h.change_date,
        h.old_value,
        h.new_value,
        h.reason,
        e.inventory_number,
        e.name AS equipment_name,
        CONCAT(np.building, ', ', np.room_number) AS new_location,
        ne.full_name AS new_responsible_name,
        nc.name AS new_condition_name
    FROM equipment_history h
    LEFT JOIN equipment e ON h.equipment_id = e.id
    LEFT JOIN premises np ON h.new_premise_id = np.id
    LEFT JOIN employees ne ON h.new_responsible_id = ne.id
    LEFT JOIN condition_status nc ON h.new_condition_id = nc.id
    ORDER BY h.change_date DESC
    LIMIT ?";
    
    $stmt = sqlsrv_query($conn, $query, array($limit));
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $history = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['change_date']) && $row['change_date'] instanceof DateTime) {
            $row['change_date'] = $row['change_date']->format('Y-m-d H:i:s');
        }
        $history[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
}

/**
 * Получить всю историю
 */
function getAllHistory() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $change_type = $_GET['type'] ?? null;
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    
    $query = "SELECT 
        h.id,
        h.equipment_id,
        h.change_type,
        h.change_date,
        h.old_value,
        h.new_value,
        h.reason,
        h.notes,
        e.inventory_number,
        e.name AS equipment_name,
        CONCAT(COALESCE(op.building, ''), COALESCE(CONCAT(', ', op.room_number), '')) AS old_location,
        CONCAT(COALESCE(np.building, ''), COALESCE(CONCAT(', ', np.room_number), '')) AS new_location,
        oe.full_name AS old_responsible_name,
        ne.full_name AS new_responsible_name,
        oc.name AS old_condition_name,
        nc.name AS new_condition_name
    FROM equipment_history h
    LEFT JOIN equipment e ON h.equipment_id = e.id
    LEFT JOIN premises op ON h.old_premise_id = op.id
    LEFT JOIN premises np ON h.new_premise_id = np.id
    LEFT JOIN employees oe ON h.old_responsible_id = oe.id
    LEFT JOIN employees ne ON h.new_responsible_id = ne.id
    LEFT JOIN condition_status oc ON h.old_condition_id = oc.id
    LEFT JOIN condition_status nc ON h.new_condition_id = nc.id
    WHERE 1=1";
    
    $params = array();
    
    if ($change_type) {
        $query .= " AND h.change_type = ?";
        $params[] = $change_type;
    }
    
    if ($date_from) {
        $query .= " AND h.change_date >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND h.change_date <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $query .= " ORDER BY h.change_date DESC LIMIT 500";
    
    $stmt = sqlsrv_query($conn, $query, $params);
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $history = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['change_date']) && $row['change_date'] instanceof DateTime) {
            $row['change_date'] = $row['change_date']->format('Y-m-d H:i:s');
        }
        $history[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'data' => $history,
        'count' => count($history)
    ]);
}

/**
 * Добавить запись в историю
 */
function addHistoryRecord() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['equipment_id']) || empty($input['change_type'])) {
        echo json_encode(['success' => false, 'error' => 'Не указаны обязательные поля']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "INSERT INTO equipment_history 
        (equipment_id, change_type, old_value, new_value, old_premise_id, new_premise_id, 
         old_responsible_id, new_responsible_id, old_condition_id, new_condition_id, 
         reason, performed_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $performed_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    $params = array(
        $input['equipment_id'],
        $input['change_type'],
        $input['old_value'] ?? null,
        $input['new_value'] ?? null,
        $input['old_premise_id'] ?? null,
        $input['new_premise_id'] ?? null,
        $input['old_responsible_id'] ?? null,
        $input['new_responsible_id'] ?? null,
        $input['old_condition_id'] ?? null,
        $input['new_condition_id'] ?? null,
        $input['reason'] ?? null,
        $performed_by,
        $input['notes'] ?? null
    );
    
    $stmt = sqlsrv_query($conn, $query, $params);
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка добавления записи', 'details' => sqlsrv_errors()]);
        return;
    }
    
    $newId = last_insert_id($conn);
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Запись добавлена в историю',
        'id' => $newId
    ]);
}
?>
