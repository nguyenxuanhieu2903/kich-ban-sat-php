import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml, sanitizeUrl } from '../../utils/sanitize.js';

export class HomeView {
  /**
   * @param {HTMLElement} container Mount point container
   * @param {Object} options Options containing onNavigate and onOpenAuth
   */
  constructor(container, options = {}) {
    this.container = container;
    this.onNavigate = options.onNavigate || ((tab) => { window.location.hash = `#${tab}`; });
    this.onOpenAuth = options.onOpenAuth || (() => {});

    this.games = [];
    this.promotions = [];
    this.searchQuery = '';
    this.selectedPlayers = '';

    this.isLoadingGames = false;
    this.isLoadingPromotions = false;
    this.gamesError = null;
    this.promotionsError = null;
  }

  async render() {
    this.container.innerHTML = `
      <!-- 1. HERO SECTION -->
      <section class="home-hero-section">
        <div class="home-hero-badge">
          <span></span> NỀN TẢNG KỊCH BẢN SÁT & JUBENSHA CHUYÊN NGHIỆP
        </div>

        <h1 class="home-hero-title">
          Đắm Chìm Vào Thế Giới Trinh Thám & Nhập Vai Phá Án
        </h1>

        <p class="home-hero-desc">
          Khám phá kho tàng kịch bản Jubensha ly kỳ, kết nối người chơi thông minh,
          hòa mình vào những cốt truyện bí ẩn cùng Game Master (DM) tận tâm và chuyên nghiệp.
        </p>

        <div class="home-hero-actions">
          <button id="hero-cta-games" class="btn btn-primary" style="padding: 12px 24px; font-size: 1rem; border-radius: var(--radius-md);">
             Khám Phá Kịch Bản
          </button>
          <button id="hero-cta-rooms" class="btn btn-secondary" style="padding: 12px 24px; font-size: 1rem; border-radius: var(--radius-md); background: rgba(30, 41, 59, 0.8); border: 1px solid var(--color-border); color: #f8fafc;">
             Xem Sảnh Phòng Chờ
          </button>
          <button id="hero-cta-dm" class="btn" style="padding: 12px 20px; font-size: 0.95rem; border-radius: var(--radius-md); background: transparent; border: 1px solid rgba(245, 158, 11, 0.4); color: #fbbf24;">
             Dành Cho Quản Trò (DM)
          </button>
        </div>

        <!-- Hero Feature Pillars -->
        <div class="home-hero-features">
          <div class="home-feature-pill">
            <span class="home-feature-icon"></span>
            <div>
              <div class="home-feature-title">Kịch Bản Chọn Lọc</div>
              <div class="home-feature-sub">Đa dạng thể loại & độ khó</div>
            </div>
          </div>
          <div class="home-feature-pill">
            <span class="home-feature-icon"></span>
            <div>
              <div class="home-feature-title">Ghép Phòng Linh Hoạt</div>
              <div class="home-feature-sub">Tìm đồng đội trong vài phút</div>
            </div>
          </div>
          <div class="home-feature-pill">
            <span class="home-feature-icon"></span>
            <div>
              <div class="home-feature-title">Cọc Cố Định 50.000đ</div>
              <div class="home-feature-sub">Chuẩn V16 không phát sinh phí ẩn</div>
            </div>
          </div>
          <div class="home-feature-pill">
            <span class="home-feature-icon"></span>
            <div>
              <div class="home-feature-title">Hoàn Tiền Trước 24h</div>
              <div class="home-feature-sub">Quy tắc vàng bảo vệ quyền lợi</div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. GAME DISCOVERY & SEARCH BAR -->
      <section class="home-section" id="section-discovery">
        <div class="home-search-bar">
          <div class="home-search-input-wrap">
            <span class="home-search-icon"></span>
            <input
              type="text"
              id="home-search-input"
              class="form-input home-search-input"
              placeholder="Tìm kiếm kịch bản theo tên hoặc cốt truyện..."
              value="${escapeHtml(this.searchQuery)}"
            />
          </div>

          <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 0.85rem; color: var(--color-text-muted); white-space: nowrap;">Số người chơi:</label>
            <select id="home-filter-players" class="form-input" style="padding: 8px 12px; font-size: 0.85rem;">
              <option value="">Tất cả</option>
              <option value="5" ${this.selectedPlayers === '5' ? 'selected' : ''}>5 người</option>
              <option value="6" ${this.selectedPlayers === '6' ? 'selected' : ''}>6 người</option>
              <option value="7" ${this.selectedPlayers === '7' ? 'selected' : ''}>7 người</option>
              <option value="8" ${this.selectedPlayers === '8' ? 'selected' : ''}>8 người</option>
              <option value="10" ${this.selectedPlayers === '10' ? 'selected' : ''}>10 người</option>
            </select>
          </div>

          <button id="home-btn-search" class="btn btn-primary" style="padding: 8px 18px;">
            Tìm kiếm
          </button>
          <button id="home-btn-reset-filter" class="btn" style="padding: 8px 14px; background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted);">
            Đặt lại
          </button>
        </div>
      </section>

      <!-- 3. FEATURED GAMES SECTION -->
      <section class="home-section" id="section-featured-games">
        <div class="home-section-header">
          <div class="home-section-title-wrap">
            <h2><span>[CAN NGUOI THE CHO]</span> Kịch Bản Nổi Bật</h2>
            <p>Những vụ án ly kỳ được cộng đồng yêu thích nhất, sẵn sàng khởi tạo phòng chơi ngay</p>
          </div>
          <button id="btn-see-all-games" class="btn" style="background: transparent; border: 1px solid var(--color-border); color: #60a5fa; font-size: 0.88rem; gap: 6px;">
            Xem toàn bộ kịch bản <span>→</span>
          </button>
        </div>

        <div id="home-games-container">
          <!-- Populated by fetchGames -->
        </div>
      </section>

      <!-- 4. PROMOTIONS SECTION -->
      <section class="home-section" id="section-promotions">
        <div class="home-section-header">
          <div class="home-section-title-wrap">
            <h2><span></span> Chương Trình Ưu Đãi Đang Áp Dụng</h2>
            <p>Ưu đãi được tính toán tự động tại Backend theo quy chuẩn V16 khi đặt phòng và thanh toán</p>
          </div>
        </div>

        <div id="home-promotions-container">
          <!-- Populated by fetchPromotions -->
        </div>
      </section>

      <!-- 5. HOW IT WORKS (4 STEPS) -->
      <section class="home-section" id="section-how-it-works">
        <div class="home-section-header">
          <div class="home-section-title-wrap">
            <h2><span></span> Quy Trình Trải Nghiệm Đơn Giản</h2>
            <p>4 bước nhanh chóng từ việc chọn kịch bản đến buổi phá án đầy cảm xúc</p>
          </div>
        </div>

        <div class="steps-grid">
          <div class="step-card">
            <div class="step-number">1</div>
            <h3 class="step-title">Chọn Kịch Bản</h3>
            <p class="step-desc">Khám phá nội dung, cốt truyện bí ẩn, thời lượng và khung giờ chơi phù hợp với nhóm bạn.</p>
          </div>
          <div class="step-card">
            <div class="step-number">2</div>
            <h3 class="step-title">Tạo Hoặc Ghép Phòng</h3>
            <p class="step-desc">Mở phòng riêng mời bạn bè hoặc tham gia sảnh chờ để hệ thống ghép thám tử đồng hành.</p>
          </div>
          <div class="step-card">
            <div class="step-number">3</div>
            <h3 class="step-title">Đặt Cọc 50.000đ/Người</h3>
            <p class="step-desc">Mức cọc cố định chuẩn V16, tự động giữ chỗ phòng chơi và hoàn tiền 100% nếu hủy trước 24 giờ.</p>
          </div>
          <div class="step-card">
            <div class="step-number">4</div>
            <h3 class="step-title">Nhập Vai & Phá Án</h3>
            <p class="step-desc">Có mặt tại phòng chủ đề, nhận trang phục, tài liệu và tận hưởng sự dẫn dắt lôi cuốn từ Game Master.</p>
          </div>
        </div>
      </section>

      <!-- 6. VENUES & BRANCHES -->
      <section class="home-section" id="section-venues">
        <div class="home-section-header">
          <div class="home-section-title-wrap">
            <h2><span></span> Hệ Thống Chi Nhánh Chuyên Nghiệp</h2>
            <p>Không gian nhập vai chủ đề được đầu tư bài bản, âm thanh và ánh sáng chuẩn điện ảnh</p>
          </div>
        </div>

        <div class="venues-grid">
          <div class="venue-card">
            <div class="venue-header">
              <h3 class="venue-name">Kịch Bản Sát Station - Chi nhánh Quận 1</h3>
              <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399;">Hoạt động</span>
            </div>
            <div class="venue-address">
              <span></span> 123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh
            </div>
            <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin: 0 0 14px 0;">
              Chi nhánh trung tâm với không gian nhập vai phong cách Châu Âu cổ điển.
              Trang bị: <em>Phòng Sherlock Holmes, Phòng VIP Huyết Tộc Dracula</em>.
            </p>
            <div style="font-size: 0.82rem; color: #60a5fa; margin-top: auto;">
               Hotline đặt phòng: 0901 234 567
            </div>
          </div>

          <div class="venue-card">
            <div class="venue-header">
              <h3 class="venue-name">Kịch Bản Sát Hub - Chi nhánh Bình Thạnh</h3>
              <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399;">Hoạt động</span>
            </div>
            <div class="venue-address">
              <span></span> 45 Điện Biên Phủ, Phường 15, Quận Bình Thạnh, TP. Hồ Chí Minh
            </div>
            <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin: 0 0 14px 0;">
              Không gian hiện đại chuyên kịch bản kinh dị & trinh thám Á Đông huyền bí.
              Trang bị: <em>Phòng Cổ Trang Á Đông, hệ thống âm thanh vòm và đạo cụ thực tế</em>.
            </p>
            <div style="font-size: 0.82rem; color: #60a5fa; margin-top: auto;">
               Hotline đặt phòng: 0907 654 321
            </div>
          </div>
        </div>
      </section>
    `;

    this.setupEventListeners();

    // Fetch data in parallel
    await Promise.all([
      this.fetchGames(),
      this.fetchPromotions()
    ]);
  }

