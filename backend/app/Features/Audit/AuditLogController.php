<?php

declare(strict_types=1);

namespace App\Features\Audit;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

class AuditLogController
{
    private AuditLogService $service;

    public function __construct(?AuditLogService $service = null)
    {
        $this->service = $service ?? new AuditLogService();
    }

    public function index(Request $request): Response
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtoupper((string) ($_SESSION['role'] ?? ''));

        if ($userId <= 0 || $role === '') {
            throw new HttpException('Bạn cần đăng nhập để thực hiện thao tác này', 401);
        }
        if ($role !== 'ADMIN') {
            throw new HttpException('Chỉ quản trị viên mới có quyền xem nhật ký kiểm toán', 403);
        }

        $logs = $this->service->listLogs($userId, $role, [
            'target_type' => $request->query('target_type'),
            'target_id' => $request->query('target_id'),
            'action' => $request->query('action'),
            'actor_user_id' => $request->query('actor_user_id'),
            'limit' => $request->query('limit'),
        ]);

        return Response::json([
            'success' => true,
            'data' => $logs,
            'serverTime' => date('c'),
        ]);
    }
}