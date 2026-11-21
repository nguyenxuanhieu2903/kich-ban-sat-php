/**
 * Accessible Custom Confirmation Dialog
 * Direct replacement for native window.confirm()
 */
import { h, renderIcon } from '../utils/dom.js';
import { Modal } from './modal.js';

export function showConfirmDialog({
  title = 'Xác nhận thao tác',
  message,
  confirmLabel = 'Đồng ý',
  cancelLabel = 'Hủy bỏ',
  variant = 'warning', // 'warning', 'danger', 'info'
  onConfirm,
  onCancel,
}) {
  let modalInstance = null;

  const content = h('div', { className: 'confirm-dialog-content' },
    h('div', { className: 'confirm-dialog-message' },
      renderIcon(variant === 'danger' ? 'alertTriangle' : 'info', `confirm-dialog-icon icon-${variant}`),
      h('p', {}, message)
    ),
    h('div', { className: 'dialog-actions' },
      h('button', {
        className: 'btn btn-secondary',
        onclick: () => {
          modalInstance.close();
          if (onCancel) onCancel();
        },
      }, cancelLabel),
      h('button', {
        className: `btn btn-${variant === 'danger' ? 'danger' : 'primary'}`,
        onclick: () => {
          modalInstance.close();
          if (onConfirm) onConfirm();
        },
      }, confirmLabel)
    )
  );

  modalInstance = new Modal({
    title,
    contentEl: content,
    className: 'modal-confirm',
    onClose: onCancel,
  });

  modalInstance.open();
}
