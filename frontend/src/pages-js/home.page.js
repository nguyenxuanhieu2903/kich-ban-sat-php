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

function escHtml(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

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
async function renderOpenRooms() {
  const grid = document.getElementById('home-rooms-grid');
  if (!grid) return;

  // Khách chưa đăng nhập không được phép xem danh sách phòng đang tuyển
  let isLoggedIn = false;
  let userRole = 'GUEST';
  try {
    const meRes = await fetch('/api/v1/auth/me', { credentials: 'include' });
    if (meRes.ok) {
      const meJson = await meRes.json();
      const meUser = meJson?.data?.user || meJson?.data;
      isLoggedIn = Boolean(meJson?.success && meUser?.id);
      userRole = String(meUser?.role_code || meUser?.role || 'PLAYER').toUpperCase();
    }
  } catch {
    isLoggedIn = false;
  }

  if (!isLoggedIn) {
    grid.innerHTML = `
      <div class="paper-ticket" style="grid-column:1/-1; padding:28px; text-align:center;">
        <h3 style="font-family:var(--font-display); font-size:1.35rem; margin-bottom:8px;">Đăng nhập để xem phòng đang tuyển</h3>
        <p style="font-size:0.92rem; color:var(--ink-muted); margin-bottom:18px;">
          Thông tin bàn chơi đang ghép chỉ hiển thị cho thành viên đã đăng nhập nhằm bảo mật lịch đặt của người chơi.
        </p>
        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
          <a href="./pages/auth.html#login" class="btn btn-crimson btn-sm">Đăng nhập ngay</a>
          <a href="./pages/auth.html#register" class="btn btn-gold btn-sm">Đăng ký thành viên</a>
        </div>
      </div>
    `;
    return;
  }

  let rooms = [];
  try {
    const res = await fetch('/api/v1/rooms?status=FORMING&per_page=6', { credentials: 'include' });
    const json = await res.json();
    const list = json?.data?.rooms || json?.rooms || [];
    rooms = list
      .filter((r) => Number(r.current_players ?? 0) > 0)
      .filter((r) => !(String(r.booking_type || r.room_type || 'PUBLIC').toUpperCase() === 'PRIVATE' && Number(r.guest_slots ?? 0) === 0))
      .slice(0, 3);
  } catch {
    rooms = [];
  }

  const isPlayerRole = userRole === 'PLAYER';
  if (!rooms.length) {
    grid.innerHTML = `<p class="text-muted">${isPlayerRole ? 'Hiện chưa có phòng nào đang tuyển người chơi. Hãy tự tạo phòng mới!' : 'Hiện chưa có phòng nào đang tuyển người chơi.'}</p>`;
    return;
  }

  grid.innerHTML = rooms.map((r) => {
    const filledSlots = Number(r.current_players ?? 1);
    const max = Number(r.max_players ?? 8);
    const missing = Math.max(0, max - filledSlots);
    return `
      <div class="paper-ticket" style="padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:14px;">
        <div>
          <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
            <span class="badge badge-18">PHÒNG #${Number(r.id)}</span>
            <span class="badge ${missing > 0 ? 'badge-green' : 'badge-dark'}">${missing > 0 ? `Thiếu ${missing} người` : 'Đủ người'}</span>
          </div>
          <h3 style="font-family:var(--font-display); font-size:1.25rem; margin-bottom:6px;">${escHtml(r.game_name || r.title || 'Kịch bản bí ẩn')}</h3>
          <p style="font-size:0.86rem; color:var(--ink-muted);">Khung giờ dự kiến: ${escHtml(r.target_time_slot || 'Đang cập nhật')}</p>
          <p style="font-size:0.86rem; color:var(--ink-muted);">Sĩ số: ${filledSlots}/${max} người chơi</p>
        </div>
        <a href="./pages/room-detail.html?id=${encodeURIComponent(r.id)}" class="btn btn-crimson btn-sm">${isPlayerRole ? 'Xem chi tiết &amp; Vào ghế' : 'Xem chi tiết phòng'}</a>
      </div>
    `;
  }).join('');
}

/* =========================================================
3. KHỐI KỊCH BẢN NỔI BẬT (DẠNG POSTER + TÌM KIẾM + POPUP 18+)
   ========================================================= */
let ACTIVE_SCENARIOS = [];
let catalogLoaded = false;
let activeGenre = 'Tất cả';
let searchKeyword = '';
let pendingUrl = '';

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/\s+/g, ' ')
    .trim();
}

