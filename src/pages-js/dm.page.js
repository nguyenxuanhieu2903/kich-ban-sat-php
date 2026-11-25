import * as P from '../logic/portal-data.js';
import { badge, esc, field, initPortalChrome, openModal, runAction, toast } from '../logic/portal-ui.js';

const PFX = 'dm';
const { fmt } = P;
const WHO_KEY = 'kbs_portal_dm';
const $main = document.getElementById('dm-main');
const $tabs = document.getElementById('dm-tabs');
const $who = document.getElementById('dm-who');
const chrome = initPortalChrome({ prefix: PFX, expectedRole: 'dm', root: document.getElementById('dm-root') });

const SESSION_STATUS = {
  SCHEDULED: ['Đã lên lịch', 'info'], IN_PROGRESS: ['Đang diễn ra', 'gold'], COMPLETED: ['Hoàn tất', 'neutral'],
};

const ui = { tab: 'assignments', view: 'week', anchor: startOfDay(new Date()), sessionId: null, dmId: null };

function startOfDay(d) { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; }
function addDays(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x; }
function sameDay(a, b) { return fmt.dayKey(a.toISOString ? a.toISOString() : a) === fmt.dayKey(b.toISOString ? b.toISOString() : b); }
function storageGet(k) { try { return localStorage.getItem(k); } catch { return null; } }
function storageSet(k, v) { try { localStorage.setItem(k, v); } catch {  } }

function initDm() {
  const dms = P.dmUsers();
  const saved = Number(storageGet(WHO_KEY));
  ui.dmId = dms.some((d) => d.id === saved) ? saved : dms[0].id;
  $who.innerHTML = dms.map((d) => `<option value="${d.id}" ${d.id === ui.dmId ? 'selected' : ''}>${esc(d.full_name)}</option>`).join('');
}

function render() {
  const assignments = P.assignmentsOf(ui.dmId);
  const pending = assignments.filter((a) => a.assignment.status === 'PENDING').length;
  chrome.setBadge(pending);
  const tabs = [
    { id: 'assignments', label: 'Ca được giao', count: pending },
    { id: 'schedule', label: 'Lịch dẫn' },
    { id: 'session', label: 'Phiên chơi · Check-in & thu tiền' },
  ];
  $tabs.innerHTML = tabs.map((t) => `
    <button class="dm-tab" role="tab" type="button" data-tab="${t.id}" aria-selected="${t.id === ui.tab}">
      ${esc(t.label)}${t.count ? `<span class="dm-tab__count">${t.count}</span>` : ''}
    </button>`).join('');
  $main.innerHTML = { assignments: viewAssignments, schedule: viewSchedule, session: viewSession }[ui.tab](assignments);
  if (ui.tab === 'session') document.getElementById('dm-code')?.focus({ preventScroll: true });
}

function ticket(item, { actions = '' } = {}) {
  const { assignment: a, room, game, physicalRoom, count } = item;
  const cfg = P.currentConfig(game.id);
  const tone = { PENDING: 'warn', ACCEPTED: 'ok', REJECTED: 'bad' }[a.status];
  const label = { PENDING: 'Chờ bạn phản hồi', ACCEPTED: 'Đã nhận ca', REJECTED: 'Đã từ chối' }[a.status];
  return `
  <article class="ticket-card dm-ticket">
    <div class="dm-ticket__kicker">Ca dẫn #${a.id} · ${badge(PFX, label, tone)}</div>
    <h3 class="dm-ticket__title">${esc(game.name)} ${game.is_18plus ? '<span class="stamp-badge">18+</span>' : ''}</h3>
    <div class="dm-ticket__meta">
      <span>🕒 ${esc(fmt.dayLabel(room.scheduled_start_at))} · ${esc(fmt.time(room.scheduled_start_at))}${cfg ? ` (khoảng ${cfg.duration_min / 60} giờ)` : ''}</span>
      <span>🚪 ${physicalRoom ? esc(physicalRoom.name) : 'Chưa gán phòng'} · 👥 ${count}/${room.capacity} người</span>
      ${a.status === 'REJECTED' && a.reject_reason ? `<span>Lý do từ chối: ${esc(a.reject_reason)}</span>` : ''}
    </div>
    ${actions ? `<div class="dm-ticket__actions">${actions}</div>` : ''}
  </article>`;
}

