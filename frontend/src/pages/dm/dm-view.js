import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';

export class DmView {
  constructor(container, onOpenAuthModal = () => {}) {
    this.container = container;
    this.onOpenAuthModal = onOpenAuthModal;
    this.assignments = [];
  }

  async render() {
    const user = authState.getUser();

    if (!user) {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 48px 24px; max-width: 600px; margin: 40px auto;">
          <div style="font-size: 3rem; margin-bottom: 16px;">📜</div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px;">Cổng Quản Trò (DM Portal)</h2>
          <p style="color: var(--color-text-muted); margin-bottom: 24px;">
            Vui lòng đăng nhập với tài khoản Quản Trò (DM) để quản lý các ca dẫn truyện được phân công.
          </p>
          <button id="btn-dm-login" class="btn btn-primary" style="padding: 10px 24px; font-size: 1rem;">
            Đăng nhập / Đổi tài khoản
          </button>
        </div>
      `;
      this.container.querySelector('#btn-dm-login').onclick = () => this.onOpenAuthModal();
      return;
    }

    if (user.role !== 'DM' && user.role !== 'ADMIN') {
      this.container.innerHTML = `
        <div class="card" style="text-align: center; padding: 48px 24px; max-width: 600px; margin: 40px auto; border-color: var(--color-warning);">
          <div style="font-size: 3rem; margin-bottom: 16px;">⚠️</div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: var(--color-warning);">Yêu cầu quyền Quản Trò (DM)</h2>
          <p style="color: var(--color-text-muted); margin-bottom: 16px;">
            Tài khoản hiện tại của bạn (<strong>${user.name}</strong>) có vai trò <strong>${user.role}</strong>. Cổng này chỉ dành riêng cho Quản Trò hoặc Quản Trị Viên.
          </p>
          <p style="color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 24px;">
            💡 <em>Mẹo Demo: Dùng "Chuyển nhanh tài khoản" trong bảng Đăng nhập để vào bằng tài khoản DM Hoàng Nam.</em>
          </p>
          <button id="btn-dm-switch" class="btn btn-primary" style="padding: 10px 24px;">
            Chuyển sang tài khoản DM (1-click)
          </button>
        </div>
      `;
      this.container.querySelector('#btn-dm-switch').onclick = () => this.onOpenAuthModal();
      return;
    }

    this.container.innerHTML = `
      <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0;">Cổng Quản Trò — Phân Công Ca Diễn</h2>
          <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">
            Quản lý lịch trình, xác nhận hoặc từ chối ca dẫn kịch bản sát được điều phối
          </p>
        </div>
        <div>
          <button id="btn-dm-refresh" class="btn" style="background: var(--color-bg-card); border: 1px solid var(--color-border); color: var(--color-text-main); display: flex; align-items: center; gap: 6px;">
            <span>🔄</span> Tải lại ca làm việc
          </button>
        </div>
      </div>

      <div id="dm-assignments-list">
        <div style="text-align: center; padding: 40px; color: var(--color-text-muted);">Đang tải lịch phân công...</div>
      </div>

      <!-- Native Dialog: Modal từ chối ca kèm lý do -->
      <dialog id="dm-reject-dialog" style="padding: 24px; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main); max-width: 500px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
          <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600; color: var(--color-error);">Từ Chối Ca Quản Trò</h3>
          <button id="btn-close-reject-dialog" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>
        <form id="dm-reject-form" method="dialog">
          <input type="hidden" id="dm-reject-assignment-id" />
          <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">
              Lý do từ chối ca làm việc <span style="color: var(--color-error);">* (Bắt buộc)</span>
            </label>
            <textarea id="dm-reject-reason" required rows="3" class="form-input" style="width: 100%; box-sizing: border-box; resize: vertical;" placeholder="Nhập lý do chi tiết (VD: Bận việc cá nhân đột xuất, trùng lịch học, sức khỏe không đảm bảo...)"></textarea>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" id="btn-cancel-reject" class="btn" style="background: transparent; border: 1px solid var(--color-border); color: var(--color-text-muted);">
              Hủy bỏ
            </button>
            <button type="submit" class="btn" style="background: var(--color-error); color: white; border: none;">
              Xác nhận Từ Chối
            </button>
          </div>
        </form>
      </dialog>
    `;

    this.container.querySelector('#btn-dm-refresh').onclick = () => this.fetchAssignments();
    this.setupRejectDialog();
    await this.fetchAssignments();
  }

  setupRejectDialog() {
    const dialog = this.container.querySelector('#dm-reject-dialog');
    const closeBtn = this.container.querySelector('#btn-close-reject-dialog');
    const cancelBtn = this.container.querySelector('#btn-cancel-reject');
    const form = this.container.querySelector('#dm-reject-form');

    const closeDialog = () => {
      dialog.close();
      form.reset();
    };

    closeBtn.onclick = closeDialog;
    cancelBtn.onclick = closeDialog;

    form.onsubmit = async (e) => {
      e.preventDefault();
      const assignmentId = this.container.querySelector('#dm-reject-assignment-id').value;
      const reason = this.container.querySelector('#dm-reject-reason').value.trim();

      if (!reason) {
        toast.error('Vui lòng nhập lý do từ chối ca!');
        return;
      }

      try {
        const res = await api.post(`/dm/assignments/${assignmentId}/reject`, { rejection_reason: reason });
        toast.success(res.message || 'Đã từ chối ca thành công!');
        closeDialog();
        this.fetchAssignments();
      } catch (err) {
        toast.error(err.message || 'Không thể từ chối ca');
      }
    };
  }

  openRejectModal(assignmentId) {
    const dialog = this.container.querySelector('#dm-reject-dialog');
    this.container.querySelector('#dm-reject-assignment-id').value = assignmentId;
    this.container.querySelector('#dm-reject-reason').value = '';
    dialog.showModal();
  }

  async fetchAssignments() {
    const listEl = this.container.querySelector('#dm-assignments-list');
    listEl.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--color-text-muted);">Đang tải lịch phân công...</div>`;

    try {
      const res = await api.get('/dm/assignments');
      this.assignments = res.data || [];

      if (this.assignments.length === 0) {
        listEl.innerHTML = `
          <div class="card" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 12px;">☕</div>
            <h4 style="margin: 0 0 6px 0; color: var(--color-text-main);">Hiện tại không có ca phân công nào</h4>
            <p style="margin: 0; font-size: 0.85rem;">Khi có phòng chơi chốt lịch và được ghép Quản trò, phân công sẽ hiển thị ở đây.</p>
          </div>
        `;
        return;
      }

      listEl.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 16px;">
          ${this.assignments.map(a => this.renderAssignmentCard(a)).join('')}
        </div>
      `;

      // Gắn sự kiện nút Accept và Reject
      this.assignments.forEach(a => {
        const acceptBtn = listEl.querySelector(`#btn-accept-${a.id}`);
        if (acceptBtn) {
          acceptBtn.onclick = () => this.handleAccept(a.id);
        }

        const rejectBtn = listEl.querySelector(`#btn-reject-${a.id}`);
        if (rejectBtn) {
          rejectBtn.onclick = () => this.openRejectModal(a.id);
        }
      });
    } catch (err) {
      listEl.innerHTML = `
        <div class="card" style="text-align: center; padding: 30px; color: var(--color-error); border-color: var(--color-error);">
          <p>Lỗi khi tải danh sách phân công: ${err.message}</p>
          <button id="btn-retry-dm" class="btn btn-primary" style="margin-top: 10px;">Thử lại</button>
        </div>
      `;
      const retryBtn = listEl.querySelector('#btn-retry-dm');
      if (retryBtn) retryBtn.onclick = () => this.fetchAssignments();
    }
  }

  renderAssignmentCard(a) {
    let statusBadge = '';
    if (a.status === 'PENDING') {
      statusBadge = `<span class="badge" style="background: rgba(245, 158, 11, 0.2); color: var(--color-warning); border: 1px solid var(--color-warning);">Đang chờ phản hồi (PENDING)</span>`;
    } else if (a.status === 'ACCEPTED') {
      statusBadge = `<span class="badge" style="background: var(--color-success-bg); color: var(--color-success); border: 1px solid var(--color-success);">Đã nhận ca (ACCEPTED)</span>`;
    } else if (a.status === 'REJECTED') {
      statusBadge = `<span class="badge" style="background: var(--color-error-bg); color: var(--color-error); border: 1px solid var(--color-error);">Đã từ chối (REJECTED)</span>`;
    } else {
      statusBadge = `<span class="badge">${a.status}</span>`;
    }

    const sessionStart = a.session_start_at ? new Date(a.session_start_at).toLocaleString('vi-VN') : 'Chưa xếp giờ';
    const sessionEnd = a.session_end_at ? new Date(a.session_end_at).toLocaleString('vi-VN') : 'Chưa xếp giờ';

    return `
      <div class="card" style="border-left: 4px solid ${a.status === 'PENDING' ? 'var(--color-warning)' : (a.status === 'ACCEPTED' ? 'var(--color-success)' : 'var(--color-border)')};">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
          <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
              <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700;">
                🎭 ${a.game_title || 'Kịch bản sát'}
              </h3>
              ${statusBadge}
            </div>
            <div style="font-size: 0.85rem; color: var(--color-text-muted);">
              Mã phòng: <strong style="color: var(--color-text-main);">${a.game_room_code || 'ROOM-#' + a.game_room_id}</strong> &bull; Phân công lúc: ${new Date(a.assigned_at || a.created_at).toLocaleString('vi-VN')}
            </div>
          </div>

          <div style="display: flex; gap: 8px;">
            ${a.status === 'PENDING' ? `
              <button id="btn-accept-${a.id}" class="btn" style="background: var(--color-success); color: white; border: none; padding: 6px 14px; font-size: 0.85rem;">
                ✓ Chấp nhận ca
              </button>
              <button id="btn-reject-${a.id}" class="btn" style="background: var(--color-error); color: white; border: none; padding: 6px 14px; font-size: 0.85rem;">
                ✕ Từ chối ca
              </button>
            ` : ''}
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; background: rgba(15, 23, 42, 0.5); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
          <div>
            <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Thời gian bắt đầu</div>
            <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-primary); margin-top: 2px;">
              ⏰ ${sessionStart}
            </div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Dự kiến kết thúc</div>
            <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-text-main); margin-top: 2px;">
              🏁 ${sessionEnd}
            </div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; font-weight: 600;">Địa điểm & Phòng</div>
            <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-text-main); margin-top: 2px;">
              📍 ${a.venue_name || 'Chi nhánh trung tâm'} — Phòng ${a.room_name || 'Phòng Diễn'}
            </div>
          </div>
        </div>

        ${a.rejection_reason ? `
          <div style="margin-top: 12px; padding: 10px; background: var(--color-error-bg); border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--color-error);">
            <strong>Lý do từ chối:</strong> ${a.rejection_reason}
          </div>
        ` : ''}
      </div>
    `;
  }

  async handleAccept(assignmentId) {
    if (!confirm('Bạn có chắc chắn muốn CHẤP NHẬN ca dẫn truyện này không?')) return;

    try {
      const res = await api.post(`/dm/assignments/${assignmentId}/accept`);
      toast.success(res.message || 'Đã chấp nhận ca thành công!');
      this.fetchAssignments();
    } catch (err) {
      toast.error(err.message || 'Không thể chấp nhận ca');
    }
  }
}
