<?php
/**
 * API авторизации и регистрации
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
    case 'login':
        login();
        break;
    case 'register':
        register();
        break;
    case 'logout':
        logout();
        break;
    case 'check_username':
        checkUsername();
        break;
    case 'check_auth':
        checkAuthStatus();
        break;
    case 'get_user':
        getUserInfo();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}

/**
 * Вход в систему
 */
function login() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Введите логин и пароль']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT id, username, password_hash, full_name, email, role, is_active FROM users WHERE username = ?";
    $stmt = sqlsrv_query($conn, $query, array($username));
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if (!$user) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль']);
        return;
    }
    
    if ($user['is_active'] == 0) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Аккаунт заблокирован']);
        return;
    }
    
    // Проверка пароля
    if (!password_verify($password, $user['password_hash'])) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Неверный логин или пароль']);
        return;
    }
    
    // Обновляем время последнего входа
    $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
    sqlsrv_query($conn, $update_query, array($user['id']));
    
    // Сохраняем в сессию
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Вход выполнен успешно',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
}

/**
 * Регистрация нового пользователя
 */
function register() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Валидация
    if (empty($username) || strlen($username) < 3) {
        echo json_encode(['success' => false, 'error' => 'Логин должен быть не менее 3 символов']);
        return;
    }
    
    if (strlen($username) > 50) {
        echo json_encode(['success' => false, 'error' => 'Логин слишком длинный (макс. 50 символов)']);
        return;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode(['success' => false, 'error' => 'Логин может содержать только латинские буквы, цифры и _']);
        return;
    }
    
    if (empty($full_name)) {
        echo json_encode(['success' => false, 'error' => 'Введите ФИО']);
        return;
    }
    
    if (strlen($full_name) > 100) {
        echo json_encode(['success' => false, 'error' => 'ФИО слишком длинное (макс. 100 символов)']);
        return;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Некорректный email']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => 'Пароль должен быть не менее 6 символов']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    // Проверяем, существует ли пользователь
    $check_query = "SELECT id FROM users WHERE username = ?";
    $check_stmt = sqlsrv_query($conn, $check_query, array($username));
    
    if ($check_stmt && sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Пользователь с таким логином уже существует']);
        return;
    }
    
    // Проверяем email если указан
    if (!empty($email)) {
        $email_query = "SELECT id FROM users WHERE email = ?";
        $email_stmt = sqlsrv_query($conn, $email_query, array($email));
        
        if ($email_stmt && sqlsrv_fetch_array($email_stmt, SQLSRV_FETCH_ASSOC)) {
            closeDBConnection($conn);
            echo json_encode(['success' => false, 'error' => 'Пользователь с таким email уже существует']);
            return;
        }
    }
    
    // Хешируем пароль
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Создаем пользователя
    $insert_query = "INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, 'user', 1)";
    $insert_stmt = sqlsrv_query($conn, $insert_query, array(
        $username,
        $password_hash,
        $full_name,
        !empty($email) ? $email : null
    ));
    
    if ($insert_stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка регистрации', 'details' => sqlsrv_errors()]);
        return;
    }
    
    $newId = last_insert_id($conn);
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Регистрация успешна',
        'user_id' => $newId
    ]);
}

/**
 * Выход из системы
 */
function logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Выход выполнен'
    ]);
}

/**
 * Проверка доступности логина
 */
function checkUsername() {
    $username = trim($_GET['username'] ?? '');
    
    if (empty($username) || strlen($username) < 3) {
        echo json_encode(['available' => false]);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['available' => false]);
        return;
    }
    
    $query = "SELECT id FROM users WHERE username = ?";
    $stmt = sqlsrv_query($conn, $query, array($username));
    
    $exists = $stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    closeDBConnection($conn);
    
    echo json_encode(['available' => !$exists]);
}

/**
 * Проверка статуса авторизации
 */
function checkAuthStatus() {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ]);
    }
}

/**
 * Получить информацию о текущем пользователе
 */
function getUserInfo() {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Не авторизован']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        return;
    }
    
    $query = "SELECT id, username, full_name, email, role, created_at, last_login FROM users WHERE id = ?";
    $stmt = sqlsrv_query($conn, $query, array($_SESSION['user_id']));
    
    if ($stmt === false) {
        closeDBConnection($conn);
        echo json_encode(['success' => false, 'error' => 'Ошибка запроса']);
        return;
    }
    
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    closeDBConnection($conn);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        return;
    }
    
    // Конвертация DateTime
    if (isset($user['created_at']) && $user['created_at'] instanceof DateTime) {
        $user['created_at'] = $user['created_at']->format('Y-m-d H:i:s');
    }
    if (isset($user['last_login']) && $user['last_login'] instanceof DateTime) {
        $user['last_login'] = $user['last_login']->format('Y-m-d H:i:s');
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}
?>
