/**
 * Sanitization and XSS Prevention Utilities
 * Kịch Bản Sát V16 - Application Security
 */

/**
 * Escape HTML special characters to prevent Cross-Site Scripting (XSS).
 *
 * @param {string|null|undefined} str
 * @returns {string}
 */
export function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Validate and sanitize URL to prevent javascript: or data: injection.
 * Only allows http://, https://, or relative / URLs (not protocol-relative //).
 *
 * @param {string|null|undefined} url
 * @returns {string}
 */
export function sanitizeUrl(url) {
  if (!url || typeof url !== 'string') return '';
  const trimmed = url.trim();
  // Disallow control characters
  if (/[\x00-\x1f\x7f-\x9f]/u.test(trimmed)) return '';
  // Whitelist: http://, https://, single slash relative (/ not //), ./, tel:, mailto:
  if (/^(https?:\/\/|\/(?!\/)|\.\/|tel:|mailto:)/i.test(trimmed)) {
    return escapeHtml(trimmed);
  }
  return '';
}
