<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Shared\Services\AuditLogService;

class AdminAuditLogController
{
    protected AuditLogService $auditService;

    public function __construct(?AuditLogService $auditService = null)
    {
        $this->auditService = $auditService ?? new AuditLogService();
    }

    /**
     * GET /api/v1/admin/audit-logs
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $action = $request->query('action') !== null ? (string) $request->query('action') : null;
        $entityType = $request->query('entity_type') !== null ? (string) $request->query('entity_type') : null;

        $result = $this->auditService->listLogs($page, $perPage, $action, $entityType);

        return Response::paginated($result['items'], $page, $perPage, $result['total'], 'Nhật ký kiểm toán hệ thống');
    }
}