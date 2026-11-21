/**
 * Admin Games Management Page (#/admin/games)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { DataTable } from '../../components/data-table.js';
import { formatCurrency } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';

export class AdminGamesPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.editingGame = null;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-games container' });

    const user = auth.getUser();
    if (!user || user.role !== 'ADMIN') {
      this.router.navigate('/403');
      return;
    }

    container.appendChild(
      h('div', { className: 'page-header flex-between' },
        h('div', {},
          h('div', { className: 'dossier-badge-pill' },
            renderIcon('book', 'text-gold'),
            'CATALOG REGISTRY // PRICING & SPECIFICATIONS'
          ),
          h('h1', { className: 'page-title' }, 'Danh Mục Kịch Bản & Cấu Hình Bảng Giá'),
          h('p', { className: 'page-subtitle text-muted' },
            'Quản lý 42 kịch bản Jubensha, thiết lập giá vé 2 khung giờ (Ngày/Tối), thời lượng và kiểm định đối chiếu menu gốc.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    // Price Snapshot Guarantee Banner
    container.appendChild(
      h('div', { className: 'snapshot-guarantee-banner' },
        renderIcon('shield', 'text-emerald mr-2'),
        h('strong', {}, 'CAM KẾT BẤT BIẾN PRICING SNAPSHOT: '),
        'Mọi thay đổi biểu phí hoặc thời lượng tại danh mục chỉ áp dụng cho các lượt đặt phòng mới về sau. Các vụ án đã đặt hoặc đang hoạt động được bảo toàn nguyên vẹn snapshot giá gốc (`pricing_snapshot`) trong hệ thống.'
      )
    );

    const games = sharedStore.data.games || [];

    const columns = [
      { key: 'code', label: 'Mã', sortable: true, render: (val) => h('strong', { className: 'highlight-gold font-mono' }, val) },
      { key: 'title', label: 'Tên kịch bản', sortable: true, render: (val) => h('strong', { className: 'text-light' }, val) },
      { key: 'genre', label: 'Thể loại', sortable: true },
      {
        key: 'duration_minutes',
        label: 'Thời lượng',
        sortable: true,
        render: (val) => `${val || 240} phút`,
      },
      {
        key: 'is_age_restricted',
        label: 'Độ tuổi',
        sortable: true,
        render: (val) => val ? h('span', { className: 'game-18-badge' }, '18+') : h('span', { className: 'text-muted' }, 'Thường'),
      },
      {
        key: 'verified',
        label: 'Menu gốc',
        sortable: true,
        render: (val) => val ? h('span', { className: 'text-emerald' }, '✓ Đã kiểm') : h('span', { className: 'badge-unverified' }, 'Chờ đối chiếu'),
      },
      {
        key: 'price_day_vnd',
        label: 'Giá ngày (09:00-19:59)',
        sortable: true,
        render: (val) => formatCurrency(val),
      },
      {
        key: 'price_night_vnd',
        label: 'Giá tối (từ 20:00)',
        sortable: true,
        render: (val) => h('strong', { className: 'highlight-gold font-mono' }, formatCurrency(val)),
      },
      {
        key: 'actions',
        label: 'Thao tác',
        render: (_, row) =>
          h('button', {
            className: 'btn btn-sm btn-secondary text-gold',
            onclick: () => this.openEditModal(row, user),
          }, renderIcon('edit'), ' Sửa biểu phí'),
      },
    ];

    const table = new DataTable({
      columns,
      data: games,
      pageSize: 10,
      searchPlaceholder: 'Tìm kiếm kịch bản theo tên, mã, thể loại...',
    });

    const card = h('div', { className: 'tactical-card mt-3' });
    card.appendChild(table.render());
    container.appendChild(card);

    // Edit Modal if open
    if (this.editingGame) {
      container.appendChild(this.renderEditModal(user));
    }

    this.mountPoint.appendChild(container);
  }

  openEditModal(game) {
    this.editingGame = { ...game };
    this.render();
  }

  closeEditModal() {
    this.editingGame = null;
    this.render();
  }

  renderEditModal(adminActor) {
    const game = this.editingGame;

    let dayPrice = game.price_day_vnd;
    let nightPrice = game.price_night_vnd;
    let duration = game.duration_minutes || 240;
    let isVerified = Boolean(game.verified);

    const backdrop = h('div', { className: 'tactical-modal-backdrop' },
      h('div', { className: 'tactical-modal-dialog' },
        h('div', { className: 'flex-between mb-3' },
          h('h3', { className: 'card-title highlight-gold mb-0' }, `Cấu Hình Bảng Giá: ${game.title}`),
          h('button', { className: 'btn btn-sm btn-secondary', onclick: () => this.closeEditModal() }, '✕')
        ),
        h('p', { className: 'text-muted mb-3', style: { fontSize: '0.85rem' } },
          `Mã kịch bản: `,
          h('strong', { className: 'text-light font-mono' }, game.code),
          ` • Thể loại: ${game.genre}`
        ),
        h('div', { className: 'form-group mb-3' },
          h('label', { className: 'form-label text-muted' }, 'Giá Vé Ca Ngày (VNĐ / người) [09:00 - 19:59]:'),
          h('input', {
            type: 'number',
            step: '5000',
            className: 'form-control',
            value: String(dayPrice),
            oninput: (e) => { dayPrice = Number(e.target.value); },
          })
        ),
        h('div', { className: 'form-group mb-3' },
          h('label', { className: 'form-label text-muted' }, 'Giá Vé Ca Tối (VNĐ / người) [Từ 20:00]:'),
          h('input', {
            type: 'number',
            step: '5000',
            className: 'form-control',
            value: String(nightPrice),
            oninput: (e) => { nightPrice = Number(e.target.value); },
          })
        ),
        h('div', { className: 'form-group mb-3' },
          h('label', { className: 'form-label text-muted' }, 'Thời Lượng Phiên Chơi (Phút):'),
          h('input', {
            type: 'number',
            step: '30',
            className: 'form-control',
            value: String(duration),
            oninput: (e) => { duration = Number(e.target.value); },
          })
        ),
        h('div', { className: 'form-group mb-4 flex-align-center gap-2' },
          h('input', {
            type: 'checkbox',
            id: 'chk_verified',
            checked: isVerified,
            onchange: (e) => { isVerified = e.target.checked; },
          }),
          h('label', { htmlFor: 'chk_verified', className: 'text-light mb-0', style: { fontSize: '0.9rem', cursor: 'pointer' } },
            'Đã đối chiếu khớp menu quán (Verified)'
          )
        ),
        h('div', { className: 'flex-between' },
          h('button', { className: 'btn btn-secondary btn-sm', onclick: () => this.closeEditModal() }, 'Hủy bỏ'),
          h('button', {
            className: 'btn btn-primary btn-sm',
            onclick: () => {
              try {
                sharedStore.adminUpdateGamePricing(game.id, {
                  price_day_vnd: dayPrice,
                  price_night_vnd: nightPrice,
                  duration_minutes: duration,
                  verified: isVerified,
                }, adminActor);
                showToast(`Đã cập nhật biểu phí kịch bản "${game.title}". Pricing snapshot được bảo vệ!`, 'success');
                this.closeEditModal();
              } catch (err) {
                showToast(err.message, 'error');
              }
            },
          }, renderIcon('check'), ' Lưu cấu hình bảng giá')
        )
      )
    );

    return backdrop;
  }
}
