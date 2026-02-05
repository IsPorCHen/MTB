<?php
/**
 * API для инвентаризаций
 * Полная реализация создания, проведения и завершения инвентаризации
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
            getInventory($id);
        } else {
            getAllInventories();
        }
        break;
    case 'POST':
        if ($action === 'add') {
            addInventory();
        } elseif ($action === 'complete') {
            completeInventory();
        } elseif ($action === 'start') {
            startInventory();
        } elseif ($action === 'cancel') {
            cancelInventory();
        } else {
            addInventory();
        }
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

function getAllInventories() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }

    $query = "SELECT 
        i.id, i.inventory_number, i.start_date, i.end_date, i.status, 
        i.responsible_id, emp.full_name AS responsible, i.notes, i.created_at,
        (SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id = i.id) AS total_items,
        (SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id = i.id AND ii.checked_at IS NOT NULL) AS checked,
        (SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id = i.id AND ii.status = 'совпадает') AS matched,
        (SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id = i.id AND ii.status = 'расхождение') AS discrepancies,
        (SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id = i.id AND ii.status = 'не найдено') AS not_found
    FROM inventories i
    LEFT JOIN employees emp ON i.responsible_id = emp.id
    ORDER BY i.start_date DESC";

    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach (['start_date', 'end_date', 'created_at'] as $field) {
            if (isset($row[$field]) && $row[$field] instanceof DateTime) {
                $row[$field] = $row[$field]->format($field === 'created_at' ? 'Y-m-d H:i:s' : 'Y-m-d');
            }
        }
        $rows[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
}

function getInventory($id) {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }

    // Получаем данные инвентаризации
    $query = "SELECT 
        i.id, i.inventory_number, i.start_date, i.end_date, i.status, 
        i.responsible_id, emp.full_name AS responsible, i.notes
    FROM inventories i 
    LEFT JOIN employees emp ON i.responsible_id = emp.id 
    WHERE i.id = ?";
    
    $stmt = sqlsrv_query($conn, $query, [$id]);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }

    $inventory = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$inventory) {
        echo json_encode(['success' => false, 'error' => 'Инвентаризация не найдена']);
        return;
    }

    foreach (['start_date', 'end_date'] as $field) {
        if (isset($inventory[$field]) && $inventory[$field] instanceof DateTime) {
            $inventory[$field] = $inventory[$field]->format('Y-m-d');
        }
    }

    // Получаем позиции инвентаризации
    $itemsQuery = "SELECT 
        ii.id, ii.equipment_id, ii.status, ii.notes, ii.checked_at,
        e.inventory_number, e.name,
        ii.expected_location_id, CONCAT(ep.building, ', ', ep.room_number) AS expected_location,
        ii.actual_location_id, CONCAT(ap.building, ', ', ap.room_number) AS actual_location,
        ii.expected_condition_id, ecs.name AS expected_condition,
        ii.actual_condition_id, acs.name AS actual_condition
    FROM inventory_items ii
    LEFT JOIN equipment e ON ii.equipment_id = e.id
    LEFT JOIN premises ep ON ii.expected_location_id = ep.id
    LEFT JOIN premises ap ON ii.actual_location_id = ap.id
    LEFT JOIN condition_status ecs ON ii.expected_condition_id = ecs.id
    LEFT JOIN condition_status acs ON ii.actual_condition_id = acs.id
    WHERE ii.inventory_id = ?
    ORDER BY e.inventory_number";

    $itemsStmt = sqlsrv_query($conn, $itemsQuery, [$id]);
    if ($itemsStmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка загрузки позиций']);
        return;
    }

    $items = [];
    while ($row = sqlsrv_fetch_array($itemsStmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['checked_at']) && $row['checked_at'] instanceof DateTime) {
            $row['checked_at'] = $row['checked_at']->format('Y-m-d H:i:s');
        }
        $items[] = $row;
    }

    echo json_encode(['success' => true, 'data' => ['inventory' => $inventory, 'items' => $items]]);
}

function addInventory() {
    $input = json_decode(file_get_contents('php://input'), true);
    $input = $input ?: [];

    if (empty($input['inventory_number']) || empty($input['start_date'])) {
        echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля: номер и дата начала']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }

    // Проверка уникальности номера
    $checkStmt = sqlsrv_query($conn, "SELECT id FROM inventories WHERE inventory_number = ?", [$input['inventory_number']]);
    if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'error' => 'Инвентаризация с таким номером уже существует']);
        return;
    }

    // Создаём инвентаризацию
    $query = "INSERT INTO inventories (inventory_number, start_date, end_date, status, responsible_id, notes) 
              VALUES (?, ?, ?, 'в процессе', ?, ?)";
    $params = [
        $input['inventory_number'], 
        $input['start_date'], 
        $input['end_date'] ?? null, 
        !empty($input['responsible_id']) ? $input['responsible_id'] : null, 
        $input['notes'] ?? null
    ];

    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка создания инвентаризации']);
        return;
    }

    $invId = last_insert_id($conn);

    // Автоматически заполняем позиции всем активным оборудованием
    $populateQuery = "INSERT INTO inventory_items (inventory_id, equipment_id, expected_location_id, expected_condition_id, status) 
                      SELECT ?, e.id, e.premise_id, e.condition_id, 'не проверено' 
                      FROM equipment e WHERE e.is_active = 1";
    
    $popStmt = sqlsrv_query($conn, $populateQuery, [$invId]);
    
    // Считаем добавленные позиции
    $countStmt = sqlsrv_query($conn, "SELECT COUNT(*) as cnt FROM inventory_items WHERE inventory_id = ?", [$invId]);
    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $itemsCount = $countRow['cnt'] ?? 0;

    echo json_encode([
        'success' => true, 
        'id' => $invId, 
        'message' => "Инвентаризация создана. Добавлено позиций: $itemsCount"
    ]);
}

function startInventory() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID инвентаризации не указан']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }

    $stmt = sqlsrv_query($conn, "UPDATE inventories SET status = 'в процессе' WHERE id = ? AND status = 'запланирована'", [$input['id']]);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка запуска инвентаризации']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Инвентаризация начата']);
}

function completeInventory() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID инвентаризации не указан']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }

    // Проверяем что все позиции проверены
    $checkStmt = sqlsrv_query($conn, 
        "SELECT COUNT(*) as unchecked FROM inventory_items WHERE inventory_id = ? AND checked_at IS NULL", 
        [$input['id']]
    );
    $checkRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    
    if ($checkRow['unchecked'] > 0) {
        echo json_encode(['success' => false, 'error' => "Не все позиции проверены. Осталось: {$checkRow['unchecked']}"]);
        return;
    }

    // Завершаем инвентаризацию
    $stmt = sqlsrv_query($conn, 
        "UPDATE inventories SET status = 'завершена', end_date = CURDATE() WHERE id = ?", 
        [$input['id']]
    );
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка завершения инвентаризации']);
        return;
    }

    // Записываем результаты в историю оборудования для позиций с расхождениями
    $discrepancyStmt = sqlsrv_query($conn, 
        "SELECT ii.equipment_id, ii.status, ii.notes, ii.actual_location_id, ii.actual_condition_id,
                e.name, i.inventory_number
         FROM inventory_items ii
         JOIN equipment e ON ii.equipment_id = e.id
         JOIN inventories i ON ii.inventory_id = i.id
         WHERE ii.inventory_id = ? AND ii.status IN ('расхождение', 'не найдено')",
        [$input['id']]
    );

    while ($row = sqlsrv_fetch_array($discrepancyStmt, SQLSRV_FETCH_ASSOC)) {
        $historyQuery = "INSERT INTO equipment_history 
            (equipment_id, change_type, reason, notes) 
            VALUES (?, 'инвентаризация', ?, ?)";
        
        sqlsrv_query($conn, $historyQuery, [
            $row['equipment_id'],
            "Инвентаризация {$row['inventory_number']}: {$row['status']}",
            $row['notes']
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Инвентаризация завершена']);
}

function cancelInventory() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID инвентаризации не указан']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }

    $stmt = sqlsrv_query($conn, "UPDATE inventories SET status = 'отменена' WHERE id = ?", [$input['id']]);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка отмены инвентаризации']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Инвентаризация отменена']);
}
?>
