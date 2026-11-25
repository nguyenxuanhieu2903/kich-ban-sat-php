/**
 * ============================================================================
 * KỊCH BẢN SÁT V16 — MODERN DIALOG & DRAWER SERVICE
 * Thay thế hoàn toàn window.alert(), window.prompt(), window.confirm()
 * Hỗ trợ: Accessible Slide-over Drawer, Custom Prompt, Custom Confirm & Alert
 * ============================================================================
 */

import { escapeHtml } from './sanitize.js';

class DialogService {
  /**
   * Mở Slide-over Drawer từ cạnh phải màn hình
   * @param {Object} options
   * @param {string} options.title - Tiêu đề Drawer
   * @param {string} options.badge - Nhãn phụ (VD: '#HD-102')
   * @param {string} options.htmlContent - Nội dung HTML an toàn bên trong
   * @param {string} [options.footerHtml] - Các nút bấm dưới đáy
   * @param {number} [options.width=540] - Độ rộng drawer (px)
   */
  static drawer({ title, badge = '', htmlContent, footerHtml = '', width = 540 }) {
    this.closeDrawer();

    const backdrop = document.createElement('div');
    backdrop.id = 'kbs-drawer-backdrop';
    backdrop.className = 'kbs-dialog-backdrop';
    backdrop.style.cssText = `
      position: fixed; inset: 0; background: rgba(0, 0, 0, 0.72);
      backdrop-filter: blur(4px); z-index: 1000; display: flex;
      justify-content: flex-end; opacity: 0; transition: opacity 0.25s ease;
    `;

    const drawer = document.createElement('aside');
    drawer.id = 'kbs-drawer-panel';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-label', title);
    drawer.style.cssText = `
      width: min(${width}px, 92vw); height: 100vh;
      background: var(--kbs-bg-surface, #13151F);
      border-left: 1px solid var(--kbs-admin-primary-border, rgba(99, 102, 241, 0.3));
      box-shadow: var(--kbs-shadow-drawer, -4px 0 30px rgba(0,0,0,0.7));
      display: flex; flex-direction: column; justify-content: space-between;
      transform: translateX(100%); transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
      color: var(--kbs-text-primary, #F8FAFC);
    `;

    drawer.innerHTML = `
      <div style="padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2);">
        <div>
          ${badge ? `<span class="badge" style="background: rgba(99,102,241,0.18); color: #C7D2FE; margin-bottom: 6px; font-family: var(--font-mono);">${escapeHtml(badge)}</span>` : ''}
          <h3 style="margin: 0; font-family: var(--font-display); font-size: 1.35rem; color: #FFFFFF;">${escapeHtml(title)}</h3>
        </div>
        <button id="btn-close-drawer" class="btn btn-ghost btn-sm" aria-label="Đóng bảng chi tiết" style="font-size: 1.4rem; padding: 4px 10px; line-height: 1; color: var(--kbs-text-muted);">&times;</button>
      </div>

      <div style="padding: 24px; overflow-y: auto; flex: 1;">
        ${htmlContent}
      </div>

      ${footerHtml ? `
        <div style="padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.25); display: flex; justify-content: flex-end; gap: 10px;">
          ${footerHtml}
        </div>
      ` : ''}
    `;

    backdrop.appendChild(drawer);
    document.body.appendChild(backdrop);

    // Kích hoạt animation xuất hiện
    requestAnimationFrame(() => {
      backdrop.style.opacity = '1';
      drawer.style.transform = 'translateX(0)';
    });

    const closeHandler = () => {
      backdrop.style.opacity = '0';
      drawer.style.transform = 'translateX(100%)';
      setTimeout(() => backdrop.remove(), 280);
    };

    drawer.querySelector('#btn-close-drawer').onclick = closeHandler;
    backdrop.onclick = (e) => {
      if (e.target === backdrop) closeHandler();
    };

    const escListener = (e) => {
      if (e.key === 'Escape') {
        closeHandler();
        document.removeEventListener('keydown', escListener);
      }
    };
    document.addEventListener('keydown', escListener);
  }

  static closeDrawer() {
    const existing = document.getElementById('kbs-drawer-backdrop');
    if (existing) existing.remove();
  }

