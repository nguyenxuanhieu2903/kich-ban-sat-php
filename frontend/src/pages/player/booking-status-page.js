import { playerApi } from '../../api/player-api.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { renderState } from '../../components/player-ui.js';
import { errorMessage, formatClock, formatDate, formatDateTime, formatVnd, statusLabel, statusStampClass } from '../../logic/format.js';
import { normalizeBooking } from '../../logic/normalize.js';
import { formatCountdown, remainingMs } from '../../logic/timer.engine.js';

const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
let timerId = 0;

function transactionsOf(booking) {
  const raw = booking.raw || {};
  const lists = [
    raw.transactions,
    raw.payment?.transactions,
    booking.room?.payment?.transactions,
    booking.room?.raw?.transactions,
  ];
  const found = lists.find((list) => Array.isArray(list));
  return found || [];
}

function paymentOf(booking) {
  return booking.raw?.payment || booking.room?.payment || {};
}

function paint(booking) {
  const room = booking.room;
  const payment = paymentOf(booking);
  const members = room?.memberCount;
  const capacity = room?.capacity;
  const ratio = members != null && capacity ? Math.min(100, Math.round((members / capacity) * 100)) : 0;
  const deadline = payment.deposit_deadline_at || payment.deadline_at || booking.raw?.deposit_deadline_at;
  const transactions = transactionsOf(booking);
  const clock = h('p', { class: 'booking-clock', id: 'booking-clock' }, deadline ? formatCountdown(remainingMs(deadline)) : '—');

  const history = transactions.length
    ? h('table', { class: 'booking-history' }, [
      h('thead', {}, [h('tr', {}, ['Thời điểm', 'Loại', 'Số tiền', 'Trạng thái'].map((label) => h('th', { scope: 'col' }, label)))]),
      h('tbody', {}, transactions.map((item) => h('tr', {}, [
        h('td', {}, formatDateTime(item.created_at || item.paid_at || item.at)),
        h('td', {}, item.type || item.kind || 'Giao dịch'),
        h('td', {}, formatVnd(item.amount)),
        h('td', {}, statusLabel(item.status)),
      ]))),
    ])
    : h('p', {}, 'Chưa có giao dịch nào.');

  root.replaceChildren(h('div', { class: 'container' }, [
    params.get('joined') ? h('p', { class: 'player-banner' }, 'Bạn đã tham gia phòng. Vé dưới đây lấy từ máy chủ.') : null,
    h('header', { class: 'player-hero' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, 'Player Portal'),
        h('h1', { class: 'player-title' }, 'Vé đặt phòng'),
        h('p', { class: 'player-lead' }, 'Mã phòng, mã vé, tiến trình thành lập và hạn đặt cọc.'),
      ]),
    ]),
    h('article', { class: 'ticket-card' }, [
      h('div', { class: 'booking-ticket-head' }, [
        h('div', {}, [
          h('span', { class: statusStampClass(booking.status || room?.status) }, statusLabel(booking.status || room?.status)),
          h('h2', {}, booking.title),
        ]),
        h('div', {}, [
          h('p', { class: 'player-label' }, 'Hạn đặt cọc'),
          clock,
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
        h('dt', {}, 'Thể loại'),
        h('dd', {}, room?.genre || room?.game?.genre || '—'),
        h('dt', {}, 'Ngày chơi'),
        h('dd', {}, formatDate(booking.playDate)),
        h('dt', {}, 'Giờ chơi'),
        h('dd', {}, formatClock(booking.playTime)),
        h('dt', {}, 'Trạng thái tham gia'),
        h('dd', {}, statusLabel(booking.status)),
      ]),
      h('hr', { class: 'ticket-divider' }),
      h('h3', {}, 'Tiến trình thành lập phòng'),
      h('p', {}, members == null ? 'Chưa có số thành viên.' : `${members}${capacity ? ` / ${capacity}` : ''} thành viên`),
      h('div', { class: 'room-progress', 'aria-hidden': 'true' }, [h('span', { style: `width:${ratio}%` })]),
      h('h3', {}, 'Thanh toán'),
      h('dl', { class: 'player-definition' }, [
        h('dt', {}, 'Trạng thái'),
        h('dd', {}, statusLabel(payment.status)),
        h('dt', {}, 'Tiền cọc'),
        h('dd', {}, formatVnd(payment.deposit_amount ?? payment.deposit)),
        h('dt', {}, 'Khoản còn lại'),
        h('dd', {}, formatVnd(payment.remaining_amount ?? payment.remaining)),
        h('dt', {}, 'Hạn chót'),
        h('dd', {}, deadline ? formatDateTime(deadline) : '—'),
      ]),
      h('hr', { class: 'ticket-divider' }),
      h('h3', {}, 'Lịch sử giao dịch'),
      history,
      h('div', { class: 'player-actions' }, [
        room?.id ? h('a', { class: 'btn btn-primary', href: `./room-detail.html?id=${encodeURIComponent(room.id)}` }, 'Chi tiết phòng') : null,
        h('a', { class: 'btn btn-outline', href: './lobby.html' }, 'Về sảnh'),
      ]),
    ]),
  ]));

  window.clearInterval(timerId);
  if (deadline) {
    timerId = window.setInterval(() => {
      const node = document.getElementById('booking-clock');
      if (node) node.textContent = formatCountdown(remainingMs(deadline));
    }, 1000);
  }
}

function lookupForm(message) {
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

async function boot() {
  mountChrome('ticket');
  const roomId = params.get('room');
  const ticket = params.get('ticket');
  if (!roomId && !ticket) {
    lookupForm('Nhập mã phòng hoặc mã vé để xem trạng thái giữ chỗ.');
    return;
  }
  renderState(root, { kind: 'loading', title: 'Đang lấy vé', message: 'Đang hỏi thông tin đặt phòng từ máy chủ.' });
  try {
    let data;
    if (roomId) data = await playerApi.getBookingByRoom(roomId);
    else {
      try {
        data = await playerApi.getBooking(ticket);
      } catch (err) {
        if (err.status !== 404) throw err;
        data = await playerApi.getBookingByRoom(ticket);
      }
    }
    const booking = normalizeBooking(data);
    if (!booking.roomCode && !booking.ticketCode && !booking.title) {
      lookupForm('Máy chủ không trả vé tương ứng. Hãy thử mã khác.');
      return;
    }
    paint(booking);
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

boot();