  setupEventListeners() {
    // Hero CTAs
    const btnHeroGames = this.container.querySelector('#hero-cta-games');
    if (btnHeroGames) {
      btnHeroGames.onclick = () => {
        const sec = this.container.querySelector('#section-featured-games');
        if (sec) sec.scrollIntoView({ behavior: 'smooth' });
      };
    }

    const btnHeroRooms = this.container.querySelector('#hero-cta-rooms');
    if (btnHeroRooms) {
      btnHeroRooms.onclick = () => this.onNavigate('rooms');
    }

    const btnHeroDm = this.container.querySelector('#hero-cta-dm');
    if (btnHeroDm) {
      btnHeroDm.onclick = () => {
        const user = authState.getUser();
        if (user && (user.role === 'DM' || user.role === 'ADMIN')) {
          this.onNavigate('dm');
        } else {
          this.onOpenAuth('DM');
        }
      };
    }

    const btnSeeAllGames = this.container.querySelector('#btn-see-all-games');
    if (btnSeeAllGames) {
      btnSeeAllGames.onclick = () => this.onNavigate('games');
    }

    // Search and Filter controls
    const searchInput = this.container.querySelector('#home-search-input');
    const filterPlayers = this.container.querySelector('#home-filter-players');
    const btnSearch = this.container.querySelector('#home-btn-search');
    const btnReset = this.container.querySelector('#home-btn-reset-filter');

    if (btnSearch) {
      btnSearch.onclick = () => {
        this.searchQuery = searchInput.value.trim();
        this.selectedPlayers = filterPlayers.value;
        this.fetchGames();
      };
    }

    if (searchInput) {
      searchInput.onkeydown = (e) => {
        if (e.key === 'Enter') {
          this.searchQuery = searchInput.value.trim();
          this.selectedPlayers = filterPlayers.value;
          this.fetchGames();
        }
      };
    }

    if (filterPlayers) {
      filterPlayers.onchange = () => {
        this.selectedPlayers = filterPlayers.value;
        this.fetchGames();
      };
    }

    if (btnReset) {
      btnReset.onclick = () => {
        this.searchQuery = '';
        this.selectedPlayers = '';
        if (searchInput) searchInput.value = '';
        if (filterPlayers) filterPlayers.value = '';
        this.fetchGames();
      };
    }
  }

