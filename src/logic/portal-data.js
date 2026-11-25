import { ADMIN_SEED, DM_SEED } from '../data/mock-db.js';
import { SCENARIOS } from '../data/scripts.data.js';

const STORAGE_KEY = 'kbs_portal_db_v1';
const HOUR = 3600000;
const DAY = 24 * HOUR;

export const DEPOSIT_AMOUNT = DM_SEED.DEPOSIT_AMOUNT;
export const MIN_CHECKED_IN_TO_START = DM_SEED.MIN_CHECKED_IN_TO_START;
export const REFUND_MIN_HOURS = ADMIN_SEED.REFUND_MIN_HOURS;
export const PRICE_SLOTS = ADMIN_SEED.PRICE_SLOTS;
export const DIFFICULTIES = ADMIN_SEED.DIFFICULTIES;

export class PortalError extends Error {}
const fail = (message) => { throw new PortalError(message); };

export const fmt = {
  vnd: (n) => `${Math.round(Number(n) || 0).toLocaleString('vi-VN')} ₫`,
  dateTime: (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    const p = (x) => String(x).padStart(2, '0');
    return `${p(d.getHours())}:${p(d.getMinutes())} ${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()}`;
  },
  time: (iso) => {
    const d = new Date(iso);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  },
  dayKey: (iso) => {
    const d = new Date(iso);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  },
  dayLabel: (iso) => {
    const d = new Date(iso);
    const names = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    return `${names[d.getDay()]}, ${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`;
  },
};

export function uuid() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 3) | 8).toString(16);
  });
}

const iso = (ms) => new Date(ms).toISOString();

