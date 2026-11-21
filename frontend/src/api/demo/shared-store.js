/**
 * Shared In-Memory & LocalStorage Domain Store
 * Single Source of Truth for Player, DM, and Admin Portals.
 * State transitions strictly follow V15/V16 Business Specification.
 */
import { GAMES_CATALOG } from './games-data.js';
import { GAMEROOM_STATUS, PARTICIPANT_STATUS, DM_ASSIGNMENT_STATUS, SCHEDULE_PROPOSAL_STATUS, SESSION_STATUS, REFUND_STATUS } from '../../utils/status.js';
import { simulateQuote } from './quote-simulator.js';

const STORAGE_KEY = 'kbs_shared_demo_store_v16';

function getFutureIso(hoursAhead, minutesAhead = 0) {
  const d = new Date(Date.now() + (hoursAhead * 60 + minutesAhead) * 60 * 1000);
  return d.toISOString();
}

function getInitialStore() {
  const now = new Date();

  // 1. Physical Rooms (Quán)
  const physicalRooms = [
    { id: 'room_phy_01', name: 'Phòng Mật Thất 01 (Cung Đình)', capacity: 10, status: 'AVAILABLE' },
    { id: 'room_phy_02', name: 'Phòng Mật Thất 02 (Dinh Thự Cổ)', capacity: 8, status: 'AVAILABLE' },
    { id: 'room_phy_03', name: 'Phòng Mật Thất 03 (Toa Tàu VIP)', capacity: 8, status: 'AVAILABLE' },
    { id: 'room_phy_04', name: 'Phòng Mật Thất 04 (Bệnh Viện U Tối)', capacity: 7, status: 'MAINTENANCE' },
  ];

  // 2. DM Staff
  const dmStaff = [
    { id: 'user_dm_01', name: 'Hải Đăng (GM)', experience_rooms: 48, rating: 4.9, status: 'ACTIVE' },
    { id: 'user_dm_02', name: 'Minh Thảo (GM)', experience_rooms: 35, rating: 4.8, status: 'ACTIVE' },
    { id: 'user_dm_03', name: 'Quốc Bảo (GM)', experience_rooms: 12, rating: 4.7, status: 'ACTIVE' },
  ];

  // 3. GameRooms (>= 12 phòng mẫu minh họa đầy đủ 7 domain states)
  const game1 = GAMES_CATALOG[0]; // Cô Gái Bên Cửa Sổ
  const game2 = GAMES_CATALOG[8]; // Tuyết Hương Liên Hoàn Án
  const game3 = GAMES_CATALOG[15]; // Vòng Lặp Mưu Sát Đảo Mèo (18+)
  const game4 = GAMES_CATALOG[27]; // Niên Luân
  const game5 = GAMES_CATALOG[23]; // Ly Hôn Đi! Nhanh Lên!

  const gameRooms = [
    // 1. FORMING (Đang tuyển người, còn hạn 2h45p)
    {
      id: 'room_001',
      code: 'PHONG-001',
      game_id: game1.id,
      creator_id: 'user_player_01',
      status: GAMEROOM_STATUS.FORMING,
      session_start_time: getFutureIso(36, 0), // 36h nữa
      formation_deadline: getFutureIso(2, 45), // còn 2h45p
      created_at: getFutureIso(-1, -15),
      participants: [
        { id: 'part_01_1', user_id: 'user_player_01', name: 'Trường Nguyễn (Bạn)', status: PARTICIPANT_STATUS.PENDING_DEPOSIT, joined_at: getFutureIso(-1, -15) },
        { id: 'part_01_2', user_id: 'user_p_02', name: 'Hoàng Long', status: PARTICIPANT_STATUS.PENDING_DEPOSIT, joined_at: getFutureIso(-1) },
        { id: 'part_01_3', user_id: 'user_p_03', name: 'Mai Anh', status: PARTICIPANT_STATUS.PENDING_DEPOSIT, joined_at: getFutureIso(0, -30) },
      ],
      allowed_actions: ['JOIN', 'LEAVE_BEFORE_DEPOSIT', 'SHARE'],
    },
    // 2. WAITING_FOR_DM (Đủ người, chờ Admin phân công DM)
    {
      id: 'room_002',
      code: 'PHONG-002',
      game_id: game2.id,
      creator_id: 'user_p_04',
      status: GAMEROOM_STATUS.WAITING_FOR_DM,
      session_start_time: getFutureIso(48, 0),
      created_at: getFutureIso(-3),
      participants: [
        { id: 'part_02_1', user_id: 'user_p_04', name: 'Khánh Vy', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_02_2', user_id: 'user_player_01', name: 'Trường Nguyễn (Bạn)', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_02_3', user_id: 'user_p_05', name: 'Bảo Ngọc', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_02_4', user_id: 'user_p_06', name: 'Anh Tuấn', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_02_5', user_id: 'user_p_07', name: 'Minh Quân', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
      ],
      dm_assignment: null,
      allowed_actions: ['LEAVE_BEFORE_DEPOSIT', 'SHARE'],
    },
    // 3. WAITING_FOR_ORGANIZATION (DM đã nhận, chờ Admin chốt phòng & lịch)
    {
      id: 'room_003',
      code: 'PHONG-003',
      game_id: game4.id,
      creator_id: 'user_p_08',
      status: GAMEROOM_STATUS.WAITING_FOR_ORGANIZATION,
      session_start_time: getFutureIso(30, 0),
      dm_assignment: {
        id: 'dma_03',
        dm_id: 'user_dm_01',
        dm_name: 'Hải Đăng (GM)',
        status: DM_ASSIGNMENT_STATUS.ACCEPTED,
        assigned_at: getFutureIso(-5),
        accepted_at: getFutureIso(-4),
      },
      participants: [
        { id: 'part_03_1', user_id: 'user_p_08', name: 'Thanh Hằng', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_03_2', user_id: 'user_p_09', name: 'Việt Anh', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_03_3', user_id: 'user_p_10', name: 'Gia Bảo', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_03_4', user_id: 'user_p_11', name: 'Thảo My', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
        { id: 'part_03_5', user_id: 'user_p_12', name: 'Hải Nam', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
      ],
      allowed_actions: ['LEAVE_BEFORE_DEPOSIT'],
    },
    // 4. WAITING_FOR_DEPOSIT (Admin đã chốt, đang mở cọc hạn 2h)
    {
      id: 'room_004',
      code: 'PHONG-004',
      game_id: game1.id,
      creator_id: 'user_player_01',
      status: GAMEROOM_STATUS.WAITING_FOR_DEPOSIT,
      session_start_time: getFutureIso(28, 30),
      physical_room: { id: 'room_phy_02', name: 'Phòng Mật Thất 02 (Dinh Thự Cổ)' },
      dm_assignment: { id: 'dma_04', dm_id: 'user_dm_01', dm_name: 'Hải Đăng (GM)', status: DM_ASSIGNMENT_STATUS.ACCEPTED },
      deposit_opened_at: getFutureIso(0, -45),
      participants: [
        { id: 'part_04_1', user_id: 'user_player_01', name: 'Trường Nguyễn (Bạn)', status: PARTICIPANT_STATUS.PENDING_DEPOSIT, deposit_deadline: getFutureIso(1, 15) },
        { id: 'part_04_2', user_id: 'user_p_13', name: 'Thế Vinh', status: PARTICIPANT_STATUS.DEPOSITED, deposited_at: getFutureIso(0, -20) },
        { id: 'part_04_3', user_id: 'user_p_14', name: 'Phương Linh', status: PARTICIPANT_STATUS.PENDING_DEPOSIT, deposit_deadline: getFutureIso(1, 15) },
        { id: 'part_04_4', user_id: 'user_p_15', name: 'Đức Huy', status: PARTICIPANT_STATUS.DEPOSITED, deposited_at: getFutureIso(0, -10) },
        { id: 'part_04_5', user_id: 'user_p_16', name: 'Hương Giang', status: PARTICIPANT_STATUS.PENDING_DEPOSIT, deposit_deadline: getFutureIso(1, 15) },
      ],
      allowed_actions: ['MAKE_DEPOSIT', 'LEAVE_BEFORE_DEPOSIT'],
    },
    // 5. READY_FOR_SESSION (Tất cả đã cọc đủ 50k, sẵn sàng chơi)
    {
      id: 'room_005',
      code: 'PHONG-005',
      game_id: game2.id,
      creator_id: 'user_p_17',
      status: GAMEROOM_STATUS.READY_FOR_SESSION,
      session_start_time: getFutureIso(18, 0),
      physical_room: { id: 'room_phy_01', name: 'Phòng Mật Thất 01 (Cung Đình)' },
      dm_assignment: { id: 'dma_05', dm_id: 'user_dm_01', dm_name: 'Hải Đăng (GM)', status: DM_ASSIGNMENT_STATUS.ACCEPTED },
      participants: [
        { id: 'part_05_1', user_id: 'user_p_17', name: 'Minh Trí', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_05_2', user_id: 'user_player_01', name: 'Trường Nguyễn (Bạn)', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_05_3', user_id: 'user_p_18', name: 'Thu Trang', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_05_4', user_id: 'user_p_19', name: 'Văn Hậu', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_05_5', user_id: 'user_p_20', name: 'Kim Oanh', status: PARTICIPANT_STATUS.DEPOSITED },
      ],
      allowed_actions: ['REQUEST_LEAVE_AFTER_DEPOSIT', 'PAY_REMAINING_ONLINE', 'PAY_REMAINING_AT_STORE'],
    },
    // 6. REPLENISHING (Có người xin rời, đang mở replacement window 2h)
    {
      id: 'room_006',
      code: 'PHONG-006',
      game_id: game3.id,
      creator_id: 'user_p_21',
      status: GAMEROOM_STATUS.REPLENISHING,
      session_start_time: getFutureIso(32, 0),
      replacement_deadline: getFutureIso(1, 40), // còn 1h40p
      reopen_count: 1,
      reopen_history: [
        { reopened_at: getFutureIso(0, -20), window_minutes: 120, reason: 'Người chơi Bảo An báo bận đột xuất' },
      ],
      participants: [
        { id: 'part_06_1', user_id: 'user_p_21', name: 'Thành Đạt', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_06_2', user_id: 'user_p_22', name: 'Bảo An', status: PARTICIPANT_STATUS.LEAVE_REQUESTED },
        { id: 'part_06_3', user_id: 'user_p_23', name: 'Hồng Nhung', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_06_4', user_id: 'user_p_24', name: 'Quang Dũng', status: PARTICIPANT_STATUS.DEPOSITED },
      ],
      allowed_actions: ['JOIN_REPLACEMENT'],
    },
    // 7. WAITING_FOR_ADMIN_DECISION (Hết replacement window mà chưa ai thay)
    {
      id: 'room_007',
      code: 'PHONG-007',
      game_id: game4.id,
      creator_id: 'user_p_25',
      status: GAMEROOM_STATUS.WAITING_FOR_ADMIN_DECISION,
      session_start_time: getFutureIso(26, 0),
      reopen_count: 1,
      participants: [
        { id: 'part_07_1', user_id: 'user_p_25', name: 'Ngọc Lan', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_07_2', user_id: 'user_p_26', name: 'Quốc Huy', status: PARTICIPANT_STATUS.LEFT },
        { id: 'part_07_3', user_id: 'user_p_27', name: 'Mỹ Duyên', status: PARTICIPANT_STATUS.DEPOSITED },
      ],
      allowed_actions: [],
    },
    // 8. Đang có Đề xuất đổi lịch (ScheduleProposal PROPOSED)
    {
      id: 'room_008',
      code: 'PHONG-008',
      game_id: game5.id,
      creator_id: 'user_p_28',
      status: GAMEROOM_STATUS.READY_FOR_SESSION,
      session_start_time: getFutureIso(40, 0),
      schedule_proposal: {
        id: 'prop_008',
        proposed_by: 'user_admin_01',
        proposed_time: getFutureIso(46, 30),
        status: SCHEDULE_PROPOSAL_STATUS.PROPOSED,
        deadline: getFutureIso(1, 20),
        votes: {
          'user_p_28': 'ACCEPTED',
          'user_player_01': null, // Chưa vote
          'user_p_29': 'ACCEPTED',
        },
      },
      participants: [
        { id: 'part_08_1', user_id: 'user_p_28', name: 'Nhật Minh', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_08_2', user_id: 'user_player_01', name: 'Trường Nguyễn (Bạn)', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_08_3', user_id: 'user_p_29', name: 'Bích Phương', status: PARTICIPANT_STATUS.DEPOSITED },
      ],
      allowed_actions: ['VOTE_SCHEDULE_PROPOSAL'],
    },
    // 9. Session IN_PROGRESS (Ca chơi đang diễn ra tại quán)
    {
      id: 'room_009',
      code: 'PHONG-009',
      game_id: game1.id,
      creator_id: 'user_p_30',
      status: GAMEROOM_STATUS.READY_FOR_SESSION,
      session_start_time: getFutureIso(-1, 0), // Đã bắt đầu 1 tiếng trước
      physical_room: { id: 'room_phy_03', name: 'Phòng Mật Thất 03 (Toa Tàu VIP)' },
      dm_assignment: { id: 'dma_09', dm_id: 'user_dm_01', dm_name: 'Hải Đăng (GM)', status: DM_ASSIGNMENT_STATUS.ACCEPTED },
      session: {
        id: 'sess_009',
        status: SESSION_STATUS.IN_PROGRESS,
        started_at: getFutureIso(-1, 0),
        attendance: { 'part_09_1': true, 'part_09_2': true, 'part_09_3': true },
      },
      participants: [
        { id: 'part_09_1', user_id: 'user_p_30', name: 'Thanh Tùng', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_09_2', user_id: 'user_p_31', name: 'Bảo Trâm', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_09_3', user_id: 'user_p_32', name: 'Huy Khánh', status: PARTICIPANT_STATUS.DEPOSITED },
      ],
      allowed_actions: [],
    },
    // 10. COMPLETED (Đã kết thúc thành công)
    {
      id: 'room_010',
      code: 'PHONG-010',
      game_id: game2.id,
      creator_id: 'user_player_01',
      status: GAMEROOM_STATUS.COMPLETED,
      session_start_time: getFutureIso(-48, 0),
      participants: [
        { id: 'part_10_1', user_id: 'user_player_01', name: 'Trường Nguyễn (Bạn)', status: PARTICIPANT_STATUS.DEPOSITED },
        { id: 'part_10_2', user_id: 'user_p_33', name: 'Đăng Khoa', status: PARTICIPANT_STATUS.DEPOSITED },
      ],
      allowed_actions: [],
    },
    // 11. EXPIRED (Quá 4h không đủ điều kiện gom người)
    {
      id: 'room_011',
      code: 'PHONG-011',
      game_id: game4.id,
      creator_id: 'user_p_34',
      status: GAMEROOM_STATUS.EXPIRED,
      created_at: getFutureIso(-6),
      formation_deadline: getFutureIso(-2),
      participants: [
        { id: 'part_11_1', user_id: 'user_p_34', name: 'Quỳnh Nga', status: PARTICIPANT_STATUS.PENDING_DEPOSIT },
      ],
      allowed_actions: [],
    },
    // 12. CANCELLED (Admin hủy phòng kèm lý do)
    {
      id: 'room_012',
      code: 'PHONG-012',
      game_id: game5.id,
      creator_id: 'user_p_35',
      status: GAMEROOM_STATUS.CANCELLED,
      cancel_reason: 'Cơ sở vật chất phòng chơi gặp sự cố kỹ thuật về hệ thống âm thanh, ánh sáng.',
      cancelled_at: getFutureIso(-12),
      participants: [
        { id: 'part_12_1', user_id: 'user_p_35', name: 'Mạnh Cường', status: PARTICIPANT_STATUS.CANCELLED_BY_ADMIN },
      ],
      allowed_actions: [],
    },
  ];

  // 4. Refunds Queue
  const refunds = [
    {
      id: 'ref_001',
      room_id: 'room_005',
      user_id: 'user_p_99',
      participant_id: 'part_old_99',
      amount_vnd: 50000,
      status: REFUND_STATUS.REQUESTED,
      reason: 'Báo bận trước 30 giờ, đúng hạn quy định',
      created_at: getFutureIso(-4),
    },
    {
      id: 'ref_002',
      room_id: 'room_012',
      user_id: 'user_p_35',
      participant_id: 'part_12_1',
      amount_vnd: 50000,
      status: REFUND_STATUS.FAILED,
      reason: 'Lỗi chuyển khoản liên ngân hàng',
      retry_count: 1,
      last_error: 'Tài khoản người thụ hưởng bị khóa tạm thời',
      created_at: getFutureIso(-8),
    },
  ];

  // 5. Audit Logs
  const auditLogs = [
    { id: 'aud_01', actor_name: 'Ban Quản Trị', action: 'ASSIGN_DM', target_type: 'GameRoom', target_id: 'room_003', details: 'Phân công DM Hải Đăng cho phòng PHONG-003', created_at: getFutureIso(-5) },
    { id: 'aud_02', actor_name: 'Hải Đăng (GM)', action: 'ACCEPT_ASSIGNMENT', target_type: 'DMAssignment', target_id: 'dma_03', details: 'Chấp nhận phân công dẫn dắt phòng PHONG-003', created_at: getFutureIso(-4) },
    { id: 'aud_03', actor_name: 'Ban Quản Trị', action: 'CONFIRM_VENUE_SCHEDULE', target_type: 'GameRoom', target_id: 'room_004', details: 'Chốt phòng vật lý 02 và mở thời hạn đặt cọc', created_at: getFutureIso(0, -45) },
  ];

  // 6. Notifications
  const notifications = [
    { id: 'notif_01', user_id: 'user_player_01', type: 'PAYMENT_OPENED', title: 'Phòng PHONG-004 đã mở thanh toán cọc', message: 'Vui lòng hoàn tất khoản cọc 50.000 VNĐ trong thời hạn 2 giờ để giữ ghế.', is_read: false, created_at: getFutureIso(0, -40), target_hash: '#/player/rooms/room_004' },
    { id: 'notif_02', user_id: 'user_player_01', type: 'SCHEDULE_PROPOSAL', title: 'Đề xuất đổi lịch mới cho PHONG-008', message: 'Quản trị viên đã gửi đề xuất dời lịch chơi sang khung giờ mới. Vui lòng cho ý kiến.', is_read: false, created_at: getFutureIso(0, -15), target_hash: '#/player/rooms/room_008' },
    { id: 'notif_03', user_id: 'user_dm_01', type: 'DM_ASSIGNED', title: 'Phân công dẫn truyện mới', message: 'Bạn vừa được giao điều phối phòng PHONG-002 kịch bản Tuyết Hương Liên Hoàn Án.', is_read: false, created_at: getFutureIso(-1), target_hash: '#/dm/assignments' },
    { id: 'notif_04', user_id: 'user_admin_01', type: 'ADMIN_ALERT', title: 'Cần phân công DM', message: 'Phòng PHONG-002 đã đủ người và đang chờ xếp Quản trò.', is_read: false, created_at: getFutureIso(-2), target_hash: '#/admin/dm-assignments' },
  ];

  // 7. DM Availability Matrix (Thứ 2 đến CN x 3 Ca: Sáng, Chiều, Tối)
  const dmAvailability = {
    user_dm_01: {
      1: { morning: true, afternoon: false, night: true },
      2: { morning: false, afternoon: true, night: true },
      3: { morning: true, afternoon: true, night: false },
      4: { morning: false, afternoon: false, night: true },
      5: { morning: true, afternoon: true, night: true },
      6: { morning: true, afternoon: true, night: true },
      0: { morning: false, afternoon: true, night: true },
    },
    user_dm_02: {
      1: { morning: true, afternoon: true, night: true },
      2: { morning: true, afternoon: true, night: true },
      3: { morning: false, afternoon: false, night: false },
      4: { morning: true, afternoon: true, night: true },
      5: { morning: true, afternoon: true, night: true },
      6: { morning: true, afternoon: true, night: true },
      0: { morning: true, afternoon: true, night: true },
    },
    user_dm_03: {
      1: { morning: false, afternoon: false, night: true },
      2: { morning: false, afternoon: false, night: true },
      3: { morning: false, afternoon: false, night: true },
      4: { morning: false, afternoon: false, night: true },
      5: { morning: true, afternoon: true, night: true },
      6: { morning: true, afternoon: true, night: true },
      0: { morning: true, afternoon: true, night: true },
    },
  };

  return {
    games: GAMES_CATALOG,
    gameRooms,
    physicalRooms,
    dmStaff,
    dmAvailability,
    refunds,
    auditLogs,
    notifications,
  };
}

class SharedStore {
  constructor() {
    this.listeners = new Set();
    this.data = this.load();
  }

  load() {
    if (typeof localStorage !== 'undefined') {
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (!parsed.dmAvailability) {
            parsed.dmAvailability = getInitialStore().dmAvailability;
            this.save(parsed);
          }
          return parsed;
        }
      } catch (e) {
        console.warn('Failed to parse localStorage store, fallback to initial store', e);
      }
    }
    const initial = getInitialStore();
    this.save(initial);
    return initial;
  }

  save(data) {
    this.data = data;
    if (typeof localStorage !== 'undefined') {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
      } catch (e) {
        console.error('Failed to save store to localStorage', e);
      }
    }
    this.notify();
  }

  reset() {
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem(STORAGE_KEY);
    }
    const initial = getInitialStore();
    this.save(initial);
    return initial;
  }

  resetStore() {
    return this.reset();
  }

  subscribe(listener) {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  notify() {
    if (!this.listeners) return;
    for (const l of this.listeners) {
      try { l(this.data); } catch (err) { console.error('Store listener error:', err); }
    }
  }

  getRooms() {
    return this.data.gameRooms;
  }

  getRoomById(id) {
    return this.data.gameRooms.find((r) => r.id === id);
  }

  getRoomByCode(code) {
    return this.data.gameRooms.find((r) => r.code === code);
  }

  // --- Domain Mutators (Follow V15 State Transitions) ---

  createRoom({ gameId, sessionStartTime, creatorUser }) {
    const game = this.data.games.find((g) => g.id === gameId);
    if (!game) throw new Error('Kịch bản không tồn tại.');

    // Kiểm tra quota creator (tối đa 3 phòng active)
    const activeRooms = this.data.gameRooms.filter(
      (r) => r.creator_id === creatorUser.id && !['COMPLETED', 'EXPIRED', 'CANCELLED'].includes(r.status)
    );
    if (activeRooms.length >= 3) {
      throw new Error('Bạn đã tạo tối đa 3 phòng chơi đang hoạt động cùng lúc.');
    }

    const roomId = `room_${Date.now()}`;
    const code = `PHONG-${Math.floor(100 + Math.random() * 900)}`;

    const newRoom = {
      id: roomId,
      code,
      game_id: game.id,
      creator_id: creatorUser.id,
      status: GAMEROOM_STATUS.FORMING,
      session_start_time: sessionStartTime,
      formation_deadline: getFutureIso(4), // 4 giờ gom người
      created_at: new Date().toISOString(),
      participants: [
        {
          id: `part_${Date.now()}`,
          user_id: creatorUser.id,
          name: creatorUser.name,
          status: PARTICIPANT_STATUS.PENDING_DEPOSIT,
          joined_at: new Date().toISOString(),
        },
      ],
      allowed_actions: ['JOIN', 'LEAVE_BEFORE_DEPOSIT', 'SHARE'],
    };

    this.data.gameRooms.unshift(newRoom);
    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}`,
      actor_name: creatorUser.name,
      action: 'CREATE_ROOM',
      target_type: 'GameRoom',
      target_id: roomId,
      details: `Tạo phòng ${code} cho kịch bản ${game.title}`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return newRoom;
  }

  joinRoom(roomId, user) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room) throw new Error('Phòng không tồn tại.');

    const game = this.data.games.find((g) => g.id === room.game_id);
    const activeParticipants = room.participants.filter((p) => !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status));

    if (activeParticipants.length >= game.max_players) {
      throw new Error('Phòng chơi đã đủ người.');
    }

    const isAlreadyIn = activeParticipants.some((p) => p.user_id === user.id);
    if (isAlreadyIn) {
      throw new Error('Bạn đã có mặt trong phòng chơi này.');
    }

    const newPart = {
      id: `part_${Date.now()}`,
      user_id: user.id,
      name: user.name,
      status: PARTICIPANT_STATUS.PENDING_DEPOSIT,
      joined_at: new Date().toISOString(),
    };

    room.participants.push(newPart);

    // Kiểm tra nếu đủ điều kiện game -> chuyển sang WAITING_FOR_DM
    const newCount = activeParticipants.length + 1;
    if (newCount >= game.min_players) {
      room.status = GAMEROOM_STATUS.WAITING_FOR_DM;
      this.data.notifications.unshift({
        id: `notif_${Date.now()}`,
        user_id: 'user_admin_01',
        type: 'ROOM_READY_FOR_DM',
        title: `Phòng ${room.code} đã đủ người`,
        message: `Phòng đã gom đủ ${newCount} người chơi. Vui lòng phân công Quản trò (DM).`,
        is_read: false,
        created_at: new Date().toISOString(),
        target_hash: `#/admin/dm-assignments`,
      });
    }

    this.save(this.data);
    return room;
  }

  makeDeposit(roomId, userId) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room) throw new Error('Phòng không tồn tại.');

    const part = room.participants.find((p) => p.user_id === userId && p.status === PARTICIPANT_STATUS.PENDING_DEPOSIT);
    if (!part) throw new Error('Không tìm thấy lượt đặt cọc hợp lệ của bạn.');

    part.status = PARTICIPANT_STATUS.DEPOSITED;
    part.deposited_at = new Date().toISOString();

    // Kiểm tra nếu tất cả active participants đã cọc -> READY_FOR_SESSION
    const activeParticipants = room.participants.filter((p) => !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status));
    const allDeposited = activeParticipants.every((p) => p.status === PARTICIPANT_STATUS.DEPOSITED);

    if (allDeposited) {
      room.status = GAMEROOM_STATUS.READY_FOR_SESSION;
      this.data.notifications.unshift({
        id: `notif_${Date.now()}`,
        user_id: room.creator_id,
        type: 'ROOM_READY',
        title: `Phòng ${room.code} sẵn sàng chơi!`,
        message: 'Tất cả người chơi đã hoàn tất cọc 50.000 VNĐ. Hẹn gặp bạn tại quán đúng giờ chơi.',
        is_read: false,
        created_at: new Date().toISOString(),
        target_hash: `#/player/rooms/${room.id}`,
      });
    }

    this.save(this.data);
    return { success: true, room };
  }

  adminAssignDm(roomId, dmId, adminActor = null) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    const dm = this.data.dmStaff.find((d) => d.id === dmId);
    if (!room || !dm) throw new Error('Dữ liệu phân công không hợp lệ.');

    if (room.dm_assignment && ['PENDING', 'ACCEPTED'].includes(room.dm_assignment.status)) {
      throw new Error(`Phòng ${room.code} đã có Quản trò (${room.dm_assignment.dm_name}) được phân công. Vui lòng thu hồi phân công hiện tại trước khi xếp Quản trò mới.`);
    }

    const hoursUntilSession = (new Date(room.session_start_time).getTime() - Date.now()) / (3600 * 1000);
    if (hoursUntilSession < 12) {
      throw new Error(`Vi phạm quy tắc V16 Freeze: Quản trò bắt buộc có tối thiểu 12 giờ chuẩn bị trước ca chơi (khoảng đệm hiện tại: ${Math.round(hoursUntilSession * 10) / 10}h).`);
    }

    room.assignment_history = room.assignment_history || [];
    room.dm_assignment = {
      id: `dma_${Date.now()}`,
      dm_id: dm.id,
      dm_name: dm.name,
      status: DM_ASSIGNMENT_STATUS.PENDING,
      assigned_at: new Date().toISOString(),
      assigned_by: adminActor?.name || 'Ban Quản Trị',
      prep_buffer_hours: Math.max(0, Math.round(hoursUntilSession * 10) / 10),
      prep_buffer_warning: false,
    };

    this.data.notifications.unshift({
      id: `notif_${Date.now()}`,
      user_id: dm.id,
      type: 'DM_ASSIGNED',
      title: 'Phân công dẫn dắt phiên chơi mới',
      message: `Bạn được phân công dẫn truyện phòng ${room.code}. Vui lòng xác nhận tiếp nhận.`,
      is_read: false,
      created_at: new Date().toISOString(),
      target_hash: `#/dm/assignments`,
    });

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: adminActor?.name || 'Ban Quản Trị',
      actor_id: adminActor?.id || 'user_admin_01',
      actor_role: 'ADMIN',
      action: 'ASSIGN_DM',
      target_type: 'DMAssignment',
      target_id: room.dm_assignment.id,
      details: `Phân công Quản trò ${dm.name} cho phòng ${room.code} (Kịch bản: ${room.game_details?.title || room.game_id}). Khoảng đệm chuẩn bị: ${Math.round(hoursUntilSession * 10) / 10}h.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return room;
  }

  adminRevokeDm(roomId, reason, adminActor = null) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room) throw new Error('Phòng không tồn tại.');

    if (!room.dm_assignment || !['PENDING', 'ACCEPTED'].includes(room.dm_assignment.status)) {
      throw new Error('Chỉ có thể thu hồi phân công Quản trò đang ở trạng thái PENDING hoặc ACCEPTED.');
    }

    if (!reason || reason.trim().length < 5) {
      throw new Error('Bắt buộc phải cung cấp lý do cụ thể (tối thiểu 5 ký tự) khi thu hồi phân công Quản trò.');
    }

    const trimmedReason = reason.trim();
    const oldDm = room.dm_assignment;

    room.assignment_history = room.assignment_history || [];
    const revokedRecord = {
      ...oldDm,
      status: DM_ASSIGNMENT_STATUS.CANCELLED,
      revoked_at: new Date().toISOString(),
      revoked_by: adminActor?.name || 'Ban Quản Trị',
      revocation_reason: trimmedReason,
    };
    room.assignment_history.push(revokedRecord);

    room.dm_assignment = null;
    room.status = GAMEROOM_STATUS.WAITING_FOR_DM;

    this.data.notifications.unshift({
      id: `notif_${Date.now()}`,
      user_id: oldDm.dm_id,
      type: 'DM_ASSIGNMENT_REVOKED',
      title: `Thu hồi phân công phòng ${room.code}`,
      message: `Ban Quản Trị đã thu hồi phân công dẫn dắt phòng ${room.code}. Lý do: ${trimmedReason}.`,
      is_read: false,
      created_at: new Date().toISOString(),
      target_hash: '#/dm/assignments',
    });

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: adminActor?.name || 'Ban Quản Trị',
      actor_id: adminActor?.id || 'user_admin_01',
      actor_role: 'ADMIN',
      action: 'ADMIN_REVOKE_ASSIGNMENT',
      target_type: 'DMAssignment',
      target_id: revokedRecord.id,
      details: `Thu hồi phân công Quản trò ${oldDm.dm_name} cho phòng ${room.code}. Lý do: ${trimmedReason}. Phòng chuyển về WAITING_FOR_DM.`,
      reason: trimmedReason,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return room;
  }

  dmRespondAssignment(roomId, dmId, isAccepted, reason = '') {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room || !room.dm_assignment || room.dm_assignment.dm_id !== dmId) {
      throw new Error('Phân công không tồn tại hoặc bạn không có quyền thao tác trên ca này.');
    }

    if (room.dm_assignment.status !== DM_ASSIGNMENT_STATUS.PENDING) {
      throw new Error(`Phân công đã ở trạng thái ${room.dm_assignment.status}, không thể phản hồi lại.`);
    }

    const dm = this.data.dmStaff.find((d) => d.id === dmId);
    const dmName = dm?.name || room.dm_assignment.dm_name;

    if (isAccepted) {
      // Calculate buffer
      const hoursUntilSession = (new Date(room.session_start_time).getTime() - Date.now()) / (3600 * 1000);
      const bufferViolated = hoursUntilSession < 12;

      room.dm_assignment.status = DM_ASSIGNMENT_STATUS.ACCEPTED;
      room.dm_assignment.accepted_at = new Date().toISOString();
      room.dm_assignment.prep_buffer_hours = Math.max(0, Math.round(hoursUntilSession * 10) / 10);
      room.dm_assignment.prep_buffer_warning = bufferViolated;
      room.status = GAMEROOM_STATUS.WAITING_FOR_ORGANIZATION;

      // Audit Log
      this.data.auditLogs.unshift({
        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
        actor_name: dmName,
        actor_id: dmId,
        action: 'ACCEPT_ASSIGNMENT',
        target_type: 'DMAssignment',
        target_id: room.dm_assignment.id,
        details: `Quản trò ${dmName} tiếp nhận phân công dẫn dắt phòng ${room.code}. Khoảng đệm chuẩn bị: ${Math.round(hoursUntilSession * 10) / 10}h.`,
        created_at: new Date().toISOString(),
      });
    } else {
      if (!reason || reason.trim().length < 5) {
        throw new Error('Bắt buộc phải cung cấp lý do rõ ràng (tối thiểu 5 ký tự) khi từ chối phân công dẫn dắt.');
      }
      const trimmedReason = reason.trim();
      room.dm_assignment.status = DM_ASSIGNMENT_STATUS.REJECTED;
      room.dm_assignment.rejected_reason = trimmedReason;
      room.dm_assignment.rejected_at = new Date().toISOString();
      // Từ chối không hủy phòng, chuyển lại WAITING_FOR_DM
      room.status = GAMEROOM_STATUS.WAITING_FOR_DM;

      // Notify Admin
      this.data.notifications.unshift({
        id: `notif_${Date.now()}`,
        user_id: 'user_admin_01',
        type: 'DM_REJECTED',
        title: `Quản trò từ chối phòng ${room.code}`,
        message: `Quản trò ${dmName} từ chối phân công phòng ${room.code}. Lý do: ${trimmedReason}. Phòng đã được hoàn trả lại hàng đợi chờ Quản trị viên xếp Quản trò mới.`,
        is_read: false,
        created_at: new Date().toISOString(),
        target_hash: '#/admin/dm-assignments',
      });

      // Audit Log
      this.data.auditLogs.unshift({
        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
        actor_name: dmName,
        actor_id: dmId,
        action: 'REJECT_ASSIGNMENT',
        target_type: 'DMAssignment',
        target_id: room.dm_assignment.id,
        details: `Quản trò ${dmName} từ chối phân công phòng ${room.code}. Lý do: ${trimmedReason}. Phòng chuyển về WAITING_FOR_DM.`,
        created_at: new Date().toISOString(),
      });
    }

    this.save(this.data);
    return room;
  }

  getDmAvailability(dmId) {
    if (!this.data.dmAvailability) {
      this.data.dmAvailability = getInitialStore().dmAvailability;
    }
    const defaultSchedule = {
      1: { morning: true, afternoon: true, night: true },
      2: { morning: true, afternoon: true, night: true },
      3: { morning: true, afternoon: true, night: true },
      4: { morning: true, afternoon: true, night: true },
      5: { morning: true, afternoon: true, night: true },
      6: { morning: true, afternoon: true, night: true },
      0: { morning: true, afternoon: true, night: true },
    };
    return JSON.parse(JSON.stringify(this.data.dmAvailability[dmId] || defaultSchedule));
  }

  saveDmAvailability(dmId, schedule) {
    if (!schedule || typeof schedule !== 'object') {
      throw new Error('Dữ liệu ma trận lịch rảnh không hợp lệ.');
    }
    // Validate days 0 to 6
    for (let day = 0; day <= 6; day++) {
      if (!schedule[day] || typeof schedule[day] !== 'object') {
        throw new Error(`Thiếu cấu hình ca trực cho ngày ${day} (0=CN, 1=T2..6=T7).`);
      }
      schedule[day] = {
        morning: Boolean(schedule[day].morning),
        afternoon: Boolean(schedule[day].afternoon),
        night: Boolean(schedule[day].night),
      };
    }

    if (!this.data.dmAvailability) {
      this.data.dmAvailability = {};
    }
    this.data.dmAvailability[dmId] = schedule;

    const dm = this.data.dmStaff.find((d) => d.id === dmId);
    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: dm?.name || dmId,
      actor_id: dmId,
      action: 'UPDATE_DM_AVAILABILITY',
      target_type: 'DMAvailability',
      target_id: dmId,
      details: `Quản trò ${dm?.name || dmId} cập nhật ma trận lịch rảnh tuần.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return this.data.dmAvailability[dmId];
  }

  updateSessionStatus(roomId, dmId, newStatus) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room || !room.dm_assignment || room.dm_assignment.dm_id !== dmId) {
      throw new Error('Bạn không có quyền quản lý ca chơi này.');
    }

    if (![SESSION_STATUS.IN_PROGRESS, SESSION_STATUS.COMPLETED].includes(newStatus)) {
      throw new Error(`Trạng thái phiên chơi ${newStatus} không hợp lệ.`);
    }

    const dm = this.data.dmStaff.find((d) => d.id === dmId);
    const dmName = dm?.name || room.dm_assignment.dm_name;

    room.session = room.session || {
      id: `ses_${room.id}`,
      room_id: room.id,
      status: SESSION_STATUS.SCHEDULED,
    };

    if (newStatus === SESSION_STATUS.IN_PROGRESS) {
      if (room.session.status === SESSION_STATUS.COMPLETED) {
        throw new Error('Phiên chơi đã hoàn tất, không thể bắt đầu lại.');
      }
      room.session.status = SESSION_STATUS.IN_PROGRESS;
      room.session.started_at = room.session.started_at || new Date().toISOString();
      room.status = GAMEROOM_STATUS.READY_FOR_SESSION;

      this.data.auditLogs.unshift({
        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
        actor_name: dmName,
        actor_id: dmId,
        action: 'START_SESSION',
        target_type: 'Session',
        target_id: room.session.id,
        details: `Quản trò ${dmName} kích hoạt bắt đầu phiên chơi tại phòng ${room.code}.`,
        created_at: new Date().toISOString(),
      });
    } else if (newStatus === SESSION_STATUS.COMPLETED) {
      room.session.status = SESSION_STATUS.COMPLETED;
      room.session.completed_at = new Date().toISOString();
      room.status = GAMEROOM_STATUS.COMPLETED;

      this.data.auditLogs.unshift({
        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
        actor_name: dmName,
        actor_id: dmId,
        action: 'COMPLETE_SESSION',
        target_type: 'Session',
        target_id: room.session.id,
        details: `Quản trò ${dmName} xác nhận hoàn thành phiên chơi phòng ${room.code}.`,
        created_at: new Date().toISOString(),
      });
    }

    this.save(this.data);
    return room;
  }

  updateParticipantAttendance(roomId, dmId, participantId, isPresent, note = '') {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room || !room.dm_assignment || room.dm_assignment.dm_id !== dmId) {
      throw new Error('Bạn không có quyền điểm danh ca chơi này.');
    }

    const participant = room.participants.find((p) => p.id === participantId || p.user_id === participantId);
    if (!participant) {
      throw new Error('Không tìm thấy thành viên trong danh sách phòng.');
    }

    const dm = this.data.dmStaff.find((d) => d.id === dmId);
    const dmName = dm?.name || room.dm_assignment.dm_name;

    participant.attendance = isPresent ? 'PRESENT' : 'ABSENT';
    participant.attendance_recorded_at = new Date().toISOString();
    if (note && note.trim()) {
      participant.attendance_note = note.trim();
    }

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: dmName,
      actor_id: dmId,
      action: 'RECORD_ATTENDANCE',
      target_type: 'RoomParticipant',
      target_id: participant.id,
      details: `Quản trò ${dmName} ghi nhận điểm danh cho ${participant.name}: ${isPresent ? 'CÓ MẶT' : 'VẮNG MẶT'}.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return room;
  }

  confirmRemainingPayment(roomId, dmId, participantId, amount, collectedBy) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room || !room.dm_assignment || room.dm_assignment.dm_id !== dmId) {
      throw new Error('Bạn không có quyền thu tiền cho ca chơi này.');
    }

    const participant = room.participants.find((p) => p.id === participantId || p.user_id === participantId);
    if (!participant) {
      throw new Error('Không tìm thấy thành viên trong danh sách phòng.');
    }

    if (participant.remaining_payment && participant.remaining_payment.status === 'PAID') {
      throw new Error(`Khoản còn lại tại quán của ${participant.name} đã được thu trước đó (Mã biên nhận: ${participant.remaining_payment.receipt_code}). Không thể thu trùng.`);
    }

    const numAmount = Number(amount);
    if (isNaN(numAmount) || numAmount < 0) {
      throw new Error('Số tiền thu tại quán không hợp lệ.');
    }

    const dm = this.data.dmStaff.find((d) => d.id === dmId);
    const collectorName = collectedBy || dm?.name || 'Quản trò';
    const receiptCode = `REC-${Date.now().toString().slice(-6)}-${(participant.id || 'P').slice(-4).toUpperCase()}`;

    participant.remaining_payment = {
      status: 'PAID',
      amount: numAmount,
      deposit_deducted: 50000,
      collected_by: collectorName,
      collected_at: new Date().toISOString(),
      receipt_code: receiptCode,
    };

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: collectorName,
      actor_id: dmId,
      action: 'COLLECT_REMAINING_PAYMENT',
      target_type: 'PlayerPayment',
      target_id: receiptCode,
      details: `${collectorName} xác nhận thu ${numAmount.toLocaleString('vi-VN')} đ tại quán cho thám tử ${participant.name} (Đã khấu trừ 50.000đ cọc). Biên nhận: ${receiptCode}.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return {
      success: true,
      participant,
      receipt_code: receiptCode,
      amount: numAmount,
      collected_by: collectorName,
    };
  }

  adminConfirmVenueAndSchedule(roomId, physicalRoomId, adminActor = null) {
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    const phyRoom = this.data.physicalRooms.find((p) => p.id === physicalRoomId);
    if (!room || !phyRoom) throw new Error('Dữ liệu phòng vật lý không hợp lệ.');

    if (phyRoom.status !== 'AVAILABLE') {
      throw new Error(`Phòng mật thất "${phyRoom.name}" đang trong tình trạng bảo trì, không thể xếp lịch.`);
    }

    // Capacity validation: active players + 1 DM
    const activeParticipants = room.participants.filter(
      (p) => !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status)
    );
    const totalOccupants = activeParticipants.length + 1; // Includes 1 DM
    if (phyRoom.capacity < totalOccupants) {
      throw new Error(`Sức chứa của phòng mật thất (${phyRoom.capacity} người) không đủ cho ${activeParticipants.length} thám tử và 1 Quản trò DM (tổng ${totalOccupants} người).`);
    }

    // Session duration + 30-min cleanup buffer conflict check
    const game = this.data.games.find((g) => g.id === room.game_id);
    const durationMinutes = game?.duration_minutes || 240;
    const targetStart = new Date(room.session_start_time).getTime();
    const targetEnd = targetStart + (durationMinutes + 30) * 60 * 1000; // Includes 30m cleanup buffer

    for (const other of this.data.gameRooms) {
      if (other.id === room.id) continue;
      if (!other.physical_room || other.physical_room.id !== physicalRoomId) continue;
      // Exclude cancelled, expired, or completed rooms from occupying conflicts
      if (['CANCELLED', 'EXPIRED', 'COMPLETED'].includes(other.status)) continue;

      const otherGame = this.data.games.find((g) => g.id === other.game_id);
      const otherDuration = otherGame?.duration_minutes || 240;
      const otherStart = new Date(other.session_start_time).getTime();
      const otherEnd = otherStart + (otherDuration + 30) * 60 * 1000;

      if (Math.max(targetStart, otherStart) < Math.min(targetEnd, otherEnd)) {
        throw new Error(`Xung đột lịch mật thất: Phòng "${phyRoom.name}" đã được xếp cho vụ án ${other.code} trong khoảng thời gian này (bao gồm 30 phút dọn dẹp buffer giữa các ca).`);
      }
    }

    room.physical_room = { id: phyRoom.id, name: phyRoom.name };
    room.status = GAMEROOM_STATUS.WAITING_FOR_DEPOSIT;
    room.deposit_opened_at = new Date().toISOString();

    // Hạn cọc 2 giờ cho từng participant
    room.participants.forEach((p) => {
      if (p.status === PARTICIPANT_STATUS.PENDING_DEPOSIT) {
        p.deposit_deadline = getFutureIso(2);
      }
    });

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: adminActor?.name || 'Ban Quản Trị',
      actor_id: adminActor?.id || 'user_admin_01',
      actor_role: 'ADMIN',
      action: 'CONFIRM_VENUE_AND_SCHEDULE',
      target_type: 'GameRoom',
      target_id: room.id,
      details: `Chốt mật thất "${phyRoom.name}" cho vụ án ${room.code} và mở cọc 2 giờ cho từng thành viên.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return room;
  }

  adminProcessRefund(refundId, isApproved, reason = '', adminActor = null) {
    const refund = this.data.refunds.find((r) => r.id === refundId);
    if (!refund) throw new Error('Yêu cầu hoàn tiền không tồn tại.');

    if (refund.status === 'SUCCEEDED') {
      throw new Error('Yêu cầu hoàn tiền này đã được hoàn tất thành công trước đó (chống hoàn trùng).');
    }

    if (!['REQUESTED', 'FAILED'].includes(refund.status)) {
      throw new Error(`Không thể xử lý yêu cầu hoàn tiền ở trạng thái ${refund.status}.`);
    }

    const room = this.data.gameRooms.find((r) => r.id === refund.room_id || r.code === refund.room_code);

    if (isApproved) {
      if (room) {
        const hoursBeforeSession = (new Date(room.session_start_time).getTime() - new Date(refund.created_at).getTime()) / (3600 * 1000);
        const isEligible = hoursBeforeSession >= 24; // Exactly 24h qualifies
        if (!isEligible) {
          const hasValidOverride = adminActor && adminActor.override_decision_code && adminActor.role === 'ADMIN';
          if (!hasValidOverride) {
            throw new Error(`Theo quy định V16: Yêu cầu hủy dưới 24 giờ (${Math.round(hoursBeforeSession * 10) / 10}h trước ca) không đủ điều kiện xét hoàn cọc.`);
          }
        }
      }

      const refCode = refund.reference_code || `BANK-REF-${Date.now().toString().slice(-6)}-${Math.floor(1000 + Math.random() * 9000)}`;
      refund.status = 'SUCCEEDED';
      refund.reference_code = refCode;
      refund.processed_at = new Date().toISOString();
      refund.processed_by = adminActor?.name || 'Ban Quản Trị';

      this.data.auditLogs.unshift({
        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
        actor_name: adminActor?.name || 'Ban Quản Trị',
        actor_id: adminActor?.id || 'user_admin_01',
        actor_role: 'ADMIN',
        action: 'APPROVE_REFUND',
        target_type: 'Refund',
        target_id: refund.id,
        details: `Duyệt hoàn ${refund.amount_vnd?.toLocaleString('vi-VN')} đ cho người chơi ${refund.user_name || refund.user_id}. Mã GD: ${refCode}.`,
        created_at: new Date().toISOString(),
      });
    } else {
      if (!reason || reason.trim().length < 5) {
        throw new Error('Bắt buộc phải cung cấp lý do cụ thể (tối thiểu 5 ký tự) khi từ chối hoàn cọc.');
      }
      const trimmedReason = reason.trim();
      refund.status = 'REJECTED';
      refund.rejected_reason = trimmedReason;
      refund.processed_at = new Date().toISOString();
      refund.processed_by = adminActor?.name || 'Ban Quản Trị';

      this.data.auditLogs.unshift({
        id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
        actor_name: adminActor?.name || 'Ban Quản Trị',
        actor_id: adminActor?.id || 'user_admin_01',
        actor_role: 'ADMIN',
        action: 'REJECT_REFUND',
        target_type: 'Refund',
        target_id: refund.id,
        details: `Từ chối hoàn cọc ${refund.id}. Lý do: ${trimmedReason}.`,
        reason: trimmedReason,
        created_at: new Date().toISOString(),
      });
    }

    this.save(this.data);
    return refund;
  }

  adminUpdateGamePricing(gameId, updates, adminActor = null) {
    const game = this.data.games.find((g) => g.id === gameId);
    if (!game) throw new Error('Kịch bản không tồn tại.');

    const oldPrices = {
      price_day_vnd: game.price_day_vnd,
      price_night_vnd: game.price_night_vnd,
    };

    if (updates.price_day_vnd !== undefined) game.price_day_vnd = Number(updates.price_day_vnd);
    if (updates.price_night_vnd !== undefined) game.price_night_vnd = Number(updates.price_night_vnd);
    if (updates.duration_minutes !== undefined) game.duration_minutes = Number(updates.duration_minutes);
    if (updates.verified !== undefined) game.verified = Boolean(updates.verified);

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: adminActor?.name || 'Ban Quản Trị',
      actor_id: adminActor?.id || 'user_admin_01',
      actor_role: 'ADMIN',
      action: 'UPDATE_GAME_PRICING',
      target_type: 'Game',
      target_id: game.id,
      details: `Cập nhật cấu hình bảng giá kịch bản "${game.title}". Giá ngày: ${oldPrices.price_day_vnd} -> ${game.price_day_vnd}, Giá đêm: ${oldPrices.price_night_vnd} -> ${game.price_night_vnd}.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return game;
  }

  adminUpdateUserRole(userId, newRole, adminActor = null) {
    if (!adminActor || adminActor.role !== 'ADMIN') {
      throw new Error('Chỉ Quản trị viên (ADMIN) mới có quyền thay đổi vai trò tài khoản.');
    }

    const validRoles = ['PLAYER', 'DM', 'ADMIN'];
    if (!validRoles.includes(newRole)) {
      throw new Error(`Vai trò "${newRole}" không hợp lệ. Các vai trò được chấp nhận: ${validRoles.join(', ')}.`);
    }

    if (!this.data.users) {
      this.data.users = [
        { id: 'user_player_01', name: 'Trường Nguyễn (Người chơi)', role: 'PLAYER' },
        { id: 'user_dm_01', name: 'Hải Đăng (Quản trò)', role: 'DM' },
        { id: 'user_admin_01', name: 'Ban Quản Trị Hệ Thống', role: 'ADMIN' },
      ];
    }

    const targetUser = this.data.users.find((u) => u.id === userId);
    if (targetUser && targetUser.role === 'ADMIN' && newRole !== 'ADMIN') {
      const adminCount = this.data.users.filter((u) => u.role === 'ADMIN').length;
      if (adminCount <= 1) {
        throw new Error('Không thể hạ quyền Quản trị viên cuối cùng của hệ thống.');
      }
    }

    if (targetUser) {
      targetUser.role = newRole;
    }

    this.data.auditLogs.unshift({
      id: `aud_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
      actor_name: adminActor.name,
      actor_id: adminActor.id,
      actor_role: 'ADMIN',
      action: 'UPDATE_USER_ROLE',
      target_type: 'User',
      target_id: userId,
      details: `Admin ${adminActor.name} cập nhật phân quyền tài khoản ${userId} sang vai trò ${newRole}.`,
      created_at: new Date().toISOString(),
    });

    this.save(this.data);
    return { success: true, userId, newRole };
  }

  adminCancelRoom(roomId, reason) {
    if (!reason || !reason.trim()) {
      throw new Error('Bắt buộc phải nhập lý do khi Admin hủy phòng chơi.');
    }
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room) throw new Error('Phòng không tồn tại.');

    room.status = GAMEROOM_STATUS.CANCELLED;
    room.cancel_reason = reason.trim();
    room.cancelled_at = new Date().toISOString();

    this.save(this.data);
    return room;
  }

  adminReopenRecruitment(roomId, windowMinutes, reason) {
    if (!reason || !reason.trim()) {
      throw new Error('Bắt buộc phải cung cấp lý do khi mở lại tuyển người.');
    }
    const room = this.data.gameRooms.find((r) => r.id === roomId);
    if (!room) throw new Error('Phòng không tồn tại.');

    room.status = GAMEROOM_STATUS.REPLENISHING;
    room.reopen_count = (room.reopen_count || 0) + 1;
    room.replacement_deadline = getFutureIso(0, windowMinutes);

    if (!room.reopen_history) room.reopen_history = [];
    room.reopen_history.push({
      reopened_at: new Date().toISOString(),
      window_minutes: windowMinutes,
      reason: reason.trim(),
    });

    this.save(this.data);
    return room;
  }
}

export const sharedStore = new SharedStore();
