/**
 * Player Dashboard Page (#/player/dashboard)
 * Detective Dossier & Command Desk: Integrated Metrics Strip, Active Cases Ledger, and Asymmetric Layout.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { renderCountdown } from '../../components/countdown.js';
import { formatDateTime } from '../../utils/format.js';

export class PlayerDashboardPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-player-dashboard container' });

    const user = auth.getUser();
    if (!user) {
      this.router.navigate('/login');
      return;
    }

    // 1. Executive Player Operations Header
    const header = h('div', { className: 'player-dossier-hero flex-between flex-align-center', style: 'margin-bottom: 2rem; gap: 1.5rem; flex-wrap: wrap;' },
      h('div', { style: 'flex: 1; min-width: 280px;' },
        h('span', { className: 'portal-role-pill', style: 'margin-bottom: 0.6rem;' },
          renderIcon('shield', 'text-crimson'), ' THÁM TỬ / NGƯỜI CHƠI'
        ),
        h('h1', { className: 'player-desk-title' }, `Bàn Làm Việc: ${user.name}`),
        h('p', { className: 'page-subtitle' },
          'Theo dõi tiến trình các vụ án đang tham gia, thời hạn gom nhóm và đồng hồ đếm ngược đặt cọc.'
        )
      ),
      h('div', { className: 'dashboard-header-actions', style: 'display: flex; gap: 0.75rem; align-items: center;' },
        h('a', { className: 'btn btn-primary', href: '#/player/create-room' },
          renderIcon('plus'), ' Khởi Tạo Vụ Án Mới'
        )
      )
    );
    container.appendChild(header);

    // 3. Integrated Metrics Strip
    this.metricsContainer = h('div', { className: 'dossier-metrics-strip' },
      h('div', { className: 'loading-pulse', style: 'padding: 1.5rem;' }, 'Đang tải thông tin hạn ngạch...')
    );
    container.appendChild(this.metricsContainer);

    // 4. Main 2-Column Desk Layout
    this.mainDeskContainer = h('div', { className: 'player-desk-grid' });
    container.appendChild(this.mainDeskContainer);

    this.mountPoint.appendChild(container);

    await this.loadDashboardData();
  }

  async loadDashboardData() {
    const user = auth.getUser();
    try {
      const res = await apiService.getPublicRooms();
      const allRooms = res.data;

      // Filter rooms where user is participant or creator
      const myRooms = allRooms.filter((r) =>
        r.creator_id === user.id ||
        r.participants.some((p) => p.user_id === user.id && !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status))
      );

      // Quota: Created active rooms (max 3 per V16 Freeze)
      const myCreatedActive = allRooms.filter(
        (r) => r.creator_id === user.id && !['COMPLETED', 'EXPIRED', 'CANCELLED'].includes(r.status)
      );

      // Populate Integrated Metrics Strip
      this.metricsContainer.innerHTML = '';

      // Strip 1: Quota
      const quotaItem = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Hạn ngạch mở phòng (V16)'),
        h('div', { className: 'metric-strip-val' },
          h('span', { className: myCreatedActive.length >= 3 ? 'text-danger' : 'highlight-gold' },
            `${myCreatedActive.length}`
          ),
          h('span', { className: 'metric-strip-sub' }, ' / 3 phòng tối đa')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          myCreatedActive.length >= 3 ? 'Đã đạt giới hạn tối đa' : `Còn được mở thêm ${3 - myCreatedActive.length} phòng`
        )
      );
      this.metricsContainer.appendChild(quotaItem);

      // Strip 2: Active Enlistments
      const activeItem = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Vụ án đang tham gia'),
        h('div', { className: 'metric-strip-val' },
          h('span', { className: 'text-light' }, String(myRooms.length)),
          h('span', { className: 'metric-strip-sub' }, ' phòng đang hoạt động')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          'Bao gồm đang tuyển, chờ cọc và chuẩn bị chơi'
        )
      );
      this.metricsContainer.appendChild(activeItem);

      // Strip 3: Student Verification
      const studentItem = h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Chứng chỉ Học sinh - Sinh viên'),
        h('div', { className: 'metric-strip-val' },
          user.student_verified
            ? h('span', { className: 'text-emerald', style: 'font-size: 1.15rem; font-weight: 600;' }, '✓ ĐÃ XÁC MINH HSSV')
            : h('span', { className: 'text-muted', style: 'font-size: 1.15rem;' }, 'CHƯA ĐĂNG KÝ HSSV')
        ),
        h('span', { className: 'text-muted', style: 'font-size: 0.75rem; margin-top: 0.25rem;' },
          user.student_verified
            ? 'Tự động giảm 20.000đ/người từ Thứ 2 đến Thứ 5 (09:00–19:00)'
            : 'Xuất trình thẻ tại quầy quán để mở khóa ưu đãi'
        )
      );
      this.metricsContainer.appendChild(studentItem);

      // Populate Main 2-Column Desk
      this.mainDeskContainer.innerHTML = '';

      // Left Column: Active Cases
      const leftCol = h('div', { className: 'desk-left-col' },
        h('div', { className: 'cases-ledger-header' },
          h('h2', { className: 'cases-ledger-title' }, `Hồ Sơ Các Vụ Án Của Bạn (${myRooms.length})`),
          h('span', { className: 'font-mono text-muted', style: 'font-size: 0.8rem;' }, 'DANH SÁCH THỜI GIAN THỰC')
        )
      );

      if (myRooms.length === 0) {
        leftCol.appendChild(
          h('div', { className: 'empty-state text-center', style: 'background: #12151D; border: 1px solid rgba(255, 255, 255, 0.08); border-left: 3px solid #C4A36B; padding: 3rem 1.5rem; clip-path: polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 14px 100%, 0 calc(100% - 14px));' },
            h('p', { className: 'text-muted', style: 'font-size: 1.05rem; margin-bottom: 1.5rem;' },
              'Bạn chưa tham gia hoặc khởi tạo phòng chơi nào.'
            ),
            h('div', { style: 'display: flex; gap: 1rem; justify-content: center;' },
              h('a', { className: 'btn btn-primary', href: '#/player/create-room' }, 'Khởi tạo phòng mới'),
              h('a', { className: 'btn btn-secondary', href: '#/rooms' }, 'Khám phá phòng đang chiêu mộ')
            )
          )
        );
      } else {
        const caseList = h('div', { className: 'cases-list' });
        for (const room of myRooms) {
          const gameRes = await apiService.getGameById(room.game_id);
          const game = gameRes.data;
          const isCreator = room.creator_id === user.id;
          const myPart = room.participants.find((p) => p.user_id === user.id);

          const item = h('div', { className: 'case-dossier-item' },
            h('div', { className: 'case-dossier-header' },
              h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
                h('span', { className: 'room-code' }, room.code),
                isCreator ? h('span', { className: 'badge-creator' }, 'Chủ phòng') : null,
                myPart ? renderStatusBadge(myPart.status) : null
              ),
              renderStatusBadge(room.status)
            ),
            h('h3', { className: 'case-dossier-title' },
              h('a', { href: `#/player/rooms/${room.id}` }, game.title)
            ),
            h('div', { className: 'case-dossier-meta' },
              h('span', {}, renderIcon('calendar'), ' Khởi hành: ', h('strong', { className: 'text-blood' }, formatDateTime(room.session_start_time))),
              h('span', {}, renderIcon('users'), ` ${room.participants.length}/${game.max_players} thám tử`),
              h('span', {}, renderIcon('shield'), room.dm_assignment ? `DM: ${room.dm_assignment.dm_name}` : 'Chưa xếp DM')
            ),
            // Countdown Tickers
            room.formation_deadline && room.status === 'FORMING'
              ? h('div', { style: 'margin-bottom: 0.75rem;' },
                  renderCountdown(room.formation_deadline, { label: 'Hạn thành lập phòng (4h)' })
                )
              : null,
            myPart?.deposit_deadline && myPart.status === 'PENDING_DEPOSIT' && room.status === 'WAITING_FOR_DEPOSIT'
              ? h('div', { style: 'margin-bottom: 0.75rem;' },
                  renderCountdown(myPart.deposit_deadline, { label: 'Hạn đặt cọc 50.000đ của bạn (2h)' })
                )
              : null,
            h('div', { className: 'case-dossier-footer' },
              h('span', { className: 'text-muted font-mono', style: 'font-size: 0.78rem;' },
                room.physical_room ? `Phòng: ${room.physical_room.name}` : 'Mật thất: Chờ Admin ấn định'
              ),
              h('a', { className: 'btn btn-primary btn-sm', href: `#/player/rooms/${room.id}` },
                'Vào Vụ Án →'
              )
            )
          );
          caseList.appendChild(item);
        }
        leftCol.appendChild(caseList);
      }
      this.mainDeskContainer.appendChild(leftCol);

      // Right Column: Detective Guidance & Quick Links
      const rightCol = h('div', { className: 'desk-right-col' },
        h('div', { className: 'cases-ledger-header' },
          h('h3', { className: 'cases-ledger-title' }, 'Lưu Ý Quan Trọng'),
          h('span', { className: 'badge-pill-subtle' }, 'Quy chế quán')
        ),
        h('div', { className: 'info-box-crimson mb-4' },
          h('h4', { className: 'highlight-blood mb-2' }, renderIcon('shield'), ' Quy Tắc Đặt Cọc & Hoàn Tiền'),
          h('ul', { style: 'color: var(--color-text-secondary); font-size: 0.88rem; line-height: 1.7; padding-left: 1.25rem; margin: 0;' },
            h('li', {}, 'Tiền cọc cố định ', h('strong', { className: 'text-blood' }, '50.000 VNĐ / người'), ', trừ trực tiếp vào hóa đơn khi tới quán.'),
            h('li', {}, 'Mỗi người chơi có ', h('strong', {}, '2 giờ để hoàn tất cọc'), ' sau khi phòng được Admin chốt lịch.'),
            h('li', {}, 'Hủy trước giờ chơi ', h('strong', { className: 'text-emerald' }, '≥ 24 giờ'), ' được hoàn 100% tiền cọc.'),
            h('li', {}, 'Đổi lịch chơi yêu cầu ', h('strong', {}, '100% thành viên'), ' trong phòng đồng thuận.')
          )
        ),
        h('div', { className: 'info-box-crimson' },
          h('h4', { style: 'font-size: 1rem; color: var(--color-text-primary); margin-bottom: 0.75rem;' }, 'Lối Tắt Hữu Ích'),
          h('div', { style: 'display: flex; flex-direction: column; gap: 0.6rem;' },
            h('a', { className: 'btn btn-secondary btn-block text-left', href: '#/games' },
              renderIcon('search'), ' Tra cứu kho 42 kịch bản'
            ),
            h('a', { className: 'btn btn-secondary btn-block text-left', href: '#/player/history' },
              renderIcon('clock'), ' Lịch sử cọc & hoàn tiền'
            ),
            h('a', { className: 'btn btn-secondary btn-block text-left', href: '#/player/notifications' },
              renderIcon('bell'), ' Thông báo & nhắc hẹn'
            )
          )
        )
      );
      this.mainDeskContainer.appendChild(rightCol);

    } catch (err) {
      this.mainDeskContainer.innerHTML = `<div class="error-state text-danger">${err.message}</div>`;
    }
  }
}
