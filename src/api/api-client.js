// src/api/api-client.js
const API_CONFIG = {
  USE_MOCK: true, // Đổi thành false khi ghép với PHP Backend
  BASE_URL: 'http://localhost/kich-ban-sat-php/backend/api',
};

export async function apiRequest(endpoint, options = {}) {
  if (API_CONFIG.USE_MOCK) {
    throw new Error('MOCK_MODE_ACTIVE');
  }

  const token = localStorage.getItem('kbs_token');
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...options.headers,
  };

  const response = await fetch(`${API_CONFIG.BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error(err.message || `Lỗi HTTP ${response.status}`);
  }

  return response.json();
}