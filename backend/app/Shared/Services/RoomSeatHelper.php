<?php

declare(strict_types=1);

namespace App\Shared\Services;

use PDO;

/**
 * Helper chuẩn hóa cách tính số chỗ (seats) của từng thành viên và tổng sĩ số phòng chơi.
 * Lưu bản đồ `participant_seats` trong cột JSON `game_rooms.price_details` (không cần thay đổi schema CSDL),
 * đồng thời xử lý an toàn dữ liệu mẫu cũ bị lệch `total_players` để không bao giờ bị cộng đúp sĩ số.
 */
final class RoomSeatHelper
{
    /**
     * Đọc bản đồ số chỗ theo user_id từ cột `price_details` (JSON).
     *
     * @return array<int, int>
     */
    public static function parseSeatMap(?string $priceDetailsJson): array
    {
        if ($priceDetailsJson === null || trim($priceDetailsJson) === '') {
            return [];
        }
        $decoded = json_decode($priceDetailsJson, true);
        if (!is_array($decoded) || !isset($decoded['participant_seats']) || !is_array($decoded['participant_seats'])) {
            return [];
        }
        $map = [];
        foreach ($decoded['participant_seats'] as $uid => $seats) {
            $u = (int) $uid;
            $s = (int) $seats;
            if ($u > 0 && $s >= 1) {
                $map[$u] = $s;
            }
        }
        return $map;
    }

    /**
     * Tính số chỗ của từng thành viên đang hoạt động trong phòng (không bị cộng đúp).
     *
     * @param array<string, mixed> $room       Bắt buộc có creator_id, total_players, max_players, tùy chọn price_details
     * @param list<int>            $activeUids Danh sách user_id đang hoạt động (PENDING_DEPOSIT / DEPOSITED / LEAVE_REQUESTED)
     * @return array{seats_by_user: array<int, int>, occupied_seats: int}
     */
    public static function computeSeats(array $room, array $activeUids): array
    {
        $uniqueUids = array_values(array_unique(array_filter(array_map('intval', $activeUids), static fn (int $u): bool => $u > 0)));
        if (empty($uniqueUids)) {
            return ['seats_by_user' => [], 'occupied_seats' => 0];
        }

        $creatorId = (int) ($room['creator_id'] ?? 0);
        $maxPlayers = max(1, (int) ($room['max_players'] ?? 8));
        $rawTotalPlayers = max(1, (int) ($room['total_players'] ?? 1));
        $seatMap = self::parseSeatMap(isset($room['price_details']) ? (string) $room['price_details'] : null);

        $activeCount = count($uniqueUids);
        $creatorIn = in_array($creatorId, $uniqueUids, true);

        // Phát hiện phòng dữ liệu mẫu cũ: total_players được seed bằng tổng sĩ số cả phòng (vd 6)
        // trong khi room_participants cũng đã có nhiều dòng thành viên riêng lẻ -> tránh cộng đúp 6 + 5 = 11!
        $creatorHasExplicitMap = isset($seatMap[$creatorId]);
        $creatorLegacySeats = 1;
        if (!$creatorHasExplicitMap && $creatorIn) {
            $otherCount = max(0, $activeCount - 1);
            if ($otherCount === 0) {
                $creatorLegacySeats = min($maxPlayers, $rawTotalPlayers);
            } elseif (($rawTotalPlayers + $otherCount) <= $maxPlayers) {
                $creatorLegacySeats = $rawTotalPlayers;
            } else {
                $creatorLegacySeats = 1;
            }
        }

        $seatsByUser = [];
        $sum = 0;
        foreach ($uniqueUids as $uid) {
            if (isset($seatMap[$uid])) {
                $s = max(1, min($maxPlayers, $seatMap[$uid]));
            } elseif ($uid === $creatorId) {
                $s = $creatorLegacySeats;
            } else {
                $s = 1;
            }
            $seatsByUser[$uid] = $s;
            $sum += $s;
        }

        return [
            'seats_by_user' => $seatsByUser,
            'occupied_seats' => min($maxPlayers, $sum),
        ];
    }

    /**
     * Lấy số chỗ của một thành viên cụ thể trong phòng.
     *
     * @param array<string, mixed> $room
     * @param list<int>            $activeUids
     */
    public static function getSeatsForUser(array $room, array $activeUids, int $userId): int
    {
        $res = self::computeSeats($room, $activeUids);
        return $res['seats_by_user'][$userId] ?? 1;
    }

    /**
     * Lưu (hoặc xóa khi $seats <= 0) số chỗ của một thành viên vào `game_rooms.price_details`.
     */
    public static function saveUserSeats(PDO $pdo, int $roomId, int $userId, int $seats): void
    {
        $stmt = $pdo->prepare('SELECT `price_details` FROM `game_rooms` WHERE `id` = :rid LIMIT 1');
        $stmt->execute([':rid' => $roomId]);
        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }
        if (!isset($decoded['participant_seats']) || !is_array($decoded['participant_seats'])) {
            $decoded['participant_seats'] = [];
        }

        if ($seats <= 0) {
            unset($decoded['participant_seats'][(string) $userId]);
        } else {
            $decoded['participant_seats'][(string) $userId] = $seats;
        }

        $upd = $pdo->prepare('UPDATE `game_rooms` SET `price_details` = :pd, `updated_at` = NOW() WHERE `id` = :rid');
        $upd->execute([
            ':pd' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            ':rid' => $roomId,
        ]);
    }
}
