<?php
/**
 * API для позиций инвентаризации
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

if ($method === 'POST') {
    updateItem();
} else {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

function updateItem() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID позиции не указан']);
        return;
    }
    
    if (empty($input['status'])) {
        echo json_encode(['success' => false, 'error' => 'Статус не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Проверяем что позиция существует
    $checkStmt = sqlsrv_query($conn, "SELECT id FROM inventory_items WHERE id = ?", [$input['id']]);
    if (!$checkStmt || !sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'error' => 'Позиция не найдена']);
        return;
    }
    
    // Обновляем позицию
    $query = "UPDATE inventory_items SET 
        status = ?,
        actual_location_id = ?,
        actual_condition_id = ?,
        notes = ?,
        checked_at = NOW()
        WHERE id = ?";
    
    $params = [
        $input['status'],
        $input['actual_location_id'] ?? null,
        $input['actual_condition_id'] ?? null,
        $input['notes'] ?? null,
        $input['id']
    ];
    
    $stmt = sqlsrv_query($conn, $query, $params);
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Ошибка обновления']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Позиция обновлена',
        'status' => $input['status']
    ]);
}
?>
