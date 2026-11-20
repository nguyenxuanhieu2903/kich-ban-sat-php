<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ReferenceDataSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Idempotent seeding of essential reference venue and physical rooms.
     */
    public function run(): void
    {
        // 1. Primary Venue
        $venueCode = 'VENUE_01';
        $existingVenue = $this->fetchRow(
            sprintf("SELECT id FROM `venues` WHERE `code` = '%s' LIMIT 1", $venueCode)
        );

        if (!$existingVenue) {
            $this->table('venues')->insert([
                [
                    'code' => $venueCode,
                    'name' => 'Kịch Bản Sát Station - Chi nhánh Trung Tâm',
                    'address' => '123 Nguyễn Huệ, P. Bến Nghé, Quận 1, TP. Hồ Chí Minh',
                    'phone' => '0901234567',
                    'opening_hours' => '09:00 - 23:00',
                    'is_active' => 1,
                ]
            ])->saveData();
            $venueId = (int) $this->getAdapter()->getConnection()->lastInsertId();
        } else {
            $venueId = (int) $existingVenue['id'];
        }

        // 2. Physical Rooms at Store Venue
        $rooms = [
            [
                'venue_id' => $venueId,
                'code' => 'ROOM_01',
                'name' => 'Phòng Án Mạng 01',
                'capacity' => 8,
                'status' => 'AVAILABLE',
                'description' => 'Phòng chơi tiêu chuẩn phong cách cổ điển Châu Âu',
            ],
            [
                'venue_id' => $venueId,
                'code' => 'ROOM_02',
                'name' => 'Phòng Án Mạng 02',
                'capacity' => 8,
                'status' => 'AVAILABLE',
                'description' => 'Phòng chơi phong cách Á Đông truyền thống',
            ],
            [
                'venue_id' => $venueId,
                'code' => 'ROOM_VIP',
                'name' => 'Phòng VIP Hoàng Gia',
                'capacity' => 10,
                'status' => 'AVAILABLE',
                'description' => 'Phòng lớn chuyên dụng kịch bản đông người',
            ],
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
