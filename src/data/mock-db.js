export const DM_SEED = {
  DEPOSIT_AMOUNT: 50000,
  MIN_CHECKED_IN_TO_START: 4,
  DM_NAMES: ['Quản trò Minh', 'Quản trò Lan', 'Quản trò Khoa'],
};

export const ADMIN_SEED = {
  REFUND_MIN_HOURS: 24,
  NIGHT_SURCHARGE: 20000,
  DIFFICULTIES: ['Dễ', 'Trung bình', 'Khó'],
  PRICE_SLOTS: [
    { code: 'DAY', label: 'Ban ngày (09:00 – 19:59)' },
    { code: 'NIGHT', label: 'Buổi tối (20:00 – 23:00)' },
  ],
  VENUE: {
    name: 'Kịch Bản Sát — Bình Thạnh',
    address: '36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM',
    phone: '091.569.0033',
    open: '09:00',
    close: '23:00',
  },

  PHYSICAL_ROOMS: [
    ['Phòng Hồ Sơ Đỏ', 8],
    ['Phòng Hiện Trường', 8],
    ['Phòng Mật Thất', 10],
    ['Phòng Nhỏ A', 6],
  ],
  PLAYER_NAMES: [
    'Nguyễn Minh Thư', 'Trần Quốc Bảo', 'Lê Hà Anh', 'Phạm Tuấn Kiệt', 'Hoàng Ngọc Lan', 'Đỗ Đức Minh',
    'Vũ Phương Linh', 'Bùi Gia Huy', 'Đặng Thu Hà', 'Ngô Anh Tuấn', 'Dương Mỹ Linh', 'Lý Hoàng Nam',
    'Phan Khánh Vy', 'Trịnh Bảo Châu', 'Mai Thanh Tâm', 'Đinh Quang Huy', 'Tạ Ngọc Ánh', 'Võ Minh Khôi',
  ],
};
