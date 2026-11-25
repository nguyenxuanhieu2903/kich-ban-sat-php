import { apiClient, ApiError } from './api-client.js';
import { config } from '../utils/config.js';

/**
 * Player Portal API.
 * Base URL lấy từ api-client hiện có (mặc định http://127.0.0.1:8080/api).
 * Envelope: { success, data } / { success:false, error:{ code, message, details } }.
 *
 * GET  /games
 * GET  /rooms?q&genre&status&page&per_page
 * GET  /rooms/:id
 * POST /rooms
 * POST /rooms/quote
 * POST /rooms/:id/join
 * POST /rooms/:id/leave-requests
 * POST /rooms/:id/schedule-proposals
 * POST /schedule-proposals/:id/votes
 * GET  /rooms/:id/booking
 * GET  /bookings/:ticket
 * GET  /me
 * PATCH /me
 * POST /me/password
 * POST /me/student-card   (multipart, field student_card)
 * GET  /me/bookings?tab=upcoming|awaiting_deposit|completed
 */

function createIdempotencyKey() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  return `kbs-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function commandOptions() {
  return {
    credentials: 'include',
    headers: { 'Idempotency-Key': createIdempotencyKey() },
  };
}

const readOptions = { credentials: 'include' };

export function queryString(params) {
  const search = new URLSearchParams();
  Object.entries(params || {}).forEach(([key, value]) => {
    if (value == null || value === '' || value === 'all') return;
    search.set(key, String(value));
  });
  const text = search.toString();
  return text ? `?${text}` : '';
}

async function readData(payload) {
  if (payload && typeof payload === 'object' && payload.success === false) {
    const info = payload.error || {};
    throw new ApiError(info.message || 'Yêu cầu thất bại', 400, info.code || 'API_ERROR', info.details || null);
  }
  if (payload && typeof payload === 'object' && Object.prototype.hasOwnProperty.call(payload, 'data')) {
    return payload.data;
  }
  return payload;
}

async function send(promise) {
  return readData(await promise);
}

export const playerApi = {
  listGames() {
    return apiClient.get('/games', readOptions).catch((error) => {
      if (error instanceof ApiError && error.status === 404) {
        return apiClient.get('/scripts', readOptions);
      }
      throw error;
    });
  },
  login(body) {
    return send(apiClient.post('/auth/login', body, commandOptions())).then((data) => {
      rememberUser(normalizeProfile(data));
      return data;
    });
  },
  logout() {
    return send(apiClient.post('/auth/logout', {}, commandOptions()));
  },
  listRooms(params) {
    return apiClient.get(`/rooms${queryString(params)}`, readOptions);
  },
  getRoom(id) {
    return send(apiClient.get(`/rooms/${encodeURIComponent(id)}`, readOptions));
  },
  createRoom(body) {
    return send(apiClient.post('/rooms', body, commandOptions()));
  },
  quoteRoom(body) {
    return send(apiClient.post('/rooms/quote', body, { credentials: 'include' }));
  },
  joinRoom(id) {
    return send(apiClient.post(`/rooms/${encodeURIComponent(id)}/join`, {}, commandOptions()));
  },
  leaveRoom(id, body) {
    return send(apiClient.post(`/rooms/${encodeURIComponent(id)}/leave-requests`, body, commandOptions()));
  },
  proposeSchedule(id, body) {
    return send(apiClient.post(`/rooms/${encodeURIComponent(id)}/schedule-proposals`, body, commandOptions()));
  },
  voteSchedule(proposalId, body) {
    return send(apiClient.post(`/schedule-proposals/${encodeURIComponent(proposalId)}/votes`, body, commandOptions()));
  },
  getBookingByRoom(id) {
    return send(apiClient.get(`/rooms/${encodeURIComponent(id)}/booking`, readOptions));
  },
  getBooking(ticket) {
    return send(apiClient.get(`/bookings/${encodeURIComponent(ticket)}`, readOptions));
  },
  getMe() {
    return send(apiClient.get('/me', readOptions)).then((data) => {
      rememberUser(normalizeProfile(data));
      return data;
    });
  },
  updateMe(body) {
    return send(apiClient.request('/me', {
      ...readOptions,
      method: 'PATCH',
      body: JSON.stringify(body),
    }));
  },
  changePassword(body) {
    return send(apiClient.post('/me/password', body, commandOptions()));
  },
  listMyBookings(tab) {
    return apiClient.get(`/me/bookings${queryString({ tab })}`, readOptions);
  },
  async uploadStudentCard(file) {
    const url = `${config.apiBaseUrl.replace(/\/+$/, '')}/me/student-card`;
    const body = new FormData();
    body.append('student_card', file);
    let response;
    try {
      response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Idempotency-Key': createIdempotencyKey(),
        },
      });
    } catch (err) {
      throw new ApiError(
        'Không kết nối được máy chủ. Hãy kiểm tra API đang chạy tại cổng 8080.',
        0,
        'NETWORK_FAILURE',
        { originalError: err.message }
      );
    }
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : await response.text();
    if (!response.ok) {
      const info = typeof payload === 'object' ? payload.error : null;
      throw new ApiError(info?.message || `HTTP ${response.status}`, response.status, info?.code || `HTTP_${response.status}`, info?.details || null);
    }
    return readData(payload);
  },
  getRoomPayments(id) {
    return send(apiClient.get(`/rooms/${encodeURIComponent(id)}/payments`, readOptions)).catch((error) => {
      if (error instanceof ApiError && (error.status === 404 || error.status === 405)) {
        return send(apiClient.get(`/payments/room/${encodeURIComponent(id)}`, readOptions));
      }
      throw error;
    });
  },
  payDeposit(id) {
    return send(apiClient.post(`/rooms/${encodeURIComponent(id)}/payments/deposit`, { provider: 'VNPAY' }, commandOptions()));
  },
  payRemaining(id) {
    return send(apiClient.post(`/rooms/${encodeURIComponent(id)}/payments/remaining`, { provider: 'VNPAY' }, commandOptions()));
  },
  getPayment(id) {
    return send(apiClient.get(`/payments/${encodeURIComponent(id)}`, readOptions)).catch((error) => {
      if (error instanceof ApiError && (error.status === 404 || error.status === 405)) {
        return send(apiClient.get(`/rooms/${encodeURIComponent(id)}/payment`, readOptions));
      }
      throw error;
    });
  },
};

const SESSION_USER_KEY = 'kbs_player_user';

function listFrom(payload) {
  if (Array.isArray(payload)) return payload;
  if (payload && typeof payload === 'object') {
    if (Array.isArray(payload.data)) return payload.data;
    if (Array.isArray(payload.items)) return payload.items;
    if (Array.isArray(payload.rooms)) return payload.rooms;
    if (Array.isArray(payload.games)) return payload.games;
    if (Array.isArray(payload.bookings)) return payload.bookings;
    if (payload.data && Array.isArray(payload.data.items)) return payload.data.items;
  }
  return [];
}

function metaFrom(payload, total) {
  const meta = payload?.meta || payload?.pagination || payload?.data?.meta || {};
  return {
    total: Number(meta.total ?? total ?? 0),
    page: Number(meta.page ?? meta.current_page ?? 1),
    limit: Number(meta.limit ?? meta.per_page ?? 8),
    total_pages: Number(meta.total_pages ?? meta.last_page ?? 1),
  };
}

export function normalizeGame(item = {}) {
  const id = item.id ?? item.game_id ?? item.code;
  const day = Number(item.price_day_vnd ?? item.price_day ?? item.price_per_player ?? item.price ?? item.priceDay ?? 0);
  return {
    id,
    backend_id: item.id ?? id,
    code: item.code || '',
    title: item.title || item.name || 'Kịch bản',
    genre: item.genre || item.category || 'Trinh thám',
    description: item.description || item.summary || '',
    min_players: Number(item.min_players ?? item.minPlayers ?? item.playerTotal ?? 4),
    max_players: Number(item.max_players ?? item.maxPlayers ?? item.capacity ?? 8),
    duration_minutes: Number(item.duration_minutes ?? item.durationMinutes ?? item.duration ?? 180),
    difficulty: item.difficulty || '',
    price_day: day,
    price_night: Number(item.price_night_vnd ?? item.price_night ?? item.priceNight ?? (day ? day + 30000 : 0)),
    age_restriction: Boolean(item.age_restriction || item.is_age_restricted || item.is18Plus),
    raw: item,
  };
}

function mapParticipant(item = {}) {
  return {
    id: item.id ?? item.user_id,
    user_id: String(item.user_id ?? item.userId ?? item.id ?? ''),
    name: item.full_name || item.name || item.display_name || 'Người chơi',
    avatar_url: item.avatar_url || item.avatar || item.avatarUrl || '',
    status: item.status || 'JOINED',
    ticket_code: item.ticket_code || '',
    joined_at: item.joined_at || '',
    deposit_deadline: item.deposit_deadline || item.deposit_deadline_at || '',
    payment_status: item.payment_status || '',
  };
}

export function normalizeRoom(item = {}) {
  const nested = item.game || item.game_details || item.script || {};
  const game = Object.keys(nested).length ? normalizeGame({ ...nested, id: nested.id ?? item.game_id }) : null;
  const participants = Array.isArray(item.participants)
    ? item.participants.map(mapParticipant)
    : (Array.isArray(item.members) ? item.members.map(mapParticipant) : []);
  const maxSlots = Number(item.max_players ?? item.capacity ?? game?.max_players ?? 0);
  const memberCount = Number(item.member_count ?? item.participants_count ?? participants.length);
  return {
    id: item.id ?? item.room_id,
    backend_id: item.id,
    code: item.code || item.room_code || '',
    game_id: item.game_id ?? game?.id,
    game,
    status: item.status || '',
    notes: item.notes || item.note || '',
    session_start: item.scheduled_start_at || item.session_start_time || item.start_time || [item.play_date, item.play_time].filter(Boolean).join('T'),
    formation_deadline: item.formation_deadline_at || item.formation_deadline || '',
    member_count: memberCount,
    max_slots: maxSlots,
    min_slots: Number(item.min_players ?? game?.min_players ?? 0),
    empty_slots: Number(item.empty_slots ?? item.open_slots ?? Math.max(0, maxSlots - memberCount)),
    recruiting: Boolean(item.recruitment_open !== false),
    participants,
    creator_id: String(item.host_user_id ?? item.creator_id ?? ''),
    dm: item.dm || item.dm_assignment || null,
    physical_room: item.physical_room || item.venue || null,
    session: item.session || null,
    schedule_proposals: item.schedule_proposals || item.proposals || (item.schedule_proposal ? [item.schedule_proposal] : []),
    payments: item.payments || item.transactions || [],
    refunds: item.refunds || (item.refund ? [item.refund] : []),
    raw: item,
  };
}

export function normalizeProfile(data = {}) {
  const source = data.user || data.profile || data;
  const card = source.student_card || source.hssv || source.student_verification || {};
  const statusRaw = String(card.status || source.hssv_status || '').toLowerCase();
  let cardStatus = 'none';
  if (['approved', 'verified', 'accepted', 'true'].includes(statusRaw) || source.student_verified === true) cardStatus = 'verified';
  else if (['pending', 'waiting', 'submitted', 'cho_duyet'].includes(statusRaw)) cardStatus = 'pending';
  else if (['rejected', 'denied', 'tu_choi'].includes(statusRaw)) cardStatus = 'rejected';
  return {
    id: source.id ?? null,
    full_name: source.full_name || source.fullName || source.name || '',
    phone: source.phone || source.phone_number || '',
    email: source.email || '',
    role: source.role || 'PLAYER',
    active_rooms_count: Number(source.active_rooms_count ?? data.active_rooms_count ?? 0),
    student_verified: cardStatus === 'verified',
    student_card: {
      status: cardStatus,
      file_name: card.file_name || card.filename || '',
      reject_reason: card.reject_reason || card.note || source.hssv_note || '',
    },
    csrf: data.csrf_token || source.csrf_token || '',
  };
}

function rememberUser(profile) {
  try {
    sessionStorage.setItem(SESSION_USER_KEY, JSON.stringify(profile));
    if (profile.csrf) sessionStorage.setItem('kbs_csrf', profile.csrf);
  } catch {
    /* ignore */
  }
}

export function getCachedUser() {
  try {
    const raw = sessionStorage.getItem(SESSION_USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function clearCachedUser() {
  sessionStorage.removeItem(SESSION_USER_KEY);
  sessionStorage.removeItem('kbs_csrf');
}

export async function getMe() {
  const profile = normalizeProfile(await playerApi.getMe());
  rememberUser(profile);
  return profile;
}

export async function login(credentials) {
  const profile = normalizeProfile(await playerApi.login(credentials));
  rememberUser(profile);
  try {
    return await getMe();
  } catch {
    return profile;
  }
}

export async function logout() {
  try {
    await playerApi.logout();
  } finally {
    clearCachedUser();
  }
}

export async function updateProfile(body) {
  const profile = normalizeProfile(await playerApi.updateMe(body));
  rememberUser({ ...getCachedUser(), ...profile });
  return profile;
}

export async function uploadStudentCard(file) {
  return normalizeProfile(await playerApi.uploadStudentCard(file));
}

export async function listGames(filters) {
  const payload = await playerApi.listGames(filters);
  const items = listFrom(payload).map(normalizeGame);
  return { items, meta: metaFrom(payload, items.length) };
}

export async function getGame(id) {
  const payload = await apiClient.get(`/games/${encodeURIComponent(id)}`, readOptions).catch((error) => {
    if (error instanceof ApiError && error.status === 404) {
      return apiClient.get(`/scripts/${encodeURIComponent(id)}`, readOptions);
    }
    throw error;
  });
  return normalizeGame(payload?.data || payload);
}

export async function listRooms(filters) {
  const payload = await playerApi.listRooms({
    q: filters?.search,
    genre: filters?.genre,
    status: filters?.status,
    page: filters?.page,
    per_page: filters?.limit,
  });
  const items = listFrom(payload).map(normalizeRoom);
  return { items, meta: metaFrom(payload, items.length) };
}

export async function getRoom(id) {
  return normalizeRoom(await playerApi.getRoom(id));
}

export async function createRoom(body) {
  return normalizeRoom(await playerApi.createRoom(body));
}

export async function joinRoom(id) {
  return normalizeRoom(await playerApi.joinRoom(id));
}

export async function leaveRoom(id, reason = '') {
  return normalizeRoom(await playerApi.leaveRoom(id, reason ? { reason } : {}));
}

export async function calculateQuote(body) {
  try {
    return await playerApi.quoteRoom(body);
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 405)) {
      return send(apiClient.post('/quotes/calculate', body, { credentials: 'include' }));
    }
    throw error;
  }
}

export async function proposeSchedule(roomId, proposedStartAt) {
  return playerApi.proposeSchedule(roomId, { proposed_start_at: proposedStartAt });
}

export async function voteSchedule(roomId, proposalId, vote) {
  return playerApi.voteSchedule(proposalId, { vote, room_id: roomId });
}

export async function getRoomPayments(roomId) {
  const data = await playerApi.getRoomPayments(roomId);
  return Array.isArray(data) ? data : (data?.payments || data?.transactions || []);
}

export async function payDeposit(roomId) {
  return playerApi.payDeposit(roomId);
}

export async function payRemaining(roomId) {
  return playerApi.payRemaining(roomId);
}

export async function getBookingHistory() {
  const payload = await playerApi.listMyBookings();
  const items = listFrom(payload);
  return items.map((item) => {
    const room = item.room ? normalizeRoom(item.room) : normalizeRoom(item);
    const blob = `${room.status} ${item.status || ''} ${item.payment_status || ''}`.toUpperCase();
    let tab = 'upcoming';
    if (/COMPLETE|FINISHED|PLAYED/.test(blob)) tab = 'completed';
    else if (/DEPOSIT|UNPAID|PAYMENT_PENDING|WAITING_FOR_DEPOSIT/.test(blob)) tab = 'awaiting_deposit';
    return {
      id: room.id,
      code: room.code,
      script_title: room.game?.title || item.script_title || item.title || 'Kịch bản',
      play_date: item.play_date || room.session_start,
      start_time: item.play_time || room.session_start,
      tab: item.tab || item.history_tab || tab,
      room_status: room.status,
      amount_due: Number(item.amount_due ?? 0),
      payment_deadline: item.payment_deadline || '',
      payment_status: item.payment_status || '',
      dm_name: room.dm?.name || room.dm?.dm_name || item.dm_name || '',
      ticket_code: item.ticket_code || '',
    };
  });
}

export { ApiError };
