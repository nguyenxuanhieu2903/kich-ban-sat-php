import { ALL_SCRIPTS, PHYSICAL_ROOMS } from '../data/scripts.data.js';

const STORAGE_KEY = 'KBS_V16_STATE_V1';
export const DEPOSIT_PER_PERSON = 50000;
export const STUDENT_DISCOUNT = 20000;
export const TUESDAY_DISCOUNT_RATE = 0.30;

function getFutureISO(hoursFromNow) {
  return new Date(Date.now() + hoursFromNow * 3600 * 1000).toISOString();
}

function getDatePlusDays(days) {
  const d = new Date(Date.now() + days * 86400 * 1000);
  return d.toISOString().split('T')[0];
}

const INITIAL_STATE = {
  currentRole: 'guest', // guest | player | dm | admin
  scripts: ALL_SCRIPTS,
  physicalRooms: PHYSICAL_ROOMS,
  currentUser: {
    id: 'USR-01',
    name: 'Nguyễn Minh Khôi',
    phone: '0909.123.456',
    email: 'khoi.nguyen@student.edu.vn',
    avatar: 'MK',
    studentStatus: 'pending', // none | pending | approved
    studentCardUrl: 'the_hssv_minhkhoi.jpg'
  },
  dms: [
    { id: 'DM-01', name: 'Quản trò Hoàng Nam', specialty: 'Kinh dị / Tâm lý', available: true, rating: 4.9 },
    { id: 'DM-02', name: 'Quản trò Thảo Vy', specialty: 'Cổ trang / Trinh thám', available: true, rating: 5.0 },
    { id: 'DM-03', name: 'Quản trò Đức Anh', specialty: 'Đấu trí / Sinh tồn', available: false, rating: 4.8 }
  ],
  rooms: [
    {
      id: 'KBS-104',
      scriptId: 'SCR-01',
      hostName: 'Trần Gia Bảo',
      date: getDatePlusDays(2),
      timeSlot: '19:00',
      playDateTime: getFutureISO(48),
      maxPlayers: 8,
      status: 'forming', // forming | confirmed | completed
      depositStatus: 'paid', // pending | paid | expired
      holdExpiresAt: getFutureISO(3.5),
      assignedDmId: 'DM-01',
      dmAcceptStatus: 'accepted', // pending | accepted | rejected
      physicalRoomId: 'PR-01',
      slots: [
        { seat: 1, name: 'Trần Gia Bảo (Chủ phòng)', phone: '0912.345.678', paid: true, checkedIn: true },
        { seat: 2, name: 'Lê Thu Hà', phone: '0988.111.222', paid: true, checkedIn: true },
        { seat: 3, name: 'Phạm Quốc Tuấn', phone: '0933.444.555', paid: true, checkedIn: false },
        { seat: 4, name: 'Võ Ngọc Ánh', phone: '0977.888.999', paid: true, checkedIn: false },
        { seat: 5, name: 'Đặng Hải Đăng', phone: '0903.222.333', paid: true, checkedIn: false },
        { seat: 6, name: 'Nguyễn Minh Khôi', phone: '0909.123.456', paid: true, checkedIn: false },
        { seat: 7, name: null, phone: null, paid: false, checkedIn: false },
        { seat: 8, name: null, phone: null, paid: false, checkedIn: false }
      ]
    },
    {
      id: 'KBS-107',
      scriptId: 'SCR-02',
      hostName: 'Nguyễn Minh Khôi',
      date: getDatePlusDays(1),
      timeSlot: '14:00',
      playDateTime: getFutureISO(18), // < 24h để test cảnh báo mất cọc
      maxPlayers: 7,
      status: 'forming',
      depositStatus: 'pending',
      holdExpiresAt: getFutureISO(3.8),
      assignedDmId: '',
      dmAcceptStatus: 'pending',
      physicalRoomId: '',
      slots: [
        { seat: 1, name: 'Nguyễn Minh Khôi (Chủ phòng)', phone: '0909.123.456', paid: false, checkedIn: false },
        { seat: 2, name: 'Bùi Thanh Trúc', phone: '0918.765.432', paid: true, checkedIn: false },
        { seat: 3, name: 'Hoàng Minh Trí', phone: '0902.654.321', paid: true, checkedIn: false },
        { seat: 4, name: 'Đỗ Phương Linh', phone: '0938.112.233', paid: true, checkedIn: false },
        { seat: 5, name: 'Lý Gia Huy', phone: '0945.667.788', paid: true, checkedIn: false },
        { seat: 6, name: null, phone: null, paid: false, checkedIn: false },
        { seat: 7, name: null, phone: null, paid: false, checkedIn: false }
      ]
    },
    {
      id: 'KBS-109',
      scriptId: 'SCR-03',
      hostName: 'Lê Hoàng Phúc',
      date: getDatePlusDays(3),
      timeSlot: '19:00',
      playDateTime: getFutureISO(72),
      maxPlayers: 8,
      status: 'confirmed',
      depositStatus: 'paid',
      holdExpiresAt: getFutureISO(4),
      assignedDmId: 'DM-02',
      dmAcceptStatus: 'pending',
      physicalRoomId: 'PR-02',
      slots: Array.from({ length: 8 }, (_, i) => ({
        seat: i + 1,
        name: `Người chơi #${i + 1}`,
        phone: `0901.000.00${i + 1}`,
        paid: true,
        checkedIn: false
      }))
    },
    {
      id: 'KBS-112',
      scriptId: 'SCR-04',
      hostName: 'Đào Mỹ Duyên',
      date: getDatePlusDays(4),
      timeSlot: '16:00',
      playDateTime: getFutureISO(96),
      maxPlayers: 6,
      status: 'forming',
      depositStatus: 'paid',
      holdExpiresAt: getFutureISO(2.5),
      assignedDmId: 'DM-01',
      dmAcceptStatus: 'accepted',
      physicalRoomId: 'PR-03',
      slots: [
        { seat: 1, name: 'Đào Mỹ Duyên (Chủ phòng)', phone: '0908.333.111', paid: true, checkedIn: false },
        { seat: 2, name: 'Trịnh Công Sơn', phone: '0907.222.444', paid: true, checkedIn: false },
        { seat: 3, name: 'Ngô Bảo Châu', phone: '0906.555.666', paid: true, checkedIn: false },
        { seat: 4, name: null, phone: null, paid: false, checkedIn: false },
        { seat: 5, name: null, phone: null, paid: false, checkedIn: false },
        { seat: 6, name: null, phone: null, paid: false, checkedIn: false }
      ]
    }
  ],
  refundRequests: [
    {
      id: 'RF-01',
      roomId: 'KBS-104',
      playerName: 'Lê Thu Hà',
      phone: '0988.111.222',
      amount: 50000,
      hoursBeforePlay: 36,
      isEligible: true, // > 24h
      requestedAt: 'Hôm nay, 10:15',
      status: 'pending' // pending | approved | rejected
    },
    {
      id: 'RF-02',
      roomId: 'KBS-107',
      playerName: 'Hoàng Minh Trí',
      phone: '0902.654.321',
      amount: 50000,
      hoursBeforePlay: 14,
      isEligible: false, // < 24h
      requestedAt: 'Hôm nay, 13:40',
      status: 'pending'
    }
  ],
  revenue7Days: [
    { day: 'T2', amount: 3450000 },
    { day: 'T3', amount: 4820000 },
    { day: 'T4', amount: 3190000 },
    { day: 'T5', amount: 3890000 },
    { day: 'T6', amount: 6150000 },
    { day: 'T7', amount: 8940000 },
    { day: 'CN', amount: 8420000 }
  ]
};

