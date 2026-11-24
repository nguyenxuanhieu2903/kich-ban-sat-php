// src/pages-js/home.page.js
import { SCENARIOS } from '../data/scripts.data.js';
import { apiClient } from '../api/api-client.js';
import { escapeHtml, sanitizeUrl } from '../utils/sanitize.js';

const gridEl = document.getElementById('featuredScenariosGrid');
const filterBarEl = document.getElementById('homeGenreFilters');
const ageModalEl = document.getElementById('ageGateModal');
const ageTitleEl = document.getElementById('ageGateTitle');
const ageConfirmBtn = document.getElementById('ageConfirmBtn');
const ageCancelBtn = document.getElementById('ageCancelBtn');
const roleSelectEl = document.getElementById('demoRoleSwitcher');
const authActionBtn = document.getElementById('authActionBtn');
const notifBellBtn = document.getElementById('notifBellBtn');
const notifBadge = document.getElementById('notifBadge');

let pendingTargetUrl = '';
let homeScenarios = [];

function formatPrice(vnd) {
  return Number(vnd || 220000).toLocaleString('vi-VN') + 'đ';
}

function inferScenarioMeta(game) {
  const name = String(game.name || '');
  const desc = String(game.description || '');
  const text = (name + ' ' + desc).toLowerCase();

  let genre = game.genre || 'Suy luận phá án';
  let difficulty = game.difficulty || 'Trung bình';
  const is18Plus = Number(game.is_18_plus) === 1 || name.includes('18+');
  const themeCode = String(game.theme_code || 'DETECTIVE').toUpperCase();

  if (!game.genre) {
    if (text.includes('ma cà rồng') || text.includes('huyết tộc') || text.includes('hắc miêu') || text.includes('đẫm máu') || text.includes('kinh dị') || text.includes('số 13')) {
      genre = 'Kinh dị tâm linh';
      difficulty = 'Khó';
    } else if (text.includes('học đường') || text.includes('hài hước') || text.includes('trộm đề')) {
      genre = 'Cơ chế, hài hước';
      difficulty = 'Dễ';
    } else if (text.includes('không gian') || text.includes('viễn tưởng') || text.includes('pháp thuật')) {
      genre = 'Giả tưởng, siêu nhiên';
      difficulty = 'Khó';
    } else if (text.includes('giao hưởng') || text.includes('gatsby') || text.includes('dạ hội')) {
      genre = 'Tình cảm, tâm lý xã hội';
      difficulty = 'Trung bình';
    }
  }

  const minP = Number(game.min_players || 6);
  const maxP = Number(game.max_players || 8);
  const male = Math.ceil(minP / 2);
  const female = Math.max(1, minP - male);
  const mins = Number(game.duration_minutes || 240);
  const hrs = (mins / 60).toFixed(1).replace('.0', '');

  return {
    id: game.id,
    title: name,
    genre,
    difficulty,
    theme_code: themeCode,
    duration: `${hrs} tiếng (${mins}p)`,
    price: Number(game.min_price || 180000),
    is18Plus,
    players: { total: minP === maxP ? `${minP}` : `${minP}-${maxP}`, male, female },
    summary: desc
  };
}