function cardPlaceholder(name) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="320" height="200"><rect width="320" height="200" rx="14" fill="#F5F2EB"/><rect x="0" y="0" width="320" height="46" fill="#992B2B"/><text x="16" y="30" font-family="Arial" font-size="16" fill="#F5F2EB" font-weight="bold">THẺ SINH VIÊN</text><circle cx="62" cy="108" r="34" fill="#cfc8b8"/><text x="116" y="96" font-family="Arial" font-size="15" fill="#0D0D0D" font-weight="bold">${name}</text><text x="116" y="122" font-family="Arial" font-size="12" fill="#555">MSSV: 2210${Math.abs(name.length * 731) % 9000 + 1000}</text></svg>`;
  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

function buildSeed() {
  const now = Date.now();
  const db = {
    version: 1,
    seq: {},
    users: [], games: [], game_configurations: [], game_prices: [],
    venues: [], physical_rooms: [], game_rooms: [], room_participants: [], player_payments: [],
    dm_assignments: [], sessions: [], refunds: [], idempotency_records: {},
  };
  const nextId = (table) => { db.seq[table] = (db.seq[table] || 0) + 1; return db.seq[table]; };

  db.users.push({ id: nextId('users'), full_name: 'Quản trị viên', email: 'admin@kichbansat.vn', phone: '0915690033', role: 'ADMIN', student_card_status: 'NONE' });
  DM_SEED.DM_NAMES.forEach((name, i) => db.users.push({
    id: nextId('users'), full_name: name, email: `dm${i + 1}@kichbansat.vn`, phone: `09000000${i + 1}0`, role: 'DM', student_card_status: 'NONE',
  }));
  ADMIN_SEED.PLAYER_NAMES.forEach((name, i) => {
    const status = i === 2 || i === 5 || i === 9 ? 'PENDING' : i === 1 ? 'VERIFIED' : 'NONE';
    db.users.push({
      id: nextId('users'), full_name: name, email: `player${i + 1}@mail.com`, phone: `09123400${String(i).padStart(2, '0')}`, role: 'PLAYER',
      student_card_status: status,
      student_card_url: status === 'NONE' ? null : cardPlaceholder(name),
      student_verified_at: status === 'VERIFIED' ? iso(now - 5 * DAY) : null,
      student_verified_by: status === 'VERIFIED' ? 1 : null,
      student_card_reject_reason: null,
    });
  });

  SCENARIOS.forEach((sc) => {
    const pl = sc.players || {};
    const total = Number(pl.total ?? sc.maxPlayers ?? sc.max_players ?? sc.playerCount) || 6;
    const male = pl.male ?? sc.male ?? 0;
    const female = pl.female ?? sc.female ?? 0;
    const price = Number(sc.price) || 0;
    const duration = Math.round(parseFloat(String(sc.duration).replace(',', '.')) * 60) || 180;
    db.games.push({
      id: sc.id, name: sc.title, genre: sc.genre, difficulty: sc.difficulty, summary: sc.summary || '', poster: sc.poster || '',
      max_players: total, male, female, is_18plus: !!sc.is18Plus, is_active: true,
    });
    db.game_configurations.push({ id: nextId('game_configurations'), game_id: sc.id, version: 1, duration_min: duration, max_players: total, notes: 'Cấu hình gốc', is_current: true, created_at: iso(now - 30 * DAY) });
    db.game_prices.push({ id: nextId('game_prices'), game_id: sc.id, slot: 'DAY', price_per_person: price });
    db.game_prices.push({ id: nextId('game_prices'), game_id: sc.id, slot: 'NIGHT', price_per_person: price + ADMIN_SEED.NIGHT_SURCHARGE });
  });

  const v = ADMIN_SEED.VENUE;
  db.venues.push({ id: nextId('venues'), name: v.name, address: v.address, phone: v.phone, open_time: v.open, close_time: v.close });
  ADMIN_SEED.PHYSICAL_ROOMS.forEach(([name, capacity]) => db.physical_rooms.push({
    id: nextId('physical_rooms'), venue_id: 1, name, capacity, is_active: true,
  }));

  const players = db.users.filter((u) => u.role === 'PLAYER');
  const G = (i) => db.games[i % db.games.length];

  const priceOf = (gameId, startMs) => {
    const hour = new Date(startMs).getHours();
    const slot = hour >= 9 && hour < 20 ? 'DAY' : 'NIGHT';
    return db.game_prices.find((p) => p.game_id === gameId && p.slot === slot).price_per_person;
  };
  const code = () => `KBS-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;

  const addRoom = ({ game, startMs, status, count, depositPaid = count, dm = null, dmStatus = null, room = null, session = true, hostIdx = 0 }) => {
    const g = game;
    const roomRow = { id: nextId('game_rooms'), game_id: g.id, host_id: players[hostIdx % players.length].id, scheduled_start_at: iso(startMs), capacity: 8, status, recruit_reopened: false, created_at: iso(startMs - 6 * DAY) };
    db.game_rooms.push(roomRow);
    const price = priceOf(g.id, startMs);
    for (let i = 0; i < count; i++) {
      const p = players[(hostIdx + i) % players.length];
      const part = { id: nextId('room_participants'), game_room_id: roomRow.id, user_id: p.id, ticket_code: code(), status: 'ACTIVE', final_price: price, checked_in_at: null };
      db.room_participants.push(part);
      db.player_payments.push({
        id: nextId('player_payments'), participant_id: part.id, type: 'DEPOSIT', amount: DEPOSIT_AMOUNT,
        status: i < depositPaid ? 'PAID' : 'PENDING', paid_at: i < depositPaid ? iso(startMs - 3 * DAY) : null, confirmed_by: null,
      });
    }
    if (dm) {
      db.dm_assignments.push({
        id: nextId('dm_assignments'), game_room_id: roomRow.id, dm_id: dm, physical_room_id: room, status: dmStatus,
        reject_reason: null, assigned_at: iso(now - 2 * DAY), responded_at: dmStatus === 'PENDING' ? null : iso(now - DAY),
      });
    }
    if (session && room) {
      db.sessions.push({
        id: nextId('sessions'), game_room_id: roomRow.id, physical_room_id: room, venue_id: 1, scheduled_start_at: iso(startMs),
        duration_min: db.game_configurations.find((c) => c.game_id === g.id && c.is_current).duration_min,
        started_at: null, completed_at: null, status: 'SCHEDULED',
      });
    }
    return roomRow;
  };

  const roundHour = (ms) => { const d = new Date(ms); d.setMinutes(0, 0, 0); return d.getTime(); };
  const inHours = (h) => roundHour(now + h * HOUR) + HOUR;

  addRoom({ game: G(0), startMs: inHours(2), status: 'CONFIRMED', count: 6, depositPaid: 5, dm: 2, dmStatus: 'ACCEPTED', room: 1, hostIdx: 0 });

  addRoom({ game: G(3), startMs: inHours(30), status: 'CONFIRMED', count: 7, dm: 2, dmStatus: 'PENDING', room: 2, hostIdx: 3 });

  addRoom({ game: G(4), startMs: inHours(52), status: 'FULL', count: 8, dm: 3, dmStatus: 'PENDING', room: 3, hostIdx: 6 });

  addRoom({ game: G(1), startMs: inHours(26), status: 'OPEN', count: 5, session: false, hostIdx: 9 });
  addRoom({ game: G(2), startMs: inHours(74), status: 'OPEN', count: 3, session: false, hostIdx: 11 });

  const nearRoom = addRoom({ game: G(5), startMs: inHours(10), status: 'FULL', count: 8, session: false, hostIdx: 4 });
  const cancelled = db.room_participants.filter((p) => p.game_room_id === nearRoom.id).slice(-1)[0];
  cancelled.status = 'CANCELLED';

  for (let back = 1; back <= 6; back++) {
    const start = roundHour(now - back * DAY) + 0;
    const g = G(back * 3);
    const roomRow = addRoom({ game: g, startMs: start, status: 'COMPLETED', count: 5 + (back % 4), dm: 2 + (back % 3), dmStatus: 'ACCEPTED', room: 1 + (back % 3), hostIdx: back });
    db.dm_assignments[db.dm_assignments.length - 1].responded_at = iso(start - 2 * DAY);
    const s = db.sessions.find((x) => x.game_room_id === roomRow.id);
    s.status = 'COMPLETED'; s.started_at = iso(start + 10 * 60000); s.completed_at = iso(start + 4 * HOUR);
    db.room_participants.filter((p) => p.game_room_id === roomRow.id).forEach((p) => {
      p.checked_in_at = iso(start + 5 * 60000);
      db.player_payments.push({
        id: nextId('player_payments'), participant_id: p.id, type: 'REMAINING', amount: p.final_price - DEPOSIT_AMOUNT,
        status: 'PAID', paid_at: iso(start + 6 * 60000), confirmed_by: 2 + (back % 3),
      });
    });
    db.player_payments.filter((pp) => pp.type === 'DEPOSIT' && db.room_participants.find((p) => p.id === pp.participant_id)?.game_room_id === roomRow.id)
      .forEach((pp) => { pp.status = 'PAID'; pp.paid_at = iso(start - 2 * DAY); });
  }

  const roomOpen = db.game_rooms.find((r) => r.status === 'OPEN');
  const partsOpen = db.room_participants.filter((p) => p.game_room_id === roomOpen.id);
  db.refunds.push({ id: nextId('refunds'), game_room_id: roomOpen.id, participant_id: partsOpen[0].id, user_id: partsOpen[0].user_id, amount: DEPOSIT_AMOUNT, requested_at: iso(now - 2 * HOUR), status: 'PENDING', reason: null, reviewed_by: null, reviewed_at: null });
  const nearParts = db.room_participants.filter((p) => p.game_room_id === nearRoom.id);
  db.refunds.push({ id: nextId('refunds'), game_room_id: nearRoom.id, participant_id: nearParts[0].id, user_id: nearParts[0].user_id, amount: DEPOSIT_AMOUNT, requested_at: iso(now - 30 * 60000), status: 'PENDING', reason: null, reviewed_by: null, reviewed_at: null });
  const doneRoom = db.game_rooms.find((r) => r.status === 'COMPLETED');
  const doneParts = db.room_participants.filter((p) => p.game_room_id === doneRoom.id);
  db.refunds.push({ id: nextId('refunds'), game_room_id: doneRoom.id, participant_id: doneParts[0].id, user_id: doneParts[0].user_id, amount: DEPOSIT_AMOUNT, requested_at: iso(now - 4 * DAY), status: 'APPROVED', reason: 'Hủy trước 24 giờ', reviewed_by: 1, reviewed_at: iso(now - 3 * DAY) });

  return db;
}

