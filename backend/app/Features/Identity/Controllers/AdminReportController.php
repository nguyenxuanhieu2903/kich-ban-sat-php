<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminReportController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * GET /api/v1/admin/reports/revenue (UC-A15: Xem báo cáo doanh thu)
     */
    public function revenue(Request $request): Response
    {
        $admin = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($admin instanceof Response) {
            return $admin;
        }

        // 1. Tổng tiền thực thu từ người chơi (cọc + tất toán)
        $revStmt = $this->pdo->query(
            "SELECT 
                COALESCE(SUM(paid_amount), 0) AS total_collected,
                COALESCE(SUM(CASE WHEN payment_type = 'DEPOSIT' THEN paid_amount ELSE 0 END), 0) AS deposit_collected,
                COALESCE(SUM(CASE WHEN payment_type = 'REMAINING' THEN paid_amount ELSE 0 END), 0) AS remaining_collected
             FROM player_payments"
        );
        $revData = $revStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_collected' => 0,
            'deposit_collected' => 0,
            'remaining_collected' => 0,
        ];

        // 2. Tổng tiền hoàn đã duyệt hoặc đã chi trả hoàn tất
        $refStmt = $this->pdo->query(
            "SELECT COALESCE(SUM(amount), 0) AS total_refunded FROM refunds WHERE status IN ('APPROVED', 'COMPLETED')"
        );
        $totalRefunded = (int) ($refStmt->fetchColumn() ?: 0);

        // 3. Số giao dịch thành công
        $txStmt = $this->pdo->query(
            "SELECT COUNT(*) FROM payment_transactions WHERE status = 'SUCCESS'"
        );
        $successTxCount = (int) ($txStmt->fetchColumn() ?: 0);

        // 4. Số phiên chơi đã hoàn tất
        $sessStmt = $this->pdo->query(
            "SELECT COUNT(*) FROM sessions WHERE status = 'COMPLETED'"
        );
        $completedSessions = (int) ($sessStmt->fetchColumn() ?: 0);

        // 5. Số phòng đang hoạt động
        $roomStmt = $this->pdo->query(
            "SELECT COUNT(*) FROM game_rooms WHERE status NOT IN ('CANCELLED', 'COMPLETED')"
        );
        $activeRooms = (int) ($roomStmt->fetchColumn() ?: 0);

        $totalCollected = (int) $revData['total_collected'];
        $netRevenue = max(0, $totalCollected - $totalRefunded);

        return Response::success([
            'total_net_revenue' => $netRevenue,
            'total_deposit' => (int) $revData['deposit_collected'],
            'completed_sessions' => $completedSessions,
            'revenue' => [
                'total_collected' => $totalCollected,
                'deposit_collected' => (int) $revData['deposit_collected'],
                'remaining_collected' => (int) $revData['remaining_collected'],
                'total_refunded' => $totalRefunded,
                'net_revenue' => $netRevenue,
            ],
            'statistics' => [
                'successful_transactions' => $successTxCount,
                'completed_sessions' => $completedSessions,
                'active_rooms' => $activeRooms,
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ], 'Báo cáo doanh thu và chỉ số vận hành hệ thống');
    }

    /**
     * GET /api/v1/admin/payments (UC-A14: Theo dõi giao dịch thanh toán)
     */
    public function payments(Request $request): Response
    {
        $admin = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($admin instanceof Response) {
            return $admin;
        }

        $query = $request->getQueryParams();
        $status = isset($query['status']) ? strtoupper(trim((string) $query['status'])) : null;
        $type = isset($query['type']) ? strtoupper(trim((string) $query['type'])) : null;
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($query['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'pp.status = :status';
            $params[':status'] = $status;
        }

        if ($type !== null && $type !== '') {
            $where[] = 'pp.payment_type = :type';
            $params[':type'] = $type;
        }

        $whereClause = implode(' AND ', $where);

        // Total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM player_payments pp WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Paginated items
        $sql = "SELECT 
                    pp.id, pp.room_participant_id, pp.payment_type, pp.amount, pp.paid_amount,
                    pp.status, pp.deadline_at, pp.paid_at, pp.created_at,
                    rp.user_id, u.full_name AS player_name, u.email AS player_email,
                    gr.id AS game_room_id, gr.title AS room_title
                FROM player_payments pp
                INNER JOIN room_participants rp ON rp.id = pp.room_participant_id
                INNER JOIN users u ON u.id = rp.user_id
                INNER JOIN game_rooms gr ON gr.id = rp.game_room_id
                WHERE {$whereClause}
                ORDER BY pp.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::paginated(
            $items,
            $page,
            $perPage,
            $total,
            'Danh sách giao dịch thanh toán của hệ thống'
        );
    }
}