function renderScenarios(genreFilter = 'ALL') {
  if (!gridEl) return;
  const source = homeScenarios.length > 0 ? homeScenarios : SCENARIOS;
  const list =
    genreFilter === 'ALL'
      ? source
      : source.filter((item) => String(item.genre || '').toLowerCase().includes(genreFilter.toLowerCase()));

  if (list.length === 0) {
    gridEl.innerHTML = '<p style="text-align: center; grid-column: 1/-1; color: var(--text-muted); padding: 32px 0;">Không có kịch bản nào thuộc thể loại này.</p>';
    return;
  }

  gridEl.innerHTML = list
    .slice(0, 6)
    .map((item) => {
      const codeStr = 'HS-' + String(item.id).padStart(2, '0');
      const safeTitle = escapeHtml(item.title);
      const safeGenre = escapeHtml(item.genre);
      const safeDiff = escapeHtml(item.difficulty);
      const safeTheme = escapeHtml(item.theme_code || 'DETECTIVE');
      const safeDuration = escapeHtml(item.duration);
      const safeSummary = escapeHtml(item.summary);
      return `
      <div class="home-flip-card">
        <div class="home-flip-inner">
          <div class="home-flip-front">
            <div class="dossier-cover">
              <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <span class="dossier-code">HỒ SƠ #${escapeHtml(codeStr)} • ${safeTheme}</span>
                ${item.is18Plus ? '<span class="stamp-badge">18+</span>' : `<span class="stamp-badge open">${safeDiff}</span>`}
              </div>
              <div>
                <div class="dossier-cover-title">${safeTitle}</div>
                <p class="dossier-summary-excerpt">${safeSummary}</p>
              </div>
              <div style="font-size: 0.78rem; color: var(--color-gold); opacity: 0.9;">
                [BÌA HỒ SƠ LƯU TRỮ CHÍNH THỨC]
              </div>
            </div>
            <div class="home-flip-front-info">
              <div class="home-card-tags">
                <span>${safeGenre}</span>
                <span>${safeDuration}</span>
              </div>
              <div class="home-card-tags" style="margin-top: 8px;">
                <span>${escapeHtml(String(item.players.total))} người (${item.players.male}N/${item.players.female}N)</span>
                <strong>Từ ${formatPrice(item.price)}</strong>
              </div>
            </div>
          </div>

          <div class="home-flip-back">
            <div>
              <span class="stamp-badge ${item.is18Plus ? '' : 'open'}">
                ${item.is18Plus ? 'GIỚI HẠN 18+' : safeDiff}
              </span>
              <h3>${safeTitle}</h3>
              <p style="font-size: 0.9rem; margin-bottom: 12px;">${safeSummary}</p>
              <hr class="ticket-divider" />
              <ul class="home-spec-list">
                <li><strong>Mã hồ sơ:</strong> #${escapeHtml(codeStr)} (${safeTheme})</li>
                <li><strong>Thời lượng:</strong> ${safeDuration}</li>
                <li><strong>Đội hình:</strong> ${escapeHtml(String(item.players.total))} người (${item.players.male} Nam - ${item.players.female} Nữ)</li>
                <li><strong>Giá vé từ:</strong> ${formatPrice(item.price)} (Cọc 50.000đ)</li>
              </ul>
            </div>

            <button
              type="button"
              class="btn btn-primary"
              data-id="${escapeHtml(String(item.id))}"
              data-title="${safeTitle}"
              data-adult="${item.is18Plus ? 'true' : 'false'}"
              style="width: 100%;"
            >
              Xem Chi Tiết & Đặt Phòng
            </button>
          </div>
        </div>
      </div>
    `;
    })
    .join('');
}

// Lọc thể loại
if (filterBarEl) {
  filterBarEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-genre]');
    if (!btn) return;

    filterBarEl.querySelectorAll('button').forEach((b) => {
      b.classList.remove('btn-gold', 'active');
      b.classList.add('btn-outline');
    });
    btn.classList.remove('btn-outline');
    btn.classList.add('btn-gold', 'active');

    renderScenarios(btn.dataset.genre);
  });
}

// Kiểm tra nhãn 18+ trước khi chuyển trang chi tiết
if (gridEl) {
  gridEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;

    const targetUrl = `./pages/scenario-detail.html?id=${encodeURIComponent(btn.dataset.id)}`;
    if (btn.dataset.adult === 'true' && ageModalEl) {
      pendingTargetUrl = targetUrl;
      if (ageTitleEl) ageTitleEl.textContent = `Xác Nhận Độ Tuổi — "${btn.dataset.title}"`;
      ageModalEl.hidden = false;
    } else {
      window.location.href = targetUrl;
    }
  });
}

if (ageCancelBtn && ageModalEl) {
  ageCancelBtn.addEventListener('click', () => {
    ageModalEl.hidden = true;
    pendingTargetUrl = '';
  });
}

if (ageConfirmBtn && ageModalEl) {
  ageConfirmBtn.addEventListener('click', () => {
    ageModalEl.hidden = true;
    if (pendingTargetUrl) window.location.href = pendingTargetUrl;
  });
}

