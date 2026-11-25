import { h } from './dom.js';
import { formatDateTime, formatVnd, statusStampClass } from '../logic/format.js';
import {
  DEPOSIT_VND,
  PAYMENT_STATUS,
  canPayDeposit,
  canPayRemaining,
  clockExpired,
} from '../logic/payment.js';
import { formatCountdown, remainingMs } from '../logic/timer.engine.js';

function row(label, value) {
  return [h('dt', {}, label), h('dd', {}, value == null || value === '' ? '—' : value)];
}

function historyTable(items) {
  if (!Array.isArray(items) || !items.length) {
    return h('p', { class: 'player-meta' }, 'Chưa có giao dịch nào.');
  }
  return h('table', { class: 'booking-history' }, [
    h('thead', {}, [h('tr', {}, ['Thời điểm', 'Loại', 'Số tiền', 'Trạng thái', 'Mã'].map((label) => h('th', { scope: 'col' }, label)))]),
    h('tbody', {}, items.map((item) => h('tr', {}, [
      h('td', {}, formatDateTime(item.created_at || item.paid_at || item.at)),
      h('td', {}, item.type || item.kind || 'Giao dịch'),
      h('td', {}, formatVnd(item.amount)),
      h('td', {}, item.status_label || item.status || '—'),
      h('td', {}, item.transaction_id || item.txn_id || item.id || '—'),
    ]))),
  ]);
}

function clockExpiredVisually(payment) {
  return clockExpired(payment.deadline) && payment.status === PAYMENT_STATUS.AWAITING;
}

export function renderPaymentCard(payment, {
  processing = false,
  notice = '',
  noticeTone = 'ok',
  onDeposit,
  onRemaining,
} = {}) {
  const expiredUi = clockExpiredVisually(payment);
  const showDeposit = canPayDeposit(payment) && !expiredUi && !processing;
  const showRemaining = canPayRemaining(payment) && !processing;
  const remainingMsValue = remainingMs(payment.deadline);
  const clockText = payment.deadline ? formatCountdown(remainingMsValue) : '—';
  const depositBtn = h('button', {
    class: 'btn btn-gold',
    type: 'button',
    id: 'pay-deposit-btn',
    disabled: processing || !showDeposit,
  }, processing ? 'Đang xử lý...' : 'TIẾN HÀNH ĐẶT CỌC');
  const remainingBtn = h('button', {
    class: 'btn btn-primary',
    type: 'button',
    id: 'pay-remaining-btn',
    disabled: processing || !showRemaining,
  }, processing ? 'Đang xử lý...' : 'THANH TOÁN PHẦN CÒN LẠI');
  if (showDeposit) depositBtn.addEventListener('click', onDeposit);
  if (showRemaining) remainingBtn.addEventListener('click', onRemaining);

  let actionHint = '';
  if (payment.status === PAYMENT_STATUS.NOT_OPEN) actionHint = 'Thanh toán chưa được mở. Hệ thống chỉ mở đặt cọc sau khi phòng đủ điều kiện.';
  else if (expiredUi) actionHint = 'Đã hết thời hạn hiển thị. Thao tác thanh toán cũ đã bị khóa. Đang chờ máy chủ xác nhận trạng thái.';
  else if (payment.status === PAYMENT_STATUS.OVERDUE) actionHint = 'Quá hạn đặt cọc. Không dùng lại phiên thanh toán đã hết hiệu lực.';
  else if (payment.status === PAYMENT_STATUS.CANCELLED) actionHint = 'Lượt tham gia đã bị hủy. Frontend không tự xóa người chơi khỏi phòng.';
  else if (payment.status === PAYMENT_STATUS.PROCESSING) actionHint = 'Giao dịch đang được cổng thanh toán hoặc máy chủ xử lý.';
  else if (payment.status === PAYMENT_STATUS.DEPOSIT_PAID) actionHint = 'Cọc đã được máy chủ xác nhận. Có thể thanh toán phần còn lại.';
  else if (payment.status === PAYMENT_STATUS.FULLY_PAID) actionHint = 'Máy chủ đã xác nhận thanh toán đầy đủ.';

  const qrSafe = payment.qrUrl && /^(https?:|data:image\/)/i.test(payment.qrUrl) ? payment.qrUrl : '';

  return h('article', { class: 'payment-card', id: 'payment-card' }, [
    h('header', { class: 'payment-card-head' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, 'Payment Card'),
        h('h3', {}, 'Thanh toán của bạn'),
      ]),
      h('span', { class: statusStampClass(payment.status) }, payment.statusLabel),
    ]),
    notice ? h('p', { class: `player-banner payment-notice payment-notice--${noticeTone}` }, notice) : null,
    processing ? h('p', { class: 'payment-loading', role: 'status' }, 'Đang chờ kết quả từ máy chủ hoặc cổng thanh toán...') : null,
    h('dl', { class: 'player-definition' }, [
      ...row('Mã phòng', payment.roomCode),
      ...row('Tên kịch bản', payment.gameTitle),
      ...row('Tên người chơi', payment.playerName),
      ...row('Giá gốc', formatVnd(payment.base)),
      ...row('Ưu đãi', payment.discount ? `${payment.discountLabel} · ${formatVnd(payment.discount)}` : 'Không áp dụng'),
      ...row('Giá sau giảm', formatVnd(payment.total)),
      ...row('Tiền cọc', formatVnd(DEPOSIT_VND)),
      ...row('Khoản còn lại', formatVnd(payment.remaining)),
      ...row('Deadline', payment.deadline ? formatDateTime(payment.deadline) : '—'),
      ...row('Trạng thái giao dịch', payment.statusLabel),
      ...row('Mã giao dịch', payment.transactionId),
      ...row('Mã vé', payment.ticketCode),
    ]),
    payment.status === PAYMENT_STATUS.DEPOSIT_PAID || payment.status === PAYMENT_STATUS.FULLY_PAID
      ? h('div', { class: 'payment-remain-box' }, [
        h('p', { class: 'player-kicker' }, 'Sau khi cọc thành công'),
        h('p', {}, `Tổng giá: ${formatVnd(payment.total)}.`),
        h('p', {}, `Đã cọc: ${formatVnd(DEPOSIT_VND)}.`),
        h('p', {}, `Còn lại: ${formatVnd(payment.remaining)}.`),
      ])
      : null,
    h('div', { class: 'payment-deadline' }, [
      h('p', { class: 'player-kicker' }, 'THỜI HẠN ĐẶT CỌC'),
      h('p', { class: `booking-clock${expiredUi ? ' is-expired' : ''}`, id: 'booking-clock' }, clockText),
      h('p', { class: 'player-meta' }, expiredUi
        ? 'Hết thời gian hiển thị. Chờ Backend xác nhận trạng thái.'
        : 'Còn lại để hoàn thành thanh toán.'),
    ]),
    qrSafe ? h('img', { class: 'payment-qr', alt: 'Mã QR thanh toán', src: qrSafe }) : null,
    actionHint ? h('p', { class: 'player-meta' }, actionHint) : null,
    h('div', { class: 'player-actions' }, [
      showDeposit ? depositBtn : null,
      showRemaining ? remainingBtn : null,
    ]),
    h('h4', {}, 'Lịch sử thanh toán'),
    historyTable(payment.history),
  ]);
}
