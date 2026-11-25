export function remainingMs(deadline) {
  const time = new Date(deadline).getTime();
  if (!Number.isFinite(time)) return null;
  return time - Date.now();
}

export function formatCountdown(ms) {
  if (ms == null) return '—';
  if (ms <= 0) return '00:00:00';
  const total = Math.floor(ms / 1000);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}

export function hoursUntil(moment) {
  const time = new Date(moment).getTime();
  if (!Number.isFinite(time)) return null;
  return (time - Date.now()) / 36e5;
}

/** Hoàn 100% cọc khi hủy trước giờ chơi hơn 24 giờ. */
export function refundEligible(sessionStart) {
  const hours = hoursUntil(sessionStart);
  if (hours == null) return null;
  return hours > 24;
}

export function reminderBucket(ms) {
  if (ms == null || ms <= 0) return null;
  const minutes = ms / 60000;
  if (minutes <= 30) return '30m';
  if (minutes <= 60) return '1h';
  return null;
}

export function sessionStartIso(playDate, playTime) {
  if (!playDate) return '';
  const time = /^(\d{2}):(\d{2})/.exec(String(playTime || '')) ? String(playTime).slice(0, 5) : '00:00';
  const date = String(playDate).slice(0, 10);
  return `${date}T${time}:00`;
}
