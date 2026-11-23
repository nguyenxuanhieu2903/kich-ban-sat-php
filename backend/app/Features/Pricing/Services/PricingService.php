<?php

declare(strict_types=1);

namespace App\Features\Pricing\Services;

use App\Infrastructure\Database\DatabaseConnection;
use InvalidArgumentException;
use PDO;

class PricingService
{
    public const DEPOSIT_PER_PERSON = 50000; // Quy chuẩn cố định 50.000 VNĐ / người của V16

    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * Calculate and snapshot room pricing according to V16 rules.
     *
     * @return array<string, mixed>
     */
    public function calculateAndSnapshot(
        int $roomId,
        ?string $playTime = null,
        ?string $promotionCode = null
    ): array {
        // 1. Lấy thông tin phòng và game
        $rStmt = $this->pdo->prepare(
            'SELECT gr.`id`, gr.`game_id`, gr.`title`, gr.`min_players`, gr.`max_players`, gr.`status`,
                    g.`name` AS `game_name`
             FROM `game_rooms` gr
             INNER JOIN `games` g ON g.`id` = gr.`game_id`
             WHERE gr.`id` = :room_id
             LIMIT 1'
        );
        $rStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $rStmt->execute();
        $room = $rStmt->fetch(PDO::FETCH_ASSOC);

        if ($room === false) {
            throw new InvalidArgumentException('Phòng chơi không tồn tại');
        }

        // 2. Lấy danh sách thành viên active
        $pStmt = $this->pdo->prepare(
            'SELECT `id`, `user_id` FROM `room_participants`
             WHERE `game_room_id` = :room_id
               AND `status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')'
        );
        $pStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $pStmt->execute();
        $participants = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalParticipants = count($participants);
        if ($totalParticipants === 0) {
            $totalParticipants = (int) $room['min_players'];
        }

        // 3. Xác định mốc thời gian chơi
        $playDateTime = $playTime;
        if ($playDateTime === null || trim($playDateTime) === '') {
            // Thử lấy từ session hoặc confirmed proposal
            $sessStmt = $this->pdo->prepare('SELECT `start_at` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
            $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $sessStmt->execute();
            $sessStart = $sessStmt->fetchColumn();

            if ($sessStart !== false && $sessStart !== null) {
                $playDateTime = (string) $sessStart;
            } else {
                $propStmt = $this->pdo->prepare('SELECT `proposed_start_at` FROM `schedule_proposals` WHERE `game_room_id` = :room_id AND `status` = \'CONFIRMED\' LIMIT 1');
                $propStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $propStmt->execute();
                $propStart = $propStmt->fetchColumn();
                $playDateTime = $propStart !== false && $propStart !== null ? (string) $propStart : date('Y-m-d 18:00:00');
            }
        }

        $timeOfDay = date('H:i:s', strtotime($playDateTime));
        $dayOfWeek = (int) date('N', strtotime($playDateTime)); // 1 (Mon) -> 7 (Sun)

        // 4. Tìm bảng giá game_prices theo khung giờ (09:00 - 19:59 vs từ 20:00)
        $priceStmt = $this->pdo->prepare(
            'SELECT `price_per_person`, `start_time`, `end_time`
             FROM `game_prices`
             WHERE `game_id` = :game_id AND `status` = \'ACTIVE\'
               AND :t1 >= `start_time` AND :t2 <= `end_time`
             LIMIT 1'
        );
        $priceStmt->bindValue(':game_id', (int) $room['game_id'], PDO::PARAM_INT);
        $priceStmt->bindValue(':t1', $timeOfDay, PDO::PARAM_STR);
        $priceStmt->bindValue(':t2', $timeOfDay, PDO::PARAM_STR);
        $priceStmt->execute();
        $priceRecord = $priceStmt->fetch(PDO::FETCH_ASSOC);

        $basePricePerPerson = $priceRecord !== false ? (int) $priceRecord['price_per_person'] : 250000;
        $subtotal = $basePricePerPerson * $totalParticipants;

        // Tiền cọc: Cố định 50.000 VNĐ / người
        $depositPerPerson = self::DEPOSIT_PER_PERSON;
        $depositTotal = $depositPerPerson * $totalParticipants;

        // 5. Xử lý khuyến mãi
        $discountAmount = 0;
        $appliedPromotion = null;
        $promoId = null;

        if ($promotionCode !== null && trim($promotionCode) !== '') {
            $codeUpper = strtoupper(trim($promotionCode));
            $promoStmt = $this->pdo->prepare(
                'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `rules`, `start_at`, `end_at`
                 FROM `promotions`
                 WHERE (`code` = :code OR `code` LIKE :code_like) AND `is_active` = 1
                 LIMIT 1'
            );
            $promoStmt->bindValue(':code', $codeUpper, PDO::PARAM_STR);
            $promoStmt->bindValue(':code_like', $codeUpper . '%', PDO::PARAM_STR);
            $promoStmt->execute();
            $promo = $promoStmt->fetch(PDO::FETCH_ASSOC);

            if ($promo !== false) {
                $rules = !empty($promo['rules']) ? json_decode((string) $promo['rules'], true) : [];
                $isEligible = true;

                // Kiểm tra thời hạn hiệu lực của chương trình khuyến mãi (start_at -> end_at)
                if (!empty($promo['start_at']) && !empty($promo['end_at'])) {
                    $playTs = strtotime($playDateTime);
                    $startTs = strtotime((string) $promo['start_at']);
                    $endTs = strtotime((string) $promo['end_at']);
                    if ($playTs < $startTs || $playTs > $endTs) {
                        $isEligible = false;
                    }
                }

                // Kiểm tra ngày trong tuần nếu có rule
                if (!empty($rules['weekdays']) && is_array($rules['weekdays'])) {
                    if (!in_array($dayOfWeek, $rules['weekdays'], true)) {
                        $isEligible = false; // Ví dụ mã Thứ 3 mà đặt ngày khác
                    }
                }

                // Kiểm tra khung giờ hợp lệ nếu có rule
                if ($isEligible && !empty($rules['valid_from']) && !empty($rules['valid_until'])) {
                    if ($timeOfDay < $rules['valid_from'] || $timeOfDay > $rules['valid_until']) {
                        $isEligible = false;
                    }
                }

                // Kiểm tra xác thực học sinh / sinh viên nếu rule yêu cầu
                $eligibleParticipantsCount = $totalParticipants;
                $verifiedUserIds = [];
                if ($isEligible && !empty($rules['requires_student_verification'])) {
                    if (!empty($participants)) {
                        $userIds = array_map(fn($p) => (int) $p['user_id'], $participants);
                        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                        $stuStmt = $this->pdo->prepare("SELECT `id` FROM `users` WHERE `id` IN ($placeholders) AND `student_verified` = 1");
                        $stuStmt->execute($userIds);
                        $verifiedUserIds = array_map('intval', $stuStmt->fetchAll(PDO::FETCH_COLUMN));
                        $eligibleParticipantsCount = count($verifiedUserIds);
                    } else {
                        $eligibleParticipantsCount = 0;
                    }

                    if ($eligibleParticipantsCount === 0) {
                        $isEligible = false;
                    }
                }

                if ($isEligible) {
                    $promoId = (int) $promo['id'];
                    $appliedPromotion = [
                        'id' => $promoId,
                        'code' => $promo['code'],
                        'name' => $promo['name'],
                        'discount_type' => $promo['discount_type'],
                        'discount_value' => (int) $promo['discount_value'],
                        'eligible_players' => $eligibleParticipantsCount,
                    ];

                    if ($promo['discount_type'] === 'PERCENT') {
                        $percent = (int) $promo['discount_value'];
                        $discountAmount = (int) round(($subtotal * $percent) / 100);
                    } elseif ($promo['discount_type'] === 'FIXED') {
                        $perPersonDiscount = (int) ($rules['discount_per_eligible_person'] ?? $promo['discount_value']);
                        $discountAmount = $perPersonDiscount * $eligibleParticipantsCount;
                    }
                }
            }
        }

        // Không cho discount vượt quá tiền còn lại sau cọc (luôn phải cọc đủ 50k/người)
        $maxDiscountAllowed = max(0, $subtotal - $depositTotal);
        $discountAmount = min($discountAmount, $maxDiscountAllowed);

        $finalAmount = max($depositTotal, $subtotal - $discountAmount);
        $remainingTotal = max(0, $finalAmount - $depositTotal);

        $perPlayerFinal = (int) round($finalAmount / $totalParticipants);
        $perPlayerRemaining = (int) round($remainingTotal / $totalParticipants);
        $perPlayerDiscount = (int) round($discountAmount / $totalParticipants);

        $detailsJson = json_encode([
            'room_id' => $roomId,
            'game_id' => (int) $room['game_id'],
            'game_name' => $room['game_name'],
            'play_datetime' => $playDateTime,
            'time_of_day' => $timeOfDay,
            'is_peak_hours' => strtotime($timeOfDay) >= strtotime('20:00:00'),
            'base_price_per_person' => $basePricePerPerson,
            'deposit_per_person' => $depositPerPerson,
            'per_player_discount' => $perPlayerDiscount,
            'per_player_final' => $perPlayerFinal,
            'per_player_remaining' => $perPlayerRemaining,
            'applied_promotion' => $appliedPromotion,
            'calculated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->pdo->beginTransaction();
        try {
            // Cập nhật snapshot vào game_rooms
            $upRoom = $this->pdo->prepare(
                'UPDATE `game_rooms`
                 SET `price_total_participants` = :total_parts,
                     `price_subtotal` = :subtotal,
                     `price_discount_amount` = :discount,
                     `price_final_amount` = :final_amount,
                     `price_deposit_total` = :deposit_total,
                     `price_remaining_total` = :remaining_total,
                     `price_promotion_id` = :promo_id,
                     `price_details` = :details,
                     `price_calculated_at` = NOW()
                 WHERE `id` = :room_id'
            );
            $upRoom->bindValue(':total_parts', $totalParticipants, PDO::PARAM_INT);
            $upRoom->bindValue(':subtotal', $subtotal, PDO::PARAM_INT);
            $upRoom->bindValue(':discount', $discountAmount, PDO::PARAM_INT);
            $upRoom->bindValue(':final_amount', $finalAmount, PDO::PARAM_INT);
            $upRoom->bindValue(':deposit_total', $depositTotal, PDO::PARAM_INT);
            $upRoom->bindValue(':remaining_total', $remainingTotal, PDO::PARAM_INT);
            $upRoom->bindValue(':promo_id', $promoId, $promoId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $upRoom->bindValue(':details', $detailsJson, PDO::PARAM_STR);
            $upRoom->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $upRoom->execute();

            // Cập nhật snapshot cho từng participant
            if (!empty($participants)) {
                $snapStmt = $this->pdo->prepare(
                    'INSERT INTO `participant_price_snapshots`
                        (`game_room_id`, `room_participant_id`, `base_price`, `discount_amount`, `final_amount`, `deposit_amount`, `remaining_amount`, `promotion_id`, `created_at`)
                     VALUES
                        (:room_id, :part_id, :base_price, :discount, :final_amount, :deposit, :remaining, :promo_id, NOW())
                     ON DUPLICATE KEY UPDATE
                        `base_price` = VALUES(`base_price`),
                        `discount_amount` = VALUES(`discount_amount`),
                        `final_amount` = VALUES(`final_amount`),
                        `deposit_amount` = VALUES(`deposit_amount`),
                        `remaining_amount` = VALUES(`remaining_amount`),
                        `promotion_id` = VALUES(`promotion_id`)'
                );

                foreach ($participants as $part) {
                    $snapStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $snapStmt->bindValue(':part_id', (int) $part['id'], PDO::PARAM_INT);
                    $snapStmt->bindValue(':base_price', $basePricePerPerson, PDO::PARAM_INT);
                    $snapStmt->bindValue(':discount', $perPlayerDiscount, PDO::PARAM_INT);
                    $snapStmt->bindValue(':final_amount', $perPlayerFinal, PDO::PARAM_INT);
                    $snapStmt->bindValue(':deposit', $depositPerPerson, PDO::PARAM_INT);
                    $snapStmt->bindValue(':remaining', $perPlayerRemaining, PDO::PARAM_INT);
                    $snapStmt->bindValue(':promo_id', $promoId, $promoId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $snapStmt->execute();
                }
            }

            $this->pdo->commit();

            return [
                'game_room_id' => $roomId,
                'game_name' => $room['game_name'],
                'play_datetime' => $playDateTime,
                'total_participants' => $totalParticipants,
                'base_price_per_person' => $basePricePerPerson,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'deposit_per_person' => $depositPerPerson,
                'deposit_total' => $depositTotal,
                'remaining_total' => $remainingTotal,
                'per_player_summary' => [
                    'base_price' => $basePricePerPerson,
                    'discount' => $perPlayerDiscount,
                    'deposit_required' => $depositPerPerson,
                    'remaining_due' => $perPlayerRemaining,
                    'total_per_player' => $perPlayerFinal,
                ],
                'applied_promotion' => $appliedPromotion,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
