import { authState } from '../api/auth-state.js';
import { api } from '../api/api-client.js';
import { toast } from './toast.js';
import { escapeHtml } from './sanitize.js';

export function initSharedChrome(activeKey = 'home') {
  renderRoleDemoBar(activeKey);
  renderSiteHeader(activeKey);
  renderSiteFooter();
  setupDemoBarEvents();
  setupHeaderEvents(activeKey);
}

export function renderRoleDemoBar(activeKey) {
  const demoBarEl = document.getElementById('role-demo-bar');
  if (!demoBarEl) return;
  demoBarEl.style.display = 'none';
  demoBarEl.innerHTML = '';
}

export function renderSiteHeader(activeKey) {
  const headerEl = document.getElementById('app-site-header');
  if (!headerEl) return;

  const user = authState.getUser();
  const isAdmin = Boolean(user && user.role === 'ADMIN');
  const isDm = Boolean(user && (user.role === 'DM' || user.role === 'ADMIN'));

  headerEl.innerHTML = `
    <div class="container header-inner" style="display: flex; align-items: center; justify-content: space-between; height: 72px; gap: 16px;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button id="nav-hamburger-btn" class="mobile-nav-toggle" aria-label="Mở menu điều hướng" style="background: none; border: 1px solid var(--gold-border, #d4af37); color: var(--gold); border-radius: 4px; padding: 6px 10px; font-size: 1.1rem; cursor: pointer; display: none;">
          <span id="nav-hamburger-icon">☰</span>
        </button>

        <a href="/index.html" class="brand-logo" title="Kịch Bản Sát V16" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
          <img src="/src/images/logo.png" alt="Kịch Bản Sát" style="height: 48px; width: auto; object-fit: contain; filter: drop-shadow(0 0 6px rgba(212,175,55,0.3));" />
        </a>
      </div>

      <nav class="main-nav" id="main-nav" style="display: flex; align-items: center; gap: 8px;">
        <a href="/index.html" id="nav-btn-home" class="nav-tab-btn ${activeKey === 'home' ? 'active' : ''}" data-tab="home" style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 0.9rem; text-decoration: none; color: ${activeKey === 'home' ? 'var(--gold)' : 'var(--text-secondary)'}; font-weight: ${activeKey === 'home' ? '700' : '500'};">
          <span>🏠</span> Trang Chủ
        </a>
        <a href="/admin.html" id="nav-btn-admin" class="nav-tab-btn ${activeKey === 'admin' ? 'active' : ''}" data-tab="admin" style="${isAdmin || activeKey === 'admin' ? 'display: flex;' : 'display: none;'} align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 0.9rem; text-decoration: none; color: ${activeKey === 'admin' ? 'var(--gold)' : 'var(--text-secondary)'}; font-weight: ${activeKey === 'admin' ? '700' : '500'};">
          <span>🛡️</span> Trang ADMIN
        </a>
        <a href="/dm.html" id="nav-btn-dm" class="nav-tab-btn ${activeKey === 'dm' ? 'active' : ''}" data-tab="dm" style="${isDm || activeKey === 'dm' ? 'display: flex;' : 'display: none;'} align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 0.9rem; text-decoration: none; color: ${activeKey === 'dm' ? 'var(--gold)' : 'var(--text-secondary)'}; font-weight: ${activeKey === 'dm' ? '700' : '500'};">
          <span>📜</span> Trang DM
        </a>
        <a href="/player.html" id="nav-btn-rooms" class="nav-tab-btn ${activeKey === 'player' ? 'active' : ''}" data-tab="rooms" style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 0.9rem; text-decoration: none; color: ${activeKey === 'player' ? 'var(--gold)' : 'var(--text-secondary)'}; font-weight: ${activeKey === 'player' ? '700' : '500'};">
          <span>🎲</span> Trang PLAYER
        </a>
        <a href="/index.html#games" id="nav-btn-games" class="nav-tab-btn ${activeKey === 'games' ? 'active' : ''}" data-tab="games" style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 0.9rem; text-decoration: none; color: ${activeKey === 'games' ? 'var(--gold)' : 'var(--text-secondary)'}; font-weight: ${activeKey === 'games' ? '700' : '500'};">
          <span>🎭</span> Kịch Bản
        </a>
      </nav>

      <div class="nav-actions" style="display: flex; align-items: center; gap: 12px;">
        <button id="nav-btn-notif" class="notif-btn" title="Thông báo hệ thống" style="background: rgba(255,255,255,0.05); border: 1px solid var(--color-border); color: var(--gold); border-radius: 6px; padding: 7px 12px; cursor: pointer; position: relative;">
          <span>🔔</span>
          <span id="notif-unread-count" class="notif-badge" style="display: none; position: absolute; top: -4px; right: -4px; background: var(--crimson-bright); color: white; font-size: 0.65rem; padding: 2px 5px; border-radius: 999px;">0</span>
        </button>

        <div id="nav-user-container">
          ${user ? `
            <div style="display: flex; align-items: center; gap: 8px;">
              <div class="user-pill" id="btn-user-profile" style="display: flex; align-items: center; gap: 8px; background: var(--bg-surface); border: 1px solid var(--gold-border); padding: 5px 12px; border-radius: 999px; cursor: pointer;">
                <span class="badge ${user.role === 'ADMIN' ? 'badge-18' : user.role === 'DM' ? 'badge-gold' : 'badge-green'}" style="font-size: 0.72rem; padding: 2px 6px;">
                  ${escapeHtml(user.role)}
                </span>
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">${escapeHtml(user.name)}</span>
              </div>
              <button id="btn-quick-logout" class="btn btn-ghost btn-sm" title="Đăng xuất" style="padding: 4px 10px; font-size: 0.78rem;">Thoát</button>
            </div>
          ` : `
            <button id="btn-open-login" class="btn btn-gold btn-sm" style="padding: 7px 16px; font-size: 0.85rem;">
              Đăng nhập / Đổi vai trò
            </button>
          `}
        </div>
      </div>
    </div>

    <!-- Mobile Drawer -->
    <div id="mobile-nav-drawer" class="mobile-nav-drawer" style="display: none; flex-direction: column; background: #12100e; border-bottom: 1px solid var(--gold-border); padding: 12px 20px; gap: 8px;">
      <a href="/index.html#home" class="mobile-nav-link ${activeKey === 'home' ? 'active' : ''}" data-tab="home" style="padding: 8px 0; color: var(--text-primary); text-decoration: none;">🏠 Trang Chủ</a>
      <a href="/admin.html" id="mobile-nav-admin" class="mobile-nav-link ${activeKey === 'admin' ? 'active' : ''}" data-tab="admin" style="${isAdmin || activeKey === 'admin' ? 'display: block;' : 'display: none;'} padding: 8px 0; color: var(--text-primary); text-decoration: none;">🛡️ Trang ADMIN</a>
      <a href="/dm.html" id="mobile-nav-dm" class="mobile-nav-link ${activeKey === 'dm' ? 'active' : ''}" data-tab="dm" style="${isDm || activeKey === 'dm' ? 'display: block;' : 'display: none;'} padding: 8px 0; color: var(--text-primary); text-decoration: none;">📜 Trang DM</a>
      <a href="/player.html" class="mobile-nav-link ${activeKey === 'player' ? 'active' : ''}" data-tab="rooms" style="padding: 8px 0; color: var(--text-primary); text-decoration: none;">🎲 Trang PLAYER</a>
      <a href="/index.html#games" class="mobile-nav-link" data-tab="games" style="padding: 8px 0; color: var(--text-primary); text-decoration: none;">🎭 Khám Phá Kịch Bản</a>
    </div>
  `;
}