  /**
   * Thay thế window.prompt() bằng Modal nhập liệu chuyên nghiệp
   * @param {Object} options
   * @param {string} options.title - Tiêu đề Modal
   * @param {string} options.message - Hướng dẫn nhập
   * @param {string} [options.defaultValue=''] - Giá trị mặc định
   * @param {string} [options.placeholder=''] - Gợi ý
   * @param {string} [options.confirmText='Xác nhận'] - Nút xác nhận
   * @returns {Promise<string|null>} - Chuỗi người dùng nhập, hoặc null nếu hủy
   */
  static prompt({ title, message, defaultValue = '', placeholder = '', confirmText = 'Xác nhận' }) {
    return new Promise((resolve) => {
      const dialog = document.createElement('dialog');
      dialog.className = 'kbs-custom-dialog';
      dialog.style.cssText = `
        background: var(--kbs-bg-card, #1A1D27); color: var(--kbs-text-primary, #F8FAFC);
        border: 1px solid var(--kbs-admin-primary-border, rgba(99, 102, 241, 0.3));
        border-radius: 12px; padding: 24px; max-width: 480px; width: 90vw; margin: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); box-sizing: border-box;
      `;

      dialog.innerHTML = `
        <h3 style="margin: 0 0 10px 0; font-family: var(--font-display); font-size: 1.25rem; color: #FFFFFF;">${escapeHtml(title)}</h3>
        <p style="margin: 0 0 16px 0; font-size: 0.9rem; color: var(--kbs-text-secondary); line-height: 1.5;">${escapeHtml(message)}</p>
        <textarea id="prompt-input" rows="3" style="width: 100%; box-sizing: border-box; padding: 10px 12px; background: rgba(0,0,0,0.3); border: 1px solid var(--color-border); border-radius: 6px; color: #FFF; font-family: inherit; font-size: 0.9rem; margin-bottom: 20px; outline: none;" placeholder="${escapeHtml(placeholder)}">${escapeHtml(defaultValue)}</textarea>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button id="btn-prompt-cancel" class="btn btn-ghost btn-sm" style="border: 1px solid rgba(255,255,255,0.15); color: var(--kbs-text-muted);">Hủy</button>
          <button id="btn-prompt-submit" class="btn btn-sm" style="background: var(--kbs-admin-primary, #6366F1); color: #FFF; border: none; font-weight: 600;">${escapeHtml(confirmText)}</button>
        </div>
      `;

      document.body.appendChild(dialog);
      dialog.showModal();

      const input = dialog.querySelector('#prompt-input');
      input.focus();
      input.select();

      const cleanup = (val) => {
        dialog.close();
        dialog.remove();
        resolve(val);
      };

      dialog.querySelector('#btn-prompt-cancel').onclick = () => cleanup(null);
      dialog.querySelector('#btn-prompt-submit').onclick = () => cleanup(input.value.trim());
      dialog.addEventListener('cancel', () => cleanup(null));
    });
  }

  /**
   * Thay thế window.alert() bằng Modal thông báo trang nhã
   * @param {Object} options
   */
  static alert({ title, message, icon = 'ℹ️' }) {
    return new Promise((resolve) => {
      const dialog = document.createElement('dialog');
      dialog.className = 'kbs-custom-dialog';
      dialog.style.cssText = `
        background: var(--kbs-bg-card, #1A1D27); color: var(--kbs-text-primary, #F8FAFC);
        border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 24px;
        max-width: 440px; width: 90vw; margin: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.7);
      `;

      dialog.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
          <span style="font-size: 1.8rem;">${icon}</span>
          <div>
            <h3 style="margin: 0 0 6px 0; font-family: var(--font-display); font-size: 1.25rem;">${escapeHtml(title)}</h3>
            <div style="font-size: 0.9rem; color: var(--kbs-text-secondary); line-height: 1.5; white-space: pre-wrap;">${escapeHtml(message)}</div>
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end;">
          <button id="btn-alert-ok" class="btn btn-sm" style="background: var(--kbs-gold, #D4AF37); color: #0B0909; border: none; font-weight: 700; padding: 7px 20px;">Đã hiểu</button>
        </div>
      `;

      document.body.appendChild(dialog);
      dialog.showModal();

      dialog.querySelector('#btn-alert-ok').onclick = () => {
        dialog.close();
        dialog.remove();
        resolve();
      };
      dialog.addEventListener('cancel', () => {
        dialog.remove();
        resolve();
      });
    });
  }
}

export { DialogService };
