import { api } from './api-client.js';

class AuthState {
  constructor() {
    this.user = null;
    this.listeners = [];
  }

  subscribe(listener) {
    this.listeners.push(listener);
    return () => {
      this.listeners = this.listeners.filter(l => l !== listener);
    };
  }

  notify() {
    const normalizedUser = this.getUser();
    this.listeners.forEach(listener => {
      try {
        listener(normalizedUser);
      } catch (e) {
        console.error('[AuthState] Listener error:', e);
      }
    });
  }

  getUser() {
    if (!this.user) return null;
    return {
      ...this.user,
      id: Number(this.user.id),
      name: this.user.full_name || this.user.name || 'Người dùng',
      role: (this.user.role_code || this.user.role || 'PLAYER').toUpperCase(),
    };
  }

  async checkAuth() {
    try {
      const res = await api.get('/auth/me');
      if (res && res.success && res.data && res.data.user) {
        this.user = res.data.user;
      } else {
        this.user = null;
      }
    } catch {
      this.user = null;
    }
    this.notify();
    return this.getUser();
  }

  async checkSession() {
    return this.checkAuth();
  }

  async login(email, password) {
    const res = await api.post('/auth/login', { email, password });
    if (res && res.success && res.data && res.data.user) {
      this.user = res.data.user;
      this.notify();
      return this.getUser();
    }
    throw new Error(res?.message || 'Đăng nhập thất bại');
  }

  async register(data) {
    const res = await api.post('/auth/register', data);
    if (res && res.success && res.data && res.data.user) {
      this.user = res.data.user;
      this.notify();
      return this.getUser();
    }
    throw new Error(res?.message || 'Đăng ký thất bại');
  }

  async logout() {
    try {
      await api.post('/auth/logout');
    } finally {
      api.clearCsrfToken();
      this.user = null;
      this.notify();
    }
  }

  isAuthenticated() {
    return this.user !== null;
  }

  hasRole(role) {
    if (!this.user) return false;
    const userRole = (this.user.role_code || this.user.role || '').toUpperCase();
    return userRole === role.toUpperCase();
  }

  isAdmin() {
    return this.hasRole('ADMIN');
  }

  isDM() {
    return this.hasRole('DM') || this.isAdmin();
  }
}

export const authState = new AuthState();
