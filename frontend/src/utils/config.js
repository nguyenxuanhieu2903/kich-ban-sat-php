/**
 * Centralized Application Configuration
 * Allows runtime overrides via window.__APP_CONFIG__
 */
export const config = {
  // 'demo' (mock shared-store) or 'live' (direct HTTP calls to backend)
  DATA_MODE: window.__APP_CONFIG__?.DATA_MODE || 'demo',

  apiBaseUrl: window.__APP_CONFIG__?.apiBaseUrl || 'http://127.0.0.1:8080/api',
  appTitle: 'Kịch Bản Sát — Web-based Booking Platform',
  version: '16.0.0-mvp',
  defaultTimeoutMs: 10000,

  // Polling intervals (in ms)
  POLLING_INTERVAL_MS: 30000,
  POLLING_ENABLED: true,

  // Flag to fill demo placeholder attributes for unverified games
  DEMO_FILL_UNVERIFIED: true,
};
