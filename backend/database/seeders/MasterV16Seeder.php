<?php

declare(strict_types=1);

/**
 * Master V16 Clean Seeder — Kịch Bản Sát V16
 * Nạp bộ dữ liệu thật chuẩn 100% theo cấu hình đã chốt:
 * - 3 roles (PLAYER, DM, ADMIN)
 * - 56 users (2 Admin, 4 DM, 50 Player)
 * - 1 venue (36/52 Nguyễn Gia Trí, Bình Thạnh) + 5 physical_rooms
 * - Đúng 42 kịch bản thật + 84 game_prices (2 khung giờ/kịch bản) + 42 game_configurations
 * - 2 promotions (TUESDAY_30, HSSV_20000)
 * - Dữ liệu mẫu sạch: 3 phòng FORMING + 1 ca SCHEDULED + 2 hóa đơn PAID + 1 refund PENDING (không có REPLACEMENT)
 */

$env = [];
foreach (file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$pdo = new PDO(
    'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($env['DB_PORT'] ?? '3306') . ';dbname=' . ($env['DB_DATABASE'] ?? 'kich_ban_sat_v3') . ';charset=utf8mb4',
    $env['DB_USERNAME'] ?? 'root',
    $env['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$scriptsPath = 'C:/Users/DELL/.gemini/antigravity/brain/4bc7babd-01ac-498a-8487-7db59b52f797/scratch/scripts42.json';
$scripts = json_decode(file_get_contents($scriptsPath), true);
if (!is_array($scripts) || count($scripts) !== 42) {
    throw new RuntimeException('Cannot load 42 scripts from scripts42.json');
}

// 1. Clean old test junk safely
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$tablesToTruncate = [
    'audit_logs', 'notifications', 'refunds', 'payment_transactions', 'player_payments',
    'participant_price_snapshots', 'sessions', 'dm_assignments', 'schedule_responses',
    'schedule_proposals', 'room_participants', 'game_rooms', 'promotions', 'game_prices',
    'game_configurations', 'games', 'physical_rooms', 'venues', 'users', 'roles'
];
foreach ($tablesToTruncate as $t) {
    $pdo->exec("TRUNCATE TABLE `{$t}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// 2. Seed Roles
$pdo->exec("
    INSERT INTO `roles` (`id`, `code`, `name`, `description`) VALUES
    (1, 'PLAYER', 'Người chơi', 'Thành viên tham gia ghép phòng và đặt lịch chơi'),
    (2, 'DM', 'Quản trò (DM)', 'Quản trò dẫn dắt buổi chơi Kịch Bản Sát'),
    (3, 'ADMIN', 'Quản trị viên', 'Quản trị toàn hệ thống Kịch Bản Sát V16')
");

// 3. Seed 56 Users (2 Admin, 4 DM, 50 Player)
$adminHash = password_hash('pass123456!', PASSWORD_BCRYPT);
$dmHash = password_hash('pass123456@', PASSWORD_BCRYPT);
$playerHash = password_hash('pass123456#', PASSWORD_BCRYPT);

$userStmt = $pdo->prepare(
    'INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone`, `password_hash`, `status`, `created_at`, `updated_at`)
     VALUES (:role_id, :name, :email, :phone, :hash, \'ACTIVE\', NOW(3), NOW(3))'
);

$admins = [
    ['Nguyễn Xuân Hiếu', 'nguyenxuanhieu@gmail.com', '0901000001'],
    ['Lê Nguyễn Nhật Trường', 'lenguyennhattruong@gmail.com', '0901000002'],
];
foreach ($admins as [$name, $email, $phone]) {
    $userStmt->execute([':role_id' => 3, ':name' => $name, ':email' => $email, ':phone' => $phone, ':hash' => $adminHash]);
}

$dms = [
    ['Nguyễn Phạm Y Huỳnh', 'nguyenphamyhuynh@gmail.com', '0902000001'],
    ['Lê Hữu Châu', 'lehuuchau@gmail.com', '0902000002'],
    ['Nguyễn Anh Thư', 'nguyenanhthu@gmail.com', '0902000003'],
    ['Nguyễn Thị Thảo Như', 'nguyenthithaonhu@gmail.com', '0902000004'],
];
foreach ($dms as [$name, $email, $phone]) {
    $userStmt->execute([':role_id' => 2, ':name' => $name, ':email' => $email, ':phone' => $phone, ':hash' => $dmHash]);
}

for ($i = 1; $i <= 50; $i++) {
    $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
    $userStmt->execute([
        ':role_id' => 1,
        ':name' => "Người chơi {$num}",
        ':email' => "nguoichoi{$num}@gmail.com",
        ':phone' => '09030000' . $num,
        ':hash' => $playerHash,
    ]);
}

// 4. Seed 1 Venue + 5 Physical Rooms
$pdo->exec("
    INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`, `created_at`, `updated_at`)
    VALUES (1, 'Kịch Bản Sát — Bình Thạnh', '36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM', '0915690033', 'Mở cửa 09:00 – 23:00 mỗi ngày. Không gian nhập vai phá án Murder Mystery chuẩn điện ảnh.', 'ACTIVE', NOW(3), NOW(3))
");

$pdo->exec("
    INSERT INTO `physical_rooms` (`id`, `venue_id`, `name`, `capacity`, `description`, `status`, `created_at`, `updated_at`) VALUES
    (1, 1, 'Phòng Mật Thất 01', 8, 'Tầng 2 — Cách âm tiêu chuẩn, ánh sáng điện ảnh', 'ACTIVE', NOW(3), NOW(3)),
    (2, 1, 'Phòng Biệt Thự Cổ 02', 8, 'Tầng 2 — Bối cảnh dinh thự cổ điển', 'ACTIVE', NOW(3), NOW(3)),
    (3, 1, 'Phòng Án Mạng Đêm 03', 7, 'Tầng 3 — Chuyên kịch bản kinh dị & tâm linh', 'ACTIVE', NOW(3), NOW(3)),
    (4, 1, 'Phòng Gương Vỡ 04', 6, 'Tầng 3 — Không gian suy luận tâm lý kín', 'ACTIVE', NOW(3), NOW(3)),
    (5, 1, 'Phòng Thẩm Vấn 05', 8, 'Tầng 4 — Phục vụ nhóm đông 6–8 người', 'ACTIVE', NOW(3), NOW(3))
");

// 5. Seed 42 Real Games + Configurations + 2-slot Prices
$gameStmt = $pdo->prepare(
    'INSERT INTO `games` (`name`, `slug`, `description`, `image_url`, `poster_url`, `difficulty`, `synopsis`, `min_players`, `max_players`, `duration_minutes`, `status`, `display_order`, `created_at`, `updated_at`)
     VALUES (:name, :slug, :desc, :img, :poster, :diff, :syn, :min_p, :max_p, :dur, \'ACTIVE\', :ord, NOW(3), NOW(3))'
);
$cfgStmt = $pdo->prepare(
    'INSERT INTO `game_configurations` (`game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`, `created_at`)
     VALUES (:gid, 1, :cfg, :desc, 1, 1, NOW(3))'
);
$priceStmt = $pdo->prepare(
    'INSERT INTO `game_prices` (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
     VALUES (:gid, :price, :st, :et, \'2026-01-01\', \'2035-12-31\', \'ACTIVE\', NOW(3), NOW(3))'
);

foreach ($scripts as $idx => $s) {
    $name = $s['title'] ?? ('Kịch bản ' . ($idx + 1));
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string)($s['code'] ?? ('scr-' . ($idx + 1)))));
    $poster = !empty($s['poster']) ? './src/assets/images/' . basename($s['poster']) : null;
    $minP = max(1, (int)($s['playersMin'] ?? 5));
    $maxP = max($minP, (int)($s['playersMax'] ?? 8));
    $dayPrice = (int)($s['priceDay'] ?? $s['price'] ?? 159000);
    $nightPrice = (int)($s['priceNight'] ?? ($dayPrice + 40000));

    $gameStmt->execute([
        ':name' => mb_substr($name, 0, 200),
        ':slug' => $slug,
        ':desc' => $s['summary'] ?? '',
        ':img' => $poster,
        ':poster' => $poster,
        ':diff' => mb_substr($s['difficulty'] ?? '★★★', 0, 50),
        ':syn' => $s['synopsis'] ?? ($s['summary'] ?? ''),
        ':min_p' => $minP,
        ':max_p' => $maxP,
        ':dur' => (int)(($s['durationHours'] ?? 3.5) * 60),
        ':ord' => $idx + 1,
    ]);
    $gid = (int)$pdo->lastInsertId();

    $cfgStmt->execute([
        ':gid' => $gid,
        ':cfg' => json_encode(['male' => (int)($s['maleCount'] ?? 3), 'female' => (int)($s['femaleCount'] ?? 3)]),
        ':desc' => "Bản chuẩn ({$minP}-{$maxP} người)",
    ]);

    $priceStmt->execute([':gid' => $gid, ':price' => $dayPrice, ':st' => '09:00:00', ':et' => '19:59:59']);
    $priceStmt->execute([':gid' => $gid, ':price' => $nightPrice, ':st' => '20:00:00', ':et' => '23:00:00']);
}

// 6. Seed 2 Promotions (TUESDAY_30 + HSSV_20000)
$pdo->exec("
    INSERT INTO `promotions` (`id`, `code`, `name`, `promotion_type`, `discount_type`, `discount_value`, `start_at`, `end_at`, `rules`, `is_active`) VALUES
    (1, 'TUESDAY_30', 'Ưu đãi vui vẻ - Thứ Ba Giảm 30%', 'WEEKDAY', 'PERCENT', 30, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(2), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'minimum_booking_hours', 24, 'applies_to', 'ANY_GAME', 'stackable', FALSE), 1),
    (2, 'HSSV_20000', 'Ưu đãi học sinh sinh viên (Kiểm tra thẻ tại quán)', 'STUDENT', 'FIXED', 20000, '2026-01-01 00:00:00.000', '2035-12-31 23:59:59.999', JSON_OBJECT('weekdays', JSON_ARRAY(1, 2, 3, 4), 'valid_from', '09:00:00', 'valid_until', '19:00:00', 'discount_per_eligible_person', 20000, 'requires_student_verification', TRUE, 'stackable', FALSE), 1)
");

// 7. Seed Clean Sample Rooms: 4 FORMING + 4 SCHEDULED + 3 COMPLETED + 2 Refunds PENDING (NO REPLACEMENT)
// User IDs: 1-2 Admin, 3-6 DM (3=Huỳnh, 4=Châu, 5=Thư, 6=Như), 7..56 Players (7 = Người chơi 01)
$roomIns = $pdo->prepare(
    'INSERT INTO `game_rooms` (`id`, `game_id`, `game_configuration_id`, `creator_id`, `title`, `description`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`)
     VALUES (:id, :gid, :cid, :creator, :title, :desc, :min_p, :max_p, :status, :deadline, :created, :created)'
);
$partIns = $pdo->prepare(
    'INSERT INTO `room_participants` (`game_room_id`, `user_id`, `status`, `joined_at`) VALUES (:rid, :uid, :status, :joined)'
);
$propIns = $pdo->prepare(
    'INSERT INTO `schedule_proposals` (`game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `expires_at`, `status`)
     VALUES (:rid, :uid, :st, :et, :exp, \'CONFIRMED\')'
);
$dmIns = $pdo->prepare(
    'INSERT INTO `dm_assignments` (`game_room_id`, `dm_user_id`, `assigned_by`, `status`, `responded_at`)
     VALUES (:rid, :dm_id, 1, :status, :resp)'
);
$sessIns = $pdo->prepare(
    'INSERT INTO `sessions` (`game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`, `session_notes`)
     VALUES (:rid, :pid, :did, 1, :prid, :st, :et, :status, :notes)'
);
$payIns = $pdo->prepare(
    'INSERT INTO `player_payments` (`room_participant_id`, `payment_type`, `amount`, `paid_amount`, `status`, `paid_at`, `created_at`)
     VALUES (:rpid, :type, :amt, :amt, :status, :paid_at, :created)'
);

$now = date('Y-m-d H:i:s.000');

// A. 4 FORMING Rooms (IDs 1..4)
$formingSpecs = [
    [1, 1, 7, 'Ghép nhóm phá án 2/12 tối Thứ 6', 'Tuyển thêm bạn cùng gu trinh thám kinh dị, không toxic!', 7, 7, [7, 8, 9]],
    [2, 2, 10, 'Đội Điểm Danh cuối tuần — Bình Thạnh', 'Cần thêm 3 thám tử chơi ca chiều Thứ 7.', 6, 6, [10, 11, 12]],
    [3, 3, 13, 'Phá án Yandere — Kèo vui vẻ', 'Ưu tiên bạn đã từng chơi Jubensha, cọc 50k giữ ghế.', 6, 8, [13, 14]],
    [4, 5, 15, 'Hội Suy Luận — Kịch Bản Cổ Trang', 'Cần thêm 3 bạn thích thể loại cổ trang tâm lý.', 6, 7, [15, 16, 17, 18]],
];
$refundPaymentIds = [];
foreach ($formingSpecs as [$rid, $gid, $creator, $title, $desc, $minP, $maxP, $members]) {
    $deadline = date('Y-m-d H:i:s.000', strtotime('+4 hours'));
    $roomIns->execute([':id' => $rid, ':gid' => $gid, ':cid' => $gid, ':creator' => $creator, ':title' => $title, ':desc' => $desc, ':min_p' => $minP, ':max_p' => $maxP, ':status' => 'FORMING', ':deadline' => $deadline, ':created' => $now]);
    foreach ($members as $idx => $uid) {
        $pStatus = ($idx === 0) ? 'DEPOSITED' : 'PENDING_DEPOSIT';
        $partIns->execute([':rid' => $rid, ':uid' => $uid, ':status' => $pStatus, ':joined' => $now]);
        $rpid = (int)$pdo->lastInsertId();
        if ($pStatus === 'DEPOSITED') {
            $payIns->execute([':rpid' => $rpid, ':type' => 'DEPOSIT', ':amt' => 50000, ':status' => 'PAID', ':paid_at' => $now, ':created' => $now]);
            if (count($refundPaymentIds) < 2) {
                $refundPaymentIds[] = (int)$pdo->lastInsertId();
            }
        }
    }
}

// B. 4 SCHEDULED Rooms (IDs 5..8) — 1 per DM (3=Huỳnh ACCEPTED, 4=Châu ACCEPTED, 5=Thư PENDING, 6=Như PENDING)
$scheduledSpecs = [
    [5, 6, 19, 'Ca tối Thứ 7 — Martinez Kinh Kỳ Quán', 3, 'ACCEPTED', 1, '+1 day 19:00', [19, 20, 21, 22, 23, 24]],
    [6, 7, 25, 'Ca chiều Chủ Nhật — Biệt Thự Bí Ẩn', 4, 'ACCEPTED', 2, '+2 days 14:00', [25, 26, 27, 28, 29, 30]],
    [7, 8, 31, 'Ca tối Thứ 2 — Án Mạng Nửa Đêm', 5, 'PENDING', 3, '+3 days 20:00', [31, 32, 33, 34, 35, 36]],
    [8, 9, 37, 'Ca chiều Thứ 4 — Mật Thất Cổ Điển', 6, 'PENDING', 4, '+4 days 15:00', [37, 38, 39, 40, 41, 42]],
];
foreach ($scheduledSpecs as [$rid, $gid, $creator, $title, $dmId, $dmStatus, $prid, $timeStr, $members]) {
    $st = date('Y-m-d H:i:s.000', strtotime($timeStr));
    $et = date('Y-m-d H:i:s.000', strtotime($timeStr . ' +4 hours'));
    $exp = date('Y-m-d H:i:s.000', strtotime('+12 hours'));
    $roomIns->execute([':id' => $rid, ':gid' => $gid, ':cid' => $gid, ':creator' => $creator, ':title' => $title, ':desc' => 'Phòng đã đủ người và chốt lịch chính thức.', ':min_p' => 6, ':max_p' => 6, ':status' => 'SCHEDULED', ':deadline' => $st, ':created' => $now]);
    foreach ($members as $uid) {
        $partIns->execute([':rid' => $rid, ':uid' => $uid, ':status' => 'DEPOSITED', ':joined' => $now]);
        $rpid = (int)$pdo->lastInsertId();
        $payIns->execute([':rpid' => $rpid, ':type' => 'DEPOSIT', ':amt' => 50000, ':status' => 'PAID', ':paid_at' => $now, ':created' => $now]);
    }
    $propIns->execute([':rid' => $rid, ':uid' => $creator, ':st' => $st, ':et' => $et, ':exp' => $exp]);
    $pid = (int)$pdo->lastInsertId();
    $dmIns->execute([':rid' => $rid, ':dm_id' => $dmId, ':status' => $dmStatus, ':resp' => $dmStatus === 'ACCEPTED' ? $now : null]);
    $did = (int)$pdo->lastInsertId();
    $sessIns->execute([':rid' => $rid, ':pid' => $pid, ':did' => $did, ':prid' => $prid, ':st' => $st, ':et' => $et, ':status' => 'SCHEDULED', ':notes' => null]);
}

// C. 3 COMPLETED Rooms (IDs 9..11) — within last 7 days for Admin Revenue Chart
$completedSpecs = [
    [9, 10, 43, 'Ca hoàn thành #01 — Phá án thành công', 3, 1, '-1 day 19:00', [43, 44, 45, 46, 47, 48], 'Nhóm tìm ra đúng hung thủ ở phút cuối, không có sự cố.'],
    [10, 11, 49, 'Ca hoàn thành #02 — Tối Thứ 5', 4, 2, '-3 days 20:00', [49, 50, 51, 52, 53, 54], 'Buổi chơi diễn ra suôn sẻ, khách đánh giá 5 sao.'],
    [11, 12, 7, 'Ca hoàn thành #03 — Chiều Chủ Nhật', 5, 5, '-5 days 14:00', [7, 8, 9, 55, 56, 10], 'Hoàn thành đúng tiến độ, đạo cụ đầy đủ.'],
];
foreach ($completedSpecs as [$rid, $gid, $creator, $title, $dmId, $prid, $timeStr, $members, $notes]) {
    $st = date('Y-m-d H:i:s.000', strtotime($timeStr));
    $et = date('Y-m-d H:i:s.000', strtotime($timeStr . ' +4 hours'));
    $roomIns->execute([':id' => $rid, ':gid' => $gid, ':cid' => $gid, ':creator' => $creator, ':title' => $title, ':desc' => 'Buổi chơi đã hoàn thành.', ':min_p' => 6, ':max_p' => 6, ':status' => 'COMPLETED', ':deadline' => $st, ':created' => $st]);
    foreach ($members as $uid) {
        $partIns->execute([':rid' => $rid, ':uid' => $uid, ':status' => 'DEPOSITED', ':joined' => $st]);
        $rpid = (int)$pdo->lastInsertId();
        $payIns->execute([':rpid' => $rpid, ':type' => 'DEPOSIT', ':amt' => 50000, ':status' => 'PAID', ':paid_at' => $st, ':created' => $st]);
        $payIns->execute([':rpid' => $rpid, ':type' => 'REMAINING', ':amt' => 149000, ':status' => 'PAID', ':paid_at' => $et, ':created' => $et]);
    }
    $propIns->execute([':rid' => $rid, ':uid' => $creator, ':st' => $st, ':et' => $et, ':exp' => $st]);
    $pid = (int)$pdo->lastInsertId();
    $dmIns->execute([':rid' => $rid, ':dm_id' => $dmId, ':status' => 'ACCEPTED', ':resp' => $st]);
    $did = (int)$pdo->lastInsertId();
    $sessIns->execute([':rid' => $rid, ':pid' => $pid, ':did' => $did, ':prid' => $prid, ':st' => $st, ':et' => $et, ':status' => 'COMPLETED', ':notes' => $notes]);
}

// D. 2 PENDING Refunds for Admin Approve / Reject Demo
$refIns = $pdo->prepare('INSERT INTO `refunds` (`player_payment_id`, `amount`, `reason`, `status`) VALUES (:pid, 50000, :reason, \'PENDING\')');
$refIns->execute([':pid' => $refundPaymentIds[0], ':reason' => 'Bận việc đột xuất, báo hủy trước 24 giờ theo chính sách.']);
$refIns->execute([':pid' => $refundPaymentIds[1], ':reason' => 'Xin đổi sang tuần sau nên hủy ghế phòng hiện tại.']);

echo "[SUCCESS] MasterV16Seeder completed cleanly!\n";
echo "Users: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " (2 Admin, 4 DM, 50 Player)\n";
echo "Games: " . $pdo->query('SELECT COUNT(*) FROM games')->fetchColumn() . " | Prices: " . $pdo->query('SELECT COUNT(*) FROM game_prices')->fetchColumn() . "\n";
echo "Venues: " . $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn() . " | Physical Rooms: " . $pdo->query('SELECT COUNT(*) FROM physical_rooms')->fetchColumn() . "\n";
echo "Rooms: " . $pdo->query('SELECT COUNT(*) FROM game_rooms')->fetchColumn() . " (4 FORMING, 4 SCHEDULED, 3 COMPLETED)\n";
echo "Payments: " . $pdo->query('SELECT COUNT(*) FROM player_payments')->fetchColumn() . " | Pending Refunds: " . $pdo->query('SELECT COUNT(*) FROM refunds WHERE status="PENDING"')->fetchColumn() . "\n";
