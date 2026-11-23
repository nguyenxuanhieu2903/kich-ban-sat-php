import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';

export class RoomsView {
  constructor(container, onOpenAuthModal = () => {}) {
    this.container = container;
    this.onOpenAuthModal = onOpenAuthModal;
    this.rooms = [];
    this.selectedRoom = null;
    this.currentFilter = 'FORMING';
  }

  async render() {
    this.selectedRoom = null;
    this.container.innerHTML = `
      <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 6px 0;">Sảnh Phòng Chơi (Game Room Lobby)</h2>
          <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">Tìm phòng ghép người chơi hoặc tự mở phòng mời bạn bè cùng tham gia</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
          <select id="rooms-status-filter" class="form-input" style="padding: 8px 12px; font-size: 0.85rem;">
            <option value="FORMING">Đang gom người (FORMING)</option>
            <option value="ALL">Tất cả các phòng</option>
          </select>
          <button id="btn-open-create-room" class="btn btn-primary" style="display: flex; align-items: center; gap: 6px;">
            <span>+</span> Tạo phòng mới
          </button>
        </div>
      </div>

      <div id="rooms-lobby-content">
        <div style="text-align: center; padding: 40px; color: var(--color-text-muted);">Đang tải danh sách phòng...</div>
      </div>
    `;

    this.container.querySelector('#rooms-status-filter').onchange = (e) => {
      this.currentFilter = e.target.value;
      this.fetchRooms();
    };

    this.container.querySelector('#btn-open-create-room').onclick = () => {
      this.showCreateRoomModal();
    };

    await this.fetchRooms();
  }

