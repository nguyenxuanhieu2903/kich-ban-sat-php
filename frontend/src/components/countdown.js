/**
 * Universal Countdown Component with Single Shared Interval
 * Compensates for server-time drift and changes to urgent danger color when < 15 minutes.
 */
import { h, renderIcon } from '../utils/dom.js';
import { calculateCountdown } from '../utils/format.js';

// Shared interval registry to maintain 60fps performance without interval bloat
const activeCountdowns = new Set();
let sharedTicker = null;

function ensureSharedTicker() {
  if (sharedTicker) return;
  sharedTicker = setInterval(() => {
    // If tab is hidden via Page Visibility API, we can either skip or tick lazily
    if (document.hidden) return;

    for (const item of activeCountdowns) {
      try {
        item.tick();
      } catch (err) {
        console.error('Countdown tick error:', err);
      }
    }
  }, 1000);
}

export function renderCountdown(targetDateInput, { label = 'Hạn chót', onExpire = null, showIcon = true } = {}) {
  const timeTextEl = h('span', { className: 'countdown-time' }, '—');
  const container = h(
    'div',
    { className: 'countdown-pill', 'aria-live': 'polite' },
    showIcon ? renderIcon('clock', 'countdown-icon') : null,
    label ? h('span', { className: 'countdown-label' }, `${label}:`) : null,
    timeTextEl
  );

  const tracker = {
    target: targetDateInput,
    hasExpiredHandled: false,
    tick() {
      if (!container.isConnected) {
        activeCountdowns.delete(tracker);
        return;
      }

      const res = calculateCountdown(this.target);
      timeTextEl.textContent = res.formatted;

      if (res.isExpired) {
        container.classList.add('countdown-expired');
        container.classList.remove('countdown-urgent');
        timeTextEl.textContent = 'Hết hạn';
        if (!this.hasExpiredHandled) {
          this.hasExpiredHandled = true;
          if (typeof onExpire === 'function') onExpire();
        }
      } else {
        // Less than 15 minutes (< 900 seconds) -> turn red
        if (res.totalSeconds < 900) {
          container.classList.add('countdown-urgent');
        } else {
          container.classList.remove('countdown-urgent');
        }
      }
    },
  };

  // Immediate initial calculation
  tracker.tick();
  activeCountdowns.add(tracker);
  ensureSharedTicker();

  return container;
}
