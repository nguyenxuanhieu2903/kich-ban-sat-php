/**
 * Demo API Implementation
 * Mirrors the live API signatures exactly, powered by the single shared-store.
 * Simulates real-world network latency (300-600ms) and provides consistent envelope responses.
 */
import { sharedStore } from './shared-store.js';
import { simulateQuote } from './quote-simulator.js';
import { auth } from '../../utils/auth.js';

function delay(ms = 350) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export const demoApi = {
  // --- Game Catalog ---
  async getGames(filters = {}) {
    await delay(250);
    let list = [...sharedStore.data.games];

    if (filters.search) {
      const q = filters.search.toLowerCase().trim();
      list = list.filter((g) => g.title.toLowerCase().includes(q) || g.genre.toLowerCase().includes(q));
    }
    if (filters.genre) {
      list = list.filter((g) => g.genre === filters.genre);
    }
    if (filters.is18Only) {
      list = list.filter((g) => g.is_age_restricted);
    }
    if (filters.players) {
      const p = parseInt(filters.players, 10);
      list = list.filter((g) => g.min_players <= p && g.max_players >= p);
    }

    return {
      success: true,
      data: list,
      total: list.length,
      demo_notice: 'DỮ LIỆU DEMO: Danh mục 42 kịch bản với thông số minh họa.',
    };
  },

  async getGameById(id) {
    await delay(200);
    const game = sharedStore.data.games.find((g) => g.id === id);
    if (!game) {
      throw new Error(`Kịch bản với mã "${id}" không tồn tại.`);
    }
    return {
      success: true,
      data: game,
    };
  },

  // --- GameRooms ---
  async getPublicRooms(filters = {}) {
    await delay(300);
    let rooms = [...sharedStore.data.gameRooms];

    if (filters.status) {
      rooms = rooms.filter((r) => r.status === filters.status);
    }
    if (filters.gameId) {
      rooms = rooms.filter((r) => r.game_id === filters.gameId);
    }

    return {
      success: true,
      data: rooms,
      total: rooms.length,
    };
  },

  async getRoomById(roomId) {
    await delay(250);
    const room = sharedStore.data.gameRooms.find((r) => r.id === roomId);
    if (!room) {
      throw new Error(`Phòng chơi "${roomId}" không tồn tại.`);
    }
    const game = sharedStore.data.games.find((g) => g.id === room.game_id);
    return {
      success: true,
      data: {
        ...room,
        game_details: game,
      },
    };
  },

  async createRoom(payload) {
    await delay(450);
    const user = auth.getUser();
    if (!user) throw new Error('Vui lòng đăng nhập để tạo phòng chơi.');

    const room = sharedStore.createRoom({
      gameId: payload.game_id,
      sessionStartTime: payload.session_start_time,
      creatorUser: user,
    });

    return {
      success: true,
      data: room,
      message: 'Mở phòng kịch bản sát thành công.',
    };
  },

  async joinRoom(roomId) {
    await delay(350);
    const user = auth.getUser();
    if (!user) throw new Error('Vui lòng đăng nhập để tham gia phòng chơi.');

    const room = sharedStore.joinRoom(roomId, user);
    return {
      success: true,
      data: room,
      message: 'Tham gia phòng thành công.',
    };
  },

  async makeDeposit(roomId, idempotencyKey) {
    await delay(500);
    const user = auth.getUser();
    if (!user) throw new Error('Vui lòng đăng nhập.');

    const result = sharedStore.makeDeposit(roomId, user.id);
    return {
      success: true,
      data: {
        ...result,
        idempotency_key_used: idempotencyKey,
        amount_vnd: 50000,
        receipt_code: `VOUCHER-${Math.floor(100000 + Math.random() * 900000)}`,
        watermark: 'MÔ PHỎNG',
      },
      message: 'Hoàn tất đặt cọc 50.000 VNĐ mô phỏng.',
    };
  },

  async getQuote(payload) {
    await delay(150);
    const user = auth.getUser();
    const game = sharedStore.data.games.find((g) => g.id === payload.game_id);
    if (!game) throw new Error('Kịch bản không hợp lệ.');

    const isStudent = payload.is_student_verified !== undefined
      ? payload.is_student_verified
      : (user?.student_verified || false);

    const quoteResult = simulateQuote({
      game,
      sessionStartTime: payload.session_start_time,
      playerCount: payload.player_count || game.min_players,
      isStudentVerified: isStudent,
    });

    return {
      success: true,
      data: quoteResult.pricing_snapshot,
    };
  },

  // --- DM Operations ---
  async getDmAssignments() {
    await delay(300);
    const user = auth.getUser();
    const assignments = sharedStore.data.gameRooms
      .filter((r) => r.dm_assignment && (!user || r.dm_assignment.dm_id === user.id || auth.isAdmin()))
      .map((r) => ({
        room_id: r.id,
        room_code: r.code,
        game_id: r.game_id,
        session_start_time: r.session_start_time,
        dm_assignment: r.dm_assignment,
        participants_count: r.participants.length,
      }));

    return {
      success: true,
      data: assignments,
    };
  },

  async dmRespond(roomId, isAccepted, reason = '') {
    await delay(400);
    const user = auth.getUser();
    const room = sharedStore.dmRespondAssignment(roomId, user.id, isAccepted, reason);
    return {
      success: true,
      data: room,
      message: isAccepted ? 'Đã nhận điều phối phiên chơi.' : 'Đã từ chối phân công dẫn dắt.',
    };
  },

  async getDmAvailability(dmId = null) {
    await delay(200);
    const user = auth.getUser();
    const targetId = dmId || user?.id || 'user_dm_01';
    const schedule = sharedStore.getDmAvailability(targetId);
    return {
      success: true,
      data: schedule,
    };
  },

  async saveDmAvailability(schedule, dmId = null) {
    await delay(350);
    const user = auth.getUser();
    const targetId = dmId || user?.id || 'user_dm_01';
    const updated = sharedStore.saveDmAvailability(targetId, schedule);
    return {
      success: true,
      data: updated,
      message: 'Đã lưu cấu hình ma trận lịch rảnh thành công!',
    };
  },

  async updateSessionStatus(roomId, status) {
    await delay(400);
    const user = auth.getUser();
    const room = sharedStore.updateSessionStatus(roomId, user?.id || 'user_dm_01', status);
    return {
      success: true,
      data: room,
      message: status === 'IN_PROGRESS' ? 'Đã kích hoạt bắt đầu phiên chơi.' : 'Đã xác nhận hoàn thành phiên chơi.',
    };
  },

  async updateParticipantAttendance(roomId, participantId, isPresent, note = '') {
    await delay(300);
    const user = auth.getUser();
    const room = sharedStore.updateParticipantAttendance(roomId, user?.id || 'user_dm_01', participantId, isPresent, note);
    return {
      success: true,
      data: room,
      message: `Đã cập nhật điểm danh: ${isPresent ? 'Có mặt' : 'Vắng mặt'}.`,
    };
  },

  async confirmRemainingPayment(roomId, participantId, amount) {
    await delay(400);
    const user = auth.getUser();
    const res = sharedStore.confirmRemainingPayment(roomId, user?.id || 'user_dm_01', participantId, amount, user?.name);
    return {
      success: true,
      data: res,
      message: `Đã xác nhận thu ${Number(amount).toLocaleString('vi-VN')} đ tại quán. Biên nhận: ${res.receipt_code}`,
    };
  },

  // --- Admin Operations ---
  async getAdminQueue() {
    await delay(300);
    const rooms = sharedStore.data.gameRooms;
    return {
      success: true,
      data: {
        waiting_dm: rooms.filter((r) => r.status === 'WAITING_FOR_DM'),
        waiting_org: rooms.filter((r) => r.status === 'WAITING_FOR_ORGANIZATION'),
        waiting_decision: rooms.filter((r) => r.status === 'WAITING_FOR_ADMIN_DECISION'),
        refunds_failed: sharedStore.data.refunds.filter((ref) => ref.status === 'FAILED'),
      },
    };
  },

  async adminAssignDm(roomId, dmId) {
    await delay(400);
    const user = auth.getUser();
    const room = sharedStore.adminAssignDm(roomId, dmId, user);
    return { success: true, data: room, message: 'Đã phân công Quản trò.' };
  },

  async adminRevokeDm(roomId, reason) {
    await delay(400);
    const user = auth.getUser();
    const room = sharedStore.adminRevokeDm(roomId, reason, user);
    return { success: true, data: room, message: 'Đã thu hồi phân công Quản trò thành công.' };
  },

  async adminConfirmVenue(roomId, physicalRoomId) {
    await delay(400);
    const user = auth.getUser();
    const room = sharedStore.adminConfirmVenueAndSchedule(roomId, physicalRoomId, user);
    return { success: true, data: room, message: 'Đã chốt phòng vật lý và mở thời hạn đặt cọc.' };
  },

  async adminProcessRefund(refundId, isApproved, reason = '') {
    await delay(400);
    const user = auth.getUser();
    const refund = sharedStore.adminProcessRefund(refundId, isApproved, reason, user);
    return {
      success: true,
      data: refund,
      message: isApproved ? 'Đã duyệt hoàn cọc thành công.' : 'Đã từ chối yêu cầu hoàn cọc.',
    };
  },

  async adminUpdateGamePricing(gameId, updates) {
    await delay(400);
    const user = auth.getUser();
    const game = sharedStore.adminUpdateGamePricing(gameId, updates, user);
    return { success: true, data: game, message: 'Đã cập nhật bảng giá kịch bản.' };
  },

  async adminUpdateUserRole(userId, newRole) {
    await delay(350);
    const user = auth.getUser();
    const res = sharedStore.adminUpdateUserRole(userId, newRole, user);
    return { success: true, data: res, message: 'Đã cập nhật phân quyền tài khoản.' };
  },

  async adminCancelRoom(roomId, reason) {
    await delay(400);
    const room = sharedStore.adminCancelRoom(roomId, reason);
    return { success: true, data: room, message: 'Đã hủy phòng chơi.' };
  },

  async adminReopen(roomId, windowMinutes, reason) {
    await delay(450);
    const room = sharedStore.adminReopenRecruitment(roomId, windowMinutes, reason);
    return { success: true, data: room, message: 'Đã mở lại cửa sổ gom người thay thế.' };
  },

  // --- Notifications & Audit ---
  async getNotifications() {
    await delay(150);
    const user = auth.getUser();
    const list = sharedStore.data.notifications.filter((n) => !user || n.user_id === user.id);
    return { success: true, data: list };
  },

  async getAuditLogs() {
    await delay(250);
    return { success: true, data: sharedStore.data.auditLogs };
  },
};
