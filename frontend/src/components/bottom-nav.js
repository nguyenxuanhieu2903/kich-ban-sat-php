/**
 * Mobile Bottom Tab Bar Component (Player & DM Viewports)
 */
import { h, renderIcon } from '../utils/dom.js';
import { auth, ROLES } from '../utils/auth.js';

export function renderBottomNav(currentPath) {
  const role = auth.getRole();
  if (role !== ROLES.PLAYER && role !== ROLES.DM) return null;

  const bar = h('nav', { className: 'mobile-bottom-nav' });

  if (role === ROLES.PLAYER) {
    const items = [
      { path: '/player/dashboard', icon: 'users', label: 'Phòng của tôi' },
      { path: '/games', icon: 'search', label: 'Khám phá' },
      { path: '/player/create-room', icon: 'plus', label: 'Mở phòng' },
      { path: '/player/notifications', icon: 'bell', label: 'Thông báo' },
      { path: '/player/profile', icon: 'user', label: 'Hồ sơ' },
    ];

    items.forEach((item) => {
      const isActive = currentPath.startsWith(item.path);
      bar.appendChild(
        h('a', { className: `bottom-nav-item ${isActive ? 'active' : ''}`, href: `#${item.path}` },
          renderIcon(item.icon, 'bottom-icon'),
          h('span', { className: 'bottom-label' }, item.label)
        )
      );
    });
  } else if (role === ROLES.DM) {
    const items = [
      { path: '/dm/dashboard', icon: 'calendar', label: 'Bàn việc DM' },
      { path: '/dm/assignments', icon: 'shield', label: 'Phân công' },
      { path: '/dm/availability', icon: 'clock', label: 'Lịch rảnh' },
      { path: '/dm/notifications', icon: 'bell', label: 'Thông báo' },
    ];

    items.forEach((item) => {
      const isActive = currentPath.startsWith(item.path);
      bar.appendChild(
        h('a', { className: `bottom-nav-item ${isActive ? 'active' : ''}`, href: `#${item.path}` },
          renderIcon(item.icon, 'bottom-icon'),
          h('span', { className: 'bottom-label' }, item.label)
        )
      );
    });
  }

  return bar;
}
