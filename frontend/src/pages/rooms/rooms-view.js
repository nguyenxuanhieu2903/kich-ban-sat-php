import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml } from '../../utils/sanitize.js';
import { ALL_SCRIPTS } from '../../data/scripts.data.js';
import { DialogService } from '../../utils/dialog-service.js';

export class RoomsView {
  constructor(container, onOpenAuthModal = () => {}) {
    this.container = container;
    this.onOpenAuthModal = onOpenAuthModal;
    this.rooms = [];
    this.allRooms = [];
    this.selectedRoom = null;
    this.currentTab = 'FORMING'; // FORMING | REPLACEMENT | SCHEDULED | ALL
    this.filterDate = '';
    this.filterScript = '';
  }

  async render() {
    this.selectedRoom = null;
    this.container.innerHTML = `
      <!-- HEADER TIÊU ĐỀ & NÚT TẠO PHÒNG -->
      <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
        <div>
          <div style="font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: 0.16em; color: var(--gold); text-transform: uppercase; margin-bottom: 6px;">
            GHÉP NHÓM TRỰC TUYẾN
          </div>
          <h1 style="font-family: var(--font-display); font-size: clamp(1.8rem, 3.8vw, 2.4rem); font-weight: 800; margin: 0; color: #f8fafc;">
            Sảnh Phòng Chơi
          </h1>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
          <a href="/player.html" class="btn btn-ghost" style="padding: 10px 16px; font-size: 0.88rem; border: 1px solid rgba(59, 130, 246, 0.4); color: #93c5fd; text-decoration: none; border-radius: 6px;">
            👤 Hồ Sơ &amp; Vé Cọc &rarr;
          </a>
          <button id="btn-open-create-room" class="btn btn-gold" style="padding: 10px 18px; font-weight: 700; font-size: 0.9rem;">
            + Tạo phòng mới
          </button>
        </div>
      </div>

      <!-- THANH TABS THEO TRẠNG THÁI HỆ THỐNG -->
      <div class="tabs-bar" id="lobby-tabs-bar" style="margin-bottom: 20px;">
        <button class="tab-btn ${this.currentTab === 'FORMING' ? 'active' : ''}" data-tab="FORMING">
          Đang ghép (Forming)
        </button>
        <button class="tab-btn ${this.currentTab === 'SCHEDULED' ? 'active' : ''}" data-tab="SCHEDULED">
          Đã chốt lịch (Scheduled)
        </button>
        <button class="tab-btn ${this.currentTab === 'ALL' ? 'active' : ''}" data-tab="ALL">
          Tất cả phòng
        </button>
      </div>

      <!-- BỘ LỌC NGÀY & KỊCH BẢN -->
      <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; align-items: center;">
        <input 
          type="date" 
          id="lobby-date" 
          class="form-control" 
          style="max-width: 210px; padding: 9px 12px; font-size: 0.88rem;" 
          value="${this.filterDate}"
        />
        <select id="lobby-script" class="form-control" style="max-width: 280px; padding: 9px 12px; font-size: 0.88rem;">
          <option value="">Tất cả kịch bản</option>
          ${ALL_SCRIPTS.slice(0, 15).map(s => `
            <option value="${escapeHtml(s.title)}" ${this.filterScript === s.title ? 'selected' : ''}>
              ${escapeHtml(s.title)}
            </option>
          `).join('')}
        </select>
        <button class="btn btn-ghost btn-sm" id="lobby-clear" style="padding: 9px 14px;">
          Xóa lọc
        </button>
      </div>

      <!-- GRID PHÒNG CHƠI -->
      <div class="rooms-grid" id="lobby-grid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">
          Đang nạp danh sách phòng chơi...
        </div>
      </div>
    `;

    this.bindLobbyEvents();
    await this.fetchRooms();
  }