export function renderSiteFooter() {
  const footerEl = document.getElementById('app-footer');
  if (!footerEl) return;

  footerEl.innerHTML = `
    <footer class="app-footer kbs-footer-unified" style="background: #080808; border-top: 1px solid rgba(212, 175, 55, 0.25); padding: 48px 0 24px; margin-top: 60px;">
      <div class="container">
        <div class="footer-top kbs-footer-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 32px; margin-bottom: 32px;">
          <div class="footer-col">
            <h4 style="display: flex; align-items: center; gap: 8px; font-family: var(--font-display); font-size: 1.25rem; color: var(--gold); margin-bottom: 12px;">
              <span>🎭</span> Kịch Bản Sát V16
            </h4>
            <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 12px;">
              Nền tảng đặt lịch và ghép phòng chơi Kịch Bản Sát (Jubensha / Murder Mystery) chuyên nghiệp hàng đầu TP.HCM.
              Không gian phòng kín nhập vai chân thực, kết nối thám tử và quy trình minh bạch tuyệt đối.
            </p>
            <p style="font-size: 0.8rem; color: var(--gold);">
              Chuẩn kiến trúc Monolithic MVC &bull; Clean Architecture &bull; MySQL 8.4
            </p>
          </div>

          <div class="footer-col">
            <h4 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--gold); margin-bottom: 12px;">Chi Nhánh Hoạt Động</h4>
            <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 10px; line-height: 1.5;">
              <strong style="color: #f8fafc;">📍 Chi nhánh Bình Thạnh:</strong><br>
              36/52 Nguyễn Gia Trí, P. 25, Q. Bình Thạnh, TP.HCM<br>
              <span style="color: var(--gold);">Hotline: 091.569.0033</span>
            </p>
            <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5;">
              <strong style="color: #f8fafc;">📍 Chi nhánh Quận 1:</strong><br>
              123 Nguyễn Huệ, P. Bến Nghé, Q.1, TP.HCM<br>
              <span style="color: var(--gold);">Hotline: 0901 234 567</span>
            </p>
          </div>

          <div class="footer-col">
            <h4 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--gold); margin-bottom: 12px;">4 Trang Hệ Thống</h4>
            <ul class="footer-links" style="list-style: none; padding: 0; margin: 0; font-size: 0.88rem; line-height: 1.9;">
              <li><a href="/index.html" style="color: var(--text-secondary); text-decoration: none;">🏠 Trang chủ Kịch Bản Sát</a></li>
              <li><a href="/admin.html" style="color: var(--text-secondary); text-decoration: none;">🛡️ Trang ADMIN (Quản trị vận hành)</a></li>
              <li><a href="/dm.html" style="color: var(--text-secondary); text-decoration: none;">📜 Trang DM (Dành cho Quản trò)</a></li>
              <li><a href="/player.html" style="color: var(--text-secondary); text-decoration: none;">🎲 Trang PLAYER (Sảnh ghép phòng & Hồ sơ)</a></li>
              <li><a href="/index.html#games" style="color: var(--text-secondary); text-decoration: none;">🎭 Kho 42 kịch bản trinh thám</a></li>
            </ul>
          </div>

          <div class="footer-col">
            <h4 style="font-family: var(--font-display); font-size: 1.15rem; color: var(--gold); margin-bottom: 12px;">Chính Sách & Quy Chuẩn V16</h4>
            <ul class="footer-links" style="list-style: none; padding: 0; margin: 0; font-size: 0.88rem; line-height: 1.9;">
              <li><span style="color:#5CD68D;">✔</span> Cọc cố định: 50.000đ / người</li>
              <li><span style="color:#5CD68D;">✔</span> Hoàn cọc 100% trước 24 giờ</li>
              <li><span style="color:#5CD68D;">✔</span> Ưu đãi tính tự động tại Backend</li>
              <li><span style="color:#5CD68D;">✔</span> Không cộng dồn khuyến mãi</li>
              <li><span style="color:#5CD68D;">✔</span> Giờ mở cửa: 09:00 - 23:00</li>
            </ul>
          </div>
        </div>

        <div style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.82rem; color: var(--text-dim);">
          <div>
            &copy; 2026 <strong>Kịch Bản Sát V16 Platform</strong>. Tất cả quyền được bảo lưu.
          </div>
          <div>
            Tuân thủ API Convention V16 &bull; Idempotency Keys &bull; Cọc cố định 50k &bull; Quy tắc vàng 24 giờ
          </div>
        </div>
      </div>
    </footer>
  `;
}

function setupDemoBarEvents() {
  document.querySelectorAll('[data-goto]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      // standard anchor navigation is allowed
    });
  });
}

function setupHeaderEvents(activeKey) {
  const hamburgerBtn = document.getElementById('nav-hamburger-btn');
  const drawer = document.getElementById('mobile-nav-drawer');
  const icon = document.getElementById('nav-hamburger-icon');

  if (hamburgerBtn && drawer) {
    hamburgerBtn.style.display = window.innerWidth <= 860 ? 'block' : 'none';
    window.addEventListener('resize', () => {
      hamburgerBtn.style.display = window.innerWidth <= 860 ? 'block' : 'none';
    });

    hamburgerBtn.addEventListener('click', () => {
      const isVisible = drawer.style.display === 'flex';
      drawer.style.display = isVisible ? 'none' : 'flex';
      if (icon) icon.textContent = isVisible ? '☰' : '✕';
    });
  }

  const logoutBtn = document.getElementById('btn-quick-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try {
        await authState.logout();
        toast.info('Đã đăng xuất.');
        window.location.reload();
      } catch (err) {
        console.error(err);
      }
    });
  }
}