function applyHeaderForUser(user) {
  const dmNavBtn = document.getElementById('nav-btn-dm');
  const adminNavBtn = document.getElementById('nav-btn-admin');
  const mobileDm = document.getElementById('mobile-nav-dm');
  const mobileAdmin = document.getElementById('mobile-nav-admin');

  if (!user) {
    if (authActionBtn) {
      authActionBtn.textContent = 'Đăng nhập';
      authActionBtn.href = './pages/auth.html';
    }
    if (roleSelectEl) roleSelectEl.value = 'guest';
    if (dmNavBtn) dmNavBtn.style.display = 'none';
    if (adminNavBtn) adminNavBtn.style.display = 'none';
    if (mobileDm) mobileDm.style.display = 'none';
    if (mobileAdmin) mobileAdmin.style.display = 'none';
    if (notifBadge) notifBadge.textContent = '0';
    return;
  }

  const role = String(user.role_code || user.role || 'PLAYER').toUpperCase();
  localStorage.setItem('kbs_demo_role', role.toLowerCase());
  if (roleSelectEl && ['player', 'dm', 'admin'].includes(role.toLowerCase())) {
    roleSelectEl.value = role.toLowerCase();
  }

  if (role === 'ADMIN') {
    if (authActionBtn) {
      authActionBtn.textContent = 'Cổng Admin';
      authActionBtn.href = './pages/admin-portal.html';
    }
    if (dmNavBtn) dmNavBtn.style.display = 'inline-block';
    if (adminNavBtn) adminNavBtn.style.display = 'inline-block';
    if (mobileDm) mobileDm.style.display = 'block';
    if (mobileAdmin) mobileAdmin.style.display = 'block';
  } else if (role === 'DM') {
    if (authActionBtn) {
      authActionBtn.textContent = 'Cổng Quản Trò (DM)';
      authActionBtn.href = './pages/dm-portal.html';
    }
    if (dmNavBtn) dmNavBtn.style.display = 'inline-block';
    if (adminNavBtn) adminNavBtn.style.display = 'none';
    if (mobileDm) mobileDm.style.display = 'block';
    if (mobileAdmin) mobileAdmin.style.display = 'none';
  } else {
    if (authActionBtn) {
      authActionBtn.textContent = user.student_verified ? 'Hồ sơ Player (HSSV)' : 'Hồ sơ Cá Nhân';
      authActionBtn.href = './pages/profile.html';
    }
    if (dmNavBtn) dmNavBtn.style.display = 'none';
    if (adminNavBtn) adminNavBtn.style.display = 'none';
    if (mobileDm) mobileDm.style.display = 'none';
    if (mobileAdmin) mobileAdmin.style.display = 'none';
  }

  refreshHomeNotifications();
}

async function refreshHomeNotifications() {
  if (!notifBadge) return;
  try {
    const res = await apiClient.get('/notifications?unread_only=1');
    const items = res.data?.notifications || (Array.isArray(res.data) ? res.data : []);
    notifBadge.textContent = String(items.length);
  } catch {
    notifBadge.textContent = '0';
  }
}