let db = null;
const listeners = new Set();

function storage() {
  try { return globalThis.localStorage || null; } catch { return null; }
}

function load() {
  const s = storage();
  try {
    const raw = s?.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed?.version === 1) return parsed;
    }
  } catch {  }
  return buildSeed();
}

function persist() {
  try { storage()?.setItem(STORAGE_KEY, JSON.stringify(db)); } catch {  }
  listeners.forEach((fn) => fn());
}

function ensure() {
  if (!db) { db = load(); persist(); }
  return db;
}

export function subscribe(fn) { listeners.add(fn); return () => listeners.delete(fn); }
export function resetDemo() { db = buildSeed(); persist(); }
export function state() { return ensure(); }

const nextId = (table) => { const d = ensure(); d.seq[table] = (d.seq[table] || 0) + 1; return d.seq[table]; };
const now = () => Date.now();

function once(key, name, fn) {
  const d = ensure();
  if (key && d.idempotency_records[key]) return d.idempotency_records[key].result;
  const result = fn();
  if (key) d.idempotency_records[key] = { name, result, created_at: iso(now()) };
  persist();
  return result;
}

export const find = {
  user: (id) => ensure().users.find((u) => u.id === Number(id)),
  game: (id) => ensure().games.find((g) => String(g.id) === String(id)),
  room: (id) => ensure().game_rooms.find((r) => r.id === Number(id)),
  physicalRoom: (id) => ensure().physical_rooms.find((r) => r.id === Number(id)),
  session: (id) => ensure().sessions.find((s) => s.id === Number(id)),
  sessionOfRoom: (roomId) => ensure().sessions.find((s) => s.game_room_id === Number(roomId)),
  participants: (roomId) => ensure().room_participants.filter((p) => p.game_room_id === Number(roomId)),
  activeParticipants: (roomId) => find.participants(roomId).filter((p) => p.status === 'ACTIVE'),
  payments: (participantId) => ensure().player_payments.filter((p) => p.participant_id === Number(participantId)),

  assignment: (roomId) => {
    const all = ensure().dm_assignments.filter((a) => a.game_room_id === Number(roomId));
    return all.filter((a) => a.status !== 'REJECTED').pop() || all.pop() || null;
  },
};

