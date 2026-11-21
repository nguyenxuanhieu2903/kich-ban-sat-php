/**
 * Admin DM Assignment Coordination Page (#/admin/dm-assignments)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { showToast } from '../../components/toast.js';

export class AdminDmAssignmentPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-dm-assign container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('user', 'text-gold'),
            'TACTICAL DISPATCH // DM ORCHESTRATION & REVOCATION'
          ),
          h('h1', { className: 'page-title' }, 'Điều Phối & Thu Hồi Quản Trò (DM)'),
          h('p', { className: 'page-subtitle text-muted' },
            'Phân công Quản trò cho các phòng đủ điều kiện (kiểm tra khoảng đệm chuẩn bị ≥ 12 giờ) và xử lý thu hồi phân công có lưu vết lý do bất biến.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    const rooms = sharedStore.data.gameRooms;
    const dmStaff = sharedStore.data.dmStaff.filter((d) => d.status === 'ACTIVE');

    const waitingDmRooms = rooms.filter((r) => r.status === 'WAITING_FOR_DM');
    const assignedRooms = rooms.filter(
      (r) => r.dm_assignment && ['PENDING', 'ACCEPTED'].includes(r.dm_assignment.status) && !['COMPLETED', 'CANCELLED'].includes(r.status)
    );

    // Section 1: Waiting for DM Assignment
    const assignSection = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('user', 'text-amber'),
          `1. Phòng Đang Chờ Phân Công DM (${waitingDmRooms.length})`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// QUEUE: WAITING_FOR_DM')
      ),
      waitingDmRooms.length === 0
        ? h('p', { className: 'text-muted' }, 'Tất cả các phòng chơi hiện tại đều đã được gán Quản trò.')
        : h('div', { className: 'assign-list' },
            ...waitingDmRooms.map((room) => {
              const game = sharedStore.data.games.find((g) => g.id === room.game_id);
              let selectedDmId = dmStaff[0]?.id;
              const hoursUntil = (new Date(room.session_start_time).getTime() - Date.now()) / (3600 * 1000);
              const isBufferSafe = hoursUntil >= 12;

              return h('div', { className: 'assign-item-row p-3 mb-3 card-dark', style: { border: '1px solid var(--color-border-subtle)', borderRadius: 'var(--radius-xs)' } },
                h('div', { className: 'item-top flex-between' },
                  h('div', {},
                    h('span', { className: 'highlight-gold font-mono mr-2' }, `[${room.code}]`),
                    h('strong', { className: 'item-title text-light' }, game?.title || 'Kịch bản')
                  ),
                  renderStatusBadge(room.status)
                ),
                h('div', { className: 'flex-align-center gap-3 mt-2' },
                  h('span', { className: 'text-muted', style: { fontSize: '0.85rem' } },
                    'Giờ ca chơi: ',
                    h('strong', { className: 'text-light' }, formatDateTime(room.session_start_time))
                  ),
                  h('span', { className: `buffer-pill ${isBufferSafe ? 'safe' : 'warning'}` },
                    isBufferSafe ? `✓ ĐỦ BUFFER (${Math.round(hoursUntil * 10) / 10}h)` : `⚠ CẢNH BÁO BUFFER THẤP (${Math.round(hoursUntil * 10) / 10}h < 12h)`
                  )
                ),
                h('div', { className: 'assign-controls-row flex-align-center mt-3' },
                  h('label', { className: 'mr-2 text-muted', style: { fontSize: '0.85rem' } }, 'Chỉ định Quản trò:'),
                  h('select', {
                    className: 'form-control mr-3',
                    style: { maxWidth: '320px' },
                    onchange: (e) => { selectedDmId = e.target.value; },
                  },
                    ...dmStaff.map((dm) =>
                      h('option', { value: dm.id }, `${dm.name} (${dm.experience_rooms} ca • ★ ${dm.rating})`)
                    )
                  ),
                  h('button', {
                    className: 'btn btn-primary btn-sm',
                    onclick: async () => {
                      try {
                        sharedStore.adminAssignDm(room.id, selectedDmId, user);
                        showToast(`Đã phân công DM cho phòng ${room.code}.`, 'success');
                        this.render();
                      } catch (err) {
                        showToast(err.message, 'error');
                      }
                    },
                  }, renderIcon('check'), ' Xác nhận gán DM')
                )
              );
            })
          )
    );
    container.appendChild(assignSection);

    // Section 2: Active Assigned DMs & Revocation Mechanism
    const assignedSection = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('shield', 'text-cyan'),
          `2. Phòng Đang Có Phân Công DM — Cơ Chế Thu Hồi (${assignedRooms.length})`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// REVOCATION-READY')
      ),
      assignedRooms.length === 0
        ? h('p', { className: 'text-muted' }, 'Chưa có phòng nào đang trong trạng thái gán DM chờ diễn ra.')
        : h('div', { className: 'table-scroll-wrapper' },
            h('table', { className: 'ui-data-table' },
              h('thead', {},
                h('tr', {},
                  h('th', { scope: 'col' }, 'Mã vụ án'),
                  h('th', { scope: 'col' }, 'Quản trò (DM)'),
                  h('th', { scope: 'col' }, 'Trạng thái DM'),
                  h('th', { scope: 'col' }, 'Thời điểm gán'),
                  h('th', { scope: 'col' }, 'Người gán'),
                  h('th', { scope: 'col' }, 'Thao tác thu hồi')
                )
              ),
              h('tbody', {},
                ...assignedRooms.map((room) => {
                  const assignment = room.dm_assignment;
                  return h('tr', {},
                    h('td', {}, h('strong', { className: 'highlight-gold' }, room.code)),
                    h('td', {}, h('strong', { className: 'text-light' }, assignment.dm_name)),
                    h('td', {}, renderStatusBadge(assignment.status)),
                    h('td', { className: 'text-muted', style: { fontSize: '0.8rem' } }, formatDateTime(assignment.assigned_at)),
                    h('td', { className: 'text-muted' }, assignment.assigned_by || 'Ban Quản Trị'),
                    h('td', {},
                      h('button', {
                        className: 'btn btn-sm btn-secondary text-danger',
                        onclick: () => this.handleRevokeAssignment(room, user),
                      }, renderIcon('x'), ' Thu hồi phân công')
                    )
                  );
                })
              )
            )
          )
    );
    container.appendChild(assignedSection);

    // Section 3: Immutable Revocation History across Rooms
    const allHistory = [];
    rooms.forEach((r) => {
      if (r.assignment_history && r.assignment_history.length > 0) {
        r.assignment_history.forEach((hItem) => {
          allHistory.push({ room_code: r.code, ...hItem });
        });
      }
    });

    const historySection = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('clock', 'text-gold'),
          `3. Sổ Lưu Vết Lịch Sử Thu Hồi DM Bất Biến (${allHistory.length})`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// IMMUTABLE-REVOCATION-HISTORY')
      ),
      allHistory.length === 0
        ? h('p', { className: 'text-muted' }, 'Chưa có phân công Quản trò nào từng bị thu hồi trong hệ thống.')
        : h('div', { className: 'immutable-history-box' },
            ...allHistory.map((rec) =>
              h('div', { className: 'immutable-history-item' },
                h('div', {},
                  h('div', { className: 'flex-align-center gap-2 mb-1' },
                    h('span', { className: 'highlight-gold font-mono' }, `[${rec.room_code}]`),
                    h('strong', { className: 'text-light' }, rec.dm_name),
                    h('span', { className: 'buffer-pill warning' }, 'ĐÃ THU HỒI')
                  ),
                  h('p', { className: 'text-secondary mb-0', style: { fontSize: '0.85rem' } },
                    'Lý do thu hồi: ',
                    h('span', { className: 'text-light' }, rec.revocation_reason || 'Không rõ')
                  )
                ),
                h('div', { className: 'text-right' },
                  h('div', { className: 'text-muted font-mono', style: { fontSize: '0.78rem' } }, formatDateTime(rec.revoked_at)),
                  h('div', { className: 'text-muted', style: { fontSize: '0.78rem' } }, `Người thực hiện: ${rec.revoked_by || 'Admin'}`)
                )
              )
            )
          )
    );
    container.appendChild(historySection);

    this.mountPoint.appendChild(container);
  }

  handleRevokeAssignment(room, adminActor) {
    showReasonDialog({
      title: `Thu Hồi Phân Công Quản Trò: ${room.code}`,
      actionKey: 'ADMIN_REVOKE_ASSIGNMENT',
      placeholder: 'Bắt buộc nhập lý do cụ thể (tối thiểu 5 ký tự: Quản trò bận đột xuất, điều phối khẩn cấp, vắng mặt không báo trước)...',
      confirmLabel: 'Xác nhận thu hồi phân công',
      onSubmit: (reason) => {
        try {
          sharedStore.adminRevokeDm(room.id, reason, adminActor);
          showToast(`Đã thu hồi phân công Quản trò cho phòng ${room.code} và lưu vết lịch sử thành công.`, 'success');
          this.render();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }
}
