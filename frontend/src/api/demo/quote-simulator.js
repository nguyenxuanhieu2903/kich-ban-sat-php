/**
 * Production-Grade Quote Simulator (V16 Business Freeze)
 * Implements strict calculation rules without guessing open decisions.
 */
import { RULES } from '../../utils/rules.js';

export function simulateQuote({
  game,
  sessionStartTime,      // ISO string or 'YYYY-MM-DDTHH:mm:ss'
  playerCount,           // Integer
  isStudentVerified = false, // From backend user profile
  bookingCreatedTime = new Date(), // For advance booking checks
}) {
  if (!game || !sessionStartTime || !playerCount) {
    throw new Error('Dữ liệu không đầy đủ để tính báo giá.');
  }

  const sessionDate = new Date(sessionStartTime);
  const createdDate = new Date(bookingCreatedTime);

  // Time Window detection (Day: 09:00–19:59, Night: >= 20:00)
  const hours = sessionDate.getHours();
  const minutes = sessionDate.getMinutes();
  const timeInMinutes = hours * 60 + minutes;

  const isNight = timeInMinutes >= 20 * 60; // From 20:00 onwards
  const timeSlotLabel = isNight ? 'Khung giờ tối (từ 20:00)' : 'Khung giờ ngày (09:00–19:59)';
  const basePricePerPerson = isNight ? game.price_night_vnd : game.price_day_vnd;

  const rawTotal = basePricePerPerson * playerCount;

  // Check Advance Booking (Hours difference)
  const advanceHours = (sessionDate.getTime() - createdDate.getTime()) / (1000 * 60 * 60);
  const isAdvanceAtLeast24h = advanceHours >= RULES.ADVANCE_BOOKING_HOURS;

  // Day of week (0 = Sunday, 1 = Monday, 2 = Tuesday, ..., 6 = Saturday)
  const dayOfWeek = sessionDate.getDay();

  // Eligible within 09:00 - 19:00 (19:00 cutoff for promo window)
  const isWithinPromoTime = timeInMinutes >= 9 * 60 && timeInMinutes <= 19 * 60;

  // Check Tuesday Promo: -30%, Tuesday, advance >= 24h, 09:00 - 19:00
  const eligibleTuesday = dayOfWeek === 2 && isAdvanceAtLeast24h && isWithinPromoTime;

  // Check Student Promo: -20k/person, Mon-Thu, 09:00 - 19:00, user is student_verified
  const isMonToThu = dayOfWeek >= 1 && dayOfWeek <= 4;
  const eligibleStudent = isStudentVerified && isMonToThu && isWithinPromoTime;

  let appliedPromotion = null;
  let totalDiscountVnd = 0;
  let promotionNotice = null;
  let hasConflict = false;

  if (eligibleTuesday && eligibleStudent) {
    // OPEN DECISION #7: Khi cả hai ưu đãi cùng thỏa mãn, demo không tự chọn
    hasConflict = true;
    promotionNotice = 'Đủ điều kiện cả hai ưu đãi (Thứ 3 và HSSV). Quy tắc ưu tiên đang chờ Ban Quản Trị chốt (OPEN DECISION #7). Tạm thời chưa áp dụng giảm giá.';
    totalDiscountVnd = 0;
  } else if (eligibleTuesday) {
    appliedPromotion = {
      code: 'PROMO_TUESDAY',
      name: 'Ưu đãi Thứ Ba Vui Vẻ (-30%)',
      discount_percent: 30,
      discount_per_person_vnd: basePricePerPerson * 0.3,
    };
    totalDiscountVnd = Math.round(rawTotal * 0.3);
  } else if (eligibleStudent) {
    appliedPromotion = {
      code: 'PROMO_STUDENT',
      name: 'Ưu đãi Học sinh - Sinh viên (-20.000đ/người)',
      discount_per_person_vnd: 20000,
    };
    totalDiscountVnd = 20000 * playerCount;
  }

  const finalTotalVnd = Math.max(0, rawTotal - totalDiscountVnd);

  // Deposit: Cố định 50.000 VNĐ / người
  const totalDepositVnd = RULES.DEPOSIT_VND * playerCount;
  const remainingVnd = Math.max(0, finalTotalVnd - totalDepositVnd);

  return {
    pricing_snapshot: {
      game_id: game.id,
      game_title: game.title,
      time_slot: timeSlotLabel,
      is_night: isNight,
      player_count: playerCount,
      base_price_per_person: basePricePerPerson,
      raw_total_vnd: rawTotal,
      applied_promotion: appliedPromotion,
      total_discount_vnd: totalDiscountVnd,
      final_total_vnd: finalTotalVnd,
      deposit_per_person_vnd: RULES.DEPOSIT_VND,
      total_deposit_vnd: totalDepositVnd,
      remaining_vnd: remainingVnd,
      has_promotion_conflict: hasConflict,
      promotion_notice: promotionNotice,
      calculated_at: new Date().toISOString(),
      provisional_cross_window: isWithinPromoTime && hours >= 18 && hours < 20, // Ca vắt qua 2 khung
    },
  };
}
