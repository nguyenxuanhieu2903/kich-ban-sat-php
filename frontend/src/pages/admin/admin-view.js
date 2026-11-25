import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml } from '../../utils/sanitize.js';

export class AdminView {
  constructor(container, onOpenAuthModal = () => {}) {
    this.container = container;
    this.onOpenAuthModal = onOpenAuthModal;
    this.currentSubTab = 'users'; // 'users' | 'rooms' | 'refunds'
    this.users = [];
    this.rooms = [];
    this.refunds = [];
  }

  async render() {
    const user = authState.getUser();

    if (!user) {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 48px 24px; max-width: 600px; margin: 40px auto;">
          <div style="font-size: 3rem; margin-bottom: 16px;">🛡️</div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px;">Cổng Quản Trị Hệ Thống (Admin Portal)</h2>
          <p style="color: var(--color-text-muted); margin-bottom: 24px;">
            Vui lòng đăng nhập với tài khoản Quản Trị Viên (ADMIN) để truy cập chức năng này.
          </p>
          <button id="btn-admin-login" class="btn btn-primary" style="padding: 10px 24px;">
            Đăng nhập Quản Trị Viên
          </button>
        </div>
      `;
      this.container.querySelector('#btn-admin-login').onclick = () => this.onOpenAuthModal();
      return;
    }

    if (user.role !== 'ADMIN') {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 48px 24px; max-width: 600px; margin: 40px auto; border-color: var(--color-error);">
          <div style="font-size: 3rem; margin-bottom: 16px;">⛔</div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: var(--color-error);">Không Đủ Quyền Truy Cập (403 Forbidden)</h2>
          <p style="color: var(--color-text-muted); margin-bottom: 16px;">
            Tài khoản hiện tại của bạn (<strong>${escapeHtml(user.name)}</strong>) có vai trò <strong>${escapeHtml(user.role)}</strong>. Chỉ có <strong>ADMIN</strong> mới có quyền quản trị người dùng, hủy phòng và xét duyệt hoàn tiền.
          </p>
          <button id="btn-admin-switch" class="btn btn-primary" style="padding: 10px 24px;">
            Chuyển sang tài khoản Admin (1-click)
          </button>
        </div>
      `;
      this.container.querySelector('#btn-admin-switch').onclick = () => this.onOpenAuthModal();
      return;
    }

    this.container.innerHTML = `
      <div style="margin-bottom: 24px;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0;">🛡️ Bảng Điều Khiển Quản Trị Hệ Thống</h2>
        <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">
          Quản lý người dùng, phân quyền RBAC, can thiệp hủy phòng theo Quy Tắc Vàng 24h và xét duyệt hoàn tiền
        </p>
      </div>

      <!-- Admin Sub Tabs -->
      <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px; flex-wrap: wrap;">
        <button id="tab-btn-overview" class="admin-tab-btn ${this.currentSubTab === 'overview' ? 'active' : ''}">
          📊 Tổng quan vận hành
        </button>
        <button id="tab-btn-payments" class="admin-tab-btn ${this.currentSubTab === 'payments' ? 'active' : ''}">
          💳 Sổ cái &amp; Giao dịch
        </button>
        <button id="tab-btn-users" class="admin-tab-btn ${this.currentSubTab === 'users' ? 'active' : ''}">
          👥 Quản lý Người dùng &amp; Vai trò
        </button>
        <button id="tab-btn-rooms" class="admin-tab-btn ${this.currentSubTab === 'rooms' ? 'active' : ''}">
          🎲 Quản lý &amp; Hủy Phòng Chơi (Quy Tắc 24h)
        </button>
        <button id="tab-btn-refunds" class="admin-tab-btn ${this.currentSubTab === 'refunds' ? 'active' : ''}">
          💰 Xét Duyệt Hoàn Tiền (Refunds)
        </button>
        <button id="tab-btn-games" class="admin-tab-btn ${this.currentSubTab === 'games' ? 'active' : ''}">
          🎭 Kịch Bản &amp; Bảng Giá
        </button>
        <button id="tab-btn-promotions" class="admin-tab-btn ${this.currentSubTab === 'promotions' ? 'active' : ''}">
          🎟️ Khuyến Mãi
        </button>
        <button id="tab-btn-venues" class="admin-tab-btn ${this.currentSubTab === 'venues' ? 'active' : ''}">
          📍 Chi Nhánh &amp; Phòng Vật Lý
        </button>
        <button id="tab-btn-audit" class="admin-tab-btn ${this.currentSubTab === 'audit' ? 'active' : ''}">
          📜 Nhật ký &amp; Hệ thống
        </button>
      </div>

      <div id="admin-subtab-content">
        <!-- Rendered based on sub-tab -->
      </div>

      <!-- Dialog Hủy Phòng -->
      <dialog id="admin-cancel-room-dialog" style="padding: 24px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main); max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
          <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600; color: var(--color-error);">Hủy Phòng Chơi Khẩn Cấp (Admin)</h3>
          <button id="btn-close-cancel-room" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>
        <form id="admin-cancel-room-form">
          <input type="hidden" id="cancel-room-id" />
          <div style="background: var(--color-warning-bg); border: 1px solid var(--color-warning); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.85rem; color: var(--color-warning);">
            <strong>Lưu ý Quy Tắc Vàng 24 Giờ:</strong><br/>
            - Nếu hủy <strong>&ge; 24 giờ</strong> trước giờ chơi: Toàn bộ người chơi đã cọc được tự động duyệt hoàn tiền (APPROVED).<br/>
            - Nếu hủy <strong>&lt; 24 giờ</strong> trước giờ chơi: Tiền cọc không được hoàn lại (REJECTED) do vi phạm chính sách hủy muộn.
          </div>
          <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
              Lý do hủy phòng <span style="color: var(--color-error);">* (Bắt buộc)</span>
            </label>
            <textarea id="cancel-room-reason" required rows="3" class="form-input" style="width: 100%; box-sizing: border-box; resize: vertical;" placeholder="Nhập lý do chi tiết (VD: Chi nhánh mất điện, sự cố đột xuất...)"></textarea>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="btn-cancel-cancel-room" class="btn" style="background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted);">
              Đóng
            </button>
            <button type="submit" class="btn" style="background: var(--color-error); color: white; border: none;">
              Tiến hành Hủy Phòng
            </button>
          </div>
        </form>
      </dialog>

      <!-- Dialog Phân Công DM -->
      <dialog id="admin-assign-dm-dialog" style="padding: 24px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main); max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
          <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600; color: var(--color-primary);">Phân Công Quản Trò (DM)</h3>
          <button id="btn-close-assign-dm" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>
        <form id="admin-assign-dm-form">
          <input type="hidden" id="assign-dm-room-id" />
          <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
              Chọn Quản Trò <span style="color: var(--color-error);">*</span>
            </label>
            <select id="assign-dm-user-id" class="form-input" style="width: 100%;">
              <option value="2">Host Hoàng Nam (DM - ID #2)</option>
              <option value="1">Admin Quản Trị (ADMIN - ID #1)</option>
            </select>
          </div>
          <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
              Ghi chú phân công
            </label>
            <input type="text" id="assign-dm-notes" class="form-input" style="width: 100%;" placeholder="Ghi chú thêm về ca trực..." />
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="btn-cancel-assign-dm" class="btn" style="background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted);">
              Đóng
            </button>
            <button type="submit" class="btn btn-primary">
              Xác nhận Phân Công
            </button>
          </div>
        </form>
      </dialog>
    `;

    this.container.querySelector('#tab-btn-overview').onclick = () => this.switchSubTab('overview');
    this.container.querySelector('#tab-btn-payments').onclick = () => this.switchSubTab('payments');
    this.container.querySelector('#tab-btn-users').onclick = () => this.switchSubTab('users');
    this.container.querySelector('#tab-btn-rooms').onclick = () => this.switchSubTab('rooms');
    this.container.querySelector('#tab-btn-refunds').onclick = () => this.switchSubTab('refunds');
    this.container.querySelector('#tab-btn-games').onclick = () => this.switchSubTab('games');
    this.container.querySelector('#tab-btn-promotions').onclick = () => this.switchSubTab('promotions');
    this.container.querySelector('#tab-btn-venues').onclick = () => this.switchSubTab('venues');
    this.container.querySelector('#tab-btn-audit').onclick = () => this.switchSubTab('audit');

    this.setupCancelRoomDialog();
    this.setupAssignDmDialog();
    await this.renderSubTabContent();
  }

