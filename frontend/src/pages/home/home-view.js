import { api } from '../../api/api-client.js';
import { escapeHtml } from '../../utils/sanitize.js';
import { ALL_SCRIPTS } from '../../data/scripts.data.js';
import { HOME_TEMPLATE_HTML } from './home-template.js';

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd');
}

export class HomeView {
  constructor(container, options = {}) {
    this.container = container;
    this.onNavigate = options.onNavigate || ((tab) => { window.location.hash = `#${tab}`; });
    this.onOpenAuth = options.onOpenAuth || (() => {});
    this.games = [];
    this.selectedGenre = 'Tất cả';
    this.searchQuery = '';
    this.pendingAgeAction = null;
    this._onScroll = null;
    this._onResize = null;
  }

  async render() {
    this.container.innerHTML = HOME_TEMPLATE_HTML;

    this.initScrollyParallax();
    this.bindEvents();
    await Promise.all([this.fetchOpenRooms(), this.fetchScripts()]);
  }

  initScrollyParallax() {
    // Dọn listener cũ tránh rò rỉ bộ nhớ khi chuyển tab qua lại
    if (this._onScroll) window.removeEventListener('scroll', this._onScroll);
    if (this._onResize) window.removeEventListener('resize', this._onResize);

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
    const s3CurtL = document.getElementById('s3-curtain-left');
    const s3CurtR = document.getElementById('s3-curtain-right');
    const s3Copy = document.getElementById('s3-copy');
    const tarot = [...document.querySelectorAll('.tarot-slot')];
    const flipRank = [0, 1, 4, 2, 3];
    const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2);
    const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
    const seg = (p, start, end) => clamp((p - start) / (end - start));

    let step = 180;
    const calcStep = () => {
      const w = tarot[0] ? tarot[0].offsetWidth : 150;
      step = Math.min(window.innerWidth * 0.17, w * 1.1);
    };
    calcStep();

    let ticking = false;
    const updateParallax = () => {
      if (!document.body.contains(stage)) return;
      const rect = stage.getBoundingClientRect();
      const scrollable = stage.offsetHeight - window.innerHeight;
      const p = scrollable > 0 ? clamp(-rect.top / scrollable) : 0;

      if (progressBar) progressBar.style.width = `${(p * 100).toFixed(1)}%`;

      const p1 = seg(p, 0.0, 0.35);
      const p1Fade = 1 - seg(p, 0.28, 0.40);
      const gateAngle = p1 * 82;
      const gateShift = p1 * 32;

      if (gateLeft) gateLeft.style.transform = `perspective(1400px) rotateY(${gateAngle}deg) translateX(${-gateShift}%)`;
      if (gateRight) gateRight.style.transform = `perspective(1400px) rotateY(${-gateAngle}deg) translateX(${gateShift}%)`;
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

      const p2In = seg(p, 0.34, 0.46);
      const p2Out = 1 - seg(p, 0.64, 0.74);
      if (s2Room) {
        s2Room.style.opacity = Math.min(p2In, p2Out);
        s2Room.style.transform = `scale(${0.94 + p2In * 0.06})`;
      }

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
    };

    this._onScroll = () => {
      if (!ticking) {
        requestAnimationFrame(updateParallax);
        ticking = true;
      }
    };
    this._onResize = () => {
      calcStep();
      updateParallax();
    };

    window.addEventListener('scroll', this._onScroll, { passive: true });
    window.addEventListener('resize', this._onResize);

