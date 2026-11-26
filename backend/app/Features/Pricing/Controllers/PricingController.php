<?php

declare(strict_types=1);

namespace App\Features\Pricing\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Pricing\Services\PricingService;
use App\Infrastructure\Database\DatabaseConnection;
use PDO;

class PricingController
{
    protected PricingService $pricingService;
    protected PDO $pdo;

    public function __construct(?PricingService $pricingService = null, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
        $this->pricingService = $pricingService ?? new PricingService($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * POST /api/v1/rooms/{id}/calculate-price
     * Body: play_time (datetime string, optional), promotion_code (string, optional)
     */
    public function quote(Request $request): Response
    {
        $body = $request->getBodyParams();
        $gameId = (int) ($body['game_id'] ?? 0);
        $playerCount = max(1, (int) ($body['player_count'] ?? $body['total_players'] ?? 1));
        $startRaw = trim((string) ($body['session_start_time'] ?? $body['play_time'] ?? ''));
        $verifiedStudentCount = isset($body['verified_student_count']) ? (int) $body['verified_student_count'] : 0;

        if (array_key_exists('is_student_verified', $body)) {
            $isStudentVerified = !empty($body['is_student_verified']);
        } else {
            $auth = AuthMiddleware::authenticate($request);
            $isStudentVerified = !($auth instanceof Response) && !empty($auth['student_verified']);
        }

        if ($gameId <= 0) {
            return Response::fail('Thiếu mã kịch bản (game_id)', 'VALIDATION_ERROR', 422);
        }

        $gStmt = $this->pdo->prepare('SELECT `id`, `name`, `min_players`, `max_players`, COALESCE(`duration_minutes`, 240) AS `duration_minutes` FROM `games` WHERE `id` = :gid LIMIT 1');
        $gStmt->execute([':gid' => $gameId]);
        $game = $gStmt->fetch(PDO::FETCH_ASSOC);
        if ($game === false) {
            return Response::fail('Không tìm thấy kịch bản', 'GAME_NOT_FOUND', 404);
        }

        $quote = $this->pricingService->calculateQuote(
            $gameId,
            $playerCount,
            $startRaw !== '' ? $startRaw : date('Y-m-d H:i:s'),
            $isStudentVerified,
            $verifiedStudentCount
        );

        return Response::success(array_merge([
            'game_id' => $gameId,
            'game_name' => $game['name'],
        ], $quote), 'Tính toán báo giá dự kiến thành công');
    }

    public function calculate(Request $request, string $id): Response
    {
        $roomId = (int) $id;
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $body = $request->getBodyParams();

        $playTime = isset($body['play_time']) ? trim((string) $body['play_time']) : null;
        $promotionCode = isset($body['promotion_code']) ? trim((string) $body['promotion_code']) : null;

        try {
            $calculation = $this->pricingService->calculateAndSnapshot($roomId, $playTime, $promotionCode);
            return Response::success($calculation, 'Tính toán bảng giá và chốt snapshot thành công');
        } catch (\InvalidArgumentException $e) {
            return Response::fail($e->getMessage(), 'NOT_FOUND', 404);
        } catch (\Throwable $e) {
            return Response::fail($e->getMessage(), 'CALCULATION_ERROR', 500);
        }
    }

    /**
     * GET /api/v1/rooms/{id}/price-snapshot
     */
    public function snapshot(Request $request, string $id): Response
    {
        $auth = AuthMiddleware::authenticate($request);
        if ($auth instanceof Response) {
            return $auth;
        }

        $roomId = (int) $id;
        $stmt = $this->pdo->prepare(
            'SELECT gr.`id`, gr.`price_total_participants`, gr.`price_subtotal`, gr.`price_discount_amount`,
                    gr.`price_final_amount`, gr.`price_deposit_total`, gr.`price_remaining_total`,
                    gr.`price_promotion_id`, gr.`price_details`, gr.`price_calculated_at`,
                    p.`code` AS `promotion_code`, p.`name` AS `promotion_name`
             FROM `game_rooms` gr
             LEFT JOIN `promotions` p ON p.`id` = gr.`price_promotion_id`
             WHERE gr.`id` = :room_id
             LIMIT 1'
        );
        $stmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false || empty($room['price_calculated_at'])) {
            return Response::fail('Phòng chơi chưa có snapshot giá hoặc không tồn tại', 'SNAPSHOT_NOT_FOUND', 404);
        }

        if (!empty($room['price_details'])) {
            $room['price_details'] = json_decode((string) $room['price_details'], true);
        }

        return Response::success([
            'snapshot' => $room,
        ], 'Thông tin snapshot bảng giá phòng chơi');
    }
}
