import { gamesApi } from '../../api/games-api.js';
import { motion } from '../../utils/motion.js';

const DEMO_GAMES = [
  {
    id: 1,
    title: 'Cô Gái Bên Cửa Sổ',
    category: 'Kinh dị',
    description: 'Án mạng bí ẩn xoay quanh căn biệt thự cổ và khung cửa sổ tầng hai trong đêm mưa bão.',
    min_players: 5,
    max_players: 6,
    duration_minutes: 210,
    is_18_plus: 0,
    price_day_vnd: 150000,
    price_evening_vnd: 180000,
  },
  {
    id: 2,
    title: 'Gương',
    category: 'Tâm lý',
    description: 'Khi chiếc gương bạc cổ xưa phản chiếu những bí mật đen tối không một ai muốn thừa nhận.',
    min_players: 6,
    max_players: 6,
    duration_minutes: 240,
    is_18_plus: 0,
    price_day_vnd: 160000,
    price_evening_vnd: 190000,
  },
  {
    id: 3,
    title: '1 Giờ 30',
    category: 'Trinh thám',
    description: 'Cuộc gọi đẫm máu lúc 1 giờ 30 sáng mở ra chuỗi suy luận nghẹt thở giữa 7 nghi phạm.',
    min_players: 5,
    max_players: 7,
    duration_minutes: 210,
    is_18_plus: 0,
    price_day_vnd: 150000,
    price_evening_vnd: 180000,
  },
  {
    id: 4,
    title: 'Vòng Lặp Mưu Sát Đảo Mèo (18+)',
    category: 'Kinh dị',
    description: 'Hòn đảo cô lập giữa cơn bão cuồng phong và vòng lặp thời gian án mạng đẫm máu không hồi kết.',
    min_players: 6,
    max_players: 7,
    duration_minutes: 270,
    is_18_plus: 1,
    price_day_vnd: 180000,
    price_evening_vnd: 220000,
  },
  {
    id: 5,
    title: 'Khách Điếm Hồ Ly',
    category: 'Cổ trang',
    description: 'Đêm sương mù tại khách điếm ven rừng sâu cùng truyền thuyết hồ ly chín đuôi đoạt mệnh.',
    min_players: 6,
    max_players: 8,
    duration_minutes: 240,
    is_18_plus: 0,
    price_day_vnd: 160000,
    price_evening_vnd: 195000,
  },
];

export class GamesPage {
  constructor(mountElement) {
    this.container = mountElement;
    this.state = {
      status: 'idle', // 'idle' | 'loading' | 'success' | 'error'
      games: [],
      selectedCategory: 'ALL',
      searchQuery: '',
      isDemoFallback: false,
    };
  }

  async loadGames() {
    this.state.status = 'loading';
    this.render();

    try {
      const response = await gamesApi.listGames();
      if (response && response.success && Array.isArray(response.data) && response.data.length > 0) {
        this.state.games = response.data;
        this.state.isDemoFallback = false;
      } else {
        this.state.games = DEMO_GAMES;
        this.state.isDemoFallback = true;
      }
      this.state.status = 'success';
    } catch {
      // Graceful fallback to demo seeds if server is offline
      this.state.games = DEMO_GAMES;
      this.state.isDemoFallback = true;
      this.state.status = 'success';
    } finally {
      this.render();
      const grid = this.container.querySelector('.games-grid');
      if (grid) {
        motion.animateGameCards(grid);
      }
    }
  }

  getFilteredGames() {
    return this.state.games.filter((game) => {
      const matchCat =
        this.state.selectedCategory === 'ALL' ||
        game.category.toLowerCase() === this.state.selectedCategory.toLowerCase();
      const matchQuery =
        !this.state.searchQuery ||
        game.title.toLowerCase().includes(this.state.searchQuery.toLowerCase()) ||
        (game.description && game.description.toLowerCase().includes(this.state.searchQuery.toLowerCase()));
      return matchCat && matchQuery;
    });
  }

