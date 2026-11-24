<?php

declare(strict_types=1);

namespace App\Features\Admin\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminSystemController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    protected function requireAdmin(Request $request): ?Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        return null;
    }

    /**
     * GET /api/v1/admin/audit-logs
     * Tra cứu nhật ký các thao tác nhạy cảm của hệ thống
     */
    public function auditLogs(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) {
            return $check;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $action = $request->query('action') !== null ? trim((string) $request->query('action')) : null;
        $entityType = $request->query('entity_type') !== null ? trim((string) $request->query('entity_type')) : null;
        $search = $request->query('search') !== null ? trim((string) $request->query('search')) : null;

        $where = ['1 = 1'];
        $params = [];

        if ($action !== null && $action !== '' && $action !== 'ALL') {
            $where[] = 'a.`action` = :action';
            $params[':action'] = $action;
        }

        if ($entityType !== null && $entityType !== '' && $entityType !== 'ALL') {
            $where[] = 'a.`entity_type` = :entity_type';
            $params[':entity_type'] = $entityType;
        }

        if ($search !== null && $search !== '') {
            $where[] = '(a.`action` LIKE :s1 OR a.`entity_type` LIKE :s2 OR a.`ip_address` LIKE :s3 OR u.`full_name` LIKE :s4 OR u.`email` LIKE :s5)';
            $wildcard = '%' . $search . '%';
            $params[':s1'] = $wildcard;
            $params[':s2'] = $wildcard;
            $params[':s3'] = $wildcard;
            $params[':s4'] = $wildcard;
            $params[':s5'] = $wildcard;
        }

        $whereSql = implode(' AND ', $where);

        // Đếm tổng số
        $cntStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `audit_logs` a
             LEFT JOIN `users` u ON u.`id` = a.`user_id`
             WHERE {$whereSql}"
        );
        foreach ($params as $k => $v) {
            $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $cntStmt->execute();
        $total = (int) $cntStmt->fetchColumn();

        // Lấy danh sách
        $stmt = $this->pdo->prepare(
            "SELECT a.`id`, a.`user_id`, a.`action`, a.`entity_type`, a.`entity_id`,
                    a.`old_values`, a.`new_values`, a.`ip_address`, a.`user_agent`, a.`created_at`,
                    u.`full_name` AS `user_name`, u.`email` AS `user_email`, r.`code` AS `user_role`
             FROM `audit_logs` a
             LEFT JOIN `users` u ON u.`id` = a.`user_id`
             LEFT JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE {$whereSql}
             ORDER BY a.`id` DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $logs = array_map(function ($row) {
            if (is_string($row['old_values'])) {
                $row['old_values'] = json_decode($row['old_values'], true) ?? $row['old_values'];
            }
            if (is_string($row['new_values'])) {
                $row['new_values'] = json_decode($row['new_values'], true) ?? $row['new_values'];
            }
            return $row;
        }, $rows);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return Response::success([
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ], 'Nhật ký thao tác hệ thống (Audit Logs)');
    }

    /**
     * GET /api/v1/admin/system/health
     * Kiểm tra sức khỏe toàn diện hệ thống: Database, Disk, PHP Runtime, Logging
     */
    public function health(Request $request): Response
    {
        $check = $this->requireAdmin($request);
        if ($check !== null) {
            return $check;
        }

        // 1. Kiểm tra Database
        $dbStatus = 'OK';
        $dbVersion = 'Unknown';
        $dbName = 'Unknown';
        $dbTablesCount = 0;
        try {
            $vStmt = $this->pdo->query('SELECT VERSION() AS ver, DATABASE() AS db');
            $row = $vStmt->fetch(PDO::FETCH_ASSOC);
            $dbVersion = (string) ($row['ver'] ?? 'Unknown');
            $dbName = (string) ($row['db'] ?? 'Unknown');

            $tStmt = $this->pdo->query('SHOW TABLES');
            $dbTablesCount = count($tStmt->fetchAll(PDO::FETCH_NUM));
        } catch (\Throwable $e) {
            $dbStatus = 'DEGRADED: ' . $e->getMessage();
        }

        // 2. Thông số Disk & Memory
        $rootPath = dirname(__DIR__, 4);
        $freeSpace = @disk_free_space($rootPath);
        $totalSpace = @disk_total_space($rootPath);
        $freeSpaceGb = $freeSpace !== false ? round($freeSpace / (1024 * 1024 * 1024), 2) : 0;
        $totalSpaceGb = $totalSpace !== false ? round($totalSpace / (1024 * 1024 * 1024), 2) : 0;

        $memUsageMb = round(memory_get_usage(true) / (1024 * 1024), 2);
        $memPeakMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

        // 3. Storage Directory & Logs
        $storageDir = $rootPath . '/backend/storage';
        $logDir = $storageDir . '/logs';
        $rateLimitDir = $storageDir . '/rate_limits';

        $logFilesCount = is_dir($logDir) ? count(glob($logDir . '/*.log') ?: []) : 0;
        $rateLimitFilesCount = is_dir($rateLimitDir) ? count(glob($rateLimitDir . '/*.json') ?: []) : 0;

        return Response::success([
            'status' => $dbStatus === 'OK' ? 'HEALTHY' : 'DEGRADED',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => [
                'status' => $dbStatus,
                'engine' => 'MySQL / MariaDB',
                'version' => $dbVersion,
                'database' => $dbName,
                'tables_count' => $dbTablesCount,
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'os' => PHP_OS_FAMILY,
                'memory_usage_mb' => $memUsageMb,
                'memory_peak_mb' => $memPeakMb,
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'storage' => [
                'disk_free_gb' => $freeSpaceGb,
                'disk_total_gb' => $totalSpaceGb,
                'disk_used_pct' => $totalSpaceGb > 0 ? round((($totalSpaceGb - $freeSpaceGb) / $totalSpaceGb) * 100, 1) : 0,
                'log_files_count' => $logFilesCount,
                'rate_limit_entries' => $rateLimitFilesCount,
                'storage_writable' => is_writable($storageDir),
            ],
        ], 'Báo cáo sức khỏe máy chủ & hạ tầng');
    }
}