  switchSubTab(tab) {
    this.currentSubTab = tab;
    this.container.querySelectorAll('.admin-tab-btn').forEach(b => b.classList.remove('active'));
    this.container.querySelector(`#tab-btn-${tab}`).classList.add('active');
    this.renderSubTabContent();
  }

  async renderSubTabContent() {
    const content = this.container.querySelector('#admin-subtab-content');
    if (this.currentSubTab === 'overview') {
      await this.renderOverviewTab(content);
    } else if (this.currentSubTab === 'payments') {
      await this.renderPaymentsTab(content);
    } else if (this.currentSubTab === 'users') {
      await this.renderUsersTab(content);
    } else if (this.currentSubTab === 'rooms') {
      await this.renderRoomsTab(content);
    } else if (this.currentSubTab === 'refunds') {
      await this.renderRefundsTab(content);
    } else if (this.currentSubTab === 'games') {
      await this.renderGamesTab(content);
    } else if (this.currentSubTab === 'promotions') {
      await this.renderPromotionsTab(content);
    } else if (this.currentSubTab === 'venues') {
      await this.renderVenuesTab(content);
    } else if (this.currentSubTab === 'audit') {
      await this.renderAuditTab(content);
    }
  }

  // --- SUBTAB 1: USERS & ROLES ---
  async renderUsersTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải danh sách người dùng...</div>`;

    try {
      const res = await api.get('/admin/users');
      this.users = res.data || [];

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Danh Sách Người Dùng (${this.users.length})</h3>
            <span style="font-size: 0.8rem; color: var(--color-text-muted);">Chống tự giáng chức & tự khóa tài khoản Admin</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">ID</th>
                  <th style="padding: 12px 16px;">Họ tên</th>
                  <th style="padding: 12px 16px;">Email</th>
                  <th style="padding: 12px 16px;">Vai trò (RBAC)</th>
                  <th style="padding: 12px 16px;">Thẻ HSSV</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px; text-align: right;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                ${this.users.map(u => this.renderUserRow(u)).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      // Gắn event đổi role và khóa
      this.users.forEach(u => {
        const roleSelect = container.querySelector(`#select-role-${u.id}`);
        if (roleSelect) {
          roleSelect.onchange = (e) => this.handleUpdateRole(u.id, e.target.value);
        }

