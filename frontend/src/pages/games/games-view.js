import { api } from '../../api/api-client.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml } from '../../utils/sanitize.js';
import { formatVnd, generateGamePosterSvg, normalizeScenario } from '../../utils/poster-svg.js';

export class GamesView {
  constructor(container, onSelectGameToCreateRoom = () => {}) {
    this.container = container;
    this.onSelectGameToCreateRoom = onSelectGameToCreateRoom;
    this.games = [];
    this.selectedPlayersFilter = '';
  }

  async render() {
    this.container.innerHTML = `
      <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0;">Danh mục Kịch Bản Sát (Murder Mystery)</h2>
          <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">Khám phá các kịch bản nhập vai trinh thám đặc sắc và bảng giá theo khung giờ</p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <label style="font-size: 0.85rem; color: var(--color-text-muted);">Lọc số người:</label>
          <select id="games-filter-players" class="form-input" style="padding: 6px 12px; font-size: 0.85rem;">
            <option value="">Tất cả</option>
            <option value="6">6 người</option>
            <option value="7">7 người</option>
            <option value="8">8 người</option>
            <option value="10">10 người</option>
          </select>
        </div>
      </div>

      <div id="games-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">
          Đang tải danh sách kịch bản...
        </div>
      </div>
    `;

    const filterSelect = this.container.querySelector('#games-filter-players');
    filterSelect.value = this.selectedPlayersFilter;
    filterSelect.onchange = (e) => {
      this.selectedPlayersFilter = e.target.value;
      this.fetchAndDisplayGames();
    };

    await this.fetchAndDisplayGames();
  }

  async fetchAndDisplayGames() {
    const grid = this.container.querySelector('#games-grid');
    grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">Đang tải dữ liệu...</div>';

    try {
      const endpoint = this.selectedPlayersFilter ? `/games?limit=100&players=${this.selectedPlayersFilter}` : '/games?limit=100';
      const res = await api.get(endpoint);
      this.games = res?.data?.games || [];

      if (this.games.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">Không tìm thấy kịch bản phù hợp</div>';
        return;
      }

      grid.className = 'catalog-editorial-grid';
      grid.removeAttribute('style');
      grid.innerHTML = this.games.map(raw => {
        const g = normalizeScenario(raw);
        const posterSvg = generateGamePosterSvg(g, { width: 360, height: 240 });
        const playerRange = g.minPlayers === g.maxPlayers ? `${g.minPlayers}` : `${g.minPlayers}-${g.maxPlayers}`;
        return `
        <article class="game-folio-item">
          <div class="game-folio-poster">${posterSvg}</div>
          <div class="game-folio-body">
            <div class="game-tags-row">
              <span class="game-genre-tag">${escapeHtml(g.genre)}</span>
              ${g.is18Plus ? '<span class="game-18-badge">18+</span>' : `<span class="game-diff-tag">Độ khó ${g.difficultyLevel}/5</span>`}
            </div>
            <h3 class="game-folio-title">${escapeHtml(g.title)}</h3>
            <p class="game-folio-tagline">${escapeHtml(g.tagline || g.description)}</p>
            <div class="game-folio-meta">
              <span>${playerRange} thám tử</span>
              <span>${g.durationMinutes} phút</span>
            </div>
            <div class="game-folio-price">
              <span>Ngày: <strong>${formatVnd(g.priceDay)}</strong></span>
              <span>Tối: <strong class="highlight-gold">${formatVnd(g.priceNight)}</strong></span>
            </div>
            <div class="game-folio-actions">
              <button class="btn btn-outline btn-prices" data-id="${g.id}">Bảng giá</button>
              <button class="btn btn-primary btn-create-room" data-id="${g.id}">Mở phòng</button>
            </div>
          </div>
        </article>
      `;
      }).join('');

      // Gắn sự kiện xem giá
      grid.querySelectorAll('.btn-prices').forEach(btn => {
        btn.onclick = () => this.showPriceModal(btn.dataset.id);
      });

      // Gắn sự kiện tạo phòng
      grid.querySelectorAll('.btn-create-room').forEach(btn => {
        btn.onclick = () => {
          const game = this.games.find(g => g.id == btn.dataset.id);
          this.onSelectGameToCreateRoom(game);
        };
      });
    } catch (err) {
      grid.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-error);">${err.message}</div>`;
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
      `;

      dialog.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h3 style="margin: 0; font-size: 1.2rem;">Bảng giá: ${escapeHtml(gameName)}</h3>
          <button style="background: none; border: none; color: #94a3b8; font-size: 1.4rem; cursor: pointer;">&times;</button>
        </div>
        <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 14px;">
          * Mức cọc cố định theo quy chuẩn V16: <strong>50.000 VNĐ / người</strong>.
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
                  <span style="font-weight: 500;">${p.start_time.substring(0, 5)} - ${p.end_time.substring(0, 5)}</span>
                  ${p.start_time >= '20:00:00' ? '<span style="font-size: 0.75rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">Giờ tối</span>' : '<span style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">Giờ ngày</span>'}
                </td>
                <td style="padding: 10px 8px; text-align: right; color: #10b981; font-weight: 600;">
                  ${Number(p.price_per_person).toLocaleString()} VNĐ
                </td>
              </tr>
            `).join('') : `
              <tr>
                <td colspan="2" style="padding: 12px; text-align: center; color: #94a3b8;">Khung giờ tiêu chuẩn: 250.000 VNĐ / người</td>
              </tr>
            `}
          </tbody>
        </table>
        <div style="text-align: right;">
          <button class="btn btn-secondary" style="padding: 8px 16px;">Đóng</button>
        </div>
      `;

      dialog.querySelector('button').onclick = () => dialog.close();
      dialog.querySelector('.btn-secondary').onclick = () => dialog.close();
      dialog.addEventListener('close', () => dialog.remove());
      dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
      });
      document.body.appendChild(dialog);
      dialog.showModal();
    } catch (err) {
      toast.error('Không thể tải bảng giá: ' + err.message);
    }
  }
}
