<?php

declare(strict_types=1);

namespace App\Features\PaymentRefund\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class AdminDashboardController
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/admin/dashboard
     * Tổng hợp toàn bộ số liệu thống kê doanh thu, phòng chơi, hoàn cọc và thẻ HSSV cho Admin.
     */
    public function index(Request $request): Response
    {
        $auth = AuthMiddleware::authenticate($request, ['ADMIN']);
        if ($auth instanceof Response) {
            return $auth;
        }

        // 1. Doanh thu tháng này và tháng trước
        $revMonthStmt = $this->pdo->query(
            "SELECT COALESCE(SUM(`paid_amount`), 0) FROM `player_payments`
             WHERE `status` IN ('PAID', 'PARTIALLY_PAID')
               AND `paid_amount` > 0
               AND MONTH(COALESCE(`paid_at`, `updated_at`)) = MONTH(CURRENT_DATE())
               AND YEAR(COALESCE(`paid_at`, `updated_at`)) = YEAR(CURRENT_DATE())"
        );
        $revenueThisMonth = (int) $revMonthStmt->fetchColumn();

        $revLastMonthStmt = $this->pdo->query(
            "SELECT COALESCE(SUM(`paid_amount`), 0) FROM `player_payments`
             WHERE `status` IN ('PAID', 'PARTIALLY_PAID')
               AND `paid_amount` > 0
               AND (
                   (MONTH(CURRENT_DATE()) > 1 AND MONTH(COALESCE(`paid_at`, `updated_at`)) = MONTH(CURRENT_DATE()) - 1 AND YEAR(COALESCE(`paid_at`, `updated_at`)) = YEAR(CURRENT_DATE()))
                   OR (MONTH(CURRENT_DATE()) = 1 AND MONTH(COALESCE(`paid_at`, `updated_at`)) = 12 AND YEAR(COALESCE(`paid_at`, `updated_at`)) = YEAR(CURRENT_DATE()) - 1)
               )"
        );
        $revenueLastMonth = (int) $revLastMonthStmt->fetchColumn();

        $revenueGrowth = 0.0;
        if ($revenueLastMonth > 0) {
            $revenueGrowth = round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1);
        } elseif ($revenueThisMonth > 0) {
            $revenueGrowth = 100.0;
        }

        // 2. Ca chơi hoạt động hôm nay
        $sessTodayStmt = $this->pdo->query(
            "SELECT COUNT(*) FROM `sessions`
             WHERE `status` != 'CANCELLED'
               AND DATE(`start_at`) = CURRENT_DATE()"
        );
        $sessionsTodayCount = (int) $sessTodayStmt->fetchColumn();

        // 3. Yêu cầu hoàn cọc chờ duyệt
        $pendingRefundsStmt = $this->pdo->query("SELECT COUNT(*) FROM `refunds` WHERE `status` = 'PENDING'");
        $pendingRefundsCount = (int) $pendingRefundsStmt->fetchColumn();

        // 4. Thẻ HSSV chờ xác minh
        $pendingStudentsStmt = $this->pdo->query("SELECT COUNT(*) FROM `users` WHERE `student_card_status` = 'PENDING'");
        $pendingStudentsCount = (int) $pendingStudentsStmt->fetchColumn();

        // 5. Phòng chơi đang hoạt động gom người / chốt lịch
        $activeRoomsStmt = $this->pdo->query(
            "SELECT COUNT(*) FROM `game_rooms` WHERE `status` IN ('FORMING', 'SCHEDULING', 'VOTING', 'DEPOSITING', 'CONFIRMED')"
        );
        $activeRoomsCount = (int) $activeRoomsStmt->fetchColumn();

        // 6. Biểu đồ doanh thu 7 ngày gần nhất (từ H-6 đến hôm nay)
        $chartData = [];
        $dayNames = [
            1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'
        ];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dayOfWeek = (int) date('N', strtotime($date));
            $label = $dayNames[$dayOfWeek] ?? 'T' . $dayOfWeek;

            $dStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(`paid_amount`), 0) FROM `player_payments`
                 WHERE `status` IN ('PAID', 'PARTIALLY_PAID')
                   AND `paid_amount` > 0
                   AND DATE(COALESCE(`paid_at`, `updated_at`)) = :target_date"
            );
            $dStmt->execute([':target_date' => $date]);
            $amount = (int) $dStmt->fetchColumn();

            $chartData[] = [
                'date' => $date,
                'label' => $label,
                'amount' => $amount,
                'is_today' => ($i === 0),
            ];
        }

        // 7. Thống kê phòng chơi theo trạng thái
        $roomStatsStmt = $this->pdo->query(
            "SELECT `status`, COUNT(*) AS `count` FROM `game_rooms` GROUP BY `status`"
        );
        $roomsByStatus = [];
        while ($row = $roomStatsStmt->fetch(PDO::FETCH_ASSOC)) {
            $roomsByStatus[$row['status']] = (int) $row['count'];
        }

        // 8. Tỷ lệ lấp đầy (Occupancy Rate)
        $occStmt = $this->pdo->query(
            "SELECT 
                COALESCE(SUM(gr.`max_players`), 0) AS `total_capacity`,
                (SELECT COUNT(*) FROM `room_participants` rp
                 INNER JOIN `game_rooms` gr2 ON gr2.`id` = rp.`game_room_id`
                 WHERE gr2.`status` IN ('FORMING', 'SCHEDULING', 'VOTING', 'DEPOSITING', 'CONFIRMED')
                   AND rp.`status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')) AS `active_players`
             FROM `game_rooms` gr
             WHERE gr.`status` IN ('FORMING', 'SCHEDULING', 'VOTING', 'DEPOSITING', 'CONFIRMED')"
        );
        $occData = $occStmt->fetch(PDO::FETCH_ASSOC);
        $totalCap = (int) ($occData['total_capacity'] ?? 0);
        $activePlayers = (int) ($occData['active_players'] ?? 0);
        $occupancyRate = $totalCap > 0 ? round(($activePlayers / $totalCap) * 100, 1) : 0.0;

        return Response::success([
            'overview' => [
                'revenue_month' => $revenueThisMonth,
                'revenue_last_month' => $revenueLastMonth,
                'revenue_growth_pct' => $revenueGrowth,
                'sessions_today' => $sessionsTodayCount,
                'pending_refunds' => $pendingRefundsCount,
                'pending_students' => $pendingStudentsCount,
                'active_rooms' => $activeRoomsCount,
                'occupancy_rate' => $occupancyRate,
            ],
            'revenue_chart_7d' => $chartData,
            'rooms_by_status' => $roomsByStatus,
        ], 'Số liệu tổng quan vận hành hệ thống');
    }
}
