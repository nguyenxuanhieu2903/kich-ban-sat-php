import { store } from './store.js';
import { api } from './api.js';

function escHtml(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

export function initChrome(activeKey = '') {
  const isRoot = !window.location.pathname.includes('/pages/');
  const prefix = isRoot ? './' : '../';
  const pagesPrefix = isRoot ? './pages/' : './';
  const homeAnchorPrefix = isRoot ? '' : `${prefix}index.html`;

  const role = store.state.currentRole;

  /* =========================================================
     1. HEADER CHÍNH
     ========================================================= */
  const headerEl = document.getElementById('app-header');
  if (headerEl) {
    headerEl.innerHTML = `
      <header class="site-header">
        <div class="container header-inner">
          <a href="${prefix}index.html" class="brand-logo" title="Kịch Bản Sát V16">
            <img src="${prefix}src/assets/images/logo.png" alt="Kịch Bản Sát" class="header-logo-img" />
          </a>

          <nav class="main-nav" id="main-nav">
            <a href="${prefix}index.html" class="${activeKey === 'home' ? 'active' : ''}">Trang chủ</a>
            <a href="${homeAnchorPrefix}#phong-dang-tuyen" class="${activeKey === 'lobby' ? 'active' : ''}">Phòng đang tuyển</a>
            <a href="${homeAnchorPrefix}#kho-kich-ban" class="${activeKey === 'scenarios' ? 'active' : ''}">Kho kịch bản</a>
            <a href="${pagesPrefix}info.html" class="${activeKey === 'policies' || activeKey === 'info' ? 'active' : ''}">Quy định &amp; Giá vé</a>
            <a href="#app-footer" class="${activeKey === 'contact' ? 'active' : ''}">Liên hệ</a>
            ${role === 'dm' ? `<a href="${pagesPrefix}dm-portal.html" class="nav-special ${activeKey === 'dm' ? 'active' : ''}">Cổng Quản Trò</a>` : ''}
            ${role === 'admin' ? `<a href="${pagesPrefix}admin-portal.html" class="nav-special ${activeKey === 'admin' ? 'active' : ''}">Trang Quản Trị</a>` : ''}
            ${role !== 'guest' ? `
              ${role === 'player' ? `<a href="${pagesPrefix}create-room.html" class="mobile-only-nav nav-special">+ Tạo phòng mới</a>` : ''}
              <a href="${pagesPrefix}profile.html" class="mobile-only-nav">Hồ sơ: ${escHtml(store.state.currentUser.name)}</a>
              <a href="#" id="kbs-mobile-logout" class="mobile-only-nav">Đăng xuất</a>
            ` : `
              <a href="${pagesPrefix}auth.html#login" class="mobile-only-nav">Đăng nhập</a>
              <a href="${pagesPrefix}auth.html#register" class="mobile-only-nav nav-special">Đăng ký tài khoản</a>
            `}
          </nav>

          <div class="header-actions">
            ${role === 'guest' ? `
              <a href="${pagesPrefix}auth.html#login" class="btn btn-ghost btn-sm header-desktop-only">Đăng nhập</a>
              <a href="${pagesPrefix}auth.html#register" class="btn btn-gold btn-sm">Đăng ký</a>
            ` : `
              ${role === 'player' ? `<a href="${pagesPrefix}create-room.html" class="btn btn-gold btn-sm header-desktop-only">+ Tạo phòng</a>` : ''}
              <button type="button" class="btn btn-ghost btn-sm" id="kbs-notif-link" title="Xem thông báo">Thông báo <span id="kbs-notif-badge" style="display:none;background:#8B1E1E;color:#fff;border-radius:999px;padding:1px 6px;font-size:11px;margin-left:4px;">0</span></button>
              <a href="${pagesPrefix}profile.html" class="btn btn-ghost btn-sm header-desktop-only">${escHtml(store.state.currentUser.name)}</a>
              <button type="button" id="kbs-logout-btn" class="btn btn-ghost btn-sm header-desktop-only">Đăng xuất</button>
            `}
            <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Menu">☰</button>
          </div>
        </div>
      </header>
    `;

    const logoutBtn = document.getElementById('kbs-logout-btn');
    if (logoutBtn) {
      let cachedNotifs = [];
      const badge = document.getElementById('kbs-notif-badge');
      const refreshNotifs = async () => {
        try {
          const res = await api.get('/notifications');
          cachedNotifs = Array.isArray(res) ? res : (res?.notifications || res?.items || []);
          const unread = cachedNotifs.filter((n) => !n.is_read).length;
          if (badge) {
            badge.textContent = String(unread);
            badge.style.display = unread > 0 ? 'inline-block' : 'none';
          }
        } catch { /* ignore */ }
      };
      refreshNotifs();

      const notifBtn = document.getElementById('kbs-notif-link');
      if (notifBtn) {
        const scopeBadgeStyle = (scope) => {
          if (scope === 'BROADCAST_ALL') return 'background:#1E3A5F;color:#fff;';
          if (scope === 'BROADCAST_PLAYER') return 'background:#1A5936;color:#fff;';
          if (scope === 'BROADCAST_DM') return 'background:#6B3FA0;color:#fff;';
          return 'background:#8B1E1E;color:#fff;';
        };

        const resolveNavHref = (rawUrl) => {
          const u = String(rawUrl || '/pages/lobby.html').trim();
          if (u.startsWith('/pages/')) return `${pagesPrefix}${u.slice('/pages/'.length)}`;
          if (u.startsWith('/')) return `${prefix}${u.slice(1)}`;
          return u;
        };

        const openNotifModal = async () => {
          await refreshNotifs();
          let modal = document.getElementById('kbs-notif-modal');
          if (!modal) {
            modal = document.createElement('div');
            modal.id = 'kbs-notif-modal';
            modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);display:flex;align-items:center;justify-content:center;padding:16px;';
            document.body.appendChild(modal);
          }
          const unreadCount = cachedNotifs.filter((n) => !n.is_read).length;
          modal.innerHTML = `
            <div style="background:#FAF6EE;color:#1C1917;max-width:560px;width:100%;max-height:78vh;overflow:auto;border-radius:12px;padding:20px;border:1px solid #C5A059;box-shadow:0 18px 40px rgba(0,0,0,0.35);">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;border-bottom:1px solid rgba(0,0,0,0.1);padding-bottom:10px;">
                <strong style="font-size:1.1rem;color:#8B1E1E;">Thông Báo Của Bạn</strong>
                <div style="display:flex;gap:8px;align-items:center;">
                  ${unreadCount > 0 ? `<button type="button" id="kbs-notif-read-all" class="btn btn-gold btn-sm">Đọc tất cả</button>` : ''}
                  <button type="button" id="kbs-notif-close" class="btn btn-ghost btn-sm">Đóng</button>
                </div>
              </div>
              <div style="font-size:0.78rem;color:#6B6258;margin-bottom:10px;">Nhấn trực tiếp vào bất kỳ thông báo nào để mở đúng trang liên quan.</div>
              ${cachedNotifs.length ? cachedNotifs.slice(0, 20).map((n) => `
                <div data-notif-open="${n.id}" data-notif-url="${escHtml(n.target_url || '/pages/lobby.html')}" data-notif-unread="${n.is_read ? '0' : '1'}"
                     style="padding:11px 12px;margin-bottom:8px;border-radius:8px;cursor:pointer;border:1px solid ${n.is_read ? 'rgba(0,0,0,0.08)' : 'rgba(139,30,30,0.28)'};background:${n.is_read ? 'rgba(255,255,255,0.55)' : 'rgba(197,160,89,0.16)'};transition:background 0.15s ease;">
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="display:inline-block;padding:1px 7px;border-radius:999px;font-size:0.7rem;font-weight:700;${scopeBadgeStyle(n.scope)}">${escHtml(n.scope_label || 'Riêng bạn')}</span>
                    <span style="font-size:0.74rem;color:#78716C;">${n.is_read ? 'Đã đọc' : 'Mới'}</span>
                  </div>
                  <div style="font-weight:700;font-size:0.93rem;color:#1C1917;">${escHtml(n.title || 'Thông báo')}</div>
                  <div style="font-size:0.84rem;color:#524B42;margin-top:3px;line-height:1.45;">${escHtml(n.content || n.message || '')}</div>
                  <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;">
                    <span style="color:#8B1E1E;font-weight:700;">${escHtml(n.action_label || 'Mở chi tiết')} &rarr;</span>
                    ${!n.is_read ? `<button type="button" class="btn btn-ghost btn-sm" data-read-notif="${n.id}" style="padding:2px 8px;font-size:0.72rem;">Đánh dấu đã đọc</button>` : ''}
                  </div>
                </div>
              `).join('') : '<p style="color:#524B42;text-align:center;padding:16px 0;">Hiện chưa có thông báo mới nào dành cho bạn.</p>'}
            </div>
          `;
          modal.style.display = 'flex';
          modal.querySelector('#kbs-notif-close')?.addEventListener('click', () => { modal.style.display = 'none'; });
          modal.querySelector('#kbs-notif-read-all')?.addEventListener('click', async () => {
            try { await api.post('/notifications/read-all', {}); } catch { /* ignore */ }
            await openNotifModal();
          });
          modal.addEventListener('click', (ev) => { if (ev.target === modal) modal.style.display = 'none'; });

          modal.querySelectorAll('[data-read-notif]').forEach((b) => {
            b.addEventListener('click', async (ev) => {
              ev.stopPropagation();
              try { await api.post(`/notifications/${b.getAttribute('data-read-notif')}/read`, {}); } catch { /* ignore */ }
              await openNotifModal();
            });
          });

          modal.querySelectorAll('[data-notif-open]').forEach((card) => {
            card.addEventListener('click', async () => {
              const nid = card.getAttribute('data-notif-open');
              const url = resolveNavHref(card.getAttribute('data-notif-url'));
              if (card.getAttribute('data-notif-unread') === '1' && nid) {
                try { await api.post(`/notifications/${nid}/read`, {}); } catch { /* ignore */ }
              }
              modal.style.display = 'none';
              window.location.href = url;
            });
          });
        };

        notifBtn.addEventListener('click', openNotifModal);
      }

      const doLogout = async (ev) => {
        if (ev) ev.preventDefault();
        try { await api.post('/auth/logout', {}); } catch { /* ignore */ }
        if (typeof store.setRole === 'function') {
          store.setRole('guest');
        } else {
          store.state.currentRole = 'guest';
          try { localStorage.setItem('KBS_V16_STATE_V1', JSON.stringify(store.state)); } catch { /* ignore */ }
        }
        window.location.href = `${prefix}index.html`;
      };
      logoutBtn.addEventListener('click', doLogout);
      document.getElementById('kbs-mobile-logout')?.addEventListener('click', doLogout);
    }

    const mobileBtn = document.getElementById('mobile-menu-btn');
    const nav = document.getElementById('main-nav');
    if (mobileBtn && nav) {
      mobileBtn.addEventListener('click', () => nav.classList.toggle('open'));
      nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => nav.classList.remove('open'));
      });
    }
  }

  /* =========================================================
     2. FOOTER 4 CỘT THỐNG NHẤT KÈM BẢN ĐỒ GOOGLE MAPS
     ========================================================= */
  const footerEl = document.getElementById('app-footer');
  if (footerEl) {
    footerEl.innerHTML = `
      <style>
        .header-logo-img {
          height: 54px;
          width: auto;
          object-fit: contain;
          display: block;
          filter: drop-shadow(0 0 6px rgba(245, 242, 235, 0.22));
          transition: transform 0.2s ease;
        }
        .header-logo-img:hover {
          transform: scale(1.04);
        }
        .footer-logo-img {
          height: 92px;
          width: auto;
          object-fit: contain;
          display: block;
          margin-bottom: 12px;
          filter: drop-shadow(0 0 8px rgba(245, 242, 235, 0.2));
        }
        .kbs-footer-unified {
          background: #080808;
          border-top: 1px solid rgba(212, 175, 55, 0.22);
          padding: 52px 0 24px;
          margin-top: 72px;
        }
        .kbs-footer-row {
          display: grid;
          grid-template-columns: 0.9fr 1.35fr 0.85fr 1.15fr;
          gap: 28px;
          align-items: start;
          padding-bottom: 36px;
          border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .kbs-footer-socials {
          display: flex;
          gap: 10px;
          margin-top: 18px;
        }
        .kbs-social-circle {
          width: 38px;
          height: 38px;
          border-radius: 50%;
          border: 1px solid rgba(245, 242, 235, 0.35);
          display: grid;
          place-items: center;
          font-size: 0.85rem;
          font-weight: 700;
          color: var(--paper-cream);
          transition: 0.2s;
        }
        .kbs-social-circle:hover {
          border-color: var(--gold);
          background: var(--gold);
          color: var(--ink-black);
        }
        .kbs-footer-map {
          width: 100%;
          height: 225px;
          border-radius: 8px;
          overflow: hidden;
          border: 1px solid rgba(212, 175, 55, 0.28);
        }
        .kbs-footer-map iframe {
          width: 100%;
          height: 100%;
          border: 0;
          display: block;
        }
        .kbs-footer-col-title {
          font-family: var(--font-display);
          font-size: 1.05rem;
          color: var(--gold);
          padding-bottom: 8px;
          margin-bottom: 14px;
          border-bottom: 1px solid rgba(212, 175, 55, 0.35);
          display: inline-block;
        }
        .kbs-footer-links {
          list-style: none;
          display: grid;
          gap: 10px;
          font-size: 0.9rem;
          color: var(--text-secondary);
        }
        .kbs-footer-links a:hover { color: var(--gold); }
        @media (max-width: 1024px) {
          .kbs-footer-row { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
          .kbs-footer-row { grid-template-columns: 1fr; }
        }
      </style>

      <footer class="kbs-footer-unified" id="lien-he">
        <div class="container">
          <div class="kbs-footer-row">
            <!-- CỘT 1: LOGO & MẠNG XÃ HỘI -->
            <div>
              <a href="${prefix}index.html">
                <img src="${prefix}src/assets/images/logo.png" alt="Kịch Bản Sát" class="footer-logo-img" />
              </a>
              <p class="text-muted" style="font-size:0.88rem;line-height:1.6;">
                Không gian nhập vai phá án kịch bản sát chuyên nghiệp tại TP.HCM.
              </p>
              <div class="kbs-footer-socials">
                <a href="https://www.facebook.com/share/1MTaRA71Fa/?mibextid=wwXIfr" class="kbs-social-circle" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16">
                    <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/>
                  </svg>
                </a>
                <a href="https://www.instagram.com/cafex.kichbansat.vn" class="kbs-social-circle" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16">
                    <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/>
                  </svg>
                </a>
                <a href="https://www.tiktok.com/@kichbansat.hcm" class="kbs-social-circle" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tiktok" viewBox="0 0 16 16">
                    <path d="M9 0h1.98c.144.715.54 1.617 1.235 2.512C12.895 3.389 13.797 4 15 4v2c-1.753 0-3.07-.814-4-1.829V11a5 5 0 1 1-5-5v2a3 3 0 1 0 3 3z"/>
                  </svg>
                </a>
                <a href="https://www.youtube.com/@cafex.kichbansat-hcm" class="kbs-social-circle" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-youtube" viewBox="0 0 16 16">
                    <path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z"/>
                  </svg>
                </a>
              </div>
            </div>
            <!-- CỘT 2: GOOGLE MAPS NẰM NGAY GIỮA -->
            <div class="kbs-footer-map">
              <iframe
                title="Bản đồ Kịch Bản Sát - 36/52 Nguyễn Gia Trí, Bình Thạnh"
                src="https://maps.google.com/maps?q=36/52%20Nguy%E1%BB%85n%20Gia%20Tr%C3%AD,%20Ph%C6%B0%E1%BB%9Dng%2025,%20B%C3%ACnh%20Th%E1%BA%A1nh,%20H%E1%BB%93%20Ch%C3%AD%20Minh&t=&z=16&ie=UTF8&iwloc=&output=embed"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
              </iframe>
            </div>

            <!-- CỘT 3: ĐIỀU HƯỚNG NHANH -->
            <div>
              <h4 class="kbs-footer-col-title">Điều Hướng Nhanh</h4>
              <ul class="kbs-footer-links">
                <li><a href="${prefix}index.html">Trang chủ</a></li>
                <li><a href="${homeAnchorPrefix}#phong-dang-tuyen">Phòng đang tuyển</a></li>
                <li><a href="${homeAnchorPrefix}#kho-kich-ban">Kho kịch bản</a></li>
                <li><a href="${pagesPrefix}info.html">Quy định &amp; Giá vé</a></li>
                <li><a href="${pagesPrefix}profile.html">Hồ sơ cá nhân</a></li>
              </ul>
            </div>

            <!-- CỘT 4: THÔNG TIN QUÁN -->
            <div>
              <h4 class="kbs-footer-col-title">Liên Hệ Quán</h4>
              <ul class="kbs-footer-links">
                <li>Địa chỉ: 36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM</li>
                <li>Giờ mở cửa: 09:00 – 23:00 mỗi ngày</li>
                <li>Đường dây nóng: <a href="tel:0915690033" class="text-gold"><strong>091.569.0033</strong></a></li>
                <li>Hợp tác: <a href="tel:0989896833" class="text-gold"><strong>0989.896.833 (Chị Yến)</strong></a></li>
              </ul>
            </div>
          </div>

          <div class="footer-bottom">
            <span>© 2026 KỊCH BẢN SÁT — 36/52 Nguyễn Gia Trí, Bình Thạnh, TP.HCM.</span>
            <span>Đánh giá 100% đề xuất (59 lượt) • 2,2K người theo dõi</span>
          </div>
        </div>
      </footer>
    `;
  }
}