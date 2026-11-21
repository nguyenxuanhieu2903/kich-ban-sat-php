/**
 * Admin Refund Operations Page (#/admin/refunds)
 * Master Tactical Ops Center — Dark Cinematic Luxury
 */
import { h, renderIcon } from '../../utils/dom.js';
import { sharedStore } from '../../api/demo/shared-store.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatCurrency, formatDateTime } from '../../utils/format.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { showToast } from '../../components/toast.js';

export class AdminRefundsPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-admin-refunds container' });

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
            'FINANCIAL LEDGER // REFUND DISPATCH & AUDIT'
          ),
          h('h1', { className: 'page-title' }, 'Sổ Cái & Điều Phối Hoàn Tiền Cọc'),
          h('p', { className: 'page-subtitle text-muted' },
            'Thực thi nghiêm ngặt ranh giới 24.0 giờ theo tài liệu V16, chống hoàn tiền trùng lặp và ghi nhận mã giao dịch ngân hàng bất biến.'
          )
        ),
        h('div', { className: 'quick-nav-pills' },
          h('a', { className: 'btn btn-secondary btn-sm', href: '#/admin/dashboard' }, '← Về Dashboard')
        )
      )
    );

    // V16 Policy Rule Box
    const ruleBox = h('div', { className: 'tactical-card mb-4', style: { borderLeft: '3px solid var(--color-accent-gold)' } },
      h('div', { className: 'flex-align-center mb-1' },
        renderIcon('shield', 'text-gold mr-2'),
        h('strong', { className: 'text-light' }, 'Ranh Giới Hoàn Cọc Chuẩn V16 Freeze (Exact 24.0h Boundary)')
      ),
      h('p', { className: 'text-muted mb-0', style: { fontSize: '0.85rem', lineHeight: '1.6' } },
        'Chính sách bất biến: Người chơi gửi yêu cầu hủy trước giờ bắt đầu ca chơi ',
        h('strong', { className: 'text-gold' }, 'từ 24.0 giờ trở lên (ΔT ≥ 24h)'),
        ' được xếp loại Đủ Điều Kiện xét duyệt hoàn cọc 50.000 VNĐ. Mọi trường hợp hủy dưới 24 giờ (< 24.0h) hệ thống tự động khóa duyệt, yêu cầu lý do đặc cách (ADMIN_POLICY_OVERRIDE) hoặc thực hiện từ chối kèm lưu vết lý do.'
      )
    );
    container.appendChild(ruleBox);

    const refunds = sharedStore.data.refunds || [];
    const rooms = sharedStore.data.gameRooms || [];

    const totalRequested = refunds.filter((r) => r.status === 'REQUESTED').length;
    const totalSucceeded = refunds.filter((r) => r.status === 'SUCCEEDED').length;
    const totalFailed = refunds.filter((r) => r.status === 'FAILED').length;
    const totalRejected = refunds.filter((r) => r.status === 'REJECTED').length;

    // Metrics Strip
    const metricsStrip = h('div', { className: 'admin-metrics-strip strip-4 mb-4' },
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Chờ Duyệt (Requested)'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: totalRequested > 0 ? 'text-gold' : 'text-light' }, String(totalRequested))
        )
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Đã Hoàn Tất (Succeeded)'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: 'text-emerald' }, String(totalSucceeded))
        )
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Thất Bại (Cần Retry)'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: totalFailed > 0 ? 'text-danger' : 'text-light' }, String(totalFailed))
        )
      ),
      h('div', { className: 'metric-strip-item' },
        h('span', { className: 'metric-strip-label' }, 'Đã Từ Chối (Rejected)'),
        h('div', { className: 'metric-strip-value' },
          h('strong', { className: 'text-muted' }, String(totalRejected))
        )
      )
    );
    container.appendChild(metricsStrip);

    // Refunds Ledger Table
    const ledgerCard = h('div', { className: 'tactical-card mb-4' },
      h('div', { className: 'tactical-section-header' },
        h('h3', { className: 'tactical-section-title' },
          renderIcon('dollar', 'text-gold'),
          `Sổ Cái Điều Phối Hoàn Tiền (${refunds.length} Bản Ghi)`
        ),
        h('span', { className: 'tactical-mono-tag' }, '// REFUND-LEDGER')
      ),
      refunds.length === 0
        ? h('p', { className: 'text-muted' }, 'Hiện tại không có yêu cầu hoàn tiền nào trong hệ thống.')
        : h('div', { className: 'table-scroll-wrapper' },
            h('table', { className: 'ui-data-table' },
              h('thead', {},
                h('tr', {},
                  h('th', { scope: 'col' }, 'Mã lệnh'),
                  h('th', { scope: 'col' }, 'Vụ án & Người chơi'),
                  h('th', { scope: 'col' }, 'Số tiền cọc'),
                  h('th', { scope: 'col' }, 'Thời điểm hủy'),
                  h('th', { scope: 'col' }, 'Đánh giá 24H (V16)'),
                  h('th', { scope: 'col' }, 'Trạng thái & GD'),
                  h('th', { scope: 'col' }, 'Thao tác Admin')
                )
              ),
              h('tbody', {},
                ...refunds.map((ref) => {
                  const room = rooms.find((r) => r.id === ref.room_id || r.code === ref.room_code);
                  let hoursBefore = null;
                  let isEligible = false;
                  if (room && room.session_start_time && ref.created_at) {
                    hoursBefore = (new Date(room.session_start_time).getTime() - new Date(ref.created_at).getTime()) / (3600 * 1000);
                    isEligible = hoursBefore >= 24; // Exact 24.0h qualifies
                  }

                  return h('tr', {},
                    h('td', {}, h('strong', { className: 'highlight-gold font-mono' }, ref.id)),
                    h('td', {},
                      h('div', {}, h('strong', { className: 'text-light' }, ref.room_code || ref.room_id)),
                      h('div', { className: 'text-muted', style: { fontSize: '0.78rem' } }, `User: ${ref.user_name || ref.user_id}`)
                    ),
                    h('td', { className: 'highlight-gold font-mono' }, formatCurrency(ref.amount_vnd)),
                    h('td', { className: 'text-muted', style: { fontSize: '0.8rem' } }, formatDateTime(ref.created_at)),
                    h('td', {},
                      hoursBefore !== null
                        ? h('span', { className: `buffer-pill ${isEligible ? 'safe' : 'warning'}` },
                            isEligible ? `✓ ĐỦ ĐIỀU KIỆN (${Math.round(hoursBefore * 10) / 10}h)` : `✕ < 24H (${Math.round(hoursBefore * 10) / 10}h)`
                          )
                        : h('span', { className: 'text-muted' }, 'N/A')
                    ),
                    h('td', {},
                      h('div', {}, renderStatusBadge(ref.status)),
                      ref.reference_code
                        ? h('div', { className: 'text-muted font-mono mt-1', style: { fontSize: '0.72rem' } }, ref.reference_code)
                        : null,
                      ref.rejected_reason
                        ? h('div', { className: 'text-danger mt-1', style: { fontSize: '0.72rem' } }, `Lý do: ${ref.rejected_reason}`)
                        : null
                    ),
                    h('td', {},
                      h('div', { className: 'admin-action-btn-group' },
                        ref.status === 'REQUESTED'
                          ? h('button', {
                              className: 'btn btn-sm btn-primary',
                              onclick: () => this.handleApproveRefund(ref, user),
                            }, 'Duyệt hoàn')
                          : null,
                        ref.status === 'REQUESTED'
                          ? h('button', {
                              className: 'btn btn-sm btn-secondary text-danger',
                              onclick: () => this.handleRejectRefund(ref, user),
                            }, 'Từ chối')
                          : null,
                        ref.status === 'FAILED'
                          ? h('button', {
                              className: 'btn btn-sm btn-warning',
                              onclick: () => this.handleRetryRefund(ref, user),
                            }, 'Retry lệnh')
                          : null,
                        ref.status === 'SUCCEEDED'
                          ? h('span', { className: 'text-emerald', style: { fontSize: '0.82rem' } }, '✓ Hoàn tất')
                          : null,
                        ref.status === 'REJECTED'
                          ? h('span', { className: 'text-muted', style: { fontSize: '0.82rem' } }, 'Đã từ chối')
                          : null
                      )
                    )
                  );
                })
              )
            )
          )
    );
    container.appendChild(ledgerCard);

    this.mountPoint.appendChild(container);
  }

  handleApproveRefund(refund, adminActor) {
    try {
      sharedStore.adminProcessRefund(refund.id, true, 'Đủ điều kiện hoàn cọc V16', adminActor);
      showToast(`Đã duyệt hoàn cọc thành công cho ${refund.id}. Mã GD: ${refund.reference_code}.`, 'success');
      this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  handleRejectRefund(refund, adminActor) {
    showReasonDialog({
      title: `Từ Chối Hoàn Tiền Cọc: ${refund.id}`,
      actionKey: 'ADMIN_REJECT_REFUND',
      placeholder: 'Bắt buộc nêu rõ lý do từ chối (yêu cầu gửi sau mốc 24h quy định, vi phạm quy chế tham gia, không phản hồi)...',
      confirmLabel: 'Xác nhận từ chối hoàn cọc',
      onSubmit: (reason) => {
        try {
          sharedStore.adminProcessRefund(refund.id, false, reason, adminActor);
          showToast(`Đã từ chối yêu cầu hoàn cọc kèm lưu vết lý do.`, 'info');
          this.render();
        } catch (err) {
          showToast(err.message, 'error');
        }
      },
    });
  }

  handleRetryRefund(refund, adminActor) {
    try {
      sharedStore.adminProcessRefund(refund.id, true, 'Thử lại giao dịch ngân hàng thành công', adminActor);
      showToast(`Đã thực hiện lại lệnh hoàn cọc thành công! Mã GD: ${refund.reference_code}.`, 'success');
      this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }
}
