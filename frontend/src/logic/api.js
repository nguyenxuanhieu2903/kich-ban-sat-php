// src/logic/api.js
// Tầng gọi API Backend thật (Same-Origin /api/v1) kèm tự động quản lý CSRF Token

const API_BASE = '/api/v1';
let cachedCsrf = null;

export async function getCsrfToken(force = false) {
  if (cachedCsrf && !force) return cachedCsrf;
  const res = await fetch(`${API_BASE}/auth/csrf-token`, { credentials: 'include' });
  const json = await res.json().catch(() => ({}));
  cachedCsrf = json?.data?.csrf_token || '';
  return cachedCsrf;
}

export async function apiRequest(path, { method = 'GET', body = null, isFormData = false } = {}) {
  const upper = method.toUpperCase();
  const headers = { Accept: 'application/json' };

  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(upper)) {
    const token = await getCsrfToken();
    if (token) headers['X-CSRF-Token'] = token;
    headers['Idempotency-Key'] = `kbs-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
  }

  let payload = body;
  if (body !== null && !isFormData && typeof body === 'object') {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res = await fetch(`${API_BASE}${path}`, {
    method: upper,
    headers,
    body: payload,
    credentials: 'include',
  });

  // Nếu CSRF hết hạn (403 CSRF), làm mới token 1 lần rồi gửi lại
  if (res.status === 403 && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(upper)) {
    const fresh = await getCsrfToken(true);
    if (fresh) {
      headers['X-CSRF-Token'] = fresh;
      res = await fetch(`${API_BASE}${path}`, {
        method: upper,
        headers,
        body: payload,
        credentials: 'include',
      });
    }
  }

  const json = await res.json().catch(() => ({}));
  if (!res.ok || json.success === false) {
    const msg = json?.error?.message || json?.message || `Lỗi máy chủ (HTTP ${res.status})`;
    const err = new Error(msg);
    err.status = res.status;
    err.code = json?.error?.code || json?.code || `HTTP_${res.status}`;
    throw err;
  }
  return json.data !== undefined ? json.data : json;
}

export const api = {
  get: (path) => apiRequest(path, { method: 'GET' }),
  post: (path, body = {}) => apiRequest(path, { method: 'POST', body }),
  put: (path, body = {}) => apiRequest(path, { method: 'PUT', body }),
  patch: (path, body = {}) => apiRequest(path, { method: 'PATCH', body }),
  upload: (path, formData) => apiRequest(path, { method: 'POST', body: formData, isFormData: true }),
};

export async function syncSessionUser(store) {
  try {
    const data = await api.get('/auth/me');
    const u = data?.user || data;
    if (u && u.id) {
      const role = String(u.role || u.role_code || 'PLAYER').toLowerCase();
      store.state.currentUser = {
        ...store.state.currentUser,
        id: u.id,
        name: u.full_name || u.name || u.email,
        phone: u.phone || '',
        email: u.email || '',
        studentVerified: Boolean(u.student_verified),
      };
      store.setRole(role);
      return { user: u, role };
    }
  } catch {
    // 401 khi chưa đăng nhập -> đưa về guest
    if (store.state.currentRole !== 'guest') {
      store.setRole('guest');
    }
  }
  return { user: null, role: 'guest' };
}
