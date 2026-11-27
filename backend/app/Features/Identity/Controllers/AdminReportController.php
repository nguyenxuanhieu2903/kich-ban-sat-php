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

        // 6. Doanh thu theo ngày (10 mốc ngày có phát sinh gần nhất) phục vụ biểu đồ cột ngang/dọc trên Dashboard
        $dailyStmt = $this->pdo->query(
            "SELECT
                DATE(COALESCE(pp.`paid_at`, pp.`created_at`)) AS `pay_date`,
                COALESCE(SUM(CASE WHEN pp.`payment_type` = 'DEPOSIT' THEN pp.`paid_amount` ELSE 0 END), 0) AS `deposit_vnd`,
                COALESCE(SUM(CASE WHEN pp.`payment_type` = 'REMAINING' THEN pp.`paid_amount` ELSE 0 END), 0) AS `remaining_vnd`,
                COALESCE(SUM(pp.`paid_amount`), 0) AS `total_vnd`,
                COUNT(*) AS `tx_count`
             FROM `player_payments` pp
             WHERE pp.`paid_amount` > 0
             GROUP BY DATE(COALESCE(pp.`paid_at`, pp.`created_at`))
             ORDER BY `pay_date` DESC
             LIMIT 9"
        );
        $dailyRows = array_reverse($dailyStmt->fetchAll(PDO::FETCH_ASSOC));
        $dailySeries = array_map(static function (array $r): array {
            $ts = strtotime((string) ($r['pay_date'] ?? ''));
            return [
                'date' => (string) ($r['pay_date'] ?? ''),
                'label' => $ts !== false ? date('d/m', $ts) : (string) ($r['pay_date'] ?? ''),
                'deposit_vnd' => (int) ($r['deposit_vnd'] ?? 0),
                'remaining_vnd' => (int) ($r['remaining_vnd'] ?? 0),
                'total_vnd' => (int) ($r['total_vnd'] ?? 0),
                'tx_count' => (int) ($r['tx_count'] ?? 0),
            ];
        }, $dailyRows);

        // 7. Doanh thu & lượt phòng theo từng kịch bản (Top 6 kịch bản nổi bật)
        $topGameStmt = $this->pdo->query(
            "SELECT
                g.`id` AS `game_id`,
                g.`name` AS `game_name`,
                COUNT(DISTINCT gr.`id`) AS `room_count`,
                COALESCE(SUM(pp.`paid_amount`), 0) AS `revenue_vnd`
             FROM `game_rooms` gr
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             LEFT JOIN `room_participants` rp ON rp.`game_room_id` = gr.`id`
             LEFT JOIN `player_payments` pp ON pp.`room_participant_id` = rp.`id`
             GROUP BY g.`id`, g.`name`
             ORDER BY `revenue_vnd` DESC, `room_count` DESC
             LIMIT 6"
        );
        $topScenarios = array_map(static fn (array $r): array => [
            'game_id' => (int) $r['game_id'],
            'game_name' => (string) $r['game_name'],
            'room_count' => (int) $r['room_count'],
            'revenue_vnd' => (int) $r['revenue_vnd'],
        ], $topGameStmt->fetchAll(PDO::FETCH_ASSOC));

        // 8. Đếm chính xác toàn bộ phòng chơi theo trạng thái
        $stStmt = $this->pdo->query(
            "SELECT UPPER(`status`) AS `st`, COUNT(*) AS `cnt` FROM `game_rooms` GROUP BY UPPER(`status`)"
        );
        $roomStatusCounts = [
            'FORMING' => 0,
            'SCHEDULED' => 0,
            'CONFIRMED' => 0,
            'IN_PROGRESS' => 0,
            'COMPLETED' => 0,
            'CANCELLED' => 0,
        ];
        foreach ($stStmt->fetchAll(PDO::FETCH_ASSOC) as $sr) {
            $k = (string) ($sr['st'] ?? '');
            if (array_key_exists($k, $roomStatusCounts)) {
                $roomStatusCounts[$k] = (int) $sr['cnt'];
            }
        }

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
            'daily_series' => $dailySeries,
            'top_scenarios' => $topScenarios,
            'room_status_counts' => $roomStatusCounts,
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