  async fetchGames() {
    const container = this.container.querySelector('#home-games-container');
    if (!container) return;

    this.isLoadingGames = true;
    this.gamesError = null;

    // Loading State: Render Skeleton Cards
    container.innerHTML = `
      <div class="home-games-grid">
        ${Array.from({ length: 3 }).map(() => `
          <div class="skeleton-card">
            <div class="skeleton-box" style="height: 180px; width: 100%;"></div>
            <div class="skeleton-box" style="height: 20px; width: 70%;"></div>
            <div class="skeleton-box" style="height: 14px; width: 95%;"></div>
            <div class="skeleton-box" style="height: 14px; width: 60%;"></div>
            <div class="skeleton-box" style="height: 38px; width: 100%; margin-top: 10px;"></div>
          </div>
        `).join('')}
      </div>
    `;

    try {
      const params = new URLSearchParams();
      params.append('limit', '6');
      if (this.selectedPlayers) {
        params.append('players', this.selectedPlayers);
      }
      if (this.searchQuery) {
        params.append('search', this.searchQuery);
      }

      const res = await api.get(`/games?${params.toString()}`);
      this.games = res?.data?.games || [];
      this.isLoadingGames = false;
      this.renderGamesGrid();
    } catch (err) {
      this.isLoadingGames = false;
      this.gamesError = err.message || 'Không thể tải danh sách kịch bản';
      this.renderGamesError();
    }
  }

