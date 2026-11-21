/**
 * Admin User & Roles Management Page (#/admin/users)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth, ROLES, ROLE_LABELS, DEMO_USERS } from '../../utils/auth.js';
import { showToast } from '../../components/toast.js';

export class AdminUsersPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-users container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('user', 'text-gold'),
            'RBAC SECURITY // ACCESS CONTROL & STUDENT VERIFICATION'
          ),
          h('h1', { className: 'page-title' }, 'Quản Lý Tài Khoản & Phân Quyền (RBAC)'),
          h('p', { className: 'page-subtitle text-muted' },
            'Kiểm soát đặc quyền vai trò hệ thống (Người chơi, Quản trò, Ban Quản trị) và phê duyệt trạng thái ưu đãi Học Sinh - Sinh Viên.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    const usersList = Object.values(DEMO_USERS);

    const card = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('shield', 'text-gold'),
          `Danh Sách Tài Khoản Hệ Thống (${usersList.length})`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// RBAC-REGISTRY')
      ),
      h('div', { className: 'table-scroll-wrapper' },
        h('table', { className: 'ui-data-table' },
          h('thead', {},
            h('tr', {},
              h('th', { scope: 'col' }, 'Họ và tên'),
              h('th', { scope: 'col' }, 'Email đăng nhập'),
              h('th', { scope: 'col' }, 'Vai trò hiện tại'),
              h('th', { scope: 'col' }, 'Xác thực HSSV'),
              h('th', { scope: 'col' }, 'Cập nhật vai trò (RBAC)'),
              h('th', { scope: 'col' }, 'Thao tác HSSV')
            )
          ),
          h('tbody', {},
            ...usersList.map((u) => {
              let selectedRole = u.role;

              return h('tr', {},
                h('td', {},
                  h('strong', { className: 'text-light' }, u.name),
                  h('div', { className: 'text-muted font-mono', style: { fontSize: '0.75rem' } }, `ID: ${u.id}`)
                ),
                h('td', { className: 'text-secondary font-mono', style: { fontSize: '0.85rem' } }, u.email),
                h('td', {},
                  h('span', {
                    className: `buffer-pill ${u.role === 'ADMIN' ? 'safe' : u.role === 'DM' ? 'warning' : 'safe'}`,
                  }, ROLE_LABELS[u.role] || u.role)
                ),
                h('td', {},
                  u.student_verified
                    ? h('span', { className: 'text-emerald', style: { fontSize: '0.82rem' } }, '✓ ĐÃ XÁC MINH')
                    : h('span', { className: 'text-muted', style: { fontSize: '0.82rem' } }, 'Chưa kích hoạt')
                ),
                h('td', {},
                  h('div', { className: 'flex-align-center gap-2' },
                    h('select', {
                      className: 'form-control form-control-sm',
                      style: { maxWidth: '140px' },
                      onchange: (e) => { selectedRole = e.target.value; },
                    },
                      ...Object.entries(ROLE_LABELS).map(([rKey, rLabel]) =>
                        h('option', { value: rKey, selected: rKey === u.role }, rLabel)
                      )
                    ),
                    h('button', {
                      className: 'btn btn-sm btn-secondary text-gold',
                      onclick: () => {
                        try {
                          sharedStore.adminUpdateUserRole(u.id, selectedRole, user);
                          u.role = selectedRole;
                          showToast(`Đã cập nhật vai trò của ${u.name} sang ${ROLE_LABELS[selectedRole]} và ghi nhận audit log!`, 'success');
                          this.render();
                        } catch (err) {
                          showToast(err.message, 'error');
                        }
                      },
                    }, 'Lưu')
                  )
                ),
                h('td', {},
                  h('button', {
                    className: `btn btn-sm ${u.student_verified ? 'btn-secondary text-warning' : 'btn-primary'}`,
                    onclick: () => {
                      u.student_verified = !u.student_verified;
                      sharedStore.data.auditLogs.unshift({
                        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
                        actor_name: user.name,
                        actor_id: user.id,
                        actor_role: 'ADMIN',
                        action: 'UPDATE_STUDENT_STATUS',
                        target_type: 'User',
                        target_id: u.id,
                        details: `Admin ${user.name} đã ${u.student_verified ? 'duyệt' : 'hủy'} xác thực thẻ HSSV cho ${u.name}.`,
                        created_at: new Date().toISOString(),
                      });
                      sharedStore.save(sharedStore.data);
                      showToast(`Đã ${u.student_verified ? 'xác nhận' : 'thu hồi'} ưu đãi HSSV cho ${u.name}.`, 'info');
                      this.render();
                    },
                  }, u.student_verified ? 'Hủy HSSV' : 'Duyệt HSSV')
                )
              );
            })
          )
        )
      )
    );
    container.appendChild(card);

    this.mountPoint.appendChild(container);
  }
}