  bindLobbyEvents() {
    // Tab switching
    this.container.querySelectorAll('#lobby-tabs-bar .tab-btn').forEach(btn => {
      btn.onclick = () => {
        this.currentTab = btn.dataset.tab;
        this.container.querySelectorAll('#lobby-tabs-bar .tab-btn').forEach(b => {
          b.classList.toggle('active', b === btn);
        });
        this.renderLobbyGrid();
      };
    });

    // Date filter
    const dateInput = this.container.querySelector('#lobby-date');
    dateInput?.addEventListener('change', (e) => {
      this.filterDate = e.target.value;
      this.renderLobbyGrid();
    });

    // Script filter
    const scriptSelect = this.container.querySelector('#lobby-script');
    scriptSelect?.addEventListener('change', (e) => {
      this.filterScript = e.target.value;
      this.renderLobbyGrid();
    });

    // Clear filters
    this.container.querySelector('#lobby-clear')?.addEventListener('click', () => {
      this.filterDate = '';
      this.filterScript = '';
      const dInput = this.container.querySelector('#lobby-date');
      const sSelect = this.container.querySelector('#lobby-script');
      if (dInput) dInput.value = '';
      if (sSelect) sSelect.value = '';
      this.renderLobbyGrid();
    });

    // Create room button
    this.container.querySelector('#btn-open-create-room')?.addEventListener('click', () => {
      this.showCreateRoomModal();
    });
  }

