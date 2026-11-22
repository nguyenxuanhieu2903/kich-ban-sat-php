export const PHYSICAL_ROOMS = [
  { id: 'PR-01', name: 'Phòng Mật Thất 01 — Tầng 2', capacity: 8, status: 'occupied', currentBookingId: 'KBS-104' },
  { id: 'PR-02', name: 'Phòng Biệt Thự Cổ 02 — Tầng 2', capacity: 8, status: 'available', currentBookingId: null },
  { id: 'PR-03', name: 'Phòng Án Mạng Đêm 03 — Tầng 3', capacity: 7, status: 'available', currentBookingId: null },
  { id: 'PR-04', name: 'Phòng Gương Vỡ 04 — Tầng 3', capacity: 6, status: 'maintenance', currentBookingId: null },
  { id: 'PR-05', name: 'Phòng Thẩm Vấn 05 — Tầng 4', capacity: 8, status: 'available', currentBookingId: null }
];

const FEATURED_SCRIPTS = [
  {
    id: 'SCR-01',
    code: 'KBS-YANDERE',
    title: 'Yandere: Lời Thề Máu',
    genre: 'Tâm lý / Kinh dị',
    playersMin: 6,
    playersMax: 8,
    maleCount: 4,
    femaleCount: 4,
    difficulty: 'Khó',
    durationHours: 3.5,
    price: 129000,
    is18Plus: true,
    isActive: true,
    featured: true,
    bookingsCount: 148,
    summary: 'Bữa tiệc kỷ niệm 5 năm của câu lạc bộ văn học biến thành phòng kín đẫm máu khi cuốn nhật ký tình yêu bị xé mất trang cuối.',
    hook: 'Một trong tám người yêu đến mức sẵn sàng giết tất cả những kẻ còn lại.'
  },
  {
    id: 'SCR-02',
    code: 'KBS-GUONG',
    title: 'Gương: Phản Chiếu Tội Ác',
    genre: 'Trinh thám / Siêu nhiên',
    playersMin: 5,
    playersMax: 7,
    maleCount: 4,
    femaleCount: 3,
    difficulty: 'Trung bình',
    durationHours: 3.5,
    price: 129000,
    is18Plus: false,
    isActive: true,
    featured: true,
    bookingsCount: 132,
    summary: 'Tấm gương cổ từ thế kỷ 19 trong dinh thự họ Trần phản chiếu không phải khuôn mặt hiện tại, mà là tội lỗi cách đây 10 năm.',
    hook: 'Đừng nhìn vào gương sau 12 giờ đêm nếu tay bạn từng vấy bẩn.'
  },
  {
    id: 'SCR-03',
    code: 'KBS-SINHTU',
    title: 'Sinh Tử: Chuyến Tàu Cuối',
    genre: 'Sinh tồn / Đấu trí',
    playersMin: 6,
    playersMax: 8,
    maleCount: 5,
    femaleCount: 3,
    difficulty: 'Khó',
    durationHours: 4.0,
    price: 139000,
    is18Plus: true,
    isActive: true,
    featured: true,
    bookingsCount: 119,
    summary: 'Chuyến tàu tốc hành xuyên đêm mắc kẹt giữa hầm núi sập. Chỉ có 7 mặt nạ dưỡng khí cho 8 hành khách mang quá khứ bất hảo.',
    hook: 'Muốn sống sót, bạn phải chứng minh kẻ bên cạnh đáng chết hơn mình.'
  },
  {
    id: 'SCR-04',
    code: 'KBS-SO7',
    title: 'Căn Phòng Số 7',
    genre: 'Cổ điển / Phá án',
    playersMin: 5,
    playersMax: 6,
    maleCount: 3,
    femaleCount: 3,
    difficulty: 'Dễ',
    durationHours: 3.0,
    price: 119000,
    is18Plus: false,
    isActive: true,
    featured: true,
    bookingsCount: 164,
    summary: 'Vị tỷ phú lập dị qua đời ngay trước giờ công bố di chúc trong căn phòng khóa trái từ bên trong.',
    hook: 'Mỗi người thừa kế đều có bằng chứng ngoại phạm hoàn hảo — trừ một kẻ nói dối.'
  },
  {
    id: 'SCR-05',
    code: 'KBS-HOANGHON',
    title: 'Biệt Thự Hoàng Hôn',
    genre: 'Gia tộc / Tâm lý',
    playersMin: 7,
    playersMax: 8,
    maleCount: 4,
    femaleCount: 4,
    difficulty: 'Trung bình',
    durationHours: 3.5,
    price: 129000,
    is18Plus: false,
    isActive: true,
    featured: true,
    bookingsCount: 95,
    summary: 'Cơn bão cô lập hòn đảo tư nhân đúng đêm gia tộc họ Vũ họp mặt chia gia tài nghìn tỷ.',
    hook: 'Tiếng súng vang lên lúc mất điện chỉ là phát súng mở màn.'
  },
  {
    id: 'SCR-06',
    code: 'KBS-MATNA',
    title: 'Vũ Hội Mặt Nạ Đỏ',
    genre: 'Cổ trang / Âm mưu',
    playersMin: 6,
    playersMax: 8,
    maleCount: 4,
    femaleCount: 4,
    difficulty: 'Cực khó',
    durationHours: 4.0,
    price: 149000,
    is18Plus: true,
    isActive: true,
    featured: true,
    bookingsCount: 88,
    summary: 'Đêm hội hoa đăng kinh thành, thích khách trà trộn vào nhóm quan lại cao cấp nhằm đánh cắp mật chiếu.',
    hook: 'Dưới lớp mặt nạ lụa là nụ cười của đồng minh hay lưỡi dao của kẻ phản bội?'
  }
];