export const dmUsers = () => ensure().users.filter((u) => u.role === 'DM');
export const adminUser = () => ensure().users.find((u) => u.role === 'ADMIN');
export const currentConfig = (gameId) => ensure().game_configurations.find((c) => String(c.game_id) === String(gameId) && c.is_current);

function endMs(startIso, durationMin) { return new Date(startIso).getTime() + durationMin * 60000; }
function overlaps(aStart, aEnd, bStart, bEnd) { return aStart < bEnd && bStart < aEnd; }
function roomWindow(room) {
  const cfg = currentConfig(room.game_id);
  const start = new Date(room.scheduled_start_at).getTime();
  return [start, start + (cfg?.duration_min || 180) * 60000];
}

export function kpis() {
  const d = ensure();
  const paid = d.player_payments.filter((p) => p.status === 'PAID');
  const sum = (arr) => arr.reduce((s, p) => s + p.amount, 0);
  const monthStart = new Date(); monthStart.setDate(1); monthStart.setHours(0, 0, 0, 0);

  const live = d.game_rooms.filter((r) => ['OPEN', 'FULL', 'CONFIRMED'].includes(r.status));
  const seats = live.reduce((s, r) => s + r.capacity, 0);
  const filled = live.reduce((s, r) => s + find.activeParticipants(r.id).length, 0);

  const days = [];
  for (let back = 6; back >= 0; back--) {
    const day = new Date(); day.setHours(0, 0, 0, 0); day.setDate(day.getDate() - back);
    const key = fmt.dayKey(day.toISOString());
    days.push({
      label: `${String(day.getDate()).padStart(2, '0')}/${String(day.getMonth() + 1).padStart(2, '0')}`,
      amount: sum(paid.filter((p) => fmt.dayKey(p.paid_at) === key)),
    });
  }
  return {
    revenueTotal: sum(paid),
    revenueDeposit: sum(paid.filter((p) => p.type === 'DEPOSIT')),
    revenueRemaining: sum(paid.filter((p) => p.type === 'REMAINING')),
    revenueMonth: sum(paid.filter((p) => new Date(p.paid_at) >= monthStart)),
    openRooms: d.game_rooms.filter((r) => r.status === 'OPEN').length,
    fillRate: seats ? Math.round((filled / seats) * 100) : 0,
    pendingRefunds: d.refunds.filter((r) => r.status === 'PENDING').length,
    pendingCards: d.users.filter((u) => u.student_card_status === 'PENDING').length,
    roomsNeedingDm: live.filter((r) => { const a = find.assignment(r.id); return !a || a.status === 'REJECTED'; }).length,
    revenue7d: days,
  };
}

export function listRooms() {
  const d = ensure();
  return [...d.game_rooms]
    .filter((r) => r.status !== 'COMPLETED')
    .sort((a, b) => new Date(a.scheduled_start_at) - new Date(b.scheduled_start_at))
    .map((r) => {
      const a = find.assignment(r.id);
      const s = find.sessionOfRoom(r.id);
      const startMs = new Date(r.scheduled_start_at).getTime();
      const count = find.activeParticipants(r.id).length;
      return {
        room: r,
        game: find.game(r.game_id),
        count,
        assignment: a,
        dm: a ? find.user(a.dm_id) : null,
        physicalRoom: s ? find.physicalRoom(s.physical_room_id) : null,
        session: s,
        canReopen: count < r.capacity && startMs > now() && startMs - now() <= DAY && !r.recruit_reopened && r.status !== 'CANCELLED',
        needsDm: !a || a.status === 'REJECTED',
      };
    });
}

