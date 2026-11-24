import { playerApi } from '../../api/player-api.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { confirmDialog, renderState, toast, withBusy } from '../../components/player-ui.js';
import { errorMessage, formatClock, formatDate, formatDateTime, formatVnd, initials, safeUrl, statusLabel, statusStampClass } from '../../logic/format.js';
import { normalizeRoom } from '../../logic/normalize.js';
import { refundEligible, sessionStartIso } from '../../logic/timer.engine.js';

const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
const roomId = params.get('id') || '';

function avatar(name, url) {
  const safe = safeUrl(url);
  if (!safe) return h('span', { class: 'room-avatar-fallback', 'aria-hidden': 'true' }, initials(name));
  const image = h('img', { class: 'room-avatar', alt: '', src: safe });
  image.addEventListener('error', () => image.replaceWith(h('span', { class: 'room-avatar-fallback' }, initials(name))));
  return image;
}

function proposalsOf(room) {
  const list = Array.isArray(room.proposals) ? room.proposals : [];
  return list.map((item) => ({
    id: String(item.id || ''),
    playDate: item.play_date || item.date || '',
    playTime: item.play_time || item.time || '',
    yes: Number(item.yes_count ?? item.yes ?? 0),
    no: Number(item.no_count ?? item.no ?? 0),
    myVote: item.my_vote || item.vote || '',
  })).filter((item) => item.id);
}

function venueReady(venue) {
  if (!venue || typeof venue !== 'object') return false;
  if (venue.confirmed === false) return false;
  const status = String(venue.status || '').toLowerCase();
  if (['pending', 'unassigned', 'draft'].includes(status)) return false;
  return Boolean(venue.name || venue.room_name || venue.address);
}

function sessionMoment(room) {
  return room.session?.starts_at || sessionStartIso(
    room.session?.play_date || room.playDate,
    room.session?.play_time || room.playTime
  );
}