class Store {
  constructor() {
    this.state = this.load();
  }

  load() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(INITIAL_STATE));
        return structuredClone(INITIAL_STATE);
      }
      return JSON.parse(raw);
    } catch {
      return structuredClone(INITIAL_STATE);
    }
  }

  save() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
    window.dispatchEvent(new CustomEvent('kbs:state-changed', { detail: this.state }));
  }

  resetDemo() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(INITIAL_STATE));
    this.state = structuredClone(INITIAL_STATE);
    this.save();
  }

  setRole(role) {
    this.state.currentRole = role;
    this.save();
  }

  /**
   * Tính giá tự động theo đúng quy tắc:
   * - Thứ 3 Bí Ẩn: giảm 30% giá vé
   * - Ưu đãi HSSV: giảm 20.000đ/người nếu T2-T5 khung 09:00-19:59 & tài khoản đã duyệt thẻ HSSV
   * - Cọc cố định: 50.000đ/người
   */
  calculatePricing({ basePrice, dateStr, timeSlot, playerCount }) {
    const count = Math.max(1, Number(playerCount) || 1);
    const d = dateStr ? new Date(`${dateStr}T${timeSlot || '14:00'}:00`) : new Date();
    const dayOfWeek = d.getDay(); // 0 CN, 1 T2, 2 T3, 3 T4, 4 T5, 5 T6, 6 T7
    const hour = Number((timeSlot || '14:00').split(':')[0]);

    const isTuesday = dayOfWeek === 2;
    const isMonToThu = dayOfWeek >= 1 && dayOfWeek <= 4;
    const isStudentTime = isMonToThu && hour >= 9 && hour < 20;
    const isStudentApproved = this.state.currentUser.studentStatus === 'approved';

    let unitPrice = basePrice;
    let tuesdayDiscountPerPerson = 0;
    let studentDiscountPerPerson = 0;

    if (isTuesday) {
      tuesdayDiscountPerPerson = Math.round(basePrice * TUESDAY_DISCOUNT_RATE);
      unitPrice -= tuesdayDiscountPerPerson;
    }

    if (isStudentTime && isStudentApproved) {
      studentDiscountPerPerson = STUDENT_DISCOUNT;
      unitPrice = Math.max(0, unitPrice - studentDiscountPerPerson);
    }

    const totalTicketAmount = unitPrice * count;
    const totalDeposit = DEPOSIT_PER_PERSON * count;
    const remainingAtVenue = Math.max(0, totalTicketAmount - totalDeposit);

    return {
      basePrice,
      unitPrice,
      count,
      isTuesday,
      tuesdayDiscountPerPerson,
      isStudentEligibleTime: isStudentTime,
      isStudentApproved,
      studentDiscountPerPerson,
      totalTicketAmount,
      totalDeposit,
      remainingAtVenue
    };
  }

  createRoom({ scriptId, date, timeSlot, playerCount, hostName }) {
    const script = this.state.scripts.find(s => s.id === scriptId) || this.state.scripts[0];
    const maxPlayers = script.playersMax;
    const filled = Math.min(maxPlayers, Math.max(1, Number(playerCount)));
    const newId = `KBS-${Math.floor(115 + Math.random() * 880)}`;
    const playDateTime = new Date(`${date}T${timeSlot}:00`).toISOString();

    const slots = Array.from({ length: maxPlayers }, (_, idx) => {
      if (idx === 0) {
        return { seat: 1, name: `${hostName || this.state.currentUser.name} (Chủ phòng)`, phone: this.state.currentUser.phone, paid: false, checkedIn: false };
      }
      if (idx < filled) {
        return { seat: idx + 1, name: `Thành viên nhóm #${idx + 1}`, phone: '0909.000.111', paid: false, checkedIn: false };
      }
      return { seat: idx + 1, name: null, phone: null, paid: false, checkedIn: false };
    });

    const room = {
      id: newId,
      scriptId: script.id,
      hostName: hostName || this.state.currentUser.name,
      date,
      timeSlot,
      playDateTime,
      maxPlayers,
      status: filled >= script.playersMin ? 'confirmed' : 'forming',
      depositStatus: 'pending',
      holdExpiresAt: getFutureISO(4), // Đếm ngược 4 giờ
      assignedDmId: '',
      dmAcceptStatus: 'pending',
      physicalRoomId: '',
      slots
    };

    this.state.rooms.unshift(room);
    this.save();
    return room;
  }

  confirmRoomDeposit(roomId) {
    const room = this.state.rooms.find(r => r.id === roomId);
    if (!room) return;
    room.depositStatus = 'paid';
    room.slots.forEach(s => {
      if (s.name) s.paid = true;
    });
    this.save();
  }

  requestRefundOrCancel(roomId) {
    const room = this.state.rooms.find(r => r.id === roomId);
    if (!room) return { eligible: false };
    const hoursLeft = (new Date(room.playDateTime).getTime() - Date.now()) / 3600000;
    const isEligible = hoursLeft > 24;

    if (isEligible) {
      this.state.refundRequests.unshift({
        id: `RF-${Date.now().toString().slice(-3)}`,
        roomId: room.id,
        playerName: this.state.currentUser.name,
        phone: this.state.currentUser.phone,
        amount: DEPOSIT_PER_PERSON,
        hoursBeforePlay: Math.round(hoursLeft),
        isEligible: true,
        requestedAt: 'Vừa xong',
        status: 'pending'
      });
    }
    // Giải phóng ghế người dùng hiện tại
    const userSlot = room.slots.find(s => s.name && s.name.includes(this.state.currentUser.name));
    if (userSlot) {
      userSlot.name = null;
      userSlot.phone = null;
      userSlot.paid = false;
      userSlot.checkedIn = false;
    }
    this.save();
    return { eligible: isEligible, hoursLeft: Math.round(hoursLeft) };
  }

  toggleCheckIn(roomId, seatNumber) {
    const room = this.state.rooms.find(r => r.id === roomId);
    if (!room) return;
    const slot = room.slots.find(s => s.seat === seatNumber);
    if (slot && slot.name) {
      slot.checkedIn = !slot.checkedIn;
      this.save();
    }
  }

  approveStudentCard(status) {
    this.state.currentUser.studentStatus = status; // 'approved' | 'none'
    this.save();
  }
}

export const store = new Store();

export function formatVND(amount) {
  return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
}