/**
 * Mandatory Reason Dialog
 * Enforces non-empty reason input before executing critical Admin and DM actions.
 */
import { h } from '../utils/dom.js';
import { Modal } from './modal.js';
import { isReasonRequired } from '../utils/rules.js';

export function showReasonDialog({
  title = 'Nhập lý do thực hiện',
  actionKey,
  placeholder = 'Nhập lý do cụ thể (bắt buộc, không được để trống)...',
  confirmLabel = 'Xác nhận thực hiện',
  isProvisional = false,
  onSubmit,
  onCancel,
}) {
  let modalInstance = null;
  const errorEl = h('div', { className: 'form-error-msg', style: { display: 'none', color: 'var(--color-status-danger)', fontSize: '0.85rem', marginTop: '0.5rem' } });
  const textarea = h('textarea', {
    className: 'form-control',
    rows: 4,
    placeholder,
    style: { width: '100%', resize: 'vertical' },
  });

  const isOfficiallyRequired = isReasonRequired(actionKey);
  const showProvisionalBadge = isProvisional || !isOfficiallyRequired;

  const content = h('div', { className: 'reason-dialog-content' },
    showProvisionalBadge
      ? h('div', { className: 'badge-provisional-note' },
          'Lưu ý: Hành động này mặc định yêu cầu lý do bảo toàn audit [PROVISIONAL].'
        )
      : null,
    h('label', { className: 'form-label', style: { display: 'block', marginBottom: '0.35rem', color: 'var(--color-text-secondary)', fontSize: '0.9rem' } },
      'Lý do nghiệp vụ (bắt buộc):'
    ),
    textarea,
    errorEl,
    h('div', { className: 'dialog-actions', style: { display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.25rem' } },
      h('button', {
        type: 'button',
        className: 'btn btn-secondary',
        onclick: () => {
          modalInstance.close();
          if (onCancel) onCancel();
        },
      }, 'Hủy bỏ'),
      h('button', {
        type: 'button',
        className: 'btn btn-danger',
        onclick: () => {
          const val = textarea.value.trim();
          if (!val || val.length < 5) {
            errorEl.textContent = 'Vui lòng nhập lý do cụ thể (tối thiểu 5 ký tự).';
            errorEl.style.display = 'block';
            textarea.focus();
            return;
          }
          modalInstance.close();
          if (onSubmit) onSubmit(val);
        },
      }, confirmLabel)
    )
  );

  modalInstance = new Modal({
    title,
    contentEl: content,
    className: 'modal-reason',
    onClose: onCancel,
  });

  modalInstance.open();
  setTimeout(() => textarea.focus(), 50);
}
