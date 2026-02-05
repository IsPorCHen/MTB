<?php
/**
 * API для работы с помещениями
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Проверка авторизации для всех действий кроме GET
if ($method !== 'GET') {
    checkAuth();
}

switch ($method) {
    case 'GET':
        if ($id) {
            getPremise($id);
        } else {
            getAllPremises();
        }
        break;
    case 'POST':
        if ($action === 'add') {
            addPremise();
        } elseif ($id) {
            updatePremise($id);
        }
        break;
    case 'PUT':
        updatePremise($id);
        break;
    case 'DELETE':
        deletePremise($id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

/**
 * Получить все помещения
 */
function getAllPremises() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT * FROM vw_premises_with_equipment ORDER BY building, room_number";
    $stmt = sqlsrv_query($conn, $query);
    
    // Fallback: если представление отсутствует — соберём данные через JOIN/COUNT
    if ($stmt === false) {
        $fallback = "SELECT p.id, p.room_number, p.building, p.floor, p.room_type, p.area, p.capacity, p.status, p.responsible_id, emp.full_name AS responsible_name, COUNT(e.id) AS equipment_count 
            FROM premises p 
            LEFT JOIN equipment e ON p.id = e.premise_id AND e.is_active = 1
            LEFT JOIN employees emp ON p.responsible_id = emp.id
            GROUP BY p.id, p.room_number, p.building, p.floor, p.room_type, p.area, p.capacity, p.status, p.responsible_id, emp.full_name 
            ORDER BY p.building, p.room_number";
        $stmt = sqlsrv_query($conn, $fallback);
        if ($stmt === false) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
            return;
        }
    }
    
    $premises = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $premises[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'data' => $premises,
        'count' => count($premises)
    ]);
}

/**
 * Получить одно помещение
 */
function getPremise($id) {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT * FROM vw_premises_with_equipment WHERE id = ?";
    $stmt = sqlsrv_query($conn, $query, array($id));
    
    if ($stmt === false) {
        $fallback = "SELECT p.id, p.room_number, p.building, p.floor, p.room_type, p.area, p.capacity, p.status, (SELECT COUNT(*) FROM equipment e WHERE e.premise_id = p.id) AS equipment_count FROM premises p WHERE p.id = ?";
        $stmt = sqlsrv_query($conn, $fallback, array($id));
        if ($stmt === false) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
            return;
        }
    }
    
    $premise = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    closeDBConnection($conn);
    
    if ($premise) {
        echo json_encode(['success' => true, 'data' => $premise]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Помещение не найдено']);
    }
}

/**
 * Добавить помещение
 */
function addPremise() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['room_number']) || empty($input['building'])) {
        echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "INSERT INTO premises (room_number, building, floor, room_type, area, capacity, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $params = array(
        $input['room_number'],
        $input['building'],
        $input['floor'] ?? null,
        $input['room_type'] ?? 'аудитория',
        $input['area'] ?? null,
        $input['capacity'] ?? null,
        $input['status'] ?? 'активное'
    );
    
    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка добавления', 'details' => sqlsrv_errors()]);
        return;
    }

    $newId = last_insert_id($conn);

    closeDBConnection($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Помещение успешно добавлено',
        'id' => $newId
    ]);
}

/**
 * Обновить помещение
 */
function updatePremise($id) {
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
    
    $query = "UPDATE premises 
              SET room_number = ?, building = ?, floor = ?, room_type = ?, 
                  area = ?, capacity = ?, status = ?, responsible_id = ?
              WHERE id = ?";
    
    $params = array(
        $input['room_number'],
        $input['building'],
        $input['floor'] ?? null,
        $input['room_type'] ?? 'аудитория',
        $input['area'] ?? null,
        $input['capacity'] ?? null,
        $input['status'] ?? 'активное',
        !empty($input['responsible_id']) ? $input['responsible_id'] : null,
        $id
    );
    
    $stmt = sqlsrv_query($conn, $query, $params);
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка обновления', 'details' => sqlsrv_errors()]);
        return;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Помещение успешно обновлено'
    ]);
}

/**
 * Удалить помещение
 */
function deletePremise($id) {
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'ID не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Проверка наличия оборудования в помещении
    $check_query = "SELECT COUNT(*) as count FROM equipment WHERE premise_id = ?";
    $check_stmt = sqlsrv_query($conn, $check_query, array($id));
    $check_result = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
    
    if ($check_result['count'] > 0) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Невозможно удалить помещение с оборудованием']);
        return;
    }
    
    $query = "DELETE FROM premises WHERE id = ?";
    $stmt = sqlsrv_query($conn, $query, array($id));
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка удаления', 'details' => sqlsrv_errors()]);
        return;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Помещение успешно удалено'
    ]);
}
?>
