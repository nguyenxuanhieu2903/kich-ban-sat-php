// src/logic/portal-mock.js
// Kho dữ liệu mẫu thuần Frontend (localStorage) đồng bộ cho Player, DM và Admin Portal
import * as ScriptData from '../data/scripts.data.js';
import { store } from './store.js';
import { syncSessionUser } from './api.js';

const STORAGE_KEY = 'KBS_V16_PURE_FRONTEND_V1';

export function getCurrentRole() {
  return String(store.state.currentRole || 'guest').toLowerCase();
}

export async function requireAuthRole(allowedRoles, pageTitle = 'Khu vực này') {
  const { user, role } = await syncSessionUser(store);
  if (allowedRoles.includes(role)) {
    return { allowed: true, user, role };
  }
  requireRole(allowedRoles, pageTitle);
  return { allowed: false, user, role };
}

export function requireRole(allowedRoles, pageTitle = 'Khu vực này') {
  const role = getCurrentRole();
  if (allowedRoles.includes(role)) {
    return true;
  }

  const main = document.querySelector('main');
  if (main) {
    const isGuest = role === 'guest';
    const homeTarget = role === 'admin' ? './admin-portal.html' : role === 'dm' ? './dm-portal.html' : role === 'player' ? './lobby.html' : '../index.html';
    const roleViMap = { admin: 'Quản trị viên', dm: 'Quản trò', player: 'Người chơi', guest: 'Khách chưa đăng nhập' };
    const roleVi = roleViMap[role] || 'Tài khoản hiện tại';
    main.innerHTML = `
      <div class="container" style="max-width:680px;padding-top:36px;">
        <section class="portal-panel" style="text-align:center;">
          <div class="ticket-code" style="margin-bottom:8px;">PHÂN QUYỀN TRUY CẬP HỆ THỐNG</div>
          <h1 class="portal-panel-title" style="border-bottom:none;margin-bottom:8px;">${isGuest ? 'Yêu Cầu Đăng Nhập Hệ Thống' : 'Không Đủ Quyền Truy Cập'}</h1>
          <p class="text-muted" style="margin-bottom:20px;">
            ${isGuest
              ? `Bạn chưa đăng nhập. Vui lòng đăng nhập đúng tài khoản để truy cập ${pageTitle}.`
              : `Tài khoản hiện tại (${roleVi}) không có quyền truy cập ${pageTitle}.`}
          </p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a href="./auth.html#login" class="btn btn-gold">Đăng nhập tài khoản khác</a>
            <a href="${homeTarget}" class="btn btn-ghost">Quay về trang phù hợp</a>
          </div>
        </section>
      </div>
    `;
  }
  return false;
}

export const ALL_SCENARIOS = ScriptData.SCENARIOS
  || ScriptData.ALL_SCRIPTS
  || ScriptData.SCRIPTS
  || ScriptData.default
  || Object.values(ScriptData).find((v) => Array.isArray(v))
  || [];

export function formatVND(amount) {
  return `${Math.round(Number(amount) || 0).toLocaleString('vi-VN')} đ`;
}

export function formatRoomStatus(status) {
  const s = String(status || '').toUpperCase();
  const map = {
    FORMING: 'Đang tuyển người',
    SCHEDULED: 'Đã xếp lịch (chờ Quản trò)',
    CONFIRMED: 'Đã chốt ca',
    IN_PROGRESS: 'Đang diễn ra',
    COMPLETED: 'Đã hoàn tất',
    CANCELLED: 'Đã hủy',
  };
  return map[s] || status || 'Đang cập nhật';
}

export function formatBookingType(type) {
  const t = String(type || 'PUBLIC').toUpperCase();
  return t === 'PRIVATE' ? 'Nhóm riêng' : 'Ghép công khai';
}

export function formatParticipantStatus(status) {
  const s = String(status || '').toUpperCase();
  const map = {
    PENDING_DEPOSIT: 'Chờ đặt cọc',
    DEPOSITED: 'Đã đặt cọc',
    LEAVE_REQUESTED: 'Chờ duyệt rời phòng',
    LEFT: 'Đã rời phòng',
    CANCELLED: 'Đã hủy',
    CANCELLED_UNPAID: 'Hủy do quá hạn cọc',
    CANCELLED_BY_ADMIN: 'Đã hủy bởi quản trị',
  };
  return map[s] || status || 'Đang cập nhật';
}

