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
 * Only allows http://, https://, or relative / URLs.
 *
 * @param {string|null|undefined} url
 * @returns {string}
 */
export function sanitizeUrl(url) {
  if (!url || typeof url !== 'string') return '';
  const trimmed = url.trim();
  if (/^(https?:\/\/|\/|\.\/)/i.test(trimmed)) {
    return escapeHtml(trimmed);
  }
  return '';
}
