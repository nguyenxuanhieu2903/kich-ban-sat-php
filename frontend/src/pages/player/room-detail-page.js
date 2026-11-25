import { playerApi, getCachedUser } from '../../api/player-api.js';
import { fetchOwnPayment, requestDeposit, requestRemaining } from '../../api/payment-session.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { renderPaymentCard } from '../../components/payment-card.js';
import { confirmDialog, renderState, toast, withBusy } from '../../components/player-ui.js';
import { errorMessage, formatClock, formatDate, formatVnd, initials, safeUrl, statusLabel, statusStampClass } from '../../logic/format.js';
import { normalizeRoom } from '../../logic/normalize.js';
import { PAYMENT_STATUS, refundLabel, startPaymentPoll } from '../../logic/payment.js';
import { formatCountdown, remainingMs, refundEligible, sessionStartIso } from '../../logic/timer.engine.js';

const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
const roomId = params.get('id') || '';
const view = {
  room: null,
  payment: null,
  processing: false,
  notice: '',
  noticeTone: 'ok',
};
let stopPoll = null;
let timerId = 0;

async function copyText(value, okMessage) {
  try {
    await navigator.clipboard.writeText(value);
    toast(okMessage);
  } catch {
    toast('Không sao chép được. Hãy chọn thủ công.', 'error');
  }
}

async function shareRoom(room) {
  const url = window.location.href;
  if (navigator.share) {
    try {
      await navigator.share({ title: `Phòng ${room.code}`, url });
      return;
    } catch {
      /* người dùng hủy share thì fallback copy */
    }
  }
  await copyText(url, 'Đã sao chép liên kết phòng.');
}

function currentUserId() {
  const user = getCachedUser();
  return String(user?.id || user?.user_id || '');
}

function isMember(room) {
  const id = currentUserId();
  if (!id) return false;
  return room.members.some((member) => String(member.id) === id);
}

function canJoinRoom(room) {
  const closed = ['locked', 'confirmed', 'completed', 'cancelled', 'canceled', 'closed', 'full'];
  if (closed.includes(String(room.status || '').toLowerCase())) return false;
  if (room.recruitmentOpen === false) return false;
  if (room.openSlots != null && room.openSlots <= 0) return false;
  return !isMember(room);
}

