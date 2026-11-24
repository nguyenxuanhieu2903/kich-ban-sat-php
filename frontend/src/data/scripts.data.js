// src/data/scripts.data.js
// Dữ liệu dự phòng ngoại tuyến (Không cài đặt hình ảnh theo quy chuẩn hệ thống)
export const SCENARIOS = [
  {
    id: 1,
    title: 'Đêm Đoạt Mệnh Tại Trang Viên',
    genre: 'Suy luận phá án',
    difficulty: 'Trung bình',
    theme_code: 'DETECTIVE',
    duration: '4 tiếng (240p)',
    price: 180000,
    is18Plus: false,
    players: { total: '6-8', male: 3, female: 3 },
    summary: 'Một bữa tiệc gia tộc bí ẩn nơi người đứng đầu bị sát hại trong đêm giông bão. Tám vị khách bị cô lập giữa dinh thự cổ với những bí mật huyết thống không thể tiết lộ.'
  },
  {
    id: 2,
    title: 'Biệt Thự Huyết Tộc',
    genre: 'Kinh dị tâm linh',
    difficulty: 'Khó',
    theme_code: 'VAMPIRE',
    duration: '3.5 tiếng (210p)',
    price: 200000,
    is18Plus: true,
    players: { total: '5-7', male: 3, female: 2 },
    summary: 'Cuộc đối đầu tâm lý nghẹt thở giữa các nhánh ma cà rồng cổ đại khi chén thánh ngàn năm biến mất ngay trước nghi lễ thức tỉnh.'
  },
  {
    id: 3,
    title: 'Án Mạng Trên Chuyến Tàu Tuyết',
    genre: 'Suy luận phá án',
    difficulty: 'Khó',
    theme_code: 'TRAIN',
    duration: '4 tiếng (240p)',
    price: 200000,
    is18Plus: false,
    players: { total: '6-8', male: 3, female: 3 },
    summary: 'Chuyến tàu xuyên Bắc Cực mắc kẹt giữa bão tuyết trắng xóa. Một thi thể được phát hiện trong toa hạng nhất khóa kín từ bên trong.'
  },
  {
    id: 4,
    title: 'Kỳ Án Trường An',
    genre: 'Suy luận phá án',
    difficulty: 'Khó',
    theme_code: 'ORIENTAL',
    duration: '4 tiếng (240p)',
    price: 190000,
    is18Plus: false,
    players: { total: '6-8', male: 3, female: 3 },
    summary: 'Đêm hội hoa đăng kinh thành, vị Thượng thư bộ Hình trúng độc gục chết giữa yến tiệc. Ân oán triều đình buộc các nhân vật phải giải án trước canh năm.'
  },
  {
    id: 11,
    title: 'Vụ Trộm Đề Thi Học Đường',
    genre: 'Cơ chế, hài hước',
    difficulty: 'Dễ',
    theme_code: 'SCHOOL',
    duration: '3 tiếng (180p)',
    price: 160000,
    is18Plus: false,
    players: { total: '5-7', male: 3, female: 2 },
    summary: 'Bộ đề thi học kỳ đặc biệt biến mất khỏi két sắt phòng giáo vụ trước giờ thi một đêm. Sáu thành viên hội học sinh vừa đấu trí vừa che giấu bí mật riêng.'
  },
  {
    id: 15,
    title: 'Thư Gửi Người Ở Lại',
    genre: 'Tình cảm, tâm lý xã hội',
    difficulty: 'Dễ',
    theme_code: 'MODERN',
    duration: '4 tiếng (240p)',
    price: 180000,
    is18Plus: false,
    players: { total: '6-7', male: 3, female: 3 },
    summary: 'Bảy người bạn cũ hội ngộ tại tiệm cà phê sương mù Đà Lạt theo di nguyện của người bạn đã khuất, cùng mở chiếc hộp thiếc chôn dưới gốc thông già.'
  }
];
