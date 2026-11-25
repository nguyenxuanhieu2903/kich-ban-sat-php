// src/api/api-client.js
const API_CONFIG = {
  USE_MOCK: true, // Đổi thành false khi ghép với PHP Backend
  BASE_URL: 'http://localhost/kich-ban-sat-php/backend/api',
};

function generateIdempotencyKey() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `kbs-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export async function apiRequest(endpoint, options = {}) {
  if (API_CONFIG.USE_MOCK) {
    throw new Error('MOCK_MODE_ACTIVE');
  }

  const method = (options.method || 'GET').toUpperCase();
  const isMutation = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
  const token = localStorage.getItem('kbs_token');

  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(isMutation ? { 'Idempotency-Key': options.idempotencyKey || generateIdempotencyKey() } : {}),
    ...options.headers,
  };

  const response = await fetch(`${API_CONFIG.BASE_URL}${endpoint}`, {
    ...options,
    method,
    headers,
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error(err.message || `Lỗi HTTP ${response.status}`);
  }

  return response.json();
}