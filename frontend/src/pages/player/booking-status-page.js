import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { renderState, toast } from '../../components/player-ui.js';
import { renderPaymentCard } from '../../components/payment-card.js';
import { fetchOwnPayment, requestDeposit, requestRemaining } from '../../api/payment-session.js';
import { errorMessage, formatClock, formatDate, formatDateTime, statusLabel, statusStampClass } from '../../logic/format.js';
import {
  PAYMENT_STATUS,
  clockExpired,
  maybeRemind,
  startPaymentPoll,
} from '../../logic/payment.js';
import { formatCountdown, remainingMs } from '../../logic/timer.engine.js';

const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
const roomQuery = params.get('room') || '';
const ticketQuery = params.get('ticket') || '';

const view = {
  booking: null,
  payment: null,
  processing: false,
  notice: '',
  noticeTone: 'ok',
  missing: '',
};

let stopPoll = null;
let timerId = 0;

function bindClock(deadline) {
  window.clearInterval(timerId);
  if (!deadline) return;
  timerId = window.setInterval(() => {
    const node = document.getElementById('booking-clock');
    if (!node) return;
    const ms = remainingMs(deadline);
    node.textContent = formatCountdown(ms);
    node.classList.toggle('is-expired', ms != null && ms <= 0);
    if (ms != null && ms <= 0) node.nextElementSibling && (node.nextElementSibling.textContent = 'Hết thời gian hiển thị. Chờ Backend xác nhận trạng thái.');
  }, 1000);
}

function paintLookup(message) {
  const input = h('input', { class: 'player-input', id: 'booking-lookup', placeholder: 'Mã phòng hoặc mã vé' });
  const form = h('form', { class: 'player-stack' }, [
    message ? h('p', {}, message) : null,
    h('label', { class: 'player-field' }, [h('span', {}, 'Tra cứu vé'), input]),
    h('button', { class: 'btn btn-gold', type: 'submit' }, 'Xem vé'),
  ]);
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const code = input.value.trim();
    if (!code) return;
    window.location.href = `./booking-status.html?ticket=${encodeURIComponent(code)}`;
  });
  root.replaceChildren(h('div', { class: 'container' }, [
    h('h1', { class: 'player-title' }, 'Vé đặt phòng'),
    h('section', { class: 'player-panel' }, [form]),
  ]));
}

async function onDeposit() {
  if (view.processing) {
    toast('Giao dịch đang xử lý. Không bấm thanh toán liên tục.');
    return;
  }
  view.processing = true;
  view.notice = 'Đang gửi yêu cầu đặt cọc. Trạng thái chỉ đổi khi máy chủ xác nhận.';
  view.noticeTone = 'ok';
  paint();
  try {
    const outcome = await requestDeposit(view.payment.roomId || roomQuery, ticketQuery);
    if (outcome.redirected) return;
    view.booking = outcome.snapshot.booking;
    view.payment = outcome.snapshot.payment;
    if (view.payment.status === PAYMENT_STATUS.DEPOSIT_PAID || view.payment.status === PAYMENT_STATUS.FULLY_PAID) {
      view.notice = 'Thanh toán thành công. Máy chủ đã ghi nhận tiền cọc.';
      toast('Thanh toán thành công.');
    } else if (view.payment.status === PAYMENT_STATUS.PROCESSING) {
      view.notice = 'Giao dịch đang xử lý. Không bấm thanh toán liên tục.';
      toast('Đang xử lý thanh toán.');
    } else {
      view.notice = `Máy chủ chưa xác nhận đặt cọc. Trạng thái: ${view.payment.statusLabel}.`;
      view.noticeTone = 'err';
      toast(view.notice, 'error');
    }
  } catch (err) {
    view.notice = errorMessage(err);
    view.noticeTone = 'err';
    toast(view.notice, 'error');
  } finally {
    view.processing = false;
    paint();
  }
}

