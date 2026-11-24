import { h } from './dom.js';

let toastTimer = 0;
let toastHost;
let modalHost;
let lastFocus = null;
let dialogOpen = false;

function ensureHosts() {
  if (!toastHost) {
    toastHost = h('div', { class: 'player-toast', role: 'status', 'aria-live': 'polite' });
    toastHost.hidden = true;
    document.body.append(toastHost);
  }
  if (!modalHost) {
    modalHost = h('div', { class: 'player-modal', role: 'presentation' });
    modalHost.hidden = true;
    document.body.append(modalHost);
  }
}

export function toast(message, type = 'success') {
  ensureHosts();
  toastHost.className = `player-toast player-toast-${type}`;
  toastHost.textContent = message;
  toastHost.hidden = false;
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => {
    toastHost.hidden = true;
  }, 3400);
}

export function closeModal() {
  if (!modalHost) return;
  modalHost.hidden = true;
  modalHost.replaceChildren();
  document.body.classList.remove('player-modal-open');
  if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  lastFocus = null;
}

export function confirmDialog({
  title,
  message,
  confirmText = 'Xác nhận',
  cancelText = 'Hủy',
  tone = 'primary',
}) {
  if (dialogOpen) return Promise.resolve(false);
  dialogOpen = true;
  ensureHosts();
  lastFocus = document.activeElement;
  return new Promise((resolve) => {
    const onKey = (event) => {
      if (event.key === 'Escape') finish(false);
    };
    const finish = (value) => {
      dialogOpen = false;
      document.removeEventListener('keydown', onKey);
      closeModal();
      resolve(value);
    };
    document.addEventListener('keydown', onKey);
    const confirmClass = tone === 'danger' ? 'btn booking-btn-danger' : 'btn btn-primary';
    const dialog = h('div', { class: 'ticket-card player-modal-card', role: 'dialog', 'aria-modal': 'true' }, [
      h('h2', { class: 'player-modal-title', id: 'player-modal-title' }, title),
      h('p', { class: 'player-modal-copy' }, message),
      h('hr', { class: 'ticket-divider' }),
      h('div', { class: 'player-modal-actions' }, [
        h('button', { class: 'btn btn-outline', type: 'button', onClick: () => finish(false) }, cancelText),
        h('button', { class: confirmClass, type: 'button', id: 'player-modal-confirm', onClick: () => finish(true) }, confirmText),
      ]),
    ]);
    dialog.setAttribute('aria-labelledby', 'player-modal-title');
    modalHost.replaceChildren(dialog);
    modalHost.hidden = false;
    document.body.classList.add('player-modal-open');
    dialog.querySelector('#player-modal-confirm')?.focus();
    modalHost.onclick = (event) => {
      if (event.target === modalHost) finish(false);
    };
    dialog.onkeydown = (event) => {
      if (event.key === 'Escape') finish(false);
    };
  });
}

export function renderState(host, { kind, title, message, actionLabel, onAction }) {
  host.replaceChildren(h('div', {
    class: `player-state player-state-${kind}`,
    role: kind === 'error' ? 'alert' : 'status',
  }, [
    h('h2', { class: 'player-state-title' }, title),
    message ? h('p', {}, message) : null,
    actionLabel ? h('button', { class: 'btn btn-outline', type: 'button', onClick: onAction }, actionLabel) : null,
  ]));
}

export async function withBusy(button, task) {
  if (!button) return task();
  const previous = button.textContent;
  button.disabled = true;
  try {
    return await task();
  } finally {
    button.disabled = false;
    button.textContent = previous;
  }
}
