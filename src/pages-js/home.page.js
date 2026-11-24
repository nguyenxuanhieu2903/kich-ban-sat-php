// src/pages-js/home.page.js
import * as ScriptData from '../data/scripts.data.js';
import { getState, formatVND } from '../logic/store.js';
import { initChrome } from '../logic/layout.js';

const SCENARIOS = ScriptData.SCENARIOS
  || ScriptData.ALL_SCRIPTS
  || ScriptData.SCRIPTS
  || ScriptData.default
  || Object.values(ScriptData).find((v) => Array.isArray(v))
  || [];
const FORMAT_VND = ScriptData.FORMAT_VND || formatVND;

initChrome('home');

/* =========================================================
   1. HIỆU ỨNG SCROLLYTELLING PARALLAX 3 PHÂN CẢNH
   ========================================================= */
function initScrollyParallax() {
  const stage = document.getElementById('scrolly-stage');
  if (!stage) return;

  const progressBar = document.getElementById('scrolly-progress');
  const s1Sky = document.getElementById('s1-sky');
  const s1Mansion = document.getElementById('s1-mansion');
  const s1Gates = document.getElementById('s1-gates');
  const gateLeft = document.getElementById('gate-left');
  const gateRight = document.getElementById('gate-right');
  const s1Content = document.getElementById('s1-content');
  const s2Room = document.getElementById('s2-room');
  const s3Cards = document.getElementById('s3-cards');
  const cardLeft = document.getElementById('card-left');
  const cardCenter = document.getElementById('card-center');
  const cardRight = document.getElementById('card-right');

  const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
  const seg = (p, start, end) => clamp((p - start) / (end - start));

  let ticking = false;
  function updateParallax() {
    const rect = stage.getBoundingClientRect();
    const scrollable = stage.offsetHeight - window.innerHeight;
    const p = scrollable > 0 ? clamp(-rect.top / scrollable) : 0;

    if (progressBar) progressBar.style.width = `${(p * 100).toFixed(1)}%`;

    // --- PHÂN CẢNH 1 (0.00 -> 0.35): Mở cổng sắt & phóng to dinh thự ---
    const p1 = seg(p, 0.0, 0.35);
    const p1Fade = 1 - seg(p, 0.28, 0.40);
    if (gateLeft) gateLeft.style.transform = `translateX(${-p1 * 62}%) scale(${1 + p1 * 0.25})`;
    if (gateRight) gateRight.style.transform = `translateX(${p1 * 62}%) scale(${1 + p1 * 0.25})`;
    if (s1Mansion) {
      s1Mansion.style.transform = `scale(${1 + p1 * 0.45}) translateY(${p1 * 40}px)`;
      s1Mansion.style.opacity = p1Fade;
    }
    if (s1Sky) s1Sky.style.opacity = p1Fade;
    if (s1Gates) s1Gates.style.opacity = p1Fade;
    if (s1Content) {
      s1Content.style.opacity = 1 - seg(p, 0.18, 0.32);
      s1Content.style.transform = `translateY(${-p1 * 60}px)`;
      s1Content.style.pointerEvents = p > 0.3 ? 'none' : 'auto';
    }

    // --- PHÂN CẢNH 2 (0.34 -> 0.72): Bàn tròn nghi phạm & ánh nến ---
    const p2In = seg(p, 0.34, 0.46);
    const p2Out = 1 - seg(p, 0.64, 0.74);
    const s2Op = Math.min(p2In, p2Out);
    if (s2Room) {
      s2Room.style.opacity = s2Op;
      s2Room.style.transform = `scale(${0.94 + p2In * 0.06})`;
    }

    // --- PHÂN CẢNH 3 (0.68 -> 1.00): Quạt 3 lá bài hồ sơ nhân vật ---
    const p3 = seg(p, 0.68, 0.88);
    if (s3Cards) {
      s3Cards.style.opacity = seg(p, 0.66, 0.78);
      s3Cards.style.pointerEvents = p > 0.68 ? 'auto' : 'none';
    }
    if (cardLeft) {
      cardLeft.style.transform = `translateX(${(1 - p3) * 90}px) rotate(${-14 * p3}deg) translateY(${12 * p3}px)`;
    }
    if (cardRight) {
      cardRight.style.transform = `translateX(${-(1 - p3) * 90}px) rotate(${14 * p3}deg) translateY(${12 * p3}px)`;
    }
    if (cardCenter) {
      cardCenter.style.transform = `translateY(${-10 * p3}px) scale(${0.92 + p3 * 0.1})`;
    }

    ticking = false;
  }

  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  }, { passive: true });

  updateParallax();
}

/* =========================================================
   2. KHỐI PHÒNG ĐANG TUYỂN NGƯỜI CHƠI
   ========================================================= */
