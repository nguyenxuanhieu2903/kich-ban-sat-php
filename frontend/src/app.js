import { authState } from './api/auth-state.js';
import { api } from './api/api-client.js';
import { toast } from './utils/toast.js';
import { escapeHtml } from './utils/sanitize.js';
import { AuthModal } from './pages/auth/auth-modal.js';
import { HomeView } from './pages/home/home-view.js';
import { GamesView } from './pages/games/games-view.js';
import { RoomsView } from './pages/rooms/rooms-view.js';
import { DmView } from './pages/dm/dm-view.js';
import { AdminView } from './pages/admin/admin-view.js';
import { NotificationsView } from './pages/notifications/notifications-view.js';
import { VenuesView } from './pages/venues/venues-view.js';

class App {
  constructor() {
    this.mountPoint = document.getElementById('app-mount');
    this.userContainer = document.getElementById('nav-user-container');
    this.notifBtn = document.getElementById('nav-btn-notif');
    this.notifBadge = document.getElementById('notif-unread-count');
    this.mobileDrawer = document.getElementById('mobile-nav-drawer');
    this.hamburgerBtn = document.getElementById('nav-hamburger-btn');
    this.hamburgerIcon = document.getElementById('nav-hamburger-icon');
    this.currentTab = 'home';

    // Initialize Auth Modal
    this.authModal = new AuthModal((user, selectedRole) => {
      this.updateNavigationForRole(user);
      this.renderUserNav();
      this.updateNotificationsCount();
      if (user) {
        if (user.role === 'ADMIN') {
          this.navigateTo('admin');
        } else if (user.role === 'DM') {
          this.navigateTo('dm');
        } else {
          // PLAYER: Tách riêng, vào thẳng sảnh phòng chơi
          this.navigateTo('rooms');
        }
      }
      this.refreshCurrentView();
    });
    this.authModal.render();

    // Initialize Views
    this.views = {
      home: new HomeView(this.mountPoint, {
        onNavigate: (tab) => this.navigateTo(tab),
        onOpenAuth: (role) => this.authModal.show(false, role || 'PLAYER')
      }),
      games: new GamesView(this.mountPoint, (game) => {
        if (!authState.isAuthenticated()) {
          this.authModal.show();
        } else {
          this.navigateTo('rooms');
          setTimeout(() => {
            if (this.views.rooms && typeof this.views.rooms.showCreateRoomModal === 'function') {
              this.views.rooms.showCreateRoomModal(game);
            }
          }, 120);
        }
      }),
      rooms: new RoomsView(this.mountPoint, () => this.authModal.show()),
      dm: new DmView(this.mountPoint, () => this.authModal.show(false, 'DM')),
      admin: new AdminView(this.mountPoint, () => this.authModal.show(false, 'ADMIN')),
      venues: new VenuesView(this.mountPoint),
      notifications: new NotificationsView(this.mountPoint)
    };
  }

  async init() {
    this.renderUserNav();
    this.setupNavigation();
    this.setupAuthStateListener();

    // Check session login state
    try {
      await authState.checkSession();
    } catch {
      // not logged in
    }

    const user = authState.getUser();
    this.updateNavigationForRole(user);
    this.renderUserNav();
    this.updateNotificationsCount();

    // Auto-redirect to role-specific portal on initial visit if logged in and at home/empty
    const initialHash = (window.location.hash || '').replace('#', '').trim();
    if (user && (!initialHash || initialHash === 'home')) {
      if (user.role === 'ADMIN') {
        window.location.hash = '#admin';
      } else if (user.role === 'DM') {
        window.location.hash = '#dm';
      } else if (user.role === 'PLAYER') {
        window.location.hash = '#rooms';
      }
    }

    // Handle hash change or default
    window.addEventListener('hashchange', () => this.handleRouting());
    this.handleRouting();

    // Periodic check for notifications if logged in
    setInterval(() => {
      if (authState.isAuthenticated()) {
        this.updateNotificationsCount();
      }
    }, 15000);
  }

