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
    <footer class="app-footer kbs-footer-unified" style="background: #080808; border-top: 1px solid rgba(212, 175, 55, 0.25); padding: 48px 1.5rem 24px; margin-top: 60px;">
      <div class="kbs-footer-row footer-top" style="display: grid; grid-template-columns: 0.95fr 1.35fr 0.85fr 1.15fr; gap: 28px; align-items: start; max-width: 1200px; margin: 0 auto; padding-bottom: 28px;">
        <!-- CỘT 1: LOGO & MẠNG XÃ HỘI -->
        <div class="footer-col">
          <a href="/#home">
            <img src="/src/images/logo.png" alt="Kịch Bản Sát" class="footer-logo-img" style="height: 64px; width: auto; object-fit: contain; margin-bottom: 12px;" />
          </a>
          <p style="font-size: 0.88rem; color: var(--color-text-muted, #94a3b8); line-height: 1.6; margin-bottom: 14px;">
            Không gian nhập vai phá án Murder Mystery &amp; TTRPG chuyên nghiệp tại TP.HCM.
          </p>
          <div class="kbs-footer-socials" style="display: flex; gap: 10px;">
            <a href="https://www.facebook.com/profile.php?id=61591144511514" target="_blank" rel="noopener noreferrer" class="kbs-social-circle" title="Facebook">f</a>
            <a href="https://www.instagram.com/cafex.kichbansat.vn?utm_source=ig_web_button_share_sheet&gwpx=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener noreferrer" class="kbs-social-circle" title="Instagram">IG</a>
            <a href="https://www.tiktok.com/@kichbansat.hcm?is_from_webapp=1&sender_device=pc" target="_blank" rel="noopener noreferrer" class="kbs-social-circle" title="TikTok">♪</a>
          </div>
        </div>

        <!-- CỘT 2: GOOGLE MAPS NHÚNG TRỰC TIẾP -->
        <div class="footer-col">
          <div class="kbs-footer-map" style="width: 100%; height: 200px; border-radius: 8px; overflow: hidden; border: 1px solid rgba(212, 175, 55, 0.28);">
            <iframe
              title="Bản đồ Kịch Bản Sát - 36/52 Nguyễn Gia Trí, Bình Thạnh"
              src="https://maps.google.com/maps?q=36/52%20Nguy%E1%BB%85n%20Gia%20Tr%C3%AD,%20Ph%C6%B0%E1%BB%9Dng%2025,%20B%C3%ACnh%20Th%E1%BA%A1nh,%20H%E1%BB%93%20Ch%C3%AD%20Minh&t=&z=16&ie=UTF8&iwloc=&output=embed"
              loading="lazy"
              style="width: 100%; height: 100%; border: 0; display: block;"
              referrerpolicy="no-referrer-when-downgrade">
            </iframe>
          </div>
        </div>

        <!-- CỘT 3: ĐIỀU HƯỚNG NHANH -->
        <div class="footer-col">
          <h4 class="kbs-footer-col-title" style="font-family: var(--font-display, serif); font-size: 1.05rem; font-weight: 700; color: var(--gold, #d4af37); padding-bottom: 8px; margin: 0 0 14px 0; border-bottom: 1px solid rgba(212, 175, 55, 0.35); display: inline-block;">Điều Hướng Nhanh</h4>
          <ul class="kbs-footer-links" style="list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; font-size: 0.9rem;">
            <li><a href="/#home" style="color: var(--color-text-muted, #94a3b8); text-decoration: none;">Trang chủ</a></li>
            <li><a href="/#games" style="color: var(--color-text-muted, #94a3b8); text-decoration: none;">Kho kịch bản</a></li>
            <li><a href="/#rooms" style="color: var(--color-text-muted, #94a3b8); text-decoration: none;">Phòng đang ghép</a></li>
            <li><a href="/#home" style="color: var(--color-text-muted, #94a3b8); text-decoration: none;">Quy định &amp; Giá vé</a></li>
          </ul>
        </div>

        <!-- CỘT 4: LIÊN HỆ QUÁN (1 CHI NHÁNH DUY NHẤT) -->
        <div class="footer-col">
          <h4 class="kbs-footer-col-title" style="font-family: var(--font-display, serif); font-size: 1.05rem; font-weight: 700; color: var(--gold, #d4af37); padding-bottom: 8px; margin: 0 0 14px 0; border-bottom: 1px solid rgba(212, 175, 55, 0.35); display: inline-block;">Liên Hệ Quán</h4>
          <ul class="kbs-footer-links" style="list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; font-size: 0.9rem;">
            <li>📍 36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM</li>
            <li>🕒 Mở cửa: 09:00 – 23:00 mỗi ngày</li>
            <li>📞 Hotline: <a href="tel:0915690033" style="color: var(--gold, #d4af37); font-weight: 700; text-decoration: none;">091.569.0033</a></li>
            <li>🤝 Hợp tác: <a href="tel:0989896833" style="color: var(--gold, #d4af37); font-weight: 700; text-decoration: none;">0989.896.833 (Mrs Yến)</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; max-width: 1200px; margin: 0 auto; font-size: 0.85rem; color: var(--color-text-muted, #94a3b8);">
        <div>
          &copy; 2026 <strong>KỊCH BẢN SÁT V16</strong> – 36/52 Nguyễn Gia Trí, Bình Thạnh, TP.HCM.
        </div>
        <div>
          Đánh giá 100% đề xuất (59 lượt) &bull; 2,2K người theo dõi
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
