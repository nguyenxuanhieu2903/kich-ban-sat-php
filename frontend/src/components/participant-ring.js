/**
 * Interactive SVG Round Table Participant Ring Component
 * Visually positions participants around a tabletop noir circle.
 */
import { h, escapeHtml } from '../utils/dom.js';
import { PARTICIPANT_STATUS } from '../utils/status.js';

export function renderParticipantRing({
  participants = [],
  minPlayers = 5,
  maxPlayers = 8,
  currentUserId = null,
  size = 320,
}) {
  const center = size / 2;
  const radius = size * 0.38;

  // Total slots to show = maxPlayers
  const totalSlots = Math.max(maxPlayers, participants.length);
  const activeParticipants = participants.filter((p) => !['LEFT', 'CANCELLED_UNPAID', 'CANCELLED_BY_ADMIN'].includes(p.status));
  const currentCount = activeParticipants.length;

  let slotsSvg = '';

  for (let i = 0; i < totalSlots; i++) {
    // Angle in radians (start from top: -pi/2)
    const angle = (2 * Math.PI * i) / totalSlots - Math.PI / 2;
    const cx = center + radius * Math.cos(angle);
    const cy = center + radius * Math.sin(angle);

    const part = activeParticipants[i] || null;

    if (part) {
      const isMe = part.user_id === currentUserId;
      let strokeColor = '#C4A36B'; // Gold
      let fillColor = 'rgba(196, 163, 107, 0.15)';
      let statusIcon = '●';

      if (part.status === PARTICIPANT_STATUS.DEPOSITED) {
        strokeColor = '#4FB39A'; // Emerald
        fillColor = 'rgba(79, 179, 154, 0.2)';
        statusIcon = '✓';
      } else if (part.status === PARTICIPANT_STATUS.LEAVE_REQUESTED) {
        strokeColor = '#A78BFA'; // Purple
        fillColor = 'rgba(167, 139, 250, 0.2)';
        statusIcon = '?';
      } else if (part.status === PARTICIPANT_STATUS.PENDING_DEPOSIT) {
        strokeColor = '#E8A33D'; // Amber
        fillColor = 'rgba(232, 163, 61, 0.2)';
      }

      slotsSvg += `
        <g class="slot-node slot-occupied ${isMe ? 'slot-me' : ''}" data-user="${escapeHtml(part.name)}">
          ${isMe ? `<circle cx="${cx}" cy="${cy}" r="26" fill="none" stroke="#C4A36B" stroke-width="1.5" stroke-dasharray="4,2"/>` : ''}
          <circle cx="${cx}" cy="${cy}" r="20" fill="${fillColor}" stroke="${strokeColor}" stroke-width="2"/>
          <text x="${cx}" y="${cy + 5}" font-family="'JetBrains Mono', monospace" font-size="12" font-weight="bold" fill="#F3F0E9" text-anchor="middle">
            ${statusIcon}
          </text>
          <text x="${cx}" y="${cy + 34}" font-family="'Be Vietnam Pro', sans-serif" font-size="10.5" fill="${isMe ? '#C4A36B' : '#92949B'}" font-weight="${isMe ? 'bold' : 'normal'}" text-anchor="middle">
            ${escapeHtml(part.name.split(' ')[0])}${isMe ? ' (Bạn)' : ''}
          </text>
        </g>
      `;
    } else {
      // Empty chair
      slotsSvg += `
        <g class="slot-node slot-empty">
          <circle cx="${cx}" cy="${cy}" r="18" fill="rgba(30,32,40,0.6)" stroke="#383C4A" stroke-width="1.5" stroke-dasharray="4,3"/>
          <text x="${cx}" y="${cy + 4}" font-family="'JetBrains Mono', monospace" font-size="12" fill="#676973" text-anchor="middle">+</text>
          <text x="${cx}" y="${cy + 32}" font-family="'Be Vietnam Pro', sans-serif" font-size="10" fill="#676973" text-anchor="middle">Ghế trống</text>
        </g>
      `;
    }
  }

  const svgHtml = `
    <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" class="participant-ring-svg">
      <defs>
        <radialGradient id="tableGrad" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stop-color="#1E2028"/>
          <stop offset="100%" stop-color="#15171C"/>
        </radialGradient>
      </defs>

      <!-- Center Table Surface -->
      <circle cx="${center}" cy="${center}" r="${radius * 0.65}" fill="url(#tableGrad)" stroke="#262933" stroke-width="2"/>
      <circle cx="${center}" cy="${center}" r="${radius * 0.60}" fill="none" stroke="rgba(196,163,107,0.15)" stroke-width="1" stroke-dasharray="6,4"/>

      <!-- Center Info -->
      <text x="${center}" y="${center - 6}" font-family="'JetBrains Mono', monospace" font-size="20" font-weight="bold" fill="#F3F0E9" text-anchor="middle">
        ${currentCount} / ${maxPlayers}
      </text>
      <text x="${center}" y="${center + 14}" font-family="'Be Vietnam Pro', sans-serif" font-size="10.5" fill="#C4A36B" text-anchor="middle" letter-spacing="1">
        ${currentCount >= minPlayers ? 'ĐỦ ĐIỀU KIỆN' : `THIẾU ${minPlayers - currentCount} NGƯỜI`}
      </text>

      <!-- Connecting Spokes / Rings -->
      ${slotsSvg}
    </svg>
  `;

  const container = h('div', { className: 'participant-ring-wrapper', innerHTML: svgHtml });
  return container;
}