function renderOpenRooms() {
  const grid = document.getElementById('home-rooms-grid');
  if (!grid) return;

  const state = getState();
  const rooms = (state.rooms || []).slice(0, 3);

  if (!rooms.length) {
    grid.innerHTML = `<p class="text-muted">Hiện chưa có phòng mở công khai. Hãy tự tạo phòng mới!</p>`;
    return;
  }

  grid.innerHTML = rooms.map((r) => {
    const sc = SCENARIOS.find((s) => s.id === r.scriptId) || SCENARIOS[0] || {};
    const filledSlots = Array.isArray(r.slots) ? r.slots.filter((sl) => sl.name).length : (r.players?.length || 1);
    const max = r.maxPlayers || 8;
    const missing = Math.max(0, max - filledSlots);
    return `
      <div class="paper-ticket" style="padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:14px;">
        <div>
          <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
            <span class="badge badge-18">PHÒNG #${r.id}</span>
            <span class="badge ${missing > 0 ? 'badge-green' : 'badge-dark'}">${missing > 0 ? `Thiếu ${missing} người` : 'Đủ người'}</span>
          </div>
          <h3 style="font-family:var(--font-display); font-size:1.25rem; margin-bottom:6px;">${sc.title || sc.name || 'Kịch bản bí ẩn'}</h3>
          <p style="font-size:0.86rem; color:var(--ink-muted);">📅 ${r.date || 'Hôm nay'} • ⏰ ${r.timeSlot || r.slot || '19:00'}</p>
          <p style="font-size:0.86rem; color:var(--ink-muted);">👥 Đã có ${filledSlots}/${max} người chơi</p>
        </div>
        <a href="./pages/room-detail.html?id=${r.id}" class="btn btn-crimson btn-sm">Xem chi tiết &amp; Vào ghế</a>
      </div>
    `;
  }).join('');
}

/* =========================================================
   3. KHỐI KỊCH BẢN NỔI BẬT & POPUP 18+
   ========================================================= */
let activeGenre = 'Tất cả';
let pendingUrl = '';

function renderGenreFilters() {
  const wrap = document.getElementById('home-genre-filters');
  if (!wrap) return;

  const genres = ['Tất cả', ...new Set(SCENARIOS.map((s) => s.genre).filter(Boolean))];
  wrap.innerHTML = genres.map((g) => `
    <button type="button" class="btn btn-sm ${g === activeGenre ? 'btn-gold' : 'btn-ghost'}" data-genre="${g}">
      ${g}
    </button>
  `).join('');

  wrap.querySelectorAll('button').forEach((btn) => {
    btn.addEventListener('click', () => {
      activeGenre = btn.dataset.genre;
      renderGenreFilters();
      renderFeaturedScripts();
    });
  });
}

function renderFeaturedScripts() {
  const grid = document.getElementById('home-scripts-grid');
  if (!grid) return;

  const list = activeGenre === 'Tất cả'
    ? SCENARIOS
    : SCENARIOS.filter((s) => s.genre === activeGenre);

  grid.innerHTML = list.map((sc) => {
    const title = sc.title || sc.name;
    const desc = sc.summary || sc.desc || '';
    const price = sc.priceDay || sc.price || 159000;
    const posterFile = (sc.poster || '').split('/').pop();

    return `
      <article class="script-card" style="background:var(--bg-surface); border:1px solid rgba(212,175,55,0.25); border-radius:var(--radius-md); overflow:hidden; display:flex; flex-direction:column;">
        <div style="height:210px; background:${sc.coverGrad || '#23201D'}; position:relative; overflow:hidden;">
          ${posterFile ? `<img src="./src/assets/images/${posterFile}" alt="${title}" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'" />` : ''}
          <span class="badge badge-gold" style="position:absolute; top:12px; left:12px;">${sc.genre || 'Trinh thám'}</span>
          ${sc.is18Plus ? '<span class="badge badge-18" style="position:absolute; top:12px; right:12px;">18+</span>' : ''}
        </div>

        <div style="padding:20px; flex:1; display:flex; flex-direction:column; justify-content:space-between; gap:14px;">
          <div>
            <h3 style="font-family:var(--font-display); font-size:1.25rem; margin-bottom:8px;">${title}</h3>
            <p class="text-muted" style="font-size:0.88rem; margin-bottom:12px;">${desc}</p>
            <div style="font-size:0.82rem; color:var(--text-secondary); display:grid; gap:4px;">
              <span>⏱ Thời lượng: <strong>${sc.duration || '3 - 4 tiếng'}</strong> • Độ khó: <strong>${sc.difficulty || 'Trung bình'}</strong></span>
              <span>👥 Đội hình: <strong>${sc.playersText || `${sc.players?.total || 8} người`}</strong></span>
            </div>
          </div>

          <div style="padding-top:12px; border-top:1px solid rgba(255,255,255,0.08); display:flex; justify-content:space-between; align-items:center;">
            <div>
              <div style="font-size:0.72rem; color:var(--text-secondary);">GIÁ VÉ TỪ</div>
              <strong style="color:var(--gold); font-size:1.05rem;">${FORMAT_VND(price)}</strong>
            </div>
            <button type="button" class="btn btn-gold btn-sm" data-open-script="${sc.id}" data-title="${title}" data-18="${sc.is18Plus}">
              Chọn kịch bản
            </button>
          </div>
        </div>
      </article>
    `;
  }).join('');

  grid.querySelectorAll('[data-open-script]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetUrl = `./pages/scenario-detail.html?id=${btn.dataset.openScript}`;
      if (btn.dataset['18'] === 'true') {
        pendingUrl = targetUrl;
        const nameEl = document.getElementById('age-script-name');
        if (nameEl) nameEl.textContent = btn.dataset.title;
        document.getElementById('age-modal')?.classList.add('open');
      } else {
        window.location.href = targetUrl;
      }
    });
  });
}

function initAgeModal() {
  const modal = document.getElementById('age-modal');
  document.getElementById('age-cancel-btn')?.addEventListener('click', () => {
    modal?.classList.remove('open');
    pendingUrl = '';
  });
  document.getElementById('age-confirm-btn')?.addEventListener('click', () => {
    modal?.classList.remove('open');
    if (pendingUrl) window.location.href = pendingUrl;
  });
}

initScrollyParallax();
renderOpenRooms();
renderGenreFilters();
renderFeaturedScripts();
initAgeModal();