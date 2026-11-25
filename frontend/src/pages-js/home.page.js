// src/pages-js/home.page.js
import { SCENARIOS } from '../data/scripts.data.js';
import { apiClient } from '../api/api-client.js';
import { escapeHtml } from '../utils/sanitize.js';
import { normalizeScenario, renderGameFolioCardHtml } from '../utils/poster-svg.js';

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

function inferScenarioMeta(game) {
  return normalizeScenario(game);
}

function renderScenarios(genreFilter = 'ALL') {
  if (!gridEl) return;
  const source = homeScenarios.length > 0 ? homeScenarios : SCENARIOS;
  const list =
    genreFilter === 'ALL'
      ? source
      : source.filter((item) => {
          const g = normalizeScenario(item);
          return `${g.genre} ${g.subtitle} ${g.description}`.toLowerCase().includes(genreFilter.toLowerCase());
        });

  if (list.length === 0) {
    gridEl.innerHTML = '<p style="text-align: center; grid-column: 1/-1; color: var(--text-muted); padding: 32px 0;">Không có hồ sơ kịch bản nào thuộc thể loại này.</p>';
    return;
  }

  gridEl.innerHTML = list
    .slice(0, 6)
    .map((item) =>
      renderGameFolioCardHtml(item, {
        detailBase: './pages/scenario-detail.html',
        createBase: './pages/create-room.html'
      })
    )
    .join('');
}

// Lọc thể loại
if (filterBarEl) {
  filterBarEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-genre]');
    if (!btn) return;

    filterBarEl.querySelectorAll('button[data-genre]').forEach((b) => {
      b.classList.toggle('active', b === btn);
    });

    renderScenarios(btn.dataset.genre);
  });
}

// Kiểm tra nhãn 18+ trước khi chuyển trang chi tiết / mở phòng
if (gridEl) {
  gridEl.addEventListener('click', (e) => {
    const link = e.target.closest('a.scenario-nav-link');
    if (!link) return;

    if (link.dataset.adult === 'true' && ageModalEl) {
      e.preventDefault();
      pendingTargetUrl = link.getAttribute('href') || `./pages/scenario-detail.html?id=${encodeURIComponent(link.dataset.id || '1')}`;
      if (ageTitleEl) ageTitleEl.textContent = `Xác Nhận Độ Tuổi 18+ — "${link.dataset.title || ''}"`;
      ageModalEl.hidden = false;
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