  async fetchRooms() {
    try {
      const res = await api.get('/rooms?status=ALL');
      const apiRooms = res?.data?.rooms || [];

      // Sử dụng 100% phòng thật từ MySQL (không chèn phòng giả)
      const curatedFallbacks = [];

      // Chuẩn hóa danh sách phòng từ DB
      const normalizedDbRooms = apiRooms.map(r => {
        const isRepl = r.replacement_status === 'OPEN';
        let minsLeft = 15;
        if (r.formation_deadline_at) {
          const diff = Math.round((new Date(r.formation_deadline_at) - Date.now()) / 60000);
          if (diff > 0) minsLeft = diff;
        }
        return {
          ...r,
          id: String(r.id),
          game_name: r.game_name || r.title || 'Kịch bản sát',
          creator_name: r.creator_name || 'Người chơi',
          current_players: Number(r.current_players) || 1,
          max_players: Number(r.max_players) || 8,
          status: r.status || 'FORMING',
          replacement_status: r.replacement_status || 'NONE',
          date: r.date || '2026-11-24',
          timeSlot: r.timeSlot || '19:00',
          minsLeft
        };
      });

      // Kết hợp các phòng từ DB và phòng mẫu (ưu tiên phòng từ DB)
      const merged = [...normalizedDbRooms];
      for (const fb of curatedFallbacks) {
        if (!merged.some(m => String(m.id) === String(fb.id))) {
          merged.push(fb);
        }
      }

      this.allRooms = merged;
      this.renderLobbyGrid();
    } catch (err) {
      const grid = this.container.querySelector('#lobby-grid');
      if (grid) {
        grid.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-error);">${escapeHtml(err.message)}</div>`;
      }
    }
  }

  renderLobbyGrid() {
    const grid = this.container.querySelector('#lobby-grid');
    if (!grid) return;

    // Lọc theo Tab trạng thái
    let list = this.allRooms.filter(r => {
      // Ẩn các phòng đã hủy hoặc lỗi test trừ khi ở tab ALL
      if (this.currentTab !== 'ALL' && (r.status === 'CANCELLED' || r.status === 'EXPIRED')) {
        return false;
      }

      if (this.currentTab === 'FORMING') {
        return r.status === 'FORMING' && r.replacement_status !== 'OPEN';
      }
      if (this.currentTab === 'REPLACEMENT') {
        return r.replacement_status === 'OPEN';
      }
      if (this.currentTab === 'SCHEDULED') {
        return ['SCHEDULED', 'FORMED', 'IN_PROGRESS', 'CONFIRMED'].includes(r.status);
      }
      return true;
    });

    // Lọc theo ngày
    if (this.filterDate) {
      list = list.filter(r => r.date === this.filterDate);
    }

    // Lọc theo kịch bản
    if (this.filterScript) {
      const term = this.filterScript.toLowerCase();
      list = list.filter(r => (r.game_name || '').toLowerCase().includes(term) || (r.title || '').toLowerCase().includes(term));
    }

    if (list.length === 0) {
      grid.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; padding: 54px 20px; background: var(--bg-surface); border-radius: 8px; border: 1px dashed var(--gold-border);">
          <div style="font-size: 2.2rem; margin-bottom: 10px;">🎲</div>
          <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 6px; color: #f8fafc;">
            Không có phòng nào khớp điều kiện lọc
          </h3>
          <p class="text-muted" style="font-size: 0.88rem; margin-bottom: 16px;">
            Hãy thử chọn ngày khác hoặc đổi danh mục trạng thái phòng.
          </p>
          <button class="btn btn-gold btn-sm" id="btn-empty-create-sub">Tạo phòng mới ngay</button>
        </div>
      `;
      grid.querySelector('#btn-empty-create-sub')?.addEventListener('click', () => {
        this.showCreateRoomModal();
      });
      return;
    }

    grid.innerHTML = list.map(r => {
      const filled = r.current_players;
      const max = r.max_players;
      const pct = max > 0 ? Math.min(100, Math.round((filled / max) * 100)) : 50;
      const isRepl = r.replacement_status === 'OPEN';

      // Badge cấu hình theo trạng thái
      let badgeHtml = '';
      if (isRepl) {
        badgeHtml = `<span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444;">🔥 Cần thế chỗ</span>`;
      } else if (r.status === 'FORMING') {
        badgeHtml = `<span class="badge badge-green">Đang ghép</span>`;
      } else if (['SCHEDULED', 'CONFIRMED', 'FORMED'].includes(r.status)) {
        badgeHtml = `<span class="badge badge-gold">Đã chốt lịch</span>`;
      } else {
        badgeHtml = `<span class="badge badge-dark">${r.status}</span>`;
      }

      return `
        <article class="room-card player-room-card" data-id="${r.id}" style="background: var(--portal-surface, var(--bg-surface)); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; gap: 16px; transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;">
          <div>
            <!-- Hàng trên: Badge trạng thái & Mã phòng -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
              ${badgeHtml}
              <span style="font-family: var(--font-mono); color: var(--gold); font-size: 0.84rem; font-weight: 700;">
                #KBS-${r.id}
              </span>
            </div>

            <!-- Tên kịch bản / Tiêu đề phòng -->
            <h3 style="font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; margin: 0 0 6px 0; color: #f8fafc; line-height: 1.3;">
              ${escapeHtml(r.game_name || r.title)}
            </h3>

            <!-- Chủ phòng -->
            <p class="text-muted" style="font-size: 0.85rem; margin: 0 0 8px 0;">
              Chủ phòng: <strong style="color: #e2e8f0;">${escapeHtml(r.creator_name)}</strong>
            </p>

            <!-- Lịch chơi & Giờ -->
            <p style="font-size: 0.86rem; margin: 0 0 6px 0; color: #cbd5e1;">
              📅 ${r.date} • ⏰ ${r.timeSlot}
            </p>

            <!-- Đồng hồ giữ chỗ còn lại -->
            <p style="font-size: 0.82rem; color: var(--gold); margin: 0;">
              ⏳ Giữ chỗ còn ~${r.minsLeft} phút
            </p>
          </div>

          <!-- Tầng dưới: Tiến độ ghế & Nút xem chi tiết / tham gia -->
          <div>
            <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 6px;">
              <span style="color: #94a3b8;">Tiến độ ghế</span>
              <strong style="color: var(--gold); font-weight: 700;">${filled}/${max} chỗ đã cọc</strong>
            </div>

            <!-- Thanh tiến trình ghế -->
            <div class="progress-track" style="height: 7px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden; margin-top: 6px;">
              <div class="progress-fill" style="width: ${pct}%; height: 100%; background: linear-gradient(90deg, var(--crimson), var(--gold));"></div>
            </div>

            <!-- Nút Hành động full-width -->
            ${filled >= max ? `
              <button class="btn btn-ghost btn-sm" disabled style="width: 100%; margin-top: 14px; font-weight: 700; opacity: 0.6; cursor: not-allowed; padding: 9px 14px;">
                🔒 Đã đủ người (Không thể vào)
              </button>
            ` : `
              <button class="btn btn-sm btn-header-crimson btn-action-room" data-id="${r.id}" style="width: 100%; margin-top: 14px; font-weight: 700; padding: 9px 14px;">
                Xem chi tiết / Tham gia
              </button>
            `}
          </div>
        </article>
      `;
    }).join('');

    // Sự kiện click mở chi tiết phòng (chỉ cho phòng chưa đủ người)
    grid.querySelectorAll('.btn-action-room').forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();
        this.showRoomDetail(btn.dataset.id);
      };
    });
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
    const currentUser = authState.getUser();
    const isHost = currentUser && (Number(currentUser.id) === Number(room.creator_id) || currentUser.role === 'ADMIN');
    const participants = room.participants || [];
    const canKick = isHost && ['FORMING', 'SCHEDULING', 'VOTING'].includes(room.status);
    const myParticipant = currentUser ? participants.find(p => Number(p.user_id) === Number(currentUser.id)) : null;
    const isJoined = myParticipant && ['PENDING_DEPOSIT', 'DEPOSITED', 'LEAVE_REQUESTED'].includes(myParticipant.status);
    const isReplacementOpen = room.replacement_status === 'OPEN';
    const isScheduled = ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED'].includes(room.status);
    const hasDeposited = myParticipant && ['DEPOSITED', 'LEAVE_REQUESTED'].includes(myParticipant.status);
    const isRemainingPaid = myParticipant && myParticipant.remaining_payment_status === 'PAID';
    const remainingAmount = myParticipant?.snapshot_remaining_amount !== undefined && myParticipant.snapshot_remaining_amount !== null
      ? Number(myParticipant.snapshot_remaining_amount)
      : (room.price_remaining_total && room.current_players ? Math.round(Number(room.price_remaining_total) / room.current_players) : 0);
    const remainingFormatted = Number(remainingAmount).toLocaleString() + ' đ';

    const isStep1 = room.status === 'FORMING';
    const isStep2 = room.status === 'VOTING' || (room.status === 'FORMED' && !isScheduled);
    const isStep3 = isScheduled && !hasDeposited;
    const isStep4 = isScheduled && hasDeposited;

    this.container.innerHTML = `
      <!-- 4-STEP BOOKING WORKFLOW INDICATOR -->
      <div class="kbs-steps-row">
        <div class="kbs-step-item ${isStep1 ? 'active' : (isStep2 || isStep3 || isStep4 ? 'completed' : '')}">
          <span class="kbs-step-num">${isStep2 || isStep3 || isStep4 ? '✓' : '1'}</span>
          <span>1. Gom người</span>
        </div>
        <span class="kbs-step-separator">&rarr;</span>
        <div class="kbs-step-item ${isStep2 ? 'active' : (isStep3 || isStep4 ? 'completed' : '')}">
          <span class="kbs-step-num">${isStep3 || isStep4 ? '✓' : '2'}</span>
          <span>2. Chốt lịch &amp; Quán</span>
        </div>
        <span class="kbs-step-separator">&rarr;</span>
        <div class="kbs-step-item ${isStep3 ? 'active' : (isStep4 ? 'completed' : '')}">
          <span class="kbs-step-num">${isStep4 ? '✓' : '3'}</span>
          <span>3. Đặt cọc 50.000đ</span>
        </div>
        <span class="kbs-step-separator">&rarr;</span>
        <div class="kbs-step-item ${isStep4 ? 'active' : ''}">
          <span class="kbs-step-num">4</span>
          <span>4. Nhận vé QR &amp; Chơi</span>
        </div>
      </div>

      <!-- 4-HOUR COUNTDOWN WIDGET -->
      ${room.status === 'FORMING' ? `
        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">⏳</span>
            <div>
              <strong style="color: #f59e0b; font-size: 0.95rem;">Hạn Giữ Chỗ Tự Động: 4 Giờ</strong>
              <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: #cbd5e1;">Phòng cần gom đủ ${room.max_players} người và đặt cọc 50.000đ trước khi hết hạn để tránh tự động giải tán.</p>
            </div>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-family: var(--font-mono); font-weight: 700; color: #f59e0b; font-size: 1.1rem;">~3 giờ 45 phút còn lại</span>
          </div>
        </div>
      ` : ''}

      <div style="margin-bottom: 20px;">
        <button id="btn-back-lobby" class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.85rem; margin-bottom: 16px;">
          &larr; Quay lại danh sách phòng
        </button>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
          <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
              <h2 style="font-size: 1.6rem; font-weight: 700; margin: 0;">${escapeHtml(room.title)}</h2>
              <span class="badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid #3b82f6;">
                ${escapeHtml(room.status)}
              </span>
              ${isReplacementOpen ? `
                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444;">
                  🔥 CẦN NGƯỜI THAY THẾ
                </span>
              ` : ''}
            </div>
            <p style="color: var(--color-text-muted); margin: 0; font-size: 0.9rem;">
              Kịch bản: <strong style="color: #38bdf8;">${escapeHtml(room.game?.name || 'Kịch bản sát')}</strong> (${Number(room.game?.duration_minutes) || 240} phút) | Host: <strong>${escapeHtml(room.creator_name || 'Host')}</strong>
            </p>
          </div>

          <!-- Action Buttons -->
          <div style="display: flex; gap: 10px;">
            ${!isJoined ? `
              <button id="btn-join-room" class="btn btn-primary" style="padding: 10px 20px;">
                ${isReplacementOpen ? '⚡ Vào thế chỗ (Takeover)' : '➕ Tham gia phòng'}
              </button>
            ` : `
              <button id="btn-leave-room" class="btn btn-danger" style="padding: 10px 20px;">
                🚪 Rời phòng / Tìm người thay thế
              </button>
            `}
          </div>
        </div>
      </div>

      <div class="kbs-room-detail-grid">
        <!-- Left Column: Thành viên & Biểu quyết & Giá vé -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
          <!-- Card: Danh sách thành viên -->
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;">
                👥 Thành viên tham gia (${participants.length} / ${room.max_players})
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
                          ${escapeHtml(p.name || p.user_name || 'Người chơi')} ${isMe ? '(Bạn)' : ''}
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8;">${escapeHtml(p.email || '')}</div>
                      </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <span class="badge" style="font-size: 0.75rem; background: ${p.status === 'DEPOSITED' ? 'rgba(16, 185, 129, 0.2)' : p.status === 'LEAVE_REQUESTED' ? 'rgba(245, 158, 11, 0.2)' : 'rgba(59, 130, 246, 0.2)'}; color: ${p.status === 'DEPOSITED' ? '#10b981' : p.status === 'LEAVE_REQUESTED' ? '#f59e0b' : '#60a5fa'}; border: 1px solid ${p.status === 'DEPOSITED' ? 'rgba(16, 185, 129, 0.4)' : p.status === 'LEAVE_REQUESTED' ? 'rgba(245, 158, 11, 0.4)' : 'rgba(59, 130, 246, 0.4)'};">
                        ${p.status === 'DEPOSITED' ? '✓ Đã cọc 50k' : (p.status === 'LEAVE_REQUESTED' ? 'Chờ thế chỗ' : escapeHtml(p.status))}
                      </span>
                      ${false && canKick && !isMe ? `
                        <button class="btn btn-sm btn-kick-member" data-kick-user="${escapeHtml(p.user_id)}" data-user-name="${escapeHtml(p.name || p.user_name || 'Người chơi')}" style="padding: 6px 12px; min-height: 38px; font-size: 0.82rem; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                          Mời ra
                        </button>
                      ` : ''}
                    </div>
                  </div>
                `;
              }).join('')}
            </div>
          </div>

          <!-- Card: Đề xuất Lịch chơi & Địa điểm -->
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;">📅 Đề xuất Lịch Chơi &amp; Địa Điểm</h3>
            </div>
            <div id="proposal-section">
              ${this.renderProposalSection()}
            </div>
          </div>

          <!-- Card: Bảng tính giá & Khuyến mãi (Pricing Snapshot) -->
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;">💰 Hóa đơn & Bảng giá theo khung giờ</h3>
              <button id="btn-calc-price" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 12px;">
                🔄 Tính toán lại giá
              </button>
            </div>
            <div id="pricing-summary-section">
              ${this.renderPricingSection()}
            </div>
          </div>
        </div>

        <!-- Right Column: Hướng dẫn & Thanh toán mô phỏng -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
          <!-- Card: Thanh toán cọc mô phỏng (Hiển thị khi chưa cọc) -->
          ${!hasDeposited ? `
          <div class="card" style="padding: 20px; border-left: 4px solid #10b981;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 10px 0; color: #10b981;">
              💳 Thanh Toán Cọc Giữ Chỗ
            </h3>
            <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin: 0 0 16px 0;">
              Theo quy định V16, mức cọc bắt buộc là <strong>50.000 VNĐ / người</strong> để phòng được chốt lịch chính thức.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px;">
              <button id="btn-pay-deposit" class="btn btn-success" style="width: 100%; padding: 10px;">
                📲 Quét mã nộp cọc 50.000đ
              </button>
            </div>
          </div>
          ` : ''}

          <!-- DEF-27: Card Thanh toán phần còn lại (Hiển thị khi phòng SCHEDULED và đã cọc) -->
          ${isScheduled && hasDeposited ? `
            ${isRemainingPaid ? `
              <div class="card" style="padding: 20px; border-left: 4px solid #10b981;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0 0 10px 0; color: #10b981;">
                  ✅ Đã Hoàn Tất Toàn Bộ Chi Phí
                </h3>
                <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin: 0 0 10px 0;">
                  Bạn đã thanh toán đủ 100% chi phí (cọc + số tiền còn lại). Hãy sẵn sàng cho buổi chơi phá án!
                </p>
                <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 6px; padding: 10px; color: #10b981; font-size: 0.85rem; font-weight: 600;">
                  🎉 100% hoàn tất thanh toán
                </div>
              </div>
            ` : `
              <div class="card" style="padding: 20px; border-left: 4px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                  <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: #f59e0b;">
                    💳 Thanh Toán Số Tiền Còn Lại
                  </h3>
                  <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.75rem;">
                    CHỜ THANH TOÁN
                  </span>
                </div>
                <p style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5; margin: 0 0 14px 0;">
                  Buổi chơi đã được chốt lịch chính thức! Bạn có thể thanh toán trước số tiền còn lại qua cổng thanh toán mô phỏng V16.
                </p>
                <div style="background: rgba(255, 255, 255, 0.03); padding: 12px; border-radius: 6px; margin-bottom: 14px; border: 1px solid var(--color-border);">
                  <div style="font-size: 0.75rem; color: #94a3b8;">Số tiền còn lại cần nộp:</div>
                  <div style="font-size: 1.25rem; font-weight: 700; color: #f59e0b; margin-top: 2px;">
                    ${remainingFormatted}
                  </div>
                </div>
                <button id="btn-pay-remaining" class="btn btn-warning" style="width: 100%; padding: 10px; font-weight: 700;">
                  💳 Thanh toán số tiền còn lại (${remainingFormatted})
                </button>
              </div>
            `}
          ` : ''}

          <!-- Card: Quy định hoàn tiền 24h -->
          <div class="card" style="padding: 20px; border-left: 4px solid #f59e0b;">
            <h4 style="font-size: 0.95rem; font-weight: 700; margin: 0 0 8px 0; color: #f59e0b;">
              ⚠️ Quy tắc Vàng 24h & Rút phòng:
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

    const payRemainingBtn = this.container.querySelector('#btn-pay-remaining');
    if (payRemainingBtn) {
      payRemainingBtn.onclick = () => this.handlePayRemaining();
    }

    this.container.querySelectorAll('.btn-kick-member').forEach(btn => {
      btn.onclick = async () => {
        const targetUserId = btn.dataset.kickUser;
        const targetName = btn.dataset.userName;
        const reason = await DialogService.prompt(
          'Mời thành viên rời phòng',
          `Nhập lý do mời thành viên "${targetName}" ra khỏi phòng:`,
          'Không nộp cọc / Không phản hồi'
        );
        if (reason !== null) {
          try {
            const res = await api.post(`/rooms/${room.id}/kick`, {
              user_id: targetUserId,
              reason: reason.trim() || 'Chủ phòng mời ra khỏi phòng',
            });
            toast.success(res.message || 'Đã mời thành viên ra khỏi phòng');
            this.openRoomDetails(room.id);
          } catch (err) {
            toast.error(err.message || 'Không thể mời thành viên ra khỏi phòng');
          }
        }
      };
    });

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
            🕒 Tạo đề xuất mốc giờ chơi
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
            <span style="font-size: 0.8rem; color: #94a3b8;">Đề xuất bởi: ${escapeHtml(activeProposal.proposer_name || 'Thành viên')}</span>
            <div style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-top: 4px;">
              ⏰ ${escapeHtml(activeProposal.proposed_start_at)} &rarr; ${escapeHtml(activeProposal.proposed_end_at)}
            </div>
          </div>
          <span class="badge" style="background: ${isConfirmed ? 'rgba(16, 185, 129, 0.2)' : 'rgba(59, 130, 246, 0.2)'}; color: ${isConfirmed ? '#10b981' : '#60a5fa'};">
            ${escapeHtml(activeProposal.status)}
          </span>
        </div>

        ${isConfirmed ? `
          <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; border-radius: 6px; padding: 10px; color: #10b981; font-size: 0.85rem; font-weight: 600;">
            🎉 100% thành viên đã đồng ý! Buổi chơi đã được chốt lịch chính thức vào hệ thống.
          </div>
        ` : ''}

        ${isProposed ? `
          <div style="display: flex; gap: 10px; margin-top: 14px; border-top: 1px solid var(--color-border); padding-top: 14px;">
            <button class="btn btn-success btn-vote" data-id="${activeProposal.id}" data-vote="ACCEPTED" style="flex: 1; padding: 8px;">
              👍 Đồng ý mốc giờ này
            </button>
            <button class="btn btn-danger btn-vote" data-id="${activeProposal.id}" data-vote="REJECTED" style="flex: 1; padding: 8px;">
              👎 Không tham gia được
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

    const currentUserId = authState.user ? authState.user.id : null;
    const myParticipant = this.selectedRoom && this.selectedRoom.participants
      ? this.selectedRoom.participants.find(p => p.user_id == currentUserId)
      : null;

    if (!myParticipant) {
      toast.error('Bạn chưa tham gia phòng này');
      return;
    }

    if (myParticipant.status === 'DEPOSITED') {
      toast.info('Bạn đã hoàn tất nộp cọc cho phòng này rồi');
      return;
    }

    if (!myParticipant.payment_id) {
      toast.warning('Phòng chưa chuyển sang giai đoạn biểu quyết lịch, chưa có hóa đơn cọc');
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
        const idempKey = 'DEP_' + myParticipant.payment_id + '_' + Date.now();
        const res = await api.post(`/payments/${myParticipant.payment_id}/deposit`, {
          payment_method: 'SIMULATED_MOMO',
          idempotency_key: idempKey,
        });

        if (res.success) {
          toast.success('Giao dịch cọc 50.000 VNĐ thành công!');
          modal.close();
          await this.showRoomDetail(this.selectedRoom.id);
        } else {
          toast.error(res.error?.message || 'Thanh toán cọc không thành công');
        }
      } catch (err) {
        toast.error(err.message || 'Lỗi kết nối khi thanh toán');
      } finally {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Xác nhận nộp cọc';
      }
    };

    document.body.appendChild(modal);
    modal.showModal();
  }

  handlePayRemaining() {
    if (!authState.isAuthenticated()) {
      toast.info('Vui lòng đăng nhập để thanh toán');
      this.onOpenAuthModal();
      return;
    }

    const currentUserId = authState.user ? authState.user.id : null;
    const myParticipant = this.selectedRoom && this.selectedRoom.participants
      ? this.selectedRoom.participants.find(p => Number(p.user_id) === Number(currentUserId))
      : null;

    if (!myParticipant) {
      toast.error('Bạn chưa tham gia phòng này');
      return;
    }

    if (myParticipant.remaining_payment_status === 'PAID') {
      toast.info('Bạn đã hoàn tất thanh toán toàn bộ chi phí cho phòng này');
      return;
    }

    const targetPaymentId = myParticipant.remaining_payment_id || myParticipant.payment_id;
    if (!targetPaymentId) {
      toast.warning('Chưa có hóa đơn thanh toán cho người chơi');
      return;
    }

    const remainingAmount = myParticipant?.snapshot_remaining_amount !== undefined && myParticipant.snapshot_remaining_amount !== null
      ? Number(myParticipant.snapshot_remaining_amount)
      : (this.selectedRoom.price_remaining_total && this.selectedRoom.current_players ? Math.round(Number(this.selectedRoom.price_remaining_total) / this.selectedRoom.current_players) : 0);
    const remainingFormatted = Number(remainingAmount).toLocaleString() + ' đ';

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
      <h3 style="margin: 0 0 10px 0; color: #f59e0b;">Cổng Thanh Toán Chi Phí Còn Lại (V16)</h3>
      <p style="font-size: 0.85rem; color: #94a3b8; margin: 0 0 16px 0;">Mã thanh toán phần còn lại: <strong>${remainingFormatted}</strong></p>
      
      <div style="background: #ffffff; padding: 16px; border-radius: 8px; display: inline-block; margin-bottom: 16px;">
        <!-- Simulated QR Code -->
        <div style="width: 160px; height: 160px; background: #000000; display: flex; align-items: center; justify-content: center; color: #ffffff; font-family: monospace; font-size: 0.75rem; text-align: center; padding: 10px; box-sizing: border-box;">
          [QR SIMULATED VNPAY]
        </div>
      </div>

      <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 20px;">
        Hệ thống tự động sinh <code>X-Idempotency-Key</code> bảo vệ chống trùng lặp giao dịch.
      </div>

      <div style="display: flex; gap: 10px;">
        <button id="btn-cancel-pay-rem" class="btn btn-secondary" style="flex: 1;">Hủy</button>
        <button id="btn-confirm-pay-rem" class="btn btn-warning" style="flex: 1;">Xác nhận thanh toán</button>
      </div>
    `;

    modal.querySelector('#btn-cancel-pay-rem').onclick = () => modal.close();
    modal.querySelector('#btn-confirm-pay-rem').onclick = async () => {
      const confirmBtn = modal.querySelector('#btn-confirm-pay-rem');
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Đang giao dịch...';
      try {
        const idempKey = 'REM_' + targetPaymentId + '_' + Date.now();
        const res = await api.post(`/payments/${targetPaymentId}/remaining`, {
          payment_method: 'SIMULATED_VNPAY',
          idempotency_key: idempKey,
        });

        if (res.success) {
          toast.success(res.message || 'Thanh toán số tiền còn lại thành công!');
          modal.close();
          await this.showRoomDetail(this.selectedRoom.id);
        } else {
          toast.error(res.error?.message || 'Thanh toán không thành công');
        }
      } catch (err) {
        toast.error(err.message || 'Lỗi kết nối khi thanh toán');
      } finally {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Xác nhận thanh toán';
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

    const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    const scriptOptionsHtml = (ALL_SCRIPTS || []).map((s, idx) => {
      const pText = s.playersText || `${s.playersMin || 6}–${s.playersMax || 8} người`;
      return `<option value="${idx + 1}" data-min="${s.playersMin || 6}" data-max="${s.playersMax || 8}">${escapeHtml(s.title)} (${escapeHtml(pText)})</option>`;
    }).join('');

    modal.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 1.25rem;">Tạo phòng chơi mới</h3>
        <button id="create-room-close" type="button" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer;">&times;</button>
      </div>

      <form id="create-room-form" style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Kịch bản (số người cố định theo game)</label>
          <select name="game_id" id="cr-game-select" class="form-input" required style="width: 100%;">
            ${scriptOptionsHtml}
          </select>
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Chi nhánh tổ chức</label>
          <select name="venue_id" class="form-input" required style="width: 100%;">
            <option value="1">Kịch Bản Sát — Bình Thạnh (36/52 Nguyễn Gia Trí)</option>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Ngày chơi dự kiến</label>
            <input type="date" name="play_date" required value="${tomorrow}" class="form-input" style="width: 100%; box-sizing: border-box;">
          </div>
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Giờ chơi tự chọn</label>
            <input type="time" name="play_time" required value="19:00" class="form-input" style="width: 100%; box-sizing: border-box;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 10px;">
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Tên phòng chơi</label>
            <input type="text" name="title" required placeholder="VD: Phá án tối Thứ 7" class="form-input" style="width: 100%; box-sizing: border-box;">
          </div>
          <div>
            <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Số chỗ đặt cùng lúc</label>
            <input type="number" name="slots_count" min="1" max="8" value="1" required class="form-input" style="width: 100%; box-sizing: border-box;">
          </div>
        </div>

        <div>
          <label style="font-size: 0.85rem; color: #94a3b8; display: block; margin-bottom: 4px;">Ghi chú cho Quản trò (DM)</label>
          <textarea name="description" placeholder="VD: Nhóm có bạn chơi lần đầu, cần DM hướng dẫn kỹ..." class="form-input" rows="2" style="width: 100%; box-sizing: border-box;"></textarea>
        </div>

        <button type="submit" id="btn-submit-create" class="btn btn-primary" style="margin-top: 6px;">
          Tạo phòng ngay
        </button>
      </form>
    `;

    modal.querySelector('#create-room-close').onclick = () => modal.close();

    const form = modal.querySelector('#create-room-form');
    if (preselectedGame) {
      const select = form.querySelector('select[name="game_id"]');
      if (select) {
        const gameId = preselectedGame.dbId || preselectedGame.id;
        const optExists = Array.from(select.options).some(o => o.value == gameId);
        if (!optExists) {
          const newOpt = document.createElement('option');
          newOpt.value = gameId;
          newOpt.textContent = `${preselectedGame.title || preselectedGame.name} (${preselectedGame.playersMin || 6} - ${preselectedGame.playersMax || 8} người)`;
          select.appendChild(newOpt);
        }
        select.value = gameId;
      }
      const titleInput = form.querySelector('input[name="title"]');
      if (titleInput && !titleInput.value) {
        titleInput.value = `Tuyển đồng đội: ${preselectedGame.title || preselectedGame.name}`;
      }
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
