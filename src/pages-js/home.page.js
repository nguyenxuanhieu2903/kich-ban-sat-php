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
1. HIỆU ỨNG SCROLLYTELLING PARALLAX 3 PHÂN CẢNH (3D CỔNG SẮT)
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

  // --- Phân cảnh 3: tiệm bói tarot ---
  const s3CurtL = document.getElementById('s3-curtain-left');
  const s3CurtR = document.getElementById('s3-curtain-right');
  const s3Copy = document.getElementById('s3-copy');
  const tarot = [...document.querySelectorAll('.tarot-slot')];
  const flipRank = [0, 1, 4, 2, 3]; // thứ tự lật: ngoài trước, lá giữa (hung thủ) lật cuối
  const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2);

  const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
  const seg = (p, start, end) => clamp((p - start) / (end - start));

  let step = 180;
  function calcStep() {
    const w = tarot[0] ? tarot[0].offsetWidth : 150;
    step = Math.min(window.innerWidth * 0.17, w * 1.1);
  }
  calcStep();

  let ticking = false;
  function updateParallax() {
    const rect = stage.getBoundingClientRect();
    const scrollable = stage.offsetHeight - window.innerHeight;
    const p = scrollable > 0 ? clamp(-rect.top / scrollable) : 0;

    if (progressBar) progressBar.style.width = `${(p * 100).toFixed(1)}%`;

    // --- PHÂN CẢNH 1 (0.00 -> 0.35): Mở cánh cổng sắt xoay 3D & tiến vào dinh thự ---
    const p1 = seg(p, 0.0, 0.35);
    const p1Fade = 1 - seg(p, 0.28, 0.40);
    const gateAngle = p1 * 82; // Cánh cổng xoay mở 3D vào trong tới 82 độ
    const gateShift = p1 * 32;

    if (gateLeft) {
      gateLeft.style.transform = `perspective(1400px) rotateY(${gateAngle}deg) translateX(${-gateShift}%)`;
    }
    if (gateRight) {
      gateRight.style.transform = `perspective(1400px) rotateY(${-gateAngle}deg) translateX(${gateShift}%)`;
    }
    if (s1Mansion) {
      s1Mansion.style.transform = `scale(${1 + p1 * 0.52}) translateY(${p1 * 45}px)`;
      s1Mansion.style.opacity = p1Fade;
    }
    if (s1Sky) s1Sky.style.opacity = p1Fade;
    if (s1Gates) s1Gates.style.opacity = p1Fade;
    if (s1Content) {
      s1Content.style.opacity = 1 - seg(p, 0.16, 0.30);
      s1Content.style.transform = `translateY(${-p1 * 65}px) scale(${1 + p1 * 0.08})`;
      s1Content.style.pointerEvents = p > 0.28 ? 'none' : 'auto';
    }

    // --- PHÂN CẢNH 2 (0.34 -> 0.72): Bàn tròn nghi phạm & ánh nến ---
    const p2In = seg(p, 0.34, 0.46);
    const p2Out = 1 - seg(p, 0.64, 0.74);
    const s2Op = Math.min(p2In, p2Out);
    if (s2Room) {
      s2Room.style.opacity = s2Op;
      s2Room.style.transform = `scale(${0.94 + p2In * 0.06})`;
    }

    // --- PHÂN CẢNH 3 (0.66 -> 1.00): Rèm mở, 5 lá bài bay lên, lật lần lượt ---
    if (s3Cards) {
      s3Cards.style.opacity = seg(p, 0.66, 0.78);
      s3Cards.style.pointerEvents = p > 0.68 ? 'auto' : 'none';
    }

    const open = easeInOut(seg(p, 0.68, 0.82));
    if (s3CurtL) s3CurtL.style.transform = `translateX(${-open * 62}%)`;
    if (s3CurtR) s3CurtR.style.transform = `translateX(${open * 62}%)`;

    tarot.forEach((el, i) => {
      const k = i - 2;
      const a = easeInOut(seg(p, 0.72 + Math.abs(k) * 0.015, 0.88));
      const f = seg(p, 0.78 + flipRank[i] * 0.028, 0.85 + flipRank[i] * 0.028);
      const x = k * step * a;
      const y = (1 - a) * 180 + k * k * 14 * a;
      const r = k * 7 * a;
      const s = (0.5 + 0.5 * a) * (1 + 0.1 * Math.sin(Math.PI * f));
      el.style.transform = `translate3d(${x}px, ${y}px, 0) rotate(${r}deg) scale(${s})`;
      el.style.opacity = a;
      el.style.zIndex = 5 - Math.abs(k);
      el.style.setProperty('--flip', `${(1 - easeInOut(f)) * 180}deg`);
    });

    if (s3Copy) {
      const c = seg(p, 0.88, 0.97);
      s3Copy.style.opacity = c;
      s3Copy.style.transform = `translateY(${(1 - c) * 20}px)`;
    }

    ticking = false;
  }

  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  }, { passive: true });

  window.addEventListener('resize', () => {
    calcStep();
    updateParallax();
  });

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
3. KHỐI KỊCH BẢN NỔI BẬT (DẠNG POSTER + TÌM KIẾM + POPUP 18+)
   ========================================================= */
