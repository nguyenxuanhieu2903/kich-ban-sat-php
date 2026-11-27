<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;
use App\Shared\Services\RoomSeatHelper;
use PDO;

class AuthController
{
    protected UserRepository $userRepo;
    protected PDO $pdo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->pdo = DatabaseConnection::getInstance();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * GET /api/v1/auth/csrf-token
     */
    public function csrfToken(Request $request): Response
    {
        $token = CsrfMiddleware::getToken();
        return Response::success(['csrf_token' => $token], 'CSRF token retrieved successfully');
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request): Response
    {
        $body = $request->getBodyParams();

        $fullName = trim((string) ($body['full_name'] ?? $body['name'] ?? ''));
        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $phone = isset($body['phone']) ? trim((string) $body['phone']) : null;

        $errors = [];
        if ($fullName === '') {
            $errors['full_name'] = ['Họ và tên không được để trống'];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Email không đúng định dạng'];
        }

        if (strlen($password) < 6) {
            $errors['password'] = ['Mật khẩu phải có ít nhất 6 ký tự'];
        }

        if (!empty($errors)) {
            return Response::fail('Dữ liệu không hợp lệ', 'VALIDATION_ERROR', 422, $errors);
        }

        // Check duplicate email
        $existing = $this->userRepo->findByEmail($email);
        if ($existing !== null) {
            return Response::fail('Email này đã được sử dụng trên hệ thống', 'EMAIL_ALREADY_EXISTS', 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $userId = $this->userRepo->create([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'phone' => $phone,
            'status' => 'ACTIVE',
        ]);

        $user = $this->userRepo->findById($userId);
        if ($user !== null) {
            unset($user['password_hash']);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['role_id'] = $user['role_id'] ?? 1;
        $_SESSION['role_code'] = $user['role_code'] ?? 'PLAYER';

        return Response::success([
            'user' => $user,
        ], 'Đăng ký tài khoản thành công', null, 201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): Response
    {
        $body = $request->getBodyParams();

        $rawIdentifier = trim((string) ($body['email'] ?? $body['identifier'] ?? $body['phone'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($rawIdentifier === '' || $password === '') {
            return Response::fail('Vui lòng nhập đầy đủ email hoặc số điện thoại và mật khẩu', 'VALIDATION_ERROR', 422);
        }

        if (str_contains($rawIdentifier, '@')) {
            $user = $this->userRepo->findByEmail(strtolower($rawIdentifier));
        } else {
            $user = $this->userRepo->findByPhone($rawIdentifier) ?? $this->userRepo->findByEmail(strtolower($rawIdentifier));
        }
        if ($user === null) {
            return Response::fail('Tài khoản hoặc mật khẩu không chính xác', 'INVALID_CREDENTIALS', 401);
        }

        if (($user['status'] ?? '') !== 'ACTIVE') {
            return Response::fail('Tài khoản của bạn đang bị tạm khóa hoặc chưa kích hoạt', 'ACCOUNT_INACTIVE', 403);
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return Response::fail('Tài khoản hoặc mật khẩu không chính xác', 'INVALID_CREDENTIALS', 401);
        }

        // Update last login
        $this->userRepo->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) ($user['role_id'] ?? 1);
        $_SESSION['role_code'] = (string) ($user['role_code'] ?? 'PLAYER');

        unset($user['password_hash']);
        $user['role'] = $user['role_code'] ?? 'PLAYER';

        return Response::success([
            'user' => $user,
        ], 'Đăng nhập thành công');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        unset($authUser['password_hash']);
        $authUser['role'] = $authUser['role_code'] ?? 'PLAYER';

        return Response::success([
            'user' => $authUser,
        ], 'Thông tin tài khoản');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): Response
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        return Response::success(null, 'Đăng xuất thành công');
    }

    /**
     * PATCH /api/v1/auth/profile (UC-P01: Quản lý hồ sơ cá nhân)
     */
    public function updateProfile(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        $body = $request->getBodyParams();
        $fields = [];
        if (isset($body['full_name'])) {
            $fullName = trim((string) $body['full_name']);
            if ($fullName === '') {
                return Response::fail('Họ và tên không được để trống', 'VALIDATION_ERROR', 422);
            }
            $fields['full_name'] = $fullName;
        }

        if (isset($body['phone'])) {
            $fields['phone'] = trim((string) $body['phone']);
        }

        if (empty($fields)) {
            return Response::fail('Không có dữ liệu cập nhật', 'BAD_REQUEST', 400);
        }

        $this->userRepo->update((int) $authUser['id'], $fields);
        $updatedUser = $this->userRepo->findById((int) $authUser['id']);
        if ($updatedUser !== null) {
            unset($updatedUser['password_hash']);
        }

        return Response::success(['user' => $updatedUser], 'Cập nhật thông tin cá nhân thành công');
    }

    /**
     * POST /api/v1/auth/change-password | POST /api/v1/me/password
     */
    public function changePassword(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        $body = $request->getBodyParams();
        $currentPassword = (string) ($body['current_password'] ?? $body['old_password'] ?? '');
        $newPassword = (string) ($body['new_password'] ?? $body['password'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            return Response::fail('Vui lòng nhập mật khẩu hiện tại và mật khẩu mới', 'VALIDATION_ERROR', 422);
        }

        if (strlen($newPassword) < 6) {
            return Response::fail('Mật khẩu mới phải có ít nhất 6 ký tự', 'VALIDATION_ERROR', 422);
        }

        $fullUser = $this->userRepo->findById((int) $authUser['id']);
        if ($fullUser === null || !password_verify($currentPassword, (string) ($fullUser['password_hash'] ?? ''))) {
            return Response::fail('Mật khẩu hiện tại không chính xác', 'INVALID_PASSWORD', 400);
        }

        $this->userRepo->update((int) $authUser['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        return Response::success(['changed' => true], 'Đổi mật khẩu thành công');
    }

    /**
     * POST /api/v1/auth/student-verification | POST /api/v1/me/student-card (UC-P02)
     * Hỗ trợ cả JSON (student_id, school_name) lẫn multipart/form-data (student_card)
     */
    public function requestStudentVerification(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        $actorRole = strtoupper((string) ($authUser['role_code'] ?? $authUser['role'] ?? 'PLAYER'));
        if (in_array($actorRole, ['DM', 'ADMIN'], true)) {
            return Response::fail('Chỉ tài khoản Người chơi (Player) mới sử dụng tính năng xác minh thẻ HSSV', 'FORBIDDEN_ROLE', 403);
        }

        $body = $request->getBodyParams();
        $studentId = trim((string) ($body['student_id'] ?? $body['student_card_number'] ?? $body['student_card_id'] ?? ''));
        $schoolName = trim((string) ($body['school_name'] ?? $body['university'] ?? ''));
        $uploadedFileName = '';

        if (!empty($_FILES['student_card']) && is_array($_FILES['student_card']) && ($_FILES['student_card']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['student_card'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return Response::fail('Lỗi khi tải lên tệp thẻ HSSV', 'UPLOAD_ERROR', 400);
            }
            if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                return Response::fail('Dung lượng ảnh thẻ HSSV tối đa 5MB', 'FILE_TOO_LARGE', 422);
            }
            $tmpPath = (string) ($file['tmp_name'] ?? '');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo && $tmpPath !== '' ? (string) finfo_file($finfo, $tmpPath) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                return Response::fail('Chỉ chấp nhận ảnh định dạng JPG, PNG hoặc WEBP', 'INVALID_FILE_TYPE', 422);
            }
            $uploadDir = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_cards';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $uploadedFileName = 'hssv_' . (int) $authUser['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $uploadedFileName;
            if (!@move_uploaded_file($tmpPath, $destPath) && !@copy($tmpPath, $destPath)) {
                return Response::fail('Không thể lưu tệp ảnh thẻ HSSV', 'UPLOAD_SAVE_FAILED', 500);
            }
            if ($studentId === '') {
                $studentId = 'CARD_UPLOAD_' . (int) $authUser['id'];
            }
        }

        if ($studentId === '') {
            return Response::fail('Mã số sinh viên hoặc ảnh thẻ HSSV không được để trống', 'VALIDATION_ERROR', 422);
        }

        $cardUrl = $uploadedFileName !== '' ? ('/storage/uploads/student_cards/' . $uploadedFileName) : null;
        $upCard = $this->pdo->prepare(
            "UPDATE `users`
             SET `student_verified` = 0,
                 `student_card_status` = 'PENDING',
                 `student_card_url` = COALESCE(:url, `student_card_url`),
                 `updated_at` = NOW()
             WHERE `id` = :uid"
        );
        $upCard->bindValue(':url', $cardUrl, $cardUrl === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $upCard->bindValue(':uid', (int) $authUser['id'], PDO::PARAM_INT);
        $upCard->execute();

        // Ghi nhận nhật ký kiểm toán và bắn thông báo cho Quản trị viên duyệt thẻ HSSV
        try {
            $audit = new \App\Shared\Services\AuditLogService($this->pdo);
            $audit->log((int) $authUser['id'], 'STUDENT_VERIFICATION_REQUESTED', 'USER', (int) $authUser['id'], [
                'student_id' => $studentId,
                'school_name' => $schoolName,
                'file_name' => $uploadedFileName,
            ]);

            $adminStmt = $this->pdo->query('SELECT u.`id` FROM `users` u INNER JOIN `roles` r ON r.`id` = u.`role_id` WHERE r.`code` = \'ADMIN\' AND u.`status` = \'ACTIVE\'');
            $adminIds = $adminStmt ? array_map('intval', $adminStmt->fetchAll(PDO::FETCH_COLUMN)) : [];
            if (!empty($adminIds)) {
                $notiRepo = new \App\Features\Notification\Repositories\NotificationRepository($this->pdo);
                $userName = (string) ($authUser['full_name'] ?? $authUser['email'] ?? ('User #' . $authUser['id']));
                $schoolSuffix = $schoolName !== '' ? " - Trường: {$schoolName}" : '';
                $notiRepo->createNotification(
                    'Yêu cầu duyệt thẻ HSSV mới',
                    "Người chơi {$userName} gửi yêu cầu xác minh thẻ HSSV (Mã thẻ: {$studentId}{$schoolSuffix}).",
                    'ADMIN',
                    (int) $authUser['id'],
                    $adminIds
                );
            }
        } catch (\Throwable) {
            // Không làm gián đoạn luồng chính nếu gửi thông báo phụ trợ lỗi
        }

        return Response::success([
            'user_id' => (int) $authUser['id'],
            'student_id' => $studentId,
            'school_name' => $schoolName,
            'student_verified' => false,
            'student_card' => [
                'status' => 'pending',
                'file_name' => $uploadedFileName,
            ],
            'status' => 'PENDING_APPROVAL',
        ], 'Yêu cầu xác thực sinh viên đã được gửi tới quản trị viên');
    }

    /**
     * GET /api/v1/auth/my-rooms
     * Lấy toàn bộ lịch sử phòng chơi và tình trạng cọc / hoàn tiền của người chơi
     */
    public function myRooms(Request $request): Response
    {
        $authUser = AuthMiddleware::authenticate($request);
        if ($authUser instanceof Response) {
            return $authUser;
        }

        $userId = (int) $authUser['id'];

        $sql = 'SELECT 
                    gr.`id` AS `room_id`,
                    gr.`creator_id`,
                    gr.`title` AS `room_title`,
                    gr.`status` AS `room_status`,
                    gr.`booking_type`,
                    gr.`min_players`,
                    gr.`max_players`,
                    gr.`total_players`,
                    g.`id` AS `game_id`,
                    g.`name` AS `game_name`,
                    g.`image_url` AS `game_image`,
                    g.`duration_minutes`,
                    rp.`id` AS `participant_id`,
                    rp.`status` AS `participant_status`,
                    rp.`joined_at`,
                    (SELECT COUNT(*) FROM `room_participants` p2 
                     WHERE p2.`game_room_id` = gr.`id` 
                       AND p2.`status` IN (\'PENDING_DEPOSIT\', \'DEPOSITED\', \'LEAVE_REQUESTED\')
                    ) AS `current_players`,
                    (SELECT pp.`id` FROM `player_payments` pp WHERE pp.`room_participant_id` = rp.`id` ORDER BY pp.`id` DESC LIMIT 1) AS `payment_id`,
                    (SELECT COALESCE(SUM(CASE WHEN pp.`status` != \'CANCELLED\' THEN pp.`paid_amount` ELSE 0 END), 0) FROM `player_payments` pp WHERE pp.`room_participant_id` = rp.`id`) AS `paid_amount`,
                    (SELECT pp.`status` FROM `player_payments` pp WHERE pp.`room_participant_id` = rp.`id` ORDER BY pp.`id` DESC LIMIT 1) AS `payment_status`,
                    (SELECT ref.`id` FROM `refunds` ref 
                     INNER JOIN `player_payments` pp2 ON pp2.`id` = ref.`player_payment_id` 
                     WHERE pp2.`room_participant_id` = rp.`id` ORDER BY ref.`id` DESC LIMIT 1
                    ) AS `refund_id`,
                    (SELECT ref.`status` FROM `refunds` ref 
                     INNER JOIN `player_payments` pp2 ON pp2.`id` = ref.`player_payment_id` 
                     WHERE pp2.`room_participant_id` = rp.`id` ORDER BY ref.`id` DESC LIMIT 1
                    ) AS `refund_status`,
                    (SELECT ref.`amount` FROM `refunds` ref 
                     INNER JOIN `player_payments` pp2 ON pp2.`id` = ref.`player_payment_id` 
                     WHERE pp2.`room_participant_id` = rp.`id` ORDER BY ref.`id` DESC LIMIT 1
                    ) AS `refund_amount`,
                    (SELECT ref.`reason` FROM `refunds` ref 
                     INNER JOIN `player_payments` pp2 ON pp2.`id` = ref.`player_payment_id` 
                     WHERE pp2.`room_participant_id` = rp.`id` ORDER BY ref.`id` DESC LIMIT 1
                    ) AS `refund_reason`,
                    (SELECT ref.`review_reason` FROM `refunds` ref 
                     INNER JOIN `player_payments` pp2 ON pp2.`id` = ref.`player_payment_id` 
                     WHERE pp2.`room_participant_id` = rp.`id` ORDER BY ref.`id` DESC LIMIT 1
                    ) AS `refund_review_reason`,
                    gr.`price_details`,
                    (SELECT s.`id` FROM `sessions` s WHERE s.`game_room_id` = gr.`id` ORDER BY s.`id` DESC LIMIT 1) AS `session_id`,
                    (SELECT s.`status` FROM `sessions` s WHERE s.`game_room_id` = gr.`id` ORDER BY s.`id` DESC LIMIT 1) AS `session_status`,
                    (SELECT s.`start_at` FROM `sessions` s WHERE s.`game_room_id` = gr.`id` ORDER BY s.`id` DESC LIMIT 1) AS `session_start_at`,
                    (SELECT s.`end_at` FROM `sessions` s WHERE s.`game_room_id` = gr.`id` ORDER BY s.`id` DESC LIMIT 1) AS `session_end_at`,
                    (SELECT pr.`name` FROM `sessions` s2 INNER JOIN `physical_rooms` pr ON pr.`id` = s2.`physical_room_id` WHERE s2.`game_room_id` = gr.`id` ORDER BY s2.`id` DESC LIMIT 1) AS `physical_room_name`,
                    (SELECT v.`name` FROM `sessions` s3 INNER JOIN `venues` v ON v.`id` = s3.`venue_id` WHERE s3.`game_room_id` = gr.`id` ORDER BY s3.`id` DESC LIMIT 1) AS `venue_name`
                FROM `room_participants` rp
                INNER JOIN `game_rooms` gr ON gr.`id` = rp.`game_room_id`
                INNER JOIN `games` g ON g.`id` = gr.`game_id`
                WHERE rp.`user_id` = :user_id
                ORDER BY rp.`id` DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Đồng bộ sĩ số thực tế cho tất cả phòng bằng RoomSeatHelper (1 truy vấn batch, tránh cộng đúp / thiếu ghế)
        $roomIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['room_id'] ?? 0), $rooms))));
        $activeUidsByRoom = [];
        if (!empty($roomIds)) {
            $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
            $actStmt = $this->pdo->prepare(
                "SELECT `game_room_id`, `user_id`
                 FROM `room_participants`
                 WHERE `game_room_id` IN ({$placeholders})
                   AND `status` IN ('PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED')"
            );
            foreach ($roomIds as $idx => $rid) {
                $actStmt->bindValue($idx + 1, $rid, PDO::PARAM_INT);
            }
            $actStmt->execute();
            foreach ($actStmt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $ridKey = (int) $ar['game_room_id'];
                $activeUidsByRoom[$ridKey][] = (int) $ar['user_id'];
            }
        }

        foreach ($rooms as &$mr) {
            $ridKey = (int) ($mr['room_id'] ?? 0);
            $mr['current_players'] = (int) RoomSeatHelper::computeSeats($mr, $activeUidsByRoom[$ridKey] ?? [])['occupied_seats'];
        }
        unset($mr);

        $now = time();
        foreach ($rooms as &$r) {
            $paid = (int) ($r['paid_amount'] ?? 0);
            $refStatus = $r['refund_status'] ?? null;
            $startAt = !empty($r['session_start_at']) ? strtotime((string) $r['session_start_at']) : null;
            $diffHours = $startAt !== null ? ($startAt - $now) / 3600 : null;

            $canRefund = false;
            $refundHint = '';

            if ($refStatus === 'COMPLETED') {
                $refundHint = 'Đã hoàn tất chuyển tiền cọc về tài khoản';
            } elseif ($paid > 0) {
                if ($refStatus === 'PENDING') {
                    $refundHint = 'Đang chờ Quản trị viên phê duyệt hoàn tiền';
                } elseif ($refStatus === 'APPROVED') {
                    $refundHint = 'Đã được duyệt hoàn cọc — không thể gửi thêm yêu cầu';
                } elseif ($refStatus === 'REJECTED') {
                    $revReason = trim((string) ($r['refund_review_reason'] ?? ''));
                    $refundHint = 'Yêu cầu hoàn tiền đã bị từ chối' . ($revReason !== '' ? " ({$revReason})" : '') . ' — không thể gửi lại';
                } elseif ($refStatus === null) {
                    if (
                        in_array(strtoupper((string) ($r['room_status'] ?? '')), ['COMPLETED', 'IN_PROGRESS'], true)
                        || in_array(strtoupper((string) ($r['session_status'] ?? '')), ['COMPLETED', 'IN_PROGRESS'], true)
                    ) {
                        $canRefund = false;
                        $refundHint = 'Buổi chơi đang diễn ra hoặc đã hoàn tất — không áp dụng hoàn cọc';
                    } elseif ($r['room_status'] === 'CANCELLED') {
                        $canRefund = true;
                        $refundHint = 'Phòng đã bị hủy - Đủ điều kiện nhận lại 100% tiền cọc';
                    } elseif ($diffHours === null || $diffHours >= 24) {
                        $canRefund = true;
                        $refundHint = 'Hủy lịch trước giờ chơi >= 24h - Được hoàn cọc 100%';
                    } else {
                        $refundHint = 'Dưới 24h trước giờ chơi - Không thể hoàn cọc theo quy chế';
                    }
                }
            } else {
                if ($r['participant_status'] === 'PENDING_DEPOSIT') {
                    $refundHint = 'Chưa đặt cọc giữ chỗ';
                }
            }

            $r['paid_amount'] = $paid;
            $r['can_refund'] = $canRefund;
            $r['refund_hint'] = $refundHint;
            $r['diff_hours_to_session'] = $diffHours !== null ? round($diffHours, 1) : null;
        }
        unset($r);

        return Response::success([
            'rooms' => $rooms,
            'total' => count($rooms),
        ], 'Lịch sử phòng chơi và tình trạng cọc');
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Xác minh Email + Số điện thoại đã đăng ký và cấp mã OTP 6 chữ số (lưu hash trên file, hiệu lực 10 phút, tối đa 5 lần thử)
     */
    public function forgotPassword(Request $request): Response
    {
        $body = $request->getBodyParams();
        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $phone = trim((string) ($body['phone'] ?? ''));

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Vui lòng nhập email đúng định dạng'];
        }
        $normalizedInputPhone = $this->normalizeVietnamesePhone($phone);
        if ($normalizedInputPhone === '' || strlen($normalizedInputPhone) < 9) {
            $errors['phone'] = ['Vui lòng nhập số điện thoại đã đăng ký hợp lệ'];
        }
        if (!empty($errors)) {
            return Response::fail('Vui lòng nhập đầy đủ Email và Số điện thoại đã đăng ký', 'VALIDATION_ERROR', 422, $errors);
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user === null || strtoupper((string) ($user['status'] ?? '')) !== 'ACTIVE') {
            return Response::fail(
                'Email hoặc số điện thoại không khớp với tài khoản đang hoạt động trên hệ thống',
                'ACCOUNT_VERIFY_FAILED',
                400
            );
        }

        $storedPhone = $this->normalizeVietnamesePhone((string) ($user['phone'] ?? ''));
        if ($storedPhone === '' || !hash_equals($storedPhone, $normalizedInputPhone)) {
            return Response::fail(
                'Email hoặc số điện thoại không khớp với tài khoản đang hoạt động trên hệ thống',
                'ACCOUNT_VERIFY_FAILED',
                400
            );
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $salt = bin2hex(random_bytes(16));
        $ttlSeconds = 600; // 10 phút
        $expiresAt = time() + $ttlSeconds;

        $resetDir = $this->getPasswordResetDir();
        if (!is_dir($resetDir)) {
            @mkdir($resetDir, 0755, true);
        }

        $tokenFile = $resetDir . DIRECTORY_SEPARATOR . hash('sha256', $email) . '.json';
        $record = [
            'user_id' => (int) $user['id'],
            'email' => $email,
            'otp_hash' => hash('sha256', $email . '|' . $otp . '|' . $salt),
            'salt' => $salt,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (@file_put_contents($tokenFile, json_encode($record, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            return Response::fail('Không thể khởi tạo phiên cấp lại mật khẩu, vui lòng thử lại sau', 'STORAGE_ERROR', 500);
        }

        try {
            $audit = new \App\Shared\Services\AuditLogService($this->pdo);
            $audit->log((int) $user['id'], 'PASSWORD_RESET_REQUESTED', 'USER', (int) $user['id'], [
                'email' => $email,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            ]);
        } catch (\Throwable) {
            // Không gián đoạn luồng chính nếu ghi audit gặp lỗi phụ
        }

        return Response::success([
            'email' => $email,
            'expires_in_seconds' => $ttlSeconds,
            'delivery_mode' => 'OTP_LOCAL_DEMO',
            'demo_otp' => $otp,
        ], 'Đã xác minh tài khoản và cấp mã OTP khôi phục mật khẩu (hiệu lực 10 phút).');
    }

    /**
     * POST /api/v1/auth/reset-password
     * Xác thực OTP và cập nhật users.password_hash (BCRYPT) thật trong MySQL
     */
    public function resetPassword(Request $request): Response
    {
        $body = $request->getBodyParams();
        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $otp = preg_replace('/\D+/', '', trim((string) ($body['otp'] ?? ''))) ?? '';
        $newPassword = (string) ($body['new_password'] ?? $body['password'] ?? '');
        $confirmPassword = isset($body['confirm_password']) ? (string) $body['confirm_password'] : null;

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Email không hợp lệ'];
        }
        if (strlen($otp) !== 6) {
            $errors['otp'] = ['Mã OTP phải gồm đúng 6 chữ số'];
        }
        if (strlen($newPassword) < 6) {
            $errors['new_password'] = ['Mật khẩu mới phải có ít nhất 6 ký tự'];
        }
        if ($confirmPassword !== null && $confirmPassword !== $newPassword) {
            $errors['confirm_password'] = ['Mật khẩu xác nhận không khớp'];
        }
        if (!empty($errors)) {
            return Response::fail('Dữ liệu đặt lại mật khẩu không hợp lệ', 'VALIDATION_ERROR', 422, $errors);
        }

        $tokenFile = $this->getPasswordResetDir() . DIRECTORY_SEPARATOR . hash('sha256', $email) . '.json';
        if (!is_file($tokenFile)) {
            return Response::fail('Không tìm thấy mã OTP hoặc mã đã hết hạn. Vui lòng yêu cầu cấp mã mới.', 'OTP_EXPIRED', 400);
        }

        $raw = @file_get_contents($tokenFile);
        $record = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($record) || empty($record['otp_hash']) || empty($record['salt']) || empty($record['user_id'])) {
            @unlink($tokenFile);
            return Response::fail('Phiên khôi phục mật khẩu không hợp lệ. Vui lòng yêu cầu cấp mã mới.', 'OTP_INVALID', 400);
        }

        if (time() > (int) ($record['expires_at'] ?? 0)) {
            @unlink($tokenFile);
            return Response::fail('Mã OTP đã hết hạn (quá 10 phút). Vui lòng yêu cầu cấp mã mới.', 'OTP_EXPIRED', 400);
        }

        $maxAttempts = 5;
        $attempts = (int) ($record['attempts'] ?? 0);
        if ($attempts >= $maxAttempts) {
            @unlink($tokenFile);
            return Response::fail('Bạn đã nhập sai mã OTP quá 5 lần. Vui lòng yêu cầu cấp mã mới.', 'OTP_LOCKED', 429);
        }

        $expectedHash = (string) $record['otp_hash'];
        $submittedHash = hash('sha256', $email . '|' . $otp . '|' . (string) $record['salt']);
        if (!hash_equals($expectedHash, $submittedHash)) {
            $attempts++;
            if ($attempts >= $maxAttempts) {
                @unlink($tokenFile);
                return Response::fail('Bạn đã nhập sai mã OTP quá 5 lần. Mã đã bị hủy, vui lòng yêu cầu cấp mã mới.', 'OTP_LOCKED', 429);
            }
            $record['attempts'] = $attempts;
            @file_put_contents($tokenFile, json_encode($record, JSON_UNESCAPED_UNICODE), LOCK_EX);
            $remaining = $maxAttempts - $attempts;
            return Response::fail("Mã OTP không chính xác (còn {$remaining} lần thử).", 'INVALID_OTP', 422);
        }

        $userId = (int) $record['user_id'];
        $user = $this->userRepo->findById($userId);
        if ($user === null || strtoupper((string) ($user['status'] ?? '')) !== 'ACTIVE') {
            @unlink($tokenFile);
            return Response::fail('Tài khoản không tồn tại hoặc đang bị tạm khóa', 'ACCOUNT_INACTIVE', 403);
        }

        $this->userRepo->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
        @unlink($tokenFile);

        try {
            $audit = new \App\Shared\Services\AuditLogService($this->pdo);
            $audit->log($userId, 'PASSWORD_RESET_COMPLETED', 'USER', $userId, [
                'email' => $email,
            ]);
        } catch (\Throwable) {
            // Không gián đoạn luồng chính
        }

        return Response::success([
            'reset' => true,
            'email' => $email,
        ], 'Đặt lại mật khẩu thành công! Bạn có thể đăng nhập ngay bằng mật khẩu mới.');
    }

    protected function getPasswordResetDir(): string
    {
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'password_resets';
    }

    protected function normalizeVietnamesePhone(string $rawPhone): string
    {
        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if (str_starts_with($digits, '84') && strlen($digits) >= 10) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }
}
