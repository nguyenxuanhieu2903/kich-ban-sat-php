import { el, clear } from '../utils/dom.js';
import { getCachedUser, getMe, login, logout } from '../api/player-api.js';
import { showToast } from '../components/toast.js';
import { openModal, closeModal } from '../components/modal.js';

const NAV = [
  { id: 'lobby', href: './lobby.html', label: 'Sảnh phòng' },
  { id: 'create-room', href: './create-room.html', label: 'Tạo phòng' },
  { id: 'profile', href: './profile.html', label: 'Hồ sơ' },
];

function renderUser(actions, user) {
  const chip = actions.querySelector('[data-user-chip]');
  const loginBtn = actions.querySelector('[data-login]');
  const logoutBtn = actions.querySelector('[data-logout]');
  if (user?.full_name || user?.email) {
    chip.textContent = user.full_name || user.email;
    chip.hidden = false;
    loginBtn.hidden = true;
    logoutBtn.hidden = false;
  } else {
    chip.hidden = true;
    loginBtn.hidden = false;
    logoutBtn.hidden = true;
  }
}

function openLoginModal(actions) {
  const form = el('form', { className: 'kbs-form', id: 'kbsLoginForm' },
    el('div', { className: 'kbs-field kbs-dark-field' },
      el('label', { className: 'kbs-label', htmlFor: 'kbsLoginEmail', text: 'Email' }),
      el('input', { className: 'kbs-input', id: 'kbsLoginEmail', type: 'email', autocomplete: 'username', required: true })
    ),
    el('div', { className: 'kbs-field kbs-dark-field', style: 'margin-top:12px' },
      el('label', { className: 'kbs-label', htmlFor: 'kbsLoginPassword', text: 'Mật khẩu' }),
      el('input', { className: 'kbs-input', id: 'kbsLoginPassword', type: 'password', autocomplete: 'current-password', required: true })
    )
  );
  form.querySelector('#kbsLoginEmail').style.marginTop = '0';

  openModal({
    title: 'Đăng nhập người chơi',
    body: form,
    actions: [
      { label: 'Hủy' },
      {
        label: 'Đăng nhập',
        primary: true,
        keepOpen: true,
        onClick: async () => {
          const email = form.querySelector('#kbsLoginEmail').value.trim();
          const password = form.querySelector('#kbsLoginPassword').value;
          if (!email || !password) {
            showToast('Nhập email và mật khẩu.', 'error');
            return;
          }
          try {
            const user = await login({ email, password });
            renderUser(actions, user);
            closeModal();
            showToast('Đăng nhập thành công.', 'success');
            window.dispatchEvent(new CustomEvent('kbs:auth-changed', { detail: user }));
          } catch (error) {
            showToast(error.message || 'Không đăng nhập được.', 'error');
          }
        },
      },
    ],
  });
}

export async function mountPlayerShell({ currentPage } = {}) {
  const headerHost = document.getElementById('portal-header');
  const footerHost = document.getElementById('portal-footer');
  if (!headerHost || !footerHost) return;

  clear(headerHost);
  const header = el('header', { className: 'kbs-shell-header' },
    el('div', { className: 'kbs-shell-inner' },
      el('a', { className: 'kbs-brand', href: './lobby.html' },
        el('span', { className: 'kbs-brand-mark', text: 'KS' }),
        'Kịch Bản Sát'
      ),
      el('nav', { className: 'kbs-nav', 'aria-label': 'Player Portal' },
        ...NAV.map((item) => el('a', {
          href: item.href,
          className: item.id === currentPage ? 'is-active' : '',
          'aria-current': item.id === currentPage ? 'page' : null,
          text: item.label,
        }))
      ),
      el('div', { className: 'kbs-shell-actions' },
        el('span', { className: 'kbs-user-chip', 'data-user-chip': true, hidden: true }),
        el('button', { type: 'button', className: 'kbs-btn kbs-btn--ghost', 'data-login': true, text: 'Đăng nhập' }),
        el('button', { type: 'button', className: 'kbs-btn kbs-btn--ghost', 'data-logout': true, hidden: true, text: 'Đăng xuất' })
      )
    )
  );
  headerHost.append(header);

  const actions = header.querySelector('.kbs-shell-actions');
  actions.querySelector('[data-login]').addEventListener('click', () => openLoginModal(actions));
  actions.querySelector('[data-logout]').addEventListener('click', async () => {
    await logout();
    renderUser(actions, null);
    showToast('Đã đăng xuất.', 'ok');
    window.dispatchEvent(new CustomEvent('kbs:auth-changed', { detail: null }));
  });

  clear(footerHost);
  footerHost.append(
    el('footer', { className: 'kbs-shell-footer' },
      el('div', { className: 'kbs-shell-foot-inner' },
        el('p', { text: 'Kịch Bản Sát — Cổng người chơi. Dark Luxury · Mystery · Vintage.' }),
        el('p', { text: 'Đặt phòng kịch bản sát · Không lưu mật khẩu trên trình duyệt.' })
      )
    )
  );

  renderUser(actions, getCachedUser());
  try {
    const me = await getMe();
    renderUser(actions, me);
    window.dispatchEvent(new CustomEvent('kbs:auth-changed', { detail: me }));
  } catch {
    renderUser(actions, getCachedUser());
  }
}

export async function requireAuth() {
  const cached = getCachedUser();
  if (cached?.id) return cached;
  try {
    return await getMe();
  } catch {
    return null;
  }
}
