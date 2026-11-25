import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml } from '../../utils/sanitize.js';

export const isDevEnvironment = () => {
  if (typeof window === 'undefined') return true;
  if (window.__APP_CONFIG__?.enableDemoFill !== undefined) {
    return Boolean(window.__APP_CONFIG__.enableDemoFill);
  }
  const host = window.location.hostname;
  return host === 'localhost' || host === '127.0.0.1' || host === '::1' || host.endsWith('.local') || host.endsWith('.test');
};

export const getRoleConfigs = () => {
  const isDev = isDevEnvironment();
  return {
    PLAYER: {
      code: 'PLAYER',
      name: 'Người Chơi',
      shortName: 'Player',
      icon: '🎲',
      color: '#3b82f6',
      activeClass: 'role-player',
      badgeBg: 'rgba(59, 130, 246, 0.15)',
      badgeBorder: 'rgba(59, 130, 246, 0.4)',
      description: 'Khám phá kịch bản trinh thám, đặt lịch và ghép phòng cùng đồng đội.',
      demoEmail: isDev ? 'nguoichoi01@gmail.com' : null,
      demoPassword: isDev ? 'pass123456#' : null,
      demoLabel: isDev ? 'Người chơi 01 (nguoichoi01@gmail.com)' : null
    },
    DM: {
      code: 'DM',
      name: 'Quản Trò (DM)',
      shortName: 'Dungeon Master',
      icon: '📜',
      color: '#f59e0b',
      activeClass: 'role-dm',
      badgeBg: 'rgba(245, 158, 11, 0.15)',
      badgeBorder: 'rgba(245, 158, 11, 0.4)',
      description: 'Dẫn dắt các ca chơi, điều phối đạo cụ và nhận phân công từ Admin.',
      demoEmail: isDev ? 'nguyenphamyhuynh@gmail.com' : null,
      demoPassword: isDev ? 'pass123456@' : null,
      demoLabel: isDev ? 'DM Nguyễn Phạm Y Huỳnh (nguyenphamyhuynh@gmail.com)' : null
    },
    ADMIN: {
      code: 'ADMIN',
      name: 'Quản Trị Viên (Admin)',
      shortName: 'Administrator',
      icon: '🛡️',
      color: '#ef4444',
      activeClass: 'role-admin',
      badgeBg: 'rgba(239, 68, 68, 0.15)',
      badgeBorder: 'rgba(239, 68, 68, 0.4)',
      description: 'Điều hành toàn diện hệ thống, phân công DM, duyệt hủy và hoàn cọc.',
      demoEmail: isDev ? 'nguyenxuanhieu@gmail.com' : null,
      demoPassword: isDev ? 'pass123456!' : null,
      demoLabel: isDev ? 'Admin Nguyễn Xuân Hiếu (nguyenxuanhieu@gmail.com)' : null
    }
  };
};

export const ROLE_CONFIGS = getRoleConfigs();

