/**
 * Admin GameRooms Management Page (#/admin/rooms)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { DataTable } from '../../components/data-table.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { validateReplacementWindow } from '../../utils/rules.js';
import { showToast } from '../../components/toast.js';

export class AdminRoomsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.activeFilter = 'ALL';
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-rooms container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('shield', 'text-gold'),
            'DISPATCH BOARD // GAME ROOM LIFECYCLE'
          ),
          h('h1', { className: 'page-title' }, 'Bảng Điều Phối GameRooms'),
          h('p', { className: 'page-subtitle text-muted' },
            'Theo dõi vòng đời phòng chơi toàn hệ thống, can thiệp sự cố, phân bổ tài nguyên và kiểm soát mở lại cửa sổ tuyển người thay thế.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    // Tactical Filter Bar
    const filterBar = h('div', { className: 'tactical-filter-bar' },
      ...[
        { key: 'ALL', label: 'TẤT CẢ VỤ ÁN' },
        { key: 'WAITING_FOR_DM', label: 'CẦN GÁN DM' },
        { key: 'WAITING_FOR_ORGANIZATION', label: 'CẦN XẾP MẬT THẤT' },
        { key: 'WAITING_FOR_ADMIN_DECISION', label: 'CHỜ QUYẾT ĐỊNH' },
        { key: 'IN_SESSION', label: 'ĐANG DIỄN RA' },
      ].map((f) => {
        const btn = h('button', {
          className: `tactical-filter-btn ${this.activeFilter === f.key ? 'active' : ''}`,
          onclick: () => {
            this.activeFilter = f.key;
            this.render();
          },
        }, f.label);
        return btn;
      })
    );
    container.appendChild(filterBar);

    const res = await apiService.getPublicRooms();
    let rooms = res.data;

    if (this.activeFilter !== 'ALL') {
      if (this.activeFilter === 'IN_SESSION') {
        rooms = rooms.filter((r) => ['IN_SESSION', 'READY_FOR_SESSION'].includes(r.status));
      } else {
        rooms = rooms.filter((r) => r.status === this.activeFilter);
      }
    }

    const columns = [
      {
        key: 'code',
        label: 'Mã phòng',
        sortable: true,
        render: (val, row) => {
          const game = sharedStore.data.games.find((g) => g.id === row.game_id);
          return h('div', {},
            h('strong', { className: 'highlight-gold' }, val),
            h('div', { className: 'text-muted', style: { fontSize: '0.78rem' } }, game?.title || row.game_id)
          );
        },
      },
      { key: 'status', label: 'Trạng thái', sortable: true, render: (val) => renderStatusBadge(val) },
      { key: 'session_start_time', label: 'Giờ bắt đầu ca', sortable: true, render: (val) => formatDateTime(val) },
      {
        key: 'occupancy',
        label: 'Thám tử / DM',
        render: (_, row) => {
          const count = row.participants?.length || 0;
          const dmName = row.dm_assignment?.dm_name || (row.status === 'WAITING_FOR_DM' ? 'Chưa gán DM' : '-');
          return h('div', { style: { fontSize: '0.82rem' } },
            h('span', { className: 'text-light' }, `${count} thám tử`),
            h('div', { className: 'text-muted' }, `DM: ${dmName}`)
          );
        },
      },
      {
        key: 'venue',
        label: 'Mật thất quán',
        render: (_, row) => {
          const venue = row.physical_room?.name || (row.status === 'WAITING_FOR_ORGANIZATION' ? 'Chờ chốt quán' : 'Chưa xếp');
          return h('span', { className: row.physical_room ? 'text-cyan' : 'text-muted', style: { fontSize: '0.82rem' } }, venue);
        },
      },
      {
        key: 'actions',
        label: 'Thao tác điều phối',
        render: (_, row) => {
          const wrap = h('div', { className: 'admin-action-btn-group' });

          // Fast direct link if waiting for DM
          if (row.status === 'WAITING_FOR_DM') {
            wrap.appendChild(
              h('a', {
                className: 'btn btn-sm btn-primary',
                href: `#/admin/dm-assignments?room_id=${row.id}`,
              }, 'Gán DM')
            );
          }

          // Fast direct link if waiting for Venue
          if (row.status === 'WAITING_FOR_ORGANIZATION') {
            wrap.appendChild(
              h('a', {
                className: 'btn btn-sm btn-primary',
                href: `#/admin/venues?room_id=${row.id}`,
              }, 'Xếp phòng')
            );
          }

          // Reopen recruitment window (30m - 4h, mandatory reason)
          if (['WAITING_FOR_ADMIN_DECISION', 'REPLENISHING'].includes(row.status)) {
            wrap.appendChild(
              h('button', {
                className: 'btn btn-sm btn-secondary text-gold',
                onclick: () => this.handleReopen(row),
              }, 'Mở lại tuyển')
            );
          }

          // Emergency Cancel room (Reason required)
          if (!['COMPLETED', 'CANCELLED', 'EXPIRED'].includes(row.status)) {
            wrap.appendChild(
              h('button', {
                className: 'btn btn-sm btn-secondary text-danger',
                onclick: () => this.handleCancelRoom(row),
              }, 'Hủy phòng')
            );
          }

          return wrap;
        },
      },
    ];

    const table = new DataTable({
      columns,
      data: rooms,
      pageSize: 8,
      searchPlaceholder: 'Tìm theo mã phòng hoặc kịch bản...',
    });

    const card = h('div', { className: 'tactical-card mt-3' });
    card.appendChild(table.render());
    container.appendChild(card);

    this.mountPoint.appendChild(container);
  }

  handleCancelRoom(room) {
    showReasonDialog({
      title: `Hủy Vụ Án ${room.code}`,
      actionKey: 'ADMIN_CANCEL_ROOM',
      placeholder: 'Bắt buộc cung cấp lý do hủy vụ án (bất khả kháng, vi phạm quy định quán, sự cố đột xuất)...',
      confirmLabel: 'Xác nhận hủy vụ án',
      onSubmit: async (reason) => {
        try {
          await apiService.adminCancelRoom(room.id, reason);
          showToast(`Đã hủy vụ án ${room.code} và gửi thông báo hoàn cọc tự động.`, 'info');
          this.render();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }

  handleReopen(room) {
    showReasonDialog({
      title: `Mở Lại Cửa Sổ Tuyển Người Thay Thế: ${room.code}`,
      actionKey: 'REOPEN_REPLACEMENT',
      placeholder: 'Lý do cho phép mở lại tuyển thành viên (vd: gia hạn bổ sung theo đề nghị của chủ phòng)...',
      confirmLabel: 'Xác nhận mở lại (2 Giờ)',
      onSubmit: async (reason) => {
        try {
          const durationMinutes = 120; // 2 giờ
          validateReplacementWindow(durationMinutes, room.session_start_time);
          await apiService.adminReopenWindow(room.id, durationMinutes, reason);
          showToast(`Đã mở lại cửa sổ tuyển người thay thế trong 2 giờ cho ${room.code}.`, 'success');
          this.render();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }
}
