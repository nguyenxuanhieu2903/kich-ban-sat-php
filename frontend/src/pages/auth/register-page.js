/**
 * Registration Page (#/register)
 * All newly registered accounts are strictly assigned the PLAYER role.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { auth, ROLES } from '../../utils/auth.js';
import { showToast } from '../../components/toast.js';

export class RegisterPage {
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
        h('h1', { className: 'auth-title' }, 'Đăng Ký Tài Khoản'),
        h('p', { className: 'text-muted' }, 'Tài khoản đăng ký mặc định là Người chơi (PLAYER).')
      ),

      h('form', {
        className: 'auth-form',
        onsubmit: (e) => {
          e.preventDefault();
          this.handleRegister(e.target);
        },
      },
        h('div', { className: 'form-group' },
          h('label', { className: 'form-label' }, 'Họ và tên:'),
          h('input', {
            type: 'text',
            name: 'name',
            required: true,
            className: 'form-control',
            placeholder: 'Ví dụ: Nguyễn Văn An',
          })
        ),
        h('div', { className: 'form-group' },
          h('label', { className: 'form-label' }, 'Email:'),
          h('input', {
            type: 'email',
            name: 'email',
            required: true,
            className: 'form-control',
            placeholder: 'an.nguyen@example.com',
          })
        ),
        h('div', { className: 'form-group' },
          h('label', { className: 'form-label' }, 'Số điện thoại:'),
          h('input', {
            type: 'tel',
            name: 'phone',
            required: true,
            className: 'form-control',
            placeholder: '0901234567',
          })
        ),
        h('div', { className: 'form-group' },
          h('label', { className: 'form-label' }, 'Mật khẩu:'),
          h('input', {
            type: 'password',
            name: 'password',
            required: true,
            className: 'form-control',
            placeholder: 'Tối thiểu 8 ký tự',
            minLength: 6,
          })
        ),
        h('div', { className: 'form-note text-muted', style: { fontSize: '0.85rem', marginBottom: '1rem' } },
          'Quy định phân quyền: Quyền Quản trò (DM) và Quản trị viên (Admin) chỉ do Admin cấp trong hệ thống.'
        ),
        h('button', { type: 'submit', className: 'btn btn-primary btn-block btn-lg' }, 'Tạo Tài Khoản Người Chơi')
      ),

      h('div', { className: 'auth-footer text-center' },
        h('p', { className: 'text-muted' },
          'Đã có tài khoản? ',
          h('a', { className: 'link-gold', href: '#/login' }, 'Đăng nhập ngay')
        )
      )
    );

    container.appendChild(card);
    this.mountPoint.appendChild(container);
  }

  handleRegister(form) {
    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const phone = form.phone.value.trim();

    const newUser = {
      id: `user_${Date.now()}`,
      name,
      email,
      phone,
      role: ROLES.PLAYER,
      student_verified: false,
    };

    auth.login(newUser);
    showToast('Đăng ký tài khoản thành công! Đã đăng nhập vào hệ thống.', 'success');

    const pendingRoute = auth.consumeRedirectAfterLogin();
    if (pendingRoute) {
      this.router.navigate(pendingRoute);
    } else {
      this.router.navigate('/player/dashboard');
    }
  }
}