function viewAssignments(items) {
  const by = (s) => items.filter((i) => i.assignment.status === s);
  const pending = by('PENDING');
  const accepted = by('ACCEPTED').filter((i) => i.room.status !== 'COMPLETED');
  const rejected = by('REJECTED');
  const grid = (list, actions) => `<div class="dm-grid dm-grid--2">${list.map((i) => ticket(i, { actions: actions?.(i) })).join('')}</div>`;
  return `
  <h1 class="dm-h1">Ca được giao</h1>
  <p class="dm-sub">Admin phân công ca dẫn cho bạn. Hãy nhận hoặc từ chối (kèm lý do) để Admin kịp điều phối.</p>
  <h2 class="dm-section-title">Chờ phản hồi (${pending.length})</h2>
  ${pending.length ? grid(pending, (i) => `
    <button type="button" class="dm-btn dm-btn--primary dm-btn--sm" data-action="accept" data-id="${i.assignment.id}">Nhận ca</button>
    <button type="button" class="dm-btn dm-btn--danger dm-btn--sm" data-action="reject" data-id="${i.assignment.id}">Từ chối</button>`) : '<div class="dm-card dm-empty">Không có ca nào đang chờ bạn phản hồi.</div>'}
  <h2 class="dm-section-title">Đã nhận (${accepted.length})</h2>
  ${accepted.length ? grid(accepted, (i) => `<button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-open-session="${P.find.sessionOfRoom(i.room.id)?.id}">Mở phiên chơi</button>`) : '<div class="dm-card dm-empty">Bạn chưa nhận ca nào sắp tới.</div>'}
  ${rejected.length ? `<h2 class="dm-section-title">Đã từ chối (${rejected.length})</h2>${grid(rejected)}` : ''}`;
}

function openRejectModal(assignmentId) {
  const item = P.assignmentsOf(ui.dmId).find((i) => i.assignment.id === Number(assignmentId));
  if (!item) return;
  openModal(PFX, {
    title: `Từ chối ca — ${item.game.name}`, submitLabel: 'Từ chối ca', danger: true,
    body: `<p style="margin-top:0" class="dm-muted">${esc(fmt.dateTime(item.room.scheduled_start_at))}</p>${field(PFX, 'Lý do từ chối (bắt buộc)', '<textarea name="reason" required placeholder="Ví dụ: Trùng lịch cá nhân"></textarea>')}`,
    onSubmit: async (d, key) => { P.respondAssignment({ assignmentId: item.assignment.id, dmId: ui.dmId, accept: false, reason: d.reason, key }); toast(PFX, 'Đã từ chối ca dẫn.'); },
  });
}

function weekStart(d) { const x = startOfDay(d); const dow = (x.getDay() + 6) % 7; return addDays(x, -dow); }

