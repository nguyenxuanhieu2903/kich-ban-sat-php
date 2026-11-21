/**
 * Player Profile Page (#/player/profile)
 * Detective Credentials & Read-Only Student Verification Status.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { auth } from '../../utils/auth.js';

export class PlayerProfilePage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-player-profile container', style: 'max-width: 800px;' });

    const user = auth.getUser();
    if (!user) {
      this.router.navigate('/login');
      return;
    }

    // 1. Editorial Header
    const header = h('div', { className: 'player-dossier-hero flex-between' },
      h('div', {},
        h('span', { className: 'dossier-badge-pill' }, renderIcon('shield'), ' HỒ SƠ CÁ NHÂN'),
        h('h1', { className: 'player-desk-title' }, 'Hồ Sơ & Danh Tính Thám Tử'),
        h('p', { className: 'text-muted' },
          'Thông tin định danh và chứng chỉ xác thực ưu đãi Học sinh - Sinh viên.'
        )
      ),
      h('a', { className: 'btn btn-secondary', href: '#/player/dashboard' },
        renderIcon('chevronLeft'), ' Về Bàn Làm Việc'
      )
    );
    container.appendChild(header);

    // 2. Detective Credentials Card
    const card = h('div', {
      style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2.5rem; box-shadow: var(--shadow-md);'
    },
      h('div', { style: 'display: flex; gap: 2rem; align-items: center; border-bottom: 1px solid var(--color-noir-divider); padding-bottom: 2rem; margin-bottom: 2rem;' },
        h('div', {
          style: 'width: 72px; height: 72px; border-radius: 50%; background: var(--color-accent-crimson-subtle); border: 2px solid var(--color-accent-gold); display: flex; align-items: center; justify-content: center; color: var(--color-accent-gold); flex-shrink: 0;'
        }, renderIcon('shield')),
        h('div', {},
          h('h2', { style: 'font-size: 1.8rem; color: var(--color-text-primary); margin-bottom: 0.25rem;' }, user.name),
          h('p', { className: 'text-muted font-mono', style: 'font-size: 0.85rem; margin: 0;' },
            `ĐỊNH DANH HỆ THỐNG: USER-V16-${String(user.id).padStart(4, '0')} • VAI TRÒ: NGƯỜI CHƠI (PLAYER)`
          )
        )
      ),

      // Credentials Grid
      h('div', { style: 'display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem; margin-bottom: 2rem;' },
        h('div', {},
          h('span', { className: 'metric-strip-label', style: 'display: block; margin-bottom: 0.35rem;' }, 'Địa chỉ Email:'),
          h('strong', { className: 'font-mono text-light' }, user.email)
        ),
        h('div', {},
          h('span', { className: 'metric-strip-label', style: 'display: block; margin-bottom: 0.35rem;' }, 'Số điện thoại:'),
          h('strong', { className: 'font-mono text-light' }, user.phone || '0901234567')
        ),
        h('div', {},
          h('span', { className: 'metric-strip-label', style: 'display: block; margin-bottom: 0.35rem;' }, 'Quyền hạn phân hệ:'),
          h('span', { className: 'badge-role-pill' }, 'Người chơi (PLAYER)')
        ),
        h('div', {},
          h('span', { className: 'metric-strip-label', style: 'display: block; margin-bottom: 0.35rem;' }, 'Ngày gia nhập salon:'),
          h('span', { className: 'font-mono text-muted' }, 'Tháng 11, 2026')
        )
      ),

      // Student Verification Status (Read-only per V15/V16 rules)
      h('div', { style: 'border-top: 1px solid var(--color-noir-divider); padding-top: 1.75rem;' },
        h('span', { className: 'metric-strip-label', style: 'display: block; margin-bottom: 0.5rem;' }, 'Chứng chỉ Học sinh - Sinh viên (HSSV):'),
        user.student_verified
          ? h('div', { style: 'background: rgba(63, 168, 136, 0.1); border: 1px solid var(--color-status-emerald); padding: 1.25rem; border-radius: var(--radius-xs);' },
              h('div', { className: 'text-emerald', style: 'font-weight: 700; font-size: 1.1rem; margin-bottom: 0.35rem;' },
                '✓ ĐÃ XÁC MINH DANH TÍNH HỌC SINH - SINH VIÊN'
              ),
              h('p', { className: 'text-muted', style: 'font-size: 0.88rem; line-height: 1.6; margin: 0;' },
                `Mã thẻ HSSV: `, h('strong', { className: 'text-light font-mono' }, user.student_card_no || 'SV-2026-8891'),
                ' • Tự động hưởng ưu đãi giảm 20.000 VNĐ/người từ Thứ 2 đến Thứ 5 (09:00–19:00). Không cộng dồn với ưu đãi Thứ 3.'
              )
            )
          : h('div', { style: 'background: var(--color-bg-surface-2); border: 1px solid var(--color-border-subtle); padding: 1.25rem; border-radius: var(--radius-xs);' },
              h('div', { className: 'text-muted', style: 'font-weight: 600; font-size: 1rem; margin-bottom: 0.35rem;' },
                'CHƯA XÁC MINH THẺ HSSV'
              ),
              h('p', { className: 'text-muted', style: 'font-size: 0.85rem; line-height: 1.6; margin: 0;' },
                'Quy định bảo mật: Người chơi không tự tick chọn "tôi là HSSV" trên web. Vui lòng mang thẻ HSSV hợp lệ tới quầy quán để Quản trị viên đối chiếu và kích hoạt ưu đãi trên tài khoản của bạn.'
              )
            )
      )
    );

    container.appendChild(card);
    this.mountPoint.appendChild(container);
  }
}
