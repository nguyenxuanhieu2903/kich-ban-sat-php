/**
 * Admin Operational Dashboard (#/admin/dashboard)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime, formatCurrency } from '../../utils/format.js';
import { createPortal3DSeal } from '../../components/portal-3d-seal.js';
import { renderQuickGuide } from '../../components/quick-guide.js';

export class AdminDashboardPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-dashboard container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    // 1. Clean Header with 3D Holographic Matrix Seal
    container.appendChild(
      h('div', { className: 'page-header flex-between flex-align-center', style: 'gap: 1.5rem; flex-wrap: wrap;' },
        h('div', { style: 'flex: 1; min-width: 280px;' },
          h('div', { className: 'portal-role-pill' },
            renderIcon('shield', 'text-gold'),
            ' BÀN ĐIỀU HÀNH QUẢN TRỊ VIÊN'
          ),
          h('h1', { className: 'page-title' }, 'Trung Tâm Điều Hành Quản Trị'),
          h('p', { className: 'page-subtitle text-muted' },
            'Theo dõi và điều phối ca chơi thời gian thực: Phân công Quản trò, gán phòng quán, xử lý sự cố và kiểm soát hoàn cọc.'
          )
        ),
        h('div', { className: 'hero-side-block flex-align-center', style: 'gap: 1.5rem;' },
          createPortal3DSeal('ADMIN'),
          h('div', { className: 'quick-nav-pills flex-column', style: 'display: flex; flex-direction: column; gap: 0.6rem;' },
            h('a', { className: 'btn btn-primary btn-sm', href: '#/admin/dm-assignments' }, renderIcon('user'), ' Phân Công DM'),
            h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/rooms' }, renderIcon('list'), ' Xem Tất Cả Phòng')
          )
        )
      )
    );

    // 2. Interactive 3-Step Quick Guide
    const adminGuide = renderQuickGuide({
      role: 'admin',
      title: 'Quy Trình Điều Phối Nghiệp Vụ Quản Trị',
      steps: [
        {
          icon: 'user',
          title: 'Gán Quản Trò (DM)',
          desc: 'Khi phòng đủ người, gán ngay DM có lịch rảnh và kịch bản phù hợp trước giờ chơi.',
          action: { label: 'Xếp DM Ngay', href: '#/admin/dm-assignments', primary: true }
        },
        {
          icon: 'door',
          title: 'Chốt Phòng Quán Vật Lý',
          desc: 'Sau khi DM nhận phòng, chốt phòng quán đủ sức chứa và đệm dọn dẹp 30 phút.',
          action: { label: 'Xếp Phòng', href: '#/admin/venues', primary: false }
        },
        {
          icon: 'shield',
          title: 'Kiểm Soát Cọc & Hoàn Tiền',
          desc: 'Xem xét và phê duyệt các yêu cầu hoàn cọc hợp lệ trước mốc 24.0h theo quy chế.',
          action: { label: 'Duyệt Hoàn Cọc', href: '#/admin/refunds', primary: false }
        }
      ]
    });
    container.appendChild(adminGuide);

    const res = await apiService.getAdminQueue();
    const queue = res.data;
    const allRefunds = sharedStore.data.refunds || [];
    const pendingRefunds = allRefunds.filter((r) => r.status === 'REQUESTED');
    const recentAudits = (sharedStore.data.auditLogs || []).slice(0, 5);

    // 3. 5-Item Integrated Metrics Strip
    const metricsStrip = h('div', { className: 'admin-metrics-strip strip-5' },
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Phòng Chờ DM'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: queue.waiting_dm.length > 0 ? 'text-amber' : 'text-light' }, String(queue.waiting_dm.length))
        ),
        h('a', { className: 'link-gold', href: '#/admin/dm-assignments', style: { fontSize: '0.8rem' } }, 'Điều phối DM →')
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Chờ Chốt Phòng'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: queue.waiting_org.length > 0 ? 'text-cyan' : 'text-light' }, String(queue.waiting_org.length))
        ),
        h('a', { className: 'link-gold', href: '#/admin/venues', style: { fontSize: '0.8rem' } }, 'Gán phòng quán →')
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Chờ Phê Duyệt'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: queue.waiting_decision.length > 0 ? 'text-danger' : 'text-light' }, String(queue.waiting_decision.length))
        ),
        h('a', { className: 'link-gold', href: '#/admin/rooms', style: { fontSize: '0.8rem' } }, 'Xử lý quyết định →')
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Hoàn Cọc Chờ Duyệt'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: pendingRefunds.length > 0 ? 'text-gold' : 'text-light' }, String(pendingRefunds.length))
        ),
        h('a', { className: 'link-gold', href: '#/admin/refunds', style: { fontSize: '0.8rem' } }, 'Sổ cái hoàn tiền →')
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Hoàn Tiền Thất Bại'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: queue.refunds_failed.length > 0 ? 'text-danger' : 'text-emerald' }, String(queue.refunds_failed.length))
        ),
        h('a', { className: 'link-gold', href: '#/admin/refunds', style: { fontSize: '0.8rem' } }, 'Thử lại giao dịch →')
      )
    );
    container.appendChild(metricsStrip);

    // 4. Categorized Navigation Hub (Gom 9 nút thành 3 nhóm rõ ràng)
    const navHub = h('div', { className: 'admin-hub-grid mb-4' },
      h('div', { className: 'admin-hub-card' },
        h('div', { className: 'hub-card-header' },
          renderIcon('list', 'text-amber'),
          h('h4', { className: 'hub-card-title' }, 'Quản Lý Vụ Án & Phân Công')
        ),
        h('div', { className: 'hub-links-wrap' },
          h('a', { className: 'hub-link-pill', href: '#/admin/rooms' }, 'Danh sách phòng chơi'),
          h('a', { className: 'hub-link-pill highlight', href: '#/admin/dm-assignments' },
            'Điều phối Quản trò (DM)',
            queue.waiting_dm.length > 0 ? h('span', { className: 'hub-badge' }, String(queue.waiting_dm.length)) : null
          ),
          h('a', { className: 'hub-link-pill', href: '#/admin/venues' }, 'Không gian phòng quán')
        )
      ),
      h('div', { className: 'admin-hub-card' },
        h('div', { className: 'hub-card-header' },
          renderIcon('shield', 'text-gold'),
          h('h4', { className: 'hub-card-title' }, 'Tài Chính & Kịch Bản')
        ),
        h('div', { className: 'hub-links-wrap' },
          h('a', { className: 'hub-link-pill highlight', href: '#/admin/refunds' },
            'Duyệt hoàn cọc (24h)',
            pendingRefunds.length > 0 ? h('span', { className: 'hub-badge badge-gold' }, String(pendingRefunds.length)) : null
          ),
          h('a', { className: 'hub-link-pill', href: '#/admin/games' }, 'Kho 42 kịch bản & giá'),
          h('a', { className: 'hub-link-pill', href: '#/admin/promotions' }, 'Ưu đãi & sự cố #7')
        )
      ),
      h('div', { className: 'admin-hub-card' },
        h('div', { className: 'hub-card-header' },
          renderIcon('user', 'text-cyan'),
          h('h4', { className: 'hub-card-title' }, 'Hệ Thống & Kiểm Toán')
        ),
        h('div', { className: 'hub-links-wrap' },
          h('a', { className: 'hub-link-pill', href: '#/admin/sessions' }, 'Lịch trình ca chơi'),
          h('a', { className: 'hub-link-pill', href: '#/admin/users' }, 'Tài khoản & phân quyền'),
          h('a', { className: 'hub-link-pill', href: '#/admin/audit' }, 'Nhật ký kiểm toán (Audit)')
        )
      )
    );
    container.appendChild(navHub);

    // Urgent Action Lists
    const urgentSection = h('div', { className: 'admin-urgent-section mt-4' });

    // Urgent 1: Rooms waiting for DM
    urgentSection.appendChild(
      h('div', { className: 'tactical-card mb-4' },
        h('div', { className: 'tactical-section-header' },
          h('h3', { className: 'tactical-section-title' },
            renderIcon('user', 'text-amber'),
            `1. Các Vụ Án Đã Đủ Người, Cần Gán Quản Trò (${queue.waiting_dm.length})`
          ),
          h('span', { className: 'status-tag-urgent' }, queue.waiting_dm.length > 0 ? 'Cần xử lý ngay' : 'Đã hoàn tất')
        ),
        queue.waiting_dm.length === 0
          ? h('p', { className: 'text-muted' }, 'Hiện tại không có phòng nào đang chờ phân công DM.')
          : h('div', { className: 'table-scroll-wrapper' },
              h('table', { className: 'ui-data-table' },
                h('thead', {},
                  h('tr', {},
                    h('th', { scope: 'col' }, 'Mã phòng'),
                    h('th', { scope: 'col' }, 'Kịch bản'),
                    h('th', { scope: 'col' }, 'Giờ bắt đầu ca'),
                    h('th', { scope: 'col' }, 'Thám tử'),
                    h('th', { scope: 'col' }, 'Hành động')
                  )
                ),
                h('tbody', {},
                  ...queue.waiting_dm.map((r) => {
                    const game = sharedStore.data.games.find((g) => g.id === r.game_id);
                    return h('tr', {},
                      h('td', {}, h('strong', { className: 'highlight-gold' }, r.code)),
                      h('td', {}, game?.title || r.game_id),
                      h('td', {}, formatDateTime(r.session_start_time)),
                      h('td', {}, `${r.participants.length} người`),
                      h('td', {},
                        h('a', { className: 'btn btn-primary btn-sm', href: `#/admin/dm-assignments?room_id=${r.id}` }, 'Gán DM ngay →')
                      )
                    );
                  })
                )
              )
            )
      )
    );

    // Urgent 2: Rooms waiting for Venue
    urgentSection.appendChild(
      h('div', { className: 'tactical-card mb-4' },
        h('div', { className: 'tactical-section-header' },
          h('h3', { className: 'tactical-section-title' },
            renderIcon('door', 'text-cyan'),
            `2. Các Phòng Đã Chấp Nhận DM, Chờ Chốt Mật Thất Quán (${queue.waiting_org.length})`
          ),
          h('span', { className: 'status-tag-info' }, queue.waiting_org.length > 0 ? 'Chờ xếp phòng' : 'Đã hoàn tất')
        ),
        queue.waiting_org.length === 0
          ? h('p', { className: 'text-muted' }, 'Hiện không có phòng nào đang chờ chốt địa điểm vật lý.')
          : h('div', { className: 'table-scroll-wrapper' },
              h('table', { className: 'ui-data-table' },
                h('thead', {},
                  h('tr', {},
                    h('th', { scope: 'col' }, 'Mã phòng'),
                    h('th', { scope: 'col' }, 'Quản trò (DM)'),
                    h('th', { scope: 'col' }, 'Giờ bắt đầu'),
                    h('th', { scope: 'col' }, 'Quy mô người'),
                    h('th', { scope: 'col' }, 'Hành động')
                  )
                ),
                h('tbody', {},
                  ...queue.waiting_org.map((r) =>
                    h('tr', {},
                      h('td', {}, h('strong', { className: 'highlight-gold' }, r.code)),
                      h('td', {}, r.dm_assignment?.dm_name || 'Đã phân công'),
                      h('td', {}, formatDateTime(r.session_start_time)),
                      h('td', {}, `${r.participants.length} người chơi + 1 DM`),
                      h('td', {},
                        h('a', { className: 'btn btn-primary btn-sm', href: `#/admin/venues?room_id=${r.id}` }, 'Chốt mật thất →')
                      )
                    )
                  )
                )
              )
            )
      )
    );

    // Urgent 3: Live Audit Intelligence Stream
    urgentSection.appendChild(
      h('div', { className: 'tactical-card mb-4' },
        h('div', { className: 'tactical-section-header' },
          h('h3', { className: 'tactical-section-title' },
            renderIcon('clock', 'text-gold'),
            '3. Dòng Nhật Ký Nghiệp Vụ Gần Nhất (Audit Intelligence Stream)'
          ),
          h('a', { className: 'link-gold', href: '#/admin/audit', style: { fontSize: '0.85rem' } }, 'Xem toàn bộ sổ cái →')
        ),
        h('div', { className: 'table-scroll-wrapper' },
          h('table', { className: 'ui-data-table' },
            h('thead', {},
              h('tr', {},
                h('th', { scope: 'col' }, 'Thời điểm'),
                h('th', { scope: 'col' }, 'Tác nhân'),
                h('th', { scope: 'col' }, 'Hành động'),
                h('th', { scope: 'col' }, 'Chi tiết nghiệp vụ & Lý do')
              )
            ),
            h('tbody', {},
              ...recentAudits.map((log) =>
                h('tr', {},
                  h('td', { className: 'text-muted', style: { fontFamily: 'var(--font-mono)', fontSize: '0.78rem' } }, formatDateTime(log.created_at)),
                  h('td', {}, h('strong', { className: 'text-light' }, log.actor_name), ` (${log.actor_role || 'ADMIN'})`),
                  h('td', {}, h('span', { className: 'buffer-pill safe' }, log.action)),
                  h('td', { className: 'text-secondary' }, log.details || log.reason || '-')
                )
              )
            )
          )
        )
      )
    );

    container.appendChild(urgentSection);
    this.mountPoint.appendChild(container);
  }
}