function viewSchedule() {
  const all = P.sessionsOf(ui.dmId);
  const days = ui.view === 'day' ? [ui.anchor] : Array.from({ length: 7 }, (_, i) => addDays(weekStart(ui.anchor), i));
  const step = ui.view === 'day' ? 1 : 7;
  const today = startOfDay(new Date());
  const title = ui.view === 'day' ? fmt.dayLabel(ui.anchor.toISOString()) : `${fmt.dayLabel(days[0].toISOString())} – ${fmt.dayLabel(days[6].toISOString())}`;
  return `
  <div class="dm-row dm-row--between">
    <div><h1 class="dm-h1">Lịch dẫn</h1><p class="dm-sub">Các phiên bạn đã nhận. Bấm vào một phiên để check-in, thu tiền và vận hành.</p></div>
    <div class="dm-toggle" role="group" aria-label="Kiểu xem">
      <button type="button" data-view="day" aria-pressed="${ui.view === 'day'}">Ngày</button>
      <button type="button" data-view="week" aria-pressed="${ui.view === 'week'}">Tuần</button>
    </div>
  </div>
  <div class="dm-row" style="margin-bottom:16px">
    <button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-nav="${-step}" aria-label="Trước">‹</button>
    <button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-nav="today">Hôm nay</button>
    <button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-nav="${step}" aria-label="Sau">›</button>
    <strong>${esc(title)}</strong>
  </div>
  ${days.map((day) => {
    const list = all.filter((s) => sameDay(s.session.scheduled_start_at, day));
    const isToday = sameDay(day, today);
    return `
    <section class="dm-day">
      <div class="dm-day__head"><span class="dm-day__name">${esc(fmt.dayLabel(day.toISOString()))}</span>${isToday ? '<span class="dm-day__today">Hôm nay</span>' : ''}</div>
      ${list.length ? list.map((s) => {
        const [label, tone] = SESSION_STATUS[s.session.status];
        return `
        <button type="button" class="dm-slot" data-open-session="${s.session.id}">
          <span class="dm-slot__time">${esc(fmt.time(s.session.scheduled_start_at))}</span>
          <span><div class="dm-slot__title">${esc(s.game.name)}</div><div class="dm-slot__sub">${esc(s.physicalRoom?.name || '')} · ${s.count}/${s.room.capacity} người</div></span>
          ${badge(PFX, label, tone)}
        </button>`;
      }).join('') : '<div class="dm-muted" style="font-size:13px;padding:4px 2px">Không có phiên nào.</div>'}
    </section>`;
  }).join('')}`;
}