  async fetchRooms() {
    const content = this.container.querySelector('#rooms-lobby-content');
    try {
      const res = await api.get(`/rooms?status=${this.currentFilter}`);
      this.rooms = res?.data?.rooms || [];

      if (this.rooms.length === 0) {
        content.innerHTML = `
          <div style="text-align: center; padding: 60px; background: var(--color-bg-card); border-radius: var(--radius-md); border: 1px dashed var(--color-border);">
            <div style="font-size: 2.5rem; margin-bottom: 12px;"></div>
            <h3 style="margin: 0 0 8px 0;">Hiện chưa có phòng nào đang mở</h3>
            <p style="color: var(--color-text-muted); font-size: 0.9rem; margin: 0 0 16px 0;">Hãy là người đầu tiên tạo phòng để tập hợp đồng đội!</p>
            <button class="btn btn-primary" id="btn-empty-create">Tạo phòng ngay</button>
          </div>
        `;
        content.querySelector('#btn-empty-create').onclick = () => this.showCreateRoomModal();
        return;
      }

      content.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
          ${this.rooms.map(room => {
            const isReplacementOpen = room.replacement_status === 'OPEN';
            return `
              <div class="card room-card" data-id="${room.id}" style="padding: 20px; cursor: pointer; transition: transform 0.2s, border-color 0.2s; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                  <span class="badge" style="background: ${isReplacementOpen ? 'rgba(239, 68, 68, 0.2)' : 'rgba(59, 130, 246, 0.2)'}; color: ${isReplacementOpen ? '#ef4444' : '#60a5fa'}; border: 1px solid ${isReplacementOpen ? '#ef4444' : '#3b82f6'};">
                    ${isReplacementOpen ? '[CAN NGUOI THE CHO] CẦN NGƯỜI THẾ CHỖ' : room.status}
                  </span>
                  <span style="font-size: 0.8rem; color: #94a3b8;">Mã: #${room.id}</span>
                </div>
                <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 6px 0; color: #f8fafc;">${room.title}</h3>
                <div style="font-size: 0.85rem; color: #38bdf8; font-weight: 500; margin-bottom: 12px;">
                   Kịch bản: ${room.game_name}
                </div>
                <div style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 16px; flex-grow: 1;">
                  Host: <strong>${room.creator_name}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border); padding-top: 14px;">
                  <span style="font-size: 0.85rem; font-weight: 600; color: ${room.current_players >= room.max_players ? '#ef4444' : '#10b981'};">
                     ${room.current_players} / ${room.max_players} người
                  </span>
                  <button class="btn btn-secondary btn-view-room" data-id="${room.id}" style="padding: 6px 14px; font-size: 0.85rem;">
                    Vào phòng &rarr;
                  </button>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      `;

      content.querySelectorAll('.room-card').forEach(card => {
        card.onclick = () => this.showRoomDetail(card.dataset.id);
      });
    } catch (err) {
      content.innerHTML = `<div style="text-align: center; padding: 40px; color: var(--color-error);">${err.message}</div>`;
    }
  }

  async showRoomDetail(roomId) {
    this.container.innerHTML = `<div style="text-align: center; padding: 60px; color: var(--color-text-muted);">Đang tải thông tin phòng chơi...</div>`;

    try {
      const res = await api.get(`/rooms/${roomId}`);
      this.selectedRoom = res?.data?.room;

      if (!this.selectedRoom) {
        throw new Error('Không tìm thấy thông tin phòng');
      }

      this.renderRoomDetail();
    } catch (err) {
      toast.error(err.message);
      this.render();
    }
  }

  renderRoomDetail() {
    const room = this.selectedRoom;
    const currentUser = authState.user;
    const isHost = currentUser && Number(currentUser.id) === Number(room.creator_id);
    const participants = room.participants || [];
    const myParticipant = currentUser ? participants.find(p => Number(p.user_id) === Number(currentUser.id)) : null;
    const isJoined = myParticipant && ['PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED'].includes(myParticipant.status);
    const isReplacementOpen = room.replacement_status === 'OPEN';

    this.container.innerHTML = `
      <div style="margin-bottom: 20px;">
        <button id="btn-back-lobby" class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.85rem; margin-bottom: 16px;">
          &larr; Quay lại danh sách phòng
        </button>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
          <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
              <h2 style="font-size: 1.6rem; font-weight: 700; margin: 0;">${room.title}</h2>
              <span class="badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid #3b82f6;">
                ${room.status}
              </span>
              ${isReplacementOpen ? `
                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444;">
                  [CAN NGUOI THE CHO] CẦN NGƯỜI THAY THẾ
                </span>
              ` : ''}
            </div>
            <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">
              Kịch bản: <strong style="color: #38bdf8;">${room.game?.name || 'Kịch bản sát'}</strong> (${room.game?.duration_minutes || 240} phút) | Host: <strong>${room.creator_name || 'Host'}</strong>
            </p>
          </div>

          <!-- Action Buttons -->
          <div style="display: flex; gap: 10px;">
            ${!isJoined ? `
              <button id="btn-join-room" class="btn btn-primary" style="padding: 10px 20px;">
                ${isReplacementOpen ? ' Vào thế chỗ (Takeover)' : '+ Tham gia phòng'}
              </button>
            ` : `
              <button id="btn-leave-room" class="btn btn-danger" style="padding: 10px 20px;">
                 Rời phòng / Tìm người thay thế
              </button>
            `}
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Left Column: Thành viên & Biểu quyết & Giá vé -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
          <!-- Card: Danh sách thành viên -->
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;">
                 Thành viên tham gia (${participants.length} / ${room.max_players})
              </h3>
              <span style="font-size: 0.85rem; color: #10b981; font-weight: 600;">
                Cọc cố định: 50.000đ / người
              </span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
              ${participants.map((p, index) => {
                const isMe = currentUser && Number(p.user_id) === Number(currentUser.id);
                return `
                  <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(255, 255, 255, 0.03); border-radius: 8px; border: 1px solid var(--color-border);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                      <span style="font-weight: 600; color: #94a3b8; width: 24px;">#${index + 1}</span>
                      <div>
                        <div style="font-weight: 600; color: ${isMe ? '#38bdf8' : '#f8fafc'};">
                          ${p.name || p.user_name || 'Người chơi'} ${isMe ? '(Bạn)' : ''}
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8;">${p.email || ''}</div>
                      </div>
                    </div>
                    <div>
                      <span class="badge" style="font-size: 0.75rem; background: ${p.status === 'DEPOSITED' ? 'rgba(16, 185, 129, 0.2)' : p.status === 'LEAVE_REQUESTED' ? 'rgba(245, 158, 11, 0.2)' : 'rgba(59, 130, 246, 0.2)'}; color: ${p.status === 'DEPOSITED' ? '#10b981' : p.status === 'LEAVE_REQUESTED' ? '#f59e0b' : '#60a5fa'};">
                        ${p.status}
                      </span>
                    </div>
                  </div>
                `;
              }).join('')}
            </div>
          </div>

          <!-- Card: Biểu quyết Lịch chơi & Session -->
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;"> Biểu quyết Lịch Chơi (Schedule Voting)</h3>
            </div>
            <div id="proposal-section">
              ${this.renderProposalSection()}
            </div>
          </div>

          <!-- Card: Bảng tính giá & Khuyến mãi (Pricing Snapshot) -->
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;"> Hóa đơn & Bảng giá theo khung giờ</h3>
              <button id="btn-calc-price" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 12px;">
                 Tính toán lại giá
              </button>
            </div>
            <div id="pricing-summary-section">
              ${this.renderPricingSection()}
            </div>
          </div>
        </div>

        <!-- Right Column: Hướng dẫn & Thanh toán mô phỏng -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
          <!-- Card: Thanh toán cọc mô phỏng -->
          <div class="card" style="padding: 20px; border-left: 4px solid #10b981;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 10px 0; color: #10b981;">
               Thanh Toán Cọc Giữ Chỗ
            </h3>
            <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin: 0 0 16px 0;">
              Theo quy định V16, mức cọc bắt buộc là <strong>50.000 VNĐ / người</strong> để phòng được chốt lịch chính thức.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px;">
              <button id="btn-pay-deposit" class="btn btn-success" style="width: 100%; padding: 10px;">
                 Quét mã nộp cọc 50.000đ
              </button>
            </div>
          </div>

          <!-- Card: Quy định hoàn tiền 24h -->
          <div class="card" style="padding: 20px; border-left: 4px solid #f59e0b;">
            <h4 style="font-size: 0.95rem; font-weight: 700; margin: 0 0 8px 0; color: #f59e0b;">
              [Luu y] Quy tắc Vàng 24h & Rút phòng:
            </h4>
            <ul style="font-size: 0.85rem; color: var(--color-text-muted); margin: 0; padding-left: 18px; line-height: 1.6;">
              <li>Nếu hủy phòng trước <strong>24 tiếng</strong> so với giờ chơi: Đủ điều kiện hoàn cọc 100%.</li>
              <li>Nếu hủy phòng sau mốc 24 tiếng: Phạt tiền cọc để hỗ trợ cửa hàng & quản trò.</li>
              <li>Nếu có việc bận: Bấm "Rời phòng" để mở cửa sổ 24h tìm người khác vào thế chỗ.</li>
            </ul>
          </div>
        </div>
      </div>
    `;

    // Gắn sự kiện
    this.container.querySelector('#btn-back-lobby').onclick = () => this.render();

    const joinBtn = this.container.querySelector('#btn-join-room');
    if (joinBtn) {
      joinBtn.onclick = () => this.handleJoin();
    }

    const leaveBtn = this.container.querySelector('#btn-leave-room');
    if (leaveBtn) {
      leaveBtn.onclick = () => this.handleLeave();
    }

    const calcBtn = this.container.querySelector('#btn-calc-price');
    if (calcBtn) {
      calcBtn.onclick = () => this.handleCalculatePrice();
    }

    const payBtn = this.container.querySelector('#btn-pay-deposit');
    if (payBtn) {
      payBtn.onclick = () => this.handlePayDeposit();
    }

    this.bindProposalEvents();
  }

  renderProposalSection() {
    const proposals = this.selectedRoom?.proposals || [];
    const activeProposal = proposals.find(p => p.status === 'PROPOSED') || proposals[0];

    if (!activeProposal) {
      return `
        <div style="text-align: center; padding: 20px; background: rgba(255, 255, 255, 0.02); border-radius: 8px;">
          <p style="color: #94a3b8; font-size: 0.85rem; margin: 0 0 12px 0;">Chưa có đề xuất lịch chơi nào được mở.</p>
          <button id="btn-open-propose-modal" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 14px;">
             Tạo đề xuất mốc giờ chơi
          </button>
        </div>
      `;
    }

    const isConfirmed = activeProposal.status === 'CONFIRMED';
    const isProposed = activeProposal.status === 'PROPOSED';

    return `
      <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--color-border); border-radius: 8px; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <div>
            <span style="font-size: 0.8rem; color: #94a3b8;">Đề xuất bởi: ${activeProposal.proposer_name || 'Thành viên'}</span>
            <div style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-top: 4px;">
              ⏰ ${activeProposal.proposed_start_at} &rarr; ${activeProposal.proposed_end_at}
            </div>
          </div>
          <span class="badge" style="background: ${isConfirmed ? 'rgba(16, 185, 129, 0.2)' : 'rgba(59, 130, 246, 0.2)'}; color: ${isConfirmed ? '#10b981' : '#60a5fa'};">
            ${activeProposal.status}
          </span>
        </div>

        ${isConfirmed ? `
          <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 6px; padding: 10px; color: #10b981; font-size: 0.85rem; font-weight: 600;">
             100% thành viên đã đồng ý! Buổi chơi đã được chốt lịch chính thức vào hệ thống.
          </div>
        ` : ''}

        ${isProposed ? `
          <div style="display: flex; gap: 10px; margin-top: 14px; border-top: 1px solid var(--color-border); padding-top: 14px;">
            <button class="btn btn-success btn-vote" data-id="${activeProposal.id}" data-vote="ACCEPTED" style="flex: 1; padding: 8px;">
               Đồng ý mốc giờ này
            </button>
            <button class="btn btn-danger btn-vote" data-id="${activeProposal.id}" data-vote="REJECTED" style="flex: 1; padding: 8px;">
               Không tham gia được
            </button>
          </div>
        ` : ''}
      </div>
    `;
  }

  renderPricingSection() {
    const room = this.selectedRoom;
    if (!room.price_calculated_at) {
      return `
        <div style="color: #94a3b8; font-size: 0.85rem; padding: 12px 0;">
          Chưa có bảng tính giá. Bấm "Tính toán lại giá" để xem chi tiết tiền cọc 50.000đ và tổng chi phí.
        </div>
      `;
    }

    return `
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 14px;">
        <div style="background: rgba(255, 255, 255, 0.03); padding: 12px; border-radius: 6px;">
          <div style="font-size: 0.75rem; color: #94a3b8;">Tổng tiền gốc:</div>
          <div style="font-size: 1rem; font-weight: 700; color: #f8fafc;">${Number(room.price_subtotal || 0).toLocaleString()} đ</div>
        </div>
        <div style="background: rgba(255, 255, 255, 0.03); padding: 12px; border-radius: 6px;">
          <div style="font-size: 0.75rem; color: #94a3b8;">Giảm giá khuyến mãi:</div>
          <div style="font-size: 1rem; font-weight: 700; color: #10b981;">-${Number(room.price_discount_amount || 0).toLocaleString()} đ</div>
        </div>
        <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 6px; border: 1px solid #10b981;">
          <div style="font-size: 0.75rem; color: #10b981;">Tổng cọc (50k/người):</div>
          <div style="font-size: 1.1rem; font-weight: 700; color: #10b981;">${Number(room.price_deposit_total || 0).toLocaleString()} đ</div>
        </div>
        <div style="background: rgba(255, 255, 255, 0.03); padding: 12px; border-radius: 6px;">
          <div style="font-size: 0.75rem; color: #94a3b8;">Còn lại cần nộp:</div>
          <div style="font-size: 1rem; font-weight: 700; color: #f59e0b;">${Number(room.price_remaining_total || 0).toLocaleString()} đ</div>
        </div>
      </div>
    `;
  }

  bindProposalEvents() {
    const proposeBtn = this.container.querySelector('#btn-open-propose-modal');
    if (proposeBtn) {
      proposeBtn.onclick = () => this.showProposeModal();
    }

    this.container.querySelectorAll('.btn-vote').forEach(btn => {
      btn.onclick = async () => {
        const proposalId = btn.dataset.id;
        const vote = btn.dataset.vote;
        btn.disabled = true;
        try {
          const res = await api.post(`/proposals/${proposalId}/vote`, { response: vote });
          toast.success(res?.message || 'Ghi nhận biểu quyết thành công');
          await this.showRoomDetail(this.selectedRoom.id);
        } catch (err) {
          toast.error(err.message);
          btn.disabled = false;
        }
      };
    });
  }

  async handleJoin() {
    if (!authState.isAuthenticated()) {
      toast.info('Vui lòng đăng nhập để tham gia phòng chơi');
      this.onOpenAuthModal();
      return;
    }

    try {
      const res = await api.post(`/rooms/${this.selectedRoom.id}/join`);
      toast.success(res?.message || 'Tham gia phòng thành công!');
      await this.showRoomDetail(this.selectedRoom.id);
    } catch (err) {
      toast.error(err.message);
    }
  }

  async handleLeave() {
    if (!confirm('Bạn có chắc chắn muốn rút khỏi phòng này không?')) return;

    try {
      const res = await api.post(`/rooms/${this.selectedRoom.id}/leave`);
      toast.success(res?.message || 'Thao tác thành công');
      await this.showRoomDetail(this.selectedRoom.id);
    } catch (err) {
      toast.error(err.message);
    }
  }

  async handleCalculatePrice() {
    const promoCode = prompt('Nhập mã khuyến mãi (ví dụ: TUESDAY_30, HSSV_20000 hoặc để trống):', '');
    try {
      const res = await api.post(`/rooms/${this.selectedRoom.id}/calculate-price`, {
        promotion_code: promoCode ? promoCode.trim() : null,
      });
      toast.success('Đã tính toán và chốt hóa đơn snapshot thành công!');
      await this.showRoomDetail(this.selectedRoom.id);
    } catch (err) {
      toast.error(err.message);
    }
  }

  handlePayDeposit() {
    if (!authState.isAuthenticated()) {
      toast.info('Vui lòng đăng nhập để nộp cọc');
      this.onOpenAuthModal();
      return;
    }

    const modal = document.createElement('dialog');
    modal.className = 'custom-modal';
    modal.style.cssText = `
      background: var(--color-bg-card, #1e293b);
      color: var(--color-text-main, #f8fafc);
      border: 1px solid var(--color-border, #334155);
      border-radius: 12px;
      padding: 24px;
      width: 100%;
      max-width: 400px;
      text-align: center;
    `;

    modal.innerHTML = `
      <h3 style="margin: 0 0 10px 0; color: #10b981;">Cổng Thanh Toán Mô Phỏng (V16)</h3>
      <p style="font-size: 0.85rem; color: #94a3b8; margin: 0 0 16px 0;">Mã thanh toán cọc giữ chỗ: <strong>50.000 VNĐ</strong></p>
      
      <div style="background: #ffffff; padding: 16px; border-radius: 8px; display: inline-block; margin-bottom: 16px;">
        <!-- Simulated QR Code -->
        <div style="width: 160px; height: 160px; background: #000000; display: flex; align-items: center; justify-content: center; color: #ffffff; font-family: monospace; font-size: 0.75rem; text-align: center; padding: 10px; box-sizing: border-box;">
          [QR SIMULATED MOMO / VNPAY]
        </div>
      </div>

      <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 20px;">
        Hệ thống tự động sinh <code>X-Idempotency-Key</code> bảo vệ chống trùng lặp giao dịch.
      </div>

      <div style="display: flex; gap: 10px;">
        <button id="btn-cancel-pay" class="btn btn-secondary" style="flex: 1;">Hủy</button>
        <button id="btn-confirm-pay" class="btn btn-success" style="flex: 1;">Xác nhận nộp cọc</button>
      </div>
    `;

    modal.querySelector('#btn-cancel-pay').onclick = () => modal.close();
    modal.querySelector('#btn-confirm-pay').onclick = async () => {
      const confirmBtn = modal.querySelector('#btn-confirm-pay');
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Đang giao dịch...';
      try {
        toast.success('Giao dịch cọc 50.000 VNĐ thành công!');
        modal.close();
        await this.showRoomDetail(this.selectedRoom.id);
      } catch (err) {
        toast.error(err.message);
      } finally {
        confirmBtn.disabled = false;
      }
    };

    document.body.appendChild(modal);
    modal.showModal();
  }

  showCreateRoomModal(preselectedGame = null) {
    if (!authState.isAuthenticated()) {
      toast.info('Vui lòng đăng nhập để tạo phòng');
      this.onOpenAuthModal();
      return;
    }

    const modal = document.createElement('dialog');
    modal.className = 'custom-modal';
    modal.style.cssText = `
      background: var(--color-bg-card, #1e293b);
      color: var(--color-text-main, #f8fafc);
      border: 1px solid var(--color-border, #334155);
      border-radius: 12px;
      padding: 24px;
      width: 100%;
      max-width: 480px;
    `;

    modal.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 1.25rem;">Tạo phòng chơi mới</h3>
        <button id="create-room-close" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer;">&times;</button>
      </div>

      <form id="create-room-form" style="display: flex; flex-direction: column; gap: 14px;">
        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Kịch bản kịch bản sát</label>
          <select name="game_id" class="form-input" required style="width: 100%;">
            <option value="1">Đêm Đoạt Mệnh Tại Trang Viên (6 - 8 người)</option>
            <option value="2">Kỳ Án Quán Trọ Vong Hồn (7 - 9 người)</option>
            <option value="3">Vụ Mưu Sát Trên Du Thuyền Hoàng Gia (8 - 10 người)</option>
          </select>
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Tên phòng chơi</label>
          <input type="text" name="title" required placeholder="Ví dụ: Tìm đồng đội chơi tối thứ 7" class="form-input" style="width: 100%; box-sizing: border-box;">
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Mô tả / Lời nhắn</label>
          <textarea name="description" placeholder="Yêu cầu roleplay nghiêm túc, không out giữa chừng..." class="form-input" rows="3" style="width: 100%; box-sizing: border-box;"></textarea>
        </div>

        <button type="submit" id="btn-submit-create" class="btn btn-primary" style="margin-top: 10px;">
          Tạo phòng ngay
        </button>
      </form>
    `;

    modal.querySelector('#create-room-close').onclick = () => modal.close();

    const form = modal.querySelector('#create-room-form');
    if (preselectedGame) {
      form.querySelector('select[name="game_id"]').value = preselectedGame.id;
    }

    form.onsubmit = async (e) => {
      e.preventDefault();
      const btn = modal.querySelector('#btn-submit-create');
      btn.disabled = true;
      btn.textContent = 'Đang tạo phòng...';

      const formData = new FormData(form);
      const data = Object.fromEntries(formData.entries());

      try {
        const res = await api.post('/rooms', data);
        toast.success('Tạo phòng chơi thành công!');
        modal.close();
        if (res?.data?.room?.id) {
          await this.showRoomDetail(res.data.room.id);
        } else {
          await this.fetchRooms();
        }
      } catch (err) {
        toast.error(err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = 'Tạo phòng ngay';
      }
    };

    document.body.appendChild(modal);
    modal.showModal();
  }

  showProposeModal() {
    const modal = document.createElement('dialog');
    modal.className = 'custom-modal';
    modal.style.cssText = `
      background: var(--color-bg-card, #1e293b);
      color: var(--color-text-main, #f8fafc);
      border: 1px solid var(--color-border, #334155);
      border-radius: 12px;
      padding: 24px;
      width: 100%;
      max-width: 440px;
    `;

    // Gợi ý mốc thời gian 2 ngày sau vào 18h
    const defaultStart = new Date(Date.now() + 2 * 86400000);
    defaultStart.setHours(18, 0, 0, 0);
    const defaultEnd = new Date(defaultStart.getTime() + 4 * 3600000);

    const fmt = (d) => d.toISOString().slice(0, 19).replace('T', ' ');

    modal.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 1.2rem;">Đề xuất mốc thời gian chơi</h3>
        <button id="close-prop" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer;">&times;</button>
      </div>

      <form id="propose-form" style="display: flex; flex-direction: column; gap: 14px;">
        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Thời gian bắt đầu</label>
          <input type="text" name="proposed_start_at" value="${fmt(defaultStart)}" class="form-input" required style="width: 100%; box-sizing: border-box;">
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Thời gian kết thúc</label>
          <input type="text" name="proposed_end_at" value="${fmt(defaultEnd)}" class="form-input" required style="width: 100%; box-sizing: border-box;">
        </div>

        <button type="submit" id="btn-submit-prop" class="btn btn-primary" style="margin-top: 10px;">
          Mở biểu quyết mốc giờ
        </button>
      </form>
    `;

    modal.querySelector('#close-prop').onclick = () => modal.close();

    modal.querySelector('#propose-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = modal.querySelector('#btn-submit-prop');
      btn.disabled = true;
      const data = Object.fromEntries(new FormData(e.target).entries());

      try {
        await api.post(`/rooms/${this.selectedRoom.id}/proposals`, data);
        toast.success('Đã mở biểu quyết mốc giờ chơi mới cho phòng!');
        modal.close();
        await this.showRoomDetail(this.selectedRoom.id);
      } catch (err) {
        toast.error(err.message);
      } finally {
        btn.disabled = false;
      }
    };

    document.body.appendChild(modal);
    modal.showModal();
  }
}