  renderGamesGrid() {
    const container = this.container.querySelector('#home-games-container');
    if (!container) return;

    // Empty State
    if (!this.games || this.games.length === 0) {
      container.innerHTML = `
        <div class="state-empty">
          <div class="state-icon"></div>
          <h3 class="state-title">Không tìm thấy kịch bản phù hợp</h3>
          <p class="state-desc">Hãy thử thay đổi từ khóa tìm kiếm hoặc lọc theo số người chơi khác để khám phá các vụ án.</p>
          <button id="btn-empty-reset-games" class="btn btn-primary" style="padding: 8px 18px;">
            Xem tất cả kịch bản
          </button>
        </div>
      `;

      const btnReset = container.querySelector('#btn-empty-reset-games');
      if (btnReset) {
        btnReset.onclick = () => {
          this.searchQuery = '';
          this.selectedPlayers = '';
          const sInput = this.container.querySelector('#home-search-input');
          const pSelect = this.container.querySelector('#home-filter-players');
          if (sInput) sInput.value = '';
          if (pSelect) pSelect.value = '';
          this.fetchGames();
        };
      }
      return;
    }

    // Success State: Render Game Cards
    container.innerHTML = `
      <div class="home-games-grid">
        ${this.games.map(game => {
          const minPriceText = game.min_price ? `${Number(game.min_price).toLocaleString()}đ` : '180.000đ';
          const maxPriceText = game.max_price ? `${Number(game.max_price).toLocaleString()}đ` : '250.000đ';
          const priceDisplay = game.min_price === game.max_price
            ? minPriceText
            : `${minPriceText} - ${maxPriceText}`;

          const codeStr = 'HS-' + String(game.id).padStart(2, '0');
          const themeCode = escapeHtml(String(game.theme_code || 'DETECTIVE'));

          return `
            <div class="game-card">
              <div class="game-card-img-wrap" style="height: 110px; display: flex; align-items: center; justify-content: space-between; padding: 0 18px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 1px dashed rgba(201,162,94,0.35);">
                <span style="font-family: monospace; font-size: 0.82rem; color: #c9a25e; font-weight: 700;">HỒ SƠ #${escapeHtml(codeStr)} • ${themeCode}</span>
                <span class="game-card-badge duration" style="position: static;">${Number(game.duration_minutes) || 240} phút</span>
              </div>

              <div class="game-card-body">
                <h3 class="game-card-title">${escapeHtml(game.name)}</h3>
                <p class="game-card-desc">
                  ${escapeHtml(game.description || 'Kịch bản nhập vai trinh thám đặc sắc với cốt truyện ly kỳ, dẫn dắt các thám tử đi từ bất ngờ này đến bất ngờ khác.')}
                </p>

                <div class="game-card-meta">
                  <span>${Number(game.min_players)} - ${Number(game.max_players)} người chơi</span>
                  <span>Chi nhánh TP.HCM</span>
                </div>

                <div class="game-card-price-row">
                  <div>
                    <span style="color: var(--color-text-muted); font-size: 0.75rem; display: block;">GIÁ VÉ / NGƯỜI</span>
                    <span class="game-price-val">${priceDisplay}</span>
                  </div>
                  <div style="text-align: right;">
                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.75rem;">
                      Cọc: 50.000đ
                    </span>
                  </div>
                </div>

                <div class="game-card-actions">
                  <button class="btn btn-view-prices" data-id="${Number(game.id)}" style="font-size: 0.85rem; padding: 8px; background: rgba(30, 41, 59, 0.8); border: 1px solid var(--color-border); color: #94a3b8;">
                     Bảng giá
                  </button>
                  <button class="btn btn-primary btn-join-room" data-id="${Number(game.id)}" style="font-size: 0.85rem; padding: 8px;">
                     Ghép phòng
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;

    // Bind price modal events
    container.querySelectorAll('.btn-view-prices').forEach(btn => {
      btn.onclick = () => this.showPriceModal(btn.dataset.id);
    });

    // Bind join/create room events
    container.querySelectorAll('.btn-join-room').forEach(btn => {
      btn.onclick = () => {
        // If not authenticated, prompt login modal
        if (!authState.isAuthenticated()) {
          toast.info('Vui lòng đăng nhập để bắt đầu tạo hoặc ghép phòng!');
          this.onOpenAuth();
          return;
        }
        // Navigate to rooms tab
        this.onNavigate('rooms');
      };
    });
  }

