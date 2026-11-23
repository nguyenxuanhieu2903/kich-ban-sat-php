import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';

export class AdminView {
  constructor(container, onOpenAuthModal = () => {}) {
    this.container = container;
    this.onOpenAuthModal = onOpenAuthModal;
    this.currentSubTab = 'users'; // 'users' | 'rooms' | 'refunds' | 'physical-rooms'
    this.users = [];
    this.rooms = [];
    this.refunds = [];
    this.physicalRooms = [];
  }

  async render() {
    const user = authState.getUser();

    if (!user) {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 48px 24px; max-width: 600px; margin: 40px auto;">
          <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px;">Cong Quan Tri He Thong (Admin Portal)</h2>
          <p style="color: var(--color-text-muted); margin-bottom: 24px;">
            Vui long dang nhap voi tai khoan Quan Tri Vien (ADMIN) de truy cap chuc nang nay.
          </p>
          <button id="btn-admin-login" class="btn btn-primary" style="padding: 10px 24px;">
            Dang nhap Quan Tri Vien
          </button>
        </div>
      `;
      this.container.querySelector('#btn-admin-login').onclick = () => this.onOpenAuthModal();
      return;
    }

    if (user.role !== 'ADMIN') {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 48px 24px; max-width: 600px; margin: 40px auto; border-color: var(--color-error);">
          <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: var(--color-error);">Khong Du Quyen Truy Cap (403 Forbidden)</h2>
          <p style="color: var(--color-text-muted); margin-bottom: 16px;">
            Tai khoan hien tai cua ban (<strong>${user.name}</strong>) co vai tro <strong>${user.role}</strong>. Chi co <strong>ADMIN</strong> moi co quyen quan tri nguoi dung, huy phong va xet duyet hoan tien.
          </p>
          <button id="btn-admin-switch" class="btn btn-primary" style="padding: 10px 24px;">
            Chuyen sang tai khoan Admin (1-click)
          </button>
        </div>
      `;
      this.container.querySelector('#btn-admin-switch').onclick = () => this.onOpenAuthModal();
      return;
    }

    this.container.innerHTML = `
      <div style="margin-bottom: 24px;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0;">Bang Dieu Khien Quan Tri He Thong</h2>
        <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">
          Quan ly nguoi dung, phan quyen RBAC, can thiep huy phong theo Quy Tac Vang 24h, xet duyet hoan tien va bao tri phong vat ly
        </p>
      </div>

      <!-- Admin Sub Tabs -->
      <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px; flex-wrap: wrap;">
        <button id="tab-btn-users" class="admin-tab-btn ${this.currentSubTab === 'users' ? 'active' : ''}">
          Nguoi Dung & Vai Tro
        </button>
        <button id="tab-btn-rooms" class="admin-tab-btn ${this.currentSubTab === 'rooms' ? 'active' : ''}">
          Quan Ly & Huy Phong Choi (24h)
        </button>
        <button id="tab-btn-refunds" class="admin-tab-btn ${this.currentSubTab === 'refunds' ? 'active' : ''}">
          Xet Duyet & Chi Tra Hoan Tien
        </button>
        <button id="tab-btn-physical" class="admin-tab-btn ${this.currentSubTab === 'physical-rooms' ? 'active' : ''}">
          Phong Vat Ly & Bao Tri Ky Thuat
        </button>
      </div>

      <div id="admin-subtab-content">
        <!-- Rendered based on sub-tab -->
      </div>

      <!-- Dialog Huy Phong -->
      <dialog id="admin-cancel-room-dialog" style="padding: 24px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main); max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
          <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600; color: var(--color-error);">Huy Phong Choi Khan Cap (Admin)</h3>
          <button id="btn-close-cancel-room" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>
        <form id="admin-cancel-room-form">
          <input type="hidden" id="cancel-room-id" />
          <div style="background: var(--color-warning-bg); border: 1px solid var(--color-warning); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.85rem; color: var(--color-warning);">
            <strong>Luu y Quy Tac Vang 24 Gio:</strong><br/>
            - Neu huy <strong>>= 24 gio</strong> truoc gio choi: Toan bo nguoi choi da coc duoc tu dong duyet hoan tien (APPROVED).<br/>
            - Neu huy <strong>< 24 gio</strong> truoc gio choi: Tien coc khong duoc hoan lai (REJECTED) do vi pham chinh sach huy muon.
          </div>
          <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
              Ly do huy phong <span style="color: var(--color-error);">* (Bat buoc)</span>
            </label>
            <textarea id="cancel-room-reason" required rows="3" class="form-input" style="width: 100%; box-sizing: border-box; resize: vertical;" placeholder="Nhap ly do chi tiet (VD: Chi nhanh mat dien, su co dot xuat...)"></textarea>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="btn-cancel-cancel-room" class="btn" style="background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted);">
              Dong
            </button>
            <button type="submit" class="btn" style="background: var(--color-error); color: white; border: none;">
              Tien hanh Huy Phong
            </button>
          </div>
        </form>
      </dialog>
    `;

    this.container.querySelector('#tab-btn-users').onclick = () => this.switchSubTab('users');
    this.container.querySelector('#tab-btn-rooms').onclick = () => this.switchSubTab('rooms');
    this.container.querySelector('#tab-btn-refunds').onclick = () => this.switchSubTab('refunds');
    this.container.querySelector('#tab-btn-physical').onclick = () => this.switchSubTab('physical-rooms');

    this.setupCancelRoomDialog();
    await this.renderSubTabContent();
  }

