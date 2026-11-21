/**
 * Admin Notifications Page (#/admin/notifications)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { formatDateTime } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';

export class AdminNotificationsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-notifications container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('bell', 'text-gold'),
            'ALERT DISPATCH // REAL-TIME SYSTEM SIGNALS'
          ),
          h('h1', { className: 'page-title' }, 'Hệ Thống Cảnh Báo & Thông Báo Quản Trị'),
          h('p', { className: 'page-subtitle text-muted' },
            'Tín hiệu điều hành thời gian thực: Phòng đủ điều kiện gán DM, mật thất cần kích hoạt cọc và biến động lịch trình.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    const list = sharedStore.data.notifications || [];

    const box = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('bell', 'text-gold'),
          `Hàng Đợi Cảnh Báo Điều Phối (${list.length})`
        ),
        h('button', {
          className: 'btn btn-sm btn-secondary text-gold',
          onclick: () => {
            list.forEach((n) => { n.is_read = true; });
            sharedStore.save(sharedStore.data);
            showToast('Đã đánh dấu đọc toàn bộ thông báo.', 'info');
            this.render();
          },
        }, 'Đánh dấu đã đọc tất cả')
      )
    );

    if (list.length === 0) {
      box.appendChild(h('p', { className: 'text-muted' }, 'Hiện tại không có thông báo điều phối nào.'));
    } else {
      const notifList = h('div', { className: 'notifications-list-inner' });
      list.forEach((n) => {
        notifList.appendChild(
          h('div', {
            className: `notification-card-item card-dark p-3 mb-3 ${n.is_read ? 'is-read' : 'is-unread'}`,
            style: { border: '1px solid var(--color-border-subtle)', borderRadius: 'var(--radius-xs)' },
          },
            h('div', { className: 'flex-between' },
              h('div', { className: 'flex-align-center gap-2' },
                renderIcon('bell', n.is_read ? 'text-muted' : 'text-gold'),
                h('strong', { className: n.is_read ? 'text-light' : 'highlight-gold' }, n.title)
              ),
              h('span', { className: 'text-muted font-mono', style: { fontSize: '0.78rem' } }, formatDateTime(n.created_at))
            ),
            h('p', { className: 'text-secondary mt-2 mb-2', style: { fontSize: '0.88rem' } }, n.message),
            n.target_hash
              ? h('div', { className: 'mt-2' },
                  h('a', { className: 'btn btn-sm btn-secondary text-gold', href: n.target_hash }, 'Điều phối ngay →')
                )
              : null
          )
        );
      });
      box.appendChild(notifList);
    }

    container.appendChild(box);
    this.mountPoint.appendChild(container);
  }
}
