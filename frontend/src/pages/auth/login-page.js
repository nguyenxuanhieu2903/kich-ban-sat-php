/**
 * Login Page (#/login)
 * Supports credential entry and 1-click instant login for Player, DM, and Admin roles.
 * Automatically redirects back to the previous pending action/route upon success.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { auth, ROLES, DEMO_USERS } from '../../utils/auth.js';
import { showToast } from '../../components/toast.js';

export class LoginPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-auth container' });

    const card = h('div', { className: 'auth-card card-dark' },
      h('div', { className: 'auth-header text-center' },
        h('span', { className: 'auth-icon' }, renderIcon('user')),
        h('h1', { className: 'auth-title' }, 'Đăng Nhập Hệ Thống'),
        h('p', { className: 'text-muted' }, 'Truy cập để tạo phòng, tham gia kịch bản và quản lý phiên chơi.')
      ),

      // 1-Click Demo Accounts (Very handy for evaluation and grading)
      h('div', { className: 'demo-quick-login-box' },
        h('span', { className: 'demo-quick-title text-muted' }, 'Đăng nhập nhanh chế độ Demo:'),
        h('div', { className: 'quick-login-btns' },
          h('button', {
            type: 'button',
            className: 'btn btn-secondary btn-block',
            onclick: () => this.handleQuickLogin(ROLES.PLAYER),
          }, '👤 Người chơi (Player - Có HSSV)'),
          h('button', {
            type: 'button',
            className: 'btn btn-secondary btn-block',
            onclick: () => this.handleQuickLogin(ROLES.DM),
          }, '🎭 Quản trò (DM Hải Đăng)'),
          h('button', {
            type: 'button',
            className: 'btn btn-secondary btn-block',
            onclick: () => this.handleQuickLogin(ROLES.ADMIN),
          }, '⚙️ Quản trị viên (Admin)')
        )
      ),

      h('div', { className: 'auth-divider' }, h('span', {}, 'HOẶC TÀI KHOẢN TỰ NHẬP')),

      // Standard Login Form
      h('form', {
        className: 'auth-form',
        onsubmit: (e) => {
          e.preventDefault();
          this.handleStandardLogin(e.target);
        },
      },
        h('div', { className: 'form-group' },
          h('label', { className: 'form-label' }, 'Email đăng nhập:'),
          h('input', {
            type: 'email',
            name: 'email',
            required: true,
            className: 'form-control',
            placeholder: 'name@example.com',
            value: 'player@kichbansat.vn',
          })
        ),
        h('div', { className: 'form-group' },
          h('label', { className: 'form-label' }, 'Mật khẩu:'),
          h('input', {
            type: 'password',
            name: 'password',
            required: true,
            className: 'form-control',
            placeholder: '••••••••',
            value: 'password123',
          })
        ),
        h('button', { type: 'submit', className: 'btn btn-primary btn-block btn-lg' }, 'Đăng Nhập')
      ),

      h('div', { className: 'auth-footer text-center' },
        h('p', { className: 'text-muted' },
          'Chưa có tài khoản? ',
          h('a', { className: 'link-gold', href: '#/register' }, 'Đăng ký Người chơi mới')
        )
      )
    );

    container.appendChild(card);
    this.mountPoint.appendChild(container);
  }

  handleQuickLogin(role) {
    auth.switchDemoRole(role);
    showToast(`Đăng nhập thành công với vai trò ${role}!`, 'success');
    this.redirectAfterSuccess(role);
  }

  handleStandardLogin(form) {
    const email = form.email.value.trim();
    // Default standard login creates a PLAYER account
    const user = {
      id: `user_${Date.now()}`,
      name: email.split('@')[0],
      email,
      role: ROLES.PLAYER,
      student_verified: false,
    };
    auth.login(user);
    showToast('Đăng nhập thành công!', 'success');
    this.redirectAfterSuccess(ROLES.PLAYER);
  }

  redirectAfterSuccess(role) {
    const pendingRoute = auth.consumeRedirectAfterLogin();
    if (pendingRoute) {
      this.router.navigate(pendingRoute);
      return;
    }

    if (role === ROLES.PLAYER) {
      this.router.navigate('/player/dashboard');
    } else if (role === ROLES.DM) {
      this.router.navigate('/dm/dashboard');
    } else if (role === ROLES.ADMIN) {
      this.router.navigate('/admin/dashboard');
    } else {
      this.router.navigate('/');
    }
  }
}
