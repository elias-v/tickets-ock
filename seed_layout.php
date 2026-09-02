<?php
/**
 * Converts the current hardcoded seat layout into a seat_layouts JSON record.
 * Run once: php seed_layout.php
 */
require_once __DIR__ . '/config.php';
ensureLayoutSchema();
$db = getDb();

echo "Seeding Konzertsaal Kreuzlingen layout...\n";

$gridConfig = [
    'columns' => [
        ['idx' => 1, 'type' => 'margin'], ['idx' => 2, 'type' => 'margin'],
        ['idx' => 3, 'type' => 'margin'], ['idx' => 4, 'type' => 'margin'],
        ['idx' => 5, 'type' => 'margin'], ['idx' => 6, 'type' => 'margin'],
        ['idx' => 7, 'type' => 'spacer'],
        ['idx' => 8, 'type' => 'left'], ['idx' => 9, 'type' => 'left'],
        ['idx' => 10, 'type' => 'left'], ['idx' => 11, 'type' => 'left'],
        ['idx' => 12, 'type' => 'left'], ['idx' => 13, 'type' => 'left'],
        ['idx' => 14, 'type' => 'left'], ['idx' => 15, 'type' => 'left'],
        ['idx' => 16, 'type' => 'left'], ['idx' => 17, 'type' => 'left'],
        ['idx' => 18, 'type' => 'row-num'],
        ['idx' => 19, 'type' => 'right'], ['idx' => 20, 'type' => 'right'],
        ['idx' => 21, 'type' => 'right'], ['idx' => 22, 'type' => 'right'],
        ['idx' => 23, 'type' => 'right'], ['idx' => 24, 'type' => 'right'],
        ['idx' => 25, 'type' => 'right'], ['idx' => 26, 'type' => 'right'],
        ['idx' => 27, 'type' => 'right'], ['idx' => 28, 'type' => 'right'],
        ['idx' => 29, 'type' => 'right'],
        ['idx' => 30, 'type' => 'spacer'],
        ['idx' => 31, 'type' => 'margin'], ['idx' => 32, 'type' => 'margin'],
        ['idx' => 33, 'type' => 'margin'], ['idx' => 34, 'type' => 'margin'],
        ['idx' => 35, 'type' => 'margin'],
    ],
    'rowRange' => [2, 22],
    'emporeRows' => [23, 26],
    'emporeConfig' => [
        23 => ['center' => 'spieltisch', 'leftCols' => [8,9,10,11,12,13,14], 'rightCols' => []],
        24 => ['center' => '', 'leftCols' => [8,9,10,11,12,13,14], 'rightCols' => [26,27,28,29]],
        25 => ['center' => 'orgel', 'leftCols' => [8,9,10,11,12,13,14], 'rightCols' => [26,27,28,29]],
        26 => ['center' => 'turm', 'leftCols' => [8,9,10,11,12,13,14], 'rightCols' => []],
    ],
];

$labels = [
    ['text' => 'CHOR', 'position' => 'chor'],
    ['text' => 'links', 'position' => 'header-left', 'colStart' => 8, 'colEnd' => 18],
    ['text' => 'rechts', 'position' => 'header-right', 'colStart' => 19, 'colEnd' => 29],
    ['text' => 'EMPORE', 'position' => 'empore'],
    ['text' => 'Orgel', 'position' => 'empore-overlay', 'colStart' => 16, 'colEnd' => 20, 'rowStart' => 23, 'rowEnd' => 25],
    ['text' => 'Aufgang', 'position' => 'empore-aufgang', 'colStart' => 26, 'colEnd' => 30],
];

$seats = $db->query("SELECT seat_number, row_number, category, section, col_pos, is_bodan FROM seats ORDER BY row_number, col_pos")->fetchAll();

$cells = [];
foreach ($seats as $s) {
    $key = $s['row_number'] . ':' . $s['col_pos'];
    $cell = [
        'type' => 'seat',
        'category' => (int)$s['category'],
        'section' => $s['section'],
        'seat_number' => (int)$s['seat_number'],
    ];
    if ($s['is_bodan']) {
        $cell['bodan'] = true;
    }
    $cells[$key] = $cell;
}

$gridJson = json_encode($gridConfig, JSON_UNESCAPED_UNICODE);
$cellsJson = json_encode($cells, JSON_UNESCAPED_UNICODE);
$labelsJson = json_encode($labels, JSON_UNESCAPED_UNICODE);

$db->exec("UPDATE seat_layouts SET is_active = 0");

$stmt = $db->prepare("INSERT INTO seat_layouts (name, cols, `rows`, grid_config, cells, labels, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
$stmt->execute(['Konzertsaal Kreuzlingen', 35, 30, $gridJson, $cellsJson, $labelsJson]);
$id = (int)$db->lastInsertId();

$db->prepare("UPDATE seats SET layout_id = ?")->execute([$id]);

echo "Layout created with ID $id (" . count($cells) . " cells).\n";
echo "Active layout set to: Konzertsaal Kreuzlingen\n";
