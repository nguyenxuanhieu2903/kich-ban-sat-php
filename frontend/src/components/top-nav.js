/**
 * Universal Top Navigation Bar & Demo Mode Control Banner
 */
import { h, renderIcon } from '../utils/dom.js';
import { auth, ROLES, ROLE_LABELS } from '../utils/auth.js';
import { config } from '../utils/config.js';
import { sharedStore } from '../api/demo/shared-store.js';
import { showToast } from './toast.js';

export function renderTopNav(router) {
  const container = h('header', { className: 'app-top-header' });

  // 1. Demo Mode Warning & Role Switcher Banner
  if (config.DATA_MODE === 'demo') {
    const currentUser = auth.getUser();
    const currentRole = auth.getRole();

    const banner = h('div', { className: 'demo-control-banner' },
      h('div', { className: 'demo-banner-left' },
        h('span', { className: 'demo-badge' }, 'CHẾ ĐỘ TRẢI NGHIỆM'),
        h('span', { className: 'demo-text' }, 'Chuyển đổi vai trò để thử toàn bộ quy trình đặt lịch & quản lý:')
      ),
      h('div', { className: 'demo-banner-right' },
        h('div', { className: 'demo-role-btns' },
          h('button', {
            className: `btn-role-tab ${currentRole === ROLES.PLAYER ? 'active' : ''}`,
            title: 'Trải nghiệm giao diện Người chơi: Tìm kịch bản, mở phòng, đặt cọc',
            onclick: () => {
              auth.switchDemoRole(ROLES.PLAYER);
              showToast('Đã chuyển sang tài khoản Người chơi (Player).', 'info');
              router.navigate('/player/dashboard');
            },
          }, 
            h('span', { className: 'role-label-full' }, '👤 Người chơi (HSSV)'),
            h('span', { className: 'role-label-short' }, 'Người chơi')
          ),
          h('button', {
            className: `btn-role-tab ${currentRole === ROLES.DM ? 'active' : ''}`,
            title: 'Trải nghiệm giao diện Quản trò (DM): Đăng ký lịch, nhận ca, điều khiển bàn chơi',
            onclick: () => {
              auth.switchDemoRole(ROLES.DM);
              showToast('Đã chuyển sang tài khoản Quản trò (DM).', 'info');
              router.navigate('/dm/dashboard');
            },
          }, 
            h('span', { className: 'role-label-full' }, '🎭 Quản trò (DM)'),
            h('span', { className: 'role-label-short' }, 'Quản trò')
          ),
          h('button', {
            className: `btn-role-tab ${currentRole === ROLES.ADMIN ? 'active' : ''}`,
            title: 'Trải nghiệm giao diện Quản trị viên: Gán DM, xếp phòng quán, duyệt cọc',
            onclick: () => {
              auth.switchDemoRole(ROLES.ADMIN);
              showToast('Đã chuyển sang tài khoản Quản trị viên (Admin).', 'info');
              router.navigate('/admin/dashboard');
            },
          }, 
            h('span', { className: 'role-label-full' }, '⚡ Quản trị viên'),
            h('span', { className: 'role-label-short' }, 'Quản trị')
          )
        ),
        h('button', {
          className: 'btn-reset-demo',
          title: 'Khôi phục dữ liệu mẫu ban đầu',
          onclick: () => {
            sharedStore.reset();
            showToast('Đã khôi phục dữ liệu ban đầu.', 'success');
            router.handleRouting();
          },
        }, renderIcon('refresh', 'reset-icon'), ' Làm mới')
      )
    );
    container.appendChild(banner);
  }

  // 2. Main Navigation Bar
  const navBar = h('div', { className: 'main-navbar' });

  // Brand
  const brand = h('a', { className: 'brand-link', href: '#/' },
    h('span', { className: 'brand-icon-seal' }, 'KS'),
    h('div', { className: 'brand-text-block' },
      h('span', { className: 'brand-name' }, 'KỊCH BẢN SÁT'),
      h('span', { className: 'brand-subtitle' }, 'JUBENSHA BOOKING')
    )
  );
  navBar.appendChild(brand);

  // Dynamic Navigation Links
  const user = auth.getUser();
  const role = auth.getRole();
  const navLinks = h('nav', { className: 'nav-links' });

  // Always show public game and room links
  navLinks.appendChild(h('a', { className: 'nav-item', href: '#/games' }, 'Danh mục kịch bản'));
  navLinks.appendChild(h('a', { className: 'nav-item', href: '#/rooms' }, 'Phòng đang tuyển'));

  // Role Specific Portals
  if (role === ROLES.PLAYER) {
    navLinks.appendChild(h('a', { className: 'nav-item nav-highlight', href: '#/player/dashboard' }, 'Phòng của tôi'));
    navLinks.appendChild(h('a', { className: 'nav-item btn-nav-cta', href: '#/player/create-room' }, '+ Mở phòng mới'));
  } else if (role === ROLES.DM) {
    navLinks.appendChild(h('a', { className: 'nav-item nav-highlight', href: '#/dm/dashboard' }, 'Bàn làm việc DM'));
    navLinks.appendChild(h('a', { className: 'nav-item', href: '#/dm/assignments' }, 'Nhận phân công'));
    navLinks.appendChild(h('a', { className: 'nav-item', href: '#/dm/availability' }, 'Lịch rảnh'));
  } else if (role === ROLES.ADMIN) {
    navLinks.appendChild(h('a', { className: 'nav-item nav-highlight', href: '#/admin/dashboard' }, 'Bảng quản trị'));
    navLinks.appendChild(h('a', { className: 'nav-item', href: '#/admin/dm-assignments' }, 'Điều phối DM'));
    navLinks.appendChild(h('a', { className: 'nav-item', href: '#/admin/refunds' }, 'Duyệt hoàn cọc'));
  }

  navBar.appendChild(navLinks);

  // Right Side: Notifications & User Profile
  const rightArea = h('div', { className: 'nav-right-area' });

  if (user) {
    // Notification Bell
    const unreadCount = sharedStore.data.notifications.filter((n) => n.user_id === user.id && !n.is_read).length;
    const notifTarget = role === ROLES.PLAYER ? '#/player/notifications' : (role === ROLES.DM ? '#/dm/notifications' : '#/admin/notifications');

    const bellBtn = h('a', { className: 'nav-bell-btn', href: notifTarget, title: 'Thông báo' },
      renderIcon('bell'),
      unreadCount > 0 ? h('span', { className: 'bell-badge' }, String(unreadCount)) : null
    );
    rightArea.appendChild(bellBtn);

    // User Profile Block
    const roleLabel = ROLE_LABELS[role] || 'Người dùng';
    const profileBlock = h('div', { className: 'user-profile-menu' },
      h('div', { className: 'user-info-text' },
        h('span', { className: 'user-name' }, user.name.split('(')[0].trim()),
        h('span', { className: `user-role-badge role-${role.toLowerCase()}` }, roleLabel)
      ),
      h('button', {
        className: 'btn-logout',
        title: 'Đăng xuất',
        onclick: () => {
          auth.logout();
          showToast('Đã đăng xuất khỏi tài khoản.', 'info');
          router.navigate('/login');
        },
      }, renderIcon('logout'))
    );
    rightArea.appendChild(profileBlock);
  } else {
    // Guest buttons
    rightArea.appendChild(
      h('a', { className: 'btn btn-sm btn-secondary', href: '#/login' }, 'Đăng nhập')
    );
    rightArea.appendChild(
      h('a', { className: 'btn btn-sm btn-primary', href: '#/register' }, 'Đăng ký')
    );
  }

  navBar.appendChild(rightArea);
  container.appendChild(navBar);

  return container;
}