export function assignDm({ roomId, dmId, physicalRoomId, key }) {
  return once(key, 'assignDm', () => {
    const d = ensure();
    const room = find.room(roomId) || fail('Không tìm thấy phòng chơi.');
    if (['CANCELLED', 'COMPLETED', 'IN_PROGRESS'].includes(room.status)) fail('Phòng này không thể phân công lại.');
    const dm = find.user(dmId);
    if (!dm || dm.role !== 'DM') fail('Vui lòng chọn Quản trò hợp lệ.');
    const pr = find.physicalRoom(physicalRoomId);
    if (!pr || !pr.is_active) fail('Vui lòng chọn phòng vật lý đang hoạt động.');
    const headcount = find.activeParticipants(room.id).length;
    if (pr.capacity < headcount) fail(`${pr.name} chỉ chứa ${pr.capacity} người, phòng chơi đang có ${headcount} thành viên.`);

    const [start, end] = roomWindow(room);

    for (const a of d.dm_assignments) {
      if (a.dm_id !== dm.id || !['PENDING', 'ACCEPTED'].includes(a.status) || a.game_room_id === room.id) continue;
      const other = find.room(a.game_room_id);
      if (!other || ['CANCELLED', 'COMPLETED'].includes(other.status)) continue;
      const [os, oe] = roomWindow(other);
      if (overlaps(start, end, os, oe)) fail(`${dm.full_name} đã có ca trùng giờ (${fmt.dateTime(other.scheduled_start_at)}).`);
    }

    for (const s of d.sessions) {
      if (s.physical_room_id !== pr.id || s.game_room_id === room.id || ['COMPLETED'].includes(s.status)) continue;
      const other = find.room(s.game_room_id);
      if (!other || other.status === 'CANCELLED') continue;
      const [os, oe] = roomWindow(other);
      if (overlaps(start, end, os, oe)) fail(`${pr.name} đã có phiên khác trùng giờ (${fmt.dateTime(other.scheduled_start_at)}).`);
    }

    const current = find.assignment(room.id);
    if (current && current.status === 'PENDING') current.status = 'REJECTED', current.reject_reason = 'Admin phân công lại';
    const assignment = {
      id: nextId('dm_assignments'), game_room_id: room.id, dm_id: dm.id, physical_room_id: pr.id, status: 'PENDING',
      reject_reason: null, assigned_at: iso(now()), responded_at: null,
    };
    d.dm_assignments.push(assignment);

    let session = find.sessionOfRoom(room.id);
    if (!session) {
      session = { id: nextId('sessions'), game_room_id: room.id, physical_room_id: pr.id, venue_id: pr.venue_id, scheduled_start_at: room.scheduled_start_at, duration_min: currentConfig(room.game_id)?.duration_min || 180, started_at: null, completed_at: null, status: 'SCHEDULED' };
      d.sessions.push(session);
    } else {
      session.physical_room_id = pr.id;
      session.venue_id = pr.venue_id;
    }
    return { assignmentId: assignment.id, sessionId: session.id };
  });
}

export function reopenRecruitment({ roomId, key }) {
  return once(key, 'reopenRecruitment', () => {
    const room = find.room(roomId) || fail('Không tìm thấy phòng chơi.');
    const count = find.activeParticipants(room.id).length;
    const startMs = new Date(room.scheduled_start_at).getTime();
    if (room.recruit_reopened) fail('Phòng này đã mở tuyển bổ sung rồi.');
    if (count >= room.capacity) fail('Phòng vẫn đủ người, không cần tuyển bổ sung.');
    if (startMs <= now()) fail('Phòng đã quá giờ bắt đầu.');
    room.status = 'OPEN';
    room.recruit_reopened = true;
    return { roomId: room.id, missing: room.capacity - count };
  });
}

export function listRefunds() {
  return [...ensure().refunds].sort((a, b) => new Date(b.requested_at) - new Date(a.requested_at)).map((r) => {
    const room = find.room(r.game_room_id);
    const hoursBefore = (new Date(room.scheduled_start_at) - new Date(r.requested_at)) / HOUR;
    return {
      refund: r, room, game: find.game(room.game_id), user: find.user(r.user_id),
      hoursBefore, eligible: hoursBefore > REFUND_MIN_HOURS,
    };
  });
}

export function approveRefund({ refundId, key }) {
  return once(key, 'approveRefund', () => {
    const r = ensure().refunds.find((x) => x.id === Number(refundId)) || fail('Không tìm thấy yêu cầu hoàn cọc.');
    if (r.status !== 'PENDING') fail('Yêu cầu này đã được xử lý.');
    const room = find.room(r.game_room_id);
    const hoursBefore = (new Date(room.scheduled_start_at) - new Date(r.requested_at)) / HOUR;
    if (hoursBefore <= REFUND_MIN_HOURS) fail('Hủy chưa tới 24 giờ trước giờ chơi nên không đủ điều kiện hoàn cọc.');
    r.status = 'APPROVED';
    r.reason = 'Đủ điều kiện hoàn cọc (hủy trước 24 giờ)';
    r.reviewed_by = adminUser().id;
    r.reviewed_at = iso(now());
    const part = ensure().room_participants.find((p) => p.id === r.participant_id);
    if (part) part.status = 'CANCELLED';
    return { refundId: r.id, amount: r.amount };
  });
}

export function rejectRefund({ refundId, reason, key }) {
  return once(key, 'rejectRefund', () => {
    const r = ensure().refunds.find((x) => x.id === Number(refundId)) || fail('Không tìm thấy yêu cầu hoàn cọc.');
    if (r.status !== 'PENDING') fail('Yêu cầu này đã được xử lý.');
    const text = String(reason || '').trim();
    if (text.length < 5) fail('Vui lòng nhập lý do từ chối (ít nhất 5 ký tự).');
    r.status = 'REJECTED';
    r.reason = text;
    r.reviewed_by = adminUser().id;
    r.reviewed_at = iso(now());
    return { refundId: r.id };
  });
}

export function listGames() {
  return ensure().games.map((g) => ({
    game: g,
    config: currentConfig(g.id),
    prices: Object.fromEntries(ensure().game_prices.filter((p) => String(p.game_id) === String(g.id)).map((p) => [p.slot, p.price_per_person])),
  }));
}

