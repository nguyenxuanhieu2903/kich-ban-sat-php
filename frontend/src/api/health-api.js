import { apiClient } from './api-client.js';

export const healthApi = {
  /**
   * Check backend application health.
   * Calls GET /api/health
   */
  async checkHealth() {
    return await apiClient.get('/health');
  },
};
