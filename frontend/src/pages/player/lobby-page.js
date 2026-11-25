import { playerApi, getCachedUser } from '../../api/player-api.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { confirmDialog, renderState, toast, withBusy } from '../../components/player-ui.js';
import { errorMessage, formatDate, formatClock, formatVnd, initials, safeUrl, statusLabel, statusStampClass } from '../../logic/format.js';
import { extractList, normalizeGame, normalizeRoom, openSlots, pick, readPagination } from '../../logic/normalize.js';

const PER_PAGE = 6;
const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
const state = {
  q: params.get('q') || '',
  code: params.get('code') || '',
  genre: params.get('genre') || 'all',
  difficulty: params.get('difficulty') || 'all',
  players: params.get('players') || '',
  playDate: params.get('play_date') || '',
  status: params.get('status') || 'all',
  page: Math.max(1, Number(params.get('page')) || 1),
  games: [],
  rooms: [],
  stats: null,
  pagination: null,
  loading: true,
  error: '',
};
let searchTimer = 0;

function syncUrl() {
  const next = new URLSearchParams();
  if (state.q) next.set('q', state.q);
  if (state.code) next.set('code', state.code);
  if (state.genre !== 'all') next.set('genre', state.genre);
  if (state.difficulty !== 'all') next.set('difficulty', state.difficulty);
  if (state.players) next.set('players', state.players);
  if (state.playDate) next.set('play_date', state.playDate);
  if (state.status !== 'all') next.set('status', state.status);
  if (state.page > 1) next.set('page', String(state.page));
  const query = next.toString();
  window.history.replaceState(null, '', query ? `?${query}` : window.location.pathname);
}

function hostOf(room) {
  const host = room.host;
  if (!host) return { name: '', avatar: '' };
  if (typeof host === 'string') return { name: host, avatar: '' };
  return {
    name: host.full_name || host.name || host.display_name || '',
    avatar: host.avatar_url || host.avatar || host.avatarUrl || '',
  };
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
  const cached = getCachedUser();
  if (!cached || !(cached.id || cached.full_name || cached.fullName || cached.email)) {
    toast('Bạn cần đăng nhập để tham gia phòng.', 'error');
    window.location.href = './profile.html';
    return;
  }
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
  const host = hostOf(room);
  const cover = safeUrl(room.coverUrl || pick(room.game?.raw || {}, ['cover_url', 'image_url', 'poster_url']) || '');
  const join = h('button', { class: 'btn btn-gold', type: 'button' }, 'Tham gia phòng');
  if (!canJoin(room)) join.disabled = true;
  join.addEventListener('click', () => {
    joinRoom(room, join).catch((err) => toast(errorMessage(err), 'error'));
  });
  const avatar = host.avatar && safeUrl(host.avatar)
    ? h('img', { class: 'room-avatar', alt: '', src: safeUrl(host.avatar) })
    : host.name ? h('span', { class: 'room-avatar-fallback' }, initials(host.name)) : null;
  return h('article', { class: 'room-card' }, [
    h('div', { class: 'player-actions' }, [
      h('span', { class: 'room-code' }, room.code),
      h('span', { class: statusStampClass(room.status) }, statusLabel(room.status || 'recruiting')),
    ]),
    cover ? h('img', { class: 'room-cover', alt: '', src: cover }) : null,
    h('h2', {}, room.name || room.game?.title || 'Phòng chơi'),
    h('p', { class: 'player-meta' }, room.game?.title || 'Chưa có kịch bản'),
    host.name ? h('p', { class: 'room-member' }, [avatar, h('span', {}, `Chủ phòng: ${host.name}`)]) : null,
    h('p', { class: 'player-meta' }, [
      formatDate(room.playDate),
      ' · ',
      formatClock(room.playTime),
    ]),
    h('p', { class: 'player-meta' }, [
      `Thành viên: ${members == null ? '—' : members}`,
      room.capacity != null ? ` / ${room.capacity}` : '',
      ' · ',
      `Còn trống: ${slots == null ? '—' : slots}`,
    ]),
    room.price != null ? h('p', { class: 'player-meta' }, `Giá tham khảo ${formatVnd(room.price)}`) : null,
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
      q: [state.q, state.code].filter(Boolean).join(' ').trim(),
      code: state.code,
      genre: state.genre,
      difficulty: state.difficulty,
      players: state.players,
      play_date: state.playDate,
      status: state.status,
      page: state.page,
      per_page: PER_PAGE,
    });
    const meta = payload?.meta || payload?.data?.meta || payload?.stats || {};
    state.stats = {
      total: Number(meta.total ?? meta.total_rooms),
      recruiting: Number(meta.recruiting_count ?? meta.open_count),
      upcoming: Number(meta.upcoming_count ?? meta.upcoming),
    };
    if (![state.stats.total, state.stats.recruiting, state.stats.upcoming].some(Number.isFinite)) {
      state.stats = null;
    }
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
    const statsHost = document.getElementById('lobby-stats');
    if (statsHost) {
      if (!state.stats) {
        statsHost.replaceChildren();
      } else {
        const chip = (label, value) => Number.isFinite(value)
          ? h('div', { class: 'player-stat' }, [h('strong', {}, String(value)), h('span', {}, label)])
          : null;
        statsHost.replaceChildren(
          chip('Tổng số phòng', state.stats.total),
          chip('Đang tuyển', state.stats.recruiting),
          chip('Sắp diễn ra', state.stats.upcoming),
        );
      }
    }
  }
}