  setupNavigation() {
    const navButtons = document.querySelectorAll('.nav-tab-btn[data-tab]');
    navButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        if (tab) this.navigateTo(tab);
      });
    });

    const pricingBtn = document.getElementById('nav-link-pricing');
    const priceDialog = document.getElementById('home-price-dialog');
    const openPricingDialog = (e) => {
      e.preventDefault();
      if (priceDialog && typeof priceDialog.showModal === 'function') {
        if (!priceDialog.open) priceDialog.showModal();
      } else {
        this.navigateTo('games');
      }
    };
    if (pricingBtn) {
      pricingBtn.addEventListener('click', openPricingDialog);
    }
    const footerPricingBtn = document.getElementById('footer-link-pricing');
    if (footerPricingBtn) {
      footerPricingBtn.addEventListener('click', openPricingDialog);
    }
    document.getElementById('btn-close-price-dialog')?.addEventListener('click', () => priceDialog?.close());
    document.getElementById('btn-ok-price-dialog')?.addEventListener('click', () => priceDialog?.close());
    document.getElementById('btn-price-goto-games')?.addEventListener('click', () => {
      priceDialog?.close();
      this.navigateTo('games');
    });
    document.getElementById('btn-price-goto-rooms')?.addEventListener('click', () => {
      priceDialog?.close();
      this.navigateTo('rooms');
    });

    const contactBtn = document.getElementById('nav-link-contact');
    if (contactBtn) {
      contactBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const footer = document.getElementById('app-footer');
        if (footer) footer.scrollIntoView({ behavior: 'smooth' });
      });
    }

    if (this.notifBtn) {
      this.notifBtn.addEventListener('click', () => {
        this.navigateTo('notifications');
      });
    }

    // Mobile Hamburger Toggle
    if (this.hamburgerBtn && this.mobileDrawer) {
      this.hamburgerBtn.addEventListener('click', () => {
        const isOpen = this.mobileDrawer.classList.toggle('open');
        if (this.hamburgerIcon) {
          this.hamburgerIcon.textContent = isOpen ? '✕' : '☰';
        }
      });

      // Mobile Nav Links
      const mobileLinks = this.mobileDrawer.querySelectorAll('.mobile-nav-link');
      mobileLinks.forEach(link => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          const tab = link.dataset.tab;
          this.closeMobileDrawer();
          this.navigateTo(tab);
        });
      });
    }
  }

  closeMobileDrawer() {
    if (this.mobileDrawer && this.mobileDrawer.classList.contains('open')) {
      this.mobileDrawer.classList.remove('open');
      if (this.hamburgerIcon) {
        this.hamburgerIcon.textContent = '☰';
      }
    }
  }

  navigateTo(tab) {
    window.location.hash = `#${tab}`;
  }

  setupAuthStateListener() {
    authState.subscribe(() => {
      const user = authState.getUser();
      this.updateNavigationForRole(user);
      this.renderUserNav();
      this.updateNotificationsCount();
      this.refreshCurrentView();
    });
  }

  updateNavigationForRole(user) {
    const dmBtn = document.getElementById('nav-btn-dm');
    const adminBtn = document.getElementById('nav-btn-admin');
    const mobileDm = document.getElementById('mobile-nav-dm');
    const mobileAdmin = document.getElementById('mobile-nav-admin');

    const isDm = Boolean(user && (user.role === 'DM' || user.role === 'ADMIN'));
    const isAdmin = Boolean(user && user.role === 'ADMIN');

    if (dmBtn) dmBtn.style.display = isDm ? 'flex' : 'none';
    if (mobileDm) mobileDm.style.display = isDm ? 'flex' : 'none';

    if (adminBtn) adminBtn.style.display = isAdmin ? 'flex' : 'none';
    if (mobileAdmin) mobileAdmin.style.display = isAdmin ? 'flex' : 'none';
  }

  handleRouting() {
    let hash = window.location.hash.replace('#', '') || 'home';
    const user = authState.getUser();

    // Role-specific navigation guards
    if (hash === 'dm') {
      if (!user || (user.role !== 'DM' && user.role !== 'ADMIN')) {
        toast.warning('Khu vực dành cho Quản Trò (DM). Vui lòng đăng nhập với vai trò DM.');
        this.navigateTo(user ? 'rooms' : 'home');
        if (!user) this.authModal.show(false, 'DM');
        return;
      }
    } else if (hash === 'admin') {
      if (!user || user.role !== 'ADMIN') {
        toast.warning('Khu vực dành cho Quản Trị Viên (Admin). Vui lòng đăng nhập với tài khoản Admin.');
        this.navigateTo(user?.role === 'DM' ? 'dm' : (user ? 'rooms' : 'home'));
        if (!user) this.authModal.show(false, 'ADMIN');
        return;
      }
    }

    if (!this.views[hash]) {
      hash = 'home';
    }
    this.currentTab = hash;
    this.closeMobileDrawer();

    if (typeof document !== 'undefined') {
      document.body.classList.remove('theme-public', 'theme-player', 'theme-dm', 'theme-admin');
      if (hash === 'rooms' || hash === 'player') {
        document.body.classList.add('theme-player');
      } else if (hash === 'dm') {
        document.body.classList.add('theme-dm');
      } else if (hash === 'admin') {
        document.body.classList.add('theme-admin');
      } else {
        document.body.classList.add('theme-public');
      }
    }

    if (this.mountPoint) {
      if (hash === 'home') {
        this.mountPoint.classList.add('is-home-view');
      } else {
        this.mountPoint.classList.remove('is-home-view');
      }
    }

    // Update active nav button (Desktop)
    document.querySelectorAll('.nav-tab-btn[data-tab]').forEach(btn => {
      if (btn.dataset.tab === hash) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    // Update active nav link (Mobile)
    if (this.mobileDrawer) {
      this.mobileDrawer.querySelectorAll('.mobile-nav-link').forEach(link => {
        if (link.dataset.tab === hash) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }

    this.refreshCurrentView();
  }

  async refreshCurrentView() {
    const view = this.views[this.currentTab];
    if (view && typeof view.render === 'function') {
      try {
        await view.render();
      } catch (err) {
        console.error('[App] Failed to render view:', this.currentTab, err);
        if (this.mountPoint) {
          this.mountPoint.innerHTML = `
            <div style="text-align: center; padding: 60px 20px; color: var(--color-error);">
              <h3 style="margin-bottom: 12px; color: var(--gold);">Đã xảy ra sự cố khi tải trang</h3>
              <p style="color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 20px;">${escapeHtml(err.message || 'Lỗi tải dữ liệu')}</p>
              <button onclick="window.location.hash='#home'; window.location.reload();" class="btn btn-gold btn-sm">Về trang chủ</button>
            </div>
          `;
        }
      }
    }
  }

  renderUserNav() {
    if (!this.userContainer) return;

    const user = authState.getUser();

    if (user) {
      let roleBadgeColor = 'var(--color-primary)';
      if (user.role === 'ADMIN') roleBadgeColor = 'var(--color-error)';
      else if (user.role === 'DM') roleBadgeColor = 'var(--color-warning)';

      this.userContainer.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
          <div class="user-pill" style="cursor: pointer;" id="btn-switch-user" title="Nhấn để đổi nhanh tài khoản khác">
            <span class="badge" style="background: ${roleBadgeColor}; color: white; font-size: 0.7rem;">
              ${escapeHtml(user.role)}
            </span>
            <strong style="color: var(--color-text-main); font-size: 0.85rem;">${escapeHtml(user.name)}</strong>
            <span style="font-size: 0.7rem; color: var(--color-text-muted);">🔄 Đổi vai trò</span>
          </div>
          <button id="btn-logout" class="btn" style="padding: 6px 12px; font-size: 0.8rem; background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted);" title="Đăng xuất khỏi hệ thống">
            Đăng xuất
          </button>
        </div>
      `;

      this.userContainer.querySelector('#btn-switch-user').onclick = () => {
        this.authModal.show();
      };

      this.userContainer.querySelector('#btn-logout').onclick = async () => {
        try {
          await authState.logout();
          this.updateNavigationForRole(null);
          toast.info('Đã đăng xuất khỏi hệ thống!');
          this.navigateTo('home');
        } catch (err) {
          toast.error(err.message || 'Lỗi đăng xuất');
        }
      };
    } else {
      this.userContainer.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
          <button id="btn-open-login" class="btn btn-sm btn-header-crimson">
            Đăng nhập
          </button>
          <button id="btn-open-register" class="btn btn-sm btn-header-crimson">
            Đăng ký
          </button>
        </div>
      `;

      this.userContainer.querySelector('#btn-open-login').onclick = () => {
        this.authModal.show(false);
      };
      this.userContainer.querySelector('#btn-open-register').onclick = () => {
        this.authModal.show(true);
      };
    }
  }

  async updateNotificationsCount() {
    if (!this.notifBadge) return;

    if (!authState.isAuthenticated()) {
      this.notifBadge.style.display = 'none';
      return;
    }

    try {
      const res = await api.get('/notifications?unread_only=1');
      const unreadList = Array.isArray(res?.data?.notifications)
        ? res.data.notifications
        : (Array.isArray(res?.data) ? res.data : []);
      const count = res?.data?.count !== undefined ? Number(res.data.count) : unreadList.length;

      if (count > 0) {
        this.notifBadge.textContent = count > 99 ? '99+' : count;
        this.notifBadge.style.display = 'block';
      } else {
        this.notifBadge.style.display = 'none';
      }
    } catch {
      this.notifBadge.style.display = 'none';
    }
  }
}

function startApp() {
  try {
    const app = new App();
    app.init().catch(err => {
      console.error('[App] Init failed:', err);
    });
  } catch (err) {
    console.error('[App] Constructor failed:', err);
  }
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startApp);
  } else {
    startApp();
  }
}
