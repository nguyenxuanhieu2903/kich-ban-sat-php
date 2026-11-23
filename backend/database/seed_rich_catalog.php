<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Config\Config;
use App\Infrastructure\Database\DatabaseConnection;

Config::load(dirname(__DIR__));
$pdo = DatabaseConnection::getInstance();

echo "Seeding 9 rich Jubensha scenarios into database...\n";

$scenarios = [
    [
        'id' => 4,
        'name' => 'Kỳ Án Trường An',
        'slug' => 'ky-an-truong-an',
        'description' => 'Đêm hội hoa đăng thời Đường, vị Thượng thư đột ngột trúng độc chết giữa yến tiệc cung đình. 8 nhân vật với các mối ân oán cung đình phải cùng tra án trước rạng đông.',
        'image_url' => 'https://images.unsplash.com/photo-1547891654-e66ed7ebb968',
        'min_players' => 6,
        'max_players' => 8,
        'duration_minutes' => 240,
        'display_order' => 4,
        'price_morning' => 190000,
        'price_afternoon' => 220000,
        'price_evening' => 250000,
        'props' => ['Lệnh bài Cẩm Y Vệ', 'Thư phong ấn hoàng gia', 'Bình rượu độc hoa quỳnh', 'Ngọc bội khắc chữ Minh'],
        'desc_config' => 'Kịch bản Cổ trang Cung đình phá án nhiều tầng manh mối'
    ],
    [
        'id' => 5,
        'name' => 'Hồi Ức Đẫm Máu 18+',
        'slug' => 'hoi-uc-dam-mau',
        'description' => 'Một nhóm bạn đại học hội ngộ tại căn nhà gỗ ven rừng sau 10 năm. Khi màn đêm buông xuống, bí mật đen tối về cái chết của cô bạn năm xưa dần được hé lộ qua những bức thư máu.',
        'image_url' => 'https://images.unsplash.com/photo-1509248961158-e54f6934749c',
        'min_players' => 5,
        'max_players' => 7,
        'duration_minutes' => 210,
        'display_order' => 5,
        'price_morning' => 200000,
        'price_afternoon' => 230000,
        'price_evening' => 260000,
        'props' => ['Cuốn nhật ký ố vàng', 'Băng cassette có tiếng thét', 'Chiếc vòng tay đứt đoạn', 'Chìa khóa rỉ sét'],
        'desc_config' => 'Kịch bản Kinh dị Tâm lý giới hạn độ tuổi 18+'
    ],
    [
        'id' => 6,
        'name' => 'Án Mạng Trong Thư Viện Cổ',
        'slug' => 'an-mang-trong-thu-vien-co',
        'description' => 'Nhà sử học nổi tiếng được tìm thấy gục chết trên bàn làm việc trong thư viện khóa kín từ bên trong. Không có vũ khí hung khí, chỉ có 24 manh mối giấy cổ để tìm ra hung thủ.',
        'image_url' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da',
        'min_players' => 6,
        'max_players' => 8,
        'duration_minutes' => 240,
        'display_order' => 6,
        'price_morning' => 180000,
        'price_afternoon' => 220000,
        'price_evening' => 240000,
        'props' => ['Kính lúp cổ', 'Bản thảo cổ da cừu', 'Mẫu mực dính máu', 'Danh sách mượn sách mật'],
        'desc_config' => 'Kịch bản Trinh thám Phòng kín (Locked Room Mystery) cổ điển'
    ],
    [
        'id' => 7,
        'name' => 'Trạm Không Gian Vô Định',
        'slug' => 'tram-khong-gian-vo-dinh',
        'description' => 'Năm 2140, trạm nghiên cứu quỹ đạo mất liên lạc với Trái Đất. Thuyền trưởng tử vong trong khoang cách ly sinh học. Ai trong phi hành đoàn là kẻ phản bội?',
        'image_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa',
        'min_players' => 6,
        'max_players' => 8,
        'duration_minutes' => 270,
        'display_order' => 7,
        'price_morning' => 220000,
        'price_afternoon' => 250000,
        'price_evening' => 280000,
        'props' => ['Thẻ ID kỹ thuật viên', 'Ống nghiệm mẫu sinh vật ngoài hành tinh', 'Nhật ký buồng lái', 'Mã lệnh tự hủy'],
        'desc_config' => 'Kịch bản Khoa học Viễn tưởng (Sci-Fi Cyberpunk) đa kết thúc'
    ],
    [
        'id' => 8,
        'name' => 'Bữa Tiệc Tử Thần Gatsby',
        'slug' => 'bua-tiec-tu-than-gatsby',
        'description' => 'Thập niên 1920 rực rỡ, bữa tiệc xa hoa của triệu phú New York bỗng chốc trở thành hiện trường án mạng khi đèn phụt tắt đúng nửa đêm. Hỗ trợ phục trang dạ hội lộng lẫy.',
        'image_url' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30',
        'min_players' => 7,
        'max_players' => 9,
        'duration_minutes' => 270,
        'display_order' => 8,
        'price_morning' => 230000,
        'price_afternoon' => 260000,
        'price_evening' => 290000,
        'props' => ['Ly champagne pha độc', 'Chuỗi ngọc trai đứt', 'Hộp quẹt vàng khắc tên', 'Thư tống tiền dạ tiệc'],
        'desc_config' => 'Kịch bản Dạ hội Quý tộc Âu Mỹ có trang phục hóa trang'
    ],
    [
        'id' => 9,
        'name' => 'Căn Phòng Số 13',
        'slug' => 'can-phong-so-13',
        'description' => 'Khách sạn ven biển đóng cửa mùa đông chỉ còn lại 6 vị khách trọ. Đêm bão tuyết, thi thể của viên quản lý được phát hiện trong bồn tắm căn phòng số 13 vốn bị niêm phong nhiều năm.',
        'image_url' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427',
        'min_players' => 5,
        'max_players' => 6,
        'duration_minutes' => 180,
        'display_order' => 9,
        'price_morning' => 180000,
        'price_afternoon' => 200000,
        'price_evening' => 230000,
        'props' => ['Chìa khóa đồng phòng 13', 'Sổ đăng ký khách trọ', 'Hóa đơn tiền thuốc', 'Bản đồ thoát hiểm'],
        'desc_config' => 'Kịch bản Giật gân Tâm lý học (Psychological Thriller)'
    ],
    [
        'id' => 10,
        'name' => 'Bí Mật Làng Cổ Hắc Miêu',
        'slug' => 'bi-mat-lang-co-hac-mieu',
        'description' => 'Ngôi làng hẻo lánh thờ phụng thần mèo đen tổ chức lễ tế hàng năm. Đúng vào khắc giao thừa, trưởng làng bị sát hại theo một nghi thức hiến tế rùng rợn.',
        'image_url' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba',
        'min_players' => 6,
        'max_players' => 8,
        'duration_minutes' => 240,
        'display_order' => 10,
        'price_morning' => 190000,
        'price_afternoon' => 220000,
        'price_evening' => 250000,
        'props' => ['Chuông đồng trừ tà', 'Tượng mèo gỗ cổ', 'Mảnh bùa vàng phong ấn', 'Bát hương tế thần'],
        'desc_config' => 'Kịch bản Dân gian Bí ẩn Phong tục Á Đông'
    ],
    [
        'id' => 11,
        'name' => 'Vụ Trộm Đề Thi Học Đường',
        'slug' => 'vu-trom-de-thi-hoc-duong',
        'description' => 'Một vụ trộm đề thi tốt nghiệp chấn động tại trường trung học danh giá. 6 học sinh và giáo viên trong ban kỷ luật nghi ngờ lẫn nhau trong không khí vừa hồi hộp vừa hài hước.',
        'image_url' => 'https://images.unsplash.com/photo-1580582932707-520aed937b7b',
        'min_players' => 5,
        'max_players' => 7,
        'duration_minutes' => 180,
        'display_order' => 11,
        'price_morning' => 160000,
        'price_afternoon' => 190000,
        'price_evening' => 210000,
        'props' => ['Chìa khóa phòng giáo vụ', 'Đồng phục học sinh có vết mực', 'Điện thoại chụp lén', 'Mảnh đề thi bị xé'],
        'desc_config' => 'Kịch bản Học đường Hài hước Dễ tiếp cận cho người mới (Newbie Friendly)'
    ],
    [
        'id' => 12,
        'name' => 'Bản Giao Hưởng Lúc Nửa Đêm',
        'slug' => 'ban-giao-huong-luc-nua-dem',
        'description' => 'Nhạc trưởng thiên tài của nhà hát kịch thành phố bị hạ độc ngay giữa buổi tổng duyệt hòa nhạc. Từng nốt nhạc trên bản thảo đều ẩn chứa manh mối về danh tính kẻ sát nhân.',
        'image_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4',
        'min_players' => 6,
        'max_players' => 8,
        'duration_minutes' => 210,
        'display_order' => 12,
        'price_morning' => 190000,
        'price_afternoon' => 220000,
        'price_evening' => 250000,
        'props' => ['Bản phổ giao hưởng số 9', 'Đũa chỉ huy dính độc', 'Vé nhà hát hàng VIP', 'Bức thư hâm mộ nặc danh'],
        'desc_config' => 'Kịch bản Nghệ thuật Âm nhạc Suy luận Logic cao cấp'
    ]
];

