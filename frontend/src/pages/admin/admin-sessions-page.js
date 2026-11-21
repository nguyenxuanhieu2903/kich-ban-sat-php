/**
 * Admin Sessions & Master Timeline Page (#/admin/sessions)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { showToast } from '../../components/toast.js';

export class AdminSessionsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-sessions container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('clock', 'text-gold'),
            'MASTER TIMELINE // ON-SITE SESSION DISPATCH'
          ),
          h('h1', { className: 'page-title' }, 'Lịch Trình & Giám Sát Ca Chơi Thực Tế'),
          h('p', { className: 'page-subtitle text-muted' },
            'Theo dõi tiến độ vận hành các phiên phá án tại quán, mật thất phân bổ, Quản trò trực ca và xử lý can thiệp khẩn cấp.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    const rooms = sharedStore.data.gameRooms || [];
    const activeSessions = rooms.filter((r) =>
      ['READY_FOR_SESSION', 'IN_SESSION', 'COMPLETED', 'WAITING_FOR_DEPOSIT'].includes(r.status) || r.session
    );

    const card = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('list', 'text-cyan'),
          `Danh Sách Ca Chơi Thực Tế Tại Quán (${activeSessions.length})`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// ACTIVE-SESSIONS')
      ),
      activeSessions.length === 0
        ? h('p', { className: 'text-muted' }, 'Hiện tại chưa có ca chơi nào được xếp lịch.')
        : h('div', { className: 'table-scroll-wrapper' },
            h('table', { className: 'ui-data-table' },
              h('thead', {},
                h('tr', {},
                  h('th', { scope: 'col' }, 'Mã vụ án'),
                  h('th', { scope: 'col' }, 'Kịch bản & Mật thất'),
                  h('th', { scope: 'col' }, 'Quản trò (DM)'),
                  h('th', { scope: 'col' }, 'Giờ bắt đầu ca'),
                  h('th', { scope: 'col' }, 'Tiến độ tham gia'),
                  h('th', { scope: 'col' }, 'Trạng thái'),
                  h('th', { scope: 'col' }, 'Can thiệp Admin')
                )
              ),
              h('tbody', {},
                ...activeSessions.map((room) => {
                  const game = sharedStore.data.games.find((g) => g.id === room.game_id);
                  const participants = room.participants || [];
                  const attendedCount = participants.filter((p) => p.attendance === 'PRESENT').length;

                  return h('tr', {},
                    h('td', {}, h('strong', { className: 'highlight-gold font-mono' }, room.code)),
                    h('td', {},
                      h('div', { className: 'text-light' }, game?.title || room.game_id),
                      h('div', { className: 'text-cyan', style: { fontSize: '0.78rem' } }, room.physical_room?.name || 'Chưa gán mật thất')
                    ),
                    h('td', {},
                      h('div', { className: 'text-light' }, room.dm_assignment?.dm_name || 'Chưa có DM'),
                      h('div', { className: 'text-muted', style: { fontSize: '0.75rem' } }, room.dm_assignment ? `Status: ${room.dm_assignment.status}` : '')
                    ),
                    h('td', { className: 'text-muted font-mono', style: { fontSize: '0.82rem' } }, formatDateTime(room.session_start_time)),
                    h('td', {},
                      h('span', { className: 'text-light' }, `${participants.length} người`),
                      room.status === 'IN_SESSION' || room.status === 'COMPLETED'
                        ? h('div', { className: 'text-emerald', style: { fontSize: '0.75rem' } }, `Điểm danh: ${attendedCount}/${participants.length}`)
                        : null
                    ),
                    h('td', {}, renderStatusBadge(room.status)),
                    h('td', {},
                      !['COMPLETED', 'CANCELLED'].includes(room.status)
                        ? h('button', {
                            className: 'btn btn-sm btn-secondary text-danger',
                            onclick: () => this.handleCancelSession(room, user),
                          }, 'Hủy khẩn cấp')
                        : h('span', { className: 'text-muted', style: { fontSize: '0.8rem' } }, 'Đã kết thúc')
                    )
                  );
                })
              )
            )
          )
    );
    container.appendChild(card);

    this.mountPoint.appendChild(container);
  }

  handleCancelSession(room, adminActor) {
    showReasonDialog({
      title: `Hủy Khẩn Cấp Ca Chơi: ${room.code}`,
      actionKey: 'ADMIN_EMERGENCY_CANCEL',
      placeholder: 'Bắt buộc nhập lý do hủy ca khẩn cấp (sự cố cơ sở vật chất, hỏa hoạn, mất điện, bất khả kháng)...',
      confirmLabel: 'Xác nhận hủy ca khẩn cấp',
      onSubmit: (reason) => {
        try {
          sharedStore.adminCancelRoom(room.id, reason);
          showToast(`Đã hủy ca chơi ${room.code} khẩn cấp và kích hoạt quy trình hoàn cọc tự động.`, 'info');
          this.render();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }
}
