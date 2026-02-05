<?php
try {
    ob_start();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // forward action and id to the included API file
    $_GET['action'] = 'update';
    if (isset($_GET['id'])) {
        // ensure numeric id
        $_GET['id'] = intval($_GET['id']);
    }
    include __DIR__ . '/api/inventory_items.php';
    $out = ob_get_clean();
    $decoded = json_decode($out, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $out;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON returned by API', 'raw' => $out]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
