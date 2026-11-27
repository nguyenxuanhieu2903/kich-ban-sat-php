<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Infrastructure\Database\DatabaseConnection;
use PDO;
use Throwable;

class AuditLogService
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getInstance();
    }

    /**
     * Record an audit event safely (never breaks primary business flow).
     *
     * @param array<string, mixed> $metadata
     */
    public function log(
        ?int $actorId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
        ?string $ipAddress = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `audit_logs` (`actor_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `created_at`)
                 VALUES (:actor_id, :user_id, :action, :entity_type, :entity_id, :metadata, :ip_address, NOW())'
            );
            $stmt->bindValue(':actor_id', $actorId, $actorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $actorId, $actorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $entityId, $entityId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':metadata', !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null, !empty($metadata) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindValue(':ip_address', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable) {
            // Do not fail business request if audit logging fails
        }
    }

    /**
     * List audit logs with rich actor info, Vietnamese action/entity labels, and human-readable change summary.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listLogs(
        int $page = 1,
        int $perPage = 30,
        ?string $action = null,
        ?string $entityType = null,
        ?string $search = null
    ): array {
        $where = ['1 = 1'];
        $params = [];

        if ($action !== null && trim($action) !== '') {
            $where[] = 'al.`action` = :action';
            $params[':action'] = trim($action);
        }

        if ($entityType !== null && trim($entityType) !== '') {
            $where[] = 'UPPER(al.`entity_type`) = UPPER(:entity_type)';
            $params[':entity_type'] = trim($entityType);
        }

        if ($search !== null && trim($search) !== '') {
            $kw = '%' . trim($search) . '%';
            $where[] = '(al.`action` LIKE :kw1 OR al.`entity_type` LIKE :kw2 OR CAST(al.`metadata` AS CHAR) LIKE :kw3 OR u.`full_name` LIKE :kw4 OR u.`email` LIKE :kw5)';
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
            $params[':kw4'] = $kw;
            $params[':kw5'] = $kw;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `audit_logs` al
             LEFT JOIN `users` u ON u.`id` = COALESCE(al.`actor_id`, al.`user_id`)
             WHERE {$whereSql}"
        );
        foreach ($params as $k => $v) {
            $cntStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $cntStmt->execute();
        $total = (int) $cntStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            "SELECT al.`id`, COALESCE(al.`actor_id`, al.`user_id`) AS `actor_id`,
                    u.`full_name` AS `actor_name`, u.`email` AS `actor_email`,
                    r.`code` AS `actor_role`,
                    al.`action`, al.`entity_type`, al.`entity_id`, al.`metadata`, al.`ip_address`, al.`created_at`
             FROM `audit_logs` al
             LEFT JOIN `users` u ON u.`id` = COALESCE(al.`actor_id`, al.`user_id`)
             LEFT JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE {$whereSql}
             ORDER BY al.`id` DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nạp bản đồ tên DM/User & Phòng vật lý & Kịch bản để hiển thị tên thật trong chi tiết nhật ký
        $userNames = $this->pdo->query('SELECT `id`, `full_name` FROM `users`')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $prNames = $this->pdo->query('SELECT `id`, `name` FROM `physical_rooms`')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $gameNames = $this->pdo->query('SELECT `id`, `name` FROM `games`')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        foreach ($items as &$row) {
            $meta = [];
            if (!empty($row['metadata']) && is_string($row['metadata'])) {
                $decoded = json_decode($row['metadata'], true);
                $meta = is_array($decoded) ? $decoded : [];
            } elseif (is_array($row['metadata'])) {
                $meta = $row['metadata'];
            }
            $row['metadata'] = $meta;
            $row['action_label'] = $this->translateAction((string) ($row['action'] ?? ''));
            $row['entity_label'] = $this->translateEntity(
                (string) ($row['entity_type'] ?? ''),
                isset($row['entity_id']) ? (int) $row['entity_id'] : null,
                $gameNames,
                $prNames,
                $userNames
            );
            $row['detail_summary'] = $this->buildDetailSummary(
                (string) ($row['action'] ?? ''),
                $meta,
                $userNames,
                $prNames,
                $gameNames
            );
        }
        unset($row);

        return ['items' => $items, 'total' => $total];
    }

    protected function translateAction(string $action): string
    {
        return match (strtoupper($action)) {
            'ASSIGN_DM' => 'Phân công Quản trò',
            'REVOKE_DM' => 'Thu hồi ca Quản trò',
            'ACCEPT_DM_ASSIGNMENT' => 'Quản trò chấp nhận ca trực',
            'REJECT_DM_ASSIGNMENT' => 'Quản trò từ chối ca trực',
            'ASSIGN_VENUE' => 'Gán phòng vật lý & chi nhánh',
            'START_SESSION' => 'Bắt đầu buổi chơi',
            'FINISH_GAMEPLAY' => 'Kết thúc buổi chơi (chờ tất toán)',
            'COMPLETE_SESSION' => 'Hoàn tất ca chơi',
            'SETTLE_SESSION', 'SETTLE_PAYMENT_AT_COUNTER' => 'Tất toán tại quầy',
            'ADD_SESSION_NOTES', 'SESSION_NOTES_UPDATED' => 'Ghi chú / báo cáo sự cố ca chơi',
            'REPORT_NO_SHOW', 'NO_SHOW_SINGLE_PLAYER' => 'Ghi nhận khách vắng mặt',
            'POST_GAME_REVIEW' => 'Đánh giá sau buổi chơi',
            'ADMIN_UPDATE_GAME' => 'Cập nhật kịch bản / bảng giá',
            'ADMIN_CREATE_GAME' => 'Thêm kịch bản mới',
            'ADMIN_TOGGLE_PROMOTION' => 'Bật / Tắt chương trình khuyến mãi',
            'ADMIN_CREATE_VENUE' => 'Thêm chi nhánh mới',
            'ADMIN_CREATE_PHYSICAL_ROOM' => 'Thêm phòng vật lý mới',
            'SET_PHYSICAL_ROOM_MAINTENANCE' => 'Bật bảo trì phòng vật lý',
            'CLEAR_PHYSICAL_ROOM_MAINTENANCE' => 'Tắt bảo trì phòng vật lý',
            'OFFLINE_ROOM_BLOCK_CREATED' => 'Khóa phòng đặt qua điện thoại / trực tiếp',
            'VERIFY_STUDENT', 'STUDENT_VERIFICATION_UPDATED', 'UPDATE_STUDENT_VERIFICATION' => 'Xét duyệt thẻ HSSV',
            'APPROVE_REFUND' => 'Phê duyệt yêu cầu hoàn tiền',
            'REJECT_REFUND' => 'Từ chối yêu cầu hoàn tiền',
            'COMPLETE_REFUND' => 'Hoàn tất chuyển khoản hoàn tiền',
            'DISBAND_ROOM', 'ADMIN_CANCEL_ROOM' => 'Giải tán / Hủy phòng chơi',
            'UPDATE_USER_ROLE' => 'Thay đổi vai trò tài khoản',
            'UPDATE_USER_STATUS' => 'Khóa / Mở khóa tài khoản',
            default => $action,
        };
    }

    /**
     * @param array<int|string, string> $gameNames
     * @param array<int|string, string> $prNames
     * @param array<int|string, string> $userNames
     */
    protected function translateEntity(
        string $entityType,
        ?int $entityId,
        array $gameNames,
        array $prNames,
        array $userNames
    ): string {
        $t = strtoupper(trim($entityType));
        $idSuffix = $entityId !== null ? " #{$entityId}" : '';
        return match ($t) {
            'GAME_ROOM', 'ROOM' => "Phòng chơi{$idSuffix}",
            'DM_ASSIGNMENT' => "Phân công Quản trò{$idSuffix}",
            'SESSION' => "Phiên chơi{$idSuffix}",
            'GAME' => 'Kịch bản' . $idSuffix . ($entityId !== null && isset($gameNames[$entityId]) ? " ({$gameNames[$entityId]})" : ''),
            'PHYSICAL_ROOM' => 'Phòng vật lý' . $idSuffix . ($entityId !== null && isset($prNames[$entityId]) ? " ({$prNames[$entityId]})" : ''),
            'PROMOTION' => "Khuyến mãi{$idSuffix}",
            'VENUE' => "Chi nhánh{$idSuffix}",
            'REFUND', 'REFUND_REQUEST' => "Yêu cầu hoàn tiền{$idSuffix}",
            'USER' => 'Tài khoản' . $idSuffix . ($entityId !== null && isset($userNames[$entityId]) ? " ({$userNames[$entityId]})" : ''),
            'PAYMENT' => "Giao dịch thanh toán{$idSuffix}",
            default => "{$entityType}{$idSuffix}",
        };
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<int|string, string> $userNames
     * @param array<int|string, string> $prNames
     * @param array<int|string, string> $gameNames
     */
    protected function buildDetailSummary(
        string $action,
        array $meta,
        array $userNames,
        array $prNames,
        array $gameNames
    ): string {
        if (empty($meta)) {
            return 'Không có dữ liệu bổ sung.';
        }

        $statusMap = [
            'FORMING' => 'Đang tuyển người',
            'SCHEDULED' => 'Đã xếp lịch (chờ Quản trò)',
            'CONFIRMED' => 'Đã chốt ca',
            'IN_PROGRESS' => 'Đang diễn ra',
            'COMPLETED' => 'Đã hoàn tất',
            'CANCELLED' => 'Đã hủy',
            'ACTIVE' => 'Đang hiển thị / Hoạt động',
            'INACTIVE' => 'Đang tạm ẩn',
            'SUSPENDED' => 'Đã khóa tài khoản',
        ];

        $parts = [];
        if (!empty($meta['game_room_id'])) {
            $parts[] = 'Phòng #' . (int) $meta['game_room_id'];
        }
        if (!empty($meta['dm_user_id'])) {
            $did = (int) $meta['dm_user_id'];
            $parts[] = 'Quản trò: ' . ($userNames[$did] ?? ('DM #' . $did));
        }
        if (!empty($meta['physical_room_id'])) {
            $pid = (int) $meta['physical_room_id'];
            $parts[] = 'Phòng vật lý: ' . ($prNames[$pid] ?? ('#' . $pid));
        }
        if (!empty($meta['room_name'])) {
            $parts[] = 'Phòng vật lý: ' . (string) $meta['room_name'];
        }
        if (!empty($meta['name']) && strtoupper($action) !== 'SET_PHYSICAL_ROOM_MAINTENANCE') {
            $parts[] = 'Tên: ' . (string) $meta['name'];
        }
        if (!empty($meta['room_status'])) {
            $st = strtoupper((string) $meta['room_status']);
            $parts[] = 'Trạng thái phòng: ' . ($statusMap[$st] ?? $st);
        }
        if (isset($meta['status']) && $meta['status'] !== '') {
            $st = strtoupper((string) $meta['status']);
            $parts[] = 'Trạng thái mới: ' . ($statusMap[$st] ?? $st);
        }
        if (isset($meta['day_price'])) {
            $parts[] = 'Giá ngày: ' . number_format((int) $meta['day_price'], 0, ',', '.') . ' đ';
        }
        if (isset($meta['night_price'])) {
            $parts[] = 'Giá tối: ' . number_format((int) $meta['night_price'], 0, ',', '.') . ' đ';
        }
        if (isset($meta['is_active'])) {
            $parts[] = !empty($meta['is_active']) ? 'Trạng thái: Đang bật' : 'Trạng thái: Tạm tắt';
        }
        if (isset($meta['is_maintenance'])) {
            $parts[] = !empty($meta['is_maintenance']) ? 'Bật bảo trì' : 'Hoàn tất bảo trì';
        }
        if (isset($meta['student_verified'])) {
            $parts[] = !empty($meta['student_verified']) ? 'Kết quả: Đã duyệt thẻ HSSV' : 'Kết quả: Hủy duyệt HSSV';
        }
        if (!empty($meta['amount'])) {
            $parts[] = 'Số tiền: ' . number_format((int) $meta['amount'], 0, ',', '.') . ' đ';
        }
        if (!empty($meta['bank_reference_code'])) {
            $parts[] = 'Mã CK: ' . (string) $meta['bank_reference_code'];
        }
        if (!empty($meta['role_code']) || !empty($meta['new_role'])) {
            $rc = strtoupper((string) ($meta['role_code'] ?? $meta['new_role']));
            $roleMap = ['ADMIN' => 'Quản trị viên', 'DM' => 'Quản trò', 'PLAYER' => 'Người chơi'];
            $parts[] = 'Vai trò mới: ' . ($roleMap[$rc] ?? $rc);
        }
        if (!empty($meta['start_at']) && !empty($meta['end_at'])) {
            $parts[] = 'Khung giờ: ' . $meta['start_at'] . ' -> ' . $meta['end_at'];
        }
        if (!empty($meta['reason'])) {
            $parts[] = 'Lý do: ' . (string) $meta['reason'];
        }
        if (!empty($meta['notes'])) {
            $parts[] = 'Ghi chú: ' . (string) $meta['notes'];
        }
        if (!empty($meta['rerouted_sessions']) && is_array($meta['rerouted_sessions'])) {
            $parts[] = 'Điều chuyển tự động ' . count($meta['rerouted_sessions']) . ' ca chơi';
        }

        if (empty($parts)) {
            return json_encode($meta, JSON_UNESCAPED_UNICODE) ?: 'Đã ghi nhận thao tác.';
        }
        return implode(' • ', $parts);
    }
}