function viewSession() {
  const sessions = P.sessionsOf(ui.dmId);
  if (!sessions.length) return '<h1 class="dm-h1">Phiên chơi</h1><div class="dm-card dm-empty">Bạn chưa có phiên nào. Hãy nhận ca ở mục "Ca được giao".</div>';
  const active = sessions.filter((s) => s.session.status !== 'COMPLETED');
  const ordered = [...active, ...sessions.filter((s) => s.session.status === 'COMPLETED')];
  if (!ordered.some((s) => s.session.id === ui.sessionId)) ui.sessionId = ordered[0].session.id;
  const d = P.sessionDetail(ui.sessionId);
  const [label, tone] = SESSION_STATUS[d.session.status];
  const editable = d.session.status !== 'COMPLETED';
  const need = P.MIN_CHECKED_IN_TO_START;
  const collected = d.members.reduce((s, m) => s + (m.deposit?.status === 'PAID' ? m.deposit.amount : 0) + (m.remaining?.status === 'PAID' ? m.remaining.amount : 0), 0);
  const outstanding = d.members.reduce((s, m) => s + (m.deposit?.status !== 'PAID' ? m.deposit?.amount || 0 : 0) + (m.remainingDue ? m.remainingAmount : 0), 0);
  const pct = Math.min(100, Math.round((d.checkedIn / Math.max(1, d.members.length)) * 100));
  const canStart = d.session.status === 'SCHEDULED' && d.checkedIn >= need;

  return `
  <div class="dm-row dm-row--between" style="margin-bottom:14px">
    <h1 class="dm-h1" style="margin:0">Phiên chơi</h1>
    <label class="dm-picker">Chọn phiên
      <select id="dm-session-select">${ordered.map((s) => `<option value="${s.session.id}" ${s.session.id === ui.sessionId ? 'selected' : ''}>${esc(fmt.dateTime(s.session.scheduled_start_at))} — ${esc(s.game.name)}</option>`).join('')}</select>
    </label>
  </div>

  <div class="dm-detail">
    <section class="dm-card">
      <div class="dm-detail__head">
        <div><h2 class="dm-card__title" style="font-size:20px;font-family:'Playfair Display',Georgia,serif">${esc(d.game.name)} ${d.game.is_18plus ? '<span class="stamp-badge">18+</span>' : ''}</h2>
          <div class="dm-stat"><span>🕒 <b>${esc(fmt.dateTime(d.session.scheduled_start_at))}</b></span><span>🚪 <b>${esc(d.physicalRoom?.name || '—')}</b></span><span>👥 <b>${d.members.length}</b> thành viên</span></div></div>
        ${badge(PFX, label, tone)}
      </div>
      <div style="margin-top:14px">
        <div class="dm-row dm-row--between" style="font-size:13px;margin-bottom:6px"><span>Đã check-in <b>${d.checkedIn}/${d.members.length}</b> (cần tối thiểu ${need} để bắt đầu)</span><span class="dm-muted">Đã thu ${fmt.vnd(collected)} · Còn phải thu ${fmt.vnd(outstanding)}</span></div>
        <div class="dm-progress"><span style="width:${pct}%"></span></div>
      </div>
      ${d.session.started_at ? `<p class="dm-muted" style="margin:12px 0 0;font-size:13px">Bắt đầu: ${esc(fmt.dateTime(d.session.started_at))}${d.session.completed_at ? ` · Hoàn tất: ${esc(fmt.dateTime(d.session.completed_at))}` : ''}</p>` : ''}
    </section>

    ${editable ? `
    <form class="dm-scan" id="dm-scan-form" autocomplete="off">
      <input id="dm-code" name="code" placeholder="Quét hoặc nhập mã vé (VD: KBS-AB12CD)" aria-label="Mã vé QR" maxlength="16">
      <button type="submit" class="dm-btn dm-btn--gold">Check-in</button>
    </form>` : ''}

    <section class="dm-card" style="padding:0">
      ${d.members.map((m) => {
        const depPaid = m.deposit?.status === 'PAID';
        const rem = m.remaining?.status === 'PAID';
        return `
        <div class="dm-member">
          <div>
            <div class="dm-member__name">${esc(m.user.full_name)} ${m.participant.checked_in_at ? badge(PFX, `Đã check-in ${fmt.time(m.participant.checked_in_at)}`, 'ok') : badge(PFX, 'Chưa check-in', 'neutral')}</div>
            <div class="dm-member__code">${esc(m.participant.ticket_code)} · Vé ${esc(fmt.vnd(m.participant.final_price))}</div>
            <div class="dm-member__pay">
              ${badge(PFX, depPaid ? `Cọc ${fmt.vnd(m.deposit.amount)} ✓` : `Chưa cọc ${fmt.vnd(m.deposit?.amount || P.DEPOSIT_AMOUNT)}`, depPaid ? 'ok' : 'bad')}
              ${badge(PFX, rem ? `Đã thu còn lại ${fmt.vnd(m.remaining.amount)}` : `Còn lại ${fmt.vnd(m.remainingAmount)}`, rem ? 'ok' : 'warn')}
            </div>
          </div>
          <div class="dm-actions">
            ${editable && !depPaid ? `<button type="button" class="dm-btn dm-btn--ghost dm-btn--sm" data-action="pay" data-type="DEPOSIT" data-id="${m.participant.id}">Xác nhận cọc ${fmt.vnd(P.DEPOSIT_AMOUNT)}</button>` : ''}
            ${editable && depPaid && !m.participant.checked_in_at ? `<button type="button" class="dm-btn dm-btn--gold dm-btn--sm" data-action="checkin" data-code="${esc(m.participant.ticket_code)}">Check-in</button>` : ''}
            ${editable && m.participant.checked_in_at && m.remainingDue ? `<button type="button" class="dm-btn dm-btn--primary dm-btn--sm" data-action="pay" data-type="REMAINING" data-id="${m.participant.id}">Thu ${fmt.vnd(m.remainingAmount)}</button>` : ''}
          </div>
        </div>`;
      }).join('')}
    </section>

    <div class="dm-actions">
      ${d.session.status === 'SCHEDULED' ? `<button type="button" class="dm-btn dm-btn--primary" data-action="start" ${canStart ? '' : 'disabled'} title="${canStart ? '' : `Cần tối thiểu ${need} người check-in`}">▶ Bắt đầu phiên chơi</button>${canStart ? '' : `<span class="dm-muted" style="align-self:center;font-size:13px">Cần thêm ${Math.max(0, need - d.checkedIn)} người check-in để bắt đầu.</span>`}` : ''}
      ${d.session.status === 'IN_PROGRESS' ? '<button type="button" class="dm-btn dm-btn--gold" data-action="complete">✔ Hoàn tất phiên chơi</button>' : ''}
    </div>
  </div>`;
}

