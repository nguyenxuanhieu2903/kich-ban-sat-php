/**
 * Centralized Application Configuration
 * Compatible with same-origin and cross-port local dev
 */
export const config = {
  apiBaseUrl: (typeof window !== 'undefined' ? window.__APP_CONFIG__?.apiBaseUrl : null) || (
    typeof window !== 'undefined' && window.location?.protocol?.startsWith('http')
      ? '/api/v1'
      : 'http://127.0.0.1:8000/api/v1'
  ),
  appTitle: 'Kịch Bản Sát V16',
  version: '16.0.0-full',
  defaultTimeoutMs: 15000,
};
