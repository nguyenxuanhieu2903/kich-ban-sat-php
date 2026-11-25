import { playerApi, getCachedUser, logout } from '../api/player-api.js';
import { h } from './dom.js';
import { initials } from '../logic/format.js';
import { normalizeProfile } from '../logic/normalize.js';

function portalHref(file) {
  const path = String(window.location.pathname || '').replace(/\\/g, '/');
  const prefix = /\/pages(?:\/|$)/.test(path) ? '../' : './';
  return `${prefix}${String(file).replace(/^\.\//, '')}`;
}

const NAV = [
  { id: 'home', href: 'index.html', label: 'Trang chủ' },
  { id: 'lobby', href: 'lobby.html', label: 'Sảnh phòng' },
  { id: 'create', href: 'create-room.html', label: 'Tạo phòng' },
  { id: 'ticket', href: 'booking-status.html', label: 'Vé đặt phòng' },
  { id: 'profile', href: 'profile.html', label: 'Hồ sơ' },
];

function brand() {
  const logo = h('img', { src: portalHref('src/assets/images/logo.png'), alt: 'Logo Kịch Bản Sát' });
  logo.addEventListener('error', () => logo.remove());
  return h('a', { class: 'brand-logo', href: portalHref('index.html') }, [logo, h('span', {}, 'KỊCH BẢN SÁT')]);
}

function displayName(profile) {
  if (!profile) return '';
  return profile.fullName || profile.full_name || profile.email || '';
}

export function mountChrome(active) {
  const headerHost = document.getElementById('player-header');
  const footerHost = document.getElementById('player-footer');
  if (!headerHost || !footerHost) return;

  const nav = h('nav', { class: 'nav-links', id: 'player-nav', 'aria-label': 'Điều hướng người chơi' }, NAV.map((item) => (
    h('a', {
      href: portalHref(item.href),
      class: item.id === active ? 'active' : '',
      'aria-current': item.id === active ? 'page' : false,
    }, item.label)
  )));

  const toggle = h('button', {
    class: 'btn btn-outline player-nav-toggle',
    type: 'button',
    'aria-expanded': 'false',
    'aria-controls': 'player-nav',
    onClick: () => {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    },
  }, 'Menu');

  const authHost = h('div', { class: 'player-header-actions', id: 'player-auth-host' });

  function paintAuth(profile) {
    const name = displayName(profile);
    if (!name) {
      authHost.replaceChildren(
        h('a', { class: 'btn btn-outline', href: portalHref('profile.html'), id: 'player-auth-link' }, 'Đăng nhập'),
        h('a', { class: 'btn btn-gold', href: portalHref('create-room.html') }, 'Tạo phòng'),
      );
      return;
    }
    const wrap = h('div', { class: 'landing-account' }, [
      h('button', {
        class: 'btn btn-outline landing-account-btn',
        type: 'button',
        'aria-haspopup': 'true',
        'aria-expanded': 'false',
        onClick: (event) => {
          const open = wrap.classList.toggle('is-open');
          event.currentTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
        },
      }, [
        h('span', { class: 'landing-avatar' }, initials(name)),
        h('span', {}, name),
      ]),
      h('div', { class: 'landing-account-menu', role: 'menu' }, [
        h('a', { href: portalHref('profile.html'), role: 'menuitem' }, 'Hồ sơ cá nhân'),
        h('a', { href: portalHref('profile.html?tab=upcoming'), role: 'menuitem' }, 'Lịch sử đặt phòng'),
        h('button', {
          type: 'button',
          role: 'menuitem',
          onClick: async () => {
            try {
              await logout();
            } catch {
              /* phiên có thể đã hết */
            }
            window.location.href = portalHref('profile.html');
          },
        }, 'Đăng xuất'),
      ]),
    ]);
    document.addEventListener('click', (event) => {
      if (!wrap.contains(event.target)) wrap.classList.remove('is-open');
    });
    authHost.replaceChildren(
      wrap,
      h('a', { class: 'btn btn-gold', href: portalHref('create-room.html') }, 'Tạo phòng'),
    );
  }

  paintAuth(getCachedUser());

  headerHost.replaceChildren(h('header', { class: 'site-header' }, [
    h('div', { class: 'container header-inner' }, [
      brand(),
      toggle,
      nav,
      authHost,
    ]),
  ]));

  const column = (title, lines) => h('div', {}, [
    h('strong', { class: 'player-footer-title' }, title),
    ...lines,
  ]);

  footerHost.replaceChildren(h('footer', { class: 'site-footer' }, [
    h('div', { class: 'container player-footer-grid' }, [
      column('KỊCH BẢN SÁT', [
        h('p', {}, 'Không gian nhập vai phá án tại TP.HCM. Cọc 50.000đ, giữ chỗ 4 giờ, hoàn cọc khi hủy trước 24 giờ.'),
      ]),
      column('Người chơi', NAV.map((item) => h('a', { href: portalHref(item.href) }, item.label))),
      column('Quán chơi', [
        h('p', {}, '36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh'),
        h('p', {}, 'Mở cửa 09:00–23:00 mỗi ngày'),
        h('p', {}, 'Hotline 091.569.0033'),
      ]),
      column('Ưu đãi', [
        h('p', {}, 'Thứ 3 Bí Ẩn: giảm 30% giá vé.'),
        h('p', {}, 'HSSV: giảm 20.000đ/người, Thứ 2–Thứ 5, 09:00–19:59.'),
        h('p', {}, 'Hai ưu đãi không cộng dồn.'),
      ]),
    ]),
  ]));

  playerApi.getMe().then((data) => {
    paintAuth(normalizeProfile(data));
  }).catch(() => {
    paintAuth(getCachedUser());
  });
}
