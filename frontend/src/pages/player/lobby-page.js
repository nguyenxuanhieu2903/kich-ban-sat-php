import { playerApi } from '../../api/player-api.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { confirmDialog, renderState, toast, withBusy } from '../../components/player-ui.js';
import { errorMessage, formatDate, formatClock, statusLabel, statusStampClass } from '../../logic/format.js';
import { extractList, normalizeGame, normalizeRoom, openSlots, readPagination } from '../../logic/normalize.js';

const PER_PAGE = 6;
const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
const state = {
  q: params.get('q') || '',
  genre: params.get('genre') || 'all',
  status: params.get('status') || 'all',
  page: Math.max(1, Number(params.get('page')) || 1),
  games: [],
  rooms: [],
  pagination: null,
  loading: true,
  error: '',
};

function syncUrl() {
  const next = new URLSearchParams();
  if (state.q) next.set('q', state.q);
  if (state.genre !== 'all') next.set('genre', state.genre);
  if (state.status !== 'all') next.set('status', state.status);
  if (state.page > 1) next.set('page', String(state.page));
  const query = next.toString();
  window.history.replaceState(null, '', query ? `?${query}` : window.location.pathname);
}

function canJoin(room) {
  const closed = ['locked', 'confirmed', 'completed', 'cancelled', 'canceled', 'closed', 'full'];
  if (closed.includes(String(room.status || '').toLowerCase())) return false;
  if (room.recruitmentOpen === false) return false;
  const slots = room.openSlots ?? openSlots(room);
  if (slots != null && slots <= 0) return false;
  return true;
}

function recruitmentText(room) {
  if (room.recruitmentStatus) return statusLabel(room.recruitmentStatus);
  if (!canJoin(room)) return 'Ngừng tuyển';
  return 'Đang tuyển người';
}

async function joinRoom(room, button) {
  if (room.game?.is18Plus) {
    const adult = await confirmDialog({
      title: 'Xác nhận đủ 18 tuổi',
      message: `Kịch bản “${room.game.title}” chỉ dành cho người chơi từ 18 tuổi. Bạn xác nhận đã đủ tuổi?`,
      confirmText: 'Tôi đã đủ 18 tuổi',
    });
    if (!adult) return;
  }
  const agreed = await confirmDialog({
    title: 'Tham gia phòng',
    message: `Tham gia phòng ${room.code}? Bạn sẽ giữ chỗ và cần đặt cọc 50.000đ trong 4 giờ.`,
    confirmText: 'Tham gia',
  });
  if (!agreed) return;
  await withBusy(button, async () => {
    button.textContent = 'Đang tham gia...';
    const data = await playerApi.joinRoom(room.id);
    toast('Đã gửi yêu cầu tham gia phòng.');
    const ticket = data?.ticket_code || data?.booking_code;
    const target = ticket
      ? `./booking-status.html?ticket=${encodeURIComponent(ticket)}&joined=1`
      : `./booking-status.html?room=${encodeURIComponent(room.id)}&joined=1`;
    window.location.href = target;
  });
}

function roomCard(room) {
  const slots = room.openSlots ?? openSlots(room);
  const members = room.memberCount;
  const join = h('button', { class: 'btn btn-primary', type: 'button' }, 'Tham gia');
  if (!canJoin(room)) join.disabled = true;
  join.addEventListener('click', () => {
    joinRoom(room, join).catch((err) => toast(errorMessage(err), 'error'));
  });
  return h('article', { class: 'room-card' }, [
    h('div', { class: 'player-actions' }, [
      h('span', { class: statusStampClass(room.status) }, statusLabel(room.status || 'recruiting')),
      h('span', { class: 'stamp-badge hold' }, recruitmentText(room)),
    ]),
    h('p', { class: 'room-code' }, room.code),
    h('h2', {}, room.game?.title || 'Phòng chơi'),
    h('p', { class: 'player-meta' }, room.genre || room.game?.genre || 'Chưa rõ thể loại'),
    h('p', { class: 'player-meta' }, [
      `Thành viên: ${members == null ? '—' : members}`,
      ' · ',
      `Slot trống: ${slots == null ? '—' : slots}`,
      room.capacity != null ? ` / ${room.capacity}` : '',
    ]),
    h('p', { class: 'player-meta' }, [
      formatDate(room.playDate),
      ' · ',
      formatClock(room.playTime),
    ]),
    h('div', { class: 'player-actions' }, [
      h('a', { class: 'btn btn-outline', href: `./room-detail.html?id=${encodeURIComponent(room.id)}` }, 'Chi tiết phòng'),
      join,
    ]),
  ]);
}

function paintResults(host) {
  if (state.loading) {
    renderState(host, { kind: 'loading', title: 'Đang tải sảnh phòng', message: 'Đang hỏi danh sách phòng từ máy chủ.' });
    return;
  }
  if (state.error) {
    renderState(host, {
      kind: 'error',
      title: 'Không tải được sảnh phòng',
      message: state.error,
      actionLabel: 'Thử lại',
      onAction: () => load(),
    });
    return;
  }
  if (!state.rooms.length) {
    renderState(host, {
      kind: 'empty',
      title: 'Chưa có phòng phù hợp',
      message: 'Hãy đổi từ khóa, thể loại hoặc trạng thái. Bạn cũng có thể tạo phòng mới.',
      actionLabel: 'Tạo phòng',
      onAction: () => { window.location.href = './create-room.html'; },
    });
    return;
  }
  host.replaceChildren(h('div', { class: 'room-grid' }, state.rooms.map(roomCard)));
}

