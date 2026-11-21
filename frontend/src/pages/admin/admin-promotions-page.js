/**
 * Admin Promotions Management Page (#/admin/promotions)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { auth } from '../../utils/auth.js';
import { formatCurrency } from '../../utils/format.js';

export class AdminPromotionsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-promos container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('shield', 'text-gold'),
            'POLICY ENGINE // PROMOTION CONFLICT DISCLOSURE'
          ),
          h('h1', { className: 'page-title' }, 'Chính Sách Ưu Đãi & Minh Bạch Quyết Định #7'),
          h('p', { className: 'page-subtitle text-muted' },
            'Quy chuẩn chiết khấu hệ thống V16 Freeze: Quản lý ưu đãi Thứ 3, giảm giá HSSV và cơ chế cảnh báo xung đột không cộng dồn.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    // Mandatory Open Decision #7 Conflict Disclosure Banner
    const ruleBox = h('div', { className: 'decision-7-banner' },
      renderIcon('shield', 'text-gold', { width: 32, height: 32 }),
      h('div', {},
        h('h4', { className: 'highlight-gold mb-1' }, 'OPEN DECISION #7 — CẢNH BÁO BẢO LƯU THỨ TỰ ƯU TIÊN'),
        h('p', { className: 'text-secondary mb-1', style: { fontSize: '0.88rem', lineHeight: '1.6' } },
          'Quy tắc V16 Freeze bắt buộc: ',
          h('strong', { className: 'text-light' }, 'HAI ƯU ĐÃI TUYỆT ĐỐI KHÔNG CỘNG DỒN'),
          '. Khi một phiên chơi thỏa mãn đồng thời cả 2 điều kiện (diễn ra vào Thứ Ba và người chơi có xác minh HSSV), hệ thống ',
          h('strong', { className: 'text-gold' }, 'giữ nguyên biểu giá cơ sở'),
          ' và hiển thị cảnh báo chưa chốt thứ tự ưu tiên (Open Decision #7). Tuyệt đối không tự ý quyết định hay áp dụng tự động bất kỳ ưu đãi nào.'
        ),
        h('span', { className: 'buffer-pill warning font-mono' }, 'STATUS: FROZEN // REQUIRES BOARD RESOLUTION')
      )
    );
    container.appendChild(ruleBox);

    const promos = [
      {
        id: 'promo_tuesday',
        code: 'PROMO-TUE-30',
        name: 'Ưu Đãi Thứ 3 Vui Vẻ',
        type: 'Giảm theo tỷ lệ phần trăm (30%)',
        value: '-30% giá vé niêm yết',
        days: 'Thứ Ba hàng tuần (09:00 – 19:00)',
        advance: 'Đặt trước ít nhất 1 ngày (≥ 24 giờ)',
        eligibility: 'Áp dụng tự động cho toàn bộ thám tử trong phòng',
        status: 'ACTIVE',
      },
      {
        id: 'promo_student',
        code: 'PROMO-HSSV-20K',
        name: 'Ưu Đãi Học Sinh - Sinh Viên (HSSV)',
        type: 'Giảm trừ cố định theo cá nhân',
        value: '-20.000 VNĐ / người',
        days: 'Thứ Hai đến Thứ Năm (09:00 – 19:00)',
        advance: 'Không giới hạn thời gian đặt trước',
        eligibility: 'Yêu cầu tài khoản có trạng thái HSSV VERIFIED bởi Admin',
        status: 'ACTIVE',
      },
    ];

    const cardsGrid = h('div', { className: 'grid-col-2 gap-4' },
      ...promos.map((p) =>
        h('div', { className: 'tactical-card card-dark', style: { border: '1px solid var(--color-border-subtle)' } },
          h('div', { className: 'flex-between mb-2' },
            h('span', { className: 'highlight-gold font-mono' }, `[${p.code}]`),
            h('span', { className: 'buffer-pill safe' }, p.status)
          ),
          h('h3', { className: 'card-title text-light mb-2' }, p.name),
          h('div', { className: 'dossier-specs-list mt-3' },
            h('div', { className: 'flex-between py-1', style: { borderBottom: '1px solid var(--color-noir-divider)' } },
              h('span', { className: 'text-muted' }, 'Mức giảm:'),
              h('strong', { className: 'text-gold font-mono' }, p.value)
            ),
            h('div', { className: 'flex-between py-1', style: { borderBottom: '1px solid var(--color-noir-divider)' } },
              h('span', { className: 'text-muted' }, 'Khung thời gian:'),
              h('span', { className: 'text-light' }, p.days)
            ),
            h('div', { className: 'flex-between py-1', style: { borderBottom: '1px solid var(--color-noir-divider)' } },
              h('span', { className: 'text-muted' }, 'Quy định đặt trước:'),
              h('span', { className: 'text-light' }, p.advance)
            ),
            h('div', { className: 'flex-between py-1' },
              h('span', { className: 'text-muted' }, 'Điều kiện:'),
              h('span', { className: 'text-light', style: { textAlign: 'right', maxWidth: '240px' } }, p.eligibility)
            )
          )
        )
      )
    );
    container.appendChild(cardsGrid);

    this.mountPoint.appendChild(container);
  }
}
