/**
 * DM Notifications Page (#/dm/notifications)
 * Master Command Dispatch — Dark Cinematic Luxury
 * Dispatch feed for DM assignments, shift reminders, and admin alerts.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth, ROLES } from '../../utils/auth.js';
import { formatDateTime } from '../../utils/format.js';

export class DmNotificationsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-notifications container', style: 'max-width: 860px;' });

    const user = auth.getUser();
    if (!user || user.role !== ROLES.DM) {
      this.router.navigate('/403');
      return;
    }

    // 1. Command Header
    const header = h('div', { className: 'dm-command-hero flex-between', style: 'flex-wrap: wrap; gap: 1.5rem;' },
      h('div', {},
        h('span', { className: 'dossier-badge-pill' },
          renderIcon('bell'), ' ĐIỆN TÍN QUẢN TRÒ • SALON PRIVÉ'
        ),
        h('h1', { className: 'dm-command-title' }, 'Trung Tâm Thông Báo & Điều Phối'),
        h('p', { className: 'text-muted' },
          'Thông tin phân công điều phối, nhắc nhở giờ dẫn dắt và phản hồi từ Ban Quản Trị.'
        )
      ),
      h('a', { className: 'btn btn-secondary', href: '#/dm/dashboard' },
        renderIcon('chevronLeft'), ' Về Bàn Làm Việc'
      )
    );
    container.appendChild(header);

    const res = await apiService.getNotifications();
    const notifications = res.data || [];

    // 2. Notifications Dispatch Feed
    const list = h('div', { style: 'display: flex; flex-direction: column; gap: 1rem;' });

    if (notifications.length === 0) {
      list.appendChild(
        h('div', { style: 'background: var(--color-bg-surface-1); border: 1px dashed var(--color-border-subtle); padding: 3rem; text-align: center; border-radius: var(--radius-xs);' },
          renderIcon('shield', 'text-muted', 32),
          h('p', { className: 'text-muted mt-3', style: 'font-size: 1.05rem;' }, 'Hiện chưa có điện tín phân công mới nào.')
        )
      );
    } else {
      notifications.forEach((item) => {
        const itemEl = h('div', {
          style: `background: var(--color-bg-surface-1); border: 1px solid ${item.is_read ? 'var(--color-border-subtle)' : 'var(--color-border-gold)'}; padding: 1.5rem; border-radius: var(--radius-xs); display: flex; gap: 1.25rem; align-items: flex-start; transition: all 0.2s ease;`
        },
          h('div', {
            style: `width: 44px; height: 44px; border-radius: 50%; background: ${item.is_read ? 'rgba(255, 255, 255, 0.03)' : 'rgba(196, 163, 107, 0.12)'}; border: 1px solid ${item.is_read ? 'var(--color-border-subtle)' : 'var(--color-accent-gold)'}; display: flex; align-items: center; justify-content: center; color: ${item.is_read ? 'var(--color-text-muted)' : 'var(--color-accent-gold)'}; flex-shrink: 0;`
          },
            renderIcon('shield', '', 20)
          ),
          h('div', { style: 'flex: 1;' },
            h('div', { className: 'flex-between', style: 'margin-bottom: 0.35rem;' },
              h('h4', { style: 'margin: 0; font-size: 1.05rem; color: var(--color-text-primary);' }, item.title),
              h('span', { className: 'text-muted font-mono', style: 'font-size: 0.75rem;' }, formatDateTime(item.created_at))
            ),
            h('p', { className: 'text-muted', style: 'margin: 0 0 0.75rem 0; font-size: 0.9rem; line-height: 1.5;' }, item.message),
            item.target_hash
              ? h('a', { className: 'link-gold', href: item.target_hash, style: 'font-size: 0.85rem;' },
                  'Truy cập chi tiết nhiệm vụ →'
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
