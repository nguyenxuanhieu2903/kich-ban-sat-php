/**
 * Player Notifications Center Page (#/player/notifications)
 * Detective Telegram & Dispatch Center: Reminders for deposit deadlines (1h, 30m), DM assignments, and schedule change proposals.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth } from '../../utils/auth.js';
import { formatDateTime } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';

export class PlayerNotificationsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-notifications container', style: 'max-width: 860px;' });

    const user = auth.getUser();
    if (!user) {
      this.router.navigate('/login');
      return;
    }

    // 1. Editorial Header
    const header = h('div', { className: 'player-dossier-hero flex-between' },
      h('div', {},
        h('span', { className: 'dossier-badge-pill' }, renderIcon('bell'), ' ĐIỆN TÍN SALON'),
        h('h1', { className: 'player-desk-title' }, 'Trung Tâm Thông Báo & Nhắc Hẹn'),
        h('p', { className: 'text-muted' },
          'Cập nhật tiến trình vụ án, thông báo mở hạn cọc, nhắc nhở hạn chót và đề xuất đổi lịch.'
        )
      ),
      h('a', { className: 'btn btn-secondary', href: '#/player/dashboard' },
        renderIcon('chevronLeft'), ' Về Bàn Làm Việc'
      )
    );
    container.appendChild(header);

    const res = await apiService.getNotifications();
    const notifications = res.data;

    // 2. Notifications Dispatch Feed
    const list = h('div', { style: 'display: flex; flex-direction: column; gap: 1rem;' });

    if (notifications.length === 0) {
      list.appendChild(
        h('div', { style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-subtle); padding: 3rem; text-align: center; border-radius: var(--radius-xs);' },
          h('p', { className: 'text-muted', style: 'font-size: 1.05rem;' }, 'Hộp thư của bạn hiện chưa có điện tín mới.')
        )
      );
    } else {
      notifications.forEach((item) => {
        const itemEl = h('div', {
          style: `background: var(--color-bg-surface-1); border: 1px solid ${item.is_read ? 'var(--color-border-subtle)' : 'var(--color-border-gold)'}; padding: 1.5rem; border-radius: var(--radius-xs); display: flex; gap: 1.25rem; align-items: flex-start; transition: all 0.2s ease;`
        },
          h('div', {
            style: `width: 44px; height: 44px; border-radius: 50%; background: ${item.is_read ? 'rgba(255, 255, 255, 0.03)' : 'var(--color-accent-gold-subtle)'}; border: 1px solid ${item.is_read ? 'var(--color-border-subtle)' : 'var(--color-accent-gold)'}; display: flex; align-items: center; justify-content: center; color: ${item.is_read ? 'var(--color-text-muted)' : 'var(--color-accent-gold)'}; flex-shrink: 0;`
          }, renderIcon('bell')),
          h('div', { style: 'flex: 1;' },
            h('div', { style: 'display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem;' },
              h('h4', { style: 'font-size: 1.15rem; color: var(--color-text-primary); margin: 0;' }, item.title),
              h('span', { className: 'font-mono text-muted', style: 'font-size: 0.75rem;' }, formatDateTime(item.created_at))
            ),
            h('p', { className: 'text-muted', style: 'font-size: 0.9rem; line-height: 1.6; margin: 0 0 0.85rem 0;' }, item.message),
            item.target_hash
              ? h('a', { className: 'link-gold font-mono', href: item.target_hash, style: 'font-size: 0.85rem;' },
                  'Truy cập chi tiết vụ án →'
                )
              : null
          )
        );
        list.appendChild(itemEl);
      });
    }

    container.appendChild(list);
    this.mountPoint.appendChild(container);
  }
}
