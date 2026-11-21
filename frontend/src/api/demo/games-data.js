/**
 * 42 Games Catalog (V16 Business Scope)
 * Includes 3 verified 18+ games. All catalog entries hold verified: false (pending menu audit).
 * Demo attributes are generated illustrative values tagged with is_demo_fill: true.
 */

const RAW_TITLES = [
  { title: 'Cô Gái Bên Cửa Sổ', is18: false, genre: 'Kinh dị' },
  { title: 'Gương', is18: false, genre: 'Tâm lý giật gân' },
  { title: 'Nguyệt Lạc OA', is18: false, genre: 'Trinh thám' },
  { title: 'Sinh Tử', is18: false, genre: 'Hardcore' },
  { title: 'Lý Luận Hư Cấu', is18: false, genre: 'Hardcore' },
  { title: 'Ngôi Nhà Ngọt Ngào', is18: false, genre: 'Kinh dị' },
  { title: 'Yandere 1', is18: false, genre: 'Tâm lý giật gân' },
  { title: 'Yandere 2', is18: false, genre: 'Tâm lý giật gân' },
  { title: 'Tuyết Hương Liên Hoàn Án', is18: false, genre: 'Cổ trang trinh thám' },
  { title: 'Lên Đường', is18: false, genre: 'Cảm xúc' },
  { title: '1 Giờ 30', is18: false, genre: 'Trinh thám' },
  { title: 'Giày Thêu Hoa', is18: false, genre: 'Kinh dị cổ trang' },
  { title: '2/12', is18: false, genre: 'Hardcore' },
  { title: 'Điểm Danh', is18: false, genre: 'Học đường kinh dị' },
  { title: 'Tần Số Cuối', is18: false, genre: 'Khoa học viễn tưởng' },
  { title: 'Vòng Lặp Mưu Sát Đảo Mèo', is18: true, genre: '18+ Giật gân' },
  { title: 'Bệnh Viện Phụ Sản Ái Âu', is18: true, genre: '18+ Kinh dị' },
  { title: 'Cậu Bé Váy Đỏ', is18: true, genre: '18+ Ám ảnh' },
  { title: 'Điều Dân', is18: false, genre: 'Hài hước' },
  { title: 'Tội Diễn Sư', is18: false, genre: 'Trinh thám' },
  { title: 'Kiếm Tiền', is18: false, genre: 'Cơ chế đấu trí' },
  { title: 'Cuộc Gọi Đến', is18: false, genre: 'Tâm lý giật gân' },
  { title: 'Thiên Hạ Đệ Nhất Tửu', is18: false, genre: 'Cổ trang kiếm hiệp' },
  { title: 'Ly Hôn Đi! Nhanh Lên!', is18: false, genre: 'Hài hước hoan lạc' },
  { title: '100 Cách Chết NYC', is18: false, genre: 'Hài hước hoan lạc' },
  { title: 'Thông Báo Tìm Người', is18: false, genre: 'Kinh dị tâm lý' },
  { title: 'Thược Dược Đen', is18: false, genre: 'Trinh thám châu Âu' },
  { title: 'Niên Luân', is18: false, genre: 'Hardcore kinh điển' },
  { title: 'Vụ Nhà Quán', is18: false, genre: 'Bí ẩn' },
  { title: 'Yandere 3', is18: false, genre: 'Tâm lý giật gân' },
  { title: 'Thanh Lâu', is18: false, genre: 'Cổ trang' },
  { title: 'Hậu Cung', is18: false, genre: 'Cung đấu' },
  { title: 'Thanh Thanh Man', is18: false, genre: 'Cảm xúc cổ phong' },
  { title: 'Khách Điếm Hồ Ly', is18: false, genre: 'Huyền huyễn' },
  { title: 'Lý Xuyên Quái Đàm Bộ', is18: false, genre: 'Kỳ bí Nhật Bản' },
  { title: 'Đền Thần Shimizu Hachiman', is18: false, genre: 'Kỳ bí Nhật Bản' },
  { title: 'Xin Chào', is18: false, genre: 'Cảm xúc gia đình' },
  { title: 'Thi Khúc Li Biệt', is18: false, genre: 'Cảm xúc bi kịch' },
  { title: 'Dư Hương', is18: false, genre: 'Cảm xúc' },
  { title: 'Cổ Thụ Ngân Nga', is18: false, genre: 'Huyền bí' },
  { title: 'Martinez Chết Tại Kinh Kỳ Quán', is18: false, genre: 'Trinh thám phong cách Nhật' },
  { title: 'Lập Phương Quán', is18: false, genre: 'Mật thất suy luận' },
];

export const GAMES_CATALOG = RAW_TITLES.map((item, idx) => {
  const id = `game_${String(idx + 1).padStart(3, '0')}`;
  const minPlayers = 5 + (idx % 4); // 5, 6, 7, 8
  const maxPlayers = minPlayers + (idx % 2 === 0 ? 0 : 1);
  const durationMinutes = 210 + (idx % 3) * 30; // 210, 240, 270 phút
  const difficulty = 3 + (idx % 3); // 3, 4, 5
  
  // 2 khung giá theo thời điểm trong ngày
  const priceDayVnd = 180000 + (idx % 4) * 20000; // 180k - 240k
  const priceNightVnd = priceDayVnd + 30000;      // Khung tối phụ thu 30k

  return {
    id,
    code: `KBS-${String(idx + 1).padStart(2, '0')}`,
    title: item.title,
    cover_url: `./assets/images/covers/${id}.jpg`,
    is_age_restricted: item.is18,
    genre: item.genre,
    verified: false, // Bắt buộc: Tên chờ đối chiếu với menu gốc của quán
    is_demo_fill: true, // Nhãn: Thông số minh họa
    description: `Bối cảnh kịch bản "${item.title}": Một câu chuyện nhập vai nhiều lớp lang với các tình tiết gay cấn, bí mật cá nhân và những khúc mắc cần các thành viên cùng giải mã.`,
    min_players: minPlayers,
    max_players: maxPlayers,
    duration_minutes: durationMinutes,
    difficulty,
    price_day_vnd: priceDayVnd,
    price_night_vnd: priceNightVnd,
    pricing_model: {
      day: { window: '09:00–19:59', price_vnd: priceDayVnd },
      night: { window: '20:00 trở đi', price_vnd: priceNightVnd },
    },
    status: 'ACTIVE',
  };
});
