/**
 * Hash-based Client-side Router with Role-Based Route Guards
 */
import { auth, ROLES } from './auth.js';

export function canAccess(role, path) {
  const normalized = path.replace(/^#/, '').split('?')[0] || '/';

  // Public Routes (Accessible by all, even unauthenticated)
  if (
    normalized === '/' ||
    normalized.startsWith('/games') ||
    normalized.startsWith('/rooms') ||
    normalized === '/login' ||
    normalized === '/register' ||
    normalized === '/health' ||
    normalized === '/403' ||
    normalized === '/404'
  ) {
    return true;
  }

  // Protected Role Routes
  if (normalized.startsWith('/player')) {
    return role === ROLES.PLAYER;
  }

  if (normalized.startsWith('/dm')) {
    return role === ROLES.DM;
  }

  if (normalized.startsWith('/admin')) {
    return role === ROLES.ADMIN;
  }

  return true;
}

export class Router {
  constructor(routes = {}, fallbackHandler = null) {
    this.routes = routes; // Format: { pattern: HandlerClass }
    this.fallbackHandler = fallbackHandler;
    this.currentCleanRoute = '/';
  }

  start() {
    window.addEventListener('hashchange', () => this.handleRouting());
    this.handleRouting();
  }

  navigate(hashPath) {
    window.location.hash = hashPath.startsWith('#') ? hashPath : `#${hashPath}`;
  }

  getCurrentPath() {
    const rawHash = window.location.hash || '#/';
    return rawHash.replace(/^#/, '').split('?')[0] || '/';
  }

  getQueryParams() {
    const rawHash = window.location.hash || '';
    const queryIdx = rawHash.indexOf('?');
    if (queryIdx === -1) return {};
    const queryStr = rawHash.slice(queryIdx + 1);
    const params = {};
    for (const pair of queryStr.split('&')) {
      const [k, v] = pair.split('=');
      if (k) params[decodeURIComponent(k)] = decodeURIComponent(v || '');
    }
    return params;
  }

  match(currentPath) {
    for (const [pattern, handler] of Object.entries(this.routes)) {
      const regexPattern = pattern
        .replace(/:([a-zA-Z0-9_]+)/g, '(?<$1>[^/]+)')
        .replace(/\//g, '\\/');

      const regex = new RegExp(`^${regexPattern}$`);
      const match = currentPath.match(regex);

      if (match) {
        return {
          handler,
          params: match.groups || {},
          pattern,
        };
      }
    }
    return null;
  }

  handleRouting() {
    const currentPath = this.getCurrentPath();
    const queryParams = this.getQueryParams();
    const userRole = auth.getRole();
    const isAuthenticated = auth.isAuthenticated();

    // Route Guard Check
    if (!canAccess(userRole, currentPath)) {
      if (!isAuthenticated) {
        auth.setRedirectAfterLogin(`#${currentPath}`);
        this.navigate('/login');
        return;
      } else {
        // Authenticated but wrong role
        this.navigate('/403');
        return;
      }
    }

    const matched = this.match(currentPath);
    const mountPoint = document.getElementById('app-mount');
    if (!mountPoint) return;

    if (matched) {
      this.currentCleanRoute = currentPath;
      const HandlerClass = matched.handler;
      const pageInstance = new HandlerClass(mountPoint, {
        params: matched.params,
        query: queryParams,
        router: this,
      });
      pageInstance.render();
      window.scrollTo(0, 0);
    } else if (this.fallbackHandler) {
      const FallbackClass = this.fallbackHandler;
      const notFoundInstance = new FallbackClass(mountPoint, {
        params: {},
        query: queryParams,
        router: this,
      });
      notFoundInstance.render();
      window.scrollTo(0, 0);
    }
  }
}
