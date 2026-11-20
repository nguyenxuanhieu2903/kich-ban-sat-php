import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';

export class AuthModal {
  constructor(onSuccess = () => {}) {
    this.onSuccess = onSuccess;
    this.modal = null;
    this.isRegisterMode = false;
  }

  render() {
    if (this.modal) return this.modal;

    this.modal = document.createElement('dialog');
    this.modal.id = 'auth-modal';
    this.modal.className = 'custom-modal';
    this.modal.style.cssText = `
      background: var(--color-bg-card, #1e293b);
      color: var(--color-text-main, #f8fafc);
      border: 1px solid var(--color-border, #334155);
      border-radius: 12px;
      padding: 24px;
      width: 100%;
      max-width: 440px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    `;

    this.updateContent();
    document.body.appendChild(this.modal);

    this.modal.addEventListener('click', (e) => {
      const rect = this.modal.getBoundingClientRect();
      const inDialog = (
        rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
        rect.left <= e.clientX && e.clientX <= rect.left + rect.width
      );
      if (!inDialog) {
        this.close();
      }
    });

    return this.modal;
  }

  updateContent() {
    this.modal.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">
          ${this.isRegisterMode ? 'Đăng ký tài khoản' : 'Đăng nhập hệ thống'}
        </h2>
        <button id="auth-close-btn" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer;">&times;</button>
      </div>

      <!-- Quick Demo Switcher -->
      <div style="background: rgba(59, 130, 246, 0.1); border: 1px dashed #3b82f6; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
        <div style="font-size: 0.8rem; font-weight: 600; color: #60a5fa; margin-bottom: 8px;">
          🚀 TÀI KHOẢN MẪU DEMO (CLICK ĐỂ ĐĂNG NHẬP NHANH):
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
          <button type="button" class="btn-demo" data-email="admin@kichbansat.vn" style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-error); color: var(--color-text-main); padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">👑 Admin</button>
          <button type="button" class="btn-demo" data-email="dm.nam@kichbansat.vn" style="background: rgba(245, 158, 11, 0.15); border: 1px solid var(--color-warning); color: var(--color-text-main); padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">🎭 DM Hoàng Nam</button>
          <button type="button" class="btn-demo" data-email="player.hieu@kichbansat.vn" style="background: rgba(59, 130, 246, 0.15); border: 1px solid var(--color-primary); color: var(--color-text-main); padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">🎲 Player Hiếu</button>
          <button type="button" class="btn-demo" data-email="minhanh@gmail.com" style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--color-success); color: var(--color-text-main); padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">🎲 Player Minh Anh</button>
        </div>
      </div>

      <form id="auth-form" style="display: flex; flex-direction: column; gap: 14px;">
        ${this.isRegisterMode ? `
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Họ và tên</label>
            <input type="text" name="full_name" required placeholder="Nguyễn Văn A" class="form-input" style="width: 100%; box-sizing: border-box;">
          </div>
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Số điện thoại</label>
            <input type="tel" name="phone" placeholder="0912345678" class="form-input" style="width: 100%; box-sizing: border-box;">
          </div>
        ` : ''}

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Email</label>
          <input type="email" name="email" required placeholder="email@example.com" class="form-input" style="width: 100%; box-sizing: border-box;">
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Mật khẩu</label>
          <input type="password" name="password" required minlength="6" placeholder="Tối thiểu 6 ký tự" class="form-input" style="width: 100%; box-sizing: border-box;">
        </div>

        <button type="submit" id="auth-submit-btn" class="btn btn-primary" style="margin-top: 10px; width: 100%;">
          ${this.isRegisterMode ? 'Đăng ký ngay' : 'Đăng nhập'}
        </button>
      </form>

      <div style="margin-top: 16px; text-align: center; font-size: 0.85rem; color: #94a3b8;">
        ${this.isRegisterMode
          ? 'Đã có tài khoản? <a href="#" id="toggle-auth-mode" style="color: #3b82f6; text-decoration: none;">Đăng nhập</a>'
          : 'Chưa có tài khoản? <a href="#" id="toggle-auth-mode" style="color: #3b82f6; text-decoration: none;">Đăng ký tài khoản mới</a>'}
      </div>
    `;

    // Event listeners
    this.modal.querySelector('#auth-close-btn').onclick = () => this.close();
    this.modal.querySelector('#toggle-auth-mode').onclick = (e) => {
      e.preventDefault();
      this.isRegisterMode = !this.isRegisterMode;
      this.updateContent();
    };

    // Quick demo buttons
    this.modal.querySelectorAll('.btn-demo').forEach(btn => {
      btn.onclick = async () => {
        const email = btn.dataset.email;
        btn.disabled = true;
        btn.textContent = 'Đang vào...';
        try {
          await authState.login(email, 'password123');
          toast.success(`Đăng nhập thành công với vai trò: ${authState.user?.role || 'User'}`);
          this.close();
          this.onSuccess(authState.user);
        } catch (err) {
          toast.error(err.message);
        } finally {
          btn.disabled = false;
        }
      };
    });

    // Form submit
    const form = this.modal.querySelector('#auth-form');
    form.onsubmit = async (e) => {
      e.preventDefault();
      const submitBtn = this.modal.querySelector('#auth-submit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Đang xử lý...';

      const formData = new FormData(form);
      const data = Object.fromEntries(formData.entries());

      try {
        if (this.isRegisterMode) {
          await authState.register(data);
          toast.success('Đăng ký tài khoản thành công!');
        } else {
          await authState.login(data.email, data.password);
          toast.success(`Xin chào ${authState.user.full_name}!`);
        }
        this.close();
        this.onSuccess(authState.user);
      } catch (err) {
        toast.error(err.message || 'Thao tác không thành công');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = this.isRegisterMode ? 'Đăng ký ngay' : 'Đăng nhập';
      }
    };
  }

  open(registerMode = false) {
    this.isRegisterMode = registerMode;
    this.render();
    this.updateContent();
    if (!this.modal.open) {
      this.modal.showModal();
    }
  }

  close() {
    if (this.modal && this.modal.open) {
      this.modal.close();
    }
  }
}
