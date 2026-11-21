/**
 * Public Rooms Listing Page (#/rooms)
 * Shows all active public GameRooms with formation countdowns and privacy-preserving slot counts.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { renderCountdown } from '../../components/countdown.js';
import { formatDateTime } from '../../utils/format.js';

export class RoomsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.rooms = [];
    this.selectedStatus = '';
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-rooms container' });

    // Page Header
    const header = h('div', { className: 'page-header flex-between' },
      h('div', {},
        h('h1', { className: 'page-title' }, 'Danh Sách Phòng Đang Mở'),
        h('p', { className: 'page-subtitle text-muted' },
          'Tìm kiếm phòng chơi phù hợp với thời gian rảnh của bạn và nhập vai ngay.'
        )
      ),
      h('a', { className: 'btn btn-primary', href: '#/player/create-room' },
        renderIcon('plus'), ' Tự Mở Phòng Mới'
      )
    );
    container.appendChild(header);

    // Filter
    const filterRow = h('div', { className: 'rooms-filter-bar' },
      h('select', {
        className: 'form-control',
        onchange: (e) => {
          this.selectedStatus = e.target.value;
          this.renderGrid();
        },
      },
        h('option', { value: '' }, 'Tất cả trạng thái mở'),
        h('option', { value: 'FORMING' }, 'Đang tuyển người (FORMING)'),
        h('option', { value: 'REPLENISHING' }, 'Đang tìm người thay thế (REPLENISHING)'),
        h('option', { value: 'WAITING_FOR_DEPOSIT' }, 'Chờ cọc (WAITING_FOR_DEPOSIT)')
      )
    );
    container.appendChild(filterRow);

    this.gridContainer = h('div', { className: 'rooms-cards-grid' },
      h('div', { className: 'loading-pulse' }, 'Đang tải danh sách phòng...')
    );
    container.appendChild(this.gridContainer);

    this.mountPoint.appendChild(container);

    await this.loadRooms();
  }

  async loadRooms() {
    try {
      const res = await apiService.getPublicRooms();
      this.rooms = res.data;
      this.renderGrid();
    } catch (err) {
      this.gridContainer.innerHTML = `<div class="error-state text-danger">${err.message}</div>`;
    }
  }

  async renderGrid() {
    this.gridContainer.innerHTML = '';

    let list = this.rooms.filter((r) =>
      ['FORMING', 'REPLENISHING', 'WAITING_FOR_DM', 'WAITING_FOR_ORGANIZATION', 'WAITING_FOR_DEPOSIT', 'READY_FOR_SESSION'].includes(r.status)
    );

    if (this.selectedStatus) {
      list = list.filter((r) => r.status === this.selectedStatus);
    }

    if (list.length === 0) {
      this.gridContainer.appendChild(
        h('div', { className: 'empty-state text-center' },
          h('p', { className: 'text-muted' }, 'Không có phòng nào đang mở trong trạng thái này.')
        )
      );
      return;
    }

    for (const room of list) {
      const gameRes = await apiService.getGameById(room.game_id);
      const game = gameRes.data;
      const activeCount = room.participants.filter((p) => !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status)).length;

      const card = h('div', { className: 'room-card' },
        h('div', { className: 'room-card-header' },
          h('span', { className: 'room-code' }, room.code),
          renderStatusBadge(room.status)
        ),
        h('h3', { className: 'room-game-title' }, game.title),
        h('div', { className: 'room-info-meta text-muted' },
          h('div', {}, renderIcon('calendar'), ' Giờ chơi: ', h('strong', {}, formatDateTime(room.session_start_time))),
          h('div', {}, renderIcon('users'), ` Ghế tham gia: ${activeCount}/${game.max_players} người (Tối thiểu: ${game.min_players})`)
        ),
        room.formation_deadline && room.status === 'FORMING'
          ? h('div', { className: 'room-deadline-box' },
              renderCountdown(room.formation_deadline, { label: 'Hạn tuyển' })
            )
          : null,
        room.replacement_deadline && room.status === 'REPLENISHING'
          ? h('div', { className: 'room-deadline-box' },
              renderCountdown(room.replacement_deadline, { label: 'Hạn thay thế' })
            )
          : null,
        h('div', { className: 'room-card-footer' },
          h('a', { className: 'btn btn-secondary', href: `#/rooms/${room.id}` }, 'Xem chi tiết'),
          h('a', { className: 'btn btn-primary', href: `#/rooms/${room.id}` }, 'Tham gia ghế')
        )
      );

      this.gridContainer.appendChild(card);
    }
  }
}
