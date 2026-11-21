/**
 * Price Breakdown Component (Transparency Noir)
 * Displays base pricing, promotions, fixed 50k deposit, and remaining balance.
 */
import { h, renderIcon } from '../utils/dom.js';
import { formatCurrency } from '../utils/format.js';

export function renderPriceBreakdown(snapshot, { showPerPersonOnly = false, title = 'Chiết tính chi phí phiên chơi' } = {}) {
  if (!snapshot) return h('div', {}, 'Đang tính toán chi phí...');

  const {
    base_price_per_person,
    player_count,
    raw_total_vnd,
    applied_promotion,
    total_discount_vnd,
    final_total_vnd,
    deposit_per_person_vnd,
    total_deposit_vnd,
    remaining_vnd,
    time_slot,
    has_promotion_conflict,
    promotion_notice,
    provisional_cross_window,
  } = snapshot;

  const rows = [];

  // 1. Base Price
  rows.push(
    h('div', { className: 'breakdown-row' },
      h('span', { className: 'breakdown-label' }, `Giá vé cơ sở (${time_slot || 'Khung giờ tiêu chuẩn'}):`),
      h('span', { className: 'breakdown-value' }, `${formatCurrency(base_price_per_person)} / người`)
    )
  );

  if (!showPerPersonOnly && player_count > 1) {
    rows.push(
      h('div', { className: 'breakdown-row text-muted' },
        h('span', { className: 'breakdown-label' }, `Tổng vé ${player_count} người:`),
        h('span', { className: 'breakdown-value' }, formatCurrency(raw_total_vnd))
      )
    );
  }

  // 2. Promotion (If any)
  if (applied_promotion) {
    rows.push(
      h('div', { className: 'breakdown-row row-promo' },
        h('span', { className: 'breakdown-label' },
          renderIcon('sparkles', 'promo-icon'),
          ` ${applied_promotion.name}:`
        ),
        h('span', { className: 'breakdown-value text-emerald' }, `-${formatCurrency(total_discount_vnd)}`)
      )
    );
  }

  // Promotion Conflict Warning (Open Decision 7)
  if (has_promotion_conflict && promotion_notice) {
    rows.push(
      h('div', { className: 'promo-conflict-alert' },
        renderIcon('alertTriangle'),
        h('p', {}, promotion_notice)
      )
    );
  }

  // Provisional Cross Window Notice
  if (provisional_cross_window) {
    rows.push(
      h('div', { className: 'provisional-window-note text-muted' },
        '[PROVISIONAL] Ca chơi đi qua 2 khung giờ: tạm tính theo giờ bắt đầu.'
      )
    );
  }

  // Divider
  rows.push(h('div', { className: 'breakdown-divider' }));

  // 3. Final Total
  rows.push(
    h('div', { className: 'breakdown-row row-total' },
      h('span', { className: 'breakdown-label' }, showPerPersonOnly ? 'Tổng phần của bạn:' : 'Tổng chi phí cả phòng:'),
      h('span', { className: 'breakdown-value highlight-gold' },
        formatCurrency(showPerPersonOnly ? (final_total_vnd / (player_count || 1)) : final_total_vnd)
      )
    )
  );

  // 4. Fixed Deposit 50.000 VNĐ
  const myDeposit = deposit_per_person_vnd || 50000;
  rows.push(
    h('div', { className: 'breakdown-row row-deposit' },
      h('span', { className: 'breakdown-label' },
        'Tiền cọc cố định giữ chỗ:',
        h('span', { className: 'badge-fixed' }, '50.000đ/người')
      ),
      h('span', { className: 'breakdown-value text-amber' },
        formatCurrency(showPerPersonOnly ? myDeposit : total_deposit_vnd)
      )
    )
  );

  // 5. Remaining Balance
  const myRemaining = showPerPersonOnly ? (remaining_vnd / (player_count || 1)) : remaining_vnd;
  rows.push(
    h('div', { className: 'breakdown-row row-remaining' },
      h('span', { className: 'breakdown-label text-muted' }, 'Khoản còn lại (thu tại quán hoặc online):'),
      h('span', { className: 'breakdown-value' }, formatCurrency(myRemaining))
    )
  );

  return h('div', { className: 'price-breakdown-card' },
    title ? h('h4', { className: 'breakdown-title' }, title) : null,
    h('div', { className: 'breakdown-rows' }, ...rows)
  );
}