const EXTRA_TITLES = [
  'Bóng Ma Nhà Hát Lớn', 'Hồ Sơ Mật Vụ 1998', 'Bữa Tiệc Ly Biệt', 'Lời Nguyền Huyết Ngọc',
  'Đêm Trắng Ở Đà Lạt', 'Kẻ Thừa Kế Cuối Cùng', 'Mật Mã Đồng Hồ Cát', 'Bệnh Viện Tâm Thần Cũ',
  'Chuyến Bay MH-09', 'Án Mạng Trên Du Thuyền', 'Nhật Ký Cô Dâu Đen', 'Trò Chơi Của Quỷ',
  'Ngôi Làng Không Bóng Người', 'Bản Giao Hưởng Chết Chóc', 'Thẩm Phán Bóng Đêm', 'Vụ Án Phòng Kín Số 4',
  'Mùa Đông Không Tuyết', 'Ký Ức Bị Đánh Cắp', 'Hội Kín Hoa Hồng Đen', 'Người Đàn Ông Không Tên',
  'Bức Thư Từ Địa Ngục', 'Sòng Bạc Hoàng Gia', 'Cái Chết Của Vị Giáo Sư', 'Lâu Đài Sương Mù',
  'Bí Mật Tầng Hầm B2', 'Đêm Giao Thừa Đẫm Máu', 'Ảo Thuật Gia Biến Mất', 'Vết Son Trên Ly Rượu',
  'Kẻ Săn Đêm', 'Hợp Đồng Với Tử Thần', 'Trường Nội Trú Mưa Rơi', 'Chứng Nhân Cuối Cùng',
  'Ngọn Hải Đăng Cô Độc', 'Báu Vật Triều Nguyễn', 'Cuộc Gọi Lúc 3 Giờ Sáng', 'Mê Cung Ký Ức'
];

const GENRES = ['Trinh thám / Siêu nhiên', 'Tâm lý / Kinh dị', 'Cổ điển / Phá án', 'Sinh tồn / Đấu trí', 'Gia tộc / Tâm lý', 'Cổ trang / Âm mưu'];
const DIFFICULTIES = ['Dễ', 'Trung bình', 'Khó', 'Cực khó'];

const GENERATED_SCRIPTS = EXTRA_TITLES.map((title, idx) => {
  const num = idx + 7;
  const playersMax = [5, 6, 7, 8][idx % 4];
  const playersMin = Math.max(5, playersMax - 1);
  const maleCount = Math.ceil(playersMax / 2);
  const femaleCount = playersMax - maleCount;
  const is18Plus = idx % 3 === 0;
  return {
    id: `SCR-${String(num).padStart(2, '0')}`,
    code: `KBS-${100 + num}`,
    title,
    genre: GENRES[idx % GENRES.length],
    playersMin,
    playersMax,
    maleCount,
    femaleCount,
    difficulty: DIFFICULTIES[idx % DIFFICULTIES.length],
    durationHours: idx % 2 === 0 ? 3.5 : 3.0,
    price: is18Plus ? 139000 : 129000,
    is18Plus,
    isActive: true,
    featured: false,
    bookingsCount: 25 + ((idx * 7) % 70),
    summary: `Vụ án bí ẩn mang tên "${title}" đặt ${playersMax} nghi phạm vào tình thế buộc phải lật mở từng lớp manh mối trước khi hung thủ xóa sạch dấu vết.`,
    hook: 'Sự thật luôn ẩn sau lời khai tưởng chừng vô hại nhất.'
  };
});

export const ALL_SCRIPTS = [...FEATURED_SCRIPTS, ...GENERATED_SCRIPTS];