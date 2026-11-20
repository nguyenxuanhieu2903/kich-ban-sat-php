<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ReferenceDataSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Idempotent seeding of reference categories and venue physical rooms.
     */
    public function run(): void
    {
        // 1. Categories
        $categories = [
            ['name' => 'Trinh thám / Suy luận', 'slug' => 'trinh-tham-suy-luan', 'description' => 'Tập trung tìm manh mối, giải mã án mạng logic'],
            ['name' => 'Kinh dị / Giật gân', 'slug' => 'kinh-di-giat-gan', 'description' => 'Không khí u ám, yếu tố tâm linh hoặc rùng rợn'],
            ['name' => 'Cổ trang / Cung đấu', 'slug' => 'co-trang-cung-dau', 'description' => 'Bối cảnh hoàng cung, gia tộc lịch sử'],
            ['name' => 'Tình cảm / Tâm lý', 'slug' => 'tinh-cam-tam-ly', 'description' => 'Chiều sâu nhân vật, câu chuyện xúc động'],
            ['name' => 'Hài hước / Giải trí', 'slug' => 'hai-huoc-giai-tri', 'description' => 'Không khí vui vẻ, tương tác đồng đội nhẹ nhàng'],
        ];

        foreach ($categories as $cat) {
            $existing = $this->fetchRow(
                sprintf("SELECT id FROM `categories` WHERE `slug` = '%s' LIMIT 1", $cat['slug'])
            );

            if (!$existing) {
                $this->table('categories')->insert([$cat])->saveData();
            }
        }

        // 2. Physical Rooms at Store Venue
        $rooms = [
            ['code' => 'ROOM_01', 'name' => 'Phòng Án Mạng 01', 'capacity' => 8, 'status' => 'AVAILABLE', 'description' => 'Phòng chơi tiêu chuẩn phong cách cổ điển Châu Âu'],
            ['code' => 'ROOM_02', 'name' => 'Phòng Án Mạng 02', 'capacity' => 8, 'status' => 'AVAILABLE', 'description' => 'Phòng chơi phong cách Á Đông truyền thống'],
            ['code' => 'ROOM_VIP', 'name' => 'Phòng VIP Hoàng Gia', 'capacity' => 10, 'status' => 'AVAILABLE', 'description' => 'Phòng lớn chuyên dụng kịch bản đông người'],
        ];

        foreach ($rooms as $room) {
            $existing = $this->fetchRow(
                sprintf("SELECT id FROM `physical_rooms` WHERE `code` = '%s' LIMIT 1", $room['code'])
            );

            if (!$existing) {
                $this->table('physical_rooms')->insert([$room])->saveData();
            }
        }
    }
}
