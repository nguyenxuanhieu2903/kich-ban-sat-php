import { api } from '../../api/api-client.js';
import { escapeHtml } from '../../utils/sanitize.js';
import { ALL_SCRIPTS } from '../../data/scripts.data.js';
import { HOME_TEMPLATE_HTML } from './home-template.js';

export class HomeView {
  constructor(container, options = {}) {
    this.container = container;
    this.onNavigate = options.onNavigate || ((tab) => { window.location.hash = `#${tab}`; });
    this.onOpenAuth = options.onOpenAuth || (() => {});
    this.games = [];
    this.selectedGenre = 'Tất cả';
    this.searchQuery = '';
    this.pendingAgeAction = null;
  }

  async render() {
    this.container.innerHTML = `
      ${HOME_TEMPLATE_HTML}
      <dialog id="home-price-dialog" class="custom-modal" style="max-width: 560px; background: #13151F; color: #f8fafc; border: 1px solid rgba(212,175,55,0.4); border-radius: 14px; padding: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h3 style="font-family: var(--font-display); color: #D4AF37; margin: 0;">Quy Định &amp; Bảng Giá Vé V16</h3>
          <button id="btn-close-price-dialog" class="btn btn-ghost btn-sm" style="padding: 4px 10px;">✕</button>
        </div>
        <ul style="list-style: none; padding: 0; margin: 0 0 18px; display: grid; gap: 10px; font-size: 0.92rem; color: #CBD5E1;">
          <li>• <strong>Cọc cố định:</strong> 50.000đ / người để khóa ghế chính thức.</li>
          <li>• <strong>Hoàn cọc 24h:</strong> Hoàn 100% nếu hủy trước giờ chơi tối thiểu 24 tiếng.</li>
          <li>• <strong>Thời hạn giữ phòng:</strong> 4 giờ để ghép đủ người &amp; biểu quyết lịch.</li>
          <li>• <strong>Ưu đãi HSSV &amp; Thứ 3:</strong> Giảm giá trực tiếp khi xác thực thẻ sinh viên tại Player Lounge.</li>
        </ul>
        <button id="btn-ok-price-dialog" class="btn btn-gold" style="width: 100%;">Đã hiểu</button>
      </dialog>
    `;

    this.initScrollyParallax();
    this.bindEvents();
    await Promise.all([this.fetchOpenRooms(), this.fetchScripts()]);
  }

  initScrollyParallax() {
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

  bindEvents() {
    const priceDialog = document.getElementById('home-price-dialog');
    document.getElementById('btn-close-price-dialog')?.addEventListener('click', () => priceDialog?.close());
    document.getElementById('btn-ok-price-dialog')?.addEventListener('click', () => priceDialog?.close());

    this.container.querySelectorAll('a[href="#home-price-dialog"]').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        priceDialog?.showModal?.();
      });
    });

    // Search input
    const searchInput = document.getElementById('script-search-input');
    const clearBtn = document.getElementById('script-search-clear');
    let timer = null;
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        this.searchQuery = searchInput.value.trim().toLowerCase();
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
      });
    }

    // 18+ Age Modal
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
          <div class="room-card" style="background: var(--bg-surface, #13151F); border: 1px solid rgba(212,175,55,0.28); border-radius: 10px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span class="badge badge-gold">${escapeHtml(r.status)}</span>
                <span style="font-family:var(--font-mono);font-size:0.82rem;color:var(--gold);">${joined}/${max} người</span>
              </div>
              <h3 style="font-family:var(--font-display);font-size:1.15rem;margin:0 0 6px;">${escapeHtml(r.title || r.game_name || 'Phòng Kịch Bản Sát')}</h3>
              <p class="text-muted" style="font-size:0.86rem;margin:0;">${missing > 0 ? `Còn thiếu ${missing} chỗ • Cọc 50.000đ` : 'Đã đủ người'}</p>
            </div>
            <button class="btn btn-gold btn-sm btn-join-room-home" style="width:100%;">Vào phòng ghép nhóm &rarr;</button>
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
    let apiGames = [];
    try {
      const res = await api.get('/games');
      apiGames = res?.data?.games || [];
    } catch {
      apiGames = [];
    }

    const mappedApi = apiGames.map(g => ({
      id: g.id,
      title: g.name || g.title,
      genre: g.category || g.genre || 'Trinh thám',
      players: `${g.min_players || 5}–${g.max_players || 8} người`,
      price: Number(g.price_per_person || 189000),
      is18: Boolean(g.is_18_plus || g.age_limit >= 18),
      summary: g.description || 'Kịch bản nhập vai phá án có Quản trò dẫn dắt.'
    }));

    this.games = mappedApi.length >= 6 ? mappedApi : [
      ...mappedApi,
      ...ALL_SCRIPTS.slice(0, 12).map(s => ({
        id: s.id,
        title: s.title,
        genre: s.genre || 'Trinh thám',
        players: s.players || '5–8 người',
        price: s.price || 189000,
        is18: Boolean(s.is18),
        summary: s.summary || 'Kịch bản nhập vai phá án đặc sắc.'
      }))
    ];

    this.renderGenreFilters();
    this.renderScriptsGrid();
  }

  renderGenreFilters() {
    const bar = document.getElementById('home-genre-filters');
    if (!bar) return;
    const genres = ['Tất cả', ...new Set(this.games.map(g => g.genre).filter(Boolean))];
    bar.innerHTML = genres.map(g => `
      <button type="button" class="filter-pill ${this.selectedGenre === g ? 'active' : ''}" data-genre="${escapeHtml(g)}">
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

    const filtered = this.games.filter(g => {
      const matchGenre = this.selectedGenre === 'Tất cả' || g.genre === this.selectedGenre;
      const matchQuery = !this.searchQuery || (g.title || '').toLowerCase().includes(this.searchQuery);
      return matchGenre && matchQuery;
    });

    if (!filtered.length) {
      grid.innerHTML = `<p class="text-muted">Không tìm thấy kịch bản phù hợp.</p>`;
      return;
    }

    grid.innerHTML = filtered.slice(0, 9).map(g => `
      <div class="script-card" style="background: var(--bg-surface, #13151F); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;">
            <span class="badge badge-gold">${escapeHtml(g.genre)}</span>
            ${g.is18 ? '<span class="badge badge-18">18+</span>' : ''}
          </div>
          <h3 style="font-family:var(--font-display);font-size:1.15rem;margin:0 0 6px;">${escapeHtml(g.title)}</h3>
          <p class="text-muted" style="font-size:0.85rem;margin:0 0 10px;">${escapeHtml(g.summary)}</p>
          <div style="font-family:var(--font-mono);font-size:0.82rem;color:var(--gold);">
            👥 ${escapeHtml(g.players)} &bull; ${Number(g.price).toLocaleString('vi-VN')}đ
          </div>
        </div>
        <button class="btn btn-ghost btn-sm btn-open-script" data-title="${escapeHtml(g.title)}" data-is18="${g.is18 ? '1' : '0'}" style="width:100%;">
          Đặt phòng kịch bản này &rarr;
        </button>
      </div>
    `).join('');

    grid.querySelectorAll('.btn-open-script').forEach(btn => {
      btn.addEventListener('click', () => {
        const is18 = btn.dataset.is18 === '1';
        const title = btn.dataset.title || '';
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
