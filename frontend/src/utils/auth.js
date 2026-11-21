/**
 * Authentication and Session Management
 * Handles CurrentUser, Role Definitions, and Demo Account Switching.
 */

export const ROLES = {
  PLAYER: 'PLAYER',
  DM: 'DM',
  ADMIN: 'ADMIN',
};

export const ROLE_LABELS = {
  [ROLES.PLAYER]: 'Người chơi',
  [ROLES.DM]: 'Quản trò (DM)',
  [ROLES.ADMIN]: 'Quản trị viên',
};

export const DEMO_USERS = {
  [ROLES.PLAYER]: {
    id: 'user_player_01',
    name: 'Trường Nguyễn (Người chơi)',
    email: 'player@kichbansat.vn',
    phone: '0901234567',
    role: ROLES.PLAYER,
    student_verified: true, // Để minh họa ưu đãi HSSV
    student_card_no: 'SV-HCM-2024-9981',
  },
  [ROLES.DM]: {
    id: 'user_dm_01',
    name: 'Hải Đăng (Quản trò)',
    email: 'dm.haidang@kichbansat.vn',
    phone: '0912345678',
    role: ROLES.DM,
    bio: 'DM kỳ cựu với hơn 500 giờ dẫn dắt Jubensha trinh thám & kinh dị.',
  },
  [ROLES.ADMIN]: {
    id: 'user_admin_01',
    name: 'Ban Quản Trị Hệ Thống',
    email: 'admin@kichbansat.vn',
    phone: '0988888888',
    role: ROLES.ADMIN,
  },
};

const STORAGE_KEY = 'kbs_session_user';
const REDIRECT_KEY = 'kbs_redirect_after_login';

class AuthManager {
  constructor() {
    this.currentUser = this.loadUser();
    this.listeners = new Set();
  }

  loadUser() {
    if (typeof window !== 'undefined' && window.location) {
      const hashStr = window.location.hash || '';
      const searchStr = window.location.search || '';
      const match = hashStr.match(/[?&]role=(PLAYER|DM|ADMIN)/i) || searchStr.match(/[?&]role=(PLAYER|DM|ADMIN)/i);
      if (match && match[1]) {
        const targetRole = match[1].toUpperCase();
        if (DEMO_USERS[targetRole]) {
          return DEMO_USERS[targetRole];
        }
      }
    }
    if (typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (stored) {
          return JSON.parse(stored);
        }
      } catch (e) {
        // Ignore parse errors
      }
    }
    // Default demo session: PLAYER for immediate testing
    return DEMO_USERS[ROLES.PLAYER];
  }

  saveUser(user) {
    this.currentUser = user;
    if (typeof sessionStorage !== 'undefined') {
      if (user) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(user));
      } else {
        sessionStorage.removeItem(STORAGE_KEY);
      }
    }
    this.notify();
  }

  getUser() {
    return this.currentUser;
  }

  getRole() {
    return this.currentUser?.role || null;
  }

  isAuthenticated() {
    return Boolean(this.currentUser);
  }

  isPlayer() {
    return this.currentUser?.role === ROLES.PLAYER;
  }

  isDM() {
    return this.currentUser?.role === ROLES.DM;
  }

  isAdmin() {
    return this.currentUser?.role === ROLES.ADMIN;
  }

  /**
   * 1-Click Role Switcher for Demo Mode
   */
  switchDemoRole(targetRole) {
    const demoUser = DEMO_USERS[targetRole];
    if (demoUser) {
      this.saveUser({ ...demoUser });
      return true;
    }
    return false;
  }

  login(userData) {
    this.saveUser(userData);
  }

  logout() {
    this.saveUser(null);
  }

  /**
   * Post-login redirect preserving context
   */
  setRedirectAfterLogin(hashRoute) {
    if (hashRoute && !hashRoute.includes('/login') && !hashRoute.includes('/register')) {
      sessionStorage.setItem(REDIRECT_KEY, hashRoute);
    }
  }

  consumeRedirectAfterLogin() {
    const route = sessionStorage.getItem(REDIRECT_KEY);
    sessionStorage.removeItem(REDIRECT_KEY);
    return route;
  }

  subscribe(listener) {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  notify() {
    for (const listener of this.listeners) {
      try {
        listener(this.currentUser);
      } catch (err) {
        console.error('Error in auth listener:', err);
      }
    }
  }
}

export const auth = new AuthManager();
