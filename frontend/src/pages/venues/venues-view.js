import { api } from '../../api/api-client.js';
import { escapeHtml } from '../../utils/sanitize.js';

export class VenuesView {
  constructor(container) {
    this.container = container;
    this.venues = [];
  }

  async render() {
    this.container.innerHTML = `
      <div style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div>
            <h2 style="font-size: 1.6rem; font-weight: 700; margin: 0 0 6px 0;">📍 Hệ Thống Chi Nhánh &amp; Phòng Chơi Vật Lý</h2>
            <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">
              Khám phá không gian trải nghiệm kịch bản sát nhập vai tại các chi nhánh Kịch Bản Sát V16
            </p>
          </div>
          <button id="btn-refresh-venues" class="btn" style="background: var(--color-bg-card); border: 1px solid var(--color-border); color: var(--color-text-main); display: flex; align-items: center; gap: 6px;">
            🔄 Tải lại chi nhánh
          </button>
        </div>
      </div>

      <div id="venues-content-list">
        <div style="text-align: center; padding: 40px; color: var(--color-text-muted);">Đang tải danh sách chi nhánh...</div>
      </div>
    `;

    this.container.querySelector('#btn-refresh-venues').onclick = () => this.fetchVenues();
    await this.fetchVenues();
  }

  async fetchVenues() {
    const listEl = this.container.querySelector('#venues-content-list');
    listEl.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải dữ liệu...</div>`;

    try {
      const res = await api.get('/venues');
      this.venues = res?.data?.venues || [];

      if (this.venues.length === 0) {
        listEl.innerHTML = `
          <div class="card" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 12px;">📍</div>
            <h4>Chưa có chi nhánh nào hoạt động</h4>
            <p style="font-size: 0.85rem;">Thông tin chi nhánh sẽ được cập nhật sớm nhất.</p>
          </div>
        `;
        return;
      }

      listEl.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 24px;">
          ${this.venues.map(v => this.renderVenueCard(v)).join('')}
        </div>
      `;
    } catch (err) {
      listEl.innerHTML = `
        <div class="card" style="text-align: center; padding: 30px; color: var(--color-error); border-color: var(--color-error);">
          <p>Lỗi tải danh sách chi nhánh: ${escapeHtml(err.message)}</p>
          <button id="btn-retry-venues" class="btn btn-primary" style="margin-top: 10px;">Thử lại</button>
        </div>
      `;
      const retryBtn = listEl.querySelector('#btn-retry-venues');
      if (retryBtn) retryBtn.onclick = () => this.fetchVenues();
    }
  }

  renderVenueCard(v) {
    const rooms = v.rooms || [];

    return `
      <div class="card" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; background: var(--color-bg-card);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px;">
          <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
              <h3 style="margin: 0; font-size: 1.35rem; font-weight: 700; color: var(--color-primary);">
                🏛️ ${escapeHtml(v.name)}
              </h3>
              <span class="badge" style="background: var(--color-success-bg); color: var(--color-success);">Hoạt động</span>
            </div>
            <p style="margin: 0 0 4px 0; color: var(--color-text-main); font-size: 0.92rem;">
              📍 <strong>Địa chỉ:</strong> ${escapeHtml(v.address)}
            </p>
            <p style="margin: 0; color: var(--color-text-muted); font-size: 0.86rem;">
              📞 Hotline: <strong>${escapeHtml(v.phone || '0901234567')}</strong> &bull; ⏰ Mở cửa: <strong>09:00 - 23:00</strong>
            </p>
          </div>
          <div style="text-align: right;">
            <span class="badge" style="background: rgba(212,175,55,0.15); color: var(--gold); font-size: 0.85rem; padding: 6px 12px;">
              ${rooms.length} phòng diễn vật lý
            </span>
          </div>
        </div>

        ${v.description ? `
          <p style="color: var(--color-text-muted); font-size: 0.88rem; margin-bottom: 18px; font-style: italic;">
            "${escapeHtml(v.description)}"
          </p>
        ` : ''}

        <h4 style="font-size: 1rem; font-weight: 600; margin: 0 0 12px 0; color: var(--color-text-main);">
          🚪 Danh sách phòng chơi tại chi nhánh:
        </h4>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px;">
          ${rooms.length === 0 ? `
            <div style="color: var(--color-text-muted); font-size: 0.85rem; grid-column: 1/-1;">Chưa có phòng vật lý nào được cấu hình cho chi nhánh này.</div>
          ` : rooms.map(r => `
            <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 14px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <strong style="font-size: 0.95rem; color: var(--color-text-main);">
                  🎭 ${escapeHtml(r.name)}
                </strong>
                <span class="badge" style="background: var(--color-success-bg); color: var(--color-success); font-size: 0.72rem;">
                  ${escapeHtml(r.status || 'AVAILABLE')}
                </span>
              </div>
              <div style="font-size: 0.82rem; color: var(--color-text-muted); margin-bottom: 6px;">
                Sức chứa tối đa: <strong style="color: var(--gold);">${escapeHtml(r.capacity || 8)} người</strong>
              </div>
              <p style="font-size: 0.8rem; color: var(--color-text-muted); margin: 0; line-height: 1.4;">
                ${escapeHtml(r.description || 'Không gian cách âm tiêu chuẩn, ánh sáng và phục trang phù hợp kịch bản.')}
              </p>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }
}
