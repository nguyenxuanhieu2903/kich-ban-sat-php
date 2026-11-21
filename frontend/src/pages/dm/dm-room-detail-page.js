/**
 * DM Room Detail Page (#/dm/rooms/:id)
 * Master Command Briefing — Dark Cinematic Luxury
 * Read-only operational overview of assigned room, player roster, and physical venue.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth, ROLES } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { renderParticipantRing } from '../../components/participant-ring.js';

export class DmRoomDetailPage {
  constructor(mountPoint, { params, router }) {
    this.mountPoint = mountPoint;
    this.roomId = params.id;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-dm-room-detail container' });

    const user = auth.getUser();
    if (!user || user.role !== ROLES.DM) {
      this.router.navigate('/403');
      return;
    }

    try {
      const res = await apiService.getRoomById(this.roomId);
      const room = res.data;
      const game = room.game_details || { title: 'Kịch bản Jubensha' };

      // 1. Navigation Breadcrumbs
      const navRow = h('div', { className: 'flex-between', style: 'padding: 2rem 0 1rem 0;' },
        h('a', { className: 'link-gold flex-align-center', href: '#/dm/assignments', style: 'gap: 0.4rem; font-size: 0.9rem;' },
          renderIcon('chevronLeft'), ' Về Danh Sách Phân Công'
        ),
        h('a', { className: 'btn btn-primary', href: `#/dm/sessions/${room.id}` },
          renderIcon('check'), ' Vào Trạm Vận Hành Ca Chơi →'
        )
      );
      container.appendChild(navRow);

      // 2. Command Hero Card
      const header = h('div', { className: 'room-hero-card', style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2rem; box-shadow: var(--shadow-md);' },
        h('div', { className: 'flex-between mb-3', style: 'flex-wrap: wrap; gap: 1rem;' },
          h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
            h('span', { className: 'room-code', style: 'font-size: 1.1rem; padding: 0.35rem 0.75rem;' }, room.code),
            renderStatusBadge(room.status),
            room.physical_room
              ? h('span', { className: 'badge-operator' }, renderIcon('sparkles'), ` ${room.physical_room.name}`)
              : h('span', { className: 'text-muted' }, 'Chưa chốt phòng vật lý')
          ),
          h('span', { className: 'text-muted font-mono', style: 'font-size: 0.85rem;' },
            `Quản trò: `, h('strong', { className: 'text-gold' }, user.name)
          )
        ),
        h('h1', { style: 'font-size: 2.8rem; color: var(--color-text-primary); margin-bottom: 0.75rem;' },
          `Hồ Sơ Kịch Bản: ${game.title}`
        ),
        h('div', { style: 'display: flex; gap: 1.5rem; flex-wrap: wrap; color: var(--color-text-secondary); font-size: 0.9rem;' },
          h('span', {}, renderIcon('calendar'), ' Giờ bắt đầu: ', h('strong', { className: 'text-gold font-mono' }, formatDateTime(room.session_start_time))),
          h('span', {}, renderIcon('users'), ` Bàn: ${game.min_players || 5}–${game.max_players || 8} thám tử`),
          h('span', {}, renderIcon('clock'), ` Thời lượng: ${game.duration_minutes || 240} phút`),
          h('span', {}, renderIcon('shield'), ' Chuẩn bị tối thiểu: ≥ 12 giờ trước ca')
        )
      );
      container.appendChild(header);

      // 3. Showcase Grid: SVG Round Table & Player Roster
      const showcaseGrid = h('div', { className: 'room-main-showcase-grid', style: 'margin-top: 2rem;' },
        // Left Column: Round Table
        h('div', { className: 'round-table-showcase-panel', style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2rem; text-align: center;' },
          h('div', { className: 'text-center mb-3' },
            h('h3', { className: 'highlight-gold', style: 'margin: 0; font-size: 1.3rem;' }, 'Sơ Đồ Ghế Ngồi Bàn Tròn'),
            h('span', { className: 'text-muted font-mono', style: 'font-size: 0.75rem;' }, 'VỊ TRÍ THÁM TỬ THỰC ĐỊA')
          ),
          renderParticipantRing({
            participants: room.participants,
            minPlayers: game.min_players || 5,
            maxPlayers: game.max_players || 8,
            size: 340,
          })
        ),
        // Right Column: Player Roster
        h('div', { className: 'roster-terminal-panel' },
          h('div', { className: 'flex-between mb-3' },
            h('h3', { className: 'highlight-gold', style: 'margin: 0; font-size: 1.2rem;' },
              'Danh Sách Người Chơi Thực Tế'
            ),
            h('span', { className: 'text-muted font-mono', style: 'font-size: 0.8rem;' },
              `${room.participants.length} THÀM TỬ`
            )
          ),
          h('div', { className: 'roster-list' },
            ...room.participants.map((p) =>
              h('div', { className: 'roster-item-row' },
                h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
                  h('div', { style: 'width: 32px; height: 32px; border-radius: 50%; background: var(--color-bg-surface-2); border: 1px solid var(--color-border-subtle); display: flex; align-items: center; justify-content: center;' },
                    renderIcon('user', 'text-muted', 14)
                  ),
                  h('div', {},
                    h('strong', { style: 'font-size: 0.95rem; color: var(--color-text-primary);' }, p.name),
                    h('span', { className: 'text-muted font-mono', style: 'font-size: 0.75rem; display: block;' }, `ID: ${p.user_id}`)
                  )
                ),
                renderStatusBadge(p.status)
              )
            )
          )
        )
      );
      container.appendChild(showcaseGrid);

      this.mountPoint.appendChild(container);
    } catch (err) {
      container.appendChild(
        h('div', { className: 'error-state text-danger p-4' }, err.message)
      );
      this.mountPoint.appendChild(container);
    }
  }
}
