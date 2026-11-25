import { playerApi, getCachedUser, getMe, ApiError } from './player-api.js';
import { normalizeBooking } from '../logic/normalize.js';
import {
  PAYMENT_STATUS,
  canPayDeposit,
  canPayRemaining,
  clockExpired,
  emitPaymentChanged,
  mapPaymentStatus,
  normalizePayment,
  paymentUserId,
  pickOwnPayment,
} from '../logic/payment.js';

let inFlight = false;

function profileId(profile) {
  if (!profile) return '';
  return String(profile.id ?? profile.user_id ?? '');
}

function profileName(profile) {
  if (!profile) return '';
  return String(profile.full_name || profile.fullName || profile.email || '');
}

function isForeignPayment(item, userId) {
  const owner = paymentUserId(item);
  return Boolean(userId && owner && owner !== String(userId));
}

function collectPaymentLists(raw) {
  if (!raw || typeof raw !== 'object') return [];
  return []
    .concat(raw.payments || [])
    .concat(raw.my_payments || [])
    .concat(raw.room?.payments || []);
}

async function currentUser() {
  const cached = getCachedUser();
  if (cached && profileId(cached)) return cached;
  try {
    return await getMe();
  } catch {
    return cached;
  }
}

export async function loadBooking(roomId, ticket) {
  if (roomId) return playerApi.getBookingByRoom(roomId);
  if (!ticket) return null;
  try {
    return await playerApi.getBooking(ticket);
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      return playerApi.getBookingByRoom(ticket);
    }
    throw err;
  }
}

export async function fetchOwnPayment({ roomId, ticket, bookingRaw } = {}) {
  const me = await currentUser();
  const userId = profileId(me);
  const raw = bookingRaw || await loadBooking(roomId, ticket);
  const booking = normalizeBooking(raw);
  const lists = collectPaymentLists(raw);
  const ownFromList = pickOwnPayment(lists, userId);
  const nested = raw?.my_payment || raw?.own_payment || raw?.payment || raw?.room?.my_payment || raw?.room?.payment;
  const source = ownFromList || (isForeignPayment(nested, userId) ? null : nested) || {};
  const payment = normalizePayment(source, {
    id: source.id || booking.id,
    roomId: booking.roomId,
    roomCode: booking.roomCode,
    gameTitle: booking.title,
    playerName: source.player_name || booking.playerName || profileName(me),
    userId,
    ticketCode: booking.ticketCode || source.ticket_code,
    deadline: source.deposit_deadline_at || raw?.deposit_deadline_at || raw?.payment_deadline,
    status: source.status || source.payment_status || raw?.payment_status || booking.paymentStatus,
    total: source.total ?? source.final_price ?? raw?.final_price ?? raw?.payable,
    discountLabel: source.discount_label,
    history: source.transactions || raw?.transactions,
  });
  return { booking, payment, me, userId };
}

function assertCurrentUser(me, expectedId) {
  const current = profileId(me);
  if (!current) {
    const error = new Error('Bạn cần đăng nhập để thanh toán.');
    error.status = 401;
    throw error;
  }
  if (expectedId && String(expectedId) !== current) {
    throw new Error('Phiên thanh toán không thuộc tài khoản đang đăng nhập.');
  }
}

async function waitForGateway(roomId, ticket) {
  const started = Date.now();
  while (Date.now() - started < 45000) {
    const snapshot = await fetchOwnPayment({ roomId, ticket });
    if (snapshot.payment.status !== PAYMENT_STATUS.PROCESSING) return snapshot;
    await new Promise((resolve) => window.setTimeout(resolve, 2500));
  }
  return fetchOwnPayment({ roomId, ticket });
}

function checkoutUrl(result) {
  if (!result || typeof result !== 'object') return '';
  return result.checkout_url || result.pay_url || result.payment_url || result.redirect_url || '';
}

export async function requestDeposit(roomId, ticket) {
  if (inFlight) {
    throw new Error('Đang xử lý giao dịch. Không gửi thêm yêu cầu mới.');
  }
  inFlight = true;
  try {
    const before = await fetchOwnPayment({ roomId, ticket });
    assertCurrentUser(before.me, before.payment.userId);
    if (before.payment.status === PAYMENT_STATUS.PROCESSING) {
      throw new Error('Giao dịch đang được máy chủ xử lý. Vui lòng đợi kết quả.');
    }
    if (!canPayDeposit(before.payment)) {
      throw new Error(`Không thể đặt cọc. Trạng thái hiện tại: ${before.payment.statusLabel}.`);
    }
    if (clockExpired(before.payment.deadline)) {
      throw new Error('Đã hết thời hạn đặt cọc. Đang chờ máy chủ xác nhận trạng thái.');
    }
    const result = await playerApi.payDeposit(before.payment.roomId || roomId);
    const url = checkoutUrl(result);
    if (url) {
      window.location.assign(url);
      return { redirected: true, result, snapshot: before };
    }
    const mapped = mapPaymentStatus(result?.status || result?.payment_status);
    const snapshot = mapped === PAYMENT_STATUS.PROCESSING
      ? await waitForGateway(before.payment.roomId || roomId, ticket)
      : await fetchOwnPayment({ roomId: before.payment.roomId || roomId, ticket });
    emitPaymentChanged(snapshot.payment);
    return { redirected: false, result, snapshot };
  } finally {
    inFlight = false;
  }
}

export async function requestRemaining(roomId, ticket) {
  if (inFlight) {
    throw new Error('Đang xử lý giao dịch. Không gửi thêm yêu cầu mới.');
  }
  inFlight = true;
  try {
    const before = await fetchOwnPayment({ roomId, ticket });
    assertCurrentUser(before.me, before.payment.userId);
    if (!canPayRemaining(before.payment)) {
      throw new Error(`Chưa thể thanh toán phần còn lại. Trạng thái hiện tại: ${before.payment.statusLabel}.`);
    }
    const result = await playerApi.payRemaining(before.payment.roomId || roomId);
    const url = checkoutUrl(result);
    if (url) {
      window.location.assign(url);
      return { redirected: true, result, snapshot: before };
    }
    const mapped = mapPaymentStatus(result?.status || result?.payment_status);
    const snapshot = mapped === PAYMENT_STATUS.PROCESSING
      ? await waitForGateway(before.payment.roomId || roomId, ticket)
      : await fetchOwnPayment({ roomId: before.payment.roomId || roomId, ticket });
    emitPaymentChanged(snapshot.payment);
    return { redirected: false, result, snapshot };
  } finally {
    inFlight = false;
  }
}

export function isPaymentBusy() {
  return inFlight;
}
