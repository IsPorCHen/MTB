<?php
header('Content-Type: application/json; charset=utf-8');
try {
	ob_start();
	// Use DELETE method so api/equipment.php runs delete flow
	$_SERVER['REQUEST_METHOD'] = 'DELETE';
	include __DIR__ . '/api/equipment.php';
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
