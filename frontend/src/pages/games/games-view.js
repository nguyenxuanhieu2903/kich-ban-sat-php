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
    let dayPrice = `${Number(game.price || 159000).toLocaleString('vi-VN')}đ`;
    let nightPrice = `${Number((game.price || 159000) + 40000).toLocaleString('vi-VN')}đ`;

    try {
      const res = await api.get(`/games/${game.apiId}/prices`);
      const prices = res?.data?.prices || [];
      if (prices.length >= 2) {
        dayPrice = `${Number(prices[0].price || game.price).toLocaleString('vi-VN')}đ`;
        nightPrice = `${Number(prices[1].price || game.price).toLocaleString('vi-VN')}đ`;
      } else if (prices.length === 1) {
        dayPrice = `${Number(prices[0].price || game.price).toLocaleString('vi-VN')}đ`;
      }
    } catch {
      // fallback to base price
    }

    const rawDifficulty = game.difficulty || '★★★ (Trung bình)';
    const formattedDifficulty = escapeHtml(rawDifficulty).replace(
      /(★+)/g,
      '<span class="difficulty-stars">$1</span>'
    );
    const playersDisplay = `${game.minPlayers}–${game.maxPlayers} người`;
    const durationDisplay = `~ ${Math.round((game.duration / 60) * 10) / 10} tiếng`;
    const fallbackUrl = `https://placehold.co/400x600/181512/d4af37?text=${encodeURIComponent(game.title)}`;
    const storyParagraphs = (game.description || 'Nội dung vụ án đang được cập nhật...')
      .split('\n')
      .filter(line => line.trim())
      .map(p => `<p>${escapeHtml(p)}</p>`)
      .join('');

    this.container.innerHTML = `
      <div class="container" style="padding: 32px 20px 60px;">
        <div style="margin-bottom: 20px;">
          <button type="button" id="btn-back-to-catalog" class="btn btn-ghost btn-sm">
            &larr; Quay lại Kho Kịch Bản
          </button>
        </div>

        <!-- PHẦN TRÊN: POSTER RĂNG CƯA TRÁI + VÉ GIẤY KEM PHẢI -->
        <div class="detail-hero-grid">
          <div class="detail-poster-box">
            <img
              src="${escapeHtml(game.poster)}"
              alt="${escapeHtml(game.title)}"
              onerror="this.onerror=null;this.src='${fallbackUrl}'"
            />
            ${game.isBestSeller ? '<span class="poster-badge">BEST SELLER</span>' : ''}
            ${game.is18Plus ? '<span class="poster-tag">18+</span>' : ''}
          </div>

          <div class="detail-info-box">
            <div>
              <div class="section-kicker">KỊCH BẢN SUY LUẬN PHÁ ÁN</div>
              <h1 class="detail-title">
                ${escapeHtml(game.title)}
                ${game.isBestSeller ? '<span class="detail-subtitle">(Best Seller)</span>' : ''}
              </h1>

              <ul class="detail-meta-list">
                <li><strong>Thể loại:</strong> <span>${escapeHtml(game.genre || 'Trinh thám, Tâm lý')}</span></li>
                <li><strong>Độ khó:</strong> <span>${formattedDifficulty}</span></li>
                <li><strong>Số lượng:</strong> <span>${escapeHtml(playersDisplay)}</span></li>
                <li><strong>Thời gian:</strong> <span>${escapeHtml(durationDisplay)}</span></li>
              </ul>

              <div class="detail-price-box">
                <div class="detail-price-title">Giá vé tham gia:</div>
                <ul>
                  <li>Khung giờ 9h00 – 19h59: <strong>${escapeHtml(dayPrice)} / 1 người</strong></li>
                  <li>Khung giờ từ 20h00: <strong>${escapeHtml(nightPrice)} / 1 người</strong></li>
                </ul>
              </div>
            </div>

            <div class="detail-actions">
              <button type="button" id="btn-create-room-from-detail" class="btn btn-gold">Tạo phòng chơi kịch bản này</button>
              <a href="#rooms" id="btn-view-rooms-from-detail" class="btn btn-ghost" style="text-decoration: none;">Xem phòng đang tuyển</a>
            </div>
          </div>
        </div>

        <!-- PHẦN DƯỚI: NỘI DUNG CỐT TRUYỆN -->
        <div class="detail-synopsis-box">
          <h2 class="detail-synopsis-heading">NỘI DUNG:</h2>
          <div class="detail-synopsis-text">
            ${storyParagraphs}
          </div>
        </div>
      </div>
    `;

    document.getElementById('btn-back-to-catalog')?.addEventListener('click', () => {
      this.render();
    });

    document.getElementById('btn-create-room-from-detail')?.addEventListener('click', () => {
      this.onSelectGame({
        id: game.apiId,
        name: game.title,
        min_players: game.minPlayers,
        max_players: game.maxPlayers
      });
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}
