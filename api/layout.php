<?php
require_once __DIR__ . '/../config.php';
ensureLayoutSchema();

header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['list'])) {
        jsonResponse(['layouts' => getAllLayouts()]);
    }

    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT id, name, cols, `rows`, grid_config, cells, labels, is_active FROM seat_layouts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['error' => 'Layout nicht gefunden.'], 404);
        $row['grid_config'] = json_decode($row['grid_config'], true) ?: [];
        $row['cells'] = json_decode($row['cells'], true) ?: [];
        $row['labels'] = json_decode($row['labels'] ?? '[]', true) ?: [];
        jsonResponse($row);
    }

    $layout = getActiveLayout();
    if (!$layout) jsonResponse(['error' => 'Kein aktives Layout vorhanden.'], 404);
    jsonResponse($layout);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) jsonResponse(['error' => 'Ungültige Daten.'], 400);

    $csrfToken = $body['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        jsonResponse(['error' => 'Ungültiges CSRF-Token.'], 403);
    }

    $action = $body['action'] ?? 'save';

    if ($action === 'save') {
        $name = trim($body['name'] ?? 'Unbenanntes Layout');
        $cols = max(1, (int)($body['cols'] ?? 35));
        $rows = max(1, (int)($body['rows'] ?? 30));
        $gridConfig = $body['grid_config'] ?? [];
        $cells = $body['cells'] ?? [];
        $labels = $body['labels'] ?? [];
        $setActive = !empty($body['set_active']);
        $layoutId = !empty($body['id']) ? (int)$body['id'] : null;

        if (empty($gridConfig['columns'])) {
            jsonResponse(['error' => 'grid_config.columns fehlt.'], 400);
        }

        $jsonConfig = json_encode($gridConfig, JSON_UNESCAPED_UNICODE);
        $jsonCells = json_encode($cells, JSON_UNESCAPED_UNICODE);
        $jsonLabels = json_encode($labels, JSON_UNESCAPED_UNICODE);

        if ($setActive) {
            $db->exec("UPDATE seat_layouts SET is_active = 0");
        }

        if ($layoutId) {
            $stmt = $db->prepare("UPDATE seat_layouts SET name = ?, cols = ?, `rows` = ?, grid_config = ?, cells = ?, labels = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $cols, $rows, $jsonConfig, $jsonCells, $jsonLabels, $setActive ? 1 : 0, $layoutId]);
            $id = $layoutId;
        } else {
            $stmt = $db->prepare("INSERT INTO seat_layouts (name, cols, `rows`, grid_config, cells, labels, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $cols, $rows, $jsonConfig, $jsonCells, $jsonLabels, $setActive ? 1 : 0]);
            $id = (int)$db->lastInsertId();
        }

        jsonResponse(['ok' => true, 'id' => $id]);
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Keine Layout-ID.'], 400);
        $db->prepare("DELETE FROM seat_layouts WHERE id = ?")->execute([$id]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'activate') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'Keine Layout-ID.'], 400);
        $db->exec("UPDATE seat_layouts SET is_active = 0");
        $db->prepare("UPDATE seat_layouts SET is_active = 1 WHERE id = ?")->execute([$id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Unbekannte Aktion.'], 400);
}

jsonResponse(['error' => 'Method Not Allowed'], 405);
