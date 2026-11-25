// src/logic/guard.js
import { store } from './store.js';

function getPaths() {
  const isInPages = window.location.pathname.includes('/pages/');
  const rootPrefix = isInPages ? '../' : './';
  const pagesPrefix = isInPages ? './' : './pages/';

  return {
    auth: `${pagesPrefix}auth.html`,
    roleHome: {
      guest: `${rootPrefix}index.html`,
      player: `${pagesPrefix}lobby.html`,
      dm: `${pagesPrefix}dm-portal.html`,
      admin: `${pagesPrefix}admin-portal.html`,
    },
  };
}

/**
 * Kiểm tra quyền truy cập trang hiện tại
 * @param {string[]} allowedRoles - Danh sách vai trò được phép vào trang (vd: ['player', 'dm', 'admin'])
 */
export function requireRole(allowedRoles = []) {
  const currentRole = store.state?.currentRole || 'guest';
  const paths = getPaths();

  if (allowedRoles.length === 0 || allowedRoles.includes(currentRole)) {
    return true;
  }

  if (currentRole === 'guest') {
    const returnUrl = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.replace(`${paths.auth}?redirect=${returnUrl}#login`);
    return false;
  }

  alert('Bạn không có quyền truy cập khu vực này.');
  window.location.replace(paths.roleHome[currentRole] || paths.roleHome.guest);
  return false;
}

/**
 * Chặn người đã đăng nhập quay lại trang auth.html
 */
export function redirectIfAuthenticated() {
  const currentRole = store.state?.currentRole || 'guest';
  const paths = getPaths();

  if (currentRole !== 'guest') {
    window.location.replace(paths.roleHome[currentRole] || paths.roleHome.guest);
  }
}