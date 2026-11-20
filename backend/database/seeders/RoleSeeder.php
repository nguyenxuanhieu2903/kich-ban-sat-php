<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class RoleSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Idempotent seeding of essential system roles.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'ADMIN',
                'label' => 'Quản trị viên',
                'description' => 'Toàn quyền quản trị và điều phối hệ thống',
            ],
            [
                'name' => 'DM',
                'label' => 'Quản trò (Dungeon Master)',
                'description' => 'Quản lý phiên chơi, kịch bản và dẫn truyện',
            ],
            [
                'name' => 'PLAYER',
                'label' => 'Người chơi',
                'description' => 'Người dùng tham gia ghép đội, đặt phòng và chơi game',
            ],
        ];

        foreach ($roles as $role) {
            $existing = $this->fetchRow(
                sprintf("SELECT id FROM `roles` WHERE `name` = '%s' LIMIT 1", $role['name'])
            );

            if (!$existing) {
                $this->table('roles')->insert([$role])->saveData();
            } else {
                $this->execute(sprintf(
                    "UPDATE `roles` SET `label` = '%s', `description` = '%s', `updated_at` = NOW() WHERE `name` = '%s'",
                    addslashes($role['label']),
                    addslashes($role['description']),
                    $role['name']
                ));
            }
        }
    }
}