        const toggleActiveBtn = container.querySelector(`#btn-toggle-active-${u.id}`);
        if (toggleActiveBtn) {
          toggleActiveBtn.onclick = () => this.handleToggleActive(u.id, u.is_active);
        }
      });

      container.querySelectorAll('[data-verify-student]').forEach(btn => {
        btn.onclick = async () => {
          const userId = btn.dataset.verifyStudent;
          const action = btn.dataset.action;
          let reason = '';
          if (action === 'REJECTED') {
            reason = prompt('Nhập lý do từ chối thẻ HSSV:', 'Ảnh thẻ không rõ ràng hoặc không hợp lệ');
            if (reason === null) return;
          }
          try {
            const res = await api.post(`/admin/users/${userId}/verify-student`, { status: action, reason });
            toast.success(res.message || 'Thao tác xác thực thẻ HSSV thành công!');
            this.renderUsersTab(container);
          } catch (err) {
            toast.error(err.message || 'Lỗi xác thực thẻ HSSV');
          }
        };
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  renderUserRow(u) {
    const isCurrentUser = authState.getUser()?.id === u.id;
    return `
      <tr style="border-bottom: 1px solid var(--color-border);">
        <td style="padding: 12px 16px; color: var(--color-text-muted); font-mono;">#${escapeHtml(u.id)}</td>
        <td style="padding: 12px 16px; font-weight: 600;">
          ${escapeHtml(u.name)} ${isCurrentUser ? '<span class="badge" style="background: var(--color-primary); color: white; font-size: 0.7rem;">Bạn</span>' : ''}
        </td>
        <td style="padding: 12px 16px; color: var(--color-text-muted);">${escapeHtml(u.email)}</td>
        <td style="padding: 12px 16px;">
          <select id="select-role-${escapeHtml(u.id)}" class="form-input" style="padding: 4px 8px; font-size: 0.8rem;" ${isCurrentUser ? 'disabled title="Không thể tự đổi quyền của bản thân"' : ''}>
            <option value="PLAYER" ${u.role === 'PLAYER' ? 'selected' : ''}>PLAYER</option>
            <option value="DM" ${u.role === 'DM' ? 'selected' : ''}>DM (Quản trò)</option>
            <option value="ADMIN" ${u.role === 'ADMIN' ? 'selected' : ''}>ADMIN (Quản trị)</option>
          </select>
        </td>
        <td style="padding: 12px 16px;">
          ${u.student_card_status === 'APPROVED' ? `
            <span class="badge" style="background: var(--color-success-bg); color: var(--color-success);">Đã duyệt (-20k)</span>
          ` : u.student_card_status === 'PENDING' ? `
            <div style="display: flex; align-items: center; gap: 4px;">
              <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">Chờ duyệt</span>
              <button data-verify-student="${escapeHtml(u.id)}" data-action="APPROVED" class="btn" style="padding: 2px 6px; font-size: 0.72rem; background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success); cursor: pointer;">Duyệt</button>
              <button data-verify-student="${escapeHtml(u.id)}" data-action="REJECTED" class="btn" style="padding: 2px 6px; font-size: 0.72rem; background: var(--color-error-bg); color: var(--color-error); border: 1px solid var(--color-error); cursor: pointer;">Từ chối</button>
            </div>
          ` : u.student_card_status === 'REJECTED' ? `
            <span class="badge" style="background: var(--color-error-bg); color: var(--color-error);">Bị từ chối</span>
          ` : `<span style="color: var(--color-text-muted); font-size: 0.8rem;">Chưa gửi</span>`}
        </td>
        <td style="padding: 12px 16px;">
          ${u.is_active ? 
            `<span class="badge" style="background: var(--color-success-bg); color: var(--color-success);">Hoạt động</span>` : 
            `<span class="badge" style="background: var(--color-error-bg); color: var(--color-error);">Bị khóa</span>`}
        </td>
        <td style="padding: 12px 16px; text-align: right;">
          <button id="btn-toggle-active-${escapeHtml(u.id)}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: ${u.is_active ? 'var(--color-error-bg)' : 'var(--color-success-bg)'}; color: ${u.is_active ? 'var(--color-error)' : 'var(--color-success)'}; border: 1px solid ${u.is_active ? 'var(--color-error)' : 'var(--color-success)'};" ${isCurrentUser ? 'disabled title="Không thể tự khóa tài khoản của chính mình"' : ''}>
            ${u.is_active ? 'Khóa tài khoản' : 'Mở khóa'}
          </button>
        </td>
      </tr>
    `;
  }

  async handleUpdateRole(userId, newRole) {
    try {
      const res = await api.put(`/admin/users/${userId}/role`, { role: newRole });
      toast.success(res.message || 'Cập nhật vai trò thành công!');
      this.renderUsersTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Không thể đổi vai trò');
      this.renderUsersTab(this.container.querySelector('#admin-subtab-content'));
    }
  }

  async handleToggleActive(userId, currentActive) {
    const action = currentActive ? 'KHÓA' : 'MỞ KHÓA';
    if (!confirm(`Bạn có chắc chắn muốn ${action} tài khoản này?`)) return;

    try {
      const res = await api.patch(`/admin/users/${userId}`, { is_active: !currentActive });
      toast.success(res.message || `Đã ${action.toLowerCase()} thành công!`);
      this.renderUsersTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Không thể cập nhật trạng thái');
    }
  }

  // --- SUBTAB 2: ROOM MANAGEMENT & CANCELLATION ---
  async renderRoomsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải danh sách phòng...</div>`;

    try {
      const res = await api.get('/game-rooms?status=ALL');
      this.rooms = res?.data?.rooms || (Array.isArray(res?.data) ? res.data : []);

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Danh Sách Phòng Chơi (${this.rooms.length})</h3>
            <span style="font-size: 0.8rem; color: var(--color-text-muted);">Áp dụng Quy Tắc Vàng 24 Giờ khi Admin Hủy</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">Mã phòng</th>
                  <th style="padding: 12px 16px;">Kịch bản</th>
                  <th style="padding: 12px 16px;">Chủ phòng</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px;">Thời gian chơi</th>
                  <th style="padding: 12px 16px; text-align: right;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                ${this.rooms.length === 0 ? `
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: var(--color-text-muted);">
                      Hiện chưa có phòng chơi nào.
                    </td>
                  </tr>
                ` : this.rooms.map(r => `
                  <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px 16px; font-weight: 700; color: var(--color-primary);">${escapeHtml(r.room_code || '#' + r.id)}</td>
                    <td style="padding: 12px 16px; font-weight: 600;">${escapeHtml(r.game_title || 'Kịch bản')}</td>
                    <td style="padding: 12px 16px; color: var(--color-text-muted);">${escapeHtml(r.host_name || 'N/A')}</td>
                    <td style="padding: 12px 16px;"><span class="badge">${escapeHtml(r.status)}</span></td>
                    <td style="padding: 12px 16px; font-size: 0.85rem; color: var(--color-text-muted);">
                      ${r.session_start_at ? escapeHtml(new Date(r.session_start_at).toLocaleString('vi-VN')) : 'Chưa chốt'}
                    </td>
                    <td style="padding: 12px 16px; text-align: right;">
                      ${r.status !== 'CANCELLED' && r.status !== 'FINISHED' ? `
                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                          <button id="btn-admin-assign-dm-${r.id}" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem;">
                            Phân công DM
                          </button>
                          <button id="btn-admin-cancel-room-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-error); color: white; border: none;">
                            Hủy phòng
                          </button>
                        </div>
                      ` : `<span style="color: var(--color-text-muted); font-size: 0.8rem;">Đã đóng</span>`}
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      this.rooms.forEach(r => {
        const assignBtn = container.querySelector(`#btn-admin-assign-dm-${r.id}`);
        if (assignBtn) {
          assignBtn.onclick = () => this.openAssignDmModal(r.id);
        }

        const cancelBtn = container.querySelector(`#btn-admin-cancel-room-${r.id}`);
        if (cancelBtn) {
          cancelBtn.onclick = () => this.openCancelRoomModal(r.id);
        }
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  setupCancelRoomDialog() {
    const dialog = this.container.querySelector('#admin-cancel-room-dialog');
    const closeBtn = this.container.querySelector('#btn-close-cancel-room');
    const cancelBtn = this.container.querySelector('#btn-cancel-cancel-room');
    const form = this.container.querySelector('#admin-cancel-room-form');

    const closeDialog = () => {
      dialog.close();
      form.reset();
    };

    closeBtn.onclick = closeDialog;
    cancelBtn.onclick = closeDialog;

    form.onsubmit = async (e) => {
      e.preventDefault();
      const roomId = this.container.querySelector('#cancel-room-id').value;
      const reason = this.container.querySelector('#cancel-room-reason').value.trim();

      if (!reason) {
        toast.error('Vui lòng nhập lý do hủy phòng!');
        return;
      }

      try {
        const res = await api.post(`/admin/rooms/${roomId}/cancel`, { cancellation_reason: reason });
        toast.success(res.message || 'Hủy phòng thành công!');
        closeDialog();
        this.renderRoomsTab(this.container.querySelector('#admin-subtab-content'));
      } catch (err) {
        toast.error(err.message || 'Không thể hủy phòng');
      }
    };
  }

  openCancelRoomModal(roomId) {
    const dialog = this.container.querySelector('#admin-cancel-room-dialog');
    this.container.querySelector('#cancel-room-id').value = roomId;
    this.container.querySelector('#cancel-room-reason').value = '';
    dialog.showModal();
  }

  setupAssignDmDialog() {
    const dialog = this.container.querySelector('#admin-assign-dm-dialog');
    const closeBtn = this.container.querySelector('#btn-close-assign-dm');
    const cancelBtn = this.container.querySelector('#btn-cancel-assign-dm');
    const form = this.container.querySelector('#admin-assign-dm-form');

    const closeDialog = () => {
      dialog.close();
      form.reset();
    };

    closeBtn.onclick = closeDialog;
    cancelBtn.onclick = closeDialog;

    form.onsubmit = async (e) => {
      e.preventDefault();
      const roomId = this.container.querySelector('#assign-dm-room-id').value;
      const dmUserId = parseInt(this.container.querySelector('#assign-dm-user-id').value, 10);
      const notes = this.container.querySelector('#assign-dm-notes').value.trim();

      try {
        const res = await api.post(`/admin/rooms/${roomId}/assign-dm`, { dm_user_id: dmUserId, notes });
        toast.success(res.message || 'Phân công quản trò thành công!');
        closeDialog();
        this.renderRoomsTab(this.container.querySelector('#admin-subtab-content'));
      } catch (err) {
        toast.error(err.message || 'Không thể phân công quản trò');
      }
    };
  }

  openAssignDmModal(roomId) {
    const dialog = this.container.querySelector('#admin-assign-dm-dialog');
    this.container.querySelector('#assign-dm-room-id').value = roomId;
    this.container.querySelector('#assign-dm-notes').value = '';
    dialog.showModal();
  }

  // --- SUBTAB 3: REFUND MANAGEMENT ---
  async renderRefundsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải danh sách hoàn tiền...</div>`;

    try {
      const res = await api.get('/admin/refunds');
      this.refunds = res.data || [];

      if (this.refunds.length === 0) {
        container.innerHTML = `
          <div class="card" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 12px;">✅</div>
            <h4 style="margin: 0 0 6px 0; color: var(--color-text-main);">Không có yêu cầu hoàn tiền nào cần xử lý</h4>
            <p style="margin: 0; font-size: 0.85rem;">Mọi yêu cầu hoàn cọc từ người chơi rút lui hoặc phòng bị hủy sẽ xuất hiện ở đây.</p>
          </div>
        `;
        return;
      }

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Danh Sách Yêu Cầu Hoàn Tiền (${this.refunds.length})</h3>
            <span style="font-size: 0.8rem; color: var(--color-text-muted);">Cọc 50.000 VNĐ / Người</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">Mã Refund</th>
                  <th style="padding: 12px 16px;">Người chơi</th>
                  <th style="padding: 12px 16px;">Số tiền</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px;">Lý do</th>
                  <th style="padding: 12px 16px; text-align: right;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                ${this.refunds.map(r => `
                  <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px 16px; font-mono; color: var(--color-primary);">#REF-${escapeHtml(r.id)}</td>
                    <td style="padding: 12px 16px; font-weight: 600;">${escapeHtml(r.user_name || 'Người chơi #' + (r.user_id || ''))}</td>
                    <td style="padding: 12px 16px; font-weight: 700; color: var(--color-warning);">${Number(r.amount || 50000).toLocaleString('vi-VN')} đ</td>
                    <td style="padding: 12px 16px;">
                      <span class="badge" style="background: ${r.status === 'APPROVED' ? 'var(--color-success-bg)' : (r.status === 'REJECTED' ? 'var(--color-error-bg)' : 'rgba(245, 158, 11, 0.2)')}; color: ${r.status === 'APPROVED' ? 'var(--color-success)' : (r.status === 'REJECTED' ? 'var(--color-error)' : 'var(--color-warning)')};">
                        ${escapeHtml(r.status)}
                      </span>
                    </td>
                    <td style="padding: 12px 16px; font-size: 0.85rem; color: var(--color-text-muted); max-width: 200px;">
                      ${escapeHtml(r.reason || 'N/A')}
                    </td>
                    <td style="padding: 12px 16px; text-align: right;">
                      ${r.status === 'PENDING' ? `
                        <button id="btn-approve-refund-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-success); color: white; border: none; margin-right: 6px;">
                          Duyệt
                        </button>
                        <button id="btn-reject-refund-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-error); color: white; border: none;">
                          Từ chối
                        </button>
                      ` : `<span style="font-size: 0.8rem; color: var(--color-text-muted);">Đã xử lý</span>`}
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      this.refunds.forEach(r => {
        const approveBtn = container.querySelector(`#btn-approve-refund-${r.id}`);
        if (approveBtn) {
          approveBtn.onclick = () => this.handleApproveRefund(r.id);
        }

        const rejectBtn = container.querySelector(`#btn-reject-refund-${r.id}`);
        if (rejectBtn) {
          rejectBtn.onclick = () => this.handleRejectRefund(r.id);
        }
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  async handleApproveRefund(refundId) {
    if (!confirm('Bạn có chắc chắn muốn DUYỆT hoàn tiền cho yêu cầu này?')) return;
    try {
      const res = await api.post(`/admin/refunds/${refundId}/approve`);
      toast.success(res.message || 'Đã duyệt hoàn tiền thành công!');
      this.renderRefundsTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Không thể duyệt hoàn tiền');
    }
  }

  async handleRejectRefund(refundId) {
    const reason = prompt('Nhập lý do từ chối hoàn tiền:');
    if (!reason) return;
    try {
      const res = await api.post(`/admin/refunds/${refundId}/reject`, { reason });
      toast.success(res.message || 'Đã từ chối hoàn tiền!');
      this.renderRefundsTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Không thể từ chối');
    }
  }

  // --- SUBTAB 4: GAMES & PRICING ---
  async renderGamesTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải kho kịch bản...</div>`;

    try {
      const res = await api.get('/admin/games');
      const games = res?.data?.games || [];

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
              <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Quản Lý Kho Kịch Bản &amp; Bảng Giá (${games.length})</h3>
              <span style="font-size: 0.8rem; color: var(--color-text-muted);">Điều chỉnh kịch bản, thời lượng và các khung giờ giá vé</span>
            </div>
            <button id="btn-admin-add-game" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem;">
              + Thêm kịch bản mới
            </button>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">ID</th>
                  <th style="padding: 12px 16px;">Tên kịch bản</th>
                  <th style="padding: 12px 16px;">Người chơi</th>
                  <th style="padding: 12px 16px;">Thời lượng</th>
                  <th style="padding: 12px 16px;">Mốc giá hiện tại</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px; text-align: right;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                ${games.length === 0 ? `
                  <tr><td colspan="7" style="text-align: center; padding: 24px;" class="text-muted">Chưa có kịch bản nào.</td></tr>
                ` : games.map(g => {
                  const prices = g.prices || [];
                  const priceStr = prices.length > 0
                    ? prices.map(p => `${Number(p.price_per_person).toLocaleString('vi-VN')} đ`).join(', ')
                    : 'Chưa có giá';
                  const isActive = g.status === 'ACTIVE';

                  return `
                    <tr style="border-bottom: 1px solid var(--color-border);">
                      <td style="padding: 12px 16px; font-family: monospace; color: var(--color-primary);">#${escapeHtml(g.id)}</td>
                      <td style="padding: 12px 16px; font-weight: 600;">${escapeHtml(g.name)}</td>
                      <td style="padding: 12px 16px;">${escapeHtml(g.min_players)} – ${escapeHtml(g.max_players)} người</td>
                      <td style="padding: 12px 16px;">${escapeHtml(g.duration_minutes)} phút</td>
                      <td style="padding: 12px 16px; color: var(--color-warning); font-size: 0.85rem;">${escapeHtml(priceStr)}</td>
                      <td style="padding: 12px 16px;">
                        <span class="badge" style="background: ${isActive ? 'var(--color-success-bg)' : 'var(--color-error-bg)'}; color: ${isActive ? 'var(--color-success)' : 'var(--color-error)'};">
                          ${isActive ? 'Hoạt động' : 'Tạm ẩn'}
                        </span>
                      </td>
                      <td style="padding: 12px 16px; text-align: right;">
                        <button class="btn btn-ghost" data-config-game="${g.id}" style="padding: 4px 8px; font-size: 0.78rem; border: 1px solid var(--color-border); color: var(--color-text-main); margin-right: 4px;" title="Cấu hình nhân vật">
                          🎭 Cấu hình vai
                        </button>
                        <button class="btn" data-add-price="${g.id}" style="padding: 4px 8px; font-size: 0.78rem; background: transparent; border: 1px solid var(--color-border); color: var(--color-primary); margin-right: 4px;">
                          + Thêm giá
                        </button>
                        <button class="btn" data-toggle-game="${g.id}" data-status="${g.status}" style="padding: 4px 8px; font-size: 0.78rem; background: ${isActive ? 'var(--color-error-bg)' : 'var(--color-success-bg)'}; color: ${isActive ? 'var(--color-error)' : 'var(--color-success)'}; border: none;">
                          ${isActive ? 'Tạm ẩn' : 'Kích hoạt'}
                        </button>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      container.querySelector('#btn-admin-add-game')?.addEventListener('click', async () => {
        const name = prompt('Nhập tên kịch bản mới:');
        if (!name) return;
        const price = prompt('Nhập giá vé cơ bản (VNĐ / người):', '180000');
        try {
          await api.post('/admin/games', {
            name,
            min_players: 5,
            max_players: 8,
            duration_minutes: 240,
            price_per_person: Number(price) || 180000,
          });
          toast.success(`Đã thêm kịch bản "${name}" thành công!`);
          this.renderGamesTab(container);
        } catch (err) {
          toast.error(err.message || 'Lỗi thêm kịch bản');
        }
      });

      container.querySelectorAll('[data-add-price]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const gameId = btn.dataset.addPrice;
          const price = prompt('Nhập mốc giá vé mới (VNĐ / người):', '200000');
          if (!price) return;
          try {
            await api.post(`/admin/games/${gameId}/prices`, {
              price_per_person: Number(price),
              start_time: '18:00:00',
              end_time: '23:00:00',
            });
            toast.success('Đã thêm mốc giá mới!');
            this.renderGamesTab(container);
          } catch (err) {
            toast.error(err.message || 'Lỗi thêm giá');
          }
        });
      });

      container.querySelectorAll('[data-toggle-game]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const gameId = btn.dataset.toggleGame;
          const currentStatus = btn.dataset.status;
          const newStatus = currentStatus === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
          try {
            await api.patch(`/admin/games/${gameId}`, { status: newStatus });
            toast.success('Đã cập nhật trạng thái kịch bản!');
            this.renderGamesTab(container);
          } catch (err) {
            toast.error(err.message || 'Lỗi cập nhật');
          }
        });
      });

      container.querySelectorAll('[data-config-game]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const gameId = btn.dataset.configGame;
          try {
            const res = await api.get(`/admin/games/${gameId}/configurations`);
            const configs = res?.data?.configurations || [];
            const gameName = res?.data?.game?.name || 'Kịch bản';
            let msg = `CẤU HÌNH NHÂN VẬT - ${gameName.toUpperCase()} (#${gameId})\n\n`;
            if (configs.length === 0) {
              msg += 'Chưa có cấu hình nhân vật nào được lưu.\n';
            } else {
              configs.forEach(c => {
                const charList = Array.isArray(c.configuration?.characters) ? c.configuration.characters.map(ch => ch.name || ch.title).join(', ') : (c.configuration ? JSON.stringify(c.configuration).substring(0, 80) : '—');
                msg += `• Phiên bản v${c.version} [${c.is_active ? 'ACTIVE' : 'INACTIVE'}]: ${charList}\n`;
              });
            }
            msg += '\nBạn có muốn thêm cấu hình phiên bản mới với danh sách nhân vật mẫu không?';
            if (confirm(msg)) {
              const charNames = prompt('Nhập danh sách tên nhân vật (cách nhau bởi dấu phẩy):', 'Thám tử Mori, Bác sĩ Watson, Nữ quý tộc, Quản gia');
              if (charNames) {
                const characters = charNames.split(',').map((n, idx) => ({ id: idx + 1, name: n.trim(), gender: idx % 2 === 0 ? 'M' : 'F', role: 'Player Character' }));
                await api.post(`/admin/games/${gameId}/configurations`, {
                  configuration: { characters, props: ['Bức thư mật', 'Chìa khóa bạc'] },
                  description: `Cấu hình ${characters.length} nhân vật chuẩn`,
                  is_active: 1,
                });
                toast.success(`Đã thêm cấu hình mới cho kịch bản "${gameName}"!`);
                this.renderGamesTab(container);
              }
            }
          } catch (err) {
            toast.error(err.message || 'Lỗi truy vấn cấu hình kịch bản');
          }
        });
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  // --- SUBTAB 5: PROMOTIONS ---
  async renderPromotionsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải danh sách khuyến mãi...</div>`;

    try {
      const res = await api.get('/admin/promotions');
      const promos = res?.data?.promotions || [];

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
              <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Quản Lý Mã Khuyến Mãi (${promos.length})</h3>
              <span style="font-size: 0.8rem; color: var(--color-text-muted);">Cấu hình mã giảm giá, voucher HSSV và ưu đãi ngày trong tuần</span>
            </div>
            <button id="btn-admin-add-promo" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem;">
              + Tạo mã khuyến mãi
            </button>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">Mã Code</th>
                  <th style="padding: 12px 16px;">Tên chương trình</th>
                  <th style="padding: 12px 16px;">Loại &amp; Mức giảm</th>
                  <th style="padding: 12px 16px;">Thời hạn</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px; text-align: right;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                ${promos.length === 0 ? `
                  <tr><td colspan="6" style="text-align: center; padding: 24px;" class="text-muted">Chưa có mã khuyến mãi nào.</td></tr>
                ` : promos.map(p => {
                  const isActive = Boolean(Number(p.is_active));
                  const discountDisplay = p.discount_type === 'PERCENT'
                    ? `Giảm ${p.discount_value}%`
                    : `Giảm ${Number(p.discount_value).toLocaleString('vi-VN')} đ`;

                  return `
                    <tr style="border-bottom: 1px solid var(--color-border);">
                      <td style="padding: 12px 16px; font-family: monospace; font-weight: 700; color: var(--color-primary);">${escapeHtml(p.code)}</td>
                      <td style="padding: 12px 16px; font-weight: 600;">${escapeHtml(p.name)}</td>
                      <td style="padding: 12px 16px; font-weight: 700; color: var(--color-warning);">${escapeHtml(discountDisplay)}</td>
                      <td style="padding: 12px 16px; font-size: 0.82rem; color: var(--color-text-muted);">
                        ${escapeHtml(p.start_at || p.valid_from || 'Bắt đầu')} &rarr; ${escapeHtml(p.end_at || p.valid_to || 'Dài hạn')}
                      </td>
                      <td style="padding: 12px 16px;">
                        <span class="badge" style="background: ${isActive ? 'var(--color-success-bg)' : 'var(--color-error-bg)'}; color: ${isActive ? 'var(--color-success)' : 'var(--color-error)'};">
                          ${isActive ? 'Đang áp dụng' : 'Tạm ngưng'}
                        </span>
                      </td>
                      <td style="padding: 12px 16px; text-align: right;">
                        <button class="btn" data-toggle-promo="${p.id}" data-active="${isActive ? '1' : '0'}" style="padding: 4px 8px; font-size: 0.78rem; background: ${isActive ? 'var(--color-error-bg)' : 'var(--color-success-bg)'}; color: ${isActive ? 'var(--color-error)' : 'var(--color-success)'}; border: none;">
                          ${isActive ? 'Ngưng áp dụng' : 'Kích hoạt lại'}
                        </button>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      container.querySelector('#btn-admin-add-promo')?.addEventListener('click', async () => {
        const code = prompt('Nhập mã code khuyến mãi (VD: VIP_50K):');
        if (!code) return;
        const name = prompt('Nhập tên chương trình:');
        if (!name) return;
        const discountValue = prompt('Nhập số tiền giảm (VNĐ):', '50000');
        try {
          await api.post('/admin/promotions', {
            code: code.trim().toUpperCase(),
            name,
            discount_type: 'FIXED',
            discount_value: Number(discountValue) || 50000,
          });
          toast.success(`Đã tạo mã "${code.toUpperCase()}" thành công!`);
          this.renderPromotionsTab(container);
        } catch (err) {
          toast.error(err.message || 'Lỗi tạo khuyến mãi');
        }
      });

      container.querySelectorAll('[data-toggle-promo]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const promoId = btn.dataset.togglePromo;
          const isAct = btn.dataset.active === '1';
          try {
            if (isAct) {
              await api.delete(`/admin/promotions/${promoId}`);
              toast.info('Đã ngưng áp dụng mã khuyến mãi.');
            } else {
              await api.patch(`/admin/promotions/${promoId}`, { is_active: 1 });
              toast.success('Đã kích hoạt lại mã khuyến mãi!');
            }
            this.renderPromotionsTab(container);
          } catch (err) {
            toast.error(err.message || 'Lỗi cập nhật');
          }
        });
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  // --- SUBTAB 6: VENUES & PHYSICAL ROOMS ---
  async renderVenuesTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải dữ liệu chi nhánh...</div>`;

    try {
      const res = await api.get('/admin/venues');
      const venues = res?.data?.venues || [];

      container.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600;">Hệ Thống Chi Nhánh &amp; Phòng Vật Lý (${venues.length} chi nhánh)</h3>
            <span style="font-size: 0.82rem; color: var(--color-text-muted);">Quản lý cơ sở vật chất, phòng chơi và sức chứa tại mỗi địa điểm</span>
          </div>
          <button id="btn-admin-add-venue" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem;">
            + Thêm chi nhánh mới
          </button>
        </div>

        <div style="display: grid; gap: 20px;">
          ${venues.length === 0 ? `
            <div class="card" style="text-align: center; padding: 30px;" class="text-muted">Chưa có chi nhánh nào.</div>
          ` : venues.map(v => {
            const rooms = v.rooms || [];
            const isActive = v.status === 'ACTIVE';

            return `
              <div class="card" style="border: 1px solid var(--color-border); padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
                  <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <h4 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--color-primary);">🏛️ ${escapeHtml(v.name)}</h4>
                      <span class="badge" style="background: ${isActive ? 'var(--color-success-bg)' : 'var(--color-error-bg)'}; color: ${isActive ? 'var(--color-success)' : 'var(--color-error)'}; font-size: 0.72rem;">
                        ${isActive ? 'Đang mở cửa' : 'Tạm ngưng'}
                      </span>
                    </div>
                    <p style="margin: 4px 0 2px; font-size: 0.88rem; color: var(--color-text-main);">📍 ${escapeHtml(v.address)}</p>
                    <p style="margin: 0; font-size: 0.82rem; color: var(--color-text-muted);">Hotline: ${escapeHtml(v.phone || 'Chưa cập nhật')}</p>
                  </div>
                  <div style="display: flex; gap: 6px;">
                    <button class="btn btn-sm btn-ghost" data-add-room="${v.id}" style="font-size: 0.8rem; border-color: var(--color-primary); color: var(--color-primary);">
                      + Thêm phòng
                    </button>
                    <button class="btn btn-sm" data-toggle-venue="${v.id}" data-status="${v.status}" style="font-size: 0.8rem; background: ${isActive ? 'var(--color-error-bg)' : 'var(--color-success-bg)'}; color: ${isActive ? 'var(--color-error)' : 'var(--color-success)'}; border: none;">
                      ${isActive ? 'Đóng cửa' : 'Mở lại'}
                    </button>
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px;">
                  ${rooms.length === 0 ? `
                    <div style="font-size: 0.85rem; color: var(--color-text-muted);">Chưa có phòng vật lý nào. Nhấn "+ Thêm phòng" để cấu hình.</div>
                  ` : rooms.map(r => `
                    <div style="background: rgba(15, 23, 42, 0.4); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 12px; display: flex; justify-content: space-between; align-items: center;">
                      <div>
                        <strong style="font-size: 0.9rem; color: var(--color-text-main); display: block;">🚪 ${escapeHtml(r.name)}</strong>
                        <span style="font-size: 0.78rem; color: var(--color-text-muted);">Sức chứa: <strong>${escapeHtml(r.capacity)} người</strong></span>
                      </div>
                      <span class="badge" style="background: var(--color-success-bg); color: var(--color-success); font-size: 0.72rem;">
                        ${escapeHtml(r.status || 'AVAILABLE')}
                      </span>
                    </div>
                  `).join('')}
                </div>
              </div>
            `;
          }).join('')}
        </div>
      `;

      container.querySelector('#btn-admin-add-venue')?.addEventListener('click', async () => {
        const name = prompt('Nhập tên chi nhánh mới:');
        if (!name) return;
        const address = prompt('Nhập địa chỉ chi nhánh:');
        if (!address) return;
        const phone = prompt('Nhập số hotline liên hệ:', '0901234567');
        try {
          await api.post('/admin/venues', { name, address, phone });
          toast.success(`Đã thêm chi nhánh "${name}" thành công!`);
          this.renderVenuesTab(container);
        } catch (err) {
          toast.error(err.message || 'Lỗi thêm chi nhánh');
        }
      });

      container.querySelectorAll('[data-add-room]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const venueId = btn.dataset.addRoom;
          const name = prompt('Nhập tên phòng vật lý (VD: Phòng Mật Thất 03):');
          if (!name) return;
          const capacity = prompt('Nhập sức chứa tối đa (người):', '8');
          try {
            await api.post(`/admin/venues/${venueId}/rooms`, {
              name,
              capacity: Number(capacity) || 8,
            });
            toast.success(`Đã thêm phòng "${name}" thành công!`);
            this.renderVenuesTab(container);
          } catch (err) {
            toast.error(err.message || 'Lỗi thêm phòng');
          }
        });
      });

      container.querySelectorAll('[data-toggle-venue]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const venueId = btn.dataset.toggleVenue;
          const currentStatus = btn.dataset.status;
          try {
            if (currentStatus === 'ACTIVE') {
              await api.delete(`/admin/venues/${venueId}`);
              toast.info('Đã tạm ngưng hoạt động chi nhánh.');
            } else {
              await api.patch(`/admin/venues/${venueId}`, { status: 'ACTIVE' });
              toast.success('Đã mở lại chi nhánh!');
            }
            this.renderVenuesTab(container);
          } catch (err) {
            toast.error(err.message || 'Lỗi cập nhật chi nhánh');
          }
        });
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  // --- SUBTAB 7: OVERVIEW (TỔNG QUAN VẬN HÀNH) ---
  async renderOverviewTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải số liệu tổng quan hệ thống...</div>`;

    try {
      const res = await api.get('/admin/dashboard');
      const stats = res?.data || {};
      const ov = stats.overview || {};
      const chart = stats.revenue_chart_7d || [];
      const formatMoney = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

      container.innerHTML = `
        <div class="admin-kpi-grid">
          <div class="admin-kpi-card" style="border-top: 3px solid #6366F1;">
            <div style="font-size: 0.82rem; color: var(--portal-text-muted, #9CA3AF);">Doanh thu tháng này</div>
            <div class="admin-kpi-val" style="color: #818CF8;">
              ${formatMoney(ov.revenue_month)}
            </div>
            <small style="color: ${ov.revenue_growth_pct >= 0 ? '#10B981' : '#EF4444'}; font-weight: 600;">
              ${ov.revenue_growth_pct >= 0 ? '↑' : '↓'} ${Math.abs(ov.revenue_growth_pct || 0)}% so với tháng trước
            </small>
          </div>

          <div class="admin-kpi-card" style="border-top: 3px solid #3B82F6;">
            <div style="font-size: 0.82rem; color: var(--portal-text-muted, #9CA3AF);">Ca chơi hôm nay</div>
            <div class="admin-kpi-val" style="color: #60A5FA;">
              ${ov.sessions_today || 0} ca trực
            </div>
            <small style="color: var(--portal-text-muted, #9CA3AF);">Tỷ lệ lấp đầy: <strong>${ov.occupancy_rate || 0}%</strong></small>
          </div>

          <div class="admin-kpi-card" style="border-top: 3px solid #EF4444;">
            <div style="font-size: 0.82rem; color: var(--portal-text-muted, #9CA3AF);">Hoàn cọc chờ duyệt</div>
            <div class="admin-kpi-val" style="color: #F87171;">
              ${ov.pending_refunds || 0} yêu cầu
            </div>
            <small style="color: #FBBF24;">Quy tắc vàng 24 giờ</small>
          </div>

          <div class="admin-kpi-card" style="border-top: 3px solid #10B981;">
            <div style="font-size: 0.82rem; color: var(--portal-text-muted, #9CA3AF);">Thẻ HSSV chờ duyệt</div>
            <div class="admin-kpi-val" style="color: #34D399;">
              ${ov.pending_students || 0} thẻ mới
            </div>
            <small style="color: var(--portal-text-muted, #9CA3AF);">Ưu đãi giảm 20.000đ/vé</small>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
          <!-- Biểu đồ 7 ngày -->
          <div class="admin-kpi-card" style="padding: 20px;">
            <h3 style="margin: 0 0 16px 0; font-size: 1.1rem; color: #818CF8; font-weight: 600;">
              Biểu Đồ Doanh Thu 7 Ngày Gần Nhất
            </h3>
            <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; height: 180px; padding-top: 20px;">
              ${chart.map(c => `
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end;">
                  <span style="font-size: 0.72rem; color: ${c.revenue > 0 ? '#818CF8' : 'var(--portal-text-muted, #9CA3AF)'}; font-weight: ${c.revenue > 0 ? 'bold' : 'normal'};">
                    ${escapeHtml(c.revenue_formatted || '0đ')}
                  </span>
                  <div style="width: 100%; height: ${Math.max(8, c.height_pct || 0)}%; background: linear-gradient(180deg, #6366F1, #4338CA); border-radius: 4px 4px 0 0;" title="${c.date}: ${c.revenue_formatted}"></div>
                  <strong style="font-size: 0.8rem; color: ${c.revenue > 0 ? '#C7D2FE' : 'inherit'};">${escapeHtml(c.day_of_week)}</strong>
                </div>
              `).join('')}
            </div>
          </div>

          <!-- Tình trạng phòng chơi -->
          <div class="admin-kpi-card" style="padding: 20px;">
            <h3 style="margin: 0 0 16px 0; font-size: 1.1rem; color: #818CF8; font-weight: 600;">
              Tình Trạng Các Phòng Chơi
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div style="background: rgba(17,24,39,0.7); padding: 12px; border-radius: 6px; border-left: 3px solid #10B981; border: 1px solid rgba(255,255,255,0.06); border-left-width: 3px;">
                <div style="font-size: 0.8rem; color: var(--portal-text-muted, #9CA3AF);">Đang gom người (FORMING)</div>
                <strong style="font-size: 1.3rem; color: #34D399;">${stats.rooms_by_status?.FORMING || 0} phòng</strong>
              </div>
              <div style="background: rgba(17,24,39,0.7); padding: 12px; border-radius: 6px; border-left: 3px solid #F59E0B; border: 1px solid rgba(255,255,255,0.06); border-left-width: 3px;">
                <div style="font-size: 0.8rem; color: var(--portal-text-muted, #9CA3AF);">Biểu quyết lịch (VOTING)</div>
                <strong style="font-size: 1.3rem; color: #FBBF24;">${(stats.rooms_by_status?.SCHEDULING || 0) + (stats.rooms_by_status?.VOTING || 0)} phòng</strong>
              </div>
              <div style="background: rgba(17,24,39,0.7); padding: 12px; border-radius: 6px; border-left: 3px solid #3B82F6; border: 1px solid rgba(255,255,255,0.06); border-left-width: 3px;">
                <div style="font-size: 0.8rem; color: var(--portal-text-muted, #9CA3AF);">Chờ đặt cọc (DEPOSITING)</div>
                <strong style="font-size: 1.3rem; color: #60A5FA;">${stats.rooms_by_status?.DEPOSITING || 0} phòng</strong>
              </div>
              <div style="background: rgba(17,24,39,0.7); padding: 12px; border-radius: 6px; border-left: 3px solid #8B5CF6; border: 1px solid rgba(255,255,255,0.06); border-left-width: 3px;">
                <div style="font-size: 0.8rem; color: var(--portal-text-muted, #9CA3AF);">Đã chốt &amp; phân DM (CONFIRMED)</div>
                <strong style="font-size: 1.3rem; color: #A78BFA;">${stats.rooms_by_status?.CONFIRMED || 0} phòng</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="admin-kpi-card" style="border: 1px solid rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.04); padding: 20px;">
          <h4 style="margin: 0 0 8px 0; color: #F87171; font-size: 1.1rem;">🚨 Việc Cần Xử Lý Gấp Hôm Nay</h4>
          <p style="margin: 0; font-size: 0.9rem; line-height: 1.6; color: var(--portal-text-muted, #9CA3AF);">
            • Có <strong>${ov.pending_refunds || 0} yêu cầu hoàn cọc</strong> cần đối chiếu mốc thời gian 24 giờ để duyệt trả lại tiền cho khách.<br />
            • Có <strong>${ov.pending_students || 0} yêu cầu xác thực thẻ HSSV</strong> đang chờ xem xét và cấp ưu đãi -20.000đ.<br />
            • Có <strong>${ov.active_rooms || 0} phòng chơi</strong> đang hoạt động gom người và biểu quyết lịch tổ chức.
          </p>
        </div>
      `;
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  // --- SUBTAB 8: PAYMENTS (SỔ CÁI & GIAO DỊCH) ---
  async renderPaymentsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải sổ cái giao dịch...</div>`;

    const formatMoney = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);

    const loadPayments = async (params = {}) => {
      try {
        const query = new URLSearchParams(params).toString();
        const res = await api.get(`/admin/payments${query ? '?' + query : ''}`);
        return res?.data || { payments: [], pagination: {}, meta: {} };
      } catch (err) {
        toast.error(err.message || 'Lỗi tải danh sách thanh toán');
        return { payments: [], pagination: {}, meta: {} };
      }
    };

    const renderPaymentRows = (list) => {
      if (!list || list.length === 0) {
        return `<tr><td colspan="9" style="text-align: center; padding: 30px; color: var(--color-text-muted);">Không tìm thấy giao dịch nào phù hợp.</td></tr>`;
      }
      return list.map(p => {
        const isPaid = p.status === 'PAID';
        const isPending = p.status === 'PENDING';
        const statusBg = isPaid ? 'var(--color-success-bg)' : isPending ? 'var(--color-warning-bg)' : 'rgba(255,255,255,0.1)';
        const statusColor = isPaid ? 'var(--color-success)' : isPending ? 'var(--color-warning)' : 'inherit';

        return `
          <tr style="border-bottom: 1px solid var(--color-border);">
            <td style="padding: 12px 14px;">#${p.id}</td>
            <td style="padding: 12px 14px;">
              <strong>${escapeHtml(p.user_name || 'Người chơi')}</strong><br />
              <small style="color: var(--color-text-muted);">${escapeHtml(p.user_email || p.user_phone || '—')}</small>
            </td>
            <td style="padding: 12px 14px;">
              <strong>${escapeHtml(p.game_name || 'Kịch bản')}</strong><br />
              <small style="color: var(--color-text-muted);">${escapeHtml(p.room_title || 'Phòng #' + (p.game_room_id || ''))}</small>
            </td>
            <td style="padding: 12px 14px;">
              <span class="badge" style="background: rgba(212,175,55,0.15); color: var(--color-primary); font-size: 0.75rem;">
                ${escapeHtml(p.payment_type || 'DEPOSIT')}
              </span>
            </td>
            <td style="padding: 12px 14px; font-weight: 700;">${formatMoney(p.amount)}</td>
            <td style="padding: 12px 14px; font-weight: 700; color: var(--color-success);">${formatMoney(p.paid_amount)}</td>
            <td style="padding: 12px 14px;">
              <span class="badge" style="background: ${statusBg}; color: ${statusColor};">
                ${escapeHtml(p.status)}
              </span>
            </td>
            <td style="padding: 12px 14px; font-size: 0.82rem; color: var(--color-text-muted);">${escapeHtml(p.created_at || '—')}</td>
            <td style="padding: 12px 14px; text-align: right;">
              <button class="btn btn-sm btn-ghost" data-view-payment="${p.id}" style="padding: 4px 8px; font-size: 0.8rem;">
                Chi tiết
              </button>
            </td>
          </tr>
        `;
      }).join('');
    };

    try {
      const initData = await loadPayments();
      const meta = initData.meta || {};

      container.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
          <div class="card" style="border: 1px solid var(--color-primary); padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Tổng tiền thực thu (PAID)</div>
            <div style="font-size: 1.5rem; color: var(--color-primary); font-weight: 800; margin: 4px 0;">
              ${formatMoney(meta.total_paid_amount)}
            </div>
            <small style="color: var(--color-success);">Đã thanh toán thành công</small>
          </div>
          <div class="card" style="padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Đang chờ thanh toán (PENDING)</div>
            <div style="font-size: 1.5rem; color: var(--color-warning); font-weight: 800; margin: 4px 0;">
              ${formatMoney(meta.total_pending_amount)}
            </div>
            <small style="color: var(--color-text-muted);">Chờ người chơi cọc / tất toán</small>
          </div>
          <div class="card" style="padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Tổng số hóa đơn giao dịch</div>
            <div style="font-size: 1.5rem; font-weight: 800; margin: 4px 0;">
              ${initData.pagination?.total || (initData.payments || []).length} đơn
            </div>
            <small style="color: var(--color-primary);">Toàn hệ thống</small>
          </div>
        </div>

        <div class="card" style="padding: 16px; margin-bottom: 20px;">
          <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <input type="text" id="admin-payments-search" class="form-input" placeholder="Tìm người chơi, email, điện thoại, phòng..." style="flex: 1; min-width: 220px;" />
            <select id="admin-payments-status" class="form-input" style="width: auto;">
              <option value="ALL">-- Tất cả trạng thái --</option>
              <option value="PAID">Đã thanh toán (PAID)</option>
              <option value="PENDING">Chờ thanh toán (PENDING)</option>
              <option value="PARTIALLY_PAID">Cọc một phần (PARTIALLY_PAID)</option>
              <option value="REFUNDED">Đã hoàn tiền (REFUNDED)</option>
              <option value="FAILED">Thất bại / Hủy (FAILED)</option>
            </select>
            <select id="admin-payments-type" class="form-input" style="width: auto;">
              <option value="ALL">-- Loại thanh toán --</option>
              <option value="DEPOSIT">Đặt cọc (DEPOSIT)</option>
              <option value="REMAINING">Tất toán còn lại (REMAINING)</option>
              <option value="FULL">Toàn phần (FULL)</option>
            </select>
            <button id="btn-admin-filter-payments" class="btn btn-primary" style="padding: 8px 16px;">
              Lọc
            </button>
          </div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 14px;">ID</th>
                  <th style="padding: 12px 14px;">Người chơi</th>
                  <th style="padding: 12px 14px;">Phòng / Kịch bản</th>
                  <th style="padding: 12px 14px;">Loại</th>
                  <th style="padding: 12px 14px;">Số tiền</th>
                  <th style="padding: 12px 14px;">Đã trả</th>
                  <th style="padding: 12px 14px;">Trạng thái</th>
                  <th style="padding: 12px 14px;">Thời gian</th>
                  <th style="padding: 12px 14px; text-align: right;">Thao tác</th>
                </tr>
              </thead>
              <tbody id="admin-payments-tbody">
                ${renderPaymentRows(initData.payments || [])}
              </tbody>
            </table>
          </div>
        </div>
      `;

      const attachEvents = () => {
        container.querySelectorAll('[data-view-payment]').forEach(btn => {
          btn.onclick = async () => {
            const pid = btn.dataset.viewPayment;
            try {
              const res = await api.get(`/admin/payments/${pid}`);
              const p = res?.data?.payment;
              if (!p) return;
              const txs = p.transactions || [];
              const rfs = p.refunds || [];
              alert(`CHI TIẾT HÓA ĐƠN #${p.id}\n` +
                    `Người chơi: ${p.user_name || 'N/A'} (${p.user_email || 'N/A'})\n` +
                    `Kịch bản: ${p.game_name || 'N/A'} - ${p.room_title || 'N/A'}\n` +
                    `Loại: ${p.payment_type} | Trạng thái: ${p.status}\n` +
                    `Cần thanh toán: ${formatMoney(p.amount)} | Đã trả: ${formatMoney(p.paid_amount)}\n\n` +
                    `LỊCH SỬ GIAO DỊCH (${txs.length}):\n` +
                    (txs.length ? txs.map(t => `- [${t.gateway || 'GATEWAY'}] Mã GD: ${t.transaction_reference || 'N/A'}, Số tiền: ${formatMoney(t.amount)}, TT: ${t.status}`).join('\n') : 'Chưa có giao dịch gateway') +
                    `\n\nYÊU CẦU HOÀN TIỀN (${rfs.length}):\n` +
                    (rfs.length ? rfs.map(r => `- Hoàn ${formatMoney(r.amount)}, Lý do: ${r.reason}, TT: ${r.status}`).join('\n') : 'Không có')
              );
            } catch (err) {
              toast.error(err.message || 'Lỗi tải chi tiết thanh toán');
            }
          };
        });
      };

      container.querySelector('#btn-admin-filter-payments')?.addEventListener('click', async () => {
        const search = container.querySelector('#admin-payments-search').value.trim();
        const status = container.querySelector('#admin-payments-status').value;
        const paymentType = container.querySelector('#admin-payments-type').value;
        const params = {};
        if (search) params.search = search;
        if (status !== 'ALL') params.status = status;
        if (paymentType !== 'ALL') params.payment_type = paymentType;
        const filtered = await loadPayments(params);
        const tbody = container.querySelector('#admin-payments-tbody');
        if (tbody) {
          tbody.innerHTML = renderPaymentRows(filtered.payments || []);
          attachEvents();
        }
      });

      attachEvents();
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }

  // --- SUBTAB 9: AUDIT LOGS & SYSTEM HEALTH (GÓI 3) ---
  async renderAuditTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải nhật ký thao tác và sức khỏe hệ thống...</div>`;

    const loadAudit = async (params = {}) => {
      try {
        const query = new URLSearchParams(params).toString();
        const res = await api.get(`/admin/audit-logs${query ? '?' + query : ''}`);
        return res?.data || { logs: [], pagination: {} };
      } catch (err) {
        toast.error(err.message || 'Lỗi tải nhật ký hệ thống');
        return { logs: [], pagination: {} };
      }
    };

    const renderLogRows = (list) => {
      if (!list || list.length === 0) {
        return `<tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--color-text-muted);">Không có nhật ký thao tác nào.</td></tr>`;
      }
      return list.map(l => {
        const isCritical = (l.action || '').includes('CANCEL') || (l.action || '').includes('DELETE') || (l.action || '').includes('ROLE');
        return `
          <tr style="border-bottom: 1px solid var(--color-border);">
            <td style="padding: 12px 14px;">#${l.id}</td>
            <td style="padding: 12px 14px; font-size: 0.82rem; color: var(--color-text-muted);">${escapeHtml(l.created_at || '—')}</td>
            <td style="padding: 12px 14px;">
              <strong>${escapeHtml(l.user_name || 'Hệ thống (CLI/Admin)')}</strong><br />
              <small style="color: var(--color-text-muted);">${escapeHtml(l.user_email || '—')}</small>
            </td>
            <td style="padding: 12px 14px;">
              <span class="badge" style="background: ${isCritical ? 'var(--color-error-bg)' : 'rgba(212,175,55,0.15)'}; color: ${isCritical ? 'var(--color-error)' : 'var(--color-primary)'};">
                ${escapeHtml(l.action)}
              </span>
            </td>
            <td style="padding: 12px 14px;">
              <strong>${escapeHtml(l.entity_type)}</strong>
              ${l.entity_id ? `<span style="color: var(--color-primary); font-size: 0.8rem;">#${escapeHtml(l.entity_id)}</span>` : ''}
            </td>
            <td style="padding: 12px 14px; font-family: monospace; font-size: 0.8rem; color: var(--color-text-muted);">${escapeHtml(l.ip_address || '—')}</td>
            <td style="padding: 12px 14px; text-align: right;">
              <button class="btn btn-sm btn-ghost" data-view-audit="${l.id}" style="padding: 4px 8px; font-size: 0.8rem;">
                Chi tiết
              </button>
            </td>
          </tr>
        `;
      }).join('');
    };

    try {
      let health = { status: 'HEALTHY', database: {}, runtime: {}, storage: {} };
      try {
        const hRes = await api.get('/admin/system/health');
        health = hRes?.data || health;
      } catch (e) {
        console.warn(e);
      }

      const auditData = await loadAudit();
      const logs = auditData.logs || [];
      const db = health.database || {};
      const rt = health.runtime || {};
      const st = health.storage || {};

      container.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
          <div class="card" style="border: 1px solid var(--color-primary); padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Cơ sở dữ liệu (Database)</div>
            <div style="font-size: 1.35rem; color: var(--color-primary); font-weight: 700; margin: 4px 0;">
              ${escapeHtml(db.engine || 'MySQL')} ${escapeHtml(db.version ? db.version.substring(0, 8) : '')}
            </div>
            <small style="color: var(--color-success);">● ${escapeHtml(db.status || 'OK')} (${db.tables_count || 0} bảng)</small>
          </div>

          <div class="card" style="padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Runtime &amp; Bộ nhớ PHP</div>
            <div style="font-size: 1.35rem; font-weight: 700; margin: 4px 0;">
              PHP ${escapeHtml(rt.php_version || '8.4')} (${escapeHtml(rt.os || 'Windows')})
            </div>
            <small style="color: var(--color-text-muted);">RAM sử dụng: <strong>${rt.memory_usage_mb || 0} MB</strong></small>
          </div>

          <div class="card" style="padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Dung lượng đĩa trống</div>
            <div style="font-size: 1.35rem; color: var(--color-success); font-weight: 700; margin: 4px 0;">
              ${st.disk_free_gb || 0} GB trống
            </div>
            <small style="color: var(--color-text-muted);">Tổng: ${st.disk_total_gb || 0} GB (${st.disk_used_pct || 0}% used)</small>
          </div>

          <div class="card" style="padding: 16px;">
            <div style="font-size: 0.82rem; color: var(--color-text-muted);">Tệp tin Log &amp; Rate Limit</div>
            <div style="font-size: 1.35rem; font-weight: 700; margin: 4px 0;">
              ${st.log_files_count || 0} log files
            </div>
            <small style="color: var(--color-success);">● Ghi đĩa: ${st.storage_writable ? 'Khả dụng' : 'Khóa'}</small>
          </div>
        </div>

        <div class="card" style="padding: 16px; margin-bottom: 20px;">
          <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <input type="text" id="admin-audit-search" class="form-input" placeholder="Tìm theo người thực hiện, hành động, IP..." style="flex: 1; min-width: 240px;" />
            <select id="admin-audit-action" class="form-input" style="width: auto;">
              <option value="ALL">-- Tất cả hành động --</option>
              <option value="USER_ROLE_CHANGED">Đổi vai trò người dùng (USER_ROLE_CHANGED)</option>
              <option value="REFUND_APPROVED">Duyệt hoàn tiền (REFUND_APPROVED)</option>
              <option value="REFUND_REJECTED">Từ chối hoàn tiền (REFUND_REJECTED)</option>
              <option value="GAME_ROOM_CANCELLED_EMERGENCY">Hủy phòng khẩn cấp (GAME_ROOM_CANCELLED_EMERGENCY)</option>
              <option value="GAME_CONFIGURATION_CREATED">Thêm cấu hình nhân vật (GAME_CONFIGURATION_CREATED)</option>
              <option value="GAME_CONFIGURATION_UPDATED">Cập nhật cấu hình nhân vật (GAME_CONFIGURATION_UPDATED)</option>
            </select>
            <button id="btn-admin-filter-audit" class="btn btn-primary" style="padding: 8px 16px;">
              Lọc
            </button>
          </div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 14px;">ID</th>
                  <th style="padding: 12px 14px;">Thời gian</th>
                  <th style="padding: 12px 14px;">Người thực hiện</th>
                  <th style="padding: 12px 14px;">Hành động</th>
                  <th style="padding: 12px 14px;">Đối tượng tác động</th>
                  <th style="padding: 12px 14px;">Địa chỉ IP</th>
                  <th style="padding: 12px 14px; text-align: right;">Chi tiết</th>
                </tr>
              </thead>
              <tbody id="admin-audit-tbody">
                ${renderLogRows(logs)}
              </tbody>
            </table>
          </div>
        </div>
      `;

      const attachAuditEvents = (currentLogs) => {
        container.querySelectorAll('[data-view-audit]').forEach(btn => {
          btn.onclick = () => {
            const lid = Number(btn.dataset.viewAudit);
            const l = currentLogs.find(item => item.id == lid);
            if (!l) return;
            alert(
              `CHI TIẾT NHẬT KÝ THAO TÁC #${l.id}\n` +
              `Thời gian: ${l.created_at}\n` +
              `Người thực hiện: ${l.user_name || 'Hệ thống'} (${l.user_email || 'N/A'})\n` +
              `Hành động: ${l.action}\n` +
              `Đối tượng: ${l.entity_type} #${l.entity_id || 'N/A'}\n` +
              `IP: ${l.ip_address} | User Agent: ${l.user_agent || 'N/A'}\n\n` +
              `GIÁ TRỊ CŨ (Old Values):\n` +
              JSON.stringify(l.old_values || {}, null, 2) + '\n\n' +
              `GIÁ TRỊ MỚI (New Values):\n` +
              JSON.stringify(l.new_values || {}, null, 2)
            );
          };
        });
      };

      container.querySelector('#btn-admin-filter-audit')?.addEventListener('click', async () => {
        const search = container.querySelector('#admin-audit-search').value.trim();
        const action = container.querySelector('#admin-audit-action').value;
        const params = {};
        if (search) params.search = search;
        if (action !== 'ALL') params.action = action;
        const res = await loadAudit(params);
        const tbody = container.querySelector('#admin-audit-tbody');
        if (tbody) {
          tbody.innerHTML = renderLogRows(res.logs || []);
          attachAuditEvents(res.logs || []);
        }
      });

      attachAuditEvents(logs);
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Lỗi: ${escapeHtml(err.message)}</div>`;
    }
  }
}