async function syncActiveScenariosFromApi() {
  const grid = document.getElementById('home-scripts-grid');
  if (grid && !catalogLoaded) {
    grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 32px 0;">Đang tải kho kịch bản...</p>`;
  }
  try {
    const res = await fetch(`/api/v1/games?limit=100&_t=${Date.now()}`, {
      credentials: 'include',
      cache: 'no-store',
    });
    if (!res.ok) throw new Error('API_ERROR');
    const json = await res.json();
    const apiGames = json?.data?.games;
    if (!Array.isArray(apiGames)) throw new Error('INVALID_PAYLOAD');

    const staticByNormTitle = new Map();
    const staticByNumId = new Map();
    SCENARIOS.forEach((sc) => {
      const k = normalizeText(sc.title || sc.name || '');
      if (k) staticByNormTitle.set(k, sc);
      const numId = Number(String(sc.id || '').replace(/^SCR-0*/i, ''));
      if (Number.isFinite(numId) && numId > 0) staticByNumId.set(numId, sc);
    });

    // Nguồn sự thật duy nhất là danh sách ACTIVE từ MySQL -> kịch bản bị Admin ẩn chắc chắn không thể xuất hiện
    ACTIVE_SCENARIOS = apiGames.map((g) => {
      const norm = normalizeText(g.name || g.title || '');
      const sc = staticByNormTitle.get(norm) || staticByNumId.get(Number(g.id)) || null;
      if (sc) {
        return {
          ...sc,
          dbId: Number(g.id),
          title: g.name || g.title || sc.title,
          priceDay: Number(g.day_price ?? g.min_price ?? sc.priceDay ?? sc.price ?? 199000),
          priceNight: Number(g.night_price ?? g.max_price ?? sc.priceNight ?? 239000),
          is18Plus: Boolean(Number(g.is_18_plus) === 1 || sc.is18Plus),
        };
      }
      return {
        id: `DB-${g.id}`,
        dbId: Number(g.id),
        title: g.name || g.title || `Kịch bản #${g.id}`,
        genre: g.genre || g.theme_code || 'Trinh thám',
        is18Plus: Number(g.is_18_plus) === 1,
        poster: g.image_url || '',
      };
    });

    catalogLoaded = true;
    renderGenreFilters();
    renderFeaturedScripts();
  } catch {
    // Fallback an toàn nếu máy chủ API không khả dụng
    if (!catalogLoaded) {
      ACTIVE_SCENARIOS = [...SCENARIOS];
      catalogLoaded = true;
      renderGenreFilters();
      renderFeaturedScripts();
    }
  }
}

function renderGenreFilters() {
  const wrap = document.getElementById('home-genre-filters');
  if (!wrap) return;

  const genres = ['Tất cả', ...new Set(ACTIVE_SCENARIOS.map((s) => s.genre).filter(Boolean))];
  if (!genres.includes(activeGenre)) activeGenre = 'Tất cả';
  wrap.innerHTML = genres.map((g) => `
    <button type="button" class="btn btn-sm ${g === activeGenre ? 'btn-gold' : 'btn-ghost'}" data-genre="${escHtml(g)}">
      ${escHtml(g)}
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

  const list = ACTIVE_SCENARIOS.filter((s) => {
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
    const title = String(sc.title || sc.name || '');
    const safeTitle = escHtml(title);
    const posterFile = (sc.poster || '').split('/').pop();
    const imgSrc = posterFile ? `./src/assets/images/${encodeURIComponent(posterFile)}` : '';
    const fallback = `https://placehold.co/400x600/181512/D64545?text=${encodeURIComponent(title)}`;

    return `
      <a href="./pages/scenario-detail.html?id=${encodeURIComponent(sc.id)}"
         class="poster-card"
         data-open-script="${escHtml(sc.id)}"
         data-title="${safeTitle}"
         data-18="${Boolean(sc.is18Plus)}">
        <div class="poster-thumb" style="background:${escHtml(sc.coverGrad || '#181512')};">
          ${imgSrc
            ? `<img src="${imgSrc}" alt="${safeTitle}" loading="lazy" onerror="this.src='${fallback}'" />`
            : `<img src="${fallback}" alt="${safeTitle}" loading="lazy" />`
          }
          ${sc.is18Plus ? '<span class="poster-tag">18+</span>' : ''}
        </div>
        <h3 class="poster-title">${safeTitle}</h3>
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
initScriptSearch();
initAgeModal();
syncActiveScenariosFromApi();