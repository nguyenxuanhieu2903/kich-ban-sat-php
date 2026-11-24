import { parseLocalDate, todayIso } from './format.js';

export const OPENING_MINUTES = 9 * 60;
export const CLOSING_MINUTES = 23 * 60;
export const NOTE_LIMIT = 500;

function minutesOf(timeStr) {
  const match = /^(\d{2}):(\d{2})/.exec(String(timeStr || ''));
  if (!match) return null;
  const minutes = Number(match[1]) * 60 + Number(match[2]);
  if (Number(match[1]) > 23 || Number(match[2]) > 59) return null;
  return minutes;
}

export function isWithinOpeningHours(timeStr) {
  const minutes = minutesOf(timeStr);
  if (minutes == null) return false;
  return minutes >= OPENING_MINUTES && minutes <= CLOSING_MINUTES;
}

export function validateCreateRoom({ game, playDate, playTime, notes, adultConfirmed }) {
  const errors = {};
  if (!game?.id) errors.game = 'Hãy chọn một kịch bản.';

  const date = parseLocalDate(playDate);
  if (!date) {
    errors.playDate = 'Hãy chọn ngày chơi.';
  } else if (playDate < todayIso()) {
    errors.playDate = 'Ngày chơi không được ở trong quá khứ.';
  }

  if (!isWithinOpeningHours(playTime)) {
    errors.playTime = 'Giờ chơi phải nằm trong khung 09:00–23:00.';
  } else if (!/^(\d{2}):(00|30)/.test(String(playTime))) {
    errors.playTime = 'Giờ chơi chỉ nhận mốc :00 hoặc :30.';
  } else if (playDate === todayIso()) {
    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    if (minutesOf(playTime) <= nowMinutes) {
      errors.playTime = 'Giờ chơi hôm nay phải muộn hơn thời điểm hiện tại.';
    }
  }

  if (String(notes || '').length > NOTE_LIMIT) {
    errors.notes = `Ghi chú tối đa ${NOTE_LIMIT} ký tự.`;
  }

  if (game?.is18Plus && !adultConfirmed) {
    errors.adult = 'Kịch bản 18+ cần xác nhận đủ tuổi trước khi tạo phòng.';
  }

  return { ok: Object.keys(errors).length === 0, errors };
}

export function createChecklist({ game, playDate, playTime, notes, adultConfirmed }) {
  const result = validateCreateRoom({ game, playDate, playTime, notes, adultConfirmed });
  return [
    { id: 'game', label: 'Đã chọn kịch bản', ok: !result.errors.game },
    { id: 'playDate', label: 'Ngày chơi hợp lệ', ok: !result.errors.playDate && Boolean(playDate) },
    { id: 'playTime', label: 'Giờ chơi trong khung mở cửa', ok: !result.errors.playTime && Boolean(playTime) },
    { id: 'notes', label: 'Ghi chú trong giới hạn', ok: !result.errors.notes },
    {
      id: 'adult',
      label: game?.is18Plus ? 'Đã xác nhận đủ 18 tuổi' : 'Không yêu cầu xác nhận 18+',
      ok: !result.errors.adult,
    },
  ];
}
