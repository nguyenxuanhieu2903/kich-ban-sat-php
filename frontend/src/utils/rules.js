/**
 * Business Rules, Constants, and Open Decisions (V16 Freeze Baseline)
 */

export const RULES = {
  // Deposit: Cố định 50.000 VNĐ / người, KHÔNG tính theo %
  DEPOSIT_VND: 50000,

  // Cut-off hoàn cọc: Hủy trước giờ bắt đầu Session >= 24 giờ mới đủ điều kiện xét hoàn cọc
  REFUND_CUTOFF_HOURS: 24,

  // Giới hạn phòng active tối đa của 1 creator
  MAX_ACTIVE_ROOMS: 3,

  // Thời gian gom người thành lập phòng (Formation)
  FORMATION_HOURS: 4,

  // Hạn cọc cho TỪNG participant: 2 giờ riêng biệt
  DEPOSIT_WINDOW_HOURS: 2,
  DEPOSIT_REMINDERS_MINUTES: [60, 30],

  // Replacement window khi có người xin rời/hủy
  REPLACEMENT_MIN_MINUTES: 30,
  REPLACEMENT_MAX_HOURS: 4,

  // Thời gian chuẩn bị tối thiểu cho DM
  DM_PREP_MIN_HOURS: 12,

  // Hạn biểu quyết đề xuất đổi lịch: 2 giờ, cần 100% active đồng ý
  SCHEDULE_PROPOSAL_WINDOW_HOURS: 2,

  // Giờ bắt đầu hợp lệ: chỉ phút :00 hoặc :30
  SLOT_MINUTES: [0, 30],

  // Đặt trước tối thiểu (tạm dùng 24 giờ - PROVISIONAL)
  ADVANCE_BOOKING_HOURS: 24,

  // Khung giờ giá cơ sở
  TIME_WINDOWS: {
    DAY_START: '09:00',
    DAY_END: '19:59',
    NIGHT_START: '20:00',
  },

  // Ưu đãi thứ 3: Giảm 30% mọi game, đặt trước >= 24h, áp dụng 09:00 - 19:00
  TUESDAY_PROMOTION: {
    DISCOUNT_PERCENT: 30,
    DAY_OF_WEEK: 2, // Tuesday (0 is Sunday, 2 is Tuesday)
    APPLICABLE_START: '09:00',
    APPLICABLE_END: '19:00',
    MIN_ADVANCE_HOURS: 24,
  },

  // Ưu đãi HSSV: Giảm 20.000đ/người, Thứ 2 đến Thứ 5, 09:00 - 19:00
  STUDENT_PROMOTION: {
    DISCOUNT_PER_PERSON_VND: 20000,
    DAYS_OF_WEEK: [1, 2, 3, 4], // Mon to Thu
    APPLICABLE_START: '09:00',
    APPLICABLE_END: '19:00',
  },
};

/**
 * 13 Điểm còn OPEN tuyệt đối không tự ý chốt trên UI
 */
export const OPEN_DECISIONS = [
  { id: 1, title: 'Các trường GameRoom được phép chỉnh sửa sau khi tạo' },
  { id: 2, title: 'Cách biểu diễn và validate điều kiện riêng của từng game' },
  { id: 3, title: 'Chu kỳ polling chuẩn cho các màn hình theo dõi' },
  { id: 4, title: 'Khoảng thời gian đệm (buffer) an toàn giữa các ca chơi liên tiếp' },
  { id: 5, title: 'Chính sách cho phép join/rejoin sát giờ Session diễn ra' },
  { id: 6, title: 'Cơ chế Versioning khi Admin cập nhật cấu hình game đang có phòng mở' },
  { id: 7, title: 'Thứ tự ưu tiên giữa Ưu đãi thứ 3 và Ưu đãi HSSV khi thỏa mãn đồng thời' },
  { id: 8, title: 'Cách tính giá khi phiên chơi vắt qua 2 khung giờ (09:00-19:59 và từ 20:00)' },
  { id: 9, title: 'Quy ước "đặt trước ít nhất 1 ngày" là đủ 24 giờ hay tính theo ngày lịch' },
  { id: 10, title: 'Chính sách hủy/hoàn chi tiết trong các ca quán/DM hủy, no-show, hủy 1 phần' },
  { id: 11, title: 'Đối chiếu và chuẩn hóa 42 tên kịch bản với menu gốc của quán' },
  { id: 12, title: 'Nội dung ngoại lệ ưu đãi vào các ngày lễ tết' },
  { id: 13, title: 'Phương thức xác minh thẻ Học sinh - Sinh viên (HSSV) ngoài đời thực' },
];

