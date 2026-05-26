<?php
/**
 * Cron job: Clean up expired pending reservations.
 * Run this every hour via cron:
 *   0 * * * * php /path/to/cron/cleanup.php
 */
require_once __DIR__ . '/../config.php';

$db = getDb();

// Expire old pending reservations
$stmt = $db->prepare("
    UPDATE reservations SET status = 'expired'
    WHERE status = 'pending' AND expires_at <= NOW()
");
$stmt->execute();
$expired = $stmt->rowCount();

// Clean up old rate limit entries (older than 24h)
$db->exec("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

echo date('Y-m-d H:i:s') . " - Expired $expired reservations, cleaned rate limits.\n";
