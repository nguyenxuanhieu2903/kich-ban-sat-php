export function formatVnd(amount) {
  const value = Number(amount);
  if (!Number.isFinite(value)) return '—';
  return `${value.toLocaleString('vi-VN')}đ`;
}

export function parseLocalDate(dateStr) {
  if (!dateStr || typeof dateStr !== 'string') return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(dateStr.trim());
  if (!match) return null;
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  return Number.isNaN(date.getTime()) ? null : date;
}

export function todayIso() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

export function formatDate(value) {
  if (!value) return '—';
  const local = parseLocalDate(String(value));
  const date = local || new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString('vi-VN', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

export function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleString('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

export function formatClock(value) {
  if (!value) return '—';
  const match = /^(\d{2}):(\d{2})/.exec(String(value));
  if (match) return `${match[1]}:${match[2]}`;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

const ROOM_STATUS = {
  recruiting: 'Đang tuyển thành viên',
  open: 'Đang tuyển thành viên',
  full: 'Đã đủ người',
  scheduling: 'Đang xác nhận lịch',
  schedule_pending: 'Đang xác nhận lịch',
  confirmed: 'Đã xác nhận',
  in_progress: 'Đang diễn ra',
  playing: 'Đang diễn ra',
  holding: 'Giữ chỗ',
  deposit_pending: 'Chờ đặt cọc',
  awaiting_deposit: 'Chờ đặt cọc',
  not_open: 'Chưa mở thanh toán',
  payment_not_open: 'Chưa mở thanh toán',
  deposit_paid: 'Đã đặt cọc',
  overdue: 'Quá hạn đặt cọc',
  expired: 'Quá hạn đặt cọc',
  fully_paid: 'Đã thanh toán đầy đủ',
  refund_pending: 'Đang chờ xử lý hoàn tiền',
  refunded: 'Đã hoàn tiền',
  processing: 'Đang xử lý',
  locked: 'Đã chốt',
  completed: 'Đã hoàn thành',
  cancelled: 'Đã hủy lượt tham gia',
  canceled: 'Đã hủy lượt tham gia',
  closed: 'Đã đóng',
};

export function statusLabel(status) {
  if (!status) return 'Chưa rõ';
  const key = String(status).toLowerCase();
  return ROOM_STATUS[key] || String(status);
}

export function statusStampClass(status) {
  const key = String(status || '').toLowerCase();
  if (['recruiting', 'open', 'fully_paid', 'deposit_paid', 'refunded'].includes(key)) return 'stamp-badge open';
  if (['holding', 'deposit_pending', 'awaiting_deposit', 'processing', 'refund_pending'].includes(key)) return 'stamp-badge hold';
  if (['overdue', 'expired', 'cancelled', 'canceled'].includes(key)) return 'stamp-badge hold';
  return 'stamp-badge';
}

export function errorMessage(err) {
  if (!err) return 'Đã xảy ra lỗi.';
  if (err.code === 'NETWORK_FAILURE') {
    return 'Không kết nối được máy chủ. Hãy kiểm tra API đang chạy tại cổng 8080.';
  }
  if (err.code === 'REQUEST_TIMEOUT') return 'Máy chủ phản hồi quá chậm. Vui lòng thử lại.';
  if (err.code === 'REQUEST_ABORTED') return 'Yêu cầu đã bị hủy.';
  if (err.status === 401) return 'Bạn cần đăng nhập để tiếp tục.';
  if (err.status === 403) return 'Bạn không có quyền thực hiện thao tác này.';
  if (err.status === 409) return err.message || 'Dữ liệu vừa thay đổi. Vui lòng tải lại.';
  if (err.status === 404) return err.message || 'Không tìm thấy dữ liệu.';
  if (err.status === 422 || err.status === 400) return err.message || 'Dữ liệu chưa hợp lệ.';
  return err.message || 'Không thể hoàn tất yêu cầu.';
}

export function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return 'KP';
  return parts.slice(-2).map((part) => part[0]).join('').toUpperCase();
}

export function safeUrl(url) {
  if (typeof url !== 'string') return '';
  const value = url.trim();
  if (!value) return '';
  if (value.startsWith('./') || value.startsWith('../') || value.startsWith('/')) return value;
  try {
    const parsed = new URL(value, window.location.origin);
    if (parsed.protocol === 'http:' || parsed.protocol === 'https:') return parsed.href;
  } catch {
    return '';
  }
  return '';
}