  renderGamesError() {
    const container = this.container.querySelector('#home-games-container');
    if (!container) return;

    container.innerHTML = `
      <div class="state-error">
        <div class="state-icon">[Luu y]</div>
        <h3 class="state-title">Lỗi tải danh sách kịch bản</h3>
        <p class="state-desc">${escapeHtml(this.gamesError)}</p>
        <button id="btn-retry-games" class="btn btn-primary" style="padding: 8px 18px;">
           Thử lại
        </button>
      </div>
    `;

    const btnRetry = container.querySelector('#btn-retry-games');
    if (btnRetry) {
      btnRetry.onclick = () => this.fetchGames();
    }
  }

  async fetchPromotions() {
    const container = this.container.querySelector('#home-promotions-container');
    if (!container) return;

    this.isLoadingPromotions = true;
    this.promotionsError = null;

    // Loading State
    container.innerHTML = `
      <div class="home-promo-grid">
        ${Array.from({ length: 2 }).map(() => `
          <div class="skeleton-card" style="padding: 24px;">
            <div class="skeleton-box" style="height: 24px; width: 40%; margin-bottom: 8px;"></div>
            <div class="skeleton-box" style="height: 36px; width: 70%; margin-bottom: 8px;"></div>
            <div class="skeleton-box" style="height: 18px; width: 90%;"></div>
            <div class="skeleton-box" style="height: 14px; width: 80%;"></div>
            <div class="skeleton-box" style="height: 40px; width: 100%; margin-top: 16px;"></div>
          </div>
        `).join('')}
      </div>
    `;

    try {
      const res = await api.get('/promotions');
      this.promotions = res?.data?.promotions || [];
      this.isLoadingPromotions = false;
      this.renderPromotionsGrid();
    } catch (err) {
      this.isLoadingPromotions = false;
      this.promotionsError = err.message || 'Không thể tải danh sách ưu đãi';
      this.renderPromotionsError();
    }
  }

