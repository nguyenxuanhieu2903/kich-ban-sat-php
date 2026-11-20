<?php

declare(strict_types=1);

namespace App\Features\DMManagement\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\DMManagement\Repositories\DMRepository;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class DMAssignmentController
{
    protected DMRepository $dmRepo;
    protected PDO $pdo;

    public function __construct(?DMRepository $dmRepo = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->dmRepo = $dmRepo ?? new DMRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/dm/assignments
     * Query: status, dm_id (admin only)
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));
        $targetDmId = (int) $auth['id'];

        if ($userRole === 'ADMIN') {
            $queryDmId = $request->query('dm_id');
            $targetDmId = $queryDmId !== null && (int) $queryDmId > 0 ? (int) $queryDmId : null;
        }

        $status = $request->query('status') !== null ? (string) $request->query('status') : null;

        $assignments = $this->dmRepo->listAssignments($targetDmId, $status);

        return Response::success([
            'assignments' => $assignments,
            'count' => count($assignments),
        ], 'Danh sách phân công quản trò');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/accept
     */
    public function accept(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca làm việc yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Chống IDOR: Chỉ DM được gán cho ca này hoặc Admin mới có quyền accept
        if ($userRole !== 'ADMIN' && (int) $assignment['dm_user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền phản hồi ca làm việc của người khác', 'FORBIDDEN', 403);
        }

        if ($assignment['status'] !== 'PENDING') {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'ACCEPTED');

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
        ], 'Đã chấp nhận ca làm việc thành công');
    }

    /**
     * POST /api/v1/dm/assignments/{id}/reject
     * Body: reason (string, required)
     */
    public function reject(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['DM', 'ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $assignmentId = (int) $id;
        $assignment = $this->dmRepo->findAssignmentById($assignmentId);

        if ($assignment === null) {
            return Response::fail('Không tìm thấy ca làm việc yêu cầu', 'ASSIGNMENT_NOT_FOUND', 404);
        }

        $userRole = strtoupper((string) ($auth['role_code'] ?? ''));

        // Chống IDOR: Chỉ DM được gán hoặc Admin mới có quyền reject
        if ($userRole !== 'ADMIN' && (int) $assignment['dm_user_id'] !== (int) $auth['id']) {
            return Response::fail('Bạn không có quyền từ chối ca làm việc của người khác', 'FORBIDDEN', 403);
        }

        if ($assignment['status'] !== 'PENDING') {
            return Response::fail('Ca làm việc này đã được xử lý trước đó (Trạng thái: ' . $assignment['status'] . ')', 'INVALID_STATUS', 400);
        }

        $body = $request->getBodyParams();
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return Response::fail('Vui lòng nêu rõ lý do từ chối ca làm việc', 'VALIDATION_ERROR', 422);
        }

        $this->dmRepo->respondAssignment($assignmentId, 'REJECTED', $reason);

        $updated = $this->dmRepo->findAssignmentById($assignmentId);

        return Response::success([
            'assignment' => $updated,
        ], 'Đã từ chối ca làm việc');
    }
}