async function openNotificationsPopup() {
  let modal = document.getElementById('homeNotifModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'homeNotifModal';
    modal.className = 'home-modal-backdrop';
    modal.innerHTML = `
      <div class="ticket-card home-modal-box" style="max-width: 520px; width: 92%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <h3 style="margin: 0;">Thông Báo Hệ Thống</h3>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-outline" id="homeMarkAllReadBtn" style="padding: 4px 10px; font-size: 0.8rem;">Đọc Tất Cả</button>
            <button type="button" class="btn btn-outline" id="homeCloseNotifBtn" style="padding: 4px 10px; font-size: 0.8rem;">Đóng</button>
          </div>
        </div>
        <div id="homeNotifList" style="display: flex; flex-direction: column; gap: 10px;">
          <p style="color: var(--text-muted); font-size: 0.9rem;">Đang tải thông báo...</p>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    document.getElementById('homeCloseNotifBtn').addEventListener('click', () => {
      modal.hidden = true;
    });
    document.getElementById('homeMarkAllReadBtn').addEventListener('click', async () => {
      try {
        await apiClient.post('/notifications/read-all');
        await refreshHomeNotifications();
        await openNotificationsPopup();
      } catch (err) {
        alert(err.message || 'Không thể đánh dấu đã đọc');
      }
    });
  }

  modal.hidden = false;
  const listEl = document.getElementById('homeNotifList');
  try {
    const res = await apiClient.get('/notifications?limit=20');
    const items = res.data?.notifications || (Array.isArray(res.data) ? res.data : []);
    if (items.length === 0) {
      listEl.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 16px 0;">Bạn chưa có thông báo nào.</p>';
      return;
    }
    listEl.innerHTML = items.map(n => `
      <div style="padding: 10px 12px; border: 1px solid ${n.is_read ? 'rgba(201,162,94,0.2)' : 'var(--color-gold)'}; border-radius: 4px; background: ${n.is_read ? 'rgba(0,0,0,0.15)' : 'rgba(201,162,94,0.08)'};">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
          <strong style="font-size: 0.9rem; color: var(--color-gold);">${escapeHtml(n.title)}</strong>
          <span style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(n.created_at || '')}</span>
        </div>
        <p style="font-size: 0.85rem; margin-top: 4px; line-height: 1.5;">${escapeHtml(n.message || n.body || '')}</p>
      </div>
    `).join('');
  } catch (err) {
    if (err.status === 401) {
      listEl.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 16px 0;">Vui lòng <a href="./pages/auth.html" style="color: var(--color-gold); text-decoration: underline;">đăng nhập</a> để xem thông báo của bạn.</p>';
    } else {
      listEl.innerHTML = `<p style="color: #ef9a9a;">Không thể tải thông báo: ${escapeHtml(err.message)}</p>`;
    }
  }
}

if (notifBellBtn) {
  notifBellBtn.addEventListener('click', openNotificationsPopup);
}

// Bộ chuyển nhanh 4 vai trò (chỉ kích hoạt khi người dùng chủ động đổi dropdown)
if (roleSelectEl) {
  roleSelectEl.addEventListener('change', async (e) => {
    const role = e.target.value;
    localStorage.setItem('kbs_demo_role', role);

    if (role === 'guest') {
      try { await apiClient.post('/auth/logout'); } catch {}
      localStorage.removeItem('kbs_user');
      applyHeaderForUser(null);
    } else if (role === 'player') {
      try {
        const res = await apiClient.post('/auth/login', { email: 'player.hieu@kichbansat.vn', password: 'password123' });
        if (res.data?.user) {
          localStorage.setItem('kbs_user', JSON.stringify(res.data.user));
          applyHeaderForUser(res.data.user);
        }
      } catch (err) {
        alert(err.message || 'Không thể đăng nhập tài khoản Player');
      }
    } else if (role === 'dm') {
      try {
        const res = await apiClient.post('/auth/login', { email: 'dm.nam@kichbansat.vn', password: 'password123' });
        if (res.data?.user) {
          localStorage.setItem('kbs_user', JSON.stringify(res.data.user));
          applyHeaderForUser(res.data.user);
        }
      } catch (err) {
        alert(err.message || 'Không thể đăng nhập tài khoản DM');
      }
    } else if (role === 'admin') {
      try {
        const res = await apiClient.post('/auth/login', { email: 'admin@kichbansat.vn', password: 'password123' });
        if (res.data?.user) {
          localStorage.setItem('kbs_user', JSON.stringify(res.data.user));
          applyHeaderForUser(res.data.user);
        }
      } catch (err) {
        alert(err.message || 'Không thể đăng nhập tài khoản Admin');
      }
    }
  });
}

// Khởi chạy: Tải kịch bản thật từ CSDL + đồng bộ session thật từ /auth/me (KHÔNG tự đăng nhập đè!)
async function initHomePage() {
  try {
    const gamesRes = await apiClient.get('/games?limit=12');
    if (gamesRes.success && Array.isArray(gamesRes.data?.games) && gamesRes.data.games.length > 0) {
      homeScenarios = gamesRes.data.games.map(inferScenarioMeta);
    }
  } catch {
    homeScenarios = [];
  }
  renderScenarios('ALL');

  try {
    const me = await apiClient.get('/auth/me');
    if (me.success && me.data?.user) {
      localStorage.setItem('kbs_user', JSON.stringify(me.data.user));
      applyHeaderForUser(me.data.user);
    } else {
      applyHeaderForUser(null);
    }
  } catch {
    applyHeaderForUser(null);
  }
}

initHomePage();
