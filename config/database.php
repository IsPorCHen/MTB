<?php
// MySQL (PDO) configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'college_mtb');
define('DB_USER', 'root');
define('DB_PASS', '');

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Error handling: do not display errors to output (prevents breaking JSON), log to file
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Log path inside project
if (!defined('PHP_ERROR_LOG')) define('PHP_ERROR_LOG', __DIR__ . '/../logs/php_errors.log');
ini_set('error_log', PHP_ERROR_LOG);

// SQLSRV compatibility constants (some legacy code uses these)
if (!defined('SQLSRV_FETCH_ASSOC')) define('SQLSRV_FETCH_ASSOC', 2);
if (!defined('SQLSRV_FETCH_NUMERIC')) define('SQLSRV_FETCH_NUMERIC', 1);
if (!defined('SQLSRV_FETCH_BOTH')) define('SQLSRV_FETCH_BOTH', 3);

// Compatibility wrapper for sqlsrv_close (maps to PDO connection cleanup)
function sqlsrv_close($conn) {
    closeDBConnection($conn);
}

function getDBConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('PDO connection error: ' . $e->getMessage());
        return false;
    }

    return $pdo;
}

function closeDBConnection($conn) {
    // Для PDO достаточно дать переменной значение null
    $conn = null;
}

/**
 * Compatibility wrapper: emulate minimal sqlsrv_* behaviour using PDO
 */
function sqlsrv_query($conn, $query, $params = array()) {
    try {
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->execute(array_values($params));
            return $stmt;
        } else {
            $stmt = $conn->query($query);
            return $stmt;
        }
    } catch (PDOException $e) {
        error_log('Query error: ' . $e->getMessage());
        return false;
    }
}

function sqlsrv_fetch_array($stmt, $fetch_style = null) {
    if ($stmt === false) return false;
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function sqlsrv_errors() {
    // Return a simple array with last error info if any
    return error_get_last();
}

function last_insert_id($conn) {
    try {
        return $conn->lastInsertId();
    } catch (Exception $e) {
        return null;
    }
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Требуется авторизация']);
        exit;
    }
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}

function checkRole($required_role) {
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $roles_hierarchy = ['viewer' => 1, 'user' => 2, 'admin' => 3];
    $user_level = $roles_hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $roles_hierarchy[$required_role] ?? 999;
    return $user_level >= $required_level;
}

?>
