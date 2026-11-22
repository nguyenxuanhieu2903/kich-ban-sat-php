import { api } from '../../api/api-client.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml } from '../../utils/sanitize.js';
import { ALL_SCRIPTS } from '../../data/scripts.data.js';

export class GamesView {
  constructor(container, onSelectGameToCreateRoom = () => {}) {
    this.container = container;
    this.onSelectGameToCreateRoom = onSelectGameToCreateRoom;
    this.allScripts = [];
    this.filteredScripts = [];
    this.currentPage = 1;
    this.pageSize = 9;
    this.pendingDossierScript = null;
    this.confirmedAge = false;

    // Bộ lọc trạng thái
    this.filters = {
      search: '',
      players: '',
      genre: '',
      difficulty: '',
      age: ''
    };
  }

  async render() {
    this.container.innerHTML = `
      <!-- HEADER TIÊU ĐỀ & NÚT TẠO PHÒNG -->
      <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
        <div>
          <div style="font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: 0.16em; color: var(--gold); text-transform: uppercase; margin-bottom: 6px;">
            THƯ VIỆN HỒ SƠ MẬT
          </div>
          <h1 style="font-family: var(--font-display); font-size: clamp(1.8rem, 3.8vw, 2.4rem); font-weight: 800; margin: 0; color: #f8fafc;">
            Kho Kịch Bản (<span id="total-count">0</span>)
          </h1>
        </div>
        <button id="btn-header-create-room" class="btn btn-gold" style="padding: 10px 18px; font-weight: 700; font-size: 0.9rem;">
          + Tạo phòng chơi mới
        </button>
      </div>

      <!-- BỘ LỌC KẾT HỢP (SEARCH + 4 DROPDOWNS) -->
      <div style="background: var(--bg-surface); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 18px; margin-bottom: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 12px;">
        <input 
          type="search" 
          id="f-search" 
          class="form-control" 
          placeholder="Tìm tên kịch bản..." 
          autocomplete="off" 
          value="${escapeHtml(this.filters.search)}"
        />
        
        <select id="f-players" class="form-control">
          <option value="">Số người: Tất cả</option>
          <option value="5" ${this.filters.players === '5' ? 'selected' : ''}>5 người</option>
          <option value="6" ${this.filters.players === '6' ? 'selected' : ''}>6 người</option>
          <option value="7" ${this.filters.players === '7' ? 'selected' : ''}>7 người</option>
          <option value="8" ${this.filters.players === '8' ? 'selected' : ''}>8 người</option>
        </select>

        <select id="f-genre" class="form-control">
          <option value="">Thể loại: Tất cả</option>
          <option value="Tâm lý / Kinh dị" ${this.filters.genre === 'Tâm lý / Kinh dị' ? 'selected' : ''}>Tâm lý / Kinh dị</option>
          <option value="Trinh thám / Siêu nhiên" ${this.filters.genre === 'Trinh thám / Siêu nhiên' ? 'selected' : ''}>Trinh thám / Siêu nhiên</option>
          <option value="Cổ điển / Phá án" ${this.filters.genre === 'Cổ điển / Phá án' ? 'selected' : ''}>Cổ điển / Phá án</option>
          <option value="Sinh tồn / Đấu trí" ${this.filters.genre === 'Sinh tồn / Đấu trí' ? 'selected' : ''}>Sinh tồn / Đấu trí</option>
          <option value="Gia tộc / Tâm lý" ${this.filters.genre === 'Gia tộc / Tâm lý' ? 'selected' : ''}>Gia tộc / Tâm lý</option>
          <option value="Cổ trang / Âm mưu" ${this.filters.genre === 'Cổ trang / Âm mưu' ? 'selected' : ''}>Cổ trang / Âm mưu</option>
        </select>

        <select id="f-diff" class="form-control">
          <option value="">Độ khó: Tất cả</option>
          <option value="Dễ" ${this.filters.difficulty === 'Dễ' ? 'selected' : ''}>Dễ</option>
          <option value="Trung bình" ${this.filters.difficulty === 'Trung bình' ? 'selected' : ''}>Trung bình</option>
          <option value="Khó" ${this.filters.difficulty === 'Khó' ? 'selected' : ''}>Khó</option>
          <option value="Cực khó" ${this.filters.difficulty === 'Cực khó' ? 'selected' : ''}>Cực khó</option>
        </select>

        <select id="f-age" class="form-control">
          <option value="">Độ tuổi: Tất cả</option>
          <option value="18" ${this.filters.age === '18' ? 'selected' : ''}>Chỉ kịch bản 18+</option>
          <option value="all" ${this.filters.age === 'all' ? 'selected' : ''}>Phổ thông (Mọi lứa tuổi)</option>
        </select>
      </div>

      <!-- DANH SÁCH THẺ KỊCH BẢN (GRID) -->
      <div class="scripts-grid" id="catalog-grid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">
          Đang nạp hồ sơ kịch bản...
        </div>
      </div>

      <!-- EMPTY STATE KHI KHÔNG TÌM THẤY -->
      <div id="empty-state" style="display: none; text-align: center; padding: 54px 20px; background: var(--bg-surface); border-radius: 8px; border: 1px dashed var(--gold-border); margin-top: 10px;">
        <h3 style="font-family: var(--font-display); font-size: 1.4rem; margin-bottom: 8px; color: #f8fafc;">
          Không tìm thấy kịch bản phù hợp
        </h3>
        <p class="text-muted" style="margin-bottom: 18px; font-size: 0.9rem;">
          Hãy thử đổi từ khóa tìm kiếm hoặc nới lỏng điều kiện số người, thể loại.
        </p>
        <button class="btn btn-gold" id="btn-reset-filters">Đặt lại bộ lọc</button>
      </div>

      <!-- THANH PHÂN TRANG -->
      <div id="pagination-bar" style="display: flex; justify-content: center; gap: 8px; margin-top: 36px; flex-wrap: wrap;"></div>

      <!-- POPUP XÁC NHẬN 18+ -->
      <dialog id="age-modal" class="custom-modal" style="background: var(--paper-cream); color: var(--ink-black); border-radius: 10px; border: 1px solid #D5CCB8; padding: 26px; max-width: 480px; width: 90%;">
        <span class="badge badge-18" style="margin-bottom: 8px;">XÁC NHẬN ĐỦ 18 TUỔI</span>
        <h3 style="font-family: var(--font-display); font-size: 1.45rem; margin: 12px 0 8px; color: var(--ink-black);">
          Nội dung giới hạn độ tuổi
        </h3>
        <p style="font-size: 0.92rem; color: #555; margin-bottom: 24px; line-height: 1.5;">
          Kịch bản <strong id="age-script-name" style="color: var(--crimson);"></strong> có gắn nhãn <strong>18+</strong> (yếu tố tâm lý kinh dị hoặc án mạng nhạy cảm). Vui lòng xác nhận bạn đã đủ 18 tuổi trước khi xem hồ sơ chi tiết.
        </p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
          <button class="btn btn-ghost" style="color: var(--ink-black); border-color: #999;" id="age-cancel">Hủy</button>
          <button class="btn btn-crimson" id="age-ok">Tôi đã đủ 18 tuổi</button>
        </div>
      </dialog>
    `;

    this.bindEvents();
    await this.loadScripts();
  }

