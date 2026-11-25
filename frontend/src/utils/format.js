export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function formatVnd(amount) {
  const n = Number(amount);
  if (!Number.isFinite(n)) return '—';
  return `${n.toLocaleString('vi-VN')}đ`;
}

export function pad(n) {
  return String(n).padStart(2, '0');
}

export function parseDate(value) {
  if (!value) return null;
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
  const text = String(value).trim();
  if (!text) return null;
  const mysql = text.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::(\d{2}))?/);
  if (mysql) {
    const d = new Date(`${mysql[1]}T${mysql[2]}:${mysql[3] || '00'}`);
    return Number.isNaN(d.getTime()) ? null : d;
  }
  const d = new Date(text);
  return Number.isNaN(d.getTime()) ? null : d;
}

export function formatDate(value) {
  const d = parseDate(value);
  if (!d) return String(value || '—');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function formatTime(value) {
  if (/^\d{2}:\d{2}/.test(String(value || ''))) return String(value).slice(0, 5);
  const d = parseDate(value);
  if (!d) return String(value || '—').slice(0, 5) || '—';
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatDateTime(value) {
  const d = parseDate(value);
  if (!d) return String(value || '—');
  return `${formatDate(d)} · ${formatTime(d)}`;
}

export function remainingLabel(value) {
  const d = parseDate(value);
  if (!d) return '';
  const t = d.getTime() - Date.now();
  if (t <= 0) return 'Đã hết hạn';
  const h = Math.floor(t / 3600000);
  const m = Math.floor((t % 3600000) / 60000);
  if (h > 48) return `${Math.floor(h / 24)} ngày ${h % 24} giờ`;
  return `${h} giờ ${m} phút`;
}

export function initials(name) {
  const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return parts.slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

export function queryString(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    search.set(key, String(value));
  });
  const qs = search.toString();
  return qs ? `?${qs}` : '';
}

export function pageQuery(name, fallback = '') {
  return new URLSearchParams(window.location.search).get(name) || fallback;
}

export function idempotencyKey() {
  if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
  return `kbs-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}