async function onRemaining() {
  if (view.processing) {
    toast('Giao dịch đang xử lý. Không bấm thanh toán liên tục.');
    return;
  }
  view.processing = true;
  view.notice = 'Đang gửi yêu cầu thanh toán phần còn lại.';
  view.noticeTone = 'ok';
  paint();
  try {
    const outcome = await requestRemaining(view.payment.roomId || roomQuery, ticketQuery);
    if (outcome.redirected) return;
    view.booking = outcome.snapshot.booking;
    view.payment = outcome.snapshot.payment;
    if (view.payment.status === PAYMENT_STATUS.FULLY_PAID) {
      view.notice = 'Máy chủ đã xác nhận thanh toán đầy đủ.';
      toast('Thanh toán đầy đủ thành công.');
    } else {
      view.notice = `Chưa chuyển sang đã thanh toán đầy đủ. Trạng thái: ${view.payment.statusLabel}.`;
      view.noticeTone = 'err';
      toast(view.notice, 'error');
    }
  } catch (err) {
    view.notice = errorMessage(err);
    view.noticeTone = 'err';
    toast(view.notice, 'error');
  } finally {
    view.processing = false;
    paint();
  }
}

function timeline(booking, payment) {
  const blob = `${booking.status} ${payment.status} ${booking.room?.status || ''}`.toLowerCase();
  const steps = [
    { id: 'created', label: 'Khởi tạo yêu cầu', done: Boolean(booking.id || booking.roomCode) },
    { id: 'joined', label: 'Xác nhận tham gia', done: Boolean(booking.ticketCode || /joined|confirmed|deposit|paid/.test(blob)) },
    { id: 'schedule', label: 'Xác nhận lịch', done: /locked|confirmed|scheduled|in_progress|completed/.test(blob) },
    { id: 'awaiting', label: 'Chờ đặt cọc', done: /awaiting_deposit|deposit_pending|deposit_paid|fully_paid/.test(blob) },
    { id: 'deposited', label: 'Đã đặt cọc', done: /deposit_paid|fully_paid/.test(String(payment.status || '')) },
    { id: 'done', label: 'Xác nhận hoàn tất', done: payment.status === PAYMENT_STATUS.FULLY_PAID || /completed/.test(blob) },
  ];
  let current = -1;
  steps.forEach((step, index) => { if (step.done) current = index; });
  return h('ol', { class: 'booking-timeline' }, steps.map((step, index) => (
    h('li', { class: step.done ? (index === current ? 'is-current' : 'is-done') : '' }, [
      step.done ? '●' : '○',
      ' ',
      step.label,
    ])
  )));
}

function paint() {
  const booking = view.booking;
  const payment = view.payment;
  if (!booking || !payment) return;
  const room = booking.room;
  const members = room?.memberCount;
  const capacity = room?.capacity;
  const ratio = members != null && capacity ? Math.min(100, Math.round((members / capacity) * 100)) : 0;
  const expiredUi = clockExpired(payment.deadline) && payment.status === PAYMENT_STATUS.AWAITING;

  root.replaceChildren(h('div', { class: 'container player-stack' }, [
    params.get('joined') ? h('p', { class: 'player-banner' }, 'Bạn đã tham gia phòng. Vé dưới đây lấy từ máy chủ.') : null,
    h('header', { class: 'player-hero' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, 'Player Portal'),
        h('h1', { class: 'player-title' }, 'Vé đặt phòng của bạn'),
        h('p', { class: 'player-lead' }, 'Mã phòng, mã vé, tiến trình thành lập và hạn đặt cọc của lượt tham gia này.'),
      ]),
    ]),
    h('article', { class: 'ticket-card' }, [
      h('div', { class: 'booking-ticket-head' }, [
        h('div', {}, [
          h('span', { class: statusStampClass(booking.status || room?.status) }, statusLabel(booking.status || room?.status)),
          h('h2', {}, booking.title),
        ]),
      ]),
      h('hr', { class: 'ticket-divider' }),
      h('dl', { class: 'player-definition' }, [
        h('dt', {}, 'Mã phòng'),
        h('dd', { class: 'room-code' }, booking.roomCode || '—'),
        h('dt', {}, 'Mã vé'),
        h('dd', { class: 'room-code' }, booking.ticketCode || '—'),
        h('dt', {}, 'Trò chơi'),
        h('dd', {}, booking.title),
        h('dt', {}, 'Ngày chơi'),
        h('dd', {}, formatDate(booking.playDate)),
        h('dt', {}, 'Giờ chơi'),
        h('dd', {}, formatClock(booking.playTime)),
        h('dt', {}, 'Tên người chơi'),
        h('dd', {}, payment.playerName || '—'),
        h('dt', {}, 'Ngày đặt'),
        h('dd', {}, formatDateTime(booking.raw?.created_at || booking.raw?.booked_at || '')),
        h('dt', {}, 'Trạng thái tham gia'),
        h('dd', {}, statusLabel(booking.status)),
      ]),
      h('hr', { class: 'ticket-divider' }),
      h('h3', {}, 'Tiến trình đặt phòng'),
      timeline(booking, payment),
      h('hr', { class: 'ticket-divider' }),
      h('h3', {}, 'Tiến trình thành lập phòng'),
      h('p', {}, members == null ? 'Chưa có số thành viên.' : `${members}${capacity ? ` / ${capacity}` : ''} thành viên`),
      h('div', { class: 'room-progress', 'aria-hidden': 'true' }, [h('span', { style: `width:${ratio}%` })]),
    ]),
    expiredUi ? h('p', { class: 'player-banner' }, 'Đồng hồ đã về 00:00:00. Trạng thái chính thức vẫn lấy từ API, không xóa người chơi trên trình duyệt.') : null,
    renderPaymentCard(payment, {
      processing: view.processing,
      notice: view.notice,
      noticeTone: view.noticeTone,
      onDeposit,
      onRemaining,
    }),
    h('div', { class: 'player-actions' }, [
      room?.id ? h('a', { class: 'btn btn-primary', href: `./room-detail.html?id=${encodeURIComponent(room.id)}` }, 'Chi tiết phòng') : null,
      h('a', { class: 'btn btn-outline', href: './profile.html?tab=awaiting_deposit' }, 'Hồ sơ'),
      h('a', { class: 'btn btn-outline', href: './lobby.html' }, 'Về sảnh'),
    ]),
  ]));
  bindClock(payment.deadline);
}