async function joinThisRoom(room) {
  const cached = getCachedUser();
  if (!cached || !(cached.id || cached.fullName || cached.full_name || cached.email)) {
    toast('Bạn cần đăng nhập để tham gia phòng.', 'error');
    window.location.href = './profile.html';
    return;
  }
  const agreed = await confirmDialog({
    title: 'Tham gia phòng',
    message: `Tham gia phòng ${room.code}?`,
    confirmText: 'Tham gia',
  });
  if (!agreed) return;
  const button = document.getElementById('room-join');
  try {
    await withBusy(button, async () => {
      await playerApi.joinRoom(room.id);
    });
    toast('Đã gửi yêu cầu tham gia phòng.');
    window.location.href = `./booking-status.html?room=${encodeURIComponent(room.id)}&joined=1`;
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

function publicMemberStatus(member) {
  const raw = String(member.status || '').toLowerCase();
  const paymentLike = [
    'not_open', 'awaiting_deposit', 'deposit_pending', 'deposit_paid', 'overdue',
    'fully_paid', 'refund_pending', 'refunded', 'processing', 'unpaid', 'paid',
  ];
  if (paymentLike.includes(raw)) return member.role === 'host' ? 'Chủ phòng' : 'Đã tham gia';
  return member.status || member.role || 'Đã tham gia';
}

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
    await load(true);
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
    await load(true);
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
    toast('Đã gửi yêu cầu rời phòng. Trạng thái hoàn cọc chỉ đổi khi Admin xử lý.');
    await load(true);
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

function refundPolicy(room) {
  const start = sessionMoment(room);
  const eligible = start ? refundEligible(start) : null;
  let eligibility = 'Chưa có giờ chơi chính thức nên điều kiện hoàn cọc chưa xác định.';
  if (eligible === true) eligibility = 'Đủ điều kiện yêu cầu hoàn cọc thông thường (hủy trước giờ bắt đầu ít nhất 24 giờ).';
  if (eligible === false) eligibility = 'Không đủ điều kiện hoàn cọc thông thường (hủy dưới 24 giờ trước giờ chơi).';
  return { eligible, eligibility };
}

function refundBlock(room) {
  const refund = room.refund || room.leaveRequest?.refund;
  const request = room.leaveRequest;
  const policy = refundPolicy(room);
  const refundStatus = refund?.status || request?.refund_status || (request ? 'pending' : 'none');
  return h('div', { class: 'player-stack' }, [
    h('ul', { class: 'booking-checklist' }, [
      h('li', {}, 'Hủy trước giờ bắt đầu ít nhất 24 giờ: đủ điều kiện yêu cầu hoàn cọc thông thường.'),
      h('li', {}, 'Hủy dưới 24 giờ: không đủ điều kiện hoàn cọc thông thường.'),
    ]),
    h('p', {}, policy.eligibility),
    h('dl', { class: 'player-definition' }, [
      h('dt', {}, 'Yêu cầu rời phòng'),
      h('dd', {}, request ? statusLabel(request.status) : 'Chưa yêu cầu'),
      h('dt', {}, 'Hoàn cọc'),
      h('dd', {}, refundLabel(refundStatus)),
      h('dt', {}, 'Số tiền'),
      h('dd', {}, refund?.amount != null || request?.amount != null ? formatVnd(refund?.amount ?? request?.amount) : '—'),
      h('dt', {}, 'Ghi chú'),
      h('dd', {}, refund?.note || request?.note || request?.reason || 'Frontend chỉ gửi yêu cầu và hiển thị kết quả từ máy chủ.'),
    ]),
  ]);
}

function bindClock(deadline) {
  window.clearInterval(timerId);
  if (!deadline) return;
  timerId = window.setInterval(() => {
    const node = document.getElementById('booking-clock');
    if (!node) return;
    node.textContent = formatCountdown(remainingMs(deadline));
  }, 1000);
}

async function onDeposit() {
  if (view.processing) {
    toast('Giao dịch đang xử lý. Không bấm thanh toán liên tục.');
    return;
  }
  view.processing = true;
  view.notice = 'Đang gửi yêu cầu đặt cọc.';
  view.noticeTone = 'ok';
  paint(view.room);
  try {
    const outcome = await requestDeposit(roomId);
    if (outcome.redirected) return;
    view.payment = outcome.snapshot.payment;
    if (view.payment.status === PAYMENT_STATUS.DEPOSIT_PAID) {
      view.notice = 'Thanh toán thành công. Máy chủ đã ghi nhận tiền cọc.';
      toast('Thanh toán thành công.');
    } else {
      view.notice = `Chưa xác nhận đặt cọc. Trạng thái: ${view.payment.statusLabel}.`;
      view.noticeTone = 'err';
      toast(view.notice, 'error');
    }
  } catch (err) {
    view.notice = errorMessage(err);
    view.noticeTone = 'err';
    toast(view.notice, 'error');
  } finally {
    view.processing = false;
    paint(view.room);
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
  paint(view.room);
  try {
    const outcome = await requestRemaining(roomId);
    if (outcome.redirected) return;
    view.payment = outcome.snapshot.payment;
    if (view.payment.status === PAYMENT_STATUS.FULLY_PAID) {
      view.notice = 'Máy chủ đã xác nhận thanh toán đầy đủ.';
      toast('Thanh toán đầy đủ thành công.');
    } else {
      view.notice = `Chưa thanh toán đầy đủ. Trạng thái: ${view.payment.statusLabel}.`;
      view.noticeTone = 'err';
      toast(view.notice, 'error');
    }
  } catch (err) {
    view.notice = errorMessage(err);
    view.noticeTone = 'err';
    toast(view.notice, 'error');
  } finally {
    view.processing = false;
    paint(view.room);
  }
}

function paint(room) {
  const capacity = room.capacity;
  const filled = room.memberCount ?? room.members.length;
  const slots = capacity == null ? [] : Array.from({ length: capacity }, (_, index) => index < (filled || 0));
  const proposalNodes = proposalsOf(room);
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
        h('span', { class: statusStampClass(room.status), id: 'room-status-stamp' }, statusLabel(room.status)),
        h('button', { class: 'btn btn-outline', type: 'button', id: 'room-copy' }, 'Sao chép mã'),
        h('button', { class: 'btn btn-outline', type: 'button', id: 'room-share' }, 'Chia sẻ phòng'),
        h('a', { class: 'btn btn-outline', href: './lobby.html' }, 'Quay lại sảnh'),
        h('a', { class: 'btn btn-gold', href: `./booking-status.html?room=${encodeURIComponent(room.id)}` }, 'Xem vé'),
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
        h('p', { class: 'player-meta', id: 'room-slot-meta' }, `SỐ THÀNH VIÊN / SLOT: ${filled == null ? '—' : filled}${capacity != null ? ` / ${capacity}` : ''}`),
        capacity != null ? h('div', { class: 'room-progress', 'aria-hidden': 'true' }, [h('span', { style: `width:${filled && capacity ? Math.min(100, Math.round((filled / capacity) * 100)) : 0}%` })]) : null,
        canJoinRoom(room)
          ? h('div', { class: 'player-actions' }, [
            h('p', {}, `Còn ${room.openSlots == null ? '—' : room.openSlots} slot trống.`),
            h('button', { class: 'btn btn-gold', type: 'button', id: 'room-join' }, 'Tham gia phòng'),
          ])
          : (room.openSlots === 0 || String(room.status).toLowerCase() === 'full'
            ? h('p', {}, 'Đã đủ thành viên. Chức năng tham gia đã khóa.')
            : null),
        slots.length ? h('div', { class: 'room-slots', 'aria-label': 'Sơ đồ slot' }, slots.map((taken, index) => (
          h('span', { class: taken ? 'room-slot is-filled' : 'room-slot' }, String(index + 1))
        ))) : null,
        room.members.length
          ? h('div', {}, room.members.map((member) => h('div', { class: 'room-member' }, [
            avatar(member.name, member.avatarUrl),
            h('div', {}, [
              h('strong', {}, member.name),
              h('p', { class: 'player-meta' }, [
                statusLabel(publicMemberStatus(member)),
                /host|owner|creator/i.test(member.role) || String(member.id) === String(room.creatorId) ? ' · Chủ phòng' : '',
              ]),
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
      h('section', { class: 'player-panel player-stack', id: 'room-pay-host' }, [
        h('h2', { class: 'player-section-title' }, 'Thanh toán của bạn'),
        view.payment
          ? renderPaymentCard(view.payment, {
            processing: view.processing,
            notice: view.notice,
            noticeTone: view.noticeTone,
            onDeposit,
            onRemaining,
          })
          : h('p', {}, 'Chưa lấy được trạng thái thanh toán của lượt tham gia hiện tại.'),
        h('a', { class: 'btn btn-outline', href: `./booking-status.html?room=${encodeURIComponent(room.id)}` }, 'Mở vé đặt phòng'),
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
  document.getElementById('room-copy')?.addEventListener('click', () => {
    copyText(room.code, 'Đã sao chép mã phòng.').catch((err) => toast(errorMessage(err), 'error'));
  });
  document.getElementById('room-share')?.addEventListener('click', () => {
    shareRoom(room).catch((err) => toast(errorMessage(err), 'error'));
  });
  document.getElementById('room-join')?.addEventListener('click', () => {
    joinThisRoom(room).catch((err) => toast(errorMessage(err), 'error'));
  });
  bindClock(view.payment?.deadline);
}

async function load(silent = false) {
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
  if (!silent) renderState(root, { kind: 'loading', title: 'Đang mở phòng', message: 'Đang lấy chi tiết từ máy chủ.' });
  try {
    const [data, own] = await Promise.all([
      playerApi.getRoom(roomId),
      fetchOwnPayment({ roomId }).catch(() => null),
    ]);
    const room = normalizeRoom(data);
    if (!room) {
      renderState(root, {
        kind: 'empty',
        title: 'Không có dữ liệu phòng',
        message: 'Máy chủ trả về một phản hồi không đủ để hiển thị phòng.',
      });
      return false;
    }
    view.room = room;
    if (own?.payment) view.payment = own.payment;
    paint(room);
    return true;
  } catch (err) {
    renderState(root, {
      kind: 'error',
      title: 'Không tải được phòng',
      message: errorMessage(err),
      actionLabel: 'Thử lại',
      onAction: () => load(),
    });
    return false;
  }
}

mountChrome('lobby');
load().then((ok) => {
  if (!ok || !roomId) return;
  stopPoll = startPaymentPoll(async () => {
    const [data, own] = await Promise.all([
      playerApi.getRoom(roomId),
      fetchOwnPayment({ roomId }).catch(() => null),
    ]);
    return { room: normalizeRoom(data), own };
  },     (next) => {
    if (view.processing || !next.room) return;
    view.room = next.room;
    if (next.own?.payment) view.payment = next.own.payment;
    const stamp = document.getElementById('room-status-stamp');
    if (stamp) {
      stamp.className = statusStampClass(next.room.status);
      stamp.textContent = statusLabel(next.room.status);
    }
    const meta = document.getElementById('room-slot-meta');
    if (meta) {
      const filled = next.room.memberCount ?? next.room.members.length;
      meta.textContent = `Thành viên: ${filled == null ? '—' : filled} · Slot trống: ${next.room.openSlots == null ? '—' : next.room.openSlots}`;
    }
    const payHost = document.getElementById('room-pay-host');
    const existing = payHost?.querySelector('#payment-card');
    if (existing && view.payment) {
      existing.replaceWith(renderPaymentCard(view.payment, {
        processing: view.processing,
        notice: view.notice,
        noticeTone: view.noticeTone,
        onDeposit,
        onRemaining,
      }));
      bindClock(view.payment.deadline);
    }
  }, 8000);
});
window.addEventListener('pagehide', () => {
  window.clearInterval(timerId);
  if (stopPoll) stopPoll();
});
