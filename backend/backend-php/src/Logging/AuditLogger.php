<?php

declare(strict_types=1);

namespace App\Logging;

use App\Config\Database;
use App\Support\Id;
use PDO;
use Throwable;

/**
 * Writes to the audit_logs table. Direct port of lib/logger.ts's
 * logAudit(): failures here must NEVER break the primary request, so all
 * exceptions are caught and logged to the PHP error log instead of being
 * allowed to propagate.
 */
final class AuditLogger
{
    public static function log(
        ?string $schoolId,
        ?string $userId,
        string $action,
        string $resource,
        ?string $resourceId = null,
        ?string $ipAddress = null,
        ?array $metadata = null
    ): void {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (id, school_id, user_id, action, resource, resource_id, ip_address, metadata, created_at)
                 VALUES (:id, :school_id, :user_id, :action, :resource, :resource_id, :ip_address, :metadata, NOW())'
            );

            $stmt->execute([
                'id'          => Id::generate(),
                'school_id'   => $schoolId,
                'user_id'     => $userId,
                'action'      => $action,
                'resource'    => $resource,
                'resource_id' => $resourceId,
                'ip_address'  => $ipAddress,
                'metadata'    => $metadata !== null ? json_encode($metadata) : null,
            ]);
        } catch (Throwable $e) {
            // Audit logging must never break the primary request.
            error_log('Failed to write audit log: ' . $e->getMessage());
        }
    }
}
