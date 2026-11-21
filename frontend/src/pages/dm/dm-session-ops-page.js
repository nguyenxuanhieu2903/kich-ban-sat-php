/**
 * DM Session Operations Page (#/dm/sessions/:id)
 * Live Session Terminal — Master Command Station
 * Real-time checklist, attendance recording, in-store payment collection with receipts, and session lifecycle control.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth, ROLES } from '../../utils/auth.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { formatDateTime, formatCurrency } from '../../utils/format.js';
import { showToast } from '../../components/toast.js';
import { SESSION_STATUS, GAMEROOM_STATUS } from '../../utils/status.js';

export class DmSessionOpsPage {
  constructor(mountPoint, { params, router }) {
    this.mountPoint = mountPoint;
    this.roomId = params.id;
    this.router = router;
    this.room = null;
    this.quoteSnapshot = null;
    this.prepChecklist = {
      script_read: true,
      props_ready: true,
      audio_tested: true,
    };
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-dm-session-ops container' });

    const user = auth.getUser();
    if (!user || user.role !== ROLES.DM) {
      this.router.navigate('/403');
      return;
    }

    try {
      const res = await apiService.getRoomById(this.roomId);
      this.room = res.data;
      const game = this.room.game_details || { title: 'Kịch bản Jubensha' };

      // Validate assignment ownership
      if (this.room.dm_assignment?.dm_id !== user.id && !auth.isAdmin()) {
        container.appendChild(
          h('div', { className: 'error-state text-danger p-5 text-center' },
            renderIcon('shield', 'text-danger', 36),
            h('h2', { className: 'mt-3' }, 'Truy Cập Bị Từ Chối (403)'),
            h('p', { className: 'text-muted' }, 'Bạn không phải là Quản trò được phân công dẫn dắt phòng này.'),
            h('a', { className: 'btn btn-secondary mt-3', href: '#/dm/assignments' }, 'Về danh sách phân công')
          )
        );
        this.mountPoint.appendChild(container);
        return;
      }

      // Calculate price quote snapshot for remaining payment calculation
      try {
        const quoteRes = await apiService.getQuote({
          game_id: game.id,
          session_start_time: this.room.session_start_time,
          player_count: game.min_players,
        });
        this.quoteSnapshot = quoteRes.data;
      } catch (e) {
        // Fallback
        this.quoteSnapshot = { final_price_per_player: 180000, deposit_per_person_vnd: 50000 };
      }

      // 1. Navigation Breadcrumb
      const navRow = h('div', { className: 'flex-between', style: 'padding: 2rem 0 1rem 0;' },
        h('a', { className: 'link-gold flex-align-center', href: `#/dm/dashboard`, style: 'gap: 0.4rem; font-size: 0.9rem;' },
          renderIcon('chevronLeft'), ' Về Bàn Làm Việc Quản Trò'
        ),
        h('div', { style: 'display: flex; gap: 0.75rem;' },
          h('a', { className: 'btn btn-sm btn-secondary', href: `#/dm/rooms/${this.roomId}` },
            renderIcon('users'), ' Xem Sơ Đồ Bàn Tròn'
          )
        )
      );
      container.appendChild(navRow);

      // 2. Terminal Command Hero
      const currentSessionStatus = this.room.session?.status || (this.room.status === GAMEROOM_STATUS.COMPLETED ? SESSION_STATUS.COMPLETED : SESSION_STATUS.SCHEDULED);
      const isCompleted = currentSessionStatus === SESSION_STATUS.COMPLETED;
      const isInProgress = currentSessionStatus === SESSION_STATUS.IN_PROGRESS;

      const header = h('div', { className: 'live-session-terminal-header' },
        h('div', { className: 'flex-between mb-3', style: 'flex-wrap: wrap; gap: 1rem;' },
          h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
            h('span', { className: 'room-code', style: 'font-size: 1.1rem; padding: 0.35rem 0.75rem;' }, this.room.code),
            renderStatusBadge(currentSessionStatus, `Phiên: ${currentSessionStatus}`),
            this.room.physical_room
              ? h('span', { className: 'badge-operator' }, renderIcon('sparkles'), ` Mật thất: ${this.room.physical_room.name}`)
              : h('span', { className: 'text-muted' }, 'Chưa chốt phòng vật lý')
          ),
          h('div', { className: 'text-muted font-mono', style: 'font-size: 0.85rem;' },
            `Quản trò: `, h('strong', { className: 'text-gold' }, user.name)
          )
        ),
        h('h1', { style: 'font-size: 2.5rem; color: var(--color-text-primary); margin-bottom: 0.75rem;' },
          `Trạm Vận Hành Ca Chơi: ${game.title}`
        ),
        h('div', { style: 'display: flex; gap: 1.5rem; flex-wrap: wrap; color: var(--color-text-secondary); font-size: 0.9rem;' },
          h('span', {}, renderIcon('calendar'), ' Giờ bắt đầu: ', h('strong', { className: 'text-gold font-mono' }, formatDateTime(this.room.session_start_time))),
          h('span', {}, renderIcon('users'), ` Thành viên: ${this.room.participants.length} người`),
          h('span', {}, renderIcon('clock'), ` Thời lượng dự kiến: ${game.duration_minutes || 240} phút`),
          isInProgress ? h('span', { className: 'text-emerald font-mono' }, renderIcon('check'), ' Phiên chơi đang diễn ra') : null,
          isCompleted ? h('span', { className: 'text-emerald font-mono' }, renderIcon('check'), ' Phiên chơi đã kết thúc') : null
        )
      );
      container.appendChild(header);

      // 3. Operational Grid (2 Columns: Protocol & Master Controls | Attendance & In-Store Payment)
      const opsGrid = h('div', { className: 'session-ops-layout' });

      // Left Column: Pre-session Checklist & Master State Controls
      const leftCol = h('div', { style: 'display: flex; flex-direction: column; gap: 1.5rem;' });

      // Protocol Checklist Box
      const checklistBox = h('div', { className: 'checklist-box' },
        h('h3', { className: 'highlight-gold', style: 'font-size: 1.15rem; margin: 0 0 0.5rem 0;' },
          '1. Nghi Thức Chuẩn Bị (Pre-Session Protocol)'
        ),
        h('p', { className: 'text-muted', style: 'font-size: 0.82rem; margin: 0;' },
          'Quản trò hoàn thành kiểm tra vật lý trước khi mời thám tử vào mật thất.'
        ),
        h('div', { className: 'checklist-group' },
          h('label', { className: 'checklist-label' },
            h('input', {
              type: 'checkbox',
              checked: this.prepChecklist.script_read,
              disabled: isCompleted,
              onchange: (e) => { this.prepChecklist.script_read = e.target.checked; },
            }),
            h('span', {}, 'Đã đọc kịch bản, nhánh phá án & tâm lý nhân vật')
          ),
          h('label', { className: 'checklist-label' },
            h('input', {
              type: 'checkbox',
              checked: this.prepChecklist.props_ready,
              disabled: isCompleted,
              onchange: (e) => { this.prepChecklist.props_ready = e.target.checked; },
            }),
            h('span', {}, 'Đạo cụ, manh mối vật lý & phục trang đã xếp đúng vị trí')
          ),
          h('label', { className: 'checklist-label' },
            h('input', {
              type: 'checkbox',
              checked: this.prepChecklist.audio_tested,
              disabled: isCompleted,
              onchange: (e) => { this.prepChecklist.audio_tested = e.target.checked; },
            }),
            h('span', {}, 'Hệ thống âm thanh kịch tính & ánh sáng đã kiểm tra hoàn tất')
          )
        )
      );
      leftCol.appendChild(checklistBox);

      // Master State Control Box
      const controlBox = h('div', { className: 'checklist-box' },
        h('h3', { className: 'highlight-gold', style: 'font-size: 1.15rem; margin: 0 0 0.5rem 0;' },
          '3. Điều Khiển Vòng Đời Ca Chơi'
        ),
        h('p', { className: 'text-muted mb-3', style: 'font-size: 0.82rem;' },
          'Chuyển đổi trạng thái phiên chơi theo tiến trình thực tế. Các thao tác đều được ghi Audit Log.'
        ),
        h('div', { style: 'display: flex; flex-direction: column; gap: 0.75rem;' },
          !isInProgress && !isCompleted
            ? h('button', {
                className: 'btn btn-primary btn-block',
                onclick: () => this.handleStartSession(),
              }, renderIcon('check'), ' Bắt Đầu Phiên Chơi (IN_PROGRESS)')
            : null,
          isInProgress
            ? h('button', {
                className: 'btn btn-secondary btn-block text-emerald',
                style: 'border-color: #2e7d32; background: rgba(46, 125, 50, 0.15);',
                onclick: () => this.handleCompleteSession(),
              }, renderIcon('check'), ' Xác Nhận Hoàn Thành Phiên Chơi (COMPLETED)')
            : null,
          isCompleted
            ? h('div', { className: 'p-3 text-center', style: 'background: rgba(46, 125, 50, 0.1); border: 1px solid rgba(46, 125, 50, 0.3); border-radius: var(--radius-xs);' },
                renderIcon('check', 'text-emerald', 24),
                h('p', { className: 'text-emerald font-mono mt-1', style: 'font-size: 0.9rem; margin: 0;' },
                  'Phiên chơi đã đóng thành công.'
                )
              )
            : null
        )
      );
      leftCol.appendChild(controlBox);

      opsGrid.appendChild(leftCol);

      // Right Column: Attendance & In-Store Payment Terminal
      const rightCol = h('div', { className: 'roster-terminal-panel' },
        h('div', { className: 'flex-between mb-3' },
          h('div', {},
            h('h3', { className: 'highlight-gold', style: 'font-size: 1.25rem; margin: 0;' },
              '2. Sổ Điểm Danh & Thu Khoản Còn Lại Tại Quán'
            ),
            h('p', { className: 'text-muted mt-1', style: 'font-size: 0.85rem; margin: 0;' },
              'Xác nhận thám tử có mặt và thu số tiền còn lại sau khi trừ 50.000 VNĐ tiền cọc.'
            )
          ),
          h('span', { className: 'text-muted font-mono', style: 'font-size: 0.8rem;' },
            `${this.room.participants.length} THÀM TỬ`
          )
        )
      );

      // Participant Roster Table
      const baseTicketPrice = this.quoteSnapshot?.final_price_per_player || 180000;
      const depositDeducted = this.quoteSnapshot?.deposit_per_person_vnd || 50000;
      const remainingBalance = Math.max(0, baseTicketPrice - depositDeducted);

      const rosterList = h('div', { className: 'roster-terminal-list' });

      this.room.participants.forEach((p) => {
        const isPresent = p.attendance !== 'ABSENT'; // Default present if not explicitly absent
        const isPaid = p.remaining_payment?.status === 'PAID';

        const item = h('div', { className: 'roster-item-row' },
          h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
            h('div', { style: 'width: 36px; height: 36px; border-radius: 50%; background: var(--color-bg-surface-2); border: 1px solid var(--color-border-subtle); display: flex; align-items: center; justify-content: center;' },
              renderIcon('user', 'text-muted', 16)
            ),
            h('div', {},
              h('strong', { style: 'font-size: 0.95rem; color: var(--color-text-primary);' }, p.name),
              h('div', { style: 'display: flex; gap: 0.5rem; align-items: center; margin-top: 0.2rem;' },
                renderStatusBadge(p.status),
                h('span', { className: 'text-muted font-mono', style: 'font-size: 0.75rem;' },
                  `Khoản còn lại: ${formatCurrency(remainingBalance)}`
                )
              )
            )
          ),
          h('div', { style: 'display: flex; gap: 1rem; align-items: center;' },
            // Attendance Switch
            h('label', { style: 'display: flex; gap: 0.4rem; align-items: center; cursor: pointer; font-size: 0.85rem;' },
              h('input', {
                type: 'checkbox',
                checked: isPresent,
                disabled: isCompleted,
                onchange: (e) => this.handleAttendanceToggle(p.id, e.target.checked),
              }),
              h('span', { className: isPresent ? 'text-emerald' : 'text-danger' },
                isPresent ? 'Có mặt' : 'Vắng mặt'
              )
            ),
            // Payment Action or Receipt Pill
            isPaid
              ? h('span', { className: 'receipt-pill' },
                  renderIcon('check'),
                  `Đã thu: ${formatCurrency(p.remaining_payment.amount)} (${p.remaining_payment.receipt_code})`
                )
              : h('button', {
                  className: 'btn btn-sm btn-primary',
                  disabled: isCompleted || !isPresent,
                  onclick: () => this.handleCollectPayment(p, remainingBalance),
                }, renderIcon('sparkles'), ` Thu ${formatCurrency(remainingBalance)} tại quán`)
          )
        );
        rosterList.appendChild(item);
      });

      rightCol.appendChild(rosterList);
      opsGrid.appendChild(rightCol);

      container.appendChild(opsGrid);
      this.mountPoint.appendChild(container);

    } catch (err) {
      container.appendChild(
        h('div', { className: 'error-state text-danger p-4' }, err.message)
      );
      this.mountPoint.appendChild(container);
    }
  }

  async handleAttendanceToggle(participantId, isPresent) {
    try {
      await apiService.updateParticipantAttendance(this.roomId, participantId, isPresent);
      showToast(`Đã cập nhật điểm danh: ${isPresent ? 'Có mặt' : 'Vắng mặt'}.`, 'success');
      await this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async handleCollectPayment(participant, amount) {
    const user = auth.getUser();
    const confirmed = window.confirm(
      `Xác nhận thu tiền mặt/chuyển khoản tại quán cho thám tử ${participant.name}:\n` +
      `- Số tiền thu: ${formatCurrency(amount)}\n` +
      `- Đã trừ cọc: 50.000 VNĐ\n` +
      `- Quản trò xác nhận: ${user?.name}\n\n` +
      `Thao tác này sẽ xuất biên nhận số và không thể thu trùng. Tiếp tục?`
    );

    if (!confirmed) return;

    try {
      const res = await apiService.confirmRemainingPayment(this.roomId, participant.id, amount);
      showToast(`Đã thu thành công! Mã biên nhận: ${res.data.receipt_code}`, 'success');
      await this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async handleStartSession() {
    const confirmed = window.confirm('Kích hoạt bắt đầu phiên chơi chính thức (Trạng thái chuyển sang IN_PROGRESS)?');
    if (!confirmed) return;

    try {
      await apiService.updateSessionStatus(this.roomId, SESSION_STATUS.IN_PROGRESS);
      showToast('Phiên chơi chính thức bắt đầu!', 'success');
      await this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async handleCompleteSession() {
    const confirmed = window.confirm('Xác nhận kết thúc và đóng phiên chơi (Trạng thái chuyển sang COMPLETED)?');
    if (!confirmed) return;

    try {
      await apiService.updateSessionStatus(this.roomId, SESSION_STATUS.COMPLETED);
      showToast('Phiên chơi đã kết thúc thành công (COMPLETED)!', 'success');
      await this.render();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }
}