export function formatPaymentStatus(status) {
  const s = String(status || '').toUpperCase();
  const map = {
    PAID: 'Đã thanh toán đủ',
    DEPOSITED: 'Đã đặt cọc',
    PARTIAL: 'Đã thanh toán một phần',
    PENDING: 'Chờ thanh toán',
    REFUNDED: 'Đã hoàn tiền',
    FAILED: 'Thanh toán thất bại',
    CANCELLED: 'Đã hủy',
  };
  return map[s] || status || 'Chờ thanh toán';
}

export function formatPaymentType(type) {
  const t = String(type || '').toUpperCase();
  const map = {
    DEPOSIT: 'Đặt cọc giữ chỗ',
    REMAINING: 'Thanh toán phần còn lại',
    FULL: 'Thanh toán trọn gói',
  };
  return map[t] || type || 'Đặt cọc giữ chỗ';
}

export function formatDmAssignmentStatus(status) {
  const s = String(status || '').toUpperCase();
  const map = {
    PENDING: 'Chờ xác nhận',
    ACCEPTED: 'Đã nhận ca',
    REJECTED: 'Đã từ chối',
    REVOKED: 'Đã thu hồi',
  };
  return map[s] || status || 'Chưa phân công';
}

export function formatRefundStatus(status) {
  const s = String(status || '').toUpperCase();
  const map = {
    PENDING: 'Chờ duyệt',
    APPROVED: 'Đã duyệt (chờ CK)',
    COMPLETED: 'Đã hoàn tiền',
    REJECTED: 'Đã từ chối',
  };
  return map[s] || status || 'Chờ duyệt';
}

export function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function showToast(message) {
  const old = document.querySelector('.portal-toast');
  if (old) old.remove();
  const el = document.createElement('div');
  el.className = 'portal-toast';
  el.textContent = message;
  document.body.appendChild(el);
  window.setTimeout(() => el.remove(), 3200);
}

function getScenarioByIndex(idx) {
  const s = ALL_SCENARIOS[idx] || ALL_SCENARIOS[0] || {};
  return {
    id: s.id || `kb-0${idx + 1}`,
    title: s.title || s.name || `Kịch bản #${idx + 1}`,
    genre: s.genre || s.category || 'Trinh thám',
    minPlayers: Number(s.minPlayers || s.min_players || 5),
    maxPlayers: Number(s.maxPlayers || s.max_players || 7),
    duration: Number(s.duration || s.durationMinutes || 210),
    priceDay: Number(s.priceDay || s.price_day || 220000),
    priceNight: Number(s.priceNight || s.price_night || 250000),
  };
}

