/**
 * Admin System Audit Trail Page (#/admin/audit)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { formatDateTime } from '../../utils/format.js';

export class AdminAuditPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.activeFilter = 'ALL';
    this.searchQuery = '';
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-audit container' });

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
            'IMMUTABLE COMPLIANCE // SYSTEM AUDIT INTELLIGENCE'
          ),
          h('h1', { className: 'page-title' }, 'Nhật Ký Kiểm Toán Nghiệp Vụ (Audit Trail)'),
          h('p', { className: 'page-subtitle text-muted' },
            'Ghi nhận bất biến toàn bộ hành động điều phối, phân công Quản trò, thu hồi nhiệm vụ, duyệt hoàn cọc và sửa đổi biểu phí.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    // Filter Bar
    const filterActions = [
      { key: 'ALL', label: 'TẤT CẢ HÀNH ĐỘNG' },
      { key: 'ASSIGN_DM', label: 'GÁN DM' },
      { key: 'ADMIN_REVOKE_ASSIGNMENT', label: 'THU HỒI DM' },
      { key: 'CONFIRM_VENUE', label: 'CHỐT MẬT THẤT' },
      { key: 'APPROVE_REFUND', label: 'DUYỆT HOÀN CỌC' },
      { key: 'REJECT_REFUND', label: 'TỪ CHỐI HOÀN' },
      { key: 'UPDATE_GAME_PRICING', label: 'SỬA BIỂU PHÍ' },
      { key: 'UPDATE_USER_ROLE', label: 'PHÂN QUYỀN RBAC' },
      { key: 'CANCEL_ROOM', label: 'HỦY VỤ ÁN' },
    ];

    const filterBar = h('div', { className: 'tactical-filter-bar' },
      ...filterActions.map((f) =>
        h('button', {
          className: `tactical-filter-btn ${this.activeFilter === f.key ? 'active' : ''}`,
          onclick: () => {
            this.activeFilter = f.key;
            this.render();
          },
        }, f.label)
      )
    );
    container.appendChild(filterBar);

    // Search bar
    const searchRow = h('div', { className: 'mb-3' },
      h('input', {
        type: 'text',
        className: 'form-control',
        placeholder: 'Tìm kiếm theo tên người thực hiện, đối tượng hoặc nội dung chi tiết...',
        value: this.searchQuery,
        oninput: (e) => {
          this.searchQuery = e.target.value.toLowerCase();
          this.render();
        },
      })
    );
    container.appendChild(searchRow);

    let logs = sharedStore.data.auditLogs || [];

    if (this.activeFilter !== 'ALL') {
      logs = logs.filter((l) => l.action === this.activeFilter);
    }

    if (this.searchQuery) {
      logs = logs.filter((l) =>
        (l.actor_name && l.actor_name.toLowerCase().includes(this.searchQuery)) ||
        (l.action && l.action.toLowerCase().includes(this.searchQuery)) ||
        (l.details && l.details.toLowerCase().includes(this.searchQuery)) ||
        (l.target_id && l.target_id.toLowerCase().includes(this.searchQuery))
      );
    }

    const card = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('clock', 'text-gold'),
          `Sổ Cái Kiểm Toán (${logs.length} Bản Ghi Phù Hợp)`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// IMMUTABLE-LEDGER')
      ),
      logs.length === 0
        ? h('p', { className: 'text-muted' }, 'Không tìm thấy nhật ký kiểm toán phù hợp.')
        : h('div', { className: 'table-scroll-wrapper' },
            h('table', { className: 'ui-data-table' },
              h('thead', {},
                h('tr', {},
                  h('th', { scope: 'col' }, 'Thời điểm'),
                  h('th', { scope: 'col' }, 'Người thực hiện'),
                  h('th', { scope: 'col' }, 'Hành động'),
                  h('th', { scope: 'col' }, 'Đối tượng'),
                  h('th', { scope: 'col' }, 'Chi tiết nghiệp vụ & Lý do bắt buộc')
                )
              ),
              h('tbody', {},
                ...logs.map((log) =>
                  h('tr', {},
                    h('td', { className: 'text-muted font-mono', style: { fontSize: '0.78rem', whiteSpace: 'nowrap' } }, formatDateTime(log.created_at)),
                    h('td', {},
                      h('strong', { className: 'text-light' }, log.actor_name),
                      h('div', { className: 'text-muted font-mono', style: { fontSize: '0.72rem' } }, log.actor_role || 'ADMIN')
                    ),
                    h('td', {},
                      h('span', {
                        className: `buffer-pill ${
                          log.action.includes('REVOKE') || log.action.includes('REJECT') || log.action.includes('CANCEL')
                            ? 'warning'
                            : 'safe'
                        }`,
                      }, log.action)
                    ),
                    h('td', { className: 'font-mono text-muted', style: { fontSize: '0.78rem' } },
                      `${log.target_type || ''} [${log.target_id || ''}]`
                    ),
                    h('td', { className: 'text-secondary', style: { fontSize: '0.85rem' } },
                      h('div', {}, log.details || '-'),
                      log.reason ? h('div', { className: 'text-danger font-mono mt-1', style: { fontSize: '0.78rem' } }, `Lý do: "${log.reason}"`) : null
                    )
                  )
                )
              )
            )
          )
    );
    container.appendChild(card);

    this.mountPoint.appendChild(container);
  }
}