  bindEvents() {
    // Header create room
    this.container.querySelector('#btn-header-create-room')?.addEventListener('click', () => {
      this.onSelectGameToCreateRoom(null);
    });

    // Search input
    const searchInput = this.container.querySelector('#f-search');
    searchInput?.addEventListener('input', (e) => {
      this.filters.search = e.target.value.trim().toLowerCase();
      this.currentPage = 1;
      this.applyFiltersAndRender();
    });

    // Filter dropdowns
    ['f-players', 'f-genre', 'f-diff', 'f-age'].forEach(id => {
      const select = this.container.querySelector(`#${id}`);
      select?.addEventListener('change', (e) => {
        if (id === 'f-players') this.filters.players = e.target.value;
        if (id === 'f-genre') this.filters.genre = e.target.value;
        if (id === 'f-diff') this.filters.difficulty = e.target.value;
        if (id === 'f-age') this.filters.age = e.target.value;
        this.currentPage = 1;
        this.applyFiltersAndRender();
      });
    });

    // Reset filters
    this.container.querySelector('#btn-reset-filters')?.addEventListener('click', () => {
      this.filters = { search: '', players: '', genre: '', difficulty: '', age: '' };
      const search = this.container.querySelector('#f-search');
      if (search) search.value = '';
      ['f-players', 'f-genre', 'f-diff', 'f-age'].forEach(id => {
        const el = this.container.querySelector(`#${id}`);
        if (el) el.value = '';
      });
      this.currentPage = 1;
      this.applyFiltersAndRender();
    });

    // Age modal events
    const ageModal = this.container.querySelector('#age-modal');
    this.container.querySelector('#age-cancel')?.addEventListener('click', () => {
      ageModal?.close();
      this.pendingDossierScript = null;
    });
    this.container.querySelector('#age-ok')?.addEventListener('click', () => {
      this.confirmedAge = true;
      ageModal?.close();
      if (this.pendingDossierScript) {
        const script = this.pendingDossierScript;
        this.pendingDossierScript = null;
        this.showDossierModal(script);
      }
    });
  }