export class AuthModal {
  /**
   * @param {Function} onSuccess Callback on successful login/register: (user, selectedRole) => void
   */
  constructor(onSuccess = () => {}) {
    this.onSuccess = onSuccess;
    this.modal = null;
    this.isRegisterMode = false;
    this.selectedRole = 'PLAYER'; // 'PLAYER' | 'DM' | 'ADMIN'
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
      width: 92vw;
      max-width: 460px;
      margin: auto;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
      box-sizing: border-box;
      max-height: 90vh;
      overflow-y: auto;
    `;

    this.updateContent();
    document.body.appendChild(this.modal);

    // Reliable backdrop click detection
    this.modal.addEventListener('click', (e) => {
      if (e.target === this.modal) {
        this.close();
      }
    });

    return this.modal;
  }

  selectRole(role) {
    const configs = getRoleConfigs();
    if (!configs[role]) return;
    this.selectedRole = role;
    this.updateContent();
  }

  updateContent() {
    if (!this.modal) return;
    const configs = getRoleConfigs();
    const roleConfig = configs[this.selectedRole] || configs.PLAYER;

    this.modal.innerHTML = `
      <!-- Header with title and close button -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
          <h2 style="font-size: 1.3rem; font-weight: 700; margin: 0 0 4px 0; color: #f8fafc;">
            ${this.isRegisterMode ? 'Đăng ký tài khoản' : 'Đăng nhập hệ thống'}
          </h2>
          <p style="margin: 0; font-size: 0.82rem; color: var(--color-text-muted);">
            Kịch Bản Sát V16 Platform Foundation
          </p>
        </div>
        <button id="auth-close-btn" style="background: none; border: none; color: #94a3b8; font-size: 1.6rem; cursor: pointer; padding: 4px 8px; line-height: 1;" aria-label="Đóng">&times;</button>
      </div>

      ${!this.isRegisterMode ? `
        <!-- Multi-Role Segmented Selector (Player / DM / Admin) -->
        <div style="margin-bottom: 14px;">
          <label style="font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 8px;">
            Chọn vai trò đăng nhập:
          </label>
          <div class="auth-role-selector" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
            <button
              type="button"
              class="role-tab-btn ${this.selectedRole === 'PLAYER' ? 'active role-player' : ''}"
              data-role="PLAYER"
              style="padding: 10px 6px; display: flex; flex-direction: column; align-items: center; gap: 4px; border-radius: 8px; border: 1px solid ${this.selectedRole === 'PLAYER' ? '#3b82f6' : 'var(--color-border)'}; background: ${this.selectedRole === 'PLAYER' ? 'rgba(59, 130, 246, 0.18)' : 'rgba(15, 23, 42, 0.6)'}; color: ${this.selectedRole === 'PLAYER' ? '#93c5fd' : 'var(--color-text-muted)'}; cursor: pointer; transition: all 0.2s;"
            >
              <span style="font-size: 1.3rem;">🎲</span>
              <strong style="font-size: 0.85rem;">Player</strong>
              <span style="font-size: 0.7rem; opacity: 0.85;">Người chơi</span>
            </button>

            <button
              type="button"
              class="role-tab-btn ${this.selectedRole === 'DM' ? 'active role-dm' : ''}"
              data-role="DM"
              style="padding: 10px 6px; display: flex; flex-direction: column; align-items: center; gap: 4px; border-radius: 8px; border: 1px solid ${this.selectedRole === 'DM' ? '#f59e0b' : 'var(--color-border)'}; background: ${this.selectedRole === 'DM' ? 'rgba(245, 158, 11, 0.18)' : 'rgba(15, 23, 42, 0.6)'}; color: ${this.selectedRole === 'DM' ? '#fbbf24' : 'var(--color-text-muted)'}; cursor: pointer; transition: all 0.2s;"
            >
              <span style="font-size: 1.3rem;">📜</span>
              <strong style="font-size: 0.85rem;">DM</strong>
              <span style="font-size: 0.7rem; opacity: 0.85;">Quản trò</span>
            </button>

            <button
              type="button"
              class="role-tab-btn ${this.selectedRole === 'ADMIN' ? 'active role-admin' : ''}"
              data-role="ADMIN"
              style="padding: 10px 6px; display: flex; flex-direction: column; align-items: center; gap: 4px; border-radius: 8px; border: 1px solid ${this.selectedRole === 'ADMIN' ? '#ef4444' : 'var(--color-border)'}; background: ${this.selectedRole === 'ADMIN' ? 'rgba(239, 68, 68, 0.18)' : 'rgba(15, 23, 42, 0.6)'}; color: ${this.selectedRole === 'ADMIN' ? '#fca5a5' : 'var(--color-text-muted)'}; cursor: pointer; transition: all 0.2s;"
            >
              <span style="font-size: 1.3rem;">🛡️</span>
              <strong style="font-size: 0.85rem;">Admin</strong>
              <span style="font-size: 0.7rem; opacity: 0.85;">Quản trị</span>
            </button>
          </div>
        </div>

        <!-- Role description & quick helper -->
        <div style="background: ${roleConfig.badgeBg}; border: 1px solid ${roleConfig.badgeBorder}; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.82rem;">
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px;">
            <strong style="color: ${roleConfig.color}; display: flex; align-items: center; gap: 6px;">
              <span>${roleConfig.icon}</span> ${roleConfig.name}
            </strong>
            ${isDevEnvironment() && roleConfig.demoEmail ? `
              <button
                type="button"
                id="btn-fill-demo"
                style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: #f8fafc; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px; cursor: pointer;"
                title="Điền tự động tài khoản mẫu (Chỉ khả dụng trong môi trường phát triển)"
              >
                ⚡ Điền mẫu
              </button>
            ` : ''}
          </div>
          <div style="color: var(--color-text-muted); line-height: 1.4;">
            ${escapeHtml(roleConfig.description)}
          </div>
        </div>
      ` : ''}

      <!-- Error alert container -->
      <div id="auth-error-alert" style="display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid var(--color-error); border-radius: 6px; padding: 10px 12px; font-size: 0.85rem; color: #fca5a5; margin-bottom: 14px;">
      </div>

      <!-- Auth Form -->
      <form id="auth-form" style="display: flex; flex-direction: column; gap: 14px;">
        ${this.isRegisterMode ? `
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Họ và tên</label>
            <input type="text" name="full_name" id="auth-name-input" required placeholder="Nguyễn Văn A" class="form-input" style="width: 100%; box-sizing: border-box;" autocomplete="name">
          </div>
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Số điện thoại</label>
            <input type="tel" name="phone" id="auth-phone-input" placeholder="0901234567" class="form-input" style="width: 100%; box-sizing: border-box;" autocomplete="tel">
          </div>
        ` : ''}

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Email tài khoản</label>
          <input
            type="email"
            name="email"
            id="auth-email-input"
            required
            placeholder="nhap.email@example.com"
            class="form-input"
            style="width: 100%; box-sizing: border-box;"
            autocomplete="email"
          >
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Mật khẩu</label>
          <input
            type="password"
            name="password"
            id="auth-password-input"
            required
            minlength="6"
            placeholder="Tối thiểu 6 ký tự"
            class="form-input"
            style="width: 100%; box-sizing: border-box;"
            autocomplete="current-password"
          >
        </div>

        <button
          type="submit"
          id="auth-submit-btn"
          class="btn btn-primary"
          style="margin-top: 6px; width: 100%; padding: 10px; font-size: 0.95rem; font-weight: 600; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;"
        >
          <span>🔐</span>
          <span id="auth-submit-text">
            ${this.isRegisterMode ? 'Đăng ký tài khoản' : `Đăng nhập (${roleConfig.name})`}
          </span>
        </button>
      </form>

      <!-- Toggle Mode Footer -->
      <div style="margin-top: 16px; text-align: center; font-size: 0.85rem; color: #94a3b8;">
        ${this.isRegisterMode
          ? 'Đã có tài khoản? <a href="#" id="toggle-auth-mode" style="color: #60a5fa; text-decoration: none; font-weight: 600;">Đăng nhập ngay</a>'
          : 'Chưa có tài khoản? <a href="#" id="toggle-auth-mode" style="color: #60a5fa; text-decoration: none; font-weight: 600;">Đăng ký người chơi mới</a>'}
      </div>
    `;

    // 1. Close button
    const closeBtn = this.modal.querySelector('#auth-close-btn');
    if (closeBtn) closeBtn.onclick = () => this.close();

    // 2. Toggle Login/Register
    const toggleBtn = this.modal.querySelector('#toggle-auth-mode');
    if (toggleBtn) {
      toggleBtn.onclick = (e) => {
        e.preventDefault();
        this.isRegisterMode = !this.isRegisterMode;
        this.updateContent();
      };
    }

    // 3. Role selector buttons
    this.modal.querySelectorAll('.role-tab-btn').forEach(btn => {
      btn.onclick = () => {
        const role = btn.dataset.role;
        this.selectRole(role);
      };
    });

    // 4. Fill Demo helper button
    const btnFillDemo = this.modal.querySelector('#btn-fill-demo');
    if (btnFillDemo) {
      btnFillDemo.onclick = () => {
        const emailInput = this.modal.querySelector('#auth-email-input');
        const passInput = this.modal.querySelector('#auth-password-input');
        if (emailInput && passInput) {
          emailInput.value = roleConfig.demoEmail;
          passInput.value = roleConfig.demoPassword;
          emailInput.focus();
          toast.info(`Đã điền tài khoản mẫu ${roleConfig.name}`);
        }
      };
    }

    // 5. Form submission
    const form = this.modal.querySelector('#auth-form');
    if (form) {
      form.onsubmit = async (e) => {
        e.preventDefault();
        const errorAlert = this.modal.querySelector('#auth-error-alert');
        const submitBtn = this.modal.querySelector('#auth-submit-btn');
        const submitText = this.modal.querySelector('#auth-submit-text');

        if (errorAlert) errorAlert.style.display = 'none';
        if (submitBtn) submitBtn.disabled = true;
        if (submitText) submitText.textContent = 'Đang xác thực thông tin...';

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
          if (this.isRegisterMode) {
            await authState.register(data);
            toast.success('Đăng ký tài khoản thành công!');
          } else {
            await authState.login(data.email, data.password);
            const user = authState.getUser();

            // Verification of returned role vs selected role
            if (user && user.role !== this.selectedRole) {
              toast.info(`Tài khoản của bạn thuộc vai trò ${user.role}. Hệ thống đang đăng nhập theo vai trò thực tế.`);
            } else {
              toast.success(`Đăng nhập thành công với vai trò: ${user?.role || 'Player'}`);
            }
          }

          this.close();
          this.onSuccess(authState.getUser(), this.selectedRole);
        } catch (err) {
          const errMsg = err.message || 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin.';
          if (errorAlert) {
            errorAlert.textContent = errMsg;
            errorAlert.style.display = 'block';
          }
          toast.error(errMsg);
        } finally {
          if (submitBtn) submitBtn.disabled = false;
          if (submitText) {
            submitText.textContent = this.isRegisterMode
              ? 'Đăng ký tài khoản'
              : `Đăng nhập (${roleConfig.name})`;
          }
        }
      };
    }
  }

  /**
   * Open modal (supports both open() and show() for full backwards compatibility)
   * @param {boolean} registerMode Whether to open in registration mode
   * @param {string} defaultRole Initial selected role ('PLAYER', 'DM', 'ADMIN')
   */
  open(registerMode = false, defaultRole = 'PLAYER') {
    this.isRegisterMode = registerMode;
    const configs = getRoleConfigs();
    if (configs[defaultRole]) {
      this.selectedRole = defaultRole;
    }
    this.render();
    this.updateContent();
    if (!this.modal.open) {
      this.modal.showModal();
    }
  }

  /**
   * Alias for open()
   */
  show(registerMode = false, defaultRole = 'PLAYER') {
    this.open(registerMode, defaultRole);
  }

  close() {
    if (this.modal && this.modal.open) {
      this.modal.close();
    }
  }
}
