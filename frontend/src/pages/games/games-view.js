import { api } from '../../api/api-client.js';
import { escapeHtml } from '../../utils/sanitize.js';
import { ALL_SCRIPTS } from '../../data/scripts.data.js';

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd');
}

export class GamesView {
  constructor(container, onSelectGame = () => {}) {
    this.container = container;
    this.onSelectGame = onSelectGame;
    this.games = [];
    this.activeGenre = 'Tất cả';
    this.searchKeyword = '';
    this.pendingGame = null;
  }

  async render() {
    this.container.innerHTML = `
      <div class="container" style="padding: 36px 20px 48px;">
        <div class="section-head">
          <div>
            <div class="section-kicker">THƯ VIỆN HỒ SƠ VỤ ÁN</div>
            <h1 class="section-title">Kho Kịch Bản Nhập Vai</h1>
          </div>

          <div class="script-search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="catalog-search-input" placeholder="Tìm tên kịch bản..." autocomplete="off" />
            <button type="button" id="catalog-search-clear" title="Xóa tìm kiếm" style="display:none;">✕</button>
          </div>
        </div>

        <div class="filter-pills" id="catalog-genre-filters"></div>
        <div class="scripts-grid" id="catalog-scripts-grid">
          <p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 40px 0;">
            Đang tải kho kịch bản...
          </p>
        </div>
      </div>

      <!-- Modal xác nhận 18+ -->
      <div class="modal-backdrop" id="age-modal">
        <div class="paper-ticket" style="max-width: 440px; padding: 28px; text-align: center;">
          <span class="badge badge-18" style="margin-bottom: 12px;">CẢNH BÁO NỘI DUNG 18+</span>
          <h3 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 10px;">
            Hồ Sơ Giới Hạn Độ Tuổi
          </h3>
          <p style="font-size: 0.94rem; color: var(--ink-muted); margin-bottom: 20px;">
            Kịch bản <strong id="age-script-name"></strong> có yếu tố kinh dị, tâm lý nặng hoặc bạo lực. Bạn xác nhận đã đủ 18 tuổi?
          </p>
          <div style="display: flex; gap: 12px; justify-content: center;">
            <button type="button" class="btn btn-ghost" style="color: var(--ink-black); border-color: #999;" id="age-cancel-btn">Quay lại</button>
            <button type="button" class="btn btn-gold" id="age-confirm-btn">Tôi đã đủ 18 tuổi</button>
          </div>
        </div>
      </div>

      <!-- Modal Chi Tiết Kịch Bản & Bảng Giá -->
      <dialog id="game-detail-modal" class="custom-modal" style="max-width: 620px; width: 92%; background: #13151F; color: #f8fafc; border: 1px solid rgba(212,175,55,0.4); border-radius: 14px; padding: 24px;">
        <div id="game-detail-body"></div>
      </dialog>
    `;

    this.bindEvents();
    await this.fetchGames();
  }

