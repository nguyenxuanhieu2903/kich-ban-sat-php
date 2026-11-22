import { store } from './store.js';

export function initChrome(activeKey = '') {
  const isRoot = !window.location.pathname.includes('/pages/');
  const prefix = isRoot ? './' : '../';
  const pagesPrefix = isRoot ? './pages/' : './';

  const role = store.state.currentRole;

  /* =========================================================
     1. HEADER & THANH CHUYỂN NHANH 4 VAI TRÒ
     ========================================================= */
  const headerEl = document.getElementById('app-header');
  if (headerEl) {
    headerEl.innerHTML = `
      <header class="site-header">
        <div class="container header-inner">
          <a href="${prefix}index.html" class="brand-logo" title="Kịch Bản Sát V16">
            <img src="${prefix}src/images/logo.png" alt="Kịch Bản Sát" class="header-logo-img" />
          </a>

          <nav class="main-nav" id="main-nav">
            <a href="${prefix}index.html" class="${activeKey === 'home' ? 'active' : ''}">Trang chủ</a>
            <a href="${pagesPrefix}scenarios.html" class="${activeKey === 'scenarios' ? 'active' : ''}">Kho kịch bản</a>
            <a href="${pagesPrefix}lobby.html" class="${activeKey === 'lobby' ? 'active' : ''}">Phòng đang tuyển</a>
            <a href="${pagesPrefix}info.html#policies" class="${activeKey === 'policies' ? 'active' : ''}">Quy định &amp; Giá vé</a>
            <a href="#lien-he" class="${activeKey === 'contact' ? 'active' : ''}">Liên hệ</a>
            ${role === 'dm' ? `<a href="${pagesPrefix}dm-portal.html" class="nav-special ${activeKey === 'dm' ? 'active' : ''}">Cổng Quản Trò</a>` : ''}
            ${role === 'admin' ? `<a href="${pagesPrefix}admin-portal.html" class="nav-special ${activeKey === 'admin' ? 'active' : ''}">Quản Trị Viên</a>` : ''}
          </nav>

          <div class="header-actions">
            ${role === 'guest' ? `
              <a href="${pagesPrefix}auth.html#login" class="btn btn-ghost btn-sm">Đăng nhập</a>
              <a href="${pagesPrefix}auth.html#register" class="btn btn-gold btn-sm">Đăng ký</a>
            ` : `
              <a href="${pagesPrefix}create-room.html" class="btn btn-gold btn-sm">+ Tạo phòng</a>
              <a href="${pagesPrefix}profile.html" class="btn btn-ghost btn-sm">${store.state.currentUser.name}</a>
            `}
            <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Menu">☰</button>
          </div>
        </div>
      </header>
    `;

    headerEl.querySelectorAll('.role-pill').forEach(btn => {
      btn.addEventListener('click', () => {
        const nextRole = btn.dataset.role;
        store.setRole(nextRole);
        if (nextRole === 'dm') window.location.href = `${pagesPrefix}dm-portal.html`;
        else if (nextRole === 'admin') window.location.href = `${pagesPrefix}admin-portal.html`;
        else if (nextRole === 'player') window.location.href = `${pagesPrefix}profile.html`;
        else window.location.href = `${prefix}index.html`;
      });
    });

    const mobileBtn = document.getElementById('mobile-menu-btn');
    const nav = document.getElementById('main-nav');
    if (mobileBtn && nav) {
      mobileBtn.addEventListener('click', () => nav.classList.toggle('open'));
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
                <img src="${prefix}src/images/logo.png" alt="Kịch Bản Sát V16" class="footer-logo-img" />
              </a>
              <p class="text-muted" style="font-size:0.88rem;line-height:1.6;">
                Không gian nhập vai phá án Murder Mystery &amp; TTRPG chuyên nghiệp tại TP.HCM.
              </p>
              <div class="kbs-footer-socials">
                <a href="https://www.facebook.com" target="_blank" rel="noopener" class="kbs-social-circle" title="Facebook">f</a>
                <a href="https://www.instagram.com" target="_blank" rel="noopener" class="kbs-social-circle" title="Instagram">IG</a>
                <a href="https://www.tiktok.com" target="_blank" rel="noopener" class="kbs-social-circle" title="TikTok">♪</a>
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
                <li><a href="${pagesPrefix}scenarios.html">Kho 42 kịch bản</a></li>
                <li><a href="${pagesPrefix}lobby.html">Phòng đang tuyển</a></li>
                <li><a href="${pagesPrefix}info.html#policies">Quy định &amp; Giá vé</a></li>
                <li><a href="${pagesPrefix}dm-portal.html">Cổng Quản trò (DM)</a></li>
              </ul>
            </div>

            <!-- CỘT 4: THÔNG TIN QUÁN -->
            <div>
              <h4 class="kbs-footer-col-title">Liên Hệ Quán</h4>
              <ul class="kbs-footer-links">
                <li>📍 36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM</li>
                <li>🕒 Mở cửa: 09:00 – 23:00 mỗi ngày</li>
                <li>📞 Hotline: <a href="tel:0915690033" class="text-gold"><strong>091.569.0033</strong></a></li>
                <li>🤝 Hợp tác: <a href="tel:0989896833" class="text-gold"><strong>0989.896.833 (Mrs Yến)</strong></a></li>
              </ul>
            </div>
          </div>

          <div class="footer-bottom">
            <span>© 2026 KỊCH BẢN SÁT V16 — 36/52 Nguyễn Gia Trí, Bình Thạnh, TP.HCM.</span>
            <span>Đánh giá 100% đề xuất (59 lượt) • 2,2K người theo dõi</span>
          </div>
        </div>
      </footer>
    `;
  }
}