function createInitialState() {
  const s1 = getScenarioByIndex(0);
  const s2 = getScenarioByIndex(1);
  const s3 = getScenarioByIndex(2);
  const s4 = getScenarioByIndex(3);

  return {
    user: {
      id: 'USR-01',
      fullName: 'Nguyễn Minh Khôi',
      email: 'player.hieu@kichbansat.vn',
      phone: '0909.123.456',
      role: 'PLAYER',
      studentStatus: 'APPROVED', // NONE | PENDING | APPROVED | REJECTED
      studentCardFile: 'the_hssv_minhkhoi.jpg',
    },
    dms: [
      { id: 'DM-01', name: 'Quản trò Hoàng Nam', specialty: 'Kinh dị / Tâm lý', status: 'ACTIVE' },
      { id: 'DM-02', name: 'Quản trò Thảo Vy', specialty: 'Cổ trang / Trinh thám', status: 'ACTIVE' },
      { id: 'DM-03', name: 'Quản trò Đức Anh', specialty: 'Đấu trí / Sinh tồn', status: 'ACTIVE' },
    ],
    physicalRooms: [
      { id: 'PR-01', name: 'Phòng 01 - Mật Thất Hoàng Gia', venue: 'Chi nhánh Bình Thạnh', capacity: 8, maintenance: false },
      { id: 'PR-02', name: 'Phòng 02 - Cổ Tự Âm U', venue: 'Chi nhánh Bình Thạnh', capacity: 8, maintenance: false },
      { id: 'PR-03', name: 'Phòng 03 - Thư Viện Cổ', venue: 'Chi nhánh Bình Thạnh', capacity: 6, maintenance: false },
      { id: 'PR-04', name: 'Phòng 04 - Phòng Thẩm Vấn', venue: 'Chi nhánh Quận 1', capacity: 8, maintenance: true },
    ],
    rooms: [
      {
        id: 'KBS-101',
        title: `Bàn ghép tối thứ Sáu — ${s1.title}`,
        scenarioId: s1.id,
        scenarioTitle: s1.title,
        genre: s1.genre,
        bookingType: 'PUBLIC',
        playDate: '2026-12-04',
        playTime: '19:00',
        minPlayers: s1.minPlayers,
        maxPlayers: s1.maxPlayers,
        pricePerPlayer: s1.priceDay,
        status: 'FORMING', // FORMING | SCHEDULED | CONFIRMED | IN_PROGRESS | COMPLETED
        hostName: 'Nguyễn Minh Khôi',
        dmId: 'DM-01',
        dmStatus: 'PENDING', // PENDING | ACCEPTED | REJECTED
        physicalRoomId: 'PR-01',
        notes: 'Ưu tiên người chơi đúng giờ trước 15 phút để nhận phục trang.',
        participants: [
          { name: 'Nguyễn Minh Khôi (Chủ phòng)', deposited: true, paidFull: false },
          { name: 'Lê Thu Hà', deposited: true, paidFull: false },
          { name: 'Phạm Quốc Tuấn', deposited: true, paidFull: false },
          { name: 'Võ Ngọc Ánh', deposited: false, paidFull: false },
        ],
        proposals: [
          { id: 'PROP-1', time: '2026-12-04 19:00', proposer: 'Nguyễn Minh Khôi', votesYes: 3, votesNo: 0, status: 'PROPOSED' },
        ],
      },
      {
        id: 'KBS-102',
        title: `Ca chiều Thứ Ba ưu đãi 30% — ${s2.title}`,
        scenarioId: s2.id,
        scenarioTitle: s2.title,
        genre: s2.genre,
        bookingType: 'PUBLIC',
        playDate: '2026-12-01',
        playTime: '14:00',
        minPlayers: s2.minPlayers,
        maxPlayers: s2.maxPlayers,
        pricePerPlayer: s2.priceDay,
        status: 'CONFIRMED',
        hostName: 'Trần Gia Bảo',
        dmId: 'DM-02',
        dmStatus: 'ACCEPTED',
        physicalRoomId: 'PR-02',
        notes: 'Nhóm đã chốt lịch và đủ cọc 100%.',
        participants: [
          { name: 'Trần Gia Bảo (Chủ phòng)', deposited: true, paidFull: true },
          { name: 'Nguyễn Minh Khôi', deposited: true, paidFull: false },
          { name: 'Bùi Thanh Trúc', deposited: true, paidFull: false },
          { name: 'Hoàng Minh Trí', deposited: true, paidFull: false },
          { name: 'Đỗ Phương Linh', deposited: true, paidFull: false },
        ],
        proposals: [
          { id: 'PROP-2', time: '2026-12-01 14:00', proposer: 'Trần Gia Bảo', votesYes: 5, votesNo: 0, status: 'CONFIRMED' },
        ],
      },
      {
        id: 'KBS-103',
        title: `Nhóm riêng cuối tuần — ${s3.title}`,
        scenarioId: s3.id,
        scenarioTitle: s3.title,
        genre: s3.genre,
        bookingType: 'PRIVATE',
        playDate: '2026-12-06',
        playTime: '20:00',
        minPlayers: s3.minPlayers,
        maxPlayers: s3.maxPlayers,
        pricePerPlayer: s3.priceNight,
        status: 'IN_PROGRESS',
        hostName: 'Lý Gia Huy',
        dmId: 'DM-01',
        dmStatus: 'ACCEPTED',
        physicalRoomId: 'PR-03',
        notes: 'Đang diễn ra vòng điều tra 2.',
        participants: [
          { name: 'Lý Gia Huy (Chủ phòng)', deposited: true, paidFull: true },
          { name: 'Trương Mỹ Linh', deposited: true, paidFull: true },
          { name: 'Ngô Tấn Phát', deposited: true, paidFull: true },
          { name: 'Phan Thảo Nhi', deposited: true, paidFull: true },
          { name: 'Đặng Hải Đăng', deposited: true, paidFull: true },
        ],
        proposals: [],
      },
      {
        id: 'KBS-104',
        title: `Bàn hoàn tất — ${s4.title}`,
        scenarioId: s4.id,
        scenarioTitle: s4.title,
        genre: s4.genre,
        bookingType: 'PUBLIC',
        playDate: '2026-11-20',
        playTime: '15:00',
        minPlayers: s4.minPlayers,
        maxPlayers: s4.maxPlayers,
        pricePerPlayer: s4.priceDay,
        status: 'COMPLETED',
        hostName: 'Nguyễn Minh Khôi',
        dmId: 'DM-02',
        dmStatus: 'ACCEPTED',
        physicalRoomId: 'PR-01',
        notes: 'Buổi chơi kết thúc tốt đẹp, đã tất toán tại quầy.',
        participants: [
          { name: 'Nguyễn Minh Khôi (Chủ phòng)', deposited: true, paidFull: true },
          { name: 'Lê Thu Hà', deposited: true, paidFull: true },
          { name: 'Phạm Quốc Tuấn', deposited: true, paidFull: true },
          { name: 'Võ Ngọc Ánh', deposited: true, paidFull: true },
        ],
        proposals: [],
      },
    ],
    refunds: [
      { id: 'RF-201', roomId: 'KBS-101', playerName: 'Võ Ngọc Ánh', amount: 50000, reason: 'Bận lịch thi đột xuất (hủy trước 48h)', status: 'PENDING', createdAt: '2026-11-25 10:15' },
      { id: 'RF-202', roomId: 'KBS-102', playerName: 'Hoàng Minh Trí', amount: 50000, reason: 'Chuyển sang ca khác', status: 'APPROVED', createdAt: '2026-11-24 16:40' },
    ],
    studentRequests: [
      { id: 'HSSV-01', playerName: 'Trần Minh Anh', email: 'minhanh@gmail.com', studentCode: '2251010042', school: 'ĐH Khoa học Tự nhiên TP.HCM', status: 'PENDING' },
      { id: 'HSSV-02', playerName: 'Nguyễn Minh Khôi', email: 'player.hieu@kichbansat.vn', studentCode: '21520988', school: 'ĐH Công nghệ Thông tin', status: 'APPROVED' },
    ],
    auditLogs: [
      { time: '2026-11-25 14:20', actor: 'Admin', action: 'ASSIGN_VENUE', detail: 'Gán Phòng 02 - Cổ Tự Âm U cho phòng KBS-102' },
      { time: '2026-11-25 11:05', actor: 'DM Hoàng Nam', action: 'START_SESSION', detail: 'Bắt đầu buổi chơi phòng KBS-103' },
      { time: '2026-11-24 17:10', actor: 'Admin', action: 'APPROVE_REFUND', detail: 'Duyệt hoàn cọc 50.000 đ cho yêu cầu RF-202' },
    ],
  };
}

