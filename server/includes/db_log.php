<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/db_log.php
 * Purpose: Logging helpers — audit, email, login attempt, and tracking query logs
 */
/**
 * Log a sent or failed email.
 */
function logEmail(mysqli $conn, string $recipient, string $subject, string $emailType, string $status = 'sent', ?int $userId = null, ?string $relatedTable = null, ?int $relatedId = null): void
{
    $stmt = $conn->prepare(
        'INSERT INTO email_log (user_id, recipient, subject, email_type, status, related_table, related_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isssssi', $userId, $recipient, $subject, $emailType, $status, $relatedTable, $relatedId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Log a tracking lookup.
 */
function logTrackingQuery(mysqli $conn, string $trackingNumber, string $carrier, bool $foundInDb, ?int $userId = null, ?string $ip = null): void
{
    $found = $foundInDb ? 1 : 0;
    $stmt = $conn->prepare(
        'INSERT INTO tracking_queries (user_id, tracking_number, carrier, found_in_db, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('issis', $userId, $trackingNumber, $carrier, $found, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Log an admin or user action.
 */
function logAudit(mysqli $conn, string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?string $ip = null): void
{
    $stmt = $conn->prepare(
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Record a login attempt (success or failure).
 */
function logLoginAttempt(mysqli $conn, string $email, bool $success, ?string $ip = null): void
{
    $stmt = $conn->prepare(
        'INSERT INTO login_attempts (email, ip_address, was_success) VALUES (?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $flag = $success ? 1 : 0;
    $stmt->bind_param('ssi', $email, $ip, $flag);
    $stmt->execute();
    $stmt->close();
}

/**
 * Resolve shipment id by tracking number and carrier.
 */
function findShipmentId(mysqli $conn, string $trackingNumber, string $carrier): ?int
{
    $stmt = $conn->prepare(
        'SELECT id FROM shipments WHERE tracking_number = ? AND carrier = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $trackingNumber, $carrier);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}
