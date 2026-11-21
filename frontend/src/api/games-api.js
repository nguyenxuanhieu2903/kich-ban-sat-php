import { apiClient } from './api-client.js';

export const gamesApi = {
  /**
   * Fetch all active games with optional filters.
   * Calls GET /api/games?q=&category=&is_18_plus=
   */
  async listGames(filters = {}) {
    const params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    if (filters.category) params.set('category', filters.category);
    if (filters.is_18_plus !== undefined && filters.is_18_plus !== '') {
      params.set('is_18_plus', filters.is_18_plus);
    }

    const query = params.toString() ? `?${params.toString()}` : '';
    return await apiClient.get(`/games${query}`);
  },

  /**
   * Fetch a specific game detail by ID.
   * Calls GET /api/games/{id}
   */
  async getGame(id) {
    return await apiClient.get(`/games/${id}`);
  },

  /**
   * Fetch game rooms with optional status filter.
   * Calls GET /api/rooms?status=
   */
  async listRooms(status = '') {
    const query = status ? `?status=${encodeURIComponent(status)}` : '';
    return await apiClient.get(`/rooms${query}`);
  },
};