  renderPromotionsGrid() {
    const container = this.container.querySelector('#home-promotions-container');
    if (!container) return;

    // Empty State
    if (!this.promotions || this.promotions.length === 0) {
      container.innerHTML = `
        <div class="state-empty">
          <div class="state-icon"></div>
          <h3 class="state-title">Chưa có ưu đãi mới</h3>
          <p class="state-desc">Hãy theo dõi thường xuyên để không bỏ lỡ các chương trình khuyến mãi đặc sắc từ Kịch Bản Sát.</p>
        </div>
      `;
      return;
    }

    // Success State
    container.innerHTML = `
      <div class="home-promo-grid">
        ${this.promotions.map(promo => {
          const isPercent = promo.discount_type === 'PERCENT';
          const discountDisplay = isPercent
            ? `GIẢM ${promo.discount_value}%`
            : `GIẢM ${Number(promo.discount_value).toLocaleString()}đ/người`;

          const rules = promo.rules || {};
          const weekdaysMap = { 1: 'Thứ Hai', 2: 'Thứ Ba', 3: 'Thứ Tư', 4: 'Thứ Năm', 5: 'Thứ Sáu', 6: 'Thứ Bảy', 7: 'Chủ Nhật' };
          let weekdayText = '';
          if (Array.isArray(rules.weekdays)) {
            weekdayText = rules.weekdays.map(d => weekdaysMap[d] || `T${d}`).join(', ');
          }

          return `
            <div class="promo-card">
              <span class="promo-card-tag">
                <span>⭐</span> ${promo.promotion_type === 'STUDENT' ? 'Ưu Đãi Học Đường' : 'Ưu Đãi Trong Tuần'}
              </span>

              <div class="promo-card-value">${escapeHtml(discountDisplay)}</div>
              <h3 class="promo-card-title">${escapeHtml(promo.name)}</h3>

              <ul class="promo-card-rules">
                ${weekdayText ? `<li><span class="bullet"></span> Áp dụng: <strong>${escapeHtml(weekdayText)}</strong></li>` : ''}
                <li><span class="bullet"></span> Khung giờ: <strong>09:00 – 19:00</strong></li>
                ${rules.minimum_booking_hours ? `<li><span class="bullet"></span> Đặt trước tối thiểu <strong>${Number(rules.minimum_booking_hours)} giờ</strong></li>` : ''}
                ${rules.requires_student_verification ? `<li><span class="bullet"></span> Xuất trình thẻ HSSV hợp lệ khi nhận phòng</li>` : ''}
                <li><span class="bullet"></span> Chuẩn V16: Không áp dụng đồng thời với ưu đãi khác</li>
              </ul>

              <div class="promo-code-bar">
                <div>
                  <span style="font-size: 0.72rem; color: var(--color-text-muted); display: block;">MÃ KHUYẾN MÃI</span>
                  <span class="promo-code-text">${escapeHtml(promo.code)}</span>
                </div>
                <button class="btn-copy-code" data-code="${escapeHtml(promo.code)}">
                   Sao chép mã
                </button>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;

    // Bind copy promo code buttons
    container.querySelectorAll('.btn-copy-code').forEach(btn => {
      btn.onclick = () => {
        const code = btn.dataset.code;
        this.copyPromotionCode(code);
      };
    });
  }

  renderPromotionsError() {
    const container = this.container.querySelector('#home-promotions-container');
    if (!container) return;

    container.innerHTML = `
      <div class="state-error">
        <div class="state-icon">[Luu y]</div>
        <h3 class="state-title">Lỗi tải khuyến mãi</h3>
        <p class="state-desc">${escapeHtml(this.promotionsError)}</p>
        <button id="btn-retry-promotions" class="btn btn-primary" style="padding: 8px 18px;">
           Thử lại
        </button>
      </div>
    `;

    const btnRetry = container.querySelector('#btn-retry-promotions');
    if (btnRetry) {
      btnRetry.onclick = () => this.fetchPromotions();
    }
  }

