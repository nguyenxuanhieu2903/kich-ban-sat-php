import { DEPOSIT_PER_PLAYER } from './pricing.engine.js';
import { remainingMs, reminderBucket } from './timer.engine.js';

export const DEPOSIT_VND = DEPOSIT_PER_PLAYER;

export const PAYMENT_STATUS = {
  NOT_OPEN: 'not_open',
  AWAITING: 'awaiting_deposit',
  DEPOSIT_PAID: 'deposit_paid',
  OVERDUE: 'overdue',
  FULLY_PAID: 'fully_paid',
  CANCELLED: 'cancelled',
  REFUND_PENDING: 'refund_pending',
  REFUNDED: 'refunded',
  PROCESSING: 'processing',
};

export const PAYMENT_LABELS = {
  not_open: 'Chưa mở thanh toán',
  awaiting_deposit: 'Chờ đặt cọc',
  deposit_paid: 'Đã đặt cọc',
  overdue: 'Quá hạn đặt cọc',
  fully_paid: 'Đã thanh toán đầy đủ',
  cancelled: 'Đã hủy lượt tham gia',
  refund_pending: 'Đang chờ xử lý hoàn tiền',
  refunded: 'Đã hoàn tiền',
  processing: 'Đang xử lý',
};

export const REFUND_LABELS = {
  none: 'Chưa yêu cầu',
  pending: 'Chờ Admin duyệt',
  pending_admin: 'Chờ Admin duyệt',
  waiting: 'Chờ Admin duyệt',
  approved: 'Đã chấp thuận',
  accepted: 'Đã chấp thuận',
  rejected: 'Bị từ chối',
  denied: 'Bị từ chối',
  processing: 'Đang xử lý',
  success: 'Hoàn tiền thành công',
  refunded: 'Hoàn tiền thành công',
  failed: 'Hoàn tiền thất bại',
};

export function mapPaymentStatus(raw) {
  const key = String(raw || '').toLowerCase().replace(/\s+/g, '_');
  if (['not_open', 'payment_not_open', 'closed', 'locked_payment', 'unopened'].includes(key)) return PAYMENT_STATUS.NOT_OPEN;
  if (['awaiting_deposit', 'waiting_for_deposit', 'deposit_pending', 'pending', 'unpaid', 'waiting', 'hold'].includes(key)) {
    return PAYMENT_STATUS.AWAITING;
  }
  if (['deposit_paid', 'paid', 'deposit_confirmed', 'partial', 'partially_paid'].includes(key)) return PAYMENT_STATUS.DEPOSIT_PAID;
  if (['overdue', 'expired', 'deposit_expired', 'late', 'timeout'].includes(key)) return PAYMENT_STATUS.OVERDUE;
  if (['fully_paid', 'paid_in_full', 'remaining_paid', 'settled', 'complete_payment'].includes(key)) return PAYMENT_STATUS.FULLY_PAID;
  if (['cancelled', 'canceled', 'forfeited', 'forfeit'].includes(key)) return PAYMENT_STATUS.CANCELLED;
  if (['refund_pending', 'refund_processing', 'leave_pending', 'awaiting_refund'].includes(key)) return PAYMENT_STATUS.REFUND_PENDING;
  if (['refunded', 'refund_success'].includes(key)) return PAYMENT_STATUS.REFUNDED;
  if (['processing', 'paying', 'in_progress'].includes(key)) return PAYMENT_STATUS.PROCESSING;
  return key || PAYMENT_STATUS.NOT_OPEN;
}

export function paymentLabel(status) {
  const mapped = mapPaymentStatus(status);
  return PAYMENT_LABELS[mapped] || String(status || 'Chưa rõ');
}

export function refundLabel(status) {
  const key = String(status || 'none').toLowerCase();
  return REFUND_LABELS[key] || String(status);
}

export function remainingAfterConfirmedDeposit(finalPrice, depositConfirmed) {
  const total = Number(finalPrice);
  if (!Number.isFinite(total)) return null;
  const deposit = depositConfirmed ? DEPOSIT_VND : 0;
  return Math.max(0, total - deposit);
}