  render() {
    const { status, selectedCategory, isDemoFallback } = this.state;
    const filteredGames = this.getFilteredGames();

    const wrapper = document.createElement('div');
    wrapper.className = 'games-page-wrapper';

    // Toolbar (Filter & Search)
    const toolbar = document.createElement('div');
    toolbar.className = 'catalog-toolbar';

    const filterGroup = document.createElement('div');
    filterGroup.className = 'filter-pill-group';

    const categories = ['ALL', 'Trinh thám', 'Kinh dị', 'Tâm lý', 'Cổ trang'];
    categories.forEach((cat) => {
      const btn = document.createElement('button');
      btn.className = `filter-pill ${selectedCategory === cat ? 'active' : ''}`;
      btn.textContent = cat === 'ALL' ? 'Tất cả thể loại' : cat;
      btn.addEventListener('click', () => {
        this.state.selectedCategory = cat;
        this.render();
        const grid = this.container.querySelector('.games-grid');
        if (grid) motion.animateGameCards(grid);
      });
      motion.bindButtonHover(btn);
      filterGroup.appendChild(btn);
    });

    toolbar.appendChild(filterGroup);

    // Fallback notice
    if (isDemoFallback) {
      const notice = document.createElement('div');
      notice.className = 'demo-notice-banner';
      notice.innerHTML = `
        <span class="notice-icon">✨</span>
        <span>Hiển thị dữ liệu mẫu kịch bản Jubensha (MySQL Seed Baseline V16). Kết nối live API backend tại cổng <code>/api/games</code>.</span>
      `;
      wrapper.appendChild(notice);
    }

    wrapper.appendChild(toolbar);

    // Grid Container
    const grid = document.createElement('div');
    grid.className = 'games-grid';

    if (filteredGames.length === 0) {
      const emptyBox = document.createElement('div');
      emptyBox.className = 'card empty-box';
      emptyBox.innerHTML = `
        <p style="color: var(--color-text-muted); font-size: 1.1rem;">Không tìm thấy kịch bản nào phù hợp với bộ lọc hiện tại.</p>
      `;
      grid.appendChild(emptyBox);
    } else {
      filteredGames.forEach((game) => {
        const card = document.createElement('div');
        card.className = 'game-card';

        const is18 = Boolean(game.is_18_plus);
        const formatMoney = (vnd) => new Intl.NumberFormat('vi-VN').format(vnd) + ' đ';

        card.innerHTML = `
          <div class="game-card-header">
            <span class="game-category-tag">${game.category}</span>
            ${is18 ? '<span class="badge-18">18+</span>' : ''}
          </div>
          <h3 class="game-card-title">${game.title}</h3>
          <p class="game-card-desc">${game.description || 'Chưa có mô tả chi tiết.'}</p>
          <div class="game-card-meta">
            <div class="meta-item">
              <span class="meta-icon">👥</span>
              <span>${game.min_players} - ${game.max_players} người</span>
            </div>
            <div class="meta-item">
              <span class="meta-icon">⏱️</span>
              <span>${game.duration_minutes} phút</span>
            </div>
            <div class="meta-item">
              <span class="meta-icon">🎫</span>
              <span>Từ ${formatMoney(game.price_day_vnd)}</span>
            </div>
          </div>
          <div class="game-card-actions">
            <button class="btn btn-primary btn-book" data-game-id="${game.id}">
              <span>Tạo phòng ngay</span>
              <span class="btn-arrow">→</span>
            </button>
          </div>
        `;

        const bookBtn = card.querySelector('.btn-book');
        if (bookBtn) {
          motion.bindButtonHover(bookBtn);
          bookBtn.addEventListener('click', () => {
            alert(`Tính năng Đặt phòng cho kịch bản "${game.title}" đã sẵn sàng trên Backend endpoint POST /api/rooms!`);
          });
        }

        grid.appendChild(card);
      });
    }

    wrapper.appendChild(grid);

    if (typeof this.container.replaceChildren === 'function') {
      this.container.replaceChildren(wrapper);
    } else {
      this.container.innerHTML = '';
      this.container.appendChild(wrapper);
    }
  }
}