function nextGameId(d) {
  const max = d.games.reduce((m, g) => Math.max(m, Number(/^kb-(\d+)$/.exec(g.id)?.[1] || 0)), 0);
  return `kb-${String(max + 1).padStart(2, '0')}`;
}

export function saveGame({ id, name, genre, difficulty, maxPlayers, male, female, is18Plus, durationMin, priceDay, priceNight, summary, key }) {
  return once(key, 'saveGame', () => {
    const d = ensure();
    const title = String(name || '').trim();
    if (title.length < 2) fail('Vui lòng nhập tên kịch bản.');
    if (!DIFFICULTIES.includes(difficulty)) fail('Vui lòng chọn độ khó hợp lệ.');
    const maxP = Number(maxPlayers), m = Number(male), f = Number(female), dur = Number(durationMin);
    const day = Number(priceDay), night = Number(priceNight);
    if (!(maxP >= 2 && maxP <= 8)) fail('Số người phải từ 2 đến 8.');
    if (!(m >= 0 && f >= 0) || m + f !== maxP) fail('Số Nam + Nữ phải bằng tổng số người chơi.');
    if (!(dur >= 30 && dur <= 480)) fail('Thời lượng phải từ 30 đến 480 phút.');
    if (!(day > 0 && night > 0)) fail('Giá vé phải lớn hơn 0.');
    if (d.games.some((g) => g.name.toLowerCase() === title.toLowerCase() && String(g.id) !== String(id))) fail('Tên kịch bản đã tồn tại.');

    let game = id ? find.game(id) : null;
    if (id && !game) fail('Không tìm thấy kịch bản.');
    if (!game) {
      game = { id: nextGameId(d), is_active: true, poster: '' };
      d.games.push(game);
    }
    Object.assign(game, {
      name: title, genre: String(genre || '').trim(), difficulty, summary: String(summary || '').trim(),
      max_players: maxP, male: m, female: f, is_18plus: is18Plus === true || is18Plus === 'on' || is18Plus === 'true',
    });

    const cfg = currentConfig(game.id);
    if (!cfg) {
      d.game_configurations.push({ id: nextId('game_configurations'), game_id: game.id, version: 1, duration_min: dur, max_players: maxP, notes: 'Cấu hình gốc', is_current: true, created_at: iso(now()) });
    } else if (cfg.duration_min !== dur || cfg.max_players !== maxP) {

      cfg.is_current = false;
      d.game_configurations.push({ id: nextId('game_configurations'), game_id: game.id, version: cfg.version + 1, duration_min: dur, max_players: maxP, notes: 'Cập nhật từ trang quản trị', is_current: true, created_at: iso(now()) });
    }
    for (const [slot, price] of [['DAY', day], ['NIGHT', night]]) {
      const row = d.game_prices.find((p) => String(p.game_id) === String(game.id) && p.slot === slot);
      if (row) row.price_per_person = price;
      else d.game_prices.push({ id: nextId('game_prices'), game_id: game.id, slot, price_per_person: price });
    }
    return { gameId: game.id };
  });
}

export function toggleGame({ gameId, key }) {
  return once(key, 'toggleGame', () => {
    const g = find.game(gameId) || fail('Không tìm thấy kịch bản.');
    g.is_active = !g.is_active;
    return { gameId: g.id, isActive: g.is_active };
  });
}

export const configHistory = (gameId) => ensure().game_configurations.filter((c) => String(c.game_id) === String(gameId)).sort((a, b) => b.version - a.version);

export const venue = () => ensure().venues[0];
export const physicalRooms = () => ensure().physical_rooms;

export function savePhysicalRoom({ id, name, capacity, key }) {
  return once(key, 'savePhysicalRoom', () => {
    const d = ensure();
    const title = String(name || '').trim();
    const cap = Number(capacity);
    if (title.length < 2) fail('Vui lòng nhập tên phòng.');
    if (!(cap >= 2 && cap <= 20)) fail('Sức chứa phải từ 2 đến 20 người.');
    if (d.physical_rooms.some((r) => r.name.toLowerCase() === title.toLowerCase() && r.id !== Number(id))) fail('Tên phòng đã tồn tại.');
    let room = id ? find.physicalRoom(id) : null;
    if (id && !room) fail('Không tìm thấy phòng.');
    if (room && cap < room.capacity) {
      const future = d.sessions.filter((s) => s.physical_room_id === room.id && s.status !== 'COMPLETED');
      const tooBig = future.find((s) => find.activeParticipants(s.game_room_id).length > cap);
      if (tooBig) fail('Có phiên sắp tới đang có nhiều người hơn sức chứa mới.');
    }
    if (!room) { room = { id: nextId('physical_rooms'), venue_id: d.venues[0].id, is_active: true }; d.physical_rooms.push(room); }
    room.name = title;
    room.capacity = cap;
    return { roomId: room.id };
  });
}

