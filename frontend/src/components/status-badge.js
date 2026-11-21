/**
 * Elegant Wax-Seal Style Status Badge Component
 */
import { h } from '../utils/dom.js';
import { getStatusMeta } from '../utils/status.js';

export function renderStatusBadge(status, customLabel = null) {
  const meta = getStatusMeta(status);
  const label = customLabel || meta.label;

  return h(
    'span',
    {
      className: `status-badge badge-${meta.color} badge-variant-${meta.variant}`,
      dataset: { status },
      title: `Trạng thái: ${label} (${status || 'UNKNOWN'})`,
    },
    h('span', { className: 'badge-dot' }),
    h('span', { className: 'badge-text' }, label)
  );
}
