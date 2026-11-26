<?php

declare(strict_types=1);

namespace App\Features\Identity\Controllers;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Features\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\DatabaseConnection;
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

        $email = trim(strtolower((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::fail('Vui lòng nhập đầy đủ email và mật khẩu', 'VALIDATION_ERROR', 422);
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user === null) {
            return Response::fail('Email hoặc mật khẩu không chính xác', 'INVALID_CREDENTIALS', 401);
        }

        if (($user['status'] ?? '') !== 'ACTIVE') {
            return Response::fail('Tài khoản của bạn đang bị tạm khóa hoặc chưa kích hoạt', 'ACCOUNT_INACTIVE', 403);
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return Response::fail('Email hoặc mật khẩu không chính xác', 'INVALID_CREDENTIALS', 401);
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
                    (SELECT COALESCE(SUM(pp.`paid_amount`), 0) FROM `player_payments` pp WHERE pp.`room_participant_id` = rp.`id`) AS `paid_amount`,
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
        foreach ($rooms as &$mr) {
            $rawCur = (int) ($mr['current_players'] ?? 0);
            $cSeats = max(1, (int) ($mr['total_players'] ?? 1));
            $maxP = (int) ($mr['max_players'] ?? 8);
            $mr['current_players'] = $rawCur > 0 ? min($maxP, $cSeats + max(0, $rawCur - 1)) : 0;
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

            if ($paid > 0) {
                if ($refStatus === 'PENDING') {
                    $refundHint = 'Đang chờ Quản trị viên phê duyệt hoàn tiền';
                } elseif ($refStatus === 'APPROVED') {
                    $refundHint = 'Đã được duyệt hoàn cọc - đang chờ kế toán chi trả về tài khoản';
                } elseif ($refStatus === 'COMPLETED') {
                    $refundHint = 'Đã hoàn tất chuyển tiền cọc về tài khoản';
                } elseif ($refStatus === null || $refStatus === 'REJECTED') {
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

        return Response::success([
            'rooms' => $rooms,
            'total' => count($rooms),
        ], 'Lịch sử phòng chơi và tình trạng cọc');
    }
}
