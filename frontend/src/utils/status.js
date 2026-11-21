/**
 * Status Enums & Vietnamese Mappings (V15/V16 Business Baseline)
 * 7 Domains: GameRoom, RoomParticipant, DMAssignment, ScheduleProposal, Session, PlayerPayment, Refund
 */

export const GAMEROOM_STATUS = {
  FORMING: 'FORMING',
  WAITING_FOR_DM: 'WAITING_FOR_DM',
  WAITING_FOR_ORGANIZATION: 'WAITING_FOR_ORGANIZATION',
  WAITING_FOR_DEPOSIT: 'WAITING_FOR_DEPOSIT',
  REPLENISHING: 'REPLENISHING',
  WAITING_FOR_ADMIN_DECISION: 'WAITING_FOR_ADMIN_DECISION',
  READY_FOR_SESSION: 'READY_FOR_SESSION',
  COMPLETED: 'COMPLETED',
  EXPIRED: 'EXPIRED',
  CANCELLED: 'CANCELLED',
};

export const PARTICIPANT_STATUS = {
  PENDING_DEPOSIT: 'PENDING_DEPOSIT',
  DEPOSITED: 'DEPOSITED',
  LEAVE_REQUESTED: 'LEAVE_REQUESTED', // Vẫn tính là active
  LEFT: 'LEFT',
  CANCELLED_UNPAID: 'CANCELLED_UNPAID',
  CANCELLED_BY_ADMIN: 'CANCELLED_BY_ADMIN',
};

export const DM_ASSIGNMENT_STATUS = {
  PENDING: 'PENDING',
  ACCEPTED: 'ACCEPTED',
  REJECTED: 'REJECTED',
  CANCELLED: 'CANCELLED',
};

export const SCHEDULE_PROPOSAL_STATUS = {
  PROPOSED: 'PROPOSED',
  CONFIRMED: 'CONFIRMED',
  REJECTED: 'REJECTED',
  CANCELLED: 'CANCELLED',
};

export const SESSION_STATUS = {
  SCHEDULED: 'SCHEDULED',
  IN_PROGRESS: 'IN_PROGRESS',
  COMPLETED: 'COMPLETED',
  CANCELLED: 'CANCELLED',
};

export const PAYMENT_STATUS = {
  NOT_OPEN: 'NOT_OPEN',
  DEPOSIT_PENDING: 'DEPOSIT_PENDING',
  PARTIALLY_PAID: 'PARTIALLY_PAID',
  PAID: 'PAID',
  EXPIRED: 'EXPIRED',
  CANCELLED: 'CANCELLED',
};

export const REFUND_STATUS = {
  REQUESTED: 'REQUESTED',
  PROCESSING: 'PROCESSING',
  SUCCEEDED: 'SUCCEEDED',
  FAILED: 'FAILED',
  REJECTED: 'REJECTED',
};

/**
 * Terminal States across domains
 */
export const TERMINAL_STATES = {
  GameRoom: ['COMPLETED', 'EXPIRED', 'CANCELLED'],
  RoomParticipant: ['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'],
  DMAssignment: ['ACCEPTED', 'REJECTED', 'CANCELLED'],
  ScheduleProposal: ['CONFIRMED', 'REJECTED', 'CANCELLED'],
  Session: ['COMPLETED', 'CANCELLED'],
  PlayerPayment: ['PAID', 'EXPIRED', 'CANCELLED'],
  Refund: ['SUCCEEDED', 'FAILED', 'REJECTED'],
};

/**
 * Vietnamese Status Metadata
 */
const STATUS_METADATA = {
  // GameRoom
  FORMING: { label: 'Đang tuyển người', color: 'amber', variant: 'warning' },
  WAITING_FOR_DM: { label: 'Chờ phân công DM', color: 'purple', variant: 'info' },
  WAITING_FOR_ORGANIZATION: { label: 'Chờ chốt lịch & phòng', color: 'cyan', variant: 'info' },
  WAITING_FOR_DEPOSIT: { label: 'Chờ đặt cọc', color: 'amber', variant: 'warning' },
  REPLENISHING: { label: 'Đang tìm người thay thế', color: 'amber', variant: 'warning' },
  WAITING_FOR_ADMIN_DECISION: { label: 'Chờ Admin quyết định', color: 'purple', variant: 'attention' },
  READY_FOR_SESSION: { label: 'Sẵn sàng chơi', color: 'emerald', variant: 'success' },
  COMPLETED: { label: 'Đã hoàn thành', color: 'emerald', variant: 'success' },
  EXPIRED: { label: 'Hết hạn tuyển người', color: 'danger', variant: 'danger' },
  CANCELLED: { label: 'Đã hủy', color: 'danger', variant: 'danger' },

  // RoomParticipant
  PENDING_DEPOSIT: { label: 'Chờ cọc 50k', color: 'amber', variant: 'warning' },
  DEPOSITED: { label: 'Đã đặt cọc', color: 'emerald', variant: 'success' },
  LEAVE_REQUESTED: { label: 'Đã gửi yêu cầu rời', color: 'purple', variant: 'attention' },
  LEFT: { label: 'Đã rời phòng', color: 'muted', variant: 'neutral' },
  CANCELLED_UNPAID: { label: 'Quá hạn cọc (Đã hủy)', color: 'danger', variant: 'danger' },
  CANCELLED_BY_ADMIN: { label: 'Admin hủy tham gia', color: 'danger', variant: 'danger' },

  // DM Assignment
  PENDING: { label: 'Chờ phản hồi', color: 'amber', variant: 'warning' },
  ACCEPTED: { label: 'Đã nhận dẫn dắt', color: 'emerald', variant: 'success' },
  REJECTED: { label: 'Đã từ chối', color: 'danger', variant: 'danger' },

  // Schedule Proposal
  PROPOSED: { label: 'Đang lấy ý kiến', color: 'cyan', variant: 'info' },
  CONFIRMED: { label: 'Lịch mới đã chốt', color: 'emerald', variant: 'success' },

  // Session
  SCHEDULED: { label: 'Đã xếp lịch', color: 'cyan', variant: 'info' },
  IN_PROGRESS: { label: 'Đang diễn ra', color: 'emerald', variant: 'success' },

  // PlayerPayment
  NOT_OPEN: { label: 'Chưa mở thanh toán', color: 'muted', variant: 'neutral' },
  PARTIALLY_PAID: { label: 'Đã cọc một phần', color: 'amber', variant: 'warning' },
  PAID: { label: 'Đã thanh toán đủ', color: 'emerald', variant: 'success' },

  // Refund
  REQUESTED: { label: 'Yêu cầu hoàn cọc', color: 'amber', variant: 'warning' },
  PROCESSING: { label: 'Đang xử lý hoàn', color: 'cyan', variant: 'info' },
  SUCCEEDED: { label: 'Hoàn tiền thành công', color: 'emerald', variant: 'success' },
  FAILED: { label: 'Hoàn tiền thất bại', color: 'danger', variant: 'danger' },
};

/**
 * Get human-readable status metadata safely
 */
export function getStatusMeta(status) {
  if (!status) {
    return { label: 'Không xác định', color: 'muted', variant: 'neutral' };
  }
  const meta = STATUS_METADATA[status];
  if (meta) {
    return meta;
  }
  // Fallback gracefully for unknown / custom statuses
  return {
    label: String(status).replace(/_/g, ' '),
    color: 'muted',
    variant: 'neutral',
  };
}

export function isTerminal(domain, status) {
  const list = TERMINAL_STATES[domain];
  return Array.isArray(list) && list.includes(status);
}
