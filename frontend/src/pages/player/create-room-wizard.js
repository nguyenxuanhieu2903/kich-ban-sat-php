/**
 * Create Room Wizard (#/player/create-room)
 * 4-Stage Case Opening Dossier:
 * Stage 1: Select Game (with search & 18+ indicators)
 * Stage 2: Select Date (>= 1 day advance per menu rules)
 * Stage 3: Select Start Time (Strictly :00 or :30, Day vs Night tiers)
 * Stage 4: Quote Breakdown & Idempotent Confirmation
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth } from '../../utils/auth.js';
import { renderPriceBreakdown } from '../../components/price-breakdown.js';
import { isValidSlotTime } from '../../utils/rules.js';
import { showToast } from '../../components/toast.js';

export class CreateRoomWizard {
  constructor(mountPoint, { query, router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.preselectedGameId = query.game_id || query.gameId || null;
    this.currentStep = 1;

    this.selectedGame = null;
    this.selectedDate = ''; // 'YYYY-MM-DD'
    this.selectedTime = '19:30'; // Default :00 or :30
    this.quoteSnapshot = null;
    this.games = [];
    this.gameSearchQuery = '';
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-create-wizard container', style: 'max-width: 960px;' });

    // 1. Wizard Header
    const header = h('div', { className: 'wizard-header-noir' },
      h('span', { className: 'dossier-badge-pill' }, renderIcon('sparkles'), ' KHỞI TẠO VỤ ÁN MỚI'),
      h('h1', { style: 'font-size: 2.8rem; margin-bottom: 0.5rem;' }, 'Mở Phòng Chơi Jubensha'),
      h('p', { className: 'text-muted', style: 'max-width: 650px; margin: 0 auto;' },
        'Khởi tạo phòng chơi và đề xuất khung giờ bắt đầu linh hoạt. Hệ thống có 4 giờ để chiêu mộ đủ thám tử trước khi chuyển giao cho Ban Quản Trị.'
      )
    );
    container.appendChild(header);

    // 2. Wizard Progress Bar
    this.progressNav = h('div', { className: 'wizard-progress-noir' });
    this.renderProgressBar();
    container.appendChild(this.progressNav);

    // 3. Step Content Mount
    this.stepContainer = h('div', {
      className: 'wizard-step-container card-dark',
      style: 'padding: 2.5rem;'
    });
    container.appendChild(this.stepContainer);

    this.mountPoint.appendChild(container);

    await this.loadInitialGames();
    this.renderCurrentStep();
  }

  renderProgressBar() {
    this.progressNav.innerHTML = '';
    const steps = [
      { num: 1, title: 'Chọn kịch bản' },
      { num: 2, title: 'Chọn ngày án' },
      { num: 3, title: 'Chọn giờ (:00/:30)' },
      { num: 4, title: 'Báo giá & Xác nhận' },
    ];

    const list = h('div', { className: 'wizard-nav-list' });
    steps.forEach((s) => {
      const isCurrent = s.num === this.currentStep;
      const isDone = s.num < this.currentStep;
      list.appendChild(
        h('div', { className: `wizard-nav-item ${isCurrent ? 'active' : ''} ${isDone ? 'done' : ''}` },
          h('span', { className: 'wizard-step-circle' }, isDone ? '✓' : `0${s.num}`),
          h('span', { className: 'wizard-step-name' }, s.title)
        )
      );
    });
    this.progressNav.appendChild(list);
  }

  async loadInitialGames() {
    const res = await apiService.getGames();
    this.games = res.data;
    if (this.preselectedGameId) {
      this.selectedGame = this.games.find((g) => 
        String(g.id) === String(this.preselectedGameId) ||
        g.id === `game_${String(this.preselectedGameId).padStart(3, '0')}`
      ) || null;
      if (this.selectedGame) {
        this.currentStep = 2; // Auto-advance to date picker if pre-selected
      }
    }
  }

  renderCurrentStep() {
    this.renderProgressBar();
    this.stepContainer.innerHTML = '';

    if (this.currentStep === 1) {
      this.renderStep1SelectGame();
    } else if (this.currentStep === 2) {
      this.renderStep2SelectDate();
    } else if (this.currentStep === 3) {
      this.renderStep3SelectTime();
    } else if (this.currentStep === 4) {
      this.renderStep4ReviewQuote();
    }
  }

  // --- Stage 1: Select Game ---
  renderStep1SelectGame() {
    const box = h('div', {},
      h('h3', { className: 'charter-title', style: 'font-size: 1.8rem; margin-bottom: 0.5rem;' }, 'Giai Đoạn 01: Lựa Chọn Hồ Sơ Kịch Bản'),
      h('p', { className: 'text-muted mb-3' }, 'Tìm kiếm và chọn một kịch bản trong kho tàng 42 hồ sơ để khởi tạo phòng:'),
      
      // Inline Search Filter
      h('div', { style: 'margin-bottom: 1.5rem;' },
        h('input', {
          type: 'text',
          className: 'noir-search-input',
          placeholder: 'Tìm kiếm nhanh tên kịch bản...',
          value: this.gameSearchQuery,
          oninput: (e) => {
            this.gameSearchQuery = e.target.value.toLowerCase().trim();
            this.renderFilteredGamesList();
          },
        })
      ),

      h('div', { id: 'wizard-games-list', style: 'display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; max-height: 480px; overflow-y: auto; padding-right: 0.5rem;' })
    );

    this.stepContainer.appendChild(box);
    this.renderFilteredGamesList();
  }

  renderFilteredGamesList() {
    const container = this.stepContainer.querySelector('#wizard-games-list');
    if (!container) return;
    container.innerHTML = '';

    let filtered = this.games;
    if (this.gameSearchQuery) {
      filtered = filtered.filter((g) => g.title.toLowerCase().includes(this.gameSearchQuery) || (g.genre && g.genre.toLowerCase().includes(this.gameSearchQuery)));
    }

    if (filtered.length === 0) {
      container.appendChild(h('p', { className: 'text-muted', style: 'grid-column: 1 / -1; padding: 2rem;' }, 'Không tìm thấy kịch bản phù hợp.'));
      return;
    }

    filtered.forEach((game) => {
      const isSelected = this.selectedGame?.id === game.id;
      const card = h('div', {
        style: `background: ${isSelected ? 'rgba(196, 163, 107, 0.1)' : 'var(--color-bg-surface-2)'}; border: 1px solid ${isSelected ? 'var(--color-accent-gold)' : 'var(--color-border-subtle)'}; padding: 1.25rem; border-radius: var(--radius-xs); cursor: pointer; transition: all 0.2s ease;`,
        onclick: () => {
          this.selectedGame = game;
          this.currentStep = 2;
          this.renderCurrentStep();
        },
      },
        h('div', { className: 'game-tags-row', style: 'margin-bottom: 0.4rem;' },
          h('span', { className: 'game-genre-tag' }, game.genre || 'Trinh thám'),
          game.is_age_restricted ? h('span', { className: 'game-18-badge' }, '18+') : null
        ),
        h('h4', { style: 'font-size: 1.2rem; color: var(--color-text-primary); margin-bottom: 0.35rem;' }, game.title),
        h('p', { className: 'text-muted font-mono', style: 'font-size: 0.8rem; margin: 0;' },
          `${game.min_players}–${game.max_players} thám tử • ${game.duration_minutes || 240} phút`
        )
      );
      container.appendChild(card);
    });
  }

  // --- Stage 2: Select Date ---
  renderStep2SelectDate() {
    const minDate = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().split('T')[0]; // Tối thiểu ngày mai
    if (!this.selectedDate) this.selectedDate = minDate;

    const box = h('div', {},
      h('h3', { className: 'charter-title', style: 'font-size: 1.8rem; margin-bottom: 0.5rem;' }, 'Giai Đoạn 02: Ấn Định Ngày Khởi Hành'),
      h('p', { className: 'text-muted mb-4' },
        'Kịch bản đã chọn: ', h('strong', { className: 'highlight-gold' }, this.selectedGame.title)
      ),
      h('div', { style: 'max-width: 380px; margin-bottom: 1.5rem;' },
        h('label', { className: 'form-label text-gold font-mono', style: 'display: block; margin-bottom: 0.5rem;' }, 'Chọn ngày diễn ra phiên chơi:'),
        h('input', {
          type: 'date',
          className: 'form-control',
          min: minDate,
          value: this.selectedDate,
          style: 'width: 100%; font-size: 1.05rem; padding: 0.75rem;',
          onchange: (e) => {
            this.selectedDate = e.target.value;
          },
        })
      ),
      h('div', { className: 'promo-rules-box' },
        renderIcon('sparkles'), ' ',
        h('strong', { className: 'highlight-gold' }, 'MẸO SĂN ƯU ĐÃI:'),
        ' Đặt trước ít nhất 1 ngày và khởi hành vào Thứ Ba (09:00–19:00) để được giảm ngay 30% giá vé trọn gói mọi kịch bản!'
      ),
      h('div', { className: 'flex-between mt-4', style: 'padding-top: 1.5rem; border-top: 1px solid var(--color-noir-divider);' },
        h('button', {
          className: 'btn btn-secondary',
          onclick: () => { this.currentStep = 1; this.renderCurrentStep(); },
        }, '← Chọn lại kịch bản'),
        h('button', {
          className: 'btn btn-primary btn-lg',
          onclick: () => {
            if (!this.selectedDate) {
              showToast('Vui lòng chọn ngày tổ chức phiên chơi.', 'error');
              return;
            }
            this.currentStep = 3;
            this.renderCurrentStep();
          },
        }, 'Tiếp tục: Chọn giờ khởi hành →')
      )
    );
    this.stepContainer.appendChild(box);
  }

  // --- Stage 3: Select Time Slot (Strictly :00 or :30) ---
  renderStep3SelectTime() {
    const validSlots = [];
    for (let h = 9; h <= 22; h++) {
      const hh = String(h).padStart(2, '0');
      validSlots.push(`${hh}:00`);
      if (h < 22) validSlots.push(`${hh}:30`);
    }

    const box = h('div', {},
      h('h3', { className: 'charter-title', style: 'font-size: 1.8rem; margin-bottom: 0.5rem;' }, 'Giai Đoạn 03: Chọn Giờ Bắt Đầu (:00 hoặc :30)'),
      h('p', { className: 'text-muted mb-3' },
        'Hệ thống Jubensha không gò bó suất cố định. Giờ bắt đầu bắt buộc ở mốc ',
        h('strong', { className: 'text-gold' }, ':00'), ' hoặc ', h('strong', { className: 'text-gold' }, ':30'),
        ' để Quản trị viên điều phối Quản trò và mật thất.'
      ),
      h('div', { className: 'slots-buttons-grid' },
        ...validSlots.map((slot) => {
          const isSelected = this.selectedTime === slot;
          const isNight = parseInt(slot.split(':')[0], 10) >= 20;
          return h('button', {
            type: 'button',
            className: `btn-slot-choice ${isSelected ? 'selected' : ''}`,
            onclick: () => {
              this.selectedTime = slot;
              this.renderStep3SelectTime();
            },
          },
            h('span', { className: 'slot-time-text' }, slot),
            h('span', { className: 'slot-tier-tag' }, isNight ? 'Khung tối (≥20h)' : 'Khung ngày')
          );
        })
      ),
      h('div', { className: 'flex-between mt-4', style: 'padding-top: 1.5rem; border-top: 1px solid var(--color-noir-divider);' },
        h('button', {
          className: 'btn btn-secondary',
          onclick: () => { this.currentStep = 2; this.renderCurrentStep(); },
        }, '← Chọn lại ngày'),
        h('button', {
          className: 'btn btn-primary btn-lg',
          onclick: async () => {
            if (!isValidSlotTime(this.selectedTime)) {
              showToast('Giờ bắt đầu bắt buộc phải đúng mốc phút :00 hoặc :30.', 'error');
              return;
            }
            await this.calculateQuoteAndProceed();
          },
        }, 'Tiếp tục: Bóc tách báo giá →')
      )
    );
    this.stepContainer.appendChild(box);
  }

  async calculateQuoteAndProceed() {
    try {
      const sessionStart = `${this.selectedDate}T${this.selectedTime}:00`;
      const res = await apiService.getQuote({
        game_id: this.selectedGame.id,
        session_start_time: sessionStart,
        player_count: this.selectedGame.min_players,
      });
      this.quoteSnapshot = res.data;
      this.currentStep = 4;
      this.renderCurrentStep();
    } catch (err) {
      showToast(`Không thể tính báo giá: ${err.message}`, 'error');
    }
  }

  // --- Stage 4: Review Quote & Confirm ---
  renderStep4ReviewQuote() {
    const sessionStart = `${this.selectedDate}T${this.selectedTime}:00`;

    const box = h('div', {},
      h('h3', { className: 'charter-title', style: 'font-size: 1.8rem; margin-bottom: 0.5rem;' }, 'Giai Đoạn 04: Bóc Tách Báo Giá & Niêm Phong Phòng'),
      
      // Summary Dossier
      h('div', { style: 'background: #12151D; border: 1px solid rgba(255, 255, 255, 0.08); border-left: 3px solid #C4A36B; padding: 1.5rem; clip-path: polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 14px 100%, 0 calc(100% - 14px)); margin-bottom: 1.5rem;' },
        h('h4', { className: 'highlight-gold', style: 'font-size: 1.3rem; margin-bottom: 0.75rem;' }, this.selectedGame.title),
        h('div', { style: 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.9rem;' },
          h('div', {}, '📅 Ngày & Giờ: ', h('strong', { className: 'text-light font-mono' }, `${this.selectedDate} lúc ${this.selectedTime}`)),
          h('div', {}, '👥 Quy mô bàn: ', h('strong', { className: 'text-light font-mono' }, `${this.selectedGame.min_players}–${this.selectedGame.max_players} thám tử`)),
          h('div', {}, '⏳ Hạn chiêu mộ: ', h('strong', { className: 'text-light font-mono' }, '4 giờ kể từ lúc mở')),
          h('div', {}, '💰 Cọc giữ ghế: ', h('strong', { className: 'text-gold font-mono' }, '50.000 VNĐ / người (Cố định)'))
        )
      ),

      // Transparent Price Breakdown
      renderPriceBreakdown(this.quoteSnapshot, { title: 'Bảng Chiết Tính Toàn Phòng & Phần Vé Từng Người' }),

      // Final CTA
      h('div', { className: 'flex-between mt-4', style: 'padding-top: 1.5rem; border-top: 1px solid var(--color-noir-divider);' },
        h('button', {
          className: 'btn btn-secondary',
          onclick: () => { this.currentStep = 3; this.renderCurrentStep(); },
        }, '← Chọn lại giờ'),
        h('button', {
          className: 'btn btn-primary btn-lg',
          id: 'btn-confirm-create-room',
          onclick: () => this.handleConfirmCreateRoom(sessionStart),
        }, renderIcon('check'), ' Xác Nhận Mở Phòng Ngay')
      )
    );
    this.stepContainer.appendChild(box);
  }

  async handleConfirmCreateRoom(sessionStartTime) {
    const btn = document.getElementById('btn-confirm-create-room');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Đang niêm phong mở phòng...';
    }

    try {
      const res = await apiService.createRoom({
        game_id: this.selectedGame.id,
        session_start_time: sessionStartTime,
      });

      const newRoom = res.data;
      showToast(`Mở phòng ${newRoom.code} thành công! Hạn chiêu mộ 4 giờ bắt đầu.`, 'success');
      this.router.navigate(`/player/rooms/${newRoom.id}`);
    } catch (err) {
      showToast(`Mở phòng thất bại: ${err.message}`, 'error');
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Xác Nhận Mở Phòng Ngay';
      }
    }
  }
}
