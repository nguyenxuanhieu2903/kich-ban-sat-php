/**
 * DM Assignments Page (#/dm/assignments)
 * Master Assignment Command — Dark Cinematic Luxury
 * Interactive filters, 12h buffer checks, and mandatory ReasonDialog on rejection.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth, ROLES } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { checkDmPrepBuffer } from '../../utils/rules.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { showToast } from '../../components/toast.js';

export class DmAssignmentsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.activeFilter = 'ALL'; // ALL, PENDING, ACCEPTED, REJECTED
    this.searchTerm = '';
    this.assignments = [];
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-dm-assignments container' });

    const user = auth.getUser();
    if (!user || user.role !== ROLES.DM) {
      this.router.navigate('/403');
      return;
    }

    // 1. Command Header
    const header = h('div', { className: 'dm-command-hero flex-between', style: 'flex-wrap: wrap; gap: 1.5rem;' },
      h('div', {},
        h('span', { className: 'dossier-badge-pill' },
          renderIcon('shield'), ' TRUNG TÂM PHÂN CÔNG • QUẢN TRÒ'
        ),
        h('h1', { className: 'dm-command-title' }, 'Hồ Sơ Nhiệm Vụ Dẫn Truyện'),
        h('p', { className: 'text-muted' },
          'Tiếp nhận điều phối phòng chơi từ Ban Quản Trị. Từ chối yêu cầu bắt buộc cung cấp lý do cụ thể và không hủy phòng của người chơi.'
        )
      ),
      h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
        h('a', { className: 'btn btn-secondary', href: '#/dm/dashboard' },
          renderIcon('chevronLeft'), ' Về Bàn Làm Việc'
        )
      )
    );
    container.appendChild(header);

    // 2. Filter & Search Controls
    this.controlsContainer = h('div', { className: 'flex-between mb-4', style: 'flex-wrap: wrap; gap: 1rem; align-items: center;' });
    container.appendChild(this.controlsContainer);

    // 3. Dossier List Container
    this.listContainer = h('div', { className: 'assignments-list-container' },
      h('div', { className: 'loading-pulse', style: 'padding: 2rem;' }, 'Đang giải mã hồ sơ nhiệm vụ...')
    );
    container.appendChild(this.listContainer);

    this.mountPoint.appendChild(container);

    await this.loadAssignments();
  }

  async loadAssignments() {
    try {
      const res = await apiService.getDmAssignments();
      this.assignments = res.data || [];
      this.renderControls();
      this.renderFilteredList();
    } catch (err) {
      this.listContainer.innerHTML = '';
      this.listContainer.appendChild(
        h('div', { className: 'error-state text-danger p-4' }, err.message)
      );
    }
  }

  renderControls() {
    this.controlsContainer.innerHTML = '';

    const pendingCount = this.assignments.filter((a) => a.dm_assignment?.status === 'PENDING').length;
    const acceptedCount = this.assignments.filter((a) => a.dm_assignment?.status === 'ACCEPTED').length;
    const rejectedCount = this.assignments.filter((a) => a.dm_assignment?.status === 'REJECTED').length;

    const filterPills = h('div', { className: 'filter-pills-noir' },
      h('button', {
        className: `pill-noir ${this.activeFilter === 'ALL' ? 'active' : ''}`,
        onclick: () => { this.activeFilter = 'ALL'; this.renderControls(); this.renderFilteredList(); },
      }, `Tất cả (${this.assignments.length})`),
      h('button', {
        className: `pill-noir ${this.activeFilter === 'PENDING' ? 'active' : ''}`,
        onclick: () => { this.activeFilter = 'PENDING'; this.renderControls(); this.renderFilteredList(); },
      }, `Chờ tiếp nhận (${pendingCount})`),
      h('button', {
        className: `pill-noir ${this.activeFilter === 'ACCEPTED' ? 'active' : ''}`,
        onclick: () => { this.activeFilter = 'ACCEPTED'; this.renderControls(); this.renderFilteredList(); },
      }, `Đã tiếp nhận (${acceptedCount})`),
      h('button', {
        className: `pill-noir ${this.activeFilter === 'REJECTED' ? 'active' : ''}`,
        onclick: () => { this.activeFilter = 'REJECTED'; this.renderControls(); this.renderFilteredList(); },
      }, `Đã từ chối (${rejectedCount})`)
    );

    const searchInput = h('input', {
      type: 'text',
      className: 'input-search-noir',
      placeholder: 'Tìm mã phòng hoặc tên kịch bản...',
      value: this.searchTerm,
      style: 'max-width: 300px;',
      oninput: (e) => {
        this.searchTerm = e.target.value.toLowerCase();
        this.renderFilteredList();
      },
    });

    this.controlsContainer.appendChild(filterPills);
    this.controlsContainer.appendChild(searchInput);
  }

  async renderFilteredList() {
    this.listContainer.innerHTML = '';

    let filtered = this.assignments;
    if (this.activeFilter !== 'ALL') {
      filtered = filtered.filter((a) => a.dm_assignment?.status === this.activeFilter);
    }

    if (this.searchTerm) {
      filtered = filtered.filter((a) =>
        a.room_code.toLowerCase().includes(this.searchTerm) ||
        (a.game_title && a.game_title.toLowerCase().includes(this.searchTerm))
      );
    }

    if (filtered.length === 0) {
      this.listContainer.appendChild(
        h('div', { className: 'p-5 text-center', style: 'background: var(--color-bg-surface-1); border: 1px dashed var(--color-border-subtle); border-radius: var(--radius-xs);' },
          renderIcon('shield', 'text-muted', 32),
          h('p', { className: 'text-muted mt-3' }, 'Không tìm thấy hồ sơ phân công nào phù hợp với bộ lọc hiện tại.')
        )
      );
      return;
    }

    for (const item of filtered) {
      const gameRes = await apiService.getGameById(item.game_id);
      const game = gameRes.data || { title: 'Kịch bản Jubensha' };
      const status = item.dm_assignment?.status || 'PENDING';
      const isPending = status === 'PENDING';
      const isAccepted = status === 'ACCEPTED';
      const isRejected = status === 'REJECTED';
      const buffer = checkDmPrepBuffer(item.session_start_time);

      const card = h('div', { className: 'assignment-dossier-card' },
        h('div', { className: 'flex-between', style: 'flex-wrap: wrap; gap: 0.75rem;' },
          h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
            h('span', { className: 'room-code' }, item.room_code),
            renderStatusBadge(status),
            buffer.violated
              ? h('span', { className: 'buffer-badge buffer-warning' }, renderIcon('alertTriangle'), ` Gấp: Còn ${buffer.hoursRemaining}h`)
              : h('span', { className: 'buffer-badge buffer-safe' }, renderIcon('check'), ` Đủ đệm: Còn ${buffer.hoursRemaining}h chuẩn bị`)
          ),
          h('div', { style: 'display: flex; gap: 0.5rem; align-items: center;' },
            isPending
              ? h('div', { style: 'display: flex; gap: 0.5rem;' },
                  h('button', {
                    className: 'btn btn-sm btn-primary',
                    onclick: () => this.handleAccept(item.room_id),
                  }, renderIcon('check'), ' Nhận phân công'),
                  h('button', {
                    className: 'btn btn-sm btn-secondary text-danger',
                    onclick: () => this.handleReject(item.room_id),
                  }, renderIcon('x'), ' Từ chối ca')
                )
              : (isAccepted
                  ? h('a', { className: 'btn btn-sm btn-primary', href: `#/dm/sessions/${item.room_id}` },
                      renderIcon('check'), ' Vào Trạm Vận Hành →'
                    )
                  : null),
            h('a', { className: 'btn btn-sm btn-secondary', href: `#/dm/rooms/${item.room_id}` },
              'Chi tiết phòng'
            )
          )
        ),
        h('h3', { style: 'font-size: 1.4rem; margin: 0.75rem 0 0.5rem 0; color: var(--color-text-primary);' },
          game.title
        ),
        h('div', { className: 'assignment-meta-row' },
          h('span', {}, renderIcon('calendar'), ' Khởi hành: ', h('strong', { className: 'text-gold font-mono' }, formatDateTime(item.session_start_time))),
          h('span', {}, renderIcon('users'), ` Thành viên: ${item.participants_count} thám tử`),
          h('span', {}, renderIcon('clock'), ` Thời lượng: ${game.duration_minutes || 240} phút`),
          h('span', {}, renderIcon('shield'), ' Khoảng đệm quy định: ≥ 12 giờ')
        ),
        isRejected && item.dm_assignment?.rejected_reason
          ? h('div', { style: 'background: rgba(88, 17, 26, 0.15); border-left: 3px solid var(--color-accent-blood); padding: 0.75rem 1rem; border-radius: var(--radius-xs); margin-top: 0.75rem;' },
              h('span', { className: 'text-danger', style: 'font-size: 0.8rem; font-weight: 600;' }, 'LÝ DO TỪ CHỐI: '),
              h('span', { className: 'text-muted', style: 'font-size: 0.85rem;' }, item.dm_assignment.rejected_reason)
            )
          : null
      );
      this.listContainer.appendChild(card);
    }
  }

  async handleAccept(roomId) {
    try {
      await apiService.dmRespond(roomId, true);
      showToast('Đã tiếp nhận phân công thành công!', 'success');
      await this.loadAssignments();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  handleReject(roomId) {
    showReasonDialog({
      title: 'Từ chối phân công dẫn dắt',
      actionKey: 'DM_REJECT_ASSIGNMENT',
      placeholder: 'Vui lòng nêu rõ lý do từ chối (tối thiểu 5 ký tự)...',
      onConfirm: async (reason) => {
        try {
          await apiService.dmRespond(roomId, false, reason);
          showToast('Đã từ chối phân công. Phòng đã được trả lại hàng đợi Ban Quản Trị.', 'info');
          await this.loadAssignments();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }
}