  bindEvents() {
    const searchInput = document.getElementById('catalog-search-input');
    const clearBtn = document.getElementById('catalog-search-clear');
    let timer = null;

    searchInput?.addEventListener('input', (e) => {
      this.searchKeyword = e.target.value;
      if (clearBtn) clearBtn.style.display = this.searchKeyword ? 'inline-block' : 'none';
      clearTimeout(timer);
      timer = setTimeout(() => this.renderCatalog(), 200);
    });

    clearBtn?.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      this.searchKeyword = '';
      clearBtn.style.display = 'none';
      this.renderCatalog();
      searchInput?.focus();
    });

    document.getElementById('age-cancel-btn')?.addEventListener('click', () => {
      document.getElementById('age-modal')?.classList.remove('open');
      this.pendingGame = null;
    });

    document.getElementById('age-confirm-btn')?.addEventListener('click', () => {
      document.getElementById('age-modal')?.classList.remove('open');
      if (this.pendingGame) {
        const g = this.pendingGame;
        this.pendingGame = null;
        this.openGameDetail(g);
      }
    });
  }

  resolvePoster(sc, index) {
    const posterFile = (sc.poster || '').split('/').pop();
    if (posterFile && /\.(png|jpg|jpeg|webp)$/i.test(posterFile)) {
      return `./src/assets/images/${posterFile}`;
    }
    const num = String((index % 42) + 1).padStart(3, '0');
    return `./assets/images/covers/game_${num}.jpg`;
  }

  async fetchGames() {
    let apiGames = [];
    try {
      const res = await api.get('/games');
      apiGames = res?.data?.games || [];
    } catch {
      apiGames = [];
    }

    const mappedApi = apiGames.map((g, i) => ({
      id: g.id,
      apiId: g.id,
      title: g.name || g.title,
      genre: g.category || g.genre || 'Trinh thám',
      minPlayers: g.min_players || 5,
      maxPlayers: g.max_players || 8,
      duration: g.duration_minutes || 240,
      price: Number(g.price_per_person || 189000),
      is18Plus: Boolean(g.is_18_plus || (g.age_limit && g.age_limit >= 18)),
      description: g.description || 'Kịch bản nhập vai phá án có Quản trò (DM) dẫn dắt.',
      poster: this.resolvePoster(g, i)
    }));

    const seenTitles = new Set(mappedApi.map(g => normalizeText(g.title)));
    const extraScripts = (ALL_SCRIPTS || [])
      .filter(s => !seenTitles.has(normalizeText(s.title || s.name)))
      .map((s, idx) => ({
        id: s.id || `script-${idx + 1}`,
        apiId: mappedApi[0]?.id || 1,
        title: s.title || s.name,
        genre: s.genre || 'Trinh thám',
        minPlayers: s.minPlayers || 5,
        maxPlayers: s.maxPlayers || 8,
        duration: s.duration || 240,
        price: Number(s.price || 189000),
        is18Plus: Boolean(s.is18Plus || s.is18),
        description: s.summary || s.description || 'Kịch bản nhập vai phá án đặc sắc.',
        poster: this.resolvePoster(s, mappedApi.length + idx)
      }));

    this.games = [...mappedApi, ...extraScripts];
    this.renderFilters();
    this.renderCatalog();
  }

  renderFilters() {
    const wrap = document.getElementById('catalog-genre-filters');
    if (!wrap) return;

    const genres = ['Tất cả', ...new Set(this.games.map(s => s.genre).filter(Boolean))];
    wrap.innerHTML = genres.map(g => `
      <button type="button" class="btn btn-sm ${g === this.activeGenre ? 'btn-gold' : 'btn-ghost'}" data-genre="${escapeHtml(g)}">
        ${escapeHtml(g)}
      </button>
    `).join('');

    wrap.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => {
        this.activeGenre = btn.dataset.genre;
        this.renderFilters();
        this.renderCatalog();
      });
    });
  }

  renderCatalog() {
    const grid = document.getElementById('catalog-scripts-grid');
    if (!grid) return;

    const kw = normalizeText(this.searchKeyword.trim());
    const list = this.games.filter(s => {
      const matchGenre = this.activeGenre === 'Tất cả' || s.genre === this.activeGenre;
      const matchKeyword = !kw || normalizeText(s.title).includes(kw);
      return matchGenre && matchKeyword;
    });

    if (!list.length) {
      grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 40px 0;">Không tìm thấy kịch bản phù hợp.</p>`;
      return;
    }

    grid.innerHTML = list.map((sc, idx) => {
      const fallbackUrl = `https://placehold.co/400x600/181512/D64545?text=${encodeURIComponent(sc.title)}`;
      return `
        <a href="#games"
           class="poster-card"
           data-open-idx="${idx}"
           data-title="${escapeHtml(sc.title)}"
           data-18="${sc.is18Plus ? 'true' : 'false'}">
          <div class="poster-thumb">
            <img src="${escapeHtml(sc.poster)}" alt="${escapeHtml(sc.title)}" loading="lazy" onerror="this.onerror=null;this.src='${fallbackUrl}'" />
            ${sc.is18Plus ? '<span class="poster-tag">18+</span>' : ''}
          </div>
          <h3 class="poster-title">${escapeHtml(sc.title)}</h3>
        </a>
      `;
    }).join('');

    grid.querySelectorAll('[data-open-idx]').forEach(card => {
      card.addEventListener('click', (e) => {
        e.preventDefault();
        const sc = list[Number(card.dataset.openIdx)];
        if (!sc) return;
        if (sc.is18Plus) {
          this.pendingGame = sc;
          const nameEl = document.getElementById('age-script-name');
          if (nameEl) nameEl.textContent = sc.title;
          document.getElementById('age-modal')?.classList.add('open');
        } else {
          this.openGameDetail(sc);
        }
      });
    });
  }

  async openGameDetail(game) {
    const dialog = document.getElementById('game-detail-modal');
    const body = document.getElementById('game-detail-body');
    if (!dialog || !body) return;

    let priceRowsHtml = `<li>Giá tiêu chuẩn: <strong>${Number(game.price).toLocaleString('vi-VN')}đ / người</strong></li>`;
    try {
      const res = await api.get(`/games/${game.apiId}/prices`);
      const prices = res?.data?.prices || [];
      if (prices.length > 0) {
        priceRowsHtml = prices.map(p => `
          <li>${escapeHtml(p.time_slot || p.label || 'Khung giờ')}: <strong>${Number(p.price || game.price).toLocaleString('vi-VN')}đ</strong></li>
        `).join('');
      }
    } catch {
      // fallback to base price
    }

    body.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;">
        <div>
          <span class="badge badge-gold">${escapeHtml(game.genre)}</span>
          ${game.is18Plus ? '<span class="badge badge-18" style="margin-left:6px;">18+</span>' : ''}
          <h2 style="font-family:var(--font-display);margin:8px 0 0;color:#f8fafc;">${escapeHtml(game.title)}</h2>
        </div>
        <button id="btn-close-game-detail" class="btn btn-ghost btn-sm" style="padding:4px 10px;">✕</button>
      </div>
      <div style="display:grid;grid-template-columns:150px 1fr;gap:18px;align-items:start;margin-bottom:18px;">
        <img src="${escapeHtml(game.poster)}" alt="${escapeHtml(game.title)}" style="width:100%;border-radius:8px;aspect-ratio:2/3;object-fit:cover;" />
        <div>
          <p style="color:#CBD5E1;font-size:0.9rem;line-height:1.55;margin:0 0 12px;">${escapeHtml(game.description)}</p>
          <div style="font-family:var(--font-mono);font-size:0.84rem;color:#D4AF37;margin-bottom:10px;">
            👥 ${game.minPlayers}–${game.maxPlayers} người &bull; ⏱️ ${Math.round(game.duration / 60 * 10) / 10} giờ
          </div>
          <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:10px 14px;">
            <div style="font-size:0.8rem;color:#94A3B8;margin-bottom:6px;text-transform:uppercase;">Bảng giá &amp; Cọc 50.000đ</div>
            <ul style="list-style:none;padding:0;margin:0;display:grid;gap:4px;font-size:0.88rem;color:#f8fafc;">
              ${priceRowsHtml}
            </ul>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="btn-cancel-game-detail" class="btn btn-ghost btn-sm">Đóng</button>
        <button id="btn-create-room-from-game" class="btn btn-gold btn-sm">Tạo phòng kịch bản này &rarr;</button>
      </div>
    `;

    body.querySelector('#btn-close-game-detail')?.addEventListener('click', () => dialog.close());
    body.querySelector('#btn-cancel-game-detail')?.addEventListener('click', () => dialog.close());
    body.querySelector('#btn-create-room-from-game')?.addEventListener('click', () => {
      dialog.close();
      this.onSelectGame({ id: game.apiId, name: game.title, min_players: game.minPlayers, max_players: game.maxPlayers });
    });

    dialog.showModal();
  }
}
