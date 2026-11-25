import { el, clear } from '../utils/dom.js';

function host() {
  let node = document.getElementById('kbs-modal-root');
  if (!node) {
    node = document.createElement('div');
    node.id = 'kbs-modal-root';
    document.body.appendChild(node);
  }
  return node;
}

export function closeModal() {
  const node = document.getElementById('kbs-modal-root');
  if (!node) return;
  clear(node);
  node.hidden = true;
  document.body.classList.remove('kbs-modal-open');
}

export function openModal({ title, body, actions = [], tone = 'default' }) {
  const node = host();
  node.hidden = false;
  document.body.classList.add('kbs-modal-open');
  clear(node);

  const backdrop = el('div', { className: 'kbs-modal-backdrop', role: 'presentation' });
  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) closeModal();
  });

  const dialog = el('div', {
    className: `kbs-modal ${tone === 'paper' ? 'kbs-modal--paper' : ''}`,
    role: 'dialog',
    'aria-modal': 'true',
    'aria-labelledby': 'kbs-modal-title',
  });

  dialog.append(
    el('div', { className: 'kbs-modal-head' },
      el('h3', { id: 'kbs-modal-title', className: 'kbs-modal-title', text: title }),
      el('button', { type: 'button', className: 'kbs-modal-x', 'aria-label': 'Đóng' }, '×')
    )
  );
  dialog.querySelector('.kbs-modal-x').addEventListener('click', closeModal);

  const content = el('div', { className: 'kbs-modal-body' });
  if (typeof body === 'string') content.textContent = body;
  else if (body) content.append(body);
  dialog.append(content);

  if (actions.length) {
    const bar = el('div', { className: 'kbs-modal-actions' });
    actions.forEach((action) => {
      const btn = el('button', {
        type: 'button',
        className: `kbs-btn ${action.primary ? 'kbs-btn--primary' : action.gold ? 'kbs-btn--gold' : 'kbs-btn--ghost'}`,
        text: action.label,
      });
      btn.addEventListener('click', async () => {
        if (action.onClick) await action.onClick();
        if (!action.keepOpen) closeModal();
      });
      bar.append(btn);
    });
    dialog.append(bar);
  }

  backdrop.append(dialog);
  node.append(backdrop);
  dialog.querySelector('button, input, textarea')?.focus();
  return { close: closeModal, dialog };
}

export function confirmModal({ title, message, confirmLabel = 'Xác nhận', tone }) {
  return new Promise((resolve) => {
    openModal({
      title,
      body: message,
      tone,
      actions: [
        { label: 'Hủy', onClick: () => resolve(false) },
        {
          label: confirmLabel,
          primary: true,
          onClick: () => resolve(true),
        },
      ],
    });
  });
}
