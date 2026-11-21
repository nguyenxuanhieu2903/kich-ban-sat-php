/**
 * Accessible Toast Notification System
 * Uses aria-live="polite" for screen reader accessibility.
 * Auto-caps at 2 simultaneous toasts, deduplicates messages, and auto-dismisses in 2000ms.
 */
import { h, renderIcon } from '../utils/dom.js';

let activeToasts = new Map(); // message -> { element, timer }

function getContainer() {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = h('div', {
      className: 'toast-container',
      'aria-live': 'polite',
      'aria-atomic': 'true',
    });
    document.body.appendChild(container);
  }
  return container;
}

export function showToast(message, type = 'info', durationMs = 2000) {
  if (!message) return;
  const container = getContainer();

  // Deduplicate: If an identical toast is currently displayed, reset its timer instead of stacking
  if (activeToasts.has(message)) {
    const existing = activeToasts.get(message);
    clearTimeout(existing.timer);
    existing.timer = setTimeout(() => removeToast(existing.element, message), durationMs);
    // Subtle bounce feedback
    existing.element.classList.remove('toast-leaving');
    existing.element.style.animation = 'none';
    void existing.element.offsetWidth; // trigger reflow
    existing.element.style.animation = 'toastIn 0.2s ease forwards';
    return;
  }

  // Cap at 2 toasts max to prevent screen clutter
  while (container.children.length >= 2) {
    const oldest = container.firstElementChild;
    if (oldest) {
      for (const [msg, item] of activeToasts.entries()) {
        if (item.element === oldest) {
          activeToasts.delete(msg);
          break;
        }
      }
      oldest.remove();
    }
  }

  const iconName = type === 'success' ? 'check' : (type === 'error' ? 'alertTriangle' : 'info');

  const toastEl = h(
    'div',
    { className: `toast-item toast-${type}` },
    renderIcon(iconName, 'toast-icon'),
    h('div', { className: 'toast-body' }, message),
    h('button', {
      className: 'toast-close-btn',
      'aria-label': 'Đóng thông báo',
      onclick: () => removeToast(toastEl, message, true),
    }, '×')
  );

  container.appendChild(toastEl);

  const timer = setTimeout(() => {
    removeToast(toastEl, message);
  }, durationMs);

  activeToasts.set(message, { element: toastEl, timer });

  function removeToast(el, msgKey, immediate = false) {
    if (!el || !el.parentNode) {
      if (msgKey) activeToasts.delete(msgKey);
      return;
    }
    const item = activeToasts.get(msgKey);
    if (item) clearTimeout(item.timer);
    activeToasts.delete(msgKey);

    if (immediate) {
      el.remove();
      return;
    }

    el.classList.add('toast-leaving');
    const cleanup = () => {
      if (el.parentNode) el.remove();
    };
    el.addEventListener('animationend', cleanup, { once: true });
    setTimeout(cleanup, 200);
  }
}

/** Clear all active toasts (useful on route navigation) */
export function clearAllToasts() {
  const container = document.querySelector('.toast-container');
  if (container) {
    container.innerHTML = '';
  }
  for (const item of activeToasts.values()) {
    clearTimeout(item.timer);
  }
  activeToasts.clear();
}