/**
 * Kiểm tra giờ bắt đầu hợp lệ (đúng phút :00 hoặc :30)
 */
export function isValidSlotTime(timeString) {
  if (!timeString || typeof timeString !== 'string') return false;
  const parts = timeString.split(':');
  if (parts.length < 2) return false;
  const minutes = parseInt(parts[1], 10);
  return minutes === 0 || minutes === 30;
}

/**
 * Kiểm tra tính hợp lệ của Replacement Window
 * Điều kiện: Từ 30 phút đến 4 giờ VÀ không vượt quá giờ bắt đầu Session
 */
export function validateReplacementWindow(minutes, sessionStartTime = null, nowTime = new Date()) {
  const minMins = RULES.REPLACEMENT_MIN_MINUTES; // 30
  const maxMins = RULES.REPLACEMENT_MAX_HOURS * 60; // 240

  if (minutes < minMins) {
    return { valid: false, error: `Thời gian tìm người thay thế tối thiểu là ${minMins} phút.` };
  }
  if (minutes > maxMins) {
    return { valid: false, error: `Thời gian tìm người thay thế tối đa là ${RULES.REPLACEMENT_MAX_HOURS} giờ (${maxMins} phút).` };
  }

  if (sessionStartTime) {
    const sessionDate = new Date(sessionStartTime);
    const windowEndDate = new Date(nowTime.getTime() + minutes * 60 * 1000);
    if (windowEndDate > sessionDate) {
      return { valid: false, error: 'Hạn tìm người thay thế không được vượt quá giờ bắt đầu của ca chơi.' };
    }
  }

  return { valid: true };
}

/**
 * Kiểm tra các hành động Admin/DM bắt buộc phải cung cấp lý do
 */
export function isReasonRequired(actionKey) {
  const REQUIRED_ACTIONS = [
    'DM_REJECT_ASSIGNMENT',
    'ADMIN_REVOKE_ASSIGNMENT',
    'ADMIN_CANCEL_ROOM',
    'ADMIN_REOPEN_RECRUITMENT',
    'ADMIN_APPROVE_LEAVE_REQUEST',
    'ADMIN_REJECT_LEAVE_REQUEST',
    'ADMIN_EMERGENCY_CANCEL_SESSION',
    'ADMIN_REJECT_REFUND',
  ];
  return REQUIRED_ACTIONS.includes(actionKey);
}

/**
 * Kiểm tra khoảng đệm chuẩn bị tối thiểu 12 giờ cho DM (V16)
 * Buffer >= 12h giữa lúc DM nhận/xác nhận ca và giờ bắt đầu phiên chơi.
 */
export function checkDmPrepBuffer(sessionStartTime, now = new Date()) {
  if (!sessionStartTime) {
    return { valid: false, hoursRemaining: 0, violated: true, requiredHours: RULES.DM_PREP_MIN_HOURS };
  }
  const sessionDate = new Date(sessionStartTime);
  const diffMs = sessionDate.getTime() - new Date(now).getTime();
  const hoursRemaining = diffMs / (1000 * 60 * 60);
  const violated = hoursRemaining < RULES.DM_PREP_MIN_HOURS;
  return {
    valid: !violated,
    violated,
    hoursRemaining: Math.max(0, Math.round(hoursRemaining * 10) / 10),
    requiredHours: RULES.DM_PREP_MIN_HOURS,
  };
}
