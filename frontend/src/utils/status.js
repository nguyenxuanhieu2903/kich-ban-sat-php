const STATUS_MAP = {
  OPEN: { label: 'Đang tuyển người', tone: 'open', recruiting: true },
  FORMING: { label: 'Đang tuyển người', tone: 'open', recruiting: true },
  RECRUITING: { label: 'Đang tuyển người', tone: 'open', recruiting: true },
  REPLENISHING: { label: 'Tuyển người thay thế', tone: 'hold', recruiting: true },
  HOLD: { label: 'Giữ chỗ', tone: 'hold', recruiting: false },
  WAITING_FOR_DM: { label: 'Chờ phân công DM', tone: 'hold', recruiting: false },
  WAITING_FOR_ORGANIZATION: { label: 'Chờ tổ chức lịch', tone: 'hold', recruiting: false },
  SCHEDULE_PENDING: { label: 'Chờ chốt lịch', tone: 'hold', recruiting: false },
  WAITING_FOR_DEPOSIT: { label: 'Chờ cọc', tone: 'hold', recruiting: false },
  PAYMENT_PENDING: { label: 'Chờ cọc', tone: 'hold', recruiting: false },
  READY_FOR_SESSION: { label: 'Sẵn sàng chơi', tone: 'open', recruiting: false },
  CONFIRMED: { label: 'Đã chốt lịch', tone: 'open', recruiting: false },
  READY: { label: 'Sẵn sàng chơi', tone: 'open', recruiting: false },
  IN_PROGRESS: { label: 'Đang chơi', tone: 'hold', recruiting: false },
  COMPLETED: { label: 'Đã hoàn thành', tone: 'muted', recruiting: false },
  CANCELLED: { label: 'Đã hủy', tone: 'locked', recruiting: false },
  CANCELED: { label: 'Đã hủy', tone: 'locked', recruiting: false },
};

const PARTICIPANT_MAP = {
  JOINED: { label: 'Đã vào phòng', tone: 'open' },
  PENDING_DEPOSIT: { label: 'Chờ đặt cọc', tone: 'hold' },
  DEPOSITED: { label: 'Đã đặt cọc', tone: 'open' },
  PAID: { label: 'Đã thanh toán', tone: 'open' },
  LEAVE_REQUESTED: { label: 'Đã gửi yêu cầu rời', tone: 'hold' },
  LEFT: { label: 'Đã rời phòng', tone: 'muted' },
  CANCELLED_UNPAID: { label: 'Hủy do chưa cọc', tone: 'locked' },
  CANCELLED_BY_ADMIN: { label: 'Bị hủy bởi Admin', tone: 'locked' },
  ACTIVE: { label: 'Đang tham gia', tone: 'open' },
};

const PAYMENT_MAP = {
  UNPAID: { label: 'Chưa thanh toán', tone: 'hold' },
  PENDING: { label: 'Đang xử lý', tone: 'hold' },
  DEPOSITED: { label: 'Đã nhận cọc', tone: 'open' },
  PAID: { label: 'Đã thanh toán', tone: 'open' },
  REFUNDED: { label: 'Đã hoàn tiền', tone: 'muted' },
  REFUND_PENDING: { label: 'Chờ hoàn cọc', tone: 'hold' },
  FAILED: { label: 'Thất bại', tone: 'locked' },
};

const REFUND_MAP = {
  PENDING: { label: 'Chờ duyệt hoàn cọc', tone: 'hold' },
  REQUESTED: { label: 'Đã gửi yêu cầu hoàn cọc', tone: 'hold' },
  APPROVED: { label: 'Đã duyệt hoàn cọc', tone: 'open' },
  REJECTED: { label: 'Từ chối hoàn cọc', tone: 'locked' },
  COMPLETED: { label: 'Đã hoàn cọc', tone: 'open' },
};

export function describeStatus(value, map = STATUS_MAP) {
  const key = String(value || '').toUpperCase();
  return map[key] || { label: value || 'Không rõ', tone: 'muted', recruiting: false };
}

export function roomStatusMeta(status) {
  return describeStatus(status, STATUS_MAP);
}

export function participantStatusMeta(status) {
  return describeStatus(status, PARTICIPANT_MAP);
}

export function paymentStatusMeta(status) {
  return describeStatus(status, PAYMENT_MAP);
}

export function refundStatusMeta(status) {
  return describeStatus(status, REFUND_MAP);
}

export function isRecruitingStatus(status) {
  return Boolean(roomStatusMeta(status).recruiting);
}

export function historyTabFor(room, payment) {
  const blob = `${room?.status || ''} ${payment?.status || ''} ${room?.session_status || ''}`.toUpperCase();
  if (/COMPLETE|FINISHED|PLAYED/.test(blob)) return 'completed';
  if (/DEPOSIT|UNPAID|PAYMENT_PENDING|WAITING_FOR_DEPOSIT/.test(blob)) return 'awaiting_deposit';
  return 'upcoming';
}

export const ROOM_STATUS_OPTIONS = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'FORMING', label: 'Đang tuyển người' },
  { value: 'REPLENISHING', label: 'Tuyển người thay thế' },
  { value: 'WAITING_FOR_DEPOSIT', label: 'Chờ cọc' },
  { value: 'CONFIRMED', label: 'Đã chốt lịch' },
  { value: 'COMPLETED', label: 'Đã hoàn thành' },
];