  async copyPromotionCode(code) {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(code);
      } else {
        // Fallback for non-secure context without layout shift
        const input = document.createElement('input');
        input.value = code;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        input.style.pointerEvents = 'none';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
      }
      toast.success(`Đã sao chép mã ưu đãi: ${code}`);
    } catch {
      toast.info(`Mã ưu đãi của bạn là: ${code}`);
    }
  }

  async showPriceModal(gameId) {
    try {
      const res = await api.get(`/games/${gameId}/prices`);
      const prices = res?.data?.prices || [];
      const gameName = res?.data?.game_name || 'Kịch bản';

      const dialog = document.createElement('dialog');
      dialog.className = 'custom-modal';
      dialog.style.cssText = `
        background: var(--color-bg-card, #1e293b);
        color: var(--color-text-main, #f8fafc);
        border: 1px solid var(--color-border, #334155);
        border-radius: 12px;
        padding: 24px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.6);
      `;

      dialog.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h3 style="margin: 0; font-size: 1.2rem; color: #f8fafc;">Bảng giá: ${escapeHtml(gameName)}</h3>
          <button id="modal-close-x" style="background: none; border: none; color: #94a3b8; font-size: 1.4rem; cursor: pointer;">&times;</button>
        </div>
        <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 14px;">
          * Mức cọc cố định theo chuẩn V16: <strong style="color:#10b981;">50.000 VNĐ / người</strong>.
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; margin-bottom: 20px;">
          <thead>
            <tr style="border-bottom: 1px solid var(--color-border); text-align: left; color: #94a3b8;">
              <th style="padding: 8px;">Khung giờ</th>
              <th style="padding: 8px; text-align: right;">Giá vé / người</th>
            </tr>
          </thead>
          <tbody>
            ${prices.length > 0 ? prices.map(p => `
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                <td style="padding: 10px 8px;">
                  <span style="font-weight: 500;">${escapeHtml(p.start_time.substring(0, 5))} - ${escapeHtml(p.end_time.substring(0, 5))}</span>
                  ${p.start_time >= '20:00:00'
                    ? '<span style="font-size: 0.75rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">Giờ tối</span>'
                    : '<span style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">Giờ ngày</span>'}
                </td>
                <td style="padding: 10px 8px; text-align: right; color: #10b981; font-weight: 600;">
                  ${Number(p.price_per_person).toLocaleString()} VNĐ
                </td>
              </tr>
            `).join('') : `
              <tr>
                <td colspan="2" style="padding: 16px; text-align: center; color: #94a3b8;">Chưa có biểu giá cụ thể</td>
              </tr>
            `}
          </tbody>
        </table>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button id="modal-btn-close" class="btn" style="background: rgba(255,255,255,0.05); color: #94a3b8;">
            Đóng
          </button>
          <button id="modal-btn-book" class="btn btn-primary">
             Ghép phòng kịch bản này
          </button>
        </div>
      `;

      document.body.appendChild(dialog);
      dialog.showModal();

      // Ensure dialog is removed from DOM whenever closed (ESC key or method)
      dialog.addEventListener('close', () => {
        dialog.remove();
      });

      dialog.querySelector('#modal-close-x').onclick = () => dialog.close();
      dialog.querySelector('#modal-btn-close').onclick = () => dialog.close();
      dialog.querySelector('#modal-btn-book').onclick = () => {
        dialog.close();
        if (!authState.isAuthenticated()) {
          toast.info('Vui lòng đăng nhập để bắt đầu ghép phòng!');
          this.onOpenAuth();
          return;
        }
        this.onNavigate('rooms');
      };

      dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
      });
    } catch (err) {
      toast.error(err.message || 'Lỗi khi tải bảng giá kịch bản');
    }
  }
}
