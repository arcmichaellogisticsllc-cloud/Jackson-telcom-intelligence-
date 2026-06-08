<?php

namespace App\Core;

use Throwable;

class Audit
{
    public static function log(string $action, ?string $recordType = null, mixed $recordId = null, string $outcome = 'Success', ?string $details = null): void
    {
        try {
            $user = Auth::user();
            $db = Database::connection();
            $stmt = $db->prepare('INSERT INTO audit_logs (user_id, user_name, role, action, record_type, record_id, ip_address, user_agent, outcome, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user['id'] ?? null,
                $user['name'] ?? null,
                $user['role'] ?? null,
                $action,
                $recordType,
                $recordId !== null && $recordId !== '' ? (int)$recordId : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $outcome,
                $details,
            ]);
        } catch (Throwable) {
            // Audit logging must never break the operator workflow.
        }
    }
}
