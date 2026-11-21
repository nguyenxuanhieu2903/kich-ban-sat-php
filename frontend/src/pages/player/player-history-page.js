/**
 * Player History Page (#/player/history)
 * Detective Archive & Financial Deposit Ledger: Past cases, deposit receipts (50.000 VNĐ / turn), and refund requests.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime, formatCurrency } from '../../utils/format.js';

export class PlayerHistoryPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-player-history container' });

    const user = auth.getUser();
    if (!user) {
      this.router.navigate('/login');
      return;
    }

    // 1. Editorial Header
    const header = h('div', { className: 'player-dossier-hero flex-between' },
      h('div', {},
        h('span', { className: 'dossier-badge-pill' }, renderIcon('clock'), ' SỔ LƯU TRỮ VỤ ÁN'),
        h('h1', { className: 'player-desk-title' }, 'Lịch Sử Tham Gia & Nhật Ký Ký Thác'),
        h('p', { className: 'text-muted' },
          'Theo dõi toàn bộ các phiên chơi đã tham gia, hóa đơn cọc cố định 50.000 VNĐ và tình trạng hoàn tiền.'
        )
      ),
      h('a', { className: 'btn btn-secondary', href: '#/player/dashboard' },
        renderIcon('chevronLeft'), ' Về Bàn Làm Việc'
      )
    );
    container.appendChild(header);

    const res = await apiService.getPublicRooms();
    const myRooms = res.data.filter((r) =>
      r.participants.some((p) => p.user_id === user.id)
    );

    // 2. Section 1: Past Cases Table
    const roomsSection = h('div', { style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2rem; margin-bottom: 2.5rem; box-shadow: var(--shadow-sm);' },
      h('div', { className: 'flex-between mb-3' },
        h('h3', { className: 'charter-title', style: 'font-size: 1.4rem;' }, 'Nhật Ký Các Phiên Chơi Jubensha'),
        h('span', { className: 'font-mono text-muted', style: 'font-size: 0.8rem;' }, `TỔNG CỘNG: ${myRooms.length} VỤ ÁN`)
      ),
      myRooms.length === 0
        ? h('p', { className: 'text-muted text-center', style: 'padding: 2rem;' }, 'Chưa có dữ liệu vụ án nào trong hồ sơ của bạn.')
        : h('div', { className: 'table-scroll-wrapper' },
            h('table', { className: 'ui-data-table' },
              h('thead', {},
                h('tr', {},
                  h('th', {}, 'Mã Vụ Án'),
                  h('th', {}, 'Kịch Bản Nhập Vai'),
                  h('th', {}, 'Giờ Bắt Đầu'),
                  h('th', {}, 'Trạng Thái Phòng'),
                  h('th', {}, 'Trạng Thái Ghế Của Bạn'),
                  h('th', { style: 'text-align: right;' }, 'Hồ Sơ Chi Tiết')
                )
              ),
              h('tbody', {},
                ...myRooms.map((room) => {
                  const part = room.participants.find((p) => p.user_id === user.id);
                  return h('tr', {},
                    h('td', { className: 'font-mono text-gold' }, room.code),
                    h('td', { style: 'font-family: var(--font-display); font-size: 1.05rem; font-weight: 600;' },
                      room.game_details?.title || 'Kịch bản Jubensha'
                    ),
                    h('td', { className: 'text-muted font-mono', style: 'font-size: 0.85rem;' },
                      formatDateTime(room.session_start_time)
                    ),
                    h('td', {}, renderStatusBadge(room.status)),
                    h('td', {}, renderStatusBadge(part?.status || 'UNKNOWN')),
                    h('td', { style: 'text-align: right;' },
                      h('a', { className: 'btn btn-sm btn-secondary', href: `#/player/rooms/${room.id}` }, 'Vào Phòng')
                    )
                  );
                })
              )
            )
          )
    );
    container.appendChild(roomsSection);

    // 3. Section 2: Deposit & Refund Ledger
    const depositSection = h('div', { style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2rem; box-shadow: var(--shadow-sm);' },
      h('div', { className: 'flex-between mb-3' },
        h('h3', { className: 'charter-title', style: 'font-size: 1.4rem;' }, 'Sổ Cái Ký Thác (50.000 VNĐ / Lượt) & Hoàn Tiền'),
        h('span', { className: 'font-mono text-gold', style: 'font-size: 0.8rem;' }, 'V16 FREEZE COMPLIANT')
      ),
      h('div', { className: 'table-scroll-wrapper' },
        h('table', { className: 'ui-data-table' },
          h('thead', {},
            h('tr', {},
              h('th', {}, 'Mã Giao Dịch'),
              h('th', {}, 'Vụ Án Liên Quan'),
              h('th', {}, 'Loại Khoản Thu'),
              h('th', {}, 'Định Mức'),
              h('th', {}, 'Tình Trạng'),
              h('th', {}, 'Ghi Chú Nghiệp Vụ')
            )
          ),
          h('tbody', {},
            h('tr', {},
              h('td', { className: 'font-mono text-muted' }, 'MOCK-TX-84920'),
              h('td', { className: 'font-mono text-gold' }, 'PHONG-005'),
              h('td', {}, 'Ký thác giữ ghế thám tử (1 người)'),
              h('td', { className: 'highlight-gold font-mono' }, '50.000 ₫'),
              h('td', {}, renderStatusBadge('PAID', 'Đã ghi nhận')),
              h('td', { className: 'text-muted', style: 'font-size: 0.85rem;' }, 'Khấu trừ vào hóa đơn khi đến quán chơi')
            ),
            h('tr', {},
              h('td', { className: 'font-mono text-muted' }, 'MOCK-TX-84921'),
              h('td', { className: 'font-mono text-gold' }, 'PHONG-002'),
              h('td', {}, 'Ký thác giữ ghế thám tử (1 người)'),
              h('td', { className: 'highlight-gold font-mono' }, '50.000 ₫'),
              h('td', {}, renderStatusBadge('PAID', 'Đã ghi nhận')),
              h('td', { className: 'text-muted', style: 'font-size: 0.85rem;' }, 'Khấu trừ vào hóa đơn khi đến quán chơi')
            )
          )
        )
      )
    );
    container.appendChild(depositSection);

    this.mountPoint.appendChild(container);
  }
}
