import { store, formatVND } from '../logic/store.js';
import { initChrome } from '../logic/layout.js';

initChrome('home');

/* =========================================================
   1. SCROLLYTELLING PARALLAX ENGINE (requestAnimationFrame)
   ========================================================= */
const stage = document.getElementById('scrolly-stage');
const progressBar = document.getElementById('scrolly-progress');

const s1Sky = document.getElementById('s1-sky');
const s1Mansion = document.getElementById('s1-mansion');
const gateLeft = document.getElementById('gate-left');
const gateRight = document.getElementById('gate-right');
const s1Content = document.getElementById('s1-content');
const scrollCue = document.getElementById('scroll-cue');

const s2Room = document.getElementById('s2-room');
const s2Copy = document.getElementById('s2-copy');

const s3Cards = document.getElementById('s3-cards');
const cardLeft = document.getElementById('card-left');
const cardCenter = document.getElementById('card-center');
const cardRight = document.getElementById('card-right');

let ticking = false;

function clamp(val, min, max) {
  return Math.min(max, Math.max(min, val));
}

function updateParallax() {
  if (!stage) return;
  const rect = stage.getBoundingClientRect();
  const maxScroll = stage.offsetHeight - window.innerHeight;
  const scrolled = clamp(-rect.top, 0, maxScroll);
  const progress = maxScroll > 0 ? scrolled / maxScroll : 0;

  if (progressBar) progressBar.style.width = `${(progress * 100).toFixed(1)}%`;

  // --- PHÂN CẢNH 1: 0.00 -> 0.35 ---
  const p1 = clamp(progress / 0.35, 0, 1);
  const fadeOut1 = progress > 0.28 ? clamp(1 - (progress - 0.28) / 0.09, 0, 1) : 1;

  if (s1Sky) {
    s1Sky.style.transform = `translate3d(0, ${scrolled * 0.12}px, 0)`;
    s1Sky.style.opacity = fadeOut1;
  }
  if (s1Mansion) {
    s1Mansion.style.transform = `translate3d(0, ${p1 * -45}px, 0) scale(${1 + p1 * 0.18})`;
    s1Mansion.style.opacity = fadeOut1;
  }
  if (gateLeft && gateRight) {
    const gateScale = 1 + p1 * 0.6;
    const gateSpread = p1 * 48; // Tách đôi cổng sang 2 bên
    gateLeft.style.transform = `translate3d(-${gateSpread}%, ${p1 * 30}px, 0) scale(${gateScale})`;
    gateRight.style.transform = `translate3d(${gateSpread}%, ${p1 * 30}px, 0) scale(${gateScale})`;
    gateLeft.style.opacity = fadeOut1;
    gateRight.style.opacity = fadeOut1;
  }
  if (s1Content) {
    s1Content.style.opacity = fadeOut1;
    s1Content.style.transform = `translate3d(0, ${p1 * -35}px, 0)`;
    s1Content.style.pointerEvents = fadeOut1 > 0.2 ? 'auto' : 'none';
  }
  if (scrollCue) {
    scrollCue.style.opacity = progress > 0.15 ? '0' : '1';
  }

  // --- PHÂN CẢNH 2: 0.35 -> 0.70 ---
  let op2 = 0;
  if (progress >= 0.30 && progress <= 0.74) {
    if (progress < 0.38) op2 = (progress - 0.30) / 0.08;
    else if (progress > 0.66) op2 = 1 - (progress - 0.66) / 0.08;
    else op2 = 1;
  }
  const p2 = clamp((progress - 0.35) / 0.35, 0, 1);
  if (s2Room) {
    s2Room.style.opacity = clamp(op2, 0, 1);
  }
  if (s2Copy) {
    const slideX = (1 - clamp(p2 * 2.2, 0, 1)) * -40;
    s2Copy.style.transform = `translate3d(${slideX}px, 0, 0)`;
  }

  // --- PHÂN CẢNH 3: 0.70 -> 1.00 ---
  const p3 = clamp((progress - 0.68) / 0.32, 0, 1);
  if (s3Cards) {
    s3Cards.style.opacity = p3;
    s3Cards.style.pointerEvents = p3 > 0.3 ? 'auto' : 'none';
  }
  if (cardLeft && cardCenter && cardRight) {
    const riseY = (1 - p3) * 85;
    cardLeft.style.transform = `translate3d(${(1 - p3) * 40}px, ${riseY}px, 0) rotate(${-6 * p3}deg)`;
    cardCenter.style.transform = `translate3d(0, ${riseY * 0.7}px, 0) scale(${0.95 + 0.13 * p3})`;
    cardRight.style.transform = `translate3d(${(1 - p3) * -40}px, ${riseY}px, 0) rotate(${6 * p3}deg)`;
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

/* =========================================================
   2. RENDER PHÒNG ĐANG TUYỂN & KỊCH BẢN NỔI BẬT + MODAL 18+
   ========================================================= */
let pendingScriptUrl = '';
const ageModal = document.getElementById('age-modal');
const ageScriptName = document.getElementById('age-script-name');

function openWithAgeCheck(script, targetUrl) {
  if (!script.is18Plus) {
    window.location.href = targetUrl;
    return;
  }
  pendingScriptUrl = targetUrl;
  ageScriptName.textContent = script.title;
  ageModal.classList.add('open');
}

document.getElementById('age-cancel-btn')?.addEventListener('click', () => {
  ageModal.classList.remove('open');
});
document.getElementById('age-confirm-btn')?.addEventListener('click', () => {
  ageModal.classList.remove('open');
  if (pendingScriptUrl) window.location.href = pendingScriptUrl;
});

function renderHomeRooms() {
  const grid = document.getElementById('home-rooms-grid');
  if (!grid) return;
  const formingRooms = store.state.rooms.filter(r => r.status === 'forming').slice(0, 4);

  grid.innerHTML = formingRooms.map(room => {
    const script = store.state.scripts.find(s => s.id === room.scriptId) || store.state.scripts[0];
    const filled = room.slots.filter(s => s.name).length;
    const pct = Math.round((filled / room.maxPlayers) * 100);
    return `
      <article class="room-card">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span class="badge badge-green">Đang tuyển</span>
            <span style="font-family:var(--font-mono);font-size:0.8rem;color:var(--gold);">#${room.id}</span>
          </div>
          <h3 style="font-family:var(--font-display);font-size:1.25rem;margin-bottom:4px;">${script.title}</h3>
          <p class="text-muted" style="font-size:0.86rem;">Chủ phòng: ${room.hostName}</p>
          <p style="font-size:0.86rem;margin-top:6px;">📅 ${room.date} • ⏰ ${room.timeSlot}</p>
        </div>

        <div>
          <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
            <span>Chỗ ngồi đã đặt</span>
            <strong class="text-gold">${filled}/${room.maxPlayers} người</strong>
          </div>
          <div class="progress-track">
            <div class="progress-fill" style="width:${pct}%"></div>
          </div>
          <a href="./pages/room-detail.html?id=${room.id}" class="btn btn-gold btn-sm" style="width:100%;margin-top:14px;">
            Xem &amp; tham gia
          </a>
        </div>
      </article>
    `;
  }).join('');
}

let selectedGenre = 'Tất cả';

function renderFeaturedScripts() {
  const filterWrap = document.getElementById('home-genre-filters');
  const grid = document.getElementById('home-scripts-grid');
  if (!filterWrap || !grid) return;

  const activeScripts = store.state.scripts.filter(s => s.isActive);
  const genres = ['Tất cả', ...new Set(activeScripts.slice(0, 6).map(s => s.genre))];

  filterWrap.innerHTML = genres.map(g => `
    <button class="filter-pill ${g === selectedGenre ? 'active' : ''}" data-genre="${g}">${g}</button>
  `).join('');

  filterWrap.querySelectorAll('.filter-pill').forEach(btn => {
    btn.addEventListener('click', () => {
      selectedGenre = btn.dataset.genre;
      renderFeaturedScripts();
    });
  });

  const list = activeScripts
    .filter(s => s.featured && (selectedGenre === 'Tất cả' || s.genre === selectedGenre));

  grid.innerHTML = list.map(s => `
    <article class="script-card">
      <div class="script-bg-letter">${s.title.charAt(0)}</div>
      <div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
          <span class="badge badge-gold">${s.playersMin}–${s.playersMax} người</span>
          <span class="badge badge-dark">${s.difficulty}</span>
          ${s.is18Plus ? '<span class="badge badge-18">18+</span>' : ''}
        </div>
        <h3 style="font-family:var(--font-display);font-size:1.35rem;margin-bottom:6px;">${s.title}</h3>
        <div style="font-size:0.8rem;color:var(--gold);margin-bottom:10px;">${s.genre} • Khoảng ${String(s.durationHours).replace('.', ',')} giờ</div>
        <p class="text-muted" style="font-size:0.9rem;">${s.summary}</p>
      </div>

      <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:14px;display:flex;align-items:center;justify-content:space-between;">
        <div>
          <span style="font-size:0.75rem;color:var(--text-dim);display:block;">Giá vé</span>
          <strong style="font-size:1.1rem;color:var(--gold);">${Math.round(s.price / 1000)}K/người</strong>
        </div>
        <button class="btn btn-crimson btn-sm" data-script-id="${s.id}">Chi tiết &amp; Đặt</button>
      </div>
    </article>
  `).join('');

  grid.querySelectorAll('[data-script-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const scr = store.state.scripts.find(item => item.id === btn.dataset.scriptId);
      if (scr) openWithAgeCheck(scr, `./pages/scenario-detail.html?id=${scr.id}`);
    });
  });
}

renderHomeRooms();
renderFeaturedScripts();