export function togglePhysicalRoom({ roomId, key }) {
  return once(key, 'togglePhysicalRoom', () => {
    const room = find.physicalRoom(roomId) || fail('Không tìm thấy phòng.');
    if (room.is_active) {
      const upcoming = ensure().sessions.some((s) => s.physical_room_id === room.id && s.status === 'SCHEDULED');
      if (upcoming) fail('Phòng đang có phiên sắp tới, hãy phân công sang phòng khác trước khi tắt.');
    }
    room.is_active = !room.is_active;
    return { roomId: room.id, isActive: room.is_active };
  });
}

export const studentCards = () => ensure().users.filter((u) => u.student_card_status && u.student_card_status !== 'NONE')
  .sort((a, b) => (a.student_card_status === 'PENDING' ? -1 : 1) - (b.student_card_status === 'PENDING' ? -1 : 1));

export function verifyStudentCard({ userId, key }) {
  return once(key, 'verifyStudentCard', () => {
    const u = find.user(userId) || fail('Không tìm thấy người dùng.');
    if (u.student_card_status !== 'PENDING') fail('Thẻ này không ở trạng thái chờ duyệt.');
    u.student_card_status = 'VERIFIED';
    u.student_verified_at = iso(now());
    u.student_verified_by = adminUser().id;
    u.student_card_reject_reason = null;
    return { userId: u.id };
  });
}

export function rejectStudentCard({ userId, reason, key }) {
  return once(key, 'rejectStudentCard', () => {
    const u = find.user(userId) || fail('Không tìm thấy người dùng.');
    if (u.student_card_status !== 'PENDING') fail('Thẻ này không ở trạng thái chờ duyệt.');
    const text = String(reason || '').trim();
    if (text.length < 5) fail('Vui lòng nhập lý do từ chối (ít nhất 5 ký tự).');
    u.student_card_status = 'REJECTED';
    u.student_card_reject_reason = text;
    return { userId: u.id };
  });
}

export function assignmentsOf(dmId) {
  return ensure().dm_assignments.filter((a) => a.dm_id === Number(dmId)).map((a) => {
    const room = find.room(a.game_room_id);
    return { assignment: a, room, game: find.game(room.game_id), physicalRoom: find.physicalRoom(a.physical_room_id), count: find.activeParticipants(room.id).length };
  }).sort((a, b) => new Date(a.room.scheduled_start_at) - new Date(b.room.scheduled_start_at));
}

export function respondAssignment({ assignmentId, dmId, accept, reason, key }) {
  return once(key, 'respondAssignment', () => {
    const a = ensure().dm_assignments.find((x) => x.id === Number(assignmentId)) || fail('Không tìm thấy ca dẫn.');
    if (a.dm_id !== Number(dmId)) fail('Ca dẫn này không thuộc về bạn.');
    if (a.status !== 'PENDING') fail('Ca dẫn này đã được phản hồi.');
    if (accept) {
      a.status = 'ACCEPTED';
    } else {
      const text = String(reason || '').trim();
      if (text.length < 5) fail('Vui lòng nhập lý do từ chối (ít nhất 5 ký tự).');
      a.status = 'REJECTED';
      a.reject_reason = text;
    }
    a.responded_at = iso(now());
    return { assignmentId: a.id, status: a.status };
  });
}

export function sessionsOf(dmId) {
  return ensure().dm_assignments.filter((a) => a.dm_id === Number(dmId) && a.status === 'ACCEPTED').map((a) => {
    const room = find.room(a.game_room_id);
    const session = find.sessionOfRoom(room.id);
    return session ? { assignment: a, room, session, game: find.game(room.game_id), physicalRoom: find.physicalRoom(session.physical_room_id), count: find.activeParticipants(room.id).length } : null;
  }).filter(Boolean).sort((a, b) => new Date(a.session.scheduled_start_at) - new Date(b.session.scheduled_start_at));
}

export function sessionDetail(sessionId) {
  const session = find.session(sessionId) || fail('Không tìm thấy phiên chơi.');
  const room = find.room(session.game_room_id);
  const members = find.activeParticipants(room.id).map((p) => {
    const payments = find.payments(p.id);
    const deposit = payments.find((x) => x.type === 'DEPOSIT');
    const remaining = payments.find((x) => x.type === 'REMAINING');
    return {
      participant: p, user: find.user(p.user_id), deposit, remaining,
      remainingAmount: Math.max(0, p.final_price - DEPOSIT_AMOUNT),
      remainingDue: !remaining || remaining.status !== 'PAID',
    };
  });
  return {
    session, room, game: find.game(room.game_id), physicalRoom: find.physicalRoom(session.physical_room_id), members,
    checkedIn: members.filter((m) => m.participant.checked_in_at).length,
  };
}