export function loadPortalState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) return JSON.parse(raw);
  } catch { /* ignore */ }
  const fresh = createInitialState();
  savePortalState(fresh);
  return fresh;
}

export function savePortalState(state) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch { /* ignore */ }
}

export function resetPortalState() {
  const fresh = createInitialState();
  savePortalState(fresh);
  return fresh;
}

export function addAuditLog(state, actor, action, detail) {
  const now = new Date();
  const stamp = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  state.auditLogs.unshift({ time: stamp, actor, action, detail });
}

export function calculateQuote({ scenario, playDate, playTime, playerCount, studentVerified }) {
  const count = Math.max(1, Number(playerCount) || 5);
  const [h = 19, m = 0] = String(playTime || '19:00').split(':').map(Number);
  const minutes = h * 60 + m;
  const isNight = minutes >= 20 * 60;
  const basePrice = isNight ? Number(scenario.priceNight || 250000) : Number(scenario.priceDay || 220000);
  const subtotal = basePrice * count;

  const d = playDate ? new Date(`${playDate}T${playTime || '14:00'}:00`) : new Date();
  const dow = d.getDay(); // 2 = Tuesday
  const inPromoWindow = minutes >= 9 * 60 && minutes <= 19 * 60;
  const isTuesday = dow === 2 && inPromoWindow;
  const isStudent = Boolean(studentVerified) && dow >= 1 && dow <= 4 && inPromoWindow;

  let discount = 0;
  let promoLabel = 'Không áp dụng ưu đãi';
  if (isTuesday && isStudent) {
    discount = Math.round(subtotal * 0.3);
    promoLabel = 'Ưu đãi Thứ Ba (-30%, không cộng dồn HSSV)';
  } else if (isTuesday) {
    discount = Math.round(subtotal * 0.3);
    promoLabel = 'Ưu đãi Thứ Ba (-30%)';
  } else if (isStudent) {
    discount = 20000 * count;
    promoLabel = 'Ưu đãi HSSV (-20.000 đ/người)';
  }

  const finalTotal = Math.max(0, subtotal - discount);
  const depositTotal = Math.min(finalTotal, 50000 * count);
  return {
    timeWindow: isNight ? 'Khung tối (từ 20:00)' : 'Khung ngày (09:00 - 19:59)',
    basePrice,
    count,
    subtotal,
    discount,
    promoLabel,
    finalTotal,
    depositTotal,
    remainingTotal: Math.max(0, finalTotal - depositTotal),
  };
}
