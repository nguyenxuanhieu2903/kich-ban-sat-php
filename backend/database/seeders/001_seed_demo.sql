-- Demo accounts (mật khẩu chung: 12345678)
INSERT INTO users (full_name, email, password_hash, phone, role, student_verified) VALUES
('Nguyễn Minh Quân', 'player@kbs.local', 'DAN_HASH_BCRYPT_VAO_DAY', '0901000001', 'PLAYER', 1),
('Trần Hải Đăng (DM)', 'dm@kbs.local', 'DAN_HASH_BCRYPT_VAO_DAY', '0901000002', 'DM', 0),
('Lê Quản Trị', 'admin@kbs.local', 'DAN_HASH_BCRYPT_VAO_DAY', '0901000003', 'ADMIN', 0);

INSERT INTO physical_rooms (name, capacity, theme) VALUES
('Phòng VIP Salon Cổ Điển', 8, 'Dark Luxury'),
('Phòng Á Đông Đèn Lồng Đỏ', 9, 'Oriental Mystery');

INSERT INTO games (title, slug, category, description, min_players, max_players, duration_minutes, is_18_plus, price_day_vnd, price_evening_vnd) VALUES
('Cô Gái Bên Cửa Sổ', 'co-gai-ben-cua-so', 'Kinh dị', 'Án mạng bí ẩn xoay quanh căn biệt thự cổ và khung cửa sổ tầng hai.', 5, 6, 210, 0, 150000, 180000),
('Gương', 'guong', 'Tâm lý', 'Khi chiếc gương bạc phản chiếu những bí mật không ai muốn thừa nhận.', 6, 6, 240, 0, 160000, 190000),
('1 Giờ 30', '1-gio-30', 'Trinh thám', 'Cuộc gọi lúc 1 giờ 30 sáng mở ra chuỗi suy luận nghẹt thở.', 5, 7, 210, 0, 150000, 180000),
('Vòng Lặp Mưu Sát Đảo Mèo (18+)', 'vong-lap-muu-sat-dao-meo', 'Kinh dị', 'Hòn đảo cô lập giữa cơn bão và vòng lặp thời gian đẫm máu.', 6, 7, 270, 1, 180000, 220000),
('Khách Điếm Hồ Ly', 'khach-diem-ho-ly', 'Cổ trang', 'Đêm sương mù tại khách điếm ven rừng với truyền thuyết hồ ly chín đuôi.', 6, 8, 240, 0, 160000, 195000);