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
                    g.`name` AS `game_name`, COALESCE(g.`duration_minutes`, 240) AS `duration_minutes`
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
        $endDateTime = null;
        if ($playDateTime === null || trim($playDateTime) === '') {
            // Thử lấy từ session hoặc confirmed proposal
            $sessStmt = $this->pdo->prepare('SELECT `start_at`, `end_at` FROM `sessions` WHERE `game_room_id` = :room_id LIMIT 1');
            $sessStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
            $sessStmt->execute();
            $sessRow = $sessStmt->fetch(PDO::FETCH_ASSOC);

            if ($sessRow !== false && !empty($sessRow['start_at'])) {
                $playDateTime = (string) $sessRow['start_at'];
                $endDateTime = !empty($sessRow['end_at']) ? (string) $sessRow['end_at'] : null;
            } else {
                $propStmt = $this->pdo->prepare('SELECT `proposed_start_at`, `proposed_end_at` FROM `schedule_proposals` WHERE `game_room_id` = :room_id AND `status` = \'CONFIRMED\' LIMIT 1');
                $propStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                $propStmt->execute();
                $propRow = $propStmt->fetch(PDO::FETCH_ASSOC);

                if ($propRow !== false && !empty($propRow['proposed_start_at'])) {
                    $playDateTime = (string) $propRow['proposed_start_at'];
                    $endDateTime = !empty($propRow['proposed_end_at']) ? (string) $propRow['proposed_end_at'] : null;
                } else {
                    $playDateTime = date('Y-m-d 18:00:00');
                }
            }
        }

        // 4. Tính toán giá gốc áp dụng Majority Time Block Rule và Chia đều 50-50
        $durationMinutes = !empty($room['duration_minutes']) ? (int) $room['duration_minutes'] : 240;
        $priceCalc = $this->calculateBasePriceForGame(
            (int) $room['game_id'],
            $playDateTime,
            $endDateTime,
            $durationMinutes
        );

        $basePricePerPerson = $priceCalc['base_price_per_person'];
        $pricingRuleApplied = $priceCalc['pricing_rule'];
        $endDateTime = $priceCalc['end_datetime'];
        $durationMinutes = $priceCalc['duration_minutes'];
        $tierOverlaps = $priceCalc['tier_overlaps'];

        $timeOfDay = date('H:i:s', strtotime($playDateTime));
        $dayOfWeek = (int) date('N', strtotime($playDateTime)); // 1 (Mon) -> 7 (Sun)
        $subtotal = $basePricePerPerson * $totalParticipants;

        // Tiền cọc: Cố định 50.000 VNĐ / người
        $depositPerPerson = self::DEPOSIT_PER_PERSON;
        $depositTotal = $depositPerPerson * $totalParticipants;

        // 5. Xử lý khuyến mãi (Không cộng dồn - Tự động chọn mức có lợi nhất nếu không truyền mã cụ thể)
        $discountAmount = 0;
        $appliedPromotion = null;
        $promoId = null;
        $appliedRequiresStudent = false;
        $appliedStudentDiscountPerPerson = 0;
        $verifiedUserIds = [];

        if (!empty($participants)) {
            $userIds = array_map(fn($p) => (int) $p['user_id'], $participants);
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stuStmt = $this->pdo->prepare("SELECT `id` FROM `users` WHERE `id` IN ($placeholders) AND `student_verified` = 1");
            $stuStmt->execute($userIds);
            $verifiedUserIds = array_map('intval', $stuStmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $candidatePromos = [];
        if ($promotionCode !== null && trim($promotionCode) !== '') {
            $codeUpper = strtoupper(trim($promotionCode));
            if ($codeUpper === 'AUTO' || $codeUpper === 'BEST') {
                $allPromoStmt = $this->pdo->query(
                    'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `rules`, `start_at`, `end_at`
                     FROM `promotions`
                     WHERE `is_active` = 1
                     ORDER BY `id` ASC'
                );
                $candidatePromos = $allPromoStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $promoStmt = $this->pdo->prepare(
                    'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `rules`, `start_at`, `end_at`
                     FROM `promotions`
                     WHERE (`code` = :code OR `code` LIKE :code_like) AND `is_active` = 1
                     LIMIT 1'
                );
                $promoStmt->bindValue(':code', $codeUpper, PDO::PARAM_STR);
                $promoStmt->bindValue(':code_like', $codeUpper . '%', PDO::PARAM_STR);
                $promoStmt->execute();
                $row = $promoStmt->fetch(PDO::FETCH_ASSOC);
                if ($row !== false) {
                    $candidatePromos[] = $row;
                }
            }
        }

        foreach ($candidatePromos as $promo) {
            $rules = !empty($promo['rules']) ? json_decode((string) $promo['rules'], true) : [];
            $isEligible = true;

            if (!empty($promo['start_at']) && !empty($promo['end_at'])) {
                $playTs = strtotime($playDateTime);
                $startTs = strtotime((string) $promo['start_at']);
                $endTs = strtotime((string) $promo['end_at']);
                if ($playTs < $startTs || $playTs > $endTs) {
                    $isEligible = false;
                }
            }

            if ($isEligible && !empty($rules['weekdays']) && is_array($rules['weekdays'])) {
                if (!in_array($dayOfWeek, $rules['weekdays'], true)) {
                    $isEligible = false;
                }
            }

            if ($isEligible && !empty($rules['valid_from']) && !empty($rules['valid_until'])) {
                if ($timeOfDay < $rules['valid_from'] || $timeOfDay > $rules['valid_until']) {
                    $isEligible = false;
                }
            }

            $eligibleParticipantsCount = $totalParticipants;
            $requiresStudent = !empty($rules['requires_student_verification']);
            if ($isEligible && $requiresStudent) {
                $eligibleParticipantsCount = count($verifiedUserIds);
                if ($eligibleParticipantsCount === 0) {
                    $isEligible = false;
                }
            }

            if (!$isEligible) {
                continue;
            }

            $candDiscount = 0;
            $candPerStudent = 0;
            if ($promo['discount_type'] === 'PERCENT') {
                $percent = (int) $promo['discount_value'];
                $candDiscount = (int) round(($subtotal * $percent) / 100);
            } elseif ($promo['discount_type'] === 'FIXED') {
                $candPerStudent = (int) ($rules['discount_per_eligible_person'] ?? $promo['discount_value']);
                $candDiscount = $candPerStudent * $eligibleParticipantsCount;
            }

            if ($candDiscount > $discountAmount) {
                $discountAmount = $candDiscount;
                $promoId = (int) $promo['id'];
                $appliedRequiresStudent = $requiresStudent;
                $appliedStudentDiscountPerPerson = $candPerStudent;
                $appliedPromotion = [
                    'id' => $promoId,
                    'code' => $promo['code'],
                    'name' => $promo['name'],
                    'discount_type' => $promo['discount_type'],
                    'discount_value' => (int) $promo['discount_value'],
                    'eligible_players' => $eligibleParticipantsCount,
                ];
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
            'end_datetime' => $endDateTime,
            'duration_minutes' => $durationMinutes,
            'time_of_day' => $timeOfDay,
            'pricing_rule' => $pricingRuleApplied,
            'tier_overlaps' => $tierOverlaps,
            'is_peak_hours' => strtotime($timeOfDay) >= strtotime('18:00:00'),
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
                    $indivDiscount = $perPlayerDiscount;
                    if ($appliedRequiresStudent) {
                        $isVerifiedStu = in_array((int) $part['user_id'], $verifiedUserIds, true);
                        $indivDiscount = $isVerifiedStu ? min($appliedStudentDiscountPerPerson, max(0, $basePricePerPerson - $depositPerPerson)) : 0;
                    }
                    $indivFinal = max($depositPerPerson, $basePricePerPerson - $indivDiscount);
                    $indivRemaining = max(0, $indivFinal - $depositPerPerson);

                    $snapStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
                    $snapStmt->bindValue(':part_id', (int) $part['id'], PDO::PARAM_INT);
                    $snapStmt->bindValue(':base_price', $basePricePerPerson, PDO::PARAM_INT);
                    $snapStmt->bindValue(':discount', $indivDiscount, PDO::PARAM_INT);
                    $snapStmt->bindValue(':final_amount', $indivFinal, PDO::PARAM_INT);
                    $snapStmt->bindValue(':deposit', $depositPerPerson, PDO::PARAM_INT);
                    $snapStmt->bindValue(':remaining', $indivRemaining, PDO::PARAM_INT);
                    $snapStmt->bindValue(':promo_id', $promoId, $promoId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $snapStmt->execute();
                }
            }

            $this->pdo->commit();

            return [
                'game_room_id' => $roomId,
                'game_name' => $room['game_name'],
                'play_datetime' => $playDateTime,
                'end_datetime' => $endDateTime,
                'duration_minutes' => $durationMinutes,
                'pricing_rule' => $pricingRuleApplied,
                'tier_overlaps' => $tierOverlaps,
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

    /**
     * Tính toán giá vé cơ bản của kịch bản theo Majority Time Block Rule và Chia đều 50-50.
     *
     * @return array<string, mixed>
     */
    public function calculateBasePriceForGame(
        int $gameId,
        string $startDateTime,
        ?string $endDateTime = null,
        int $defaultDurationMinutes = 240
    ): array {
        $startTs = strtotime($startDateTime);
        if ($startTs === false) {
            $startTs = time();
            $startDateTime = date('Y-m-d H:i:s', $startTs);
        }

        if ($endDateTime !== null && trim($endDateTime) !== '') {
            $endTs = strtotime($endDateTime);
            if ($endTs > $startTs) {
                $durationMinutes = (int) round(($endTs - $startTs) / 60);
            } else {
                $durationMinutes = $defaultDurationMinutes > 0 ? $defaultDurationMinutes : 240;
                $endTs = $startTs + ($durationMinutes * 60);
                $endDateTime = date('Y-m-d H:i:s', $endTs);
            }
        } else {
            $durationMinutes = $defaultDurationMinutes > 0 ? $defaultDurationMinutes : 240;
            $endTs = $startTs + ($durationMinutes * 60);
            $endDateTime = date('Y-m-d H:i:s', $endTs);
        }

        $startDateStr = date('Y-m-d', $startTs);
        $endDateStr = date('Y-m-d', $endTs);

        $priceStmt = $this->pdo->prepare(
            'SELECT `id`, `price_per_person`, `start_time`, `end_time`
             FROM `game_prices`
             WHERE `game_id` = :game_id AND `status` = \'ACTIVE\'
               AND (`effective_until` IS NULL OR `effective_until` >= :d1)
               AND `effective_from` <= :d2
             ORDER BY `start_time` ASC'
        );
        $priceStmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $priceStmt->bindValue(':d1', $startDateStr, PDO::PARAM_STR);
        $priceStmt->bindValue(':d2', $endDateStr, PDO::PARAM_STR);
        $priceStmt->execute();
        $tiers = $priceStmt->fetchAll(PDO::FETCH_ASSOC);

        $tierOverlaps = [];
        $candidateDates = array_unique([$startDateStr, $endDateStr]);

        foreach ($tiers as $tier) {
            $tStart = (string) $tier['start_time'];
            $tEnd = (string) $tier['end_time'];
            $overlapSec = 0;

            foreach ($candidateDates as $cDate) {
                $t1 = strtotime("$cDate $tStart");
                $t2 = strtotime("$cDate $tEnd");
                if ($t2 > $t1) {
                    $oStart = max($startTs, $t1);
                    $oEnd = min($endTs, $t2);
                    if ($oEnd > $oStart) {
                        $overlapSec += ($oEnd - $oStart);
                    }
                }
            }

            $overlapMin = (int) round($overlapSec / 60);
            if ($overlapMin > 0) {
                $pct = round(($overlapMin / max(1, $durationMinutes)) * 100, 1);
                $tierOverlaps[] = [
                    'id' => (int) $tier['id'],
                    'price_per_person' => (int) $tier['price_per_person'],
                    'start_time' => $tStart,
                    'end_time' => $tEnd,
                    'overlap_minutes' => $overlapMin,
                    'overlap_percentage' => $pct,
                ];
            }
        }

        if (empty($tierOverlaps)) {
            // Fallback nếu không có cấu hình chi tiết theo khung giờ
            $fbStmt = $this->pdo->prepare('SELECT `price_per_person` FROM `game_prices` WHERE `game_id` = :game_id AND `status` = \'ACTIVE\' LIMIT 1');
            $fbStmt->bindValue(':game_id', $gameId, PDO::PARAM_INT);
            $fbStmt->execute();
            $fbPrice = $fbStmt->fetchColumn();

            $basePrice = ($fbPrice !== false && $fbPrice !== null) ? (int) $fbPrice : 250000;
            $pricingRule = 'DEFAULT_FALLBACK';
        } elseif (count($tierOverlaps) === 1) {
            $basePrice = $tierOverlaps[0]['price_per_person'];
            $pricingRule = 'SINGLE_TIER';
        } else {
            // Sắp xếp giảm dần theo số phút giao thoa
            usort($tierOverlaps, fn($a, $b) => $b['overlap_minutes'] <=> $a['overlap_minutes']);

            // Kiểm tra trường hợp 50-50 tie giữa 2 khung giờ dẫn đầu
            if ($tierOverlaps[0]['overlap_minutes'] === $tierOverlaps[1]['overlap_minutes']) {
                $basePrice = (int) round(($tierOverlaps[0]['price_per_person'] + $tierOverlaps[1]['price_per_person']) / 2);
                $pricingRule = 'SPLIT_50_50_AVERAGE';
            } else {
                $basePrice = $tierOverlaps[0]['price_per_person'];
                $pricingRule = 'MAJORITY_TIME_BLOCK';
            }
        }

        return [
            'base_price_per_person' => $basePrice,
            'pricing_rule' => $pricingRule,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'duration_minutes' => $durationMinutes,
            'tier_overlaps' => $tierOverlaps,
        ];
    }

    /**
     * Tra đơn giá chuẩn theo GIỜ BẮT ĐẦU ca chơi trong bảng `game_prices`
     * (Khung ban ngày 09:00–19:59, Khung buổi tối từ 20:00 — bám sát menu quán).
     *
     * @return array<string, mixed>
     */
    public function resolveTierPriceByStartTime(int $gameId, string $startDateTime): array
    {
        $ts = strtotime(str_replace('T', ' ', $startDateTime));
        if ($ts === false) {
            $ts = time();
        }
        $playDate = date('Y-m-d', $ts);
        $timeOfDay = date('H:i:s', $ts);
        $minutesOfDay = ((int) date('G', $ts)) * 60 + (int) date('i', $ts);
        $isNight = $minutesOfDay >= 20 * 60;

        $stmt = $this->pdo->prepare(
            'SELECT `id`, `price_per_person`, `start_time`, `end_time`
             FROM `game_prices`
             WHERE `game_id` = :gid AND `status` = \'ACTIVE\'
               AND (`effective_until` IS NULL OR `effective_until` >= :d1)
               AND `effective_from` <= :d2
             ORDER BY `start_time` ASC'
        );
        $stmt->execute([':gid' => $gameId, ':d1' => $playDate, ':d2' => $playDate]);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matched = null;
        foreach ($tiers as $t) {
            if ($timeOfDay >= (string) $t['start_time'] && $timeOfDay <= (string) $t['end_time']) {
                $matched = $t;
                break;
            }
        }

        if ($matched === null && !empty($tiers)) {
            foreach ($tiers as $t) {
                $tierIsNight = ((string) $t['start_time']) >= '20:00:00';
                if ($tierIsNight === $isNight) {
                    $matched = $t;
                    break;
                }
            }
            if ($matched === null) {
                $matched = $tiers[0];
            }
        }

        if ($matched === null) {
            $fb = $this->pdo->prepare('SELECT `price_per_person`, `start_time`, `end_time` FROM `game_prices` WHERE `game_id` = :gid AND `status` = \'ACTIVE\' ORDER BY `start_time` ASC LIMIT 1');
            $fb->execute([':gid' => $gameId]);
            $row = $fb->fetch(PDO::FETCH_ASSOC);
            $price = $row !== false ? (int) $row['price_per_person'] : 199000;
        } else {
            $price = (int) $matched['price_per_person'];
        }

        return [
            'base_price_per_person' => $price,
            'is_night' => $isNight,
            'time_slot' => $isNight ? 'NIGHT' : 'DAY',
            'time_window' => $isNight ? 'Khung tối (từ 20:00)' : 'Khung ngày (09:00–19:59)',
            'play_datetime' => date('Y-m-d H:i:s', $ts),
            'timestamp' => $ts,
        ];
    }

    /**
     * Hàm tính báo giá chuẩn duy nhất (Single Source of Truth) cho toàn hệ thống:
     * - Đơn giá tra theo giờ bắt đầu từ `game_prices` (09:00–19:59 vs từ 20:00).
     * - Ưu đãi đọc trực tiếp từ bảng `promotions` đang bật (`is_active = 1`), không cộng dồn, tự chọn mức có lợi nhất.
     * - Ưu đãi HSSV áp dụng theo số thẻ HSSV đã được duyệt (mặc định 1 thẻ của chủ tài khoản).
     * - Tiền cọc cố định 50.000đ/người (không vượt quá tổng sau giảm).
     *
     * @return array<string, mixed>
     */
    public function calculateQuote(
        int $gameId,
        int $playerCount,
        string $startDateTime,
        bool $isStudentVerified = false,
        int $verifiedStudentCount = 0
    ): array {
        $playerCount = max(1, $playerCount);
        $tier = $this->resolveTierPriceByStartTime($gameId, $startDateTime);
        $ts = (int) $tier['timestamp'];
        $playDateTime = (string) $tier['play_datetime'];
        $basePrice = (int) $tier['base_price_per_person'];
        $subtotal = $basePrice * $playerCount;

        $timeOfDay = date('H:i:s', $ts);
        $dayOfWeek = (int) date('N', $ts); // 1=Mon..7=Sun
        $advanceHours = ($ts - time()) / 3600.0;

        $promoStmt = $this->pdo->prepare(
            'SELECT `id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `rules`, `start_at`, `end_at`
             FROM `promotions`
             WHERE `is_active` = 1
               AND (`start_at` IS NULL OR `start_at` <= :dt1)
               AND (`end_at` IS NULL OR `end_at` >= :dt2)
             ORDER BY `id` ASC'
        );
        $promoStmt->execute([':dt1' => $playDateTime, ':dt2' => $playDateTime]);
        $activePromos = $promoStmt->fetchAll(PDO::FETCH_ASSOC);

        $discountAmount = 0;
        $discountCode = null;
        $discountLabel = 'Không có ưu đãi';
        $appliedPromoId = null;
        $promotionNote = null;
        $eligibleCandidates = [];

        foreach ($activePromos as $promo) {
            $rules = !empty($promo['rules']) ? (json_decode((string) $promo['rules'], true) ?: []) : [];

            if (!empty($rules['weekdays']) && is_array($rules['weekdays'])) {
                $allowedDays = array_map('intval', $rules['weekdays']);
                if (!in_array($dayOfWeek, $allowedDays, true)) {
                    continue;
                }
            }

            if (!empty($rules['valid_from']) && !empty($rules['valid_until'])) {
                if ($timeOfDay < (string) $rules['valid_from'] || $timeOfDay > (string) $rules['valid_until']) {
                    continue;
                }
            }

            if (!empty($rules['minimum_booking_hours'])) {
                $minHours = (float) $rules['minimum_booking_hours'];
                if ($advanceHours < $minHours) {
                    if ($promotionNote === null && strtoupper((string) $promo['code']) === 'TUESDAY_30') {
                        $promotionNote = 'Ưu đãi Thứ Ba (-30%) yêu cầu đặt trước ít nhất 24 giờ trong khung 09:00–19:00.';
                    }
                    continue;
                }
            }

            $requiresStudent = !empty($rules['requires_student_verification']);
            $eligibleCount = $playerCount;
            if ($requiresStudent) {
                if (!$isStudentVerified) {
                    continue;
                }
                $stuCount = $verifiedStudentCount > 0 ? $verifiedStudentCount : 1;
                $eligibleCount = max(1, min($playerCount, $stuCount));
            }

            $candDiscount = 0;
            $dType = strtoupper((string) ($promo['discount_type'] ?? ''));
            if ($dType === 'PERCENT') {
                $pct = (float) ($promo['discount_value'] ?? 0);
                $candDiscount = (int) round($subtotal * ($pct / 100.0));
            } elseif ($dType === 'FIXED') {
                $perPerson = (int) ($rules['discount_per_eligible_person'] ?? $promo['discount_value'] ?? 0);
                $candDiscount = $perPerson * $eligibleCount;
            }

            if ($candDiscount > 0) {
                $eligibleCandidates[] = [
                    'id' => (int) $promo['id'],
                    'code' => (string) $promo['code'],
                    'name' => (string) $promo['name'],
                    'discount' => $candDiscount,
                    'requires_student' => $requiresStudent,
                    'eligible_count' => $eligibleCount,
                ];
            }
        }

        if (!empty($eligibleCandidates)) {
            usort($eligibleCandidates, fn ($a, $b) => $b['discount'] <=> $a['discount']);
            $best = $eligibleCandidates[0];
            $discountAmount = min($subtotal, (int) $best['discount']);
            $discountCode = $best['code'];
            $discountLabel = $best['name'];
            $appliedPromoId = $best['id'];

            if (count($eligibleCandidates) > 1) {
                $promotionNote = 'Không cộng dồn ưu đãi — hệ thống tự động chọn mức giảm cao nhất cho bạn.';
            } elseif (!empty($best['requires_student']) && $playerCount > (int) $best['eligible_count']) {
                $promotionNote = 'Đã áp dụng ưu đãi HSSV cho 01 tài khoản đã duyệt thẻ; thành viên đi cùng có thẻ HSSV sẽ được DM giảm thêm 20.000đ/người tại quầy.';
            }
        }

        $finalTotal = max(0, $subtotal - $discountAmount);
        $depositTotal = min($finalTotal, self::DEPOSIT_PER_PERSON * $playerCount);
        $remainingTotal = max(0, $finalTotal - $depositTotal);

        return [
            'time_slot' => $tier['time_slot'],
            'time_window' => $tier['time_window'],
            'play_datetime' => $playDateTime,
            'player_count' => $playerCount,
            'base_price_per_player' => $basePrice,
            'raw_total_vnd' => $subtotal,
            'subtotal' => $subtotal,
            'total_discount_vnd' => $discountAmount,
            'discount_amount' => $discountAmount,
            'discount_code' => $discountCode,
            'promotion_id' => $appliedPromoId,
            'applied_promotion' => ['id' => $appliedPromoId, 'code' => $discountCode, 'name' => $discountLabel],
            'promotion_note' => $promotionNote,
            'final_total_vnd' => $finalTotal,
            'total_amount' => $finalTotal,
            'total_deposit_vnd' => $depositTotal,
            'deposit_amount' => $depositTotal,
            'remaining_vnd' => $remainingTotal,
            'remaining_amount' => $remainingTotal,
        ];
    }
}
