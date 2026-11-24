function hasValue(value) {
  return value !== undefined && value !== null && value !== '';
}

export function unwrap(payload) {
  if (payload && typeof payload === 'object' && Object.prototype.hasOwnProperty.call(payload, 'data')) {
    return payload.data;
  }
  return payload;
}

export function pick(source, keys) {
  if (!source || typeof source !== 'object') return undefined;
  for (const key of keys) {
    const value = String(key).split('.').reduce((acc, part) => (acc == null ? acc : acc[part]), source);
    if (hasValue(value)) return value;
  }
  return undefined;
}

function asNumber(value) {
  if (typeof value === 'boolean' || value == null || value === '') return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function asBool(value) {
  if (value === true || value === 1 || value === '1' || value === 'true') return true;
  return false;
}

export function extractList(payload, keys) {
  const data = unwrap(payload);
  if (Array.isArray(data)) return { items: data, pagination: null };
  if (!data || typeof data !== 'object') return { items: [], pagination: null };
  for (const key of keys) {
    if (Array.isArray(data[key])) {
      return { items: data[key], pagination: data.pagination || data.meta || null };
    }
  }
  return { items: [], pagination: null };
}

export function readPagination(meta, fallbackPage) {
  if (!meta || typeof meta !== 'object') return null;
  const pages = asNumber(meta.total_pages ?? meta.last_page ?? meta.pages);
  if (pages == null || pages < 1) return null;
  const page = asNumber(meta.page ?? meta.current_page) ?? fallbackPage;
  const total = asNumber(meta.total ?? meta.total_items);
  return { page, pages, total };
}

export function normalizeGame(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const id = pick(raw, ['id', 'game_id', 'scenario_id']);
  const title = pick(raw, ['title', 'name']);
  if (!hasValue(id) && !hasValue(title)) return null;
  const players = raw.players && typeof raw.players === 'object' ? raw.players : null;
  return {
    id: hasValue(id) ? String(id) : '',
    title: String(title || 'Kịch bản'),
    genre: String(pick(raw, ['genre', 'category']) || ''),
    difficulty: String(pick(raw, ['difficulty']) || ''),
    duration: String(pick(raw, ['duration', 'duration_label']) || ''),
    summary: String(pick(raw, ['summary', 'description']) || ''),
    price: asNumber(pick(raw, ['price', 'base_price', 'ticket_price', 'price_day', 'price_day_vnd'])),
    priceDay: asNumber(pick(raw, ['price_day', 'price_day_vnd', 'price', 'base_price'])),
    priceNight: asNumber(pick(raw, ['price_night', 'price_night_vnd'])),
    is18Plus: asBool(pick(raw, ['is18Plus', 'is_18_plus', 'adult_only', 'age_restriction'])),
    playerTotal: asNumber(players?.total ?? raw.player_count ?? raw.capacity),
    minPlayers: asNumber(pick(raw, ['min_players', 'minPlayers']) ?? players?.total),
    maxPlayers: asNumber(pick(raw, ['max_players', 'maxPlayers']) ?? raw.capacity),
    durationMinutes: asNumber(pick(raw, ['duration_minutes', 'duration'])),
    male: asNumber(players?.male),
    female: asNumber(players?.female),
  };
}

export function memberCount(room) {
  const explicit = asNumber(room?.member_count ?? room?.occupied_slots ?? room?.participants_count);
  if (explicit != null) return explicit;
  if (Array.isArray(room?.members)) return room.members.length;
  if (Array.isArray(room?.participants)) return room.participants.length;
  return null;
}

export function roomCapacity(room) {
  return asNumber(room?.capacity ?? room?.max_players ?? room?.slot_count ?? room?.game?.players?.total ?? room?.game?.player_count);
}

export function openSlots(room) {
  const explicit = asNumber(room?.open_slots ?? room?.slots_available ?? room?.free_slots);
  if (explicit != null) return explicit;
  const capacity = roomCapacity(room);
  const members = memberCount(room);
  if (capacity == null || members == null) return null;
  return Math.max(0, capacity - members);
}

export function normalizeMember(raw) {
  if (!raw || typeof raw !== 'object') return null;
  return {
    id: String(pick(raw, ['id', 'user_id']) || ''),
    name: String(pick(raw, ['display_name', 'full_name', 'name']) || 'Người chơi'),
    avatarUrl: String(pick(raw, ['avatar_url', 'avatar']) || ''),
    status: String(pick(raw, ['status', 'participation_status']) || ''),
    role: String(pick(raw, ['role']) || 'player'),
  };
}

export function normalizeRoom(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const source = raw.room && typeof raw.room === 'object' ? { ...raw.room, ...raw } : raw;
  const id = pick(source, ['id', 'room_id']);
  const code = pick(source, ['code', 'room_code', 'public_code']);
  if (!hasValue(id) && !hasValue(code)) return null;
  const game = normalizeGame(source.game || source.scenario || {
    id: source.game_id,
    title: source.game_title || source.scenario_name || source.title,
    genre: source.genre,
    summary: source.summary,
    price: source.price,
    is_18_plus: source.is_18_plus,
  });
  const members = (Array.isArray(source.members) ? source.members : source.participants || [])
    .map(normalizeMember)
    .filter(Boolean);
  return {
    id: hasValue(id) ? String(id) : String(code),
    code: hasValue(code) ? String(code) : String(id),
    status: String(pick(source, ['status', 'room_status']) || ''),
    recruitmentOpen: source.recruitment_open !== false && source.recruitment_status !== 'closed',
    recruitmentStatus: String(pick(source, ['recruitment_status']) || ''),
    genre: String(pick(source, ['genre', 'game.genre']) || game?.genre || ''),
    notes: String(pick(source, ['notes', 'note']) || ''),
    playDate: String(pick(source, ['play_date', 'session.play_date', 'schedule.play_date']) || ''),
    playTime: String(pick(source, ['play_time', 'session.play_time', 'schedule.play_time']) || ''),
    memberCount: memberCount(source),
    capacity: roomCapacity(source),
    openSlots: openSlots(source),
    game,
    members,
    dm: source.dm || source.dungeon_master || null,
    session: source.session || null,
    venue: source.venue || source.physical_room || null,
    proposals: source.schedule_proposals || source.proposals || [],
    payment: source.payment || null,
    leaveRequest: source.leave_request || null,
    refund: source.refund || null,
    ticketCode: String(pick(source, ['ticket_code', 'booking_code', 'booking.ticket_code']) || ''),
    raw: source,
  };
}

export function normalizeProfile(raw) {
  const source = unwrap(raw) || {};
  const verification = source.student_verification || source.hssv || {};
  return {
    fullName: String(pick(source, ['full_name', 'name']) || ''),
    phone: String(pick(source, ['phone', 'phone_number']) || ''),
    email: String(pick(source, ['email']) || ''),
    hssvStatus: String(pick(verification, ['status']) || pick(source, ['hssv_status', 'student_status']) || 'missing'),
    hssvNote: String(pick(verification, ['note', 'rejected_reason']) || source.hssv_note || ''),
    hssvPreview: String(pick(verification, ['image_url', 'card_url']) || ''),
    hssvApproved: ['approved', 'verified'].includes(String(
      pick(verification, ['status']) || source.hssv_status || source.student_status || ''
    ).toLowerCase()),
  };
}

export function normalizeBooking(raw) {
  const room = normalizeRoom(raw?.room || raw);
  return {
    id: String(pick(raw, ['id', 'booking_id']) || room?.id || ''),
    roomId: String(pick(raw, ['room_id', 'room.id']) || room?.id || ''),
    roomCode: String(pick(raw, ['room_code', 'code', 'room.code']) || room?.code || ''),
    ticketCode: String(pick(raw, ['ticket_code', 'booking_code']) || room?.ticketCode || ''),
    title: String(pick(raw, ['game_title', 'scenario_name', 'title', 'game.title']) || room?.game?.title || 'Kịch bản'),
    playDate: String(pick(raw, ['play_date', 'session.play_date']) || room?.playDate || ''),
    playTime: String(pick(raw, ['play_time', 'session.play_time']) || room?.playTime || ''),
    status: String(pick(raw, ['status', 'participation_status']) || room?.status || ''),
    room,
    raw,
  };
}

export function normalizeQuote(raw) {
  const source = unwrap(raw) || {};
  const base = asNumber(pick(source, ['base', 'base_price', 'original_price']));
  const discount = asNumber(pick(source, ['discount', 'discount_amount']));
  const total = asNumber(pick(source, ['total', 'payable', 'final_price']));
  const deposit = asNumber(pick(source, ['deposit', 'deposit_amount']));
  const remaining = asNumber(pick(source, ['remaining', 'remaining_amount']));
  if ([base, total, deposit, remaining].some((value) => value == null)) return null;
  return {
    base,
    discount: discount ?? 0,
    discountLabel: String(pick(source, ['discount_label', 'offer_label']) || 'Ưu đãi'),
    total,
    deposit,
    remaining,
    source: 'server',
  };
}
