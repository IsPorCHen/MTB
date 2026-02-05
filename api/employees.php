<?php
/**
 * API для работы с сотрудниками
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
            getEmployee($id);
        } else {
            getAllEmployees();
        }
        break;
    case 'POST':
        if ($action === 'add') {
            addEmployee();
        } elseif ($id) {
            updateEmployee($id);
        }
        break;
    case 'PUT':
        updateEmployee($id);
        break;
    case 'DELETE':
        deleteEmployee($id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}

/**
 * Получить всех сотрудников
 */
function getAllEmployees() {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT * FROM vw_employees_with_equipment ORDER BY full_name";
    $stmt = sqlsrv_query($conn, $query);
    
    // Fallback: если представление отсутствует — соберём данные через JOIN/COUNT
    if ($stmt === false) {
        $fallback = "SELECT emp.id, emp.full_name, emp.position, emp.department, emp.phone, emp.email, COUNT(e.id) AS equipment_count FROM employees emp LEFT JOIN equipment e ON emp.id = e.responsible_id GROUP BY emp.id, emp.full_name, emp.position, emp.department, emp.phone, emp.email ORDER BY emp.full_name";
        $stmt = sqlsrv_query($conn, $fallback);
        if ($stmt === false) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
            return;
        }
    }
    
    $employees = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $employees[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'count' => count($employees)
    ]);
}

/**
 * Получить одного сотрудника
 */
function getEmployee($id) {
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT * FROM vw_employees_with_equipment WHERE id = ?";
    $stmt = sqlsrv_query($conn, $query, array($id));
    
    if ($stmt === false) {
        $fallback = "SELECT emp.id, emp.full_name, emp.position, emp.department, emp.phone, emp.email, (SELECT COUNT(*) FROM equipment e WHERE e.responsible_id = emp.id) AS equipment_count FROM employees emp WHERE emp.id = ?";
        $stmt = sqlsrv_query($conn, $fallback, array($id));
        if ($stmt === false) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
            return;
        }
    }
    
    $employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    closeDBConnection($conn);
    
    if ($employee) {
        echo json_encode(['success' => true, 'data' => $employee]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Сотрудник не найден']);
    }
}

/**
 * Добавить сотрудника
 */
function addEmployee() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['full_name']) || empty($input['position'])) {
        echo json_encode(['success' => false, 'error' => 'Заполните обязательные поля']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "INSERT INTO employees (full_name, position, department, phone, email) 
              VALUES (?, ?, ?, ?, ?)";
    
    $params = array(
        $input['full_name'],
        $input['position'],
        $input['department'] ?? null,
        $input['phone'] ?? null,
        $input['email'] ?? null
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
        'message' => 'Сотрудник успешно добавлен',
        'id' => $newId
    ]);
}

/**
 * Обновить сотрудника
 */
function updateEmployee($id) {
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
    
    $query = "UPDATE employees 
              SET full_name = ?, position = ?, department = ?, phone = ?, email = ?
              WHERE id = ?";
    
    $params = array(
        $input['full_name'],
        $input['position'],
        $input['department'] ?? null,
        $input['phone'] ?? null,
        $input['email'] ?? null,
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
        'message' => 'Сотрудник успешно обновлен'
    ]);
}

/**
 * Удалить сотрудника
 */
function deleteEmployee($id) {
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'ID не указан']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Проверка наличия ответственного оборудования
    $check_query = "SELECT COUNT(*) as count FROM equipment WHERE responsible_id = ?";
    $check_stmt = sqlsrv_query($conn, $check_query, array($id));
    $check_result = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
    
    if ($check_result['count'] > 0) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Невозможно удалить сотрудника с ответственным оборудованием']);
        return;
    }
    
    $query = "DELETE FROM employees WHERE id = ?";
    $stmt = sqlsrv_query($conn, $query, array($id));
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка удаления', 'details' => sqlsrv_errors()]);
        return;
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Сотрудник успешно удален'
    ]);
}
?>
