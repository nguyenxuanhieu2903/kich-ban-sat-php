import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml } from '../../utils/sanitize.js';

export class NotificationsView {
  constructor(container) {
    this.container = container;
    this.notifications = [];
    this.unreadOnly = false;
  }

  async render() {
    const user = authState.getUser();

    if (!user) {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 40px 20px;">
          <p style="color: var(--color-text-muted); margin: 0;">Vui lòng đăng nhập để xem thông báo của bạn.</p>
        </div>
      `;
      return;
    }

    this.container.innerHTML = `
      <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
          <h2 style="font-size: 1.4rem; font-weight: 700; margin: 0 0 4px 0;">🔔 Trung Tâm Thông Báo</h2>
          <p style="color: var(--color-text-muted); margin: 0; font-size: 0.85rem;">Thông báo thời gian thực về ghép phòng, biểu quyết giờ chơi, tiền cọc và quản trò</p>
        </div>
        <div style="display: flex; gap: 8px;">
          <button id="btn-toggle-unread" class="btn" style="background: var(--color-bg-card); border: 1px solid var(--color-border); color: var(--color-text-main); font-size: 0.85rem;">
            ${this.unreadOnly ? 'Xem tất cả' : 'Chỉ xem chưa đọc'}
          </button>
          <button id="btn-refresh-notifications" class="btn" style="background: var(--color-bg-card); border: 1px solid var(--color-border); color: var(--color-text-main); font-size: 0.85rem;">
            🔄 Làm mới
          </button>
        </div>
      </div>

      <div id="notifications-list-container">
        <div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải thông báo...</div>
      </div>
    `;

    this.container.querySelector('#btn-toggle-unread').onclick = () => {
      this.unreadOnly = !this.unreadOnly;
      this.render();
    };

    this.container.querySelector('#btn-refresh-notifications').onclick = () => {
      this.fetchNotifications();
    };

    await this.fetchNotifications();
  }

  async fetchNotifications() {
    const listEl = this.container.querySelector('#notifications-list-container');
    if (!listEl) return;

    try {
      const url = `/notifications?unread_only=${this.unreadOnly ? 1 : 0}`;
      const res = await api.get(url);
      this.notifications = res.data || [];

      if (this.notifications.length === 0) {
        listEl.innerHTML = `
          <div class="card" style="text-align: center; padding: 40px 20px; color: var(--color-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 12px;">📭</div>
            <p style="margin: 0; font-size: 0.95rem;">Bạn không có thông báo nào ${this.unreadOnly ? 'chưa đọc' : ''}.</p>
          </div>
        `;
        return;
      }

      listEl.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 10px;">
          ${this.notifications.map(n => this.renderNotificationItem(n)).join('')}
        </div>
      `;

      this.notifications.forEach(n => {
        if (!n.is_read) {
          const markBtn = listEl.querySelector(`#btn-mark-read-${n.id}`);
          if (markBtn) {
            markBtn.onclick = () => this.markAsRead(n.id);
          }
        }
      });
    } catch (err) {
      listEl.innerHTML = `
        <div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>
      `;
    }
  }

  renderNotificationItem(n) {
    const isUnread = !n.is_read;
    return `
      <div class="card" style="padding: 14px 18px; margin-bottom: 0; display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; background: ${isUnread ? 'rgba(59, 130, 246, 0.08)' : 'var(--color-bg-card)'}; border-left: 4px solid ${isUnread ? 'var(--color-primary)' : 'var(--color-border)'};">
        <div style="flex: 1;">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: ${isUnread ? 'var(--color-text-main)' : 'var(--color-text-muted)'};">
              ${escapeHtml(n.title || 'Thông báo hệ thống')}
            </h4>
            ${isUnread ? `<span class="badge" style="background: var(--color-primary); color: white; font-size: 0.65rem;">Mới</span>` : ''}
          </div>
          <p style="margin: 0 0 6px 0; font-size: 0.85rem; color: var(--color-text-main); line-height: 1.4;">
            ${escapeHtml(n.content || '')}
          </p>
          <span style="font-size: 0.75rem; color: var(--color-text-muted);">
            🕒 ${escapeHtml(new Date(n.created_at).toLocaleString('vi-VN'))}
          </span>
        </div>
        ${isUnread ? `
          <button id="btn-mark-read-${n.id}" class="btn" style="padding: 4px 8px; font-size: 0.75rem; background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted); white-space: nowrap;">
            Đánh dấu đã đọc
          </button>
        ` : ''}
      </div>
    `;
  }

  async markAsRead(id) {
    try {
      await api.post(`/notifications/${id}/read`);
      this.fetchNotifications();
    } catch (err) {
      toast.error('Không thể đánh dấu đã đọc');
    }
  }
}
