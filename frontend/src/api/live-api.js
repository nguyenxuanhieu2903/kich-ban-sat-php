/**
 * Live Backend API Adapter
 * Directly consumes implemented endpoints on the native PHP backend (http://127.0.0.1:8080/api).
 * Normalizes backend responses into standard frontend domain format.
 */
import { apiClient } from './api-client.js';

export const liveApi = {
  // GET /api/health
  async getHealth() {
    return apiClient.get('/health');
  },

  // GET /api/scripts
  async getGames(filters = {}) {
    let queryParams = [];
    if (filters.genre) queryParams.push(`genre=${encodeURIComponent(filters.genre)}`);
    if (filters.players) queryParams.push(`players=${encodeURIComponent(filters.players)}`);

    const queryStr = queryParams.length > 0 ? `?${queryParams.join('&')}` : '';
    const res = await apiClient.get(`/scripts${queryStr}`);
    
    // Normalize backend response
    return {
      success: res.success,
      data: res.data.map((item) => ({
        id: `game_${item.id}`,
        backend_id: item.id,
        code: item.code,
        title: item.title,
        genre: item.genre,
        description: item.description,
        min_players: item.min_players,
        max_players: item.max_players,
        duration_minutes: item.duration_minutes,
        price_day_vnd: item.price_per_player,
        price_night_vnd: item.price_per_player + 30000,
        difficulty: item.difficulty,
        verified: true, // From actual database
        is_demo_fill: false,
        status: item.status,
      })),
      total: res.total || res.data.length,
    };
  },

  // GET /api/scripts/{id}
  async getGameById(id) {
    // Extract numeric ID if string (e.g. 'game_1' -> 1)
    const numericId = String(id).replace(/^[a-zA-Z_]+/, '');
    const res = await apiClient.get(`/scripts/${numericId}`);
    const item = res.data;

    return {
      success: res.success,
      data: {
        id: `game_${item.id}`,
        backend_id: item.id,
        code: item.code,
        title: item.title,
        genre: item.genre,
        description: item.description,
        min_players: item.min_players,
        max_players: item.max_players,
        duration_minutes: item.duration_minutes,
        price_day_vnd: item.price_per_player,
        price_night_vnd: item.price_per_player + 30000,
        difficulty: item.difficulty,
        verified: true,
        is_demo_fill: false,
        status: item.status,
      },
    };
  },

  // Endpoints currently uncommitted/planned in backend
  async getPublicRooms() {
    throw new Error('[PROVISIONAL] Endpoint GET /api/game-rooms chưa được triển khai trên backend.');
  },

  async createRoom() {
    throw new Error('[PROVISIONAL] Endpoint POST /api/game-rooms chưa được triển khai trên backend.');
  },

  async makeDeposit() {
    throw new Error('[PROVISIONAL] Endpoint POST /api/payments/mock-deposit chưa được triển khai trên backend.');
  },
};
