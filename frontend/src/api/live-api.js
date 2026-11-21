/**
 * Live Backend API Adapter (MVP V16)
 * Connects Frontend (port 3000) to PHP Backend (http://127.0.0.1:8000/api).
 */
import { apiClient } from './api-client.js';

function toNumericId(id) {
  return String(id ?? '').replace(/^[a-zA-Z_]+/, '');
}

function toMysqlDateTime(isoOrDateStr) {
  if (!isoOrDateStr) return '';
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(isoOrDateStr)) {
    return isoOrDateStr;
  }
  const d = new Date(isoOrDateStr);
  if (Number.isNaN(d.getTime())) return isoOrDateStr;
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

function normalizeGame(item) {
  const basePrice = Number(item.price_day_vnd ?? item.price_per_player ?? 150000);
  return {
    id: `game_${item.id}`,
    backend_id: Number(item.id),
    code: item.code || `KBS-${item.id}`,
    title: item.title || item.name || 'Kịch bản',
    genre: item.genre || 'Trinh thám',
    description: item.description || '',
    min_players: Number(item.min_players ?? 4),
    max_players: Number(item.max_players ?? 8),
    duration_minutes: Number(item.duration_minutes ?? 180),
    price_day_vnd: basePrice,
    price_night_vnd: Number(item.price_night_vnd ?? (basePrice + 30000)),
    difficulty: item.difficulty || 'MEDIUM',
    verified: true,
    is_demo_fill: false,
    status: item.status || 'ACTIVE',
  };
}

function normalizeRoom(room) {
  const participants = Array.isArray(room.participants)
    ? room.participants.map((p) => ({
        id: p.id ? `part_${p.id}` : `part_${p.user_id}`,
        backend_id: Number(p.id ?? 0),
        user_id: String(p.user_id),
        name: p.full_name || p.name || `Player #${p.user_id}`,
        status: p.status || 'JOINED',
        joined_at: p.joined_at || p.created_at || null,
      }))
    : [];

  return {
    ...room,
    id: `room_${room.id}`,
    backend_id: Number(room.id),
    code: room.code || `PHONG-${String(room.id).padStart(3, '0')}`,
    game_id: `game_${room.game_id}`,
    creator_id: String(room.host_user_id ?? room.creator_id ?? ''),
    status: room.status,
    session_start_time: room.scheduled_start_at || room.session_start_time,
    formation_deadline: room.formation_deadline_at || room.formation_deadline || null,
    participants,
    allowed_actions: room.allowed_actions || ['JOIN', 'LEAVE_BEFORE_DEPOSIT', 'SHARE'],
  };
}

export const liveApi = {
  async getHealth() {
    return apiClient.get('/health');
  },

  // --- AUTH ---
  async login(email, password) {
    return apiClient.post('/auth/login', { email, password });
  },

  async register(payload) {
    return apiClient.post('/auth/register', payload);
  },

  async logout() {
    return apiClient.post('/auth/logout', {});
  },

  async getMe() {
    return apiClient.get('/auth/me');
  },

  // --- GAMES ---
  async getGames(filters = {}) {
    const params = new URLSearchParams();
    if (filters.genre) params.set('genre', filters.genre);
    if (filters.players) params.set('players', filters.players);
    const qs = params.toString() ? `?${params.toString()}` : '';
    const res = await apiClient.get(`/games${qs}`);
    const list = Array.isArray(res.data) ? res.data : [];
    return {
      success: res.success,
      data: list.map(normalizeGame),
      total: res.total || list.length,
    };
  },

  async getGameById(id) {
    const res = await apiClient.get(`/games/${toNumericId(id)}`);
    return {
      success: res.success,
      data: normalizeGame(res.data),
    };
  },

  // --- GAME ROOMS ---
  async getPublicRooms(filters = {}) {
    const params = new URLSearchParams();
    if (filters.status) params.set('status', filters.status);
    const qs = params.toString() ? `?${params.toString()}` : '';
    const res = await apiClient.get(`/rooms${qs}`);
    const list = Array.isArray(res.data) ? res.data : [];
    let rooms = list.map(normalizeRoom);
    if (filters.gameId) {
      rooms = rooms.filter((r) => r.game_id === filters.gameId || String(r.game_id) === `game_${toNumericId(filters.gameId)}`);
    }
    return { success: res.success, data: rooms, total: rooms.length };
  },

  async getRoomById(roomId) {
    const res = await apiClient.get(`/rooms/${toNumericId(roomId)}`);
    const normalized = normalizeRoom(res.data);
    return { success: res.success, data: normalized };
  },

  async createRoom(payload) {
    const body = {
      game_id: Number(toNumericId(payload.game_id)),
      scheduled_start_at: toMysqlDateTime(payload.scheduled_start_at || payload.session_start_time),
    };
    const res = await apiClient.post('/rooms', body);
    return {
      success: res.success,
      data: normalizeRoom(res.data),
      message: res.message || 'Tạo phòng chơi thành công',
    };
  },

  async joinRoom(roomId) {
    const res = await apiClient.post(`/rooms/${toNumericId(roomId)}/join`, {});
    return {
      success: res.success,
      data: normalizeRoom(res.data),
      message: res.message || 'Tham gia phòng chơi thành công',
    };
  },

  async leaveRoom(roomId) {
    const res = await apiClient.post(`/rooms/${toNumericId(roomId)}/leave`, {});
    return {
      success: res.success,
      data: normalizeRoom(res.data),
      message: res.message || 'Rời phòng chơi thành công',
    };
  },

  // --- PAYMENTS ---
  async makeDeposit(roomId, idempotencyKey) {
    const headers = idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {};
    return apiClient.post(
      `/rooms/${toNumericId(roomId)}/payments/deposit`,
      { provider: 'VNPAY' },
      { headers }
    );
  },

  // --- ADMIN & NOTIFICATIONS ---
  async adminCancelRoom(roomId, reason) {
    return apiClient.post(`/rooms/${toNumericId(roomId)}/cancel`, { reason });
  },

  async adminReopen(roomId, windowMinutes, reason) {
    return apiClient.post(`/rooms/${toNumericId(roomId)}/reopen`, {
      window_minutes: Number(windowMinutes || 60),
      reason,
    });
  },

  async getNotifications() {
    return apiClient.get('/notifications');
  },

  async getAuditLogs() {
    return apiClient.get('/admin/audit-logs');
  },
};