export function normalizePayment(raw = {}, extras = {}) {
  const source = raw && typeof raw === 'object' && raw.payment && typeof raw.payment === 'object'
    ? { ...raw, ...raw.payment }
    : (raw || {});
  const status = mapPaymentStatus(source.status || source.payment_status || extras.status);
  const apiDeposit = Number(source.deposit_amount ?? source.deposit);
  const depositAmount = Number.isFinite(apiDeposit) && apiDeposit > 0 ? apiDeposit : DEPOSIT_VND;
  const base = Number(source.base ?? source.base_price ?? source.original_price);
  const discount = Number(source.discount ?? source.discount_amount ?? 0);
  const total = Number(source.total ?? source.final_price ?? source.payable ?? extras.total);
  const confirmed = [PAYMENT_STATUS.DEPOSIT_PAID, PAYMENT_STATUS.FULLY_PAID, PAYMENT_STATUS.REFUND_PENDING, PAYMENT_STATUS.REFUNDED].includes(status);
  const apiRemaining = Number(source.remaining_amount ?? source.remaining);
  const remaining = Number.isFinite(apiRemaining)
    ? apiRemaining
    : remainingAfterConfirmedDeposit(total, confirmed);
  return {
    id: String(source.id || source.payment_id || extras.id || ''),
    roomId: String(source.room_id || extras.roomId || ''),
    roomCode: String(source.room_code || extras.roomCode || ''),
    gameTitle: String(source.game_title || extras.gameTitle || ''),
    playerName: String(source.player_name || source.full_name || extras.playerName || ''),
    userId: String(source.user_id || extras.userId || ''),
    status,
    statusLabel: paymentLabel(status),
    base: Number.isFinite(base) ? base : null,
    discount: Number.isFinite(discount) ? discount : 0,
    discountLabel: String(source.discount_label || extras.discountLabel || 'Ưu đãi'),
    total: Number.isFinite(total) ? total : null,
    depositAmount,
    remaining: remaining == null ? null : remaining,
    deadline: source.deposit_deadline_at || source.deadline_at || extras.deadline || '',
    ticketCode: String(source.ticket_code || extras.ticketCode || ''),
    transactionId: String(source.transaction_id || source.txn_id || extras.transactionId || ''),
    qrUrl: String(source.qr_url || source.qr || extras.qrUrl || ''),
    retryAllowed: source.retry_allowed !== false,
    checkoutUrl: source.checkout_url || source.pay_url || '',
    history: Array.isArray(source.transactions) ? source.transactions : (Array.isArray(source.history) ? source.history : extras.history || []),
    raw: source,
  };
}

export function canPayDeposit(payment) {
  if (!payment) return false;
  if (payment.retryAllowed === false) return false;
  if (payment.status !== PAYMENT_STATUS.AWAITING) return false;
  return true;
}

export function canPayRemaining(payment) {
  if (!payment || payment.retryAllowed === false) return false;
  return payment.status === PAYMENT_STATUS.DEPOSIT_PAID;
}

export function clockExpired(deadline) {
  const ms = remainingMs(deadline);
  return ms != null && ms <= 0;
}

export function emitPaymentChanged(detail) {
  window.dispatchEvent(new CustomEvent('kbs:payment-changed', { detail }));
}

export function startPaymentPoll(fetcher, onData, intervalMs = 8000) {
  let stopped = false;
  let timer = 0;

  const tick = async () => {
    if (stopped) return;
    try {
      const data = await fetcher();
      onData(data);
    } catch {
      /* giữ bản vẽ trước, không bịa trạng thái */
    }
    if (!stopped) timer = window.setTimeout(tick, intervalMs);
  };

  const stop = () => {
    stopped = true;
    window.clearTimeout(timer);
  };

  window.addEventListener('pagehide', stop, { once: true });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      window.clearTimeout(timer);
      return;
    }
    if (!stopped) tick();
  });

  tick();
  return stop;
}

export function paymentUserId(item) {
  if (!item || typeof item !== 'object') return '';
  return String(item.user_id ?? item.player_id ?? item.member_id ?? '');
}

export function pickOwnPayment(list, userId) {
  if (!Array.isArray(list) || !userId) return null;
  return list.find((item) => paymentUserId(item) === String(userId)) || null;
}

export function maybeRemind(payment) {
  if (!payment?.deadline || payment.status !== PAYMENT_STATUS.AWAITING) return;
  const ms = remainingMs(payment.deadline);
  const bucket = reminderBucket(ms);
  if (!bucket) return;
  const key = `kbs_pay_remind_${payment.roomId || payment.id}_${bucket}`;
  try {
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');
  } catch {
    return;
  }
  return bucket === '1h'
    ? 'Còn khoảng 1 giờ để đặt cọc 50.000đ.'
    : 'Còn khoảng 30 phút để đặt cọc 50.000đ.';
}
