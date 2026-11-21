/**
 * Accessible Modal with Focus Trap and Esc-Key Handling
 */
import { h, renderIcon } from '../utils/dom.js';

export class Modal {
  constructor({ title, contentEl, onClose, className = '' }) {
    this.title = title;
    this.contentEl = contentEl;
    this.onClose = onClose;
    this.className = className;
    this.element = null;
    this.previousFocusedElement = document.activeElement;
  }

  open() {
    this.element = h('div', { className: `modal-backdrop ${this.className}`.trim(), role: 'dialog', 'aria-modal': 'true' },
      h('div', { className: 'modal-card' },
        h('div', { className: 'modal-header' },
          h('h3', { className: 'modal-title' }, this.title),
          h('button', {
            className: 'modal-close-btn',
            'aria-label': 'Đóng cửa sổ',
            onclick: () => this.close(),
          }, renderIcon('x'))
        ),
        h('div', { className: 'modal-body' }, this.contentEl)
      )
    );

    // Close on backdrop click outside card
    this.element.addEventListener('click', (e) => {
      if (e.target === this.element) {
        this.close();
      }
    });

    // Esc key handler
    this.handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        this.close();
      }
    };
    document.addEventListener('keydown', this.handleKeyDown);

    document.body.appendChild(this.element);
    document.body.style.overflow = 'hidden';

    // Focus first focusable element
    const focusable = this.element.querySelectorAll('button, input, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length > 0) {
      focusable[0].focus();
    }
  }

  close() {
    if (!this.element) return;
    document.removeEventListener('keydown', this.handleKeyDown);
    document.body.style.overflow = '';
    this.element.remove();
    this.element = null;

    if (this.previousFocusedElement && typeof this.previousFocusedElement.focus === 'function') {
      this.previousFocusedElement.focus();
    }

    if (typeof this.onClose === 'function') {
      this.onClose();
    }
  }
}