function paintPager(host) {
  const pages = state.pagination?.pages || 1;
  const page = state.pagination?.page || state.page;
  if (state.loading || state.error || pages <= 1) {
    host.replaceChildren();
    return;
  }
  const buttons = [];
  const go = (next) => h('button', {
    class: 'btn btn-outline',
    type: 'button',
    'aria-current': next === page ? 'page' : false,
    disabled: next === page || next < 1 || next > pages,
    onClick: () => {
      state.page = next;
      load();
    },
  }, String(next));
  buttons.push(h('button', {
    class: 'btn btn-outline',
    type: 'button',
    disabled: page <= 1,
    onClick: () => { state.page = page - 1; load(); },
  }, 'Trước'));
  for (let number = 1; number <= pages && number <= 8; number += 1) buttons.push(go(number));
  buttons.push(h('button', {
    class: 'btn btn-outline',
    type: 'button',
    disabled: page >= pages,
    onClick: () => { state.page = page + 1; load(); },
  }, 'Sau'));
  const total = state.pagination?.total;
  host.replaceChildren(h('nav', { class: 'player-pager', 'aria-label': 'Phân trang phòng' }, [
    ...buttons,
    total != null ? h('span', { class: 'player-meta' }, `${total} phòng`) : null,
  ]));
}

async function load() {
  state.loading = true;
  state.error = '';
  syncUrl();
  paintResults(document.getElementById('room-results'));
  paintPager(document.getElementById('room-pager'));
  try {
    const payload = await playerApi.listRooms({
      q: state.q,
      genre: state.genre,
      status: state.status,
      page: state.page,
      per_page: PER_PAGE,
    });
    const { items, pagination } = extractList(payload, ['items', 'rooms', 'data']);
    const rooms = items.map(normalizeRoom).filter(Boolean);
    const serverPage = readPagination(pagination, state.page);
    if (serverPage) {
      state.rooms = rooms;
      state.pagination = serverPage;
      state.page = serverPage.page;
    } else {
      const pages = Math.max(1, Math.ceil(rooms.length / PER_PAGE));
      state.page = Math.min(state.page, pages);
      const start = (state.page - 1) * PER_PAGE;
      state.rooms = rooms.slice(start, start + PER_PAGE);
      state.pagination = { page: state.page, pages, total: rooms.length };
    }
  } catch (err) {
    state.error = errorMessage(err);
    state.rooms = [];
    state.pagination = null;
  } finally {
    state.loading = false;
    paintResults(document.getElementById('room-results'));
    paintPager(document.getElementById('room-pager'));
  }
}

function mount() {
  const genre = h('select', { class: 'player-select', id: 'room-genre' }, [
    h('option', { value: 'all' }, 'Mọi thể loại'),
  ]);
  const status = h('select', { class: 'player-select', id: 'room-status' }, [
    ['all', 'Mọi trạng thái'],
    ['recruiting', 'Đang tuyển'],
    ['holding', 'Giữ chỗ'],
    ['locked', 'Đã chốt'],
    ['completed', 'Đã hoàn thành'],
  ].map(([value, label]) => h('option', { value, selected: value === state.status }, label)));
  const search = h('input', {
    class: 'player-input',
    type: 'search',
    value: state.q,
    placeholder: 'Tìm mã phòng hoặc tên kịch bản',
    'aria-label': 'Tìm phòng',
  });
  const form = h('form', { class: 'player-filters', id: 'room-filters' }, [
    search,
    genre,
    status,
    h('button', { class: 'btn btn-gold', type: 'submit' }, 'Lọc phòng'),
  ]);
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    state.q = search.value.trim();
    state.genre = genre.value;
    state.status = status.value;
    state.page = 1;
    load();
  });

  root.replaceChildren(h('div', { class: 'container' }, [
    h('header', { class: 'player-hero' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, 'Player Portal'),
        h('h1', { class: 'player-title' }, 'Sảnh phòng'),
        h('p', { class: 'player-lead' }, 'Tìm phòng đang tuyển, xem số thành viên và slot còn trống, rồi tham gia.'),
      ]),
      h('a', { class: 'btn btn-primary', href: './create-room.html' }, 'Tạo phòng mới'),
    ]),
    form,
    h('div', { id: 'room-results' }),
    h('div', { id: 'room-pager' }),
  ]));

  playerApi.listGames().then((payload) => {
    const { items } = extractList(payload, ['items', 'games', 'data']);
    const genres = [...new Set(items.map(normalizeGame).filter(Boolean).map((game) => game.genre).filter(Boolean))];
    genres.forEach((name) => {
      genre.append(h('option', { value: name, selected: name === state.genre }, name));
    });
  }).catch(() => {
    genre.append(h('option', { value: '', disabled: true }, 'Chưa tải được thể loại'));
  });

  load();
}

mountChrome('lobby');
mount();