function ownedSession(sessionId, dmId) {
  const session = find.session(sessionId) || fail('Không tìm thấy phiên chơi.');
  const a = find.assignment(session.game_room_id);
  if (!a || a.dm_id !== Number(dmId) || a.status !== 'ACCEPTED') fail('Phiên chơi này không thuộc ca dẫn của bạn.');
  return session;
}

export function checkIn({ sessionId, dmId, ticketCode, key }) {
  return once(key, 'checkIn', () => {
    const session = ownedSession(sessionId, dmId);
    if (session.status === 'COMPLETED') fail('Phiên chơi đã hoàn tất.');
    const codeText = String(ticketCode || '').trim().toUpperCase();
    if (!codeText) fail('Vui lòng nhập hoặc quét mã vé.');
    const part = find.activeParticipants(session.game_room_id).find((p) => p.ticket_code === codeText);
    if (!part) fail('Mã vé không thuộc phiên chơi này hoặc vé đã hủy.');
    const deposit = find.payments(part.id).find((p) => p.type === 'DEPOSIT');
    if (!deposit || deposit.status !== 'PAID') fail('Người chơi chưa thanh toán cọc 50.000đ. Hãy xác nhận thu cọc trước.');
    if (part.checked_in_at) fail('Vé này đã check-in lúc ' + fmt.time(part.checked_in_at) + '.');
    part.checked_in_at = iso(now());
    return { participantId: part.id, name: find.user(part.user_id).full_name };
  });
}

export function confirmPayment({ sessionId, dmId, participantId, type, key }) {
  return once(key, 'confirmPayment', () => {
    const session = ownedSession(sessionId, dmId);
    if (session.status === 'COMPLETED') fail('Phiên chơi đã hoàn tất.');
    const part = find.activeParticipants(session.game_room_id).find((p) => p.id === Number(participantId)) || fail('Không tìm thấy thành viên.');
    if (!['DEPOSIT', 'REMAINING'].includes(type)) fail('Loại thanh toán không hợp lệ.');
    const payments = find.payments(part.id);
    if (type === 'DEPOSIT') {
      const dep = payments.find((p) => p.type === 'DEPOSIT') || fail('Không có khoản cọc cho thành viên này.');
      if (dep.status === 'PAID') fail('Khoản cọc đã được xác nhận.');
      dep.status = 'PAID'; dep.paid_at = iso(now()); dep.confirmed_by = Number(dmId);
      return { type, amount: dep.amount };
    }
    const dep = payments.find((p) => p.type === 'DEPOSIT');
    if (!dep || dep.status !== 'PAID') fail('Cần xác nhận cọc 50.000đ trước khi thu khoản còn lại.');
    if (!part.checked_in_at) fail('Hãy check-in người chơi trước khi thu tiền còn lại.');
    if (payments.some((p) => p.type === 'REMAINING' && p.status === 'PAID')) fail('Khoản còn lại đã được thu.');
    const amount = Math.max(0, part.final_price - DEPOSIT_AMOUNT);
    ensure().player_payments.push({
      id: nextId('player_payments'), participant_id: part.id, type: 'REMAINING', amount, status: 'PAID', paid_at: iso(now()), confirmed_by: Number(dmId),
    });
    return { type, amount };
  });
}

export function startSession({ sessionId, dmId, key }) {
  return once(key, 'startSession', () => {
    const session = ownedSession(sessionId, dmId);
    if (session.status !== 'SCHEDULED') fail('Chỉ bắt đầu được phiên đang ở trạng thái đã lên lịch.');
    const checked = find.activeParticipants(session.game_room_id).filter((p) => p.checked_in_at).length;
    if (checked < MIN_CHECKED_IN_TO_START) fail(`Cần tối thiểu ${MIN_CHECKED_IN_TO_START} người check-in để bắt đầu (hiện có ${checked}).`);
    session.status = 'IN_PROGRESS';
    session.started_at = iso(now());
    find.room(session.game_room_id).status = 'IN_PROGRESS';
    return { sessionId: session.id, startedAt: session.started_at };
  });
}

export function completeSession({ sessionId, dmId, key }) {
  return once(key, 'completeSession', () => {
    const session = ownedSession(sessionId, dmId);
    if (session.status !== 'IN_PROGRESS') fail('Chỉ hoàn tất được phiên đang diễn ra.');
    const unpaid = find.activeParticipants(session.game_room_id).filter((p) => {
      if (!p.checked_in_at) return false;
      return !find.payments(p.id).some((x) => x.type === 'REMAINING' && x.status === 'PAID') && p.final_price > DEPOSIT_AMOUNT;
    });
    if (unpaid.length) fail(`Còn ${unpaid.length} người chưa thu khoản còn lại. Hãy thu đủ trước khi hoàn tất.`);
    session.status = 'COMPLETED';
    session.completed_at = iso(now());
    find.room(session.game_room_id).status = 'COMPLETED';
    return { sessionId: session.id, completedAt: session.completed_at };
  });
}