  switchSubTab(tab) {
    this.currentSubTab = tab;
    this.container.querySelectorAll('.admin-tab-btn').forEach(b => b.classList.remove('active'));
    const btn = this.container.querySelector(`#tab-btn-${tab === 'physical-rooms' ? 'physical' : tab}`);
    if (btn) btn.classList.add('active');
    this.renderSubTabContent();
  }

  async renderSubTabContent() {
    const content = this.container.querySelector('#admin-subtab-content');
    if (!content) return;
    if (this.currentSubTab === 'users') {
      await this.renderUsersTab(content);
    } else if (this.currentSubTab === 'rooms') {
      await this.renderRoomsTab(content);
    } else if (this.currentSubTab === 'refunds') {
      await this.renderRefundsTab(content);
    } else if (this.currentSubTab === 'physical-rooms') {
      await this.renderPhysicalRoomsTab(content);
    }
  }

  // --- SUBTAB 1: USERS & ROLES ---
  async renderUsersTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Dang tai danh sach nguoi dung...</div>`;

    try {
      const res = await api.get('/admin/users');
      this.users = res.data || [];

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Danh Sach Nguoi Dung (${this.users.length})</h3>
            <span style="font-size: 0.8rem; color: var(--color-text-muted);">Chong tu giang chuc & tu khoa tai khoan Admin</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">ID</th>
                  <th style="padding: 12px 16px;">Ho ten</th>
                  <th style="padding: 12px 16px;">Email</th>
                  <th style="padding: 12px 16px;">Vai tro (RBAC)</th>
                  <th style="padding: 12px 16px;">Trang thai</th>
                  <th style="padding: 12px 16px; text-align: right;">Hanh dong</th>
                </tr>
              </thead>
              <tbody>
                ${this.users.map(u => this.renderUserRow(u)).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

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
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Loi: ${err.message}</div>`;
    }
  }

  renderUserRow(u) {
    const isCurrentUser = authState.getUser()?.id === u.id;
    return `
      <tr style="border-bottom: 1px solid var(--color-border);">
        <td style="padding: 12px 16px; color: var(--color-text-muted); font-mono;">#${u.id}</td>
        <td style="padding: 12px 16px; font-weight: 600;">
          ${u.name} ${isCurrentUser ? '<span class="badge" style="background: var(--color-primary); color: white; font-size: 0.7rem;">Ban</span>' : ''}
        </td>
        <td style="padding: 12px 16px; color: var(--color-text-muted);">${u.email}</td>
        <td style="padding: 12px 16px;">
          <select id="select-role-${u.id}" class="form-input" style="padding: 4px 8px; font-size: 0.8rem;" ${isCurrentUser ? 'disabled title="Khong the tu doi quyen cua ban than"' : ''}>
            <option value="PLAYER" ${u.role === 'PLAYER' ? 'selected' : ''}>PLAYER</option>
            <option value="DM" ${u.role === 'DM' ? 'selected' : ''}>DM (Quan tro)</option>
            <option value="ADMIN" ${u.role === 'ADMIN' ? 'selected' : ''}>ADMIN (Quan tri)</option>
          </select>
        </td>
        <td style="padding: 12px 16px;">
          ${u.is_active ? 
            `<span class="badge" style="background: var(--color-success-bg); color: var(--color-success);">Hoat dong</span>` : 
            `<span class="badge" style="background: var(--color-error-bg); color: var(--color-error);">Bi khoa</span>`}
        </td>
        <td style="padding: 12px 16px; text-align: right;">
          <button id="btn-toggle-active-${u.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: ${u.is_active ? 'var(--color-error-bg)' : 'var(--color-success-bg)'}; color: ${u.is_active ? 'var(--color-error)' : 'var(--color-success)'}; border: none;" ${isCurrentUser ? 'disabled title="Khong the tu khoa tai khoan cua ban than"' : ''}>
            ${u.is_active ? 'Khoa tai khoan' : 'Mo khoa'}
          </button>
        </td>
      </tr>
    `;
  }

  async handleUpdateRole(userId, newRole) {
    try {
      const res = await api.put(`/admin/users/${userId}/role`, { role: newRole });
      toast.success(res.message || 'Cap nhat vai tro thanh cong!');
      this.renderUsersTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Khong the cap nhat vai tro');
      this.renderUsersTab(this.container.querySelector('#admin-subtab-content'));
    }
  }

  async handleToggleActive(userId, currentStatus) {
    const actionName = currentStatus ? 'KHOA' : 'MO KHOA';
    if (!confirm(`Ban co chac chan muon ${actionName} tai khoan nay?`)) return;

    try {
      const res = await api.patch(`/admin/users/${userId}`, { is_active: !currentStatus });
      toast.success(res.message || `Da ${actionName.toLowerCase()} tai khoan thanh cong!`);
      this.renderUsersTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || `Khong the ${actionName.toLowerCase()} tai khoan`);
    }
  }

  // --- SUBTAB 2: ROOM MANAGEMENT & 24H CANCELLATION ---
  async renderRoomsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Dang tai danh sach phong choi...</div>`;

    try {
      const res = await api.get('/rooms');
      this.rooms = res.data || [];

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Quan Ly Phong Choi (${this.rooms.length})</h3>
            <span style="font-size: 0.8rem; color: var(--color-text-muted);">Quy tac 24h: Tu dong duyet hoan tien neu huy >= 24h</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">Ma phong</th>
                  <th style="padding: 12px 16px;">Tieu de & Kich ban</th>
                  <th style="padding: 12px 16px;">Chu phong</th>
                  <th style="padding: 12px 16px;">Trang thai</th>
                  <th style="padding: 12px 16px;">Lich hen</th>
                  <th style="padding: 12px 16px; text-align: right;">Hanh dong</th>
                </tr>
              </thead>
              <tbody>
                ${this.rooms.map(r => `
                  <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px 16px; font-mono; color: var(--color-primary);">${r.room_code || '#' + r.id}</td>
                    <td style="padding: 12px 16px; font-weight: 600;">${r.title} — ${r.game_name || 'Kich ban'}</td>
                    <td style="padding: 12px 16px; color: var(--color-text-muted);">${r.host_name || 'N/A'}</td>
                    <td style="padding: 12px 16px;"><span class="badge">${r.status}</span></td>
                    <td style="padding: 12px 16px; font-size: 0.85rem; color: var(--color-text-muted);">
                      ${r.session_start_at ? new Date(r.session_start_at).toLocaleString('vi-VN') : 'Chua chot'}
                    </td>
                    <td style="padding: 12px 16px; text-align: right;">
                      ${r.status !== 'CANCELLED' && r.status !== 'FINISHED' ? `
                        <button id="btn-admin-cancel-room-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-error); color: white; border: none;">
                          Huy phong
                        </button>
                      ` : `<span style="color: var(--color-text-muted); font-size: 0.8rem;">Da dong</span>`}
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      this.rooms.forEach(r => {
        const cancelBtn = container.querySelector(`#btn-admin-cancel-room-${r.id}`);
        if (cancelBtn) {
          cancelBtn.onclick = () => this.openCancelRoomModal(r.id);
        }
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Loi: ${err.message}</div>`;
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
        toast.error('Vui long nhap ly do huy phong!');
        return;
      }

      try {
        const res = await api.post(`/admin/rooms/${roomId}/cancel`, { cancellation_reason: reason });
        toast.success(res.message || 'Huy phong thanh cong!');
        closeDialog();
        this.renderRoomsTab(this.container.querySelector('#admin-subtab-content'));
      } catch (err) {
        toast.error(err.message || 'Khong the huy phong');
      }
    };
  }

  openCancelRoomModal(roomId) {
    const dialog = this.container.querySelector('#admin-cancel-room-dialog');
    this.container.querySelector('#cancel-room-id').value = roomId;
    this.container.querySelector('#cancel-room-reason').value = '';
    dialog.showModal();
  }

  // --- SUBTAB 3: REFUND MANAGEMENT & SETTLEMENT ---
  async renderRefundsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Dang tai danh sach hoan tien...</div>`;

    try {
      const res = await api.get('/admin/refunds');
      this.refunds = res.data || [];

      if (this.refunds.length === 0) {
        container.innerHTML = `
          <div class="card" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
            <h4 style="margin: 0 0 6px 0; color: var(--color-text-main);">Khong co yeu cau hoan tien nao can xu ly</h4>
            <p style="margin: 0; font-size: 0.85rem;">Moi yeu cau hoan coc tu nguoi choi rut lui hoac phong bi huy se xuat hien o day.</p>
          </div>
        `;
        return;
      }

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Danh Sach Yeu Cau Hoan Tien (${this.refunds.length})</h3>
            <span style="font-size: 0.8rem; color: var(--color-text-muted);">Coc 50.000 VND / Nguoi</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">Ma Refund</th>
                  <th style="padding: 12px 16px;">Nguoi choi</th>
                  <th style="padding: 12px 16px;">So tien</th>
                  <th style="padding: 12px 16px;">Trang thai</th>
                  <th style="padding: 12px 16px;">Ly do</th>
                  <th style="padding: 12px 16px; text-align: right;">Hanh dong</th>
                </tr>
              </thead>
              <tbody>
                ${this.refunds.map(r => `
                  <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px 16px; font-mono; color: var(--color-primary);">#REF-${r.id}</td>
                    <td style="padding: 12px 16px; font-weight: 600;">${r.user_name || 'Nguoi choi #' + (r.user_id || '')}</td>
                    <td style="padding: 12px 16px; font-weight: 700; color: var(--color-warning);">${Number(r.amount || 50000).toLocaleString('vi-VN')} d</td>
                    <td style="padding: 12px 16px;">
                      <span class="badge" style="background: ${r.status === 'COMPLETED' ? 'var(--color-primary-bg, rgba(14, 165, 233, 0.2))' : (r.status === 'APPROVED' ? 'var(--color-success-bg)' : (r.status === 'REJECTED' ? 'var(--color-error-bg)' : 'rgba(245, 158, 11, 0.2)'))}; color: ${r.status === 'COMPLETED' ? 'var(--color-primary, #0ea5e9)' : (r.status === 'APPROVED' ? 'var(--color-success)' : (r.status === 'REJECTED' ? 'var(--color-error)' : 'var(--color-warning)'))};">
                        ${r.status}
                      </span>
                    </td>
                    <td style="padding: 12px 16px; font-size: 0.85rem; color: var(--color-text-muted); max-width: 200px;">
                      ${r.reason || 'N/A'}
                    </td>
                    <td style="padding: 12px 16px; text-align: right;">
                      ${r.status === 'PENDING' ? `
                        <button id="btn-approve-refund-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-success); color: white; border: none; margin-right: 6px;">
                          Duyet
                        </button>
                        <button id="btn-reject-refund-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-error); color: white; border: none;">
                          Tu choi
                        </button>
                      ` : (r.status === 'APPROVED' ? `
                        <button id="btn-complete-refund-${r.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: var(--color-primary, #0ea5e9); color: white; border: none;">
                          Chi tien (Hoan tat)
                        </button>
                      ` : (r.status === 'COMPLETED' ? `
                        <span style="font-size: 0.8rem; color: var(--color-success); font-weight: 600;">Da chi tra</span>
                      ` : `
                        <span style="font-size: 0.8rem; color: var(--color-error); font-weight: 600;">Da tu choi</span>
                      `))}
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

        const completeBtn = container.querySelector(`#btn-complete-refund-${r.id}`);
        if (completeBtn) {
          completeBtn.onclick = () => this.handleCompleteRefund(r.id);
        }
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Loi: ${err.message}</div>`;
    }
  }

  async handleApproveRefund(refundId) {
    if (!confirm('Ban co chac chan muon DUYET hoan tien cho yeu cau nay?')) return;
    try {
      const res = await api.post(`/admin/refunds/${refundId}/approve`);
      toast.success(res.message || 'Da duyet hoan tien thanh cong!');
      this.renderRefundsTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Khong the duyet hoan tien');
    }
  }

  async handleRejectRefund(refundId) {
    const reason = prompt('Nhap ly do tu choi hoan tien:');
    if (!reason) return;
    try {
      const res = await api.post(`/admin/refunds/${refundId}/reject`, { reason });
      toast.success(res.message || 'Da tu choi hoan tien!');
      this.renderRefundsTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Khong the tu choi');
    }
  }

  async handleCompleteRefund(refundId) {
    const bankRef = prompt('Nhap ma tham chieu giao dich ngan hang (Uy nhiem chi):', 'UNC_' + Date.now());
    if (!bankRef) return;
    try {
      const res = await api.post(`/admin/refunds/${refundId}/complete`, { bank_reference_code: bankRef });
      toast.success(res.message || 'Da hoan tat chi tra tien coc thanh cong!');
      this.renderRefundsTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Khong the cap nhat hoan tat hoan tien');
    }
  }

  // --- SUBTAB 4: PHYSICAL ROOMS & MAINTENANCE ---
  async renderPhysicalRoomsTab(container) {
    container.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Dang tai danh sach phong vat ly...</div>`;

    try {
      const res = await api.get('/admin/physical-rooms');
      this.physicalRooms = res.data || [];

      container.innerHTML = `
        <div class="card" style="padding: 0; overflow: hidden;">
          <div style="padding: 16px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div>
              <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">Quan Ly Phong Vat Ly & Bao Tri (${this.physicalRooms.length})</h3>
              <p style="margin: 2px 0 0 0; font-size: 0.8rem; color: var(--color-text-muted);">Tu dong dieu chuyen ca choi (Dynamic Rerouting) sang phong trong tuong duong khi bat bao tri</p>
            </div>
            <span class="badge" style="background: rgba(14, 165, 233, 0.2); color: #0ea5e9;">BUFFER 30m</span>
          </div>
          <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
              <thead>
                <tr style="background: rgba(15, 23, 42, 0.7); text-align: left; border-bottom: 1px solid var(--color-border);">
                  <th style="padding: 12px 16px;">ID</th>
                  <th style="padding: 12px 16px;">Ten phong</th>
                  <th style="padding: 12px 16px;">Chi nhanh</th>
                  <th style="padding: 12px 16px;">Suc chua</th>
                  <th style="padding: 12px 16px;">Chu de (Theme)</th>
                  <th style="padding: 12px 16px;">Trang thai</th>
                  <th style="padding: 12px 16px;">Ca choi ke tiep</th>
                  <th style="padding: 12px 16px; text-align: right;">Hanh dong</th>
                </tr>
              </thead>
              <tbody>
                ${this.physicalRooms.map(pr => `
                  <tr style="border-bottom: 1px solid var(--color-border);">
                    <td style="padding: 12px 16px; font-mono; color: var(--color-primary);">#PR-${pr.id}</td>
                    <td style="padding: 12px 16px; font-weight: 600;">${pr.name}</td>
                    <td style="padding: 12px 16px; color: var(--color-text-muted);">${pr.venue_name || 'Chi nhanh #' + pr.venue_id}</td>
                    <td style="padding: 12px 16px;">${pr.capacity} nguoi</td>
                    <td style="padding: 12px 16px;"><span class="badge" style="font-size: 0.75rem;">${pr.theme_code || 'GENERAL'}</span></td>
                    <td style="padding: 12px 16px;">
                      ${pr.is_maintenance ? `
                        <span class="badge" style="background: var(--color-error-bg); color: var(--color-error);">Bao tri</span>
                      ` : `
                        <span class="badge" style="background: var(--color-success-bg); color: var(--color-success);">San sang</span>
                      `}
                    </td>
                    <td style="padding: 12px 16px; font-size: 0.8rem; color: var(--color-text-muted); max-width: 220px;">
                      ${pr.upcoming_session_info ? pr.upcoming_session_info.split(':').slice(2).join(' ') : 'Khong co ca sap toi'}
                    </td>
                    <td style="padding: 12px 16px; text-align: right;">
                      <button id="btn-toggle-maint-${pr.id}" class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: ${pr.is_maintenance ? 'var(--color-success)' : 'var(--color-error)'}; color: white; border: none;">
                        ${pr.is_maintenance ? 'Tat bao tri' : 'Bat bao tri'}
                      </button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      this.physicalRooms.forEach(pr => {
        const toggleBtn = container.querySelector(`#btn-toggle-maint-${pr.id}`);
        if (toggleBtn) {
          toggleBtn.onclick = () => this.handleToggleMaintenance(pr.id, pr.is_maintenance, pr.name);
        }
      });
    } catch (err) {
      container.innerHTML = `<div class="card" style="color: var(--color-error); padding: 20px;">Loi: ${err.message}</div>`;
    }
  }

  async handleToggleMaintenance(roomId, currentIsMaintenance, roomName) {
    const action = currentIsMaintenance ? 'tat che do bao tri' : 'kich hoat che do bao tri va tu dong dieu chuyen ca choi';
    if (!confirm(`Ban co chac chan muon ${action} cho phong "${roomName}"?`)) return;

    let reason = 'Bao tri trang thiet bi';
    if (!currentIsMaintenance) {
      const input = prompt('Nhap ly do bao tri (VD: Hong den chieu, hu dao cu...):', 'Bao tri dao cu ky thuat');
      if (input) reason = input;
    }

    try {
      const res = await api.post(`/admin/physical-rooms/${roomId}/maintenance`, {
        is_maintenance: !currentIsMaintenance,
        reason: reason
      });

      let msg = res.message || 'Cap nhat trang thai bao tri thanh cong!';
      if (res.data?.rerouted_sessions && res.data.rerouted_sessions.length > 0) {
        msg += ` Da tu dong dieu chuyen ${res.data.rerouted_sessions.length} ca choi sang phong trong khac.`;
      }
      toast.success(msg);
      this.renderPhysicalRoomsTab(this.container.querySelector('#admin-subtab-content'));
    } catch (err) {
      toast.error(err.message || 'Khong the cap nhat trang thai bao tri');
    }
  }
}