foreach ($scenarios as $sc) {
    // 1. Insert/Update Game
    $stmt = $pdo->prepare("
        INSERT INTO `games` (`id`, `name`, `slug`, `description`, `image_url`, `min_players`, `max_players`, `duration_minutes`, `status`, `display_order`, `created_at`, `updated_at`)
        VALUES (:id, :name, :slug, :description, :image_url, :min_players, :max_players, :duration_minutes, 'ACTIVE', :display_order, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `description` = VALUES(`description`),
            `image_url` = VALUES(`image_url`),
            `min_players` = VALUES(`min_players`),
            `max_players` = VALUES(`max_players`),
            `duration_minutes` = VALUES(`duration_minutes`),
            `status` = 'ACTIVE',
            `display_order` = VALUES(`display_order`)
    ");
    $stmt->execute([
        ':id' => $sc['id'],
        ':name' => $sc['name'],
        ':slug' => $sc['slug'],
        ':description' => $sc['description'],
        ':image_url' => $sc['image_url'],
        ':min_players' => $sc['min_players'],
        ':max_players' => $sc['max_players'],
        ':duration_minutes' => $sc['duration_minutes'],
        ':display_order' => $sc['display_order'],
    ]);

    // 2. Insert/Update Game Configuration
    $cfgJson = json_encode([
        'props' => $sc['props'],
        'clue_count' => count($sc['props']) * 5,
        'bgm_playlist' => $sc['slug'] . '_soundtrack'
    ], JSON_UNESCAPED_UNICODE);

    $cfgStmt = $pdo->prepare("
        INSERT INTO `game_configurations` (`id`, `game_id`, `version`, `configuration`, `description`, `is_active`, `created_by`, `created_at`)
        VALUES (:cfg_id, :game_id, 1, :cfg, :desc_config, 1, 1, NOW())
        ON DUPLICATE KEY UPDATE
            `configuration` = VALUES(`configuration`),
            `description` = VALUES(`description`),
            `is_active` = 1
    ");
    $cfgStmt->execute([
        ':cfg_id' => $sc['id'],
        ':game_id' => $sc['id'],
        ':cfg' => $cfgJson,
        ':desc_config' => $sc['desc_config'],
    ]);

    // 3. Insert/Update Game Prices
    $delPrice = $pdo->prepare("DELETE FROM `game_prices` WHERE `game_id` = :gid");
    $delPrice->execute([':gid' => $sc['id']]);

    $slots = [
        ['p' => $sc['price_morning'], 's' => '09:00:00', 'e' => '13:00:00'],
        ['p' => $sc['price_afternoon'], 's' => '13:00:00', 'e' => '18:00:00'],
        ['p' => $sc['price_evening'], 's' => '18:00:00', 'e' => '23:00:00'],
    ];

    $insPrice = $pdo->prepare("
        INSERT INTO `game_prices` (`game_id`, `price_per_person`, `start_time`, `end_time`, `effective_from`, `effective_until`, `status`, `created_at`, `updated_at`)
        VALUES (:gid, :p, :st, :et, '2026-01-01', '2030-12-31', 'ACTIVE', NOW(), NOW())
    ");

    foreach ($slots as $slot) {
        $insPrice->execute([
            ':gid' => $sc['id'],
            ':p' => $slot['p'],
            ':st' => $slot['s'],
            ':et' => $slot['e'],
        ]);
    }

    echo " + Seeded scenario #{$sc['id']}: {$sc['name']}\n";
}

$count = (int) $pdo->query("SELECT COUNT(*) FROM `games` WHERE `status` = 'ACTIVE'")->fetchColumn();
echo "Success! Total active scenarios in database: {$count}\n";
