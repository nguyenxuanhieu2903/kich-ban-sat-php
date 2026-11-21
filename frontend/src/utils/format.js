/**
 * Formatting Utilities: Currency, Dates, and Server-Compensated Countdown
 */

const currencyFormatter = new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
  maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('vi-VN');

export function formatCurrency(amount) {
  if (typeof amount !== 'number' || isNaN(amount)) return '0 ₫';
  return currencyFormatter.format(amount);
}

export function formatNumber(num) {
  if (typeof num !== 'number' || isNaN(num)) return '0';
  return numberFormatter.format(num);
}

/**
 * Format date time to Vietnam Timezone (Asia/Ho_Chi_Minh)
 */
export function formatDateTime(dateInput, format = 'full') {
  if (!dateInput) return '—';
  const date = dateInput instanceof Date ? dateInput : new Date(dateInput);
  if (isNaN(date.getTime())) return '—';

  const options = {
    timeZone: 'Asia/Ho_Chi_Minh',
  };

  if (format === 'timeOnly') {
    return date.toLocaleTimeString('vi-VN', { ...options, hour: '2-digit', minute: '2-digit' });
  }

  if (format === 'dateOnly') {
    return date.toLocaleDateString('vi-VN', { ...options, day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  if (format === 'weekdayDate') {
    return date.toLocaleDateString('vi-VN', { ...options, weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  return `${date.toLocaleTimeString('vi-VN', { ...options, hour: '2-digit', minute: '2-digit' })} • ${date.toLocaleDateString('vi-VN', { ...options, day: '2-digit', month: '2-digit', year: 'numeric' })}`;
}

/**
 * Server Time offset tracker
 */
let serverTimeOffsetMs = 0;

export function updateServerTimeOffset(serverIsoStringOrHeaderDate) {
  if (!serverIsoStringOrHeaderDate) return;
  const serverTime = new Date(serverIsoStringOrHeaderDate).getTime();
  if (!isNaN(serverTime)) {
    serverTimeOffsetMs = serverTime - Date.now();
  }
}

export function getCurrentServerTime() {
  return new Date(Date.now() + serverTimeOffsetMs);
}

/**
 * Calculate countdown with server time compensation
 */
export function calculateCountdown(targetDateInput) {
  if (!targetDateInput) {
    return { isExpired: true, days: 0, hours: 0, minutes: 0, seconds: 0, totalSeconds: 0, formatted: '00:00:00' };
  }

  const targetTime = (targetDateInput instanceof Date ? targetDateInput : new Date(targetDateInput)).getTime();
  if (isNaN(targetTime)) {
    return { isExpired: true, days: 0, hours: 0, minutes: 0, seconds: 0, totalSeconds: 0, formatted: '00:00:00' };
  }

  const currentCompensatedTime = Date.now() + serverTimeOffsetMs;
  const diffMs = targetTime - currentCompensatedTime;

  if (diffMs <= 0) {
    return { isExpired: true, days: 0, hours: 0, minutes: 0, seconds: 0, totalSeconds: 0, formatted: '00:00:00' };
  }

  const totalSeconds = Math.floor(diffMs / 1000);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  const pad = (n) => String(n).padStart(2, '0');
  const formatted = days > 0
    ? `${days} ngày ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
    : `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;

  return {
    isExpired: false,
    days,
    hours,
    minutes,
    seconds,
    totalSeconds,
    formatted,
  };
}
