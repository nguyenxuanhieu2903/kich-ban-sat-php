/**
 * Centralized Application Configuration
 * Allows runtime overrides via window.__APP_CONFIG__
 */
export const config = {
  apiBaseUrl: window.__APP_CONFIG__?.apiBaseUrl || 'http://127.0.0.1:8080/api',
  appTitle: 'Kịch Bản Sát V16',
  version: '16.0.0-foundation',
  defaultTimeoutMs: 10000,
};
