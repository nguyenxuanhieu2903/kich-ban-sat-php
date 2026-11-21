/**
 * Game Catalog Page (#/games)
 * Editorial Noir Gallery: 42 Jubensha Dossiers with interactive filter pills, age restrictions, and 2-tier pricing ledgers.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { generateGamePosterSvg, renderGameCoverElement } from '../../content/assets.js';
import { formatCurrency } from '../../utils/format.js';

export class GamesPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.games = [];
    this.search = '';
    this.selectedGenre = '';
    this.is18Only = false;
    this.selectedPlayers = '';
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-games container' });

    // 1. Editorial Noir Header
    const header = h('div', { className: 'catalog-header-noir' },
      h('span', { className: 'hero-eyebrow' }, renderIcon('sparkles'), ' THƯ VIỆN KỊCH BẢN THƯỢNG HẠNG'),
      h('h1', { className: 'catalog-header-title' }, 'Kho Tàng 42 Hồ Sơ Án Mạng'),
      h('p', { className: 'catalog-header-lead' },
        'Tuyển tập 42 kịch bản Jubensha tuyển chọn với đầy đủ bối cảnh, mức độ khó và phân khúc độ tuổi. Bảng giá cấu hình động theo 2 khung giờ trong ngày.'
      )
    );
    container.appendChild(header);

    // 2. Interactive Noir Filter Toolbar
    const filterBar = h('div', { className: 'catalog-filter-noir' },
      // Search Row
      h('div', { className: 'catalog-search-row' },
        h('div', { className: 'search-input-group' },
          renderIcon('search', 'search-icon'),
          h('input', {
            type: 'text',
            className: 'noir-search-input',
            placeholder: 'Tìm kiếm tên hồ sơ án mạng hoặc từ khóa thể loại...',
            value: this.search,
            oninput: (e) => {
              this.search = e.target.value;
              this.applyFiltersAndRenderGrid();
            },
          })
        )
      ),
      // Filter Pills Row
      h('div', { className: 'filter-pills-row' },
        h('span', { className: 'filter-pill-label' }, 'Thể loại:'),
        ...['', 'Trinh thám', 'Kinh dị', 'Hardcore', 'Cổ trang', 'Hài hước'].map((genre) =>
          h('button', {
            className: `filter-pill-btn ${this.selectedGenre === genre ? 'active' : ''}`,
            onclick: () => {
              this.selectedGenre = genre;
              this.updateFilterPills();
              this.applyFiltersAndRenderGrid();
            },
          }, genre ? genre : 'Tất cả')
        ),
        h('span', { className: 'filter-pill-label', style: 'margin-left: 0.5rem;' }, 'Số người:'),
        ...['', '5', '6', '7', '8'].map((p) =>
          h('button', {
            className: `filter-pill-btn ${this.selectedPlayers === p ? 'active' : ''}`,
            onclick: () => {
              this.selectedPlayers = p;
              this.updateFilterPills();
              this.applyFiltersAndRenderGrid();
            },
          }, p ? `${p} người` : 'Tất cả')
        ),
        h('button', {
          className: `filter-pill-btn btn-pill-18 ${this.is18Only ? 'active' : ''}`,
          onclick: () => {
            this.is18Only = !this.is18Only;
            this.updateFilterPills();
            this.applyFiltersAndRenderGrid();
          },
        }, renderIcon('shield'), ' Chỉ xem 18+')
      )
    );
    this.filterBarElement = filterBar;
    container.appendChild(filterBar);

    // 3. Counter & Result Stats
    this.statsBar = h('div', { className: 'flex-between text-muted', style: 'margin-bottom: 1.5rem; font-size: 0.88rem;' });
    container.appendChild(this.statsBar);

    // 4. Asymmetric Magazine Grid
    this.gridContainer = h('div', { className: 'catalog-editorial-grid' },
      h('div', { className: 'loading-pulse' }, 'Đang giải mã thư viện hồ sơ án mạng...')
    );
    container.appendChild(this.gridContainer);

    this.mountPoint.appendChild(container);

    // Load initial games
    await this.loadGames();
  }

  updateFilterPills() {
    if (!this.filterBarElement) return;
    const btns = this.filterBarElement.querySelectorAll('.filter-pill-btn');
    btns.forEach((btn) => {
      const text = btn.textContent.trim();
      if (text.includes('18+')) {
        btn.className = `filter-pill-btn btn-pill-18 ${this.is18Only ? 'active' : ''}`;
      }
    });
  }

  async loadGames() {
    try {
      const res = await apiService.getGames();
      this.games = res.data;
      this.applyFiltersAndRenderGrid();
    } catch (err) {
      this.gridContainer.innerHTML = `<div class="error-state"><p class="text-danger">Lỗi tải dữ liệu kịch bản: ${err.message}</p></div>`;
    }
  }

  applyFiltersAndRenderGrid() {
    if (!this.gridContainer) return;
    this.gridContainer.innerHTML = '';

    let list = [...this.games];

    if (this.search.trim()) {
      const q = this.search.toLowerCase().trim();
      list = list.filter((g) => g.title.toLowerCase().includes(q) || (g.genre && g.genre.toLowerCase().includes(q)));
    }
    if (this.selectedGenre) {
      list = list.filter((g) => g.genre && g.genre.includes(this.selectedGenre));
    }
    if (this.is18Only) {
      list = list.filter((g) => g.is_age_restricted);
    }
    if (this.selectedPlayers) {
      const p = parseInt(this.selectedPlayers, 10);
      list = list.filter((g) => g.min_players <= p && g.max_players >= p);
    }

    if (this.statsBar) {
      this.statsBar.innerHTML = `
        <span>Hiển thị <strong>${list.length}</strong> / ${this.games.length} hồ sơ án mạng</span>
        <span class="font-mono text-gold">${this.is18Only ? '• Đang lọc án 18+' : ''}</span>
      `;
    }

    if (list.length === 0) {
      this.gridContainer.appendChild(
        h('div', { className: 'empty-state text-center', style: 'grid-column: 1 / -1; padding: 4rem 1rem;' },
          h('p', { className: 'text-muted', style: 'font-size: 1.1rem;' }, 'Không tìm thấy hồ sơ nào khớp với bộ lọc điều tra.')
        )
      );
      return;
    }

    list.forEach((game) => {
      const posterWrapper = h('div', { className: 'game-folio-poster' });
      posterWrapper.appendChild(renderGameCoverElement(game, { width: 360, height: 240 }));

      const item = h('div', { className: 'game-folio-item' },
        // Poster Cover
        posterWrapper,
        // Body Details
        h('div', { className: 'game-folio-body' },
          h('div', { className: 'game-tags-row' },
            h('span', { className: 'game-genre-tag' }, game.genre || 'Trinh thám'),
            game.is_age_restricted ? h('span', { className: 'game-18-badge' }, '18+') : null,
            !game.verified ? h('span', { className: 'badge-unverified' }, 'Chờ thẩm định') : null
          ),
          h('h3', { className: 'game-folio-title' },
            h('a', { href: `#/games/${game.id}` }, game.title)
          ),
          h('div', { className: 'game-folio-meta' },
            h('span', {}, renderIcon('users'), ` ${game.min_players}–${game.max_players} thám tử`),
            h('span', {}, renderIcon('clock'), ` ${game.duration_minutes || 240}p`)
          ),
          // 2-Tier Pricing Snapshot
          h('div', { className: 'game-folio-price' },
            h('span', {}, 'Ngày: ', h('strong', {}, formatCurrency(game.price_day_vnd))),
            h('span', {}, 'Tối: ', h('strong', { className: 'highlight-gold' }, formatCurrency(game.price_night_vnd)))
          ),
          h('div', { style: 'margin-top: auto;' },
            h('a', { className: 'btn btn-secondary btn-block', href: `#/games/${game.id}` },
              'Xem Hồ Sơ & Mở Phòng'
            )
          )
        )
      );
      this.gridContainer.appendChild(item);
    });
  }
}