async function refreshFromApi() {
  const snapshot = await fetchOwnPayment({
    roomId: view.payment?.roomId || roomQuery,
    ticket: ticketQuery,
  });
  view.booking = snapshot.booking;
  view.payment = snapshot.payment;
  const remind = maybeRemind(snapshot.payment);
  if (remind) toast(remind);
  paint();
}

async function boot() {
  mountChrome('ticket');
  if (!roomQuery && !ticketQuery) {
    paintLookup('Nhập mã phòng hoặc mã vé để xem trạng thái giữ chỗ.');
    return;
  }
  renderState(root, { kind: 'loading', title: 'Đang lấy vé', message: 'Đang hỏi thông tin đặt phòng từ máy chủ.' });
  try {
    const snapshot = await fetchOwnPayment({ roomId: roomQuery, ticket: ticketQuery });
    if (!snapshot.booking.roomCode && !snapshot.booking.ticketCode && !snapshot.booking.title) {
      paintLookup('Máy chủ không trả vé tương ứng. Hãy thử mã khác.');
      return;
    }
    view.booking = snapshot.booking;
    view.payment = snapshot.payment;
    const remind = maybeRemind(snapshot.payment);
    if (remind) toast(remind);
    paint();
    stopPoll = startPaymentPoll(async () => {
      const next = await fetchOwnPayment({
        roomId: snapshot.payment.roomId || roomQuery,
        ticket: ticketQuery,
      });
      return next;
    }, (next) => {
      if (view.processing) return;
      view.booking = next.booking;
      view.payment = next.payment;
      const remindNext = maybeRemind(next.payment);
      if (remindNext) toast(remindNext);
      paint();
    }, 8000);
  } catch (err) {
    root.replaceChildren(h('div', { class: 'container' }, [
      h('div', { id: 'booking-status-error' }),
    ]));
    renderState(document.getElementById('booking-status-error'), {
      kind: 'error',
      title: 'Không lấy được vé',
      message: errorMessage(err),
      actionLabel: 'Thử lại',
      onAction: () => window.location.reload(),
    });
  }
}

window.addEventListener('kbs:payment-changed', () => {
  refreshFromApi().catch(() => {});
});

window.addEventListener('pagehide', () => {
  window.clearInterval(timerId);
  if (stopPoll) stopPoll();
});

boot();
