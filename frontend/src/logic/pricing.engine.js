/** Ưu đãi không cộng dồn: Thứ 3 giảm 30%, HSSV giảm 20.000đ/người trong khung được duyệt. */
export const DEPOSIT_PER_PLAYER = 50000;
export const HSSV_DISCOUNT = 20000;
export const TUESDAY_RATE = 0.3;

function parseLocalDate(dateStr) {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(dateStr || ''));
  if (!match) return null;
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

function minutesOf(timeStr) {
  const match = /^(\d{2}):(\d{2})/.exec(String(timeStr || ''));
  if (!match) return null;
  return Number(match[1]) * 60 + Number(match[2]);
}

export function isTuesday(dateStr) {
  const date = parseLocalDate(dateStr);
  return Boolean(date) && date.getDay() === 2;
}

/** Thứ 2–Thứ 5, 09:00–19:59. */
export function isHssvWindow(dateStr, timeStr) {
  const date = parseLocalDate(dateStr);
  const minutes = minutesOf(timeStr);
  if (!date || minutes == null) return false;
  const day = date.getDay();
  if (day < 1 || day > 4) return false;
  return minutes >= 9 * 60 && minutes <= 19 * 60 + 59;
}

export function quotePrice({ basePrice, playerCount = 1, playDate, playTime, hssvApproved = false }) {
  if (basePrice == null || basePrice === '') return null;
  const unit = Number(basePrice);
  const count = Math.max(1, Number(playerCount) || 1);
  if (!Number.isFinite(unit) || unit < 0) return null;

  const base = Math.round(unit * count);
  const tuesdayAmount = isTuesday(playDate) ? Math.round(base * TUESDAY_RATE) : 0;
  const hssvAmount = hssvApproved && isHssvWindow(playDate, playTime) ? HSSV_DISCOUNT * count : 0;

  let discount = 0;
  let discountLabel = 'Không áp dụng';
  let applied = 'none';
  if (tuesdayAmount > 0 || hssvAmount > 0) {
    if (tuesdayAmount >= hssvAmount) {
      discount = tuesdayAmount;
      discountLabel = 'Thứ 3 Bí Ẩn (−30%)';
      applied = 'tuesday';
    } else {
      discount = hssvAmount;
      discountLabel = 'Ưu đãi HSSV (−20.000đ/người)';
      applied = 'hssv';
    }
  }

  const total = Math.max(0, base - discount);
  const deposit = DEPOSIT_PER_PLAYER * count;
  const remaining = Math.max(0, total - deposit);

  return {
    base,
    discount,
    discountLabel,
    applied,
    total,
    deposit,
    remaining,
    playerCount: count,
    source: 'preview',
  };
}
