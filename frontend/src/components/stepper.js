/**
 * Room Lifecycle Stepper Component
 * Visualizes normal progression as well as side branches (Expired, Cancelled, Replenishing).
 */
import { h, renderIcon } from '../utils/dom.js';

const STANDARD_STEPS = [
  { key: 'FORMING', label: 'Tuyển người' },
  { key: 'WAITING_FOR_DM', label: 'Chờ DM' },
  { key: 'WAITING_FOR_ORGANIZATION', label: 'Chốt phòng' },
  { key: 'WAITING_FOR_DEPOSIT', label: 'Đặt cọc' },
  { key: 'READY_FOR_SESSION', label: 'Sẵn sàng' },
  { key: 'COMPLETED', label: 'Hoàn thành' },
];

export function renderRoomStepper(currentStatus) {
  const isBranch = ['EXPIRED', 'CANCELLED', 'REPLENISHING', 'WAITING_FOR_ADMIN_DECISION'].includes(currentStatus);

  const container = h('div', { className: 'room-stepper-container' });

  if (isBranch) {
    const branchLabels = {
      EXPIRED: { title: 'Phòng đã hết hạn gom người (EXPIRED)', desc: 'Không gom đủ người trong thời hạn 4 giờ quy định.' },
      CANCELLED: { title: 'Phòng đã bị hủy (CANCELLED)', desc: 'Hủy theo yêu cầu của Quản trị viên hoặc sự cố bất khả kháng.' },
      REPLENISHING: { title: 'Đang tìm người thay thế (REPLENISHING)', desc: 'Có người chơi xin rời phòng, hệ thống đang mở cửa sổ thay thế.' },
      WAITING_FOR_ADMIN_DECISION: { title: 'Chờ Admin quyết định (WAITING_FOR_ADMIN_DECISION)', desc: 'Hết thời hạn thay thế, chờ Ban Quản Trị xem xét duy trì hoặc đóng phòng.' },
    };
    const info = branchLabels[currentStatus] || { title: currentStatus, desc: '' };

    container.appendChild(
      h('div', { className: `stepper-branch-alert branch-${currentStatus.toLowerCase()}` },
        renderIcon('alertTriangle', 'branch-icon'),
        h('div', {},
          h('strong', {}, info.title),
          h('p', { className: 'text-muted' }, info.desc)
        )
      )
    );
    return container;
  }

  // Normal steps
  let currentIndex = STANDARD_STEPS.findIndex((s) => s.key === currentStatus);
  if (currentIndex === -1) currentIndex = 0;

  const stepsList = h('div', { className: 'stepper-steps' });

  STANDARD_STEPS.forEach((step, idx) => {
    const isCompleted = idx < currentIndex;
    const isCurrent = idx === currentIndex;
    const stateClass = isCompleted ? 'step-completed' : (isCurrent ? 'step-current' : 'step-pending');

    const stepEl = h(
      'div',
      { className: `stepper-step ${stateClass}` },
      h('div', { className: 'step-indicator' },
        isCompleted ? renderIcon('check') : String(idx + 1)
      ),
      h('span', { className: 'step-label' }, step.label)
    );

    stepsList.appendChild(stepEl);

    if (idx < STANDARD_STEPS.length - 1) {
      stepsList.appendChild(h('div', { className: `step-connector ${idx < currentIndex ? 'connector-active' : ''}` }));
    }
  });

  container.appendChild(stepsList);
  return container;
}
