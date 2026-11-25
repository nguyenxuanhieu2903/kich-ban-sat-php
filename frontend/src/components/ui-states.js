import { escapeHtml } from '../utils/format.js';

export function loadingHtml(message = 'Đang tải dữ liệu...') {
  return `
    <div class="kbs-state kbs-state--loading" role="status">
      <div class="kbs-skeleton"></div>
      <div class="kbs-skeleton"></div>
      <p>${escapeHtml(message)}</p>
    </div>
  `;
}

export function emptyHtml(title, text) {
  return `
    <div class="kbs-state kbs-state--empty">
      <p class="kbs-state-title">${escapeHtml(title)}</p>
      <p>${escapeHtml(text)}</p>
    </div>
  `;
}

export function errorHtml(title, text, retryId = '') {
  return `
    <div class="kbs-state kbs-state--error">
      <p class="kbs-state-title">${escapeHtml(title)}</p>
      <p>${escapeHtml(text)}</p>
      ${retryId ? `<button type="button" class="kbs-btn kbs-btn--gold" id="${escapeHtml(retryId)}">Thử lại</button>` : ''}
    </div>
  `;
}

export function successHtml(title, text) {
  return `
    <div class="kbs-state kbs-state--success">
      <p class="kbs-state-title">${escapeHtml(title)}</p>
      <p>${escapeHtml(text)}</p>
    </div>
  `;
}