let activeGenre = 'Tất cả';
let searchKeyword = '';
let pendingUrl = '';

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd');
}

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

function initScriptSearch() {
  const input = document.getElementById('script-search-input');
  const clearBtn = document.getElementById('script-search-clear');
  if (!input) return;

  input.addEventListener('input', (e) => {
    searchKeyword = e.target.value;
    if (clearBtn) clearBtn.style.display = searchKeyword ? 'inline-block' : 'none';
    renderFeaturedScripts();
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      input.value = '';
      searchKeyword = '';
      clearBtn.style.display = 'none';
      renderFeaturedScripts();
      input.focus();
    });
  }
}

function renderFeaturedScripts() {
  const grid = document.getElementById('home-scripts-grid');
  if (!grid) return;

  const kw = normalizeText(searchKeyword.trim());

  const list = SCENARIOS.filter((s) => {
    const matchGenre = activeGenre === 'Tất cả' || s.genre === activeGenre;
    const title = s.title || s.name || '';
    const matchKeyword = !kw || normalizeText(title).includes(kw);
    return matchGenre && matchKeyword;
  });

  if (!list.length) {
    grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 32px 0;">Không tìm thấy kịch bản nào phù hợp.</p>`;
    return;
  }

  grid.innerHTML = list.map((sc) => {
    const title = sc.title || sc.name;
    const posterFile = (sc.poster || '').split('/').pop();
    const imgSrc = posterFile ? `./src/assets/images/${posterFile}` : '';

    return `
      <a href="./pages/scenario-detail.html?id=${sc.id}"
         class="poster-card"
         data-open-script="${sc.id}"
         data-title="${title}"
         data-18="${Boolean(sc.is18Plus)}">
        <div class="poster-thumb" style="background:${sc.coverGrad || '#181512'};">
          ${imgSrc
            ? `<img src="${imgSrc}" alt="${title}" loading="lazy" onerror="this.src='https://placehold.co/400x600/181512/D64545?text=${encodeURIComponent(title)}'" />`
            : `<img src="https://placehold.co/400x600/181512/D64545?text=${encodeURIComponent(title)}" alt="${title}" loading="lazy" />`
          }
          ${sc.is18Plus ? '<span class="poster-tag">18+</span>' : ''}
        </div>
        <h3 class="poster-title">${title}</h3>
      </a>
    `;
  }).join('');

  grid.querySelectorAll('[data-open-script]').forEach((card) => {
    card.addEventListener('click', (e) => {
      if (card.dataset['18'] === 'true') {
        e.preventDefault();
        pendingUrl = card.getAttribute('href');
        const nameEl = document.getElementById('age-script-name');
        if (nameEl) nameEl.textContent = card.dataset.title;
        document.getElementById('age-modal')?.classList.add('open');
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
initScriptSearch();
renderFeaturedScripts();
initAgeModal();