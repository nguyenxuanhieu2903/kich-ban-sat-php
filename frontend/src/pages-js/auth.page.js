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

  // 1. XỬ LÝ ĐĂNG NHẬP THẬT QUA BACKEND API (/api/v1/auth/login)
  forms.login?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-account').value.trim().toLowerCase();
    const password = document.getElementById('login-password').value;

    if (!email || !password) {
      showAlert('alert-login', 'error', 'Vui lòng nhập đầy đủ email và mật khẩu.');
      return;
    }

    try {
      const data = await api.post('/auth/login', { email, password });
      const u = data?.user || data;
      const userRole = String(u?.role || u?.role_code || 'PLAYER').toLowerCase();

      store.state.currentUser = {
        ...store.state.currentUser,
        id: u.id,
        name: u.full_name || u.name || u.email,
        phone: u.phone || '',
        email: u.email || email,
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
      showAlert('alert-login', 'error', err.message || 'Email hoặc mật khẩu không chính xác.');
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

  // 3. XỬ LÝ QUÊN MẬT KHẨU
  let otpSent = false;
  forms.forgot?.addEventListener('submit', (e) => {
    e.preventDefault();
    const recovery = document.getElementById('forgot-account').value.trim();
    const otpStep = document.getElementById('otp-step');
    const submitBtn = document.getElementById('btn-forgot-submit');

    if (!recovery) {
      showAlert('alert-forgot', 'error', 'Vui lòng nhập Email hoặc Số điện thoại của bạn.');
      return;
    }

    if (!otpSent) {
      otpSent = true;
      otpStep.style.display = 'flex';
      submitBtn.textContent = 'Xác nhận đổi mật khẩu';
      showAlert('alert-forgot', 'success', `Đã gửi mã xác thực 6 số tới "${recovery}". (Demo: nhập 6 số bất kỳ để đặt lại mật khẩu)`);
      return;
    }

    const otp = document.getElementById('forgot-otp').value.trim();
    const newPass = document.getElementById('forgot-new-pass').value;

    if (otp.length < 4) {
      showAlert('alert-forgot', 'error', 'Vui lòng nhập mã xác thực OTP.');
      return;
    }
    if (newPass.length < 6) {
      showAlert('alert-forgot', 'error', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
      return;
    }

    const savedUsers = JSON.parse(localStorage.getItem('KBS_USERS') || '[]');
    const idx = savedUsers.findIndex((u) => u.email === recovery || u.phone === recovery);
    if (idx !== -1) {
      savedUsers[idx].password = newPass;
      localStorage.setItem('KBS_USERS', JSON.stringify(savedUsers));
    }

    otpSent = false;
    otpStep.style.display = 'none';
    submitBtn.textContent = 'Gửi mã xác thực';
    forms.forgot.reset();

    window.location.hash = 'login';
    switchTab('login');
    showAlert('alert-login', 'success', 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại bằng mật khẩu mới.');
  });
});