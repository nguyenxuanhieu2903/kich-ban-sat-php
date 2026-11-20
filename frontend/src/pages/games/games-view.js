import { api } from '../../api/api-client.js';
import { toast } from '../../utils/toast.js';

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
      const endpoint = this.selectedPlayersFilter ? `/games?players=${this.selectedPlayersFilter}` : '/games';
      const res = await api.get(endpoint);
      this.games = res?.data?.games || [];

      if (this.games.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">Không tìm thấy kịch bản phù hợp</div>';
        return;
      }

      grid.innerHTML = this.games.map(game => `
        <div class="card" style="display: flex; flex-direction: column; overflow: hidden; height: 100%;">
          <div style="height: 180px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;">
            ${game.image_url ? `<img src="${game.image_url}" alt="${game.name}" style="width: 100%; height: 100%; object-fit: cover;">` : `<div style="font-size: 3rem; opacity: 0.3;">🎭</div>`}
            <span style="position: absolute; top: 12px; right: 12px; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(4px); font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(255, 255, 255, 0.1);">
              ⏱️ ${game.duration_minutes || 240} phút
            </span>
          </div>
          <div style="padding: 18px; display: flex; flex-direction: column; flex-grow: 1;">
            <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 8px 0; color: #f8fafc;">${game.name}</h3>
            <p style="color: var(--color-text-muted); font-size: 0.85rem; line-height: 1.5; margin: 0 0 16px 0; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
              ${game.description || 'Kịch bản nhập vai trinh thám ly kỳ với cốt truyện hấp dẫn và bất ngờ.'}
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #94a3b8; border-top: 1px solid var(--color-border); padding-top: 12px; margin-bottom: 14px;">
              <span>👥 ${game.min_players} - ${game.max_players} người chơi</span>
              <span style="color: #10b981; font-weight: 600;">Cọc: 50.000đ/người</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
              <button class="btn btn-secondary btn-prices" data-id="${game.id}" style="font-size: 0.85rem; padding: 8px;">
                🏷️ Xem giá
              </button>
              <button class="btn btn-primary btn-create-room" data-id="${game.id}" style="font-size: 0.85rem; padding: 8px;">
                🚀 Tạo phòng
              </button>
            </div>
          </div>
        </div>
      `).join('');

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
          <h3 style="margin: 0; font-size: 1.2rem;">Bảng giá: ${gameName}</h3>
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
      document.body.appendChild(dialog);
      dialog.showModal();
    } catch (err) {
      toast.error('Không thể tải bảng giá: ' + err.message);
    }
  }
}
