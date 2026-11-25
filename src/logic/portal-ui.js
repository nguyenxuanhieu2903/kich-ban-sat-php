import { PortalError, uuid } from './portal-data.js';

export function esc(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

let toastTimer = null;
export function toast(prefix, message, type = 'ok') {
  let el = document.getElementById(`${prefix}-toast`);
  if (!el) {
    el = document.createElement('div');
    el.id = `${prefix}-toast`;
    el.setAttribute('role', 'status');
    document.body.appendChild(el);
  }
  el.className = `${prefix}-toast ${prefix}-toast--${type}`;
  el.textContent = message;
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, 3600);
}

export function openModal(prefix, { title, body, submitLabel = 'Xác nhận', danger = false, onSubmit, wide = false }) {
  closeModal(prefix);
  const wrap = document.createElement('div');
  wrap.id = `${prefix}-modal`;
  wrap.className = `${prefix}-modal`;
  wrap.innerHTML = `
    <div class="${prefix}-modal__backdrop" data-modal-close></div>
    <form class="${prefix}-modal__box ${wide ? `${prefix}-modal__box--wide` : ''}" role="dialog" aria-modal="true" aria-label="${esc(title)}" novalidate>
      <h3 class="${prefix}-modal__title">${esc(title)}</h3>
      <div class="${prefix}-modal__body">${body}</div>
      <p class="${prefix}-modal__error" data-modal-error hidden></p>
      <div class="${prefix}-modal__actions">
        <button type="button" class="${prefix}-btn ${prefix}-btn--ghost" data-modal-close>${onSubmit ? 'Huỷ' : 'Đóng'}</button>
        ${onSubmit ? `<button type="submit" class="${prefix}-btn ${danger ? `${prefix}-btn--danger` : `${prefix}-btn--primary`}">${esc(submitLabel)}</button>` : ''}
      </div>
    </form>`;
  document.body.appendChild(wrap);
  document.body.classList.add(`${prefix}-noscroll`);

  const form = wrap.querySelector('form');
  const errorEl = wrap.querySelector('[data-modal-error]');
  wrap.addEventListener('click', (e) => { if (e.target.closest('[data-modal-close]')) closeModal(prefix); });
  const onKey = (e) => { if (e.key === 'Escape') closeModal(prefix); };
  document.addEventListener('keydown', onKey);
  wrap._cleanup = () => document.removeEventListener('keydown', onKey);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!onSubmit) return;
    const submit = form.querySelector('button[type="submit"]');
    if (submit.disabled) return;
    submit.disabled = true;
    errorEl.hidden = true;
    try {
      await onSubmit(Object.fromEntries(new FormData(form).entries()), uuid());
      closeModal(prefix);
    } catch (err) {
      errorEl.textContent = err instanceof PortalError ? err.message : 'Có lỗi xảy ra, vui lòng thử lại.';
      errorEl.hidden = false;
      submit.disabled = false;
    }
  });

  const first = form.querySelector('input, select, textarea, button');
  first?.focus();
  return wrap;
}

export function closeModal(prefix) {
  const el = document.getElementById(`${prefix}-modal`);
  if (!el) return;
  el._cleanup?.();
  el.remove();
  document.body.classList.remove(`${prefix}-noscroll`);
}

export async function runAction(prefix, button, action, successMessage) {
  if (button) {
    if (button.disabled) return false;
    button.disabled = true;
  }
  try {
    const result = await action(uuid());
    toast(prefix, typeof successMessage === 'function' ? successMessage(result) : successMessage || 'Đã thực hiện.');
    return true;
  } catch (err) {
    toast(prefix, err instanceof PortalError ? err.message : 'Có lỗi xảy ra, vui lòng thử lại.', 'error');
    if (!(err instanceof PortalError)) console.error(err);
    return false;
  } finally {
    if (button?.isConnected) button.disabled = false;
  }
}

export const field = (prefix, label, control, hint = '') =>
  `<label class="${prefix}-field"><span class="${prefix}-field__label">${esc(label)}</span>${control}${hint ? `<span class="${prefix}-field__hint">${esc(hint)}</span>` : ""}</label>`;

export function badge(prefix, text, tone = 'neutral') {
  return `<span class="${prefix}-badge ${prefix}-badge--${tone}">${esc(text)}</span>`;
}

const ROLE_KEY = 'kbs_demo_role';
const ROLE_LABEL = { guest: 'Khách vãng lai', player: 'Player', dm: 'Quản trò (DM)', admin: 'Quản trị (Admin)' };
const ROLE_HOME = { admin: './admin-portal.html', dm: './dm-portal.html', player: './profile.html', guest: '../index.html' };

export function getDemoRole() {
  try { return localStorage.getItem(ROLE_KEY) || 'guest'; } catch { return 'guest'; }
}
function setDemoRole(role) {
  try { localStorage.setItem(ROLE_KEY, role); } catch {  }
}

export function initPortalChrome({ prefix, expectedRole, root }) {
  const select = document.getElementById('demoRoleSwitcher');
  const authBtn = document.getElementById('authActionBtn');
  const badge = document.getElementById('notifBadge');
  const role = getDemoRole();

  if (select) {
    select.value = role;
    select.addEventListener('change', () => {
      setDemoRole(select.value);
      window.location.href = ROLE_HOME[select.value] || '../index.html';
    });
  }
  if (authBtn) {
    const map = {
      guest: ['Đăng nhập', './auth.html'], player: ['Hồ sơ Player (HSSV ✓)', './profile.html'],
      dm: ['Cổng Quản Trò (DM)', './dm-portal.html'], admin: ['Cổng Admin', './admin-portal.html'],
    };
    const [text, href] = map[role] || map.guest;
    authBtn.textContent = text;
    authBtn.setAttribute('href', href);
  }

  const setBadge = (n) => { if (badge) badge.textContent = String(n || 0); };
  const allowed = role === expectedRole;
  if (!allowed && root) {
    root.innerHTML = `
      <div class="${prefix}-main"><div class="${prefix}-card ${prefix}-denied">
        <span class="stamp-badge">KHÔNG CÓ QUYỀN TRUY CẬP</span>
        <h1 class="${prefix}-h1" style="margin-top:12px">Cổng ${expectedRole === 'admin' ? 'Quản trị' : 'Quản trò'} chỉ dành cho ${esc(ROLE_LABEL[expectedRole])}</h1>
        <p>Bạn đang ở vai trò <strong>${esc(ROLE_LABEL[role] || role)}</strong>. Đăng nhập đúng tài khoản, hoặc chuyển nhanh vai trò demo để xem trang này.</p>
        <button type="button" class="btn btn-primary" id="${prefix}-switch-role">Chuyển sang vai trò ${esc(ROLE_LABEL[expectedRole])} (demo)</button>
      </div></div>`;
    document.getElementById(`${prefix}-switch-role`).addEventListener('click', () => { setDemoRole(expectedRole); window.location.reload(); });
  }
  return { allowed, setBadge };
}
