/**
 * Player Room Detail Page (#/player/rooms/:id) — CORE SHOWCASE SCREEN
 * Film Noir Command Station: SVG Round Table, Individual 2h Deposit Countdown, Universal Stepper,
 * Action Terminal, Mock Deposit Voucher Modal, and 24h Refund Policy Enforcement.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { renderCountdown } from '../../components/countdown.js';
import { renderRoomStepper } from '../../components/stepper.js';
import { renderParticipantRing } from '../../components/participant-ring.js';
import { renderPriceBreakdown } from '../../components/price-breakdown.js';
import { formatDateTime, formatCurrency } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';
import { Modal } from '../../components/modal.js';
import { showReasonDialog } from '../../components/reason-dialog.js';
import { getIdempotencyKey } from '../../utils/idempotency.js';
import { PARTICIPANT_STATUS, GAMEROOM_STATUS } from '../../utils/status.js';

export class PlayerRoomDetailPage {
  constructor(mountPoint, { params, router }) {
    this.mountPoint = mountPoint;
    this.roomId = params.id;
    this.router = router;
    this.room = null;
    this.quoteSnapshot = null;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-player-room-detail container' });

    try {
      const res = await apiService.getRoomById(this.roomId);
      this.room = res.data;
      const game = this.room.game_details;
      const user = auth.getUser();

      // Calculate price quote snapshot
      const quoteRes = await apiService.getQuote({
        game_id: game.id,
        session_start_time: this.room.session_start_time,
        player_count: game.min_players,
      });
      this.quoteSnapshot = quoteRes.data;

      // 1. Breadcrumbs & Quick Navigation
      const navRow = h('div', { className: 'flex-between', style: 'padding: 2rem 0 1rem 0;' },
        h('a', { className: 'link-gold flex-align-center', href: '#/player/dashboard', style: 'gap: 0.4rem; font-size: 0.9rem;' },
          renderIcon('chevronLeft'), ' Về Bàn Làm Việc Thám Tử'
        ),
        h('button', {
          className: 'btn btn-sm btn-secondary',
          onclick: () => this.handleShareRoom(),
        }, renderIcon('share'), ' Sao Chép Mã Vụ Án')
      );
      container.appendChild(navRow);

      // 2. Room Hero Command Banner
      const myPart = this.room.participants.find((p) => p.user_id === user?.id);
      const isCreator = this.room.creator_id === user?.id;

      const header = h('div', { className: 'room-hero-card', style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2rem; box-shadow: var(--shadow-md);' },
        h('div', { className: 'flex-between mb-3', style: 'flex-wrap: wrap; gap: 1rem;' },
          h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
            h('span', { className: 'room-code', style: 'font-size: 1.1rem; padding: 0.35rem 0.75rem;' }, this.room.code),
            isCreator ? h('span', { className: 'badge-creator' }, 'Chủ phòng') : null,
            renderStatusBadge(this.room.status)
          ),
          // Dynamic Urgent Countdowns
          h('div', {},
            this.room.status === GAMEROOM_STATUS.FORMING && this.room.formation_deadline
              ? renderCountdown(this.room.formation_deadline, {
                  label: 'Hạn chiêu mộ phòng (4h)',
                  onExpire: () => this.handleDeadlineExpired(),
                })
              : null,
            this.room.status === GAMEROOM_STATUS.WAITING_FOR_DEPOSIT && myPart?.deposit_deadline
              ? renderCountdown(myPart.deposit_deadline, {
                  label: 'Hạn cọc của bạn (2h)',
                  onExpire: () => this.handleDepositDeadlineExpired(),
                })
              : null,
            this.room.status === GAMEROOM_STATUS.REPLENISHING && this.room.replacement_deadline
              ? renderCountdown(this.room.replacement_deadline, {
                  label: 'Cửa sổ tìm người thay thế',
                  onExpire: () => this.render(),
                })
              : null
          )
        ),
        h('h1', { style: 'font-size: 2.8rem; color: var(--color-text-primary); margin-bottom: 0.75rem;' }, game.title),
        h('div', { style: 'display: flex; gap: 1.5rem; flex-wrap: wrap; color: var(--color-text-secondary); font-size: 0.9rem;' },
          h('span', {}, renderIcon('calendar'), ' Khởi hành: ', h('strong', { className: 'text-gold font-mono' }, formatDateTime(this.room.session_start_time))),
          h('span', {}, renderIcon('users'), ` Bàn: ${game.min_players}–${game.max_players} thám tử`),
          h('span', {}, renderIcon('clock'), ` Thời lượng: ${game.duration_minutes || 240} phút`),
          h('span', {}, renderIcon('shield'), this.room.dm_assignment ? ` DM: ${this.room.dm_assignment.dm_name}` : ' Chờ Ban Quản Trị chỉ định DM'),
          this.room.physical_room
            ? h('span', {}, renderIcon('sparkles'), ` Mật thất: ${this.room.physical_room.name}`)
            : null
        )
      );
      container.appendChild(header);

      // 3. Universal Lifecycle Stepper
      container.appendChild(
        h('div', { style: 'margin: 2rem 0;' },
          renderRoomStepper(this.room.status)
        )
      );

      // 4. Schedule Proposal Banner (If active with 2h voting window)
      if (this.room.schedule_proposal && this.room.schedule_proposal.status === 'PROPOSED') {
        container.appendChild(this.renderScheduleProposalCard(this.room.schedule_proposal));
      }

      // 5. Asymmetric 2-Column Command Showcase
      const mainGrid = h('div', { className: 'room-main-showcase-grid' },
        // Left Column: SVG Round Table & Live Seating Ring
        h('div', { style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 2rem; text-align: center;' },
          h('h3', { className: 'charter-title', style: 'font-size: 1.5rem; margin-bottom: 0.25rem;' }, 'Sơ Đồ Ghế Ngồi Bàn Tròn'),
          h('p', { className: 'text-muted font-mono', style: 'font-size: 0.75rem; margin-bottom: 1.5rem;' },
            'VỊ TRÍ THÁM TỬ THỰC ĐỊA • ĐỒNG BỘ THỜI GIAN THỰC'
          ),
          renderParticipantRing({
            participants: this.room.participants,
            minPlayers: game.min_players,
            maxPlayers: game.max_players,
            currentUserId: user?.id,
            size: 340,
          }),
          // Detailed Seat Roster
          h('div', { style: 'margin-top: 2rem; text-align: left; border-top: 1px solid var(--color-noir-divider); padding-top: 1.25rem;' },
            h('h4', { style: 'font-size: 1rem; color: var(--color-text-gold); margin-bottom: 1rem;' }, 'Trạng Thái Từng Thám Tử:'),
            h('div', { style: 'display: flex; flex-direction: column; gap: 0.6rem;' },
              ...this.room.participants.map((p) => {
                const isMe = p.user_id === user?.id;
                return h('div', {
                  style: `display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: ${isMe ? 'rgba(196, 163, 107, 0.08)' : 'var(--color-bg-surface-2)'}; border: 1px solid ${isMe ? 'var(--color-accent-gold)' : 'var(--color-border-subtle)'}; border-radius: var(--radius-xs);`
                },
                  h('div', {},
                    h('strong', { style: isMe ? 'color: var(--color-accent-gold);' : '' }, p.name),
                    isMe ? h('span', { className: 'badge-me', style: 'margin-left: 0.5rem;' }, 'Bạn') : null
                  ),
                  h('div', { style: 'display: flex; gap: 0.6rem; align-items: center;' },
                    renderStatusBadge(p.status),
                    p.deposit_deadline && p.status === PARTICIPANT_STATUS.PENDING_DEPOSIT
                      ? renderCountdown(p.deposit_deadline, { label: 'Hạn cọc', showIcon: false })
                      : null
                  )
                );
              })
            )
          )
        ),

        // Right Column: Action Terminal, Transparent Receipt, and Refund Policy
        h('div', {},
          // Action Terminal
          h('div', { style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-border-hairline); border-radius: var(--radius-xs); padding: 1.75rem; margin-bottom: 1.5rem;' },
            h('h3', { className: 'charter-title', style: 'font-size: 1.35rem; margin-bottom: 1rem;' }, 'Bảng Điều Khiển Của Bạn'),
            this.renderActionButtons(myPart, isCreator, game)
          ),

          // Price Breakdown (Per-person transparent receipt)
          renderPriceBreakdown(this.quoteSnapshot, { showPerPersonOnly: true, title: 'Bóc Tách Phần Vé Của Bạn' }),

          // Strict 24h Refund Charter Banner
          h('div', { style: 'background: var(--color-bg-surface-1); border-left: 3px solid var(--color-accent-crimson); padding: 1.25rem; margin-top: 1.5rem; border-radius: var(--radius-xs);' },
            h('h4', { className: 'highlight-gold mb-1', style: 'font-size: 0.95rem;' },
              renderIcon('shield'), ' Quy Tắc Hoàn Cọc (V16 Freeze)'
            ),
            h('p', { className: 'text-muted', style: 'font-size: 0.82rem; line-height: 1.6; margin: 0;' },
              'Người chơi chỉ đủ điều kiện xét hoàn khoản cọc 50.000 VNĐ khi chủ động gửi yêu cầu xin rời phòng trước giờ bắt đầu của ca chơi ít nhất 24 giờ. Quyết định hoàn cọc sẽ được Ban Quản Trị thẩm định và ghi chú mã tham chiếu giao dịch rõ ràng.'
            )
          )
        )
      );

      container.appendChild(mainGrid);
      this.mountPoint.appendChild(container);

    } catch (err) {
      container.appendChild(
        h('div', { className: 'error-state text-center' },
          h('p', { className: 'text-danger' }, err.message),
          h('a', { className: 'btn btn-secondary', href: '#/player/dashboard' }, 'Về danh sách vụ án')
        )
      );
      this.mountPoint.appendChild(container);
    }
  }

  // --- Schedule Proposal Voting Card ---
  renderScheduleProposalCard(proposal) {
    const user = auth.getUser();
    const myVote = proposal.votes[user?.id];

    return h('div', { style: 'background: var(--color-bg-surface-1); border: 1px solid var(--color-accent-gold); padding: 1.75rem; border-radius: var(--radius-xs); margin-bottom: 2rem;' },
      h('div', { className: 'flex-between mb-2', style: 'flex-wrap: wrap; gap: 1rem;' },
        h('div', {},
          h('span', { className: 'dossier-badge-pill' }, 'ĐỀ XUẤT ĐỔI LỊCH MỚI'),
          h('h3', { style: 'font-size: 1.5rem; color: var(--color-text-gold);' },
            `Đề xuất dời lịch sang: ${formatDateTime(proposal.proposed_time)}`
          )
        ),
        renderCountdown(proposal.deadline, { label: 'Hạn biểu quyết (2h)' })
      ),
      h('p', { className: 'text-muted', style: 'font-size: 0.9rem; line-height: 1.6;' },
        'Quy định V16: Yêu cầu 100% thám tử đang hoạt động đồng ý trong hạn 2 giờ. Nếu có 1 người từ chối hoặc hết thời hạn, đề xuất sẽ bị hủy bỏ và giữ nguyên lịch cũ.'
      ),
      h('div', { style: 'margin-top: 1.25rem;' },
        myVote
          ? h('div', { className: 'text-emerald', style: 'font-weight: 600;' },
              `✓ Bạn đã bỏ phiếu: ${myVote === 'ACCEPTED' ? 'ĐỒNG Ý DỜI LỊCH' : 'TỪ CHỐI (GIỮ LỊCH CŨ)'}`
            )
          : h('div', { style: 'display: flex; gap: 1rem;' },
              h('button', {
                className: 'btn btn-primary',
                onclick: () => this.handleVoteProposal('ACCEPTED'),
              }, 'Đồng Ý Dời Lịch'),
              h('button', {
                className: 'btn btn-secondary',
                onclick: () => this.handleVoteProposal('REJECTED'),
              }, 'Từ Chối (Giữ Lịch Cũ)')
            )
      )
    );
  }

  // --- Context-Sensitive Action Buttons ---
  renderActionButtons(myPart, isCreator, game) {
    const box = h('div', { style: 'display: flex; flex-direction: column; gap: 0.75rem;' });
    const room = this.room;

    // Case 1: Chưa tham gia phòng
    if (!myPart) {
      box.appendChild(
        h('button', {
          className: 'btn btn-primary btn-block btn-lg',
          onclick: () => this.handleJoin(),
        }, renderIcon('plus'), ' Tiếp Nhận Ghế Trống Này')
      );
      return box;
    }

    // Case 2: Đang chờ cọc (PENDING_DEPOSIT)
    if (myPart.status === PARTICIPANT_STATUS.PENDING_DEPOSIT) {
      if (room.status === GAMEROOM_STATUS.WAITING_FOR_DEPOSIT) {
        box.appendChild(
          h('button', {
            className: 'btn btn-primary btn-block btn-lg',
            onclick: () => this.openDepositModal(),
          }, renderIcon('creditCard'), ' Đặt Cọc 50.000 VNĐ Giữ Ghế')
        );
      } else {
        box.appendChild(
          h('div', { className: 'promo-rules-box' },
            'Ghế của bạn đã được giữ. Vui lòng chờ Ban Quản Trị chỉ định Quản trò và mở thời hạn đặt cọc 2 giờ.'
          )
        );
      }

      // Rời phòng trước khi cọc
      box.appendChild(
        h('button', {
          className: 'btn btn-secondary btn-block',
          onclick: () => this.handleLeaveBeforeDeposit(),
        }, 'Rời Khỏi Phòng (Hủy Giữ Ghế)')
      );
      return box;
    }

    // Case 3: Đã cọc thành công (DEPOSITED)
    if (myPart.status === PARTICIPANT_STATUS.DEPOSITED) {
      box.appendChild(
        h('div', { style: 'background: rgba(63, 168, 136, 0.12); border: 1px solid var(--color-status-emerald); padding: 0.85rem; border-radius: var(--radius-xs); color: #72D2B5; font-size: 0.9rem;' },
          renderIcon('check'), ' Bạn đã hoàn tất ký thác 50.000 VNĐ giữ ghế an toàn.'
        )
      );

      // Xin rời phòng sau cọc (Reason Dialog + xét hoàn cọc 24h)
      box.appendChild(
        h('button', {
          className: 'btn btn-secondary btn-block text-danger',
          onclick: () => this.handleRequestLeaveAfterDeposit(),
        }, 'Gửi Yêu Cầu Xin Rời Phòng (Xét Hoàn Cọc)')
      );

      // Thanh toán khoản còn lại tại quán
      box.appendChild(
        h('button', {
          className: 'btn btn-secondary btn-block',
          onclick: () => showToast('Bạn có thể thanh toán số tiền còn lại trực tiếp tại quầy quán khi đến chơi.', 'info'),
        }, 'Thanh Toán Khoản Còn Lại Tại Quán')
      );
      return box;
    }

    // Case 4: Đã gửi yêu cầu rời phòng
    if (myPart.status === PARTICIPANT_STATUS.LEAVE_REQUESTED) {
      box.appendChild(
        h('div', { className: 'promo-rules-box text-amber', style: 'border-color: var(--color-status-amber);' },
          'Bạn đã gửi yêu cầu xin rời phòng. Ban Quản Trị đang thẩm định lý do và đối chiếu mốc 24 giờ để ra quyết định hoàn cọc.'
        )
      );
      return box;
    }

    return box;
  }

  // --- Handlers ---
  async handleJoin() {
    try {
      await apiService.joinRoom(this.room.id);
      showToast('Tiếp nhận ghế thành công!', 'success');
      this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  handleLeaveBeforeDeposit() {
    showToast('Đã rời khỏi phòng chơi.', 'info');
    this.router.navigate('/player/dashboard');
  }

  handleRequestLeaveAfterDeposit() {
    showReasonDialog({
      title: 'Yêu Cầu Xin Rời Phòng & Xét Hoàn Cọc (V16)',
      actionKey: 'PLAYER_REQUEST_LEAVE',
      placeholder: 'Cung cấp lý do bạn cần hủy ca chơi để Ban Quản Trị đối chiếu mốc 24 giờ...',
      confirmLabel: 'Gửi Yêu Cầu Tới Admin',
      onSubmit: () => {
        showToast('Đã gửi yêu cầu xin rời phòng tới Ban Quản Trị.', 'success');
        this.render();
      },
    });
  }

  handleVoteProposal(vote) {
    showToast(`Đã ghi nhận phiếu ${vote === 'ACCEPTED' ? 'ĐỒNG Ý' : 'TỪ CHỐI'} dời lịch của bạn.`, 'success');
    this.render();
  }

  handleShareRoom() {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(window.location.href);
      showToast('Đã sao chép liên kết phòng vào bộ nhớ tạm!', 'success');
    } else {
      showToast(`Mã phòng: ${this.room.code}`, 'info');
    }
  }

  handleDeadlineExpired() {
    showToast('Thời hạn chiêu mộ 4 giờ của phòng đã kết thúc.', 'error');
    this.render();
  }

  handleDepositDeadlineExpired() {
    showToast('Thời hạn đặt cọc 2 giờ của bạn đã hết. Ghế đã bị hủy.', 'error');
    this.render();
  }

  // --- Mock Deposit Luxury Voucher Modal ---
  openDepositModal() {
    let depositModalInstance = null;
    let isSubmitting = false;
    const idempotencyKey = getIdempotencyKey(`deposit_${this.room.id}_${auth.getUser()?.id}`);

    const modalBody = h('div', {},
      h('div', { className: 'deposit-voucher-preview' },
        h('div', { className: 'voucher-watermark' }, 'MÔ PHỎNG'),
        h('h4', { className: 'voucher-title' }, 'PHIẾU KÝ THÁC GIỮ GHẾ THÁM TỬ'),
        h('p', { className: 'text-muted font-mono', style: 'font-size: 0.85rem; margin-bottom: 1rem;' },
          `PHÒNG: ${this.room.code} • KỊCH BẢN: ${this.room.game_details.title}`
        ),
        h('div', { className: 'voucher-amount-box' },
          h('span', { className: 'text-muted font-mono', style: 'font-size: 0.78rem; text-transform: uppercase;' }, 'Số Tiền Cọc Quy Định (V16 Freeze):'),
          h('span', { className: 'voucher-amount highlight-gold' }, '50.000 VNĐ')
        ),
        h('p', { className: 'text-muted', style: 'font-size: 0.8rem; line-height: 1.5; margin: 0;' },
          'Tiền cọc cố định, không tính theo phần trăm và được khấu trừ trực tiếp vào hóa đơn khi đến quán tham gia phiên chơi.'
        )
      ),

      h('div', { className: 'flex-between', style: 'padding-top: 1.25rem; border-top: 1px solid var(--color-noir-divider);' },
        h('button', {
          className: 'btn btn-secondary',
          onclick: () => depositModalInstance.close(),
        }, 'Đóng'),
        h('button', {
          id: 'btn-confirm-deposit',
          className: 'btn btn-primary btn-lg',
          onclick: async () => {
            if (isSubmitting) return;
            isSubmitting = true;
            const btn = document.getElementById('btn-confirm-deposit');
            if (btn) {
              btn.disabled = true;
              btn.textContent = 'Đang niêm phong tiền cọc...';
            }

            try {
              await apiService.makeDeposit(this.room.id, idempotencyKey);
              showToast('Hoàn tất đặt cọc 50.000 VNĐ giữ ghế thành công!', 'success');
              depositModalInstance.close();
              this.render();
            } catch (err) {
              showToast(`Đặt cọc thất bại: ${err.message}`, 'error');
              if (btn) {
                btn.disabled = false;
                btn.textContent = 'Xác nhận cọc 50.000 VNĐ';
              }
              isSubmitting = false;
            }
          },
        }, 'Xác Nhận Ký Thác 50.000 VNĐ (Mô Phỏng)')
      )
    );

    depositModalInstance = new Modal({
      title: 'Ký Thác Đặt Cọc Giữ Chỗ (Mô Phỏng V16)',
      contentEl: modalBody,
    });

    depositModalInstance.open();
  }
}
