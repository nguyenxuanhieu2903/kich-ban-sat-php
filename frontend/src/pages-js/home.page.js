// src/pages-js/home.page.js
import { SCENARIOS } from '../data/scripts.data.js';
import { apiClient } from '../api/api-client.js';

const gridEl = document.getElementById('featuredScenariosGrid');
const filterBarEl = document.getElementById('homeGenreFilters');
const ageModalEl = document.getElementById('ageGateModal');
const ageTitleEl = document.getElementById('ageGateTitle');
const ageConfirmBtn = document.getElementById('ageConfirmBtn');
const ageCancelBtn = document.getElementById('ageCancelBtn');
const roleSelectEl = document.getElementById('demoRoleSwitcher');
const authActionBtn = document.getElementById('authActionBtn');

let pendingTargetUrl = '';

function formatPrice(vnd) {
  return vnd.toLocaleString('vi-VN') + 'đ';
}

function renderScenarios(genreFilter = 'ALL') {
  const list =
    genreFilter === 'ALL'
      ? SCENARIOS
      : SCENARIOS.filter((item) => item.genre.toLowerCase().includes(genreFilter.toLowerCase()));

  gridEl.innerHTML = list
    .map(
      (item) => `
      <div class="home-flip-card">
        <div class="home-flip-inner">
          <!-- Mặt trước: Poster & nhãn 18+ -->
          <div class="home-flip-front">
            <img src="./src/assets/images/${item.poster}" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=500&auto=format&fit=crop';" alt="${item.title}" />
            <div class="home-flip-front-info">
              <div class="home-card-tags">
                <span>${item.genre}</span>
                ${item.is18Plus ? '<span class="stamp-badge">18+</span>' : '<span>' + item.duration + '</span>'}
              </div>
              <h3>${item.title}</h3>
              <div class="home-card-tags">
                <span>${item.players.male} Nam / ${item.players.female} Nữ</span>
                <strong>${formatPrice(item.price)}</strong>
              </div>
            </div>
          </div>

          <!-- Mặt sau: Tóm tắt vụ án & Nút đặt phòng -->
          <div class="home-flip-back">
            <div>
              <span class="stamp-badge ${item.is18Plus ? '' : 'open'}">
                ${item.is18Plus ? 'GIỚI HẠN 18+' : item.difficulty}
              </span>
              <h3>${item.title}</h3>
              <p style="font-size: 0.9rem; margin-bottom: 12px;">${item.summary}</p>
              <hr class="ticket-divider" />
              <ul class="home-spec-list">
                <li><strong>Thời lượng:</strong> ${item.duration}</li>
                <li><strong>Đội hình:</strong> ${item.players.total} người (${item.players.male} Nam - ${item.players.female} Nữ)</li>
                <li><strong>Giá vé:</strong> ${formatPrice(item.price)} (Cọc 50.000đ)</li>
              </ul>
            </div>

            <button
              type="button"
              class="btn btn-primary"
              data-id="${item.id}"
              data-title="${item.title}"
              data-adult="${item.is18Plus}"
              style="width: 100%;"
            >
              Xem Chi Tiết & Đặt Phòng
            </button>
          </div>
        </div>
      </div>
    `
    )
    .join('');
}

// Lọc thể loại
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

// Kiểm tra nhãn 18+ trước khi chuyển trang chi tiết
gridEl.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-id]');
  if (!btn) return;

  const targetUrl = `./pages/scenario-detail.html?id=${btn.dataset.id}`;
  if (btn.dataset.adult === 'true') {
    pendingTargetUrl = targetUrl;
    ageTitleEl.textContent = `Xác Nhận Độ Tuổi — "${btn.dataset.title}"`;
    ageModalEl.hidden = false;
  } else {
    window.location.href = targetUrl;
  }
});

ageCancelBtn.addEventListener('click', () => {
  ageModalEl.hidden = true;
  pendingTargetUrl = '';
});

ageConfirmBtn.addEventListener('click', () => {
  ageModalEl.hidden = true;
  if (pendingTargetUrl) window.location.href = pendingTargetUrl;
});

// Bộ chuyển nhanh 4 vai trò để demo báo cáo
roleSelectEl.addEventListener('change', async (e) => {
  const role = e.target.value;
  localStorage.setItem('kbs_demo_role', role);

  const dmNavBtn = document.getElementById('nav-btn-dm');
  const adminNavBtn = document.getElementById('nav-btn-admin');

  if (role === 'guest') {
    try { await apiClient.post('/auth/logout'); } catch (err) {}
    authActionBtn.textContent = 'Đăng nhập';
    authActionBtn.href = './pages/auth.html';
    if (dmNavBtn) dmNavBtn.style.display = 'none';
    if (adminNavBtn) adminNavBtn.style.display = 'none';
  } else if (role === 'player') {
    try {
      await apiClient.post('/auth/login', { email: 'player.hieu@kichbansat.vn', password: 'password123' });
    } catch (err) {}
    authActionBtn.textContent = 'Hồ sơ Player (HSSV)';
    authActionBtn.href = './pages/profile.html';
    if (dmNavBtn) dmNavBtn.style.display = 'none';
    if (adminNavBtn) adminNavBtn.style.display = 'none';
  } else if (role === 'dm') {
    try {
      await apiClient.post('/auth/login', { email: 'dm.nam@kichbansat.vn', password: 'password123' });
    } catch (err) {}
    authActionBtn.textContent = 'Cổng Quản Trò (DM)';
    authActionBtn.href = './pages/dm-portal.html';
    if (dmNavBtn) dmNavBtn.style.display = 'inline-block';
    if (adminNavBtn) adminNavBtn.style.display = 'none';
  } else if (role === 'admin') {
    try {
      await apiClient.post('/auth/login', { email: 'admin@kichbansat.vn', password: 'password123' });
    } catch (err) {}
    authActionBtn.textContent = 'Cổng Admin';
    authActionBtn.href = './pages/admin-portal.html';
    if (dmNavBtn) dmNavBtn.style.display = 'none';
    if (adminNavBtn) adminNavBtn.style.display = 'inline-block';
  }
});

// Khởi chạy
renderScenarios('ALL');

const savedRole = localStorage.getItem('kbs_demo_role');
if (savedRole && roleSelectEl) {
  roleSelectEl.value = savedRole;
  roleSelectEl.dispatchEvent(new Event('change'));
}