$tabs.addEventListener('click', (e) => {
  const t = e.target.closest('[data-tab]');
  if (t) { ui.tab = t.dataset.tab; render(); }
});

function openSession(id) { ui.sessionId = Number(id); ui.tab = 'session'; render(); window.scrollTo({ top: 0 }); }

$main.addEventListener('click', (e) => {
  const open = e.target.closest('[data-open-session]');
  if (open) { openSession(open.dataset.openSession); return; }
  const view = e.target.closest('[data-view]');
  if (view) { ui.view = view.dataset.view; render(); return; }
  const nav = e.target.closest('[data-nav]');
  if (nav) {
    ui.anchor = nav.dataset.nav === 'today' ? startOfDay(new Date()) : addDays(ui.anchor, Number(nav.dataset.nav));
    render();
    return;
  }

  const btn = e.target.closest('[data-action]');
  if (!btn || btn.disabled) return;
  const dmId = ui.dmId;
  const sessionId = ui.sessionId;
  switch (btn.dataset.action) {
    case 'accept':
      runAction(PFX, btn, (key) => P.respondAssignment({ assignmentId: btn.dataset.id, dmId, accept: true, key }), 'Đã nhận ca dẫn.');
      break;
    case 'reject': openRejectModal(btn.dataset.id); break;
    case 'checkin':
      runAction(PFX, btn, (key) => P.checkIn({ sessionId, dmId, ticketCode: btn.dataset.code, key }), (r) => `Đã check-in ${r.name}.`);
      break;
    case 'pay':
      runAction(PFX, btn, (key) => P.confirmPayment({ sessionId, dmId, participantId: btn.dataset.id, type: btn.dataset.type, key }),
        (r) => (r.type === 'DEPOSIT' ? `Đã xác nhận cọc ${fmt.vnd(r.amount)}.` : `Đã thu ${fmt.vnd(r.amount)}.`));
      break;
    case 'start':
      runAction(PFX, btn, (key) => P.startSession({ sessionId, dmId, key }), 'Phiên chơi đã bắt đầu.');
      break;
    case 'complete':
      runAction(PFX, btn, (key) => P.completeSession({ sessionId, dmId, key }), 'Đã hoàn tất phiên chơi.');
      break;
    default: break;
  }
});

$main.addEventListener('submit', (e) => {
  if (e.target.id !== 'dm-scan-form') return;
  e.preventDefault();
  const input = document.getElementById('dm-code');
  const btn = e.target.querySelector('button[type="submit"]');
  const code = input.value;
  runAction(PFX, btn, (key) => P.checkIn({ sessionId: ui.sessionId, dmId: ui.dmId, ticketCode: code, key }), (r) => `Đã check-in ${r.name}.`)
    .then((ok) => { if (ok) { input.value = ''; } document.getElementById('dm-code')?.focus(); });
});

$main.addEventListener('change', (e) => {
  if (e.target.id === 'dm-session-select') { ui.sessionId = Number(e.target.value); render(); }
});

$who.addEventListener('change', () => {
  ui.dmId = Number($who.value);
  storageSet(WHO_KEY, String(ui.dmId));
  ui.sessionId = null;
  render();
});

document.getElementById('dm-reset')?.addEventListener('click', () => {
  if (window.confirm('Xoá toàn bộ dữ liệu demo và tạo lại từ đầu?')) { P.resetDemo(); toast(PFX, 'Đã đặt lại dữ liệu demo.'); }
});

if (chrome.allowed) {
  P.subscribe(render);
  initDm();
  render();
}
