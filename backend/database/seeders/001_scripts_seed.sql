-- Seeder: 001_scripts_seed.sql
-- Description: Dữ liệu mẫu kịch bản thực tế từ hệ thống X - Kịch Bản Sát HCM
-- Author: TV2 (Backend Developer)

INSERT INTO `scripts` (`code`, `title`, `genre`, `description`, `min_players`, `max_players`, `male_players`, `female_players`, `duration_minutes`, `price_per_player`, `difficulty`, `cover_image`, `status`)
VALUES
(
    'KBS-01',
    'Sương Mù Biệt Thự Cổ',
    'Kinh dị',
    'Đêm giông bão tại dinh thự biệt lập vùng ngoại ô, chủ nhân gia tộc đột ngột tử vong trong căn phòng kín bị khóa trái từ bên trong. Người thừa kế, quản gia và khách viếng thăm mang theo những toan tính đẫm máu.',
    6,
    6,
    3,
    3,
    240,
    220000,
    4,
    'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=800&q=80',
    'ACTIVE'
),
(
    'KBS-02',
    'Trường Nhạc Cung',
    'Cổ trang',
    'Đêm yến tiệc Trung thu chốn hoàng cung lộng lẫy, chén rượu ngự ban bất ngờ chứa độc dược đoạt mệnh. Một sủng phi qua đời bí ẩn, khơi mào cho cuộc huyết chiến tranh quyền đoạt vị giữa chốn thâm cung.',
    7,
    7,
    4,
    3,
    270,
    250000,
    5,
    'https://images.unsplash.com/photo-1578632767115-351597cf2477?auto=format&fit=crop&w=800&q=80',
    'ACTIVE'
),
(
    'KBS-03',
    'Chuyến Tàu Định Mệnh',
    'Trinh thám',
    'Trên chuyến tàu đêm xuyên bão tuyết vùng Siberia hẻo lánh, một thương gia giàu có biến mất bí ẩn cùng kho báu vô giá. 6 hành khách trên toa VIP bị kẹt lại cùng nhau với 6 bản lời khai đầy mâu thuẫn.',
    6,
    6,
    4,
    2,
    210,
    200000,
    5,
    'https://images.unsplash.com/photo-1517649763962-0c623266ddc0?auto=format&fit=crop&w=800&q=80',
    'ACTIVE'
),
(
    'KBS-04',
    'Đêm Hội Trộm Trăng',
    'Hài hước',
    'Một khách sạn cổ kính bất ngờ bị mất viên ngọc Dạ Minh Châu quý giá. Trong số những người có mặt, ai cũng tự xưng là thám tử đại tài nhưng trong túi ai cũng giấu dụng cụ bẻ khóa và sơ đồ tẩu thoát dở khóc dở cười.',
    8,
    8,
    4,
    4,
    180,
    180000,
    3,
    'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?auto=format&fit=crop&w=800&q=80',
    'ACTIVE'
),
(
    'KBS-05',
    'Ký Túc Xá Nữ Tầng 4',
    'Kinh dị',
    'Căn phòng 404 từng bị niêm phong nhiều năm sau vụ tai nạn bí ẩn của một nữ sinh viên. Một đêm mưa gió, nhóm bạn trẻ tò mò mở cửa làm lễ cầu cơ và phát hiện những bí mật đen tối của trường học.',
    5,
    5,
    2,
    3,
    210,
    200000,
    4,
    'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?auto=format&fit=crop&w=800&q=80',
    'ACTIVE'
),
(
    'KBS-06',
    'Năm Tháng Ấy Từng Có Nhau',
    'Cảm xúc',
    'Buổi họp lớp sau 10 năm ra trường mở lại bức thư gửi từ quá khứ, hé lộ những ký ức thanh xuân tươi đẹp cùng những bí mật, hiểu lầm và vết thương lòng chưa từng được giãi bày của tuổi trẻ.',
    6,
    6,
    3,
    3,
    240,
    220000,
    3,
    'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?auto=format&fit=crop&w=800&q=80',
    'ACTIVE'
)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `price_per_player` = VALUES(`price_per_player`);
