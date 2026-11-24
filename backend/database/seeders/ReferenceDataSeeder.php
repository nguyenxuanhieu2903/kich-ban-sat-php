<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ReferenceDataSeeder extends AbstractSeed
{
    /**
     * Nạp dữ liệu chi nhánh và phòng vật lý tham chiếu đúng cấu trúc bảng V16.
     */
    public function run(): void
    {
        $this->execute("
            INSERT INTO `venues` (`id`, `name`, `address`, `phone`, `description`, `status`)
            VALUES
                (1, 'Kịch Bản Sát Station - Chi nhánh Quận 1', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh', '0901234567', 'Chi nhánh trung tâm với không gian nhập vai phong cách Châu Âu cổ điển', 'ACTIVE'),
                (2, 'Kịch Bản Sát Hub - Chi nhánh Bình Thạnh', '36/52 Nguyễn Gia Trí, Phường 25, Quận Bình Thạnh, TP. Hồ Chí Minh', '0915690033', 'Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông', 'ACTIVE')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`), `phone` = VALUES(`phone`);
        ");

        $this->execute("
            INSERT INTO `physical_rooms` (`id`, `venue_id`, `name`, `capacity`, `description`, `status`, `is_maintenance`, `theme_code`)
            VALUES
                (1, 1, 'Phòng Sherlock Holmes', 8, 'Phòng phong cách phòng khách quý tộc Anh quốc, ánh sáng ấm cúng', 'ACTIVE', 0, 'DETECTIVE'),
                (2, 1, 'Phòng VIP Huyết Tộc Dracula', 10, 'Phòng VIP rộng rãi, hệ thống âm thanh vòm và đèn đổi màu theo bối cảnh', 'ACTIVE', 0, 'VAMPIRE'),
                (3, 2, 'Phòng Cổ Trang Á Đông', 8, 'Bối cảnh dinh thự phong kiến, trà cụ và phục trang kèm theo', 'ACTIVE', 0, 'ORIENTAL')
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `theme_code` = VALUES(`theme_code`);
        ");
    }
}
