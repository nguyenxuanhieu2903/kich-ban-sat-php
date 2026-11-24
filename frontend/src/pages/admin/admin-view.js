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
        <button id="tab-btn-users" class="admin-tab-btn ${this.currentSubTab === 'users' ? 'active' : ''}">
          👥 Quản lý Người dùng & Vai trò
        </button>
        <button id="tab-btn-rooms" class="admin-tab-btn ${this.currentSubTab === 'rooms' ? 'active' : ''}">
          🎲 Quản lý & Hủy Phòng Chơi (Quy Tắc 24h)
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

    this.container.querySelector('#tab-btn-users').onclick = () => this.switchSubTab('users');
    this.container.querySelector('#tab-btn-rooms').onclick = () => this.switchSubTab('rooms');
    this.container.querySelector('#tab-btn-refunds').onclick = () => this.switchSubTab('refunds');
    this.container.querySelector('#tab-btn-games').onclick = () => this.switchSubTab('games');
    this.container.querySelector('#tab-btn-promotions').onclick = () => this.switchSubTab('promotions');
    this.container.querySelector('#tab-btn-venues').onclick = () => this.switchSubTab('venues');

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
    if (this.currentSubTab === 'users') {
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
      const res = await api.get('/game-rooms');
      this.rooms = res.data || [];

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
                ${this.rooms.map(r => `
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
}
