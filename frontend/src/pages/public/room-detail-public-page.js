/**
 * Public Room Detail Page (#/rooms/:id)
 * Allows guests to view room status and click "Join", redirecting to login if unauthenticated.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { renderCountdown } from '../../components/countdown.js';
import { renderRoomStepper } from '../../components/stepper.js';
import { renderParticipantRing } from '../../components/participant-ring.js';
import { formatDateTime } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';

export class RoomDetailPublicPage {
  constructor(mountPoint, { params, router }) {
    this.mountPoint = mountPoint;
    this.roomId = params.id;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-room-detail-public container' });

    try {
      const res = await apiService.getRoomById(this.roomId);
      const room = res.data;
      const game = room.game_details;

      // 1. Back link
      container.appendChild(
        h('div', { className: 'page-nav-back' },
          h('a', { className: 'btn-back', href: '#/rooms' }, renderIcon('chevronLeft'), ' Quay lại danh sách phòng')
        )
      );

      // 2. Room Main Header
      const header = h('div', { className: 'room-hero-header' },
        h('div', { className: 'room-title-block' },
          h('div', { className: 'code-row' },
            h('span', { className: 'room-code-badge' }, room.code),
            renderStatusBadge(room.status)
          ),
          h('h1', { className: 'room-game-title' }, game.title),
          h('p', { className: 'text-muted' }, `Bắt đầu: ${formatDateTime(room.session_start_time)} • ${game.genre} • Thời lượng: ${game.duration_minutes} phút`)
        ),
        h('div', { className: 'room-hero-actions' },
          h('button', {
            className: 'btn btn-primary btn-lg',
            onclick: () => this.handleJoinRoom(room),
          }, renderIcon('plus'), ' Tham Gia Phòng Chơi')
        )
      );
      container.appendChild(header);

      // 3. Stepper
      container.appendChild(renderRoomStepper(room.status));

      // 4. Two Column Layout (Ring & Info)
      const layout = h('div', { className: 'room-public-grid' },
        // Left: Participant Ring
        h('div', { className: 'ring-card card-dark text-center' },
          h('h3', { className: 'card-title' }, 'Sơ Đồ Ghế Ngồi'),
          renderParticipantRing({
            participants: room.participants,
            minPlayers: game.min_players,
            maxPlayers: game.max_players,
            currentUserId: auth.getUser()?.id,
            size: 320,
          }),
          h('p', { className: 'text-muted mt-2', style: { fontSize: '0.85rem' } },
            'Quy định bảo mật: Tên thành viên được ẩn danh cho đến khi bạn chính thức gia nhập phòng.'
          )
        ),
        // Right: Timeline and details
        h('div', { className: 'info-card card-dark' },
          h('h3', { className: 'card-title' }, 'Thông Tin Phiên Chơi'),
          h('ul', { className: 'specs-list text-muted' },
            h('li', {}, h('strong', {}, 'Kịch bản: '), game.title),
            h('li', {}, h('strong', {}, 'Số người yêu cầu: '), `${game.min_players} – ${game.max_players} người`),
            h('li', {}, h('strong', {}, 'Giờ bắt đầu: '), formatDateTime(room.session_start_time)),
            room.formation_deadline && room.status === 'FORMING'
              ? h('li', { className: 'highlight-deadline' },
                  h('strong', {}, 'Thời hạn gom người (4h): '),
                  renderCountdown(room.formation_deadline)
                )
              : null,
            room.replacement_deadline && room.status === 'REPLENISHING'
              ? h('li', { className: 'highlight-deadline' },
                  h('strong', {}, 'Thời hạn tìm người thay thế: '),
                  renderCountdown(room.replacement_deadline)
                )
              : null,
            h('li', {}, h('strong', {}, 'Tiền cọc giữ ghế: '), '50.000 VNĐ / người (Cố định)')
          ),
          h('div', { className: 'action-box mt-3' },
            h('button', {
              className: 'btn btn-primary btn-block',
              onclick: () => this.handleJoinRoom(room),
            }, 'Xác nhận tham gia ghế trống này')
          )
        )
      );
      container.appendChild(layout);

      this.mountPoint.appendChild(container);
    } catch (err) {
      container.appendChild(
        h('div', { className: 'error-state text-center' },
          h('p', { className: 'text-danger' }, err.message),
          h('a', { className: 'btn btn-secondary', href: '#/rooms' }, 'Về danh sách phòng')
        )
      );
      this.mountPoint.appendChild(container);
    }
  }

  async handleJoinRoom(room) {
    if (!auth.isAuthenticated()) {
      showToast('Vui lòng đăng nhập để tham gia phòng.', 'info');
      auth.setRedirectAfterLogin(`#/rooms/${room.id}`);
      this.router.navigate('/login');
      return;
    }

    try {
      await apiService.joinRoom(room.id);
      showToast('Tham gia phòng thành công!', 'success');
      // Chuyển sang Player Portal chi tiết phòng
      this.router.navigate(`/player/rooms/${room.id}`);
    } catch (err) {
      showToast(`Không thể tham gia: ${err.message}`, 'error');
    }
  }
}
