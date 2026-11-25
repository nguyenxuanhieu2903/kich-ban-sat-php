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

// 7. Seed Clean Sample Rooms (3 FORMING + 1 SCHEDULED, NO REPLACEMENT)
// User IDs: 1-2 Admin, 3-6 DM, 7..56 Players (7 = Người chơi 01)
$pdo->exec("
    INSERT INTO `game_rooms` (`id`, `game_id`, `game_configuration_id`, `creator_id`, `title`, `description`, `min_players`, `max_players`, `status`, `formation_deadline_at`, `created_at`, `updated_at`) VALUES
    (1, 1, 1, 7, 'Ghép nhóm phá án 2/12 tối Thứ 6', 'Tuyển thêm bạn cùng gu trinh thám kinh dị, không toxic!', 7, 7, 'FORMING', DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(3), NOW(3)),
    (2, 2, 2, 8, 'Đội Điểm Danh cuối tuần — Bình Thạnh', 'Cần thêm 3 thám tử chơi ca chiều Thứ 7.', 6, 6, 'FORMING', DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(3), NOW(3)),
    (3, 3, 3, 9, 'Phá án Yandere — Kèo vui vẻ', 'Ưu tiên bạn đã từng chơi Jubensha, cọc 50k giữ ghế.', 6, 8, 'FORMING', DATE_ADD(NOW(), INTERVAL 4 HOUR), NOW(3), NOW(3)),
    (4, 4, 4, 7, 'Ca đã chốt — Martinez Tại Kinh Kỳ Quán', 'Phòng đã đủ người và chốt lịch cùng DM Nguyễn Phạm Y Huỳnh.', 6, 6, 'SCHEDULED', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(3), NOW(3))
");

// Participants
$pdo->exec("
    INSERT INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`) VALUES
    (1, 1, 7, 'PENDING_DEPOSIT', NOW(3)),
    (2, 1, 8, 'DEPOSITED', NOW(3)),
    (3, 2, 8, 'PENDING_DEPOSIT', NOW(3)),
    (4, 3, 9, 'PENDING_DEPOSIT', NOW(3)),
    (5, 4, 7, 'DEPOSITED', NOW(3)),
    (6, 4, 10, 'DEPOSITED', NOW(3))
");

// Proposal + DM Assignment + Session for Room 4
$pdo->exec("
    INSERT INTO `schedule_proposals` (`id`, `game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `expires_at`, `status`)
    VALUES (1, 4, 7, DATE_ADD(NOW(), INTERVAL 2 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 1 DAY), 'CONFIRMED')
");
$pdo->exec("
    INSERT INTO `dm_assignments` (`id`, `game_room_id`, `dm_user_id`, `assigned_by`, `status`, `responded_at`)
    VALUES (1, 4, 3, 1, 'ACCEPTED', NOW(3))
");
$pdo->exec("
    INSERT INTO `sessions` (`id`, `game_room_id`, `schedule_proposal_id`, `dm_assignment_id`, `venue_id`, `physical_room_id`, `start_at`, `end_at`, `status`)
    VALUES (1, 4, 1, 1, 1, 1, DATE_ADD(NOW(), INTERVAL 2 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 2 DAY), INTERVAL 4 HOUR), 'SCHEDULED')
");

// 3 PAID Deposit Payments + 1 PENDING Refund for Admin Demo
$pdo->exec("
    INSERT INTO `player_payments` (`id`, `room_participant_id`, `payment_type`, `amount`, `status`, `paid_at`) VALUES
    (1, 5, 'DEPOSIT', 50000, 'PAID', NOW(3)),
    (2, 6, 'DEPOSIT', 50000, 'PAID', NOW(3)),
    (3, 2, 'DEPOSIT', 50000, 'PAID', NOW(3))
");
$pdo->exec("
    INSERT INTO `refunds` (`id`, `player_payment_id`, `amount`, `reason`, `status`)
    VALUES (1, 3, 50000, 'Bận việc đột xuất, báo hủy trước 24 giờ theo chính sách.', 'PENDING')
");

echo "[SUCCESS] MasterV16Seeder completed cleanly!\n";
echo "Users: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " (2 Admin, 4 DM, 50 Player)\n";
echo "Games: " . $pdo->query('SELECT COUNT(*) FROM games')->fetchColumn() . " | Prices: " . $pdo->query('SELECT COUNT(*) FROM game_prices')->fetchColumn() . "\n";
echo "Venues: " . $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn() . " | Physical Rooms: " . $pdo->query('SELECT COUNT(*) FROM physical_rooms')->fetchColumn() . "\n";
echo "Promotions: " . $pdo->query('SELECT COUNT(*) FROM promotions')->fetchColumn() . " | Sample Rooms: " . $pdo->query('SELECT COUNT(*) FROM game_rooms')->fetchColumn() . "\n";
