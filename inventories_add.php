<?php
// Wrapper to create inventory via API
try {
    ob_start();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // ensure action is provided when including API
    $_GET['action'] = 'add';
    include __DIR__ . '/api/inventories.php';
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
