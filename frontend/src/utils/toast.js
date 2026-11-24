/**
 * Lightweight Toast Notification System
 */
class ToastManager {
  constructor() {
    this.container = null;
  }

  ensureContainer() {
    if (!this.container || !document.body.contains(this.container)) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      this.container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 380px;
        pointer-events: none;
      `;
      document.body.appendChild(this.container);
    }
  }

  show(message, type = 'info', duration = 3500) {
    this.ensureContainer();

    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    toast.style.cssText = `
      background: var(--color-bg-card, #1e293b);
      color: var(--color-text-main, #f8fafc);
      padding: 12px 18px;
      border-radius: 8px;
      border: 1px solid var(--color-border, #334155);
      border-left: 5px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
      font-size: 0.9rem;
      line-height: 1.4;
      pointer-events: auto;
      transition: all 0.3s ease;
      opacity: 0;
      transform: translateY(-10px);
    `;

    toast.innerHTML = `
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
        <div class="toast-content"></div>
        <button style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; line-height: 1;">&times;</button>
      </div>
    `;

    const contentEl = toast.querySelector('.toast-content');
    if (contentEl) {
      contentEl.textContent = message;
    }

    toast.querySelector('button').onclick = () => this.dismiss(toast);

    this.container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    });

    if (duration > 0) {
      setTimeout(() => this.dismiss(toast), duration);
    }
  }

  dismiss(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-10px)';
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
  }

  success(msg) {
    this.show(msg, 'success');
  }

  error(msg) {
    this.show(msg, 'error', 4500);
  }

  info(msg) {
    this.show(msg, 'info');
  }
}

export const toast = new ToastManager();
