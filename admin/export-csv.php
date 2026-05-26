<?php
require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $db->prepare("SELECT id, customer_name, email, phone, address, notes, seats_json, total_amount, discount_type, delivery_option, status, created_at, expires_at, confirmed_at FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll();
} else {
    $rows = $db->query("SELECT id, customer_name, email, phone, address, notes, seats_json, total_amount, discount_type, delivery_option, status, created_at, expires_at, confirmed_at FROM reservations ORDER BY created_at DESC")->fetchAll();
}

$filename = $id ? "reservierung-$id.csv" : 'reservierungen-alle.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

fputcsv($out, [
    'ID', 'Name', 'E-Mail', 'Telefon', 'Adresse', 'Lieferung',
    'Plätze', 'Betrag', 'Ermässigung', 'Bemerkungen',
    'Status', 'Erstellt', 'Bestätigt'
]);

foreach ($rows as $r) {
    $seats = implode(', ', json_decode($r['seats_json'], true) ?: []);
    fputcsv($out, [
        $r['id'],
        $r['customer_name'],
        $r['email'],
        $r['phone'],
        $r['address'],
        $r['delivery_option'] === 'mail' ? 'Zustellung' : 'Abholung',
        $seats,
        number_format((float)$r['total_amount'], 2, '.', ''),
        $r['discount_type'] === 'student' ? 'Schüler/Student' : 'Keine',
        $r['notes'],
        $r['status'],
        $r['created_at'],
        $r['confirmed_at'] ?? '',
    ]);
}

fclose($out);