function applyFilters(form) {
  const data = new FormData(form);
  state.q = String(data.get('q') || '').trim();
  state.code = String(data.get('code') || '').trim();
  state.genre = String(data.get('genre') || 'all');
  state.difficulty = String(data.get('difficulty') || 'all');
  state.players = String(data.get('players') || '').trim();
  state.playDate = String(data.get('play_date') || '');
  state.status = String(data.get('status') || 'all');
  state.page = 1;
  load();
}

function mount() {
  const genre = h('select', { class: 'player-select', id: 'room-genre', name: 'genre' }, [
    h('option', { value: 'all' }, 'Mọi thể loại'),
  ]);
  const difficulty = h('select', { class: 'player-select', name: 'difficulty' }, [
    ['all', 'Mọi độ khó'],
    ['easy', 'Dễ'],
    ['medium', 'Trung bình'],
    ['hard', 'Khó'],
  ].map(([value, label]) => h('option', { value, selected: value === state.difficulty }, label)));
  const status = h('select', { class: 'player-select', id: 'room-status', name: 'status' }, [
    ['all', 'Mọi trạng thái'],
    ['recruiting', 'Đang tuyển thành viên'],
    ['full', 'Đã đủ người'],
    ['scheduling', 'Đang xác nhận lịch'],
    ['confirmed', 'Đã xác nhận'],
    ['in_progress', 'Đang diễn ra'],
    ['completed', 'Đã hoàn thành'],
    ['closed', 'Đã đóng'],
  ].map(([value, label]) => h('option', { value, selected: value === state.status }, label)));
  const search = h('input', {
    class: 'player-input',
    type: 'search',
    name: 'q',
    value: state.q,
    placeholder: 'Tên phòng hoặc kịch bản',
    'aria-label': 'Tìm theo tên',
  });
  const code = h('input', {
    class: 'player-input',
    type: 'search',
    name: 'code',
    value: state.code,
    placeholder: 'Mã phòng',
    'aria-label': 'Tìm theo mã phòng',
  });
  const players = h('input', {
    class: 'player-input',
    type: 'number',
    name: 'players',
    min: '1',
    value: state.players,
    placeholder: 'Số người',
    'aria-label': 'Số người chơi',
  });
  const playDate = h('input', {
    class: 'player-input',
    type: 'date',
    name: 'play_date',
    value: state.playDate,
    'aria-label': 'Ngày chơi',
  });
  const form = h('form', { class: 'player-filters', id: 'room-filters' }, [
    search,
    code,
    genre,
    difficulty,
    players,
    playDate,
    status,
    h('button', { class: 'btn btn-gold', type: 'submit' }, 'Tìm kiếm'),
    h('button', { class: 'btn btn-outline', type: 'reset' }, 'Xóa bộ lọc'),
  ]);
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    applyFilters(form);
  });
  form.addEventListener('reset', (event) => {
    event.preventDefault();
    form.reset();
    state.q = '';
    state.code = '';
    state.genre = 'all';
    state.difficulty = 'all';
    state.players = '';
    state.playDate = '';
    state.status = 'all';
    state.page = 1;
    load();
  });
  [search, code].forEach((input) => {
    input.addEventListener('input', () => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => applyFilters(form), 400);
    });
  });

  root.replaceChildren(h('div', { class: 'container' }, [
    h('header', { class: 'player-hero' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, 'Sảnh phòng'),
        h('h1', { class: 'player-title' }, 'Khám phá những phòng đang mở'),
        h('p', { class: 'player-lead' }, 'Tìm đồng đội và tham gia những vụ án đang chờ được giải mã.'),
      ]),
      h('a', { class: 'btn btn-gold', href: './create-room.html' }, 'Tạo phòng mới'),
    ]),
    h('div', { class: 'player-stats', id: 'lobby-stats' }),
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
