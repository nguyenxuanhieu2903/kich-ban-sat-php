import { authState } from './api/auth-state.js';
import { api } from './api/api-client.js';
import { toast } from './utils/toast.js';
import { AuthModal } from './pages/auth/auth-modal.js';
import { GamesView } from './pages/games/games-view.js';
import { RoomsView } from './pages/rooms/rooms-view.js';
import { DmView } from './pages/dm/dm-view.js';
import { AdminView } from './pages/admin/admin-view.js';
import { NotificationsView } from './pages/notifications/notifications-view.js';

class App {
  constructor() {
    this.mountPoint = document.getElementById('app-mount');
    this.userContainer = document.getElementById('nav-user-container');
    this.notifBtn = document.getElementById('nav-btn-notif');
    this.notifBadge = document.getElementById('notif-unread-count');
    this.currentTab = 'games';

    // Initialize Auth Modal
    this.authModal = new AuthModal(() => {
      this.refreshCurrentView();
      this.updateNotificationsCount();
    });
    this.authModal.render();

    // Initialize Views
    this.views = {
      games: new GamesView(this.mountPoint),
      rooms: new RoomsView(this.mountPoint, () => this.authModal.show()),
      dm: new DmView(this.mountPoint, () => this.authModal.show()),
      admin: new AdminView(this.mountPoint, () => this.authModal.show()),
      notifications: new NotificationsView(this.mountPoint)
    };
  }

  async init() {
    this.setupNavigation();
    this.setupAuthStateListener();

    // Check session login state
    try {
      await authState.checkSession();
    } catch {
      // not logged in
    }

    this.renderUserNav();
    this.updateNotificationsCount();

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
    const navButtons = document.querySelectorAll('.nav-tab-btn');
    navButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        window.location.hash = `#${tab}`;
      });
    });

    if (this.notifBtn) {
      this.notifBtn.addEventListener('click', () => {
        window.location.hash = '#notifications';
      });
    }
  }

  setupAuthStateListener() {
    authState.subscribe(() => {
      this.renderUserNav();
      this.updateNotificationsCount();
      this.refreshCurrentView();
    });
  }

  handleRouting() {
    let hash = window.location.hash.replace('#', '') || 'games';
    if (!this.views[hash]) {
      hash = 'games';
    }
    this.currentTab = hash;

    // Update active nav button
    document.querySelectorAll('.nav-tab-btn').forEach(btn => {
      if (btn.dataset.tab === hash) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    this.refreshCurrentView();
  }

  async refreshCurrentView() {
    const view = this.views[this.currentTab];
    if (view && typeof view.render === 'function') {
      await view.render();
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
              ${user.role}
            </span>
            <strong style="color: var(--color-text-main); font-size: 0.85rem;">${user.name}</strong>
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
          toast.info('Đã đăng xuất!');
        } catch (err) {
          toast.error(err.message || 'Lỗi đăng xuất');
        }
      };
    } else {
      this.userContainer.innerHTML = `
        <button id="btn-open-login" class="btn btn-primary" style="padding: 7px 16px; font-size: 0.85rem;">
          Đăng nhập / Đổi vai trò
        </button>
      `;

      this.userContainer.querySelector('#btn-open-login').onclick = () => {
        this.authModal.show();
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
      const unreadList = res.data || [];
      const count = unreadList.length;

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

document.addEventListener('DOMContentLoaded', () => {
  const app = new App();
  app.init();
});