async function vote(proposalId, decision, button) {
  try {
    await withBusy(button, async () => {
      button.textContent = 'Đang gửi...';
      await playerApi.voteSchedule(proposalId, { decision });
    });
    toast(decision === 'yes' ? 'Đã đồng ý khung giờ.' : 'Đã từ chối khung giờ.');
    await load();
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

async function propose(event) {
  event.preventDefault();
  const date = document.getElementById('room-propose-date').value;
  const time = document.getElementById('room-propose-time').value;
  if (!date || !time) {
    toast('Hãy chọn ngày và giờ đề xuất.', 'error');
    return;
  }
  const button = event.submitter || event.target.querySelector('button[type="submit"]');
  try {
    await withBusy(button, async () => {
      await playerApi.proposeSchedule(roomId, { play_date: date, play_time: time });
    });
    toast('Đã gửi đề xuất lịch.');
    await load();
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

async function requestLeave(room) {
  const start = sessionMoment(room);
  const eligible = start ? refundEligible(start) : null;
  let message = 'Chưa có giờ chơi chính thức. Nếu rời phòng trong vòng 24 giờ trước giờ chơi, tiền cọc sẽ không được hoàn.';
  if (eligible === true) {
    message = 'Bạn đang hủy trước giờ chơi hơn 24 giờ. Hệ thống sẽ gửi yêu cầu hoàn 100% cọc 50.000đ để Admin duyệt.';
  } else if (eligible === false) {
    message = 'Giờ chơi còn chưa tới 24 giờ. Nếu tiếp tục, bạn mất tiền cọc và không được hoàn.';
  }
  const agreed = await confirmDialog({
    title: 'Rời phòng',
    message,
    confirmText: 'Gửi yêu cầu rời phòng',
    tone: eligible === false ? 'danger' : 'primary',
  });
  if (!agreed) return;
  const button = document.getElementById('room-leave');
  try {
    await withBusy(button, async () => {
      button.textContent = 'Đang gửi...';
      await playerApi.leaveRoom(roomId, {
        acknowledge_forfeit: eligible === false,
      });
    });
    toast('Đã gửi yêu cầu rời phòng.');
    await load();
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

function refundBlock(room) {
  const refund = room.refund || room.leaveRequest?.refund;
  const request = room.leaveRequest;
  if (!refund && !request) return h('p', {}, 'Chưa có yêu cầu hoàn cọc.');
  return h('dl', { class: 'player-definition' }, [
    h('dt', {}, 'Yêu cầu rời phòng'),
    h('dd', {}, request ? statusLabel(request.status) : '—'),
    h('dt', {}, 'Hoàn cọc'),
    h('dd', {}, refund ? statusLabel(refund.status) : 'Đang chờ xét duyệt'),
    h('dt', {}, 'Số tiền'),
    h('dd', {}, refund?.amount != null || request?.amount != null ? formatVnd(refund?.amount ?? request?.amount) : '—'),
    h('dt', {}, 'Ghi chú'),
    h('dd', {}, refund?.note || request?.note || request?.reason || '—'),
  ]);
}

function paint(room) {
  const capacity = room.capacity;
  const filled = room.memberCount ?? room.members.length;
  const slots = capacity == null ? [] : Array.from({ length: capacity }, (_, index) => index < (filled || 0));
  const proposalNodes = proposalsOf(room);
  const payment = room.payment || {};
  const venue = venueReady(room.venue) ? room.venue : null;

  root.replaceChildren(h('div', { class: 'container player-stack' }, [
    params.get('created') ? h('p', { class: 'player-banner' }, 'Phòng đã được tạo. Thông tin bên dưới lấy từ máy chủ.') : null,
    h('header', { class: 'player-hero' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, room.code),
        h('h1', { class: 'player-title' }, room.game?.title || 'Chi tiết phòng'),
        h('p', { class: 'player-lead' }, room.game?.summary || 'Chưa có mô tả kịch bản.'),
      ]),
      h('div', { class: 'player-actions' }, [
        h('span', { class: statusStampClass(room.status) }, statusLabel(room.status)),
        h('a', { class: 'btn btn-outline', href: `./booking-status.html?room=${encodeURIComponent(room.id)}` }, 'Xem vé'),
      ]),
    ]),
    h('div', { class: 'player-grid-2' }, [
      h('section', { class: 'ticket-card' }, [
        h('h2', {}, 'Kịch bản'),
        h('dl', { class: 'player-definition' }, [
          h('dt', {}, 'Thể loại'),
          h('dd', {}, room.genre || room.game?.genre || '—'),
          h('dt', {}, 'Độ khó'),
          h('dd', {}, room.game?.difficulty || '—'),
          h('dt', {}, 'Thời lượng'),
          h('dd', {}, room.game?.duration || '—'),
          h('dt', {}, 'Ghi chú phòng'),
          h('dd', {}, room.notes || 'Không có'),
        ]),
      ]),
      h('section', { class: 'player-panel' }, [
        h('h2', { class: 'player-section-title' }, 'Thành viên và slot'),
        h('p', { class: 'player-meta' }, `Thành viên: ${filled == null ? '—' : filled} · Slot trống: ${room.openSlots == null ? '—' : room.openSlots}`),
        slots.length ? h('div', { class: 'room-slots', 'aria-label': 'Sơ đồ slot' }, slots.map((taken, index) => (
          h('span', { class: taken ? 'room-slot is-filled' : 'room-slot' }, String(index + 1))
        ))) : null,
        room.members.length
          ? h('div', {}, room.members.map((member) => h('div', { class: 'room-member' }, [
            avatar(member.name, member.avatarUrl),
            h('div', {}, [
              h('strong', {}, member.name),
              h('p', { class: 'player-meta' }, statusLabel(member.status || member.role)),
            ]),
          ])))
          : h('p', {}, 'Chưa có danh sách thành viên chi tiết.'),
      ]),
    ]),
    h('section', { class: 'player-panel player-stack' }, [
      h('h2', { class: 'player-section-title' }, 'Đề xuất và biểu quyết lịch'),
      proposalNodes.length
        ? h('div', { class: 'player-stack' }, proposalNodes.map((item) => {
          const yes = h('button', { class: 'btn btn-gold', type: 'button' }, 'Đồng ý');
          const no = h('button', { class: 'btn btn-outline', type: 'button' }, 'Từ chối');
          if (item.myVote) {
            yes.disabled = true;
            no.disabled = true;
          }
          yes.addEventListener('click', () => vote(item.id, 'yes', yes));
          no.addEventListener('click', () => vote(item.id, 'no', no));
          return h('article', { class: 'room-card' }, [
            h('strong', {}, `${formatDate(item.playDate)} · ${formatClock(item.playTime)}`),
            h('p', { class: 'player-meta' }, `Đồng ý ${item.yes} · Từ chối ${item.no}${item.myVote ? ` · Bạn đã chọn ${item.myVote}` : ''}`),
            h('div', { class: 'player-actions' }, [yes, no]),
          ]);
        }))
        : h('p', {}, 'Chưa có đề xuất lịch. Hãy gửi khung giờ bên dưới.'),
      h('form', { class: 'player-filters', id: 'room-propose-form' }, [
        h('label', { class: 'player-field' }, [
          h('span', {}, 'Ngày đề xuất'),
          h('input', { class: 'player-input', id: 'room-propose-date', type: 'date', required: true }),
        ]),
        h('label', { class: 'player-field' }, [
          h('span', {}, 'Giờ đề xuất'),
          h('input', { class: 'player-input', id: 'room-propose-time', type: 'time', min: '09:00', max: '23:00', step: '1800', required: true }),
        ]),
        h('button', { class: 'btn btn-primary', type: 'submit' }, 'Đề xuất giờ'),
      ]),
    ]),
    h('div', { class: 'player-grid-2' }, [
      h('section', { class: 'player-panel' }, [
        h('h2', { class: 'player-section-title' }, 'Quản trò'),
        room.dm && (room.dm.display_name || room.dm.full_name || room.dm.name)
          ? h('div', { class: 'room-member' }, [
            avatar(room.dm.display_name || room.dm.full_name || room.dm.name, room.dm.avatar_url || room.dm.avatar),
            h('div', {}, [
              h('strong', {}, room.dm.display_name || room.dm.full_name || room.dm.name),
              h('p', { class: 'player-meta' }, room.dm.status ? statusLabel(room.dm.status) : 'Đã phân công'),
            ]),
          ])
          : h('p', {}, 'Chưa có Quản trò được phân công.'),
      ]),
      h('section', { class: 'player-panel' }, [
        h('h2', { class: 'player-section-title' }, 'Phiên chơi'),
        room.session || room.playDate
          ? h('dl', { class: 'player-definition' }, [
            h('dt', {}, 'Ngày'),
            h('dd', {}, formatDate(room.session?.play_date || room.playDate)),
            h('dt', {}, 'Giờ'),
            h('dd', {}, formatClock(room.session?.play_time || room.playTime)),
            h('dt', {}, 'Trạng thái'),
            h('dd', {}, statusLabel(room.session?.status || 'draft')),
          ])
          : h('p', {}, 'Phiên chơi chưa được chốt.'),
      ]),
    ]),
    h('div', { class: 'player-grid-2' }, [
      h('section', { class: 'player-panel' }, [
        h('h2', { class: 'player-section-title' }, 'Phòng vật lý'),
        venue
          ? h('dl', { class: 'player-definition' }, [
            h('dt', {}, 'Phòng'),
            h('dd', {}, venue.name || venue.room_name),
            h('dt', {}, 'Địa chỉ'),
            h('dd', {}, venue.address || '—'),
            h('dt', {}, 'Trạng thái'),
            h('dd', {}, statusLabel(venue.status || 'confirmed')),
          ])
          : h('p', {}, 'Phòng vật lý chưa được xác nhận.'),
      ]),
      h('section', { class: 'player-panel player-stack' }, [
        h('h2', { class: 'player-section-title' }, 'Thanh toán'),
        h('dl', { class: 'player-definition' }, [
          h('dt', {}, 'Trạng thái'),
          h('dd', {}, statusLabel(payment.status)),
          h('dt', {}, 'Cọc'),
          h('dd', {}, formatVnd(payment.deposit_amount ?? payment.deposit)),
          h('dt', {}, 'Còn lại'),
          h('dd', {}, formatVnd(payment.remaining_amount ?? payment.remaining)),
          h('dt', {}, 'Hạn cọc'),
          h('dd', {}, payment.deposit_deadline_at ? formatDateTime(payment.deposit_deadline_at) : '—'),
        ]),
        h('a', { class: 'btn btn-outline', href: `./booking-status.html?room=${encodeURIComponent(room.id)}` }, 'Xem trạng thái thanh toán'),
      ]),
    ]),
    h('section', { class: 'player-panel player-stack' }, [
      h('h2', { class: 'player-section-title' }, 'Rời phòng và hoàn cọc'),
      refundBlock(room),
      room.leaveRequest
        ? h('p', {}, 'Yêu cầu rời phòng đã được ghi nhận. Bạn có thể theo dõi trạng thái hoàn cọc ở trên.')
        : h('button', { class: 'btn booking-btn-danger', type: 'button', id: 'room-leave' }, 'Gửi yêu cầu rời phòng'),
    ]),
  ]));

  document.getElementById('room-propose-form')?.addEventListener('submit', (event) => {
    propose(event).catch((err) => toast(errorMessage(err), 'error'));
  });
  document.getElementById('room-leave')?.addEventListener('click', () => {
    requestLeave(room).catch((err) => toast(errorMessage(err), 'error'));
  });
}

async function load() {
  if (!roomId) {
    renderState(root, {
      kind: 'error',
      title: 'Thiếu mã phòng',
      message: 'Hãy mở phòng từ sảnh hoặc từ vé đặt phòng.',
      actionLabel: 'Về sảnh',
      onAction: () => { window.location.href = './lobby.html'; },
    });
    return;
  }
  renderState(root, { kind: 'loading', title: 'Đang mở phòng', message: 'Đang lấy chi tiết từ máy chủ.' });
  try {
    const data = await playerApi.getRoom(roomId);
    const room = normalizeRoom(data);
    if (!room) {
      renderState(root, {
        kind: 'empty',
        title: 'Không có dữ liệu phòng',
        message: 'Máy chủ trả về một phản hồi không đủ để hiển thị phòng.',
      });
      return;
    }
    paint(room);
  } catch (err) {
    renderState(root, {
      kind: 'error',
      title: 'Không tải được phòng',
      message: errorMessage(err),
      actionLabel: 'Thử lại',
      onAction: () => load(),
    });
  }
}

mountChrome('lobby');
load();
