// src/pages-js/auth.page.js
import { initChrome } from '../logic/layout.js';
import { store } from '../logic/store.js';
import { api } from '../logic/api.js';

initChrome('auth');
document.addEventListener('DOMContentLoaded', () => {
  const tabsBar = document.getElementById('auth-tabs');
  const tabButtons = document.querySelectorAll('.auth-tab');
  const forms = {
    login: document.getElementById('form-login'),
    register: document.getElementById('form-register'),
    forgot: document.getElementById('form-forgot')
  };
  const titleEl = document.getElementById('auth-title');

  const TITLES = {
    login: 'Đăng nhập hệ thống',
    register: 'Đăng ký thành viên',
    forgot: 'Khôi phục mật khẩu'
  };

  function showAlert(id, type, message) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = `auth-alert ${type}`;
    el.textContent = message;
  }

  function clearAlerts() {
    document.querySelectorAll('.auth-alert').forEach((el) => {
      el.className = 'auth-alert';
      el.textContent = '';
    });
  }

  function switchTab(tabName) {
    const cleanName = (tabName || '').replace('#', '').trim();
    const target = forms[cleanName] ? cleanName : 'login';
    clearAlerts();

    Object.entries(forms).forEach(([key, form]) => {
      if (form) form.classList.toggle('active', key === target);
    });

    if (tabsBar) {
      tabsBar.style.display = target === 'forgot' ? 'none' : 'grid';
    }
    tabButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tab === target);
    });

    if (titleEl) {
      titleEl.textContent = TITLES[target];
    }
  }

  // Bắt sự kiện click vào 2 tab Đăng nhập / Đăng ký
  tabButtons.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const target = btn.dataset.tab;
      window.location.hash = target;
      switchTab(target);
    });
  });

  // Bắt sự kiện chuyển sang Quên mật khẩu / Quay lại
  document.getElementById('link-forgot')?.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.hash = 'forgot';
    switchTab('forgot');
  });

  document.getElementById('link-back-login')?.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.hash = 'login';
    switchTab('login');
  });

  // Lắng nghe thay đổi hash trên thanh địa chỉ (khi bấm link Đăng ký / Đăng nhập trên Header)
  window.addEventListener('hashchange', () => {
    switchTab(window.location.hash);
  });

  // Khởi tạo tab ban đầu theo URL
  switchTab(window.location.hash || 'login');

  // Nút Ẩn / Hiện mật khẩu
  document.querySelectorAll('.toggle-pass').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? 'Ẩn' : 'Hiện';
    });
  });

  // 1. XỬ LÝ ĐĂNG NHẬP THẬT QUA BACKEND API (/api/v1/auth/login — hỗ trợ Email hoặc Số điện thoại)
  forms.login?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const account = document.getElementById('login-account').value.trim();
    const password = document.getElementById('login-password').value;

    if (!account || !password) {
      showAlert('alert-login', 'error', 'Vui lòng nhập đầy đủ Email hoặc Số điện thoại và mật khẩu.');
      return;
    }

    try {
      const data = await api.post('/auth/login', { email: account, password });
      const u = data?.user || data;
      const userRole = String(u?.role || u?.role_code || 'PLAYER').toLowerCase();

      store.state.currentUser = {
        ...store.state.currentUser,
        id: u.id,
        name: u.full_name || u.name || u.email,
        phone: u.phone || '',
        email: u.email || account,
        avatar: String(u.full_name || u.email || 'US').slice(0, 2).toUpperCase()
      };
      store.setRole(userRole);

      const redirectMap = {
        admin: './admin-portal.html',
        dm: './dm-portal.html',
        player: './lobby.html'
      };
      const roleNameMap = {
        admin: 'Quản Trị Viên (Admin)',
        dm: 'Quản Trò (DM)',
        player: 'Người Chơi (Player)'
      };

      showAlert('alert-login', 'success', `Đăng nhập thành công (${roleNameMap[userRole] || userRole})! Đang chuyển hướng...`);
      setTimeout(() => {
        window.location.href = redirectMap[userRole] || '../index.html';
      }, 500);
    } catch (err) {
      showAlert('alert-login', 'error', err.message || 'Email/Số điện thoại hoặc mật khẩu không chính xác.');
    }
  });

  // 2. XỬ LÝ ĐĂNG KÝ THẬT QUA BACKEND API (/api/v1/auth/register)
  forms.register?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('reg-name').value.trim();
    const phone = document.getElementById('reg-phone').value.trim();
    const email = document.getElementById('reg-email').value.trim().toLowerCase();
    const password = document.getElementById('reg-password').value;
    const confirm = document.getElementById('reg-confirm').value;

    if (!name || !phone || !email || !password) {
      showAlert('alert-register', 'error', 'Vui lòng điền đầy đủ tất cả các trường bắt buộc.');
      return;
    }
    if (password.length < 6) {
      showAlert('alert-register', 'error', 'Mật khẩu phải có ít nhất 6 ký tự.');
      return;
    }
    if (password !== confirm) {
      showAlert('alert-register', 'error', 'Mật khẩu xác nhận không khớp.');
      return;
    }

    try {
      const data = await api.post('/auth/register', { full_name: name, phone, email, password });
      const u = data?.user || data;
      store.state.currentUser = {
        ...store.state.currentUser,
        id: u?.id || 'NEW',
        name: u?.full_name || name,
        phone: u?.phone || phone,
        email: u?.email || email,
        avatar: name.slice(0, 2).toUpperCase()
      };
      store.setRole('player');
      showAlert('alert-register', 'success', 'Đăng ký tài khoản thành công! Đang chuyển vào Sảnh Ghép Phòng...');
      setTimeout(() => {
        window.location.href = './lobby.html';
      }, 650);
    } catch (err) {
      showAlert('alert-register', 'error', err.message || 'Đăng ký thất bại. Vui lòng thử lại.');
    }
  });

  // 3. KHÔI PHỤC MẬT KHẨU THẬT QUA BACKEND API (/auth/forgot-password -> /auth/reset-password)
  const forgotStep1 = document.getElementById('forgot-step-1');
  const forgotStep2 = document.getElementById('forgot-step-2');
  const forgotOtpBox = document.getElementById('forgot-otp-box');
  const btnForgotRequest = document.getElementById('btn-forgot-request');
  const btnForgotReset = document.getElementById('btn-forgot-reset');
  const btnForgotRestart = document.getElementById('btn-forgot-restart');
  let verifiedForgotEmail = '';

  function resetForgotSteps() {
    verifiedForgotEmail = '';
    if (forgotStep1) forgotStep1.style.display = 'flex';
    if (forgotStep2) forgotStep2.style.display = 'none';
    if (forgotOtpBox) {
      forgotOtpBox.style.display = 'none';
      forgotOtpBox.textContent = '';
    }
    const otpInput = document.getElementById('forgot-otp');
    const newPassInput = document.getElementById('forgot-new-password');
    const confirmInput = document.getElementById('forgot-confirm-password');
    if (otpInput) otpInput.value = '';
    if (newPassInput) newPassInput.value = '';
    if (confirmInput) confirmInput.value = '';
  }

  document.getElementById('link-forgot')?.addEventListener('click', () => {
    resetForgotSteps();
  });

  btnForgotRestart?.addEventListener('click', () => {
    clearAlerts();
    resetForgotSteps();
  });

  forms.forgot?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (forgotStep2 && forgotStep2.style.display === 'flex') {
      btnForgotReset?.click();
      return;
    }

    const email = (document.getElementById('forgot-email')?.value || '').trim().toLowerCase();
    const phone = (document.getElementById('forgot-phone')?.value || '').trim();

    if (!email || !phone) {
      showAlert('alert-forgot', 'error', 'Vui lòng nhập đầy đủ Email và Số điện thoại đã đăng ký.');
      return;
    }

    if (btnForgotRequest) {
      btnForgotRequest.disabled = true;
      btnForgotRequest.textContent = 'Đang xác minh...';
    }

    try {
      const data = await api.post('/auth/forgot-password', { email, phone });
      verifiedForgotEmail = data?.email || email;

      if (forgotStep1) forgotStep1.style.display = 'none';
      if (forgotStep2) forgotStep2.style.display = 'flex';

      if (forgotOtpBox && data?.demo_otp) {
        forgotOtpBox.style.display = 'block';
        forgotOtpBox.innerHTML = '';
        const titleNode = document.createElement('strong');
        titleNode.style.color = '#8B1E1E';
        titleNode.style.display = 'block';
        titleNode.textContent = `Mã OTP xác thực cho ${verifiedForgotEmail}: ${data.demo_otp}`;
        const noteNode = document.createElement('span');
        noteNode.textContent = 'Mã có hiệu lực 10 phút (tối đa 5 lần nhập sai). Chế độ hiển thị OTP trực tiếp vì máy chủ demo chưa gắn SMTP.';
        forgotOtpBox.appendChild(titleNode);
        forgotOtpBox.appendChild(noteNode);
      }

      showAlert('alert-forgot', 'success', 'Xác minh thành công! Nhập mã OTP và mật khẩu mới để hoàn tất.');
      document.getElementById('forgot-otp')?.focus();
    } catch (err) {
      showAlert('alert-forgot', 'error', err.message || 'Không thể xác minh Email và Số điện thoại.');
    } finally {
      if (btnForgotRequest) {
        btnForgotRequest.disabled = false;
        btnForgotRequest.textContent = 'Nhận Mã Xác Thực OTP';
      }
    }
  });

  btnForgotReset?.addEventListener('click', async () => {
    const email = verifiedForgotEmail || (document.getElementById('forgot-email')?.value || '').trim().toLowerCase();
    const otp = (document.getElementById('forgot-otp')?.value || '').trim();
    const newPassword = document.getElementById('forgot-new-password')?.value || '';
    const confirmPassword = document.getElementById('forgot-confirm-password')?.value || '';

    if (!email || !otp || !newPassword) {
      showAlert('alert-forgot', 'error', 'Vui lòng nhập đầy đủ mã OTP và mật khẩu mới.');
      return;
    }
    if (otp.length !== 6) {
      showAlert('alert-forgot', 'error', 'Mã OTP phải gồm đúng 6 chữ số.');
      return;
    }
    if (newPassword.length < 6) {
      showAlert('alert-forgot', 'error', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
      return;
    }
    if (newPassword !== confirmPassword) {
      showAlert('alert-forgot', 'error', 'Mật khẩu xác nhận không khớp.');
      return;
    }

    btnForgotReset.disabled = true;
    btnForgotReset.textContent = 'Đang cập nhật...';

    try {
      await api.post('/auth/reset-password', {
        email,
        otp,
        new_password: newPassword,
        confirm_password: confirmPassword
      });

      resetForgotSteps();
      window.location.hash = 'login';
      switchTab('login');
      const loginAccount = document.getElementById('login-account');
      if (loginAccount) loginAccount.value = email;
      showAlert('alert-login', 'success', 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập bằng mật khẩu mới.');
    } catch (err) {
      showAlert('alert-forgot', 'error', err.message || 'Không thể đặt lại mật khẩu. Vui lòng kiểm tra mã OTP.');
    } finally {
      btnForgotReset.disabled = false;
      btnForgotReset.textContent = 'Xác Nhận Đặt Lại Mật Khẩu';
    }
  });
});