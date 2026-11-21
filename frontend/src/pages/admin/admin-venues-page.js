/**
 * Admin Physical Venues & Capacity Coordination Page (#/admin/venues)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';

export class AdminVenuesPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-venues container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('door', 'text-cyan'),
            'FACILITIES & SCHEDULING // VENUE MATRIX'
          ),
          h('h1', { className: 'page-title' }, 'Quản Lý Không Gian & Mật Thất Vật Lý'),
          h('p', { className: 'page-subtitle text-muted' },
            'Xếp phòng mật thất theo quy mô người chơi (+1 Quản trò DM), ngăn ngừa xung đột lịch kèm đệm dọn dẹp 30 phút giữa các ca, kích hoạt hạn cọc 2 giờ.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    const physicalRooms = sharedStore.data.physicalRooms;
    const gameRooms = sharedStore.data.gameRooms;
    const waitingOrgRooms = gameRooms.filter((r) => r.status === 'WAITING_FOR_ORGANIZATION');

    // Section 1: Pending Assignment
    const pendingCard = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('door', 'text-cyan'),
          `1. Phòng Chờ Chốt Mật Thất Quán (${waitingOrgRooms.length})`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// VENUE-DISPATCH-QUEUE')
      ),
      waitingOrgRooms.length === 0
        ? h('p', { className: 'text-muted' }, 'Hiện không có phòng nào đang chờ chốt địa điểm vật lý.')
        : h('div', { className: 'pending-org-list' },
            ...waitingOrgRooms.map((room) => {
              const game = sharedStore.data.games.find((g) => g.id === room.game_id);
              const activeCount = (room.participants || []).filter(
                (p) => !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status)
              ).length;
              const totalOccupants = activeCount + 1; // Includes 1 DM
              const durationMinutes = game?.duration_minutes || 240;
              const targetStart = new Date(room.session_start_time).getTime();
              const targetEnd = targetStart + (durationMinutes + 30) * 60 * 1000;

              // Pre-evaluate available options
              let selectedPhyRoomId = physicalRooms.find((p) => p.status === 'AVAILABLE' && p.capacity >= totalOccupants)?.id || physicalRooms[0]?.id;

              return h('div', { className: 'org-item-row p-3 mb-3 card-dark', style: { border: '1px solid var(--color-border-subtle)', borderRadius: 'var(--radius-xs)' } },
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
                  h('span', { className: 'buffer-pill safe' },
                    `QUY MÔ: ${activeCount} THÁM TỬ + 1 DM = ${totalOccupants} CHỖ NGỒI`
                  )
                ),
                h('div', { className: 'assign-venue-row flex-align-center mt-3' },
                  h('label', { className: 'mr-2 text-muted', style: { fontSize: '0.85rem' } }, 'Chọn mật thất:'),
                  h('select', {
                    className: 'form-control mr-3',
                    style: { maxWidth: '400px' },
                    onchange: (e) => { selectedPhyRoomId = e.target.value; },
                  },
                    ...physicalRooms.map((p) => {
                      const isCapacityOk = p.capacity >= totalOccupants;
                      const isAvailable = p.status === 'AVAILABLE';

                      // Check conflict against occupying rooms
                      let conflictWith = null;
                      if (isAvailable && isCapacityOk) {
                        for (const other of gameRooms) {
                          if (other.id === room.id) continue;
                          if (!other.physical_room || other.physical_room.id !== p.id) continue;
                          if (['CANCELLED', 'EXPIRED', 'COMPLETED'].includes(other.status)) continue;
                          const otherGame = sharedStore.data.games.find((g) => g.id === other.game_id);
                          const oDuration = otherGame?.duration_minutes || 240;
                          const oStart = new Date(other.session_start_time).getTime();
                          const oEnd = oStart + (oDuration + 30) * 60 * 1000;
                          if (Math.max(targetStart, oStart) < Math.min(targetEnd, oEnd)) {
                            conflictWith = other.code;
                            break;
                          }
                        }
                      }

                      let label = `${p.name} (Chứa: ${p.capacity} người)`;
                      let disabled = false;
                      if (!isAvailable) {
                        label += ' [ĐANG BẢO TRÌ]';
                        disabled = true;
                      } else if (!isCapacityOk) {
                        label += ` [THIẾU CHỖ: ${p.capacity} < ${totalOccupants}]`;
                        disabled = true;
                      } else if (conflictWith) {
                        label += ` [XUNG ĐỘT: Trùng ca ${conflictWith}]`;
                        disabled = true;
                      } else {
                        label += ' [KHẢ DỤNG]';
                      }

                      return h('option', { value: p.id, disabled }, label);
                    })
                  ),
                  h('button', {
                    className: 'btn btn-primary btn-sm',
                    onclick: async () => {
                      try {
                        sharedStore.adminConfirmVenueAndSchedule(room.id, selectedPhyRoomId, user);
                        showToast(`Đã chốt mật thất cho ${room.code} và mở cửa sổ đặt cọc 2 giờ!`, 'success');
                        this.render();
                      } catch (err) {
                        showToast(err.message, 'error');
                      }
                    },
                  }, renderIcon('check'), ' Chốt phòng & Mở đặt cọc (2h)')
                )
              );
            })
          )
    );
    container.appendChild(pendingCard);

    // Section 2: Physical Venue Status & Maintenance Management
    const venueListCard = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('home', 'text-gold'),
          '2. Danh Mục Phòng Mật Thất Tại Quán & Tình Trạng Thiết Bị'
        ),
        h('span', { className: 'tactical-mono-tag' }, '// PHYSICAL-INVENTORY')
      ),
      h('div', { className: 'grid-col-2 gap-3' },
        ...physicalRooms.map((p) =>
          h('div', { className: 'venue-box p-3 card-dark', style: { border: '1px solid var(--color-border-subtle)', borderRadius: 'var(--radius-xs)' } },
            h('div', { className: 'flex-between' },
              h('h4', { className: 'highlight-gold mb-1' }, p.name),
              renderStatusBadge(p.status === 'AVAILABLE' ? 'READY_FOR_SESSION' : 'CANCELLED', p.status === 'AVAILABLE' ? 'Khả dụng' : 'Bảo trì')
            ),
            h('p', { className: 'text-muted mt-1 mb-2', style: { fontSize: '0.85rem' } },
              `Sức chứa tối đa: `,
              h('strong', { className: 'text-light' }, `${p.capacity} người`),
              ` (Bao gồm thám tử và 1 Quản trò DM)`
            ),
            h('div', { className: 'flex-between pt-2', style: { borderTop: '1px solid var(--color-noir-divider)' } },
              h('span', { className: 'text-muted', style: { fontSize: '0.78rem', fontFamily: 'var(--font-mono)' } }, `ID: ${p.id}`),
              h('button', {
                className: `btn btn-sm ${p.status === 'AVAILABLE' ? 'btn-secondary text-warning' : 'btn-primary'}`,
                onclick: () => {
                  p.status = p.status === 'AVAILABLE' ? 'MAINTENANCE' : 'AVAILABLE';
                  sharedStore.save(sharedStore.data);
                  showToast(`Đã chuyển trạng thái ${p.name} sang ${p.status === 'AVAILABLE' ? 'Khả dụng' : 'Bảo trì'}.`, 'info');
                  this.render();
                },
              }, p.status === 'AVAILABLE' ? 'Đánh dấu bảo trì' : 'Mở lại khả dụng')
            )
          )
        )
      )
    );
    container.appendChild(venueListCard);

    this.mountPoint.appendChild(container);
  }
}