    updateParallax();
  }

  bindEvents() {
    const priceDialog = document.getElementById('home-price-dialog');
    this.container.querySelectorAll('a[href="#home-price-dialog"]').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        if (priceDialog && typeof priceDialog.showModal === 'function' && !priceDialog.open) {
          priceDialog.showModal();
        }
      });
    });

    // Tìm kiếm tiếng Việt không dấu + debounce 200ms
    const searchInput = document.getElementById('script-search-input');
    const clearBtn = document.getElementById('script-search-clear');
    let timer = null;
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        this.searchQuery = searchInput.value.trim();
        if (clearBtn) clearBtn.style.display = this.searchQuery ? 'inline-flex' : 'none';
        clearTimeout(timer);
        timer = setTimeout(() => this.renderScriptsGrid(), 200);
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        this.searchQuery = '';
        if (searchInput) searchInput.value = '';
        clearBtn.style.display = 'none';
        this.renderScriptsGrid();
        searchInput?.focus();
      });
    }

    // Popup xác nhận 18+
    const ageModal = document.getElementById('age-modal');
    document.getElementById('age-cancel-btn')?.addEventListener('click', () => {
      ageModal?.classList.remove('open');
      this.pendingAgeAction = null;
    });
    document.getElementById('age-confirm-btn')?.addEventListener('click', () => {
      ageModal?.classList.remove('open');
      if (typeof this.pendingAgeAction === 'function') this.pendingAgeAction();
      this.pendingAgeAction = null;
    });
  }

  async fetchOpenRooms() {
    const grid = document.getElementById('home-rooms-grid');
    if (!grid) return;
    try {
      const res = await api.get('/rooms');
      const rooms = (res?.data?.rooms || []).filter(r => ['FORMING', 'REPLACEMENT', 'SCHEDULED'].includes(r.status)).slice(0, 3);
      if (!rooms.length) {
        grid.innerHTML = `<p class="text-muted">Hiện chưa có phòng mở công khai. Hãy tự tạo phòng mới!</p>`;
        return;
      }
      grid.innerHTML = rooms.map(r => {
        const joined = Number(r.current_players || r.participants_count || 1);
        const max = Number(r.max_players || 8);
        const missing = Math.max(0, max - joined);
        return `
          <div class="room-card" style="background: var(--bg-surface, #13151F); border: 1px solid rgba(196, 55, 55, 0.45); border-radius: 10px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span class="badge badge-18">${escapeHtml(r.status)}</span>
                <span style="font-family:var(--font-mono);font-size:0.82rem;color:#EF4444;font-weight:700;">${joined}/${max} người</span>
              </div>
              <h3 style="font-family:var(--font-display);font-size:1.15rem;margin:0 0 6px;">${escapeHtml(r.title || r.game_name || 'Phòng Kịch Bản Sát')}</h3>
              <p class="text-muted" style="font-size:0.86rem;margin:0;">${missing > 0 ? `Còn thiếu ${missing} chỗ • Cọc 50.000đ` : 'Đã đủ người'}</p>
            </div>
            <button class="btn btn-sm btn-header-crimson btn-join-room-home" style="width:100%;">Vào phòng ghép nhóm &rarr;</button>
          </div>
        `;
      }).join('');
      grid.querySelectorAll('.btn-join-room-home').forEach(b => {
        b.addEventListener('click', () => this.onNavigate('rooms'));
      });
    } catch {
      grid.innerHTML = `<p class="text-muted">Không thể tải danh sách phòng trực tuyến.</p>`;
    }
  }

  async fetchScripts() {
    // Chỉ lấy đúng 42 kịch bản chuẩn có ảnh bìa thật từ scripts.data.js
    this.games = (ALL_SCRIPTS || [])
      .filter(s => Boolean(s.poster))
      .map(s => ({
        id: s.id,
        title: s.title,
        genre: s.genre,
        is18Plus: Boolean(s.is18Plus),
        poster: `./src/assets/images/${(s.poster || '').split('/').pop()}`
      }));

    this.renderGenreFilters();
    this.renderScriptsGrid();
  }

  renderGenreFilters() {
    const bar = document.getElementById('home-genre-filters');
    if (!bar) return;
    const genres = ['Tất cả', ...new Set(this.games.map(g => g.genre).filter(Boolean))];
    bar.innerHTML = genres.map(g => `
      <button type="button" class="btn btn-sm ${this.selectedGenre === g ? 'btn-header-crimson' : 'btn-ghost'}" data-genre="${escapeHtml(g)}">
        ${escapeHtml(g)}
      </button>
    `).join('');

    bar.querySelectorAll('[data-genre]').forEach(btn => {
      btn.addEventListener('click', () => {
        this.selectedGenre = btn.dataset.genre;
        this.renderGenreFilters();
        this.renderScriptsGrid();
      });
    });
  }

  renderScriptsGrid() {
    const grid = document.getElementById('home-scripts-grid');
    if (!grid) return;

    const kw = normalizeText(this.searchQuery);
    const filtered = this.games.filter(g => {
      const matchGenre = this.selectedGenre === 'Tất cả' || g.genre === this.selectedGenre;
      const matchQuery = !kw || normalizeText(g.title).includes(kw);
      return matchGenre && matchQuery;
    });

    if (!filtered.length) {
      grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 32px 0;">Không tìm thấy kịch bản phù hợp.</p>`;
      return;
    }

    grid.innerHTML = filtered.slice(0, 12).map(g => {
      const fallbackUrl = `https://placehold.co/400x600/181512/D64545?text=${encodeURIComponent(g.title)}`;
      return `
        <a href="#games"
           class="poster-card btn-open-script"
           data-title="${escapeHtml(g.title)}"
           data-18="${g.is18Plus ? '1' : '0'}">
          <div class="poster-thumb">
            <img src="${escapeHtml(g.poster)}" alt="${escapeHtml(g.title)}" loading="lazy" onerror="this.onerror=null;this.src='${fallbackUrl}'" />
            ${g.is18Plus ? '<span class="poster-tag">18+</span>' : ''}
          </div>
          <h3 class="poster-title">${escapeHtml(g.title)}</h3>
        </a>
      `;
    }).join('');

    grid.querySelectorAll('.btn-open-script').forEach(card => {
      card.addEventListener('click', (e) => {
        e.preventDefault();
        const is18 = card.dataset['18'] === '1';
        const title = card.dataset.title || '';
        if (is18) {
          const modal = document.getElementById('age-modal');
          const nameEl = document.getElementById('age-script-name');
          if (nameEl) nameEl.textContent = title;
          this.pendingAgeAction = () => this.onNavigate('games');
          modal?.classList.add('open');
        } else {
          this.onNavigate('games');
        }
      });
    });
  }
}