  async loadScripts() {
    try {
      // 1. Lấy dữ liệu games từ API backend
      let dbGames = [];
      try {
        const res = await api.get('/games');
        dbGames = res?.data?.games || [];
      } catch {
        dbGames = [];
      }

      // Map DB games chuẩn hóa theo cấu trúc card
      const mappedDbGames = dbGames.map((g, idx) => {
        const genres = ['Trinh thám / Siêu nhiên', 'Tâm lý / Kinh dị', 'Cổ điển / Phá án'];
        const diffs = ['Khó', 'Trung bình', 'Dễ'];
        return {
          id: String(g.id),
          dbId: g.id,
          code: `KBS-${g.id}`,
          title: g.name || g.title,
          genre: g.category || genres[idx % genres.length],
          playersMin: Number(g.min_players) || 5,
          playersMax: Number(g.max_players) || 8,
          difficulty: diffs[idx % diffs.length],
          durationHours: Number((g.duration_minutes / 60).toFixed(1)) || 3.5,
          price: g.min_price || 129000,
          priceText: g.min_price ? `${Math.round(g.min_price / 1000)}K` : '129K',
          is18Plus: idx === 0 || idx === 2, // Đánh dấu linh hoạt
          summary: g.description || 'Kịch bản nhập vai trinh thám đặc sắc với cốt truyện ly kỳ.',
          hook: 'Mỗi người một bí mật. Hung thủ đang ngồi ngay trong phòng.',
          image_url: g.image_url
        };
      });

      // 2. Kết hợp với kho 42 kịch bản chuẩn trong scripts.data.js
      const merged = [...mappedDbGames];
      for (const item of ALL_SCRIPTS) {
        if (!merged.some(m => m.title.toLowerCase() === item.title.toLowerCase())) {
          merged.push({
            ...item,
            id: String(item.id),
            priceText: `${Math.round(item.price / 1000)}K`
          });
        }
      }

      this.allScripts = merged;
      this.applyFiltersAndRender();
    } catch (err) {
      const grid = this.container.querySelector('#catalog-grid');
      if (grid) {
        grid.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-error);">${escapeHtml(err.message)}</div>`;
      }
    }
  }

  applyFiltersAndRender() {
    const { search, players, genre, difficulty, age } = this.filters;
    const reqPlayers = Number(players);

    this.filteredScripts = this.allScripts.filter(s => {
      // 1. Tìm kiếm từ khóa (tên hoặc tóm tắt)
      if (search) {
        const titleMatch = s.title.toLowerCase().includes(search);
        const codeMatch = (s.code || '').toLowerCase().includes(search);
        const summaryMatch = (s.summary || '').toLowerCase().includes(search);
        if (!titleMatch && !codeMatch && !summaryMatch) return false;
      }

      // 2. Lọc số người
      if (reqPlayers) {
        if (!(reqPlayers >= s.playersMin && reqPlayers <= s.playersMax)) return false;
      }

      // 3. Lọc thể loại
      if (genre && s.genre !== genre) return false;

      // 4. Lọc độ khó
      if (difficulty && s.difficulty !== difficulty) return false;

      // 5. Lọc độ tuổi 18+
      if (age === '18' && !s.is18Plus) return false;
      if (age === 'all' && s.is18Plus) return false;

      return true;
    });

    const totalCountEl = this.container.querySelector('#total-count');
    if (totalCountEl) totalCountEl.textContent = this.filteredScripts.length;

    this.renderGrid();
  }

  renderGrid() {
    const grid = this.container.querySelector('#catalog-grid');
    const emptyState = this.container.querySelector('#empty-state');
    const pagBar = this.container.querySelector('#pagination-bar');

    if (!grid) return;

    if (this.filteredScripts.length === 0) {
      grid.innerHTML = '';
      if (pagBar) pagBar.innerHTML = '';
      if (emptyState) emptyState.style.display = 'block';
      return;
    }

    if (emptyState) emptyState.style.display = 'none';

    // Phân trang
    const totalPages = Math.ceil(this.filteredScripts.length / this.pageSize);
    if (this.currentPage > totalPages) this.currentPage = 1;

    const start = (this.currentPage - 1) * this.pageSize;
    const pagedList = this.filteredScripts.slice(start, start + this.pageSize);

    grid.innerHTML = pagedList.map(s => `
      <article class="script-card" data-id="${escapeHtml(s.id)}">
        <!-- Chữ cái nền watermark mờ bí ẩn -->
        <div class="script-bg-letter">${escapeHtml(s.title.charAt(0))}</div>

        <div>
          <!-- Hàng badges -->
          <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px;">
            <span class="badge badge-gold">${s.playersMin}–${s.playersMax} người</span>
            <span class="badge badge-dark">${escapeHtml(s.difficulty || 'Trung bình')}</span>
            ${s.is18Plus ? '<span class="badge badge-18">18+</span>' : ''}
          </div>

          <!-- Tên kịch bản -->
          <h3 style="font-family: var(--font-display); font-size: 1.28rem; font-weight: 700; margin: 0 0 6px 0; color: #f8fafc; line-height: 1.25;">
            ${escapeHtml(s.title)}
          </h3>

          <!-- Thể loại & Thời lượng -->
          <div style="font-size: 0.82rem; color: var(--gold); margin-bottom: 10px; font-weight: 500;">
            ${escapeHtml(s.genre || 'Trinh thám')} • ${String(s.durationHours).replace('.', ',')} giờ
          </div>

          <!-- Tóm tắt cốt truyện -->
          <p class="text-muted" style="font-size: 0.88rem; line-height: 1.5; margin: 0 0 16px 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
            ${escapeHtml(s.summary || '')}
          </p>
        </div>

        <!-- Chân thẻ: Giá & Nút thao tác -->
        <div style="border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 14px; display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
          <strong style="color: var(--gold); font-size: 1.05rem;">
            ${s.priceText}/người
          </strong>
          
          <div style="display: flex; gap: 8px;">
            <button class="btn btn-crimson btn-sm btn-open-dossier" data-id="${escapeHtml(s.id)}">
              Xem hồ sơ
            </button>
            <button class="btn btn-gold btn-sm btn-card-create" data-id="${escapeHtml(s.id)}" title="Mở phòng chơi kịch bản này">
              + Tạo phòng
            </button>
          </div>
        </div>
      </article>
    `).join('');

    // Sự kiện nút "Xem hồ sơ"
    grid.querySelectorAll('.btn-open-dossier').forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();
        const scriptId = btn.dataset.id;
        const script = this.allScripts.find(s => s.id === scriptId);
        if (!script) return;

        // Nếu kịch bản 18+ và chưa xác nhận độ tuổi
        if (script.is18Plus && !this.confirmedAge) {
          this.pendingDossierScript = script;
          const ageModal = this.container.querySelector('#age-modal');
          const scriptName = this.container.querySelector('#age-script-name');
          if (scriptName) scriptName.textContent = script.title;
          ageModal?.showModal();
        } else {
          this.showDossierModal(script);
        }
      };
    });

    // Sự kiện nút "Tạo phòng" trên card
    grid.querySelectorAll('.btn-card-create').forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();
        const scriptId = btn.dataset.id;
        const script = this.allScripts.find(s => s.id === scriptId);
        this.onSelectGameToCreateRoom(script);
      };
    });

    // Render thanh phân trang
    this.renderPagination(totalPages);
  }

  renderPagination(totalPages) {
    const pagBar = this.container.querySelector('#pagination-bar');
    if (!pagBar) return;

    if (totalPages <= 1) {
      pagBar.innerHTML = '';
      return;
    }

    pagBar.innerHTML = Array.from({ length: totalPages }, (_, i) => i + 1).map(p => `
      <button class="btn btn-sm ${p === this.currentPage ? 'btn-gold' : 'btn-ghost'}" data-page="${p}">
        Trang ${p}
      </button>
    `).join('');

    pagBar.querySelectorAll('[data-page]').forEach(btn => {
      btn.onclick = () => {
        this.currentPage = Number(btn.dataset.page);
        this.renderGrid();
        window.scrollTo({ top: 120, behavior: 'smooth' });
      };
    });
  }

  async showDossierModal(script) {
    // Tải bảng giá từ backend nếu kịch bản có liên kết DB ID
    let prices = [];
    if (script.dbId) {
      try {
        const res = await api.get(`/games/${script.dbId}/prices`);
        prices = res?.data?.prices || [];
      } catch {
        prices = [];
      }
    }

    const modal = document.createElement('dialog');
    modal.className = 'custom-modal';
    modal.style.cssText = `
      background: var(--color-bg-card, #171311);
      color: var(--color-text-main, #f8fafc);
      border: 1px solid var(--gold-border, #d4af37);
      border-radius: 12px;
      padding: 26px;
      width: min(640px, 94vw);
      max-height: 85vh;
      overflow-y: auto;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.9);
    `;

    modal.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 12px;">
        <div>
          <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px;">
            <span class="badge badge-gold">${script.playersMin}–${script.playersMax} người</span>
            <span class="badge badge-dark">${escapeHtml(script.difficulty || 'Trung bình')}</span>
            ${script.is18Plus ? '<span class="badge badge-18">18+</span>' : ''}
          </div>
          <h2 style="margin: 0; font-family: var(--font-display); font-size: 1.55rem; color: #f8fafc;">
            ${escapeHtml(script.title)}
          </h2>
          <div style="font-size: 0.85rem; color: var(--gold); margin-top: 4px;">
            ${escapeHtml(script.genre || 'Trinh thám')} • Thời lượng: ${String(script.durationHours).replace('.', ',')} giờ
          </div>
        </div>
        <button id="modal-dossier-close" style="background: none; border: none; color: #94a3b8; font-size: 1.8rem; cursor: pointer; line-height: 1;">&times;</button>
      </div>

      <!-- Cốt truyện chi tiết & Hook -->
      <div style="margin-bottom: 20px;">
        <h4 style="font-size: 0.95rem; color: var(--gold); margin: 0 0 6px 0; text-transform: uppercase; font-family: var(--font-mono);">
          📜 Bối Cảnh & Manh Mối
        </h4>
        <p style="font-size: 0.92rem; line-height: 1.6; color: #e2e8f0; margin: 0 0 12px 0;">
          ${escapeHtml(script.summary)}
        </p>
        ${script.hook ? `
          <div style="background: rgba(196, 55, 55, 0.15); border-left: 3px solid var(--crimson); padding: 10px 14px; border-radius: 4px; font-style: italic; font-size: 0.88rem; color: #fca5a5;">
            "${escapeHtml(script.hook)}"
          </div>
        ` : ''}
      </div>

      <!-- Bảng giá vé theo khung giờ -->
      <div style="margin-bottom: 24px;">
        <h4 style="font-size: 0.95rem; color: var(--gold); margin: 0 0 8px 0; text-transform: uppercase; font-family: var(--font-mono);">
          🏷️ Bảng Giá Suất Diễn
        </h4>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
          <thead>
            <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.15); color: #94a3b8; text-align: left;">
              <th style="padding: 8px;">Khung giờ</th>
              <th style="padding: 8px; text-align: right;">Giá vé / người</th>
            </tr>
          </thead>
          <tbody>
            ${prices.length > 0 ? prices.map(p => `
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                <td style="padding: 8px;">
                  ${escapeHtml((p.start_time || '').slice(0, 5))} - ${escapeHtml((p.end_time || '').slice(0, 5))}
                  ${(p.start_time || '') >= '18:00' ? '<span style="font-size:0.75rem; background:rgba(245,158,11,0.2); color:#f59e0b; padding:1px 5px; border-radius:3px; margin-left:6px;">Giờ tối</span>' : ''}
                </td>
                <td style="padding: 8px; text-align: right; color: var(--gold); font-weight: 700;">
                  ${Number(p.price_per_person).toLocaleString()} VNĐ
                </td>
              </tr>
            `).join('') : `
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                <td style="padding: 8px;">Suất tiêu chuẩn (09:00 - 18:00)</td>
                <td style="padding: 8px; text-align: right; color: var(--gold); font-weight: 700;">
                  ${Number(script.price).toLocaleString()} VNĐ
                </td>
              </tr>
              <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                <td style="padding: 8px;">Suất tối bí mật (18:00 - 23:30)</td>
                <td style="padding: 8px; text-align: right; color: var(--gold); font-weight: 700;">
                  ${(Number(script.price) + 20000).toLocaleString()} VNĐ
                </td>
              </tr>
            `}
          </tbody>
        </table>
        <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 8px;">
          * Mức cọc tiêu chuẩn: <strong>50.000 VNĐ / người</strong> để giữ phòng trong 4 giờ.
        </div>
      </div>

      <!-- Nút hành động footer -->
      <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 16px;">
        <button id="modal-dossier-cancel" class="btn btn-ghost" style="padding: 8px 16px;">
          Đóng
        </button>
        <button id="modal-dossier-create" class="btn btn-gold" style="padding: 8px 18px; font-weight: 700;">
          + Tạo phòng với kịch bản này
        </button>
      </div>
    `;

    modal.querySelector('#modal-dossier-close').onclick = () => modal.close();
    modal.querySelector('#modal-dossier-cancel').onclick = () => modal.close();
    modal.querySelector('#modal-dossier-create').onclick = () => {
      modal.close();
      this.onSelectGameToCreateRoom(script);
    };

    modal.addEventListener('close', () => modal.remove());
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.close();
    });

    document.body.appendChild(modal);
    modal.showModal();
  }
}
