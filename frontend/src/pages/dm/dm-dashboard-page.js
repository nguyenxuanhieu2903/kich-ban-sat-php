/**
 * DM Dashboard Page (#/dm/dashboard)
 * Master Command Station — Dark Cinematic Luxury
 * Integrated Metrics Strip, Live Operations Desk, and Asymmetric Layout.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth, ROLES } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime } from '../../utils/format.js';
import { checkDmPrepBuffer } from '../../utils/rules.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { showToast } from '../../components/toast.js';

export class DmDashboardPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-dm-dashboard container' });

    const user = auth.getUser();
    if (!user || user.role !== ROLES.DM) {
      this.router.navigate('/403');
      return;
    }

    // 1. Executive DM Operations Header
    const header = h('div', { className: 'dm-command-hero flex-between flex-align-center', style: 'margin-bottom: 2rem; flex-wrap: wrap; gap: 1.5rem;' },
      h('div', { style: 'flex: 1; min-width: 280px;' },
        h('span', { className: 'portal-role-pill', style: 'margin-bottom: 0.6rem;' },
          renderIcon('shield', 'text-crimson'), ' QUẢN TRÒ (DM) CHÍNH THỨC'
        ),
        h('h1', { className: 'dm-command-title' }, `Bàn Làm Việc: ${user.name}`),
        h('p', { className: 'page-subtitle' },
          'Theo dõi các ca phân công dẫn dắt, mốc chuẩn bị kịch bản và vận hành phiên chơi tại quán.'
        )
      ),
      h('div', { className: 'dashboard-header-actions', style: 'display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;' },
        h('a', { className: 'btn btn-primary', href: '#/dm/assignments' },
          renderIcon('shield'), ' Tiếp Nhận Phân Công'
        ),
        h('a', { className: 'btn btn-secondary', href: '#/dm/availability' },
          renderIcon('calendar'), ' Đăng Ký Lịch Rảnh'
        )
      )
    );
    container.appendChild(header);

    // 3. Integrated Metrics Strip
    this.metricsContainer = h('div', { className: 'dm-metrics-strip' },
      h('div', { className: 'loading-pulse', style: 'padding: 1.5rem;' }, 'Đang tải dữ liệu ca trực...')
    );
    container.appendChild(this.metricsContainer);

    // 4. Main 2-Column Command Desk Layout
    this.mainDeskContainer = h('div', { className: 'dm-command-grid' });
    container.appendChild(this.mainDeskContainer);

    this.mountPoint.appendChild(container);

    await this.loadDashboardData();
  }

  async loadDashboardData() {
    const user = auth.getUser();
    try {
      const res = await apiService.getDmAssignments();
      const assignments = res.data || [];

      const pendingList = assignments.filter((a) => a.dm_assignment && a.dm_assignment.status === 'PENDING');
      const acceptedList = assignments.filter((a) => a.dm_assignment && a.dm_assignment.status === 'ACCEPTED');

      // Fetch availability
      const availRes = await apiService.getDmAvailability(user.id);
      const schedule = availRes.data || {};
      let availableSlotsCount = 0;
      Object.values(schedule).forEach((day) => {
        if (day.morning) availableSlotsCount++;
        if (day.afternoon) availableSlotsCount++;
        if (day.night) availableSlotsCount++;
      });

      // Populate Integrated Metrics Strip
      this.metricsContainer.innerHTML = '';

      // Strip 1: Pending Assignments
      const pendingMetric = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Phân công chờ phản hồi'),
        h('div', { className: 'metric-strip-val' },
          h('span', { className: pendingList.length > 0 ? 'text-danger' : 'text-light' },
            String(pendingList.length)
          ),
          h('span', { className: 'metric-strip-sub' }, ' ca cần duyệt')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          pendingList.length > 0 ? 'Cần chấp nhận hoặc từ chối kèm lý do' : 'Đã phản hồi toàn bộ'
        )
      );
      this.metricsContainer.appendChild(pendingMetric);

      // Strip 2: Upcoming Sessions
      const acceptedMetric = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Ca chơi đã tiếp nhận'),
        h('div', { className: 'metric-strip-val' },
          h('span', { className: 'highlight-gold' }, String(acceptedList.length)),
          h('span', { className: 'metric-strip-sub' }, ' ca đang trực')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          'Sẵn sàng dẫn dắt tại mật thất'
        )
      );
      this.metricsContainer.appendChild(acceptedMetric);

      // Strip 3: 12-Hour Prep Standard (V16 Freeze)
      const bufferMetric = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Chuẩn bị kịch bản (V16)'),
        h('div', { className: 'metric-strip-val' },
          h('span', { className: 'text-emerald' }, '≥ 12 Giờ'),
          h('span', { className: 'metric-strip-sub' }, ' đệm tối thiểu')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          'Đảm bảo nắm rõ manh mối & mật thất'
        )
      );
      this.metricsContainer.appendChild(bufferMetric);

      // Strip 4: Availability Status
      const availMetric = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Đăng ký lịch tuần này'),
        h('div', { className: 'metric-strip-val' },
          h('span', { className: 'text-gold font-mono' }, String(availableSlotsCount)),
          h('span', { className: 'metric-strip-sub' }, ' / 21 ca khả dụng')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          availableSlotsCount > 0 ? '✓ Đã đồng bộ với hệ thống xếp ca' : 'Chưa đăng ký ca tuần này'
        )
      );
      this.metricsContainer.appendChild(availMetric);

      // Populate Main 2-Column Desk
      this.mainDeskContainer.innerHTML = '';

      // Left Column: Operations & Active Tasks
      const leftCol = h('div', { className: 'desk-left-col' });

      // Section A: Pending Assignments Alert
      const pendingHeader = h('div', { className: 'flex-between mb-3' },
        h('h2', { style: 'font-size: 1.4rem; color: var(--color-text-primary); margin: 0;' },
          `Phân Công Chờ Bạn Tiếp Nhận (${pendingList.length})`
        ),
        h('a', { className: 'link-gold', href: '#/dm/assignments', style: 'font-size: 0.85rem;' },
          'Xem toàn bộ hồ sơ →'
        )
      );
      leftCol.appendChild(pendingHeader);

      if (pendingList.length === 0) {
        leftCol.appendChild(
          h('div', { className: 'p-4 text-center', style: 'background: var(--color-bg-surface-1); border: 1px dashed var(--color-border-subtle); border-radius: var(--radius-xs); margin-bottom: 2.5rem;' },
            renderIcon('check', 'text-emerald', 28),
            h('p', { className: 'text-muted mt-2', style: 'font-size: 0.9rem;' },
              'Hiện không có phân công nào đang chờ phản hồi. Bạn đã cập nhật đầy đủ.'
            )
          )
        );
      } else {
        const pendingContainer = h('div', { style: 'margin-bottom: 2.5rem;' });
        for (const item of pendingList) {
          const gameRes = await apiService.getGameById(item.game_id);
          const game = gameRes.data || { title: 'Kịch bản Jubensha' };
          const buffer = checkDmPrepBuffer(item.session_start_time);

          const card = h('div', { className: 'assignment-dossier-card' },
            h('div', { className: 'flex-between', style: 'flex-wrap: wrap; gap: 0.75rem;' },
              h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
                h('span', { className: 'room-code' }, item.room_code),
                renderStatusBadge('PENDING', 'Chờ tiếp nhận'),
                buffer.violated
                  ? h('span', { className: 'buffer-badge buffer-warning' }, renderIcon('alertTriangle'), ` Gấp: Còn ${buffer.hoursRemaining}h chuẩn bị`)
                  : h('span', { className: 'buffer-badge buffer-safe' }, renderIcon('check'), ` Đủ đệm: Còn ${buffer.hoursRemaining}h`)
              ),
              h('div', { style: 'display: flex; gap: 0.5rem;' },
                h('button', {
                  className: 'btn btn-sm btn-primary',
                  onclick: () => this.handleAccept(item.room_id),
                }, renderIcon('check'), ' Tiếp nhận'),
                h('button', {
                  className: 'btn btn-sm btn-secondary text-danger',
                  onclick: () => this.handleReject(item.room_id),
                }, renderIcon('x'), ' Từ chối ca')
              )
            ),
            h('h3', { style: 'font-size: 1.3rem; margin: 0.75rem 0 0.5rem 0; color: var(--color-text-primary);' },
              game.title
            ),
            h('div', { className: 'assignment-meta-row' },
              h('span', {}, renderIcon('calendar'), ' Khởi hành: ', h('strong', { className: 'text-gold font-mono' }, formatDateTime(item.session_start_time))),
              h('span', {}, renderIcon('users'), ` Thành viên: ${item.participants_count} thám tử`),
              h('span', {}, renderIcon('clock'), ` Thời lượng: ${game.duration_minutes || 240} phút`)
            )
          );
          pendingContainer.appendChild(card);
        }
        leftCol.appendChild(pendingContainer);
      }

      // Section B: Accepted Sessions Ready for Live Operations
      const acceptedHeader = h('div', { className: 'flex-between mb-3' },
        h('h2', { style: 'font-size: 1.4rem; color: var(--color-text-primary); margin: 0;' },
          `Các Phiên Chơi Bạn Đang Phụ Trách (${acceptedList.length})`
        ),
        h('span', { className: 'text-muted font-mono', style: 'font-size: 0.8rem;' },
          'BẢNG VẬN HÀNH THỰC ĐỊA'
        )
      );
      leftCol.appendChild(acceptedHeader);

      if (acceptedList.length === 0) {
        leftCol.appendChild(
          h('div', { className: 'p-4 text-center', style: 'background: var(--color-bg-surface-1); border: 1px dashed var(--color-border-subtle); border-radius: var(--radius-xs);' },
            h('p', { className: 'text-muted', style: 'font-size: 0.9rem;' },
              'Bạn chưa có phiên chơi nào đã tiếp nhận trong thời gian tới.'
            )
          )
        );
      } else {
        const acceptedContainer = h('div', {});
        for (const item of acceptedList) {
          const gameRes = await apiService.getGameById(item.game_id);
          const game = gameRes.data || { title: 'Kịch bản Jubensha' };
          const roomRes = await apiService.getRoomById(item.room_id);
          const roomData = roomRes.data;

          const card = h('div', { className: 'assignment-dossier-card' },
            h('div', { className: 'flex-between', style: 'flex-wrap: wrap; gap: 0.75rem;' },
              h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
                h('span', { className: 'room-code' }, item.room_code),
                renderStatusBadge(roomData.status),
                roomData.physical_room
                  ? h('span', { className: 'badge-operator' }, renderIcon('sparkles'), ` ${roomData.physical_room.name}`)
                  : h('span', { className: 'text-muted', style: 'font-size: 0.8rem;' }, 'Chờ Admin xếp phòng')
              ),
              h('a', {
                className: 'btn btn-sm btn-primary',
                href: `#/dm/sessions/${item.room_id}`,
              }, renderIcon('check'), ' Vào Trạm Vận Hành →')
            ),
            h('h3', { style: 'font-size: 1.3rem; margin: 0.75rem 0 0.5rem 0; color: var(--color-text-primary);' },
              game.title
            ),
            h('div', { className: 'assignment-meta-row' },
              h('span', {}, renderIcon('calendar'), ' Giờ chơi: ', h('strong', { className: 'text-gold font-mono' }, formatDateTime(item.session_start_time))),
              h('span', {}, renderIcon('users'), ` Số thám tử: ${roomData.participants.length} người`),
              h('span', {}, renderIcon('shield'), ' Quản trò: ', h('strong', { className: 'text-light' }, user.name))
            )
          );
          acceptedContainer.appendChild(card);
        }
        leftCol.appendChild(acceptedContainer);
      }

      this.mainDeskContainer.appendChild(leftCol);

      // Right Column: DM Operational Charter & Guidelines
      const rightCol = h('div', { className: 'desk-right-col' },
        h('div', { className: 'dm-code-charter' },
          h('div', { className: 'flex-between mb-3' },
            h('h3', { className: 'highlight-gold', style: 'margin: 0; font-size: 1.15rem;' },
              'Lưu Ý Dành Cho Quản Trò (DM)'
            ),
            h('span', { className: 'badge-pill-subtle' },
              'Quy chế salon'
            )
          ),
          h('ul', { className: 'charter-rule-list' },
            h('li', {},
              renderIcon('clock', 'text-gold'),
              h('div', {},
                h('strong', {}, 'Khoảng đệm chuẩn bị ≥ 12 giờ: '),
                'Chỉ nhận ca khi đảm bảo tối thiểu 12 giờ chuẩn bị kịch bản, nhánh rẽ và tâm lý nhân vật trước giờ xuất phát.'
              )
            ),
            h('li', {},
              renderIcon('shield', 'text-gold'),
              h('div', {},
                h('strong', {}, 'Quy tắc từ chối phân công: '),
                'Từ chối ca trực bắt buộc nhập lý do cụ thể. Thao tác từ chối KHÔNG hủy phòng của người chơi mà hoàn trả lại hàng đợi Admin.'
              )
            ),
            h('li', {},
              renderIcon('users', 'text-gold'),
              h('div', {},
                h('strong', {}, 'Điểm danh & Thu tiền tại quán: '),
                'Ghi nhận có mặt thực tế và thu đúng khoản còn lại sau khi đã khấu trừ 50.000 VNĐ tiền cọc. Không được thay đổi đơn giá vé.'
              )
            ),
            h('li', {},
              renderIcon('sparkles', 'text-gold'),
              h('div', {},
                h('strong', {}, 'Kiểm tra mật thất 30 phút trước giờ G: '),
                'Đạo cụ mật thất, phục trang thám tử, ánh sáng và hiệu ứng âm thanh cần được nghiệm thu trước khi mời người chơi vào bàn.'
              )
            )
          ),
          h('div', { style: 'margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--color-noir-divider); text-align: center;' },
            h('a', { className: 'btn btn-secondary btn-block', href: '#/dm/availability' },
              renderIcon('calendar'), ' Cập Nhật Lịch Rảnh Tuần Này'
            )
          )
        )
      );
      this.mainDeskContainer.appendChild(rightCol);

    } catch (err) {
      this.mainDeskContainer.innerHTML = '';
      this.mainDeskContainer.appendChild(
        h('div', { className: 'error-state text-danger p-4' }, err.message)
      );
    }
  }

  async handleAccept(roomId) {
    try {
      await apiService.dmRespond(roomId, true);
      showToast('Đã tiếp nhận phân công dẫn dắt thành công!', 'success');
      await this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  handleReject(roomId) {
    showReasonDialog({
      title: 'Từ chối phân công dẫn dắt',
      actionKey: 'DM_REJECT_ASSIGNMENT',
      placeholder: 'Vui lòng nêu rõ lý do từ chối (bận lịch, sức khỏe, chưa đủ thời gian chuẩn bị kịch bản)...',
      onConfirm: async (reason) => {
        try {
          await apiService.dmRespond(roomId, false, reason);
          showToast('Đã từ chối phân công. Phòng đã được chuyển về hàng đợi Ban Quản Trị.', 'info');
          await this.render();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }
}
