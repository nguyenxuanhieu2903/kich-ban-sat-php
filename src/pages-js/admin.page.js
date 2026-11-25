import * as P from '../logic/portal-data.js';
import { badge, esc, field, initPortalChrome, openModal, runAction, toast } from '../logic/portal-ui.js';

const PFX = 'admin';
const { fmt } = P;
const $main = document.getElementById('admin-main');
const $tabs = document.getElementById('admin-tabs');
const chrome = initPortalChrome({ prefix: PFX, expectedRole: 'admin', root: document.getElementById('admin-root') });

const ROOM_STATUS = {
  OPEN: ['Đang tuyển', 'info'], FULL: ['Đủ người', 'ok'], CONFIRMED: ['Đã chốt', 'ok'],
  IN_PROGRESS: ['Đang chơi', 'gold'], COMPLETED: ['Hoàn tất', 'neutral'], CANCELLED: ['Đã hủy', 'bad'],
};
const DIFF_TONE = { 'Dễ': 'ok', 'Trung bình': 'warn', 'Khó': 'bad' };

const ui = { tab: 'kpi', dispatchFilter: 'all', refundFilter: 'PENDING', gameQuery: '', gameFilter: 'all' };

function tabsDef() {
  const k = P.kpis();
  return [
    { id: 'kpi', label: 'Tổng quan' },
    { id: 'dispatch', label: 'Điều phối phòng & DM', count: k.roomsNeedingDm },
    { id: 'refunds', label: 'Hoàn cọc 24h', count: k.pendingRefunds },
    { id: 'games', label: 'Kho kịch bản' },
    { id: 'venue', label: 'Địa điểm & Thẻ HSSV', count: k.pendingCards },
  ];
}

function render() {
  const tabs = tabsDef();
  chrome.setBadge(tabs.reduce((n, t) => n + (t.count || 0), 0));
  if (!tabs.some((t) => t.id === ui.tab)) ui.tab = 'kpi';
  $tabs.innerHTML = tabs.map((t) => `
    <button class="admin-tab" role="tab" type="button" data-tab="${t.id}" aria-selected="${t.id === ui.tab}">
      ${esc(t.label)}${t.count ? `<span class="admin-tab__count">${t.count}</span>` : ''}
    </button>`).join('');
  const view = { kpi: viewKpi, dispatch: viewDispatch, refunds: viewRefunds, games: viewGames, venue: viewVenue }[ui.tab];
  $main.innerHTML = view();
}

function viewKpi() {
  const k = P.kpis();
  const peak = Math.max(1, ...k.revenue7d.map((d) => d.amount));
  const alerts = [
    k.roomsNeedingDm ? `<div class="admin-alert">⚠ <span><strong>${k.roomsNeedingDm}</strong> phòng chưa có Quản trò nhận ca.</span> <a href="#dispatch" data-go="dispatch">Điều phối ngay</a></div>` : '',
    k.pendingRefunds ? `<div class="admin-alert">⚠ <span><strong>${k.pendingRefunds}</strong> yêu cầu hoàn cọc đang chờ duyệt.</span> <a href="#refunds" data-go="refunds">Xem yêu cầu</a></div>` : '',
    k.pendingCards ? `<div class="admin-alert">⚠ <span><strong>${k.pendingCards}</strong> thẻ HSSV chờ xác minh.</span> <a href="#venue" data-go="venue">Duyệt thẻ</a></div>` : '',
  ].join('');
  return `
  <h1 class="admin-h1">Tổng quan</h1>
  <p class="admin-sub">Doanh thu cọc/vé, số phòng đang mở và tỷ lệ lấp đầy.</p>
  <div class="admin-kpis">
    <div class="admin-kpi admin-kpi--gold"><div class="admin-kpi__label">Tổng doanh thu</div><div class="admin-kpi__value">${P.fmt.vnd(k.revenueTotal)}</div><div class="admin-kpi__note">Cọc ${fmt.vnd(k.revenueDeposit)} + vé còn lại ${fmt.vnd(k.revenueRemaining)}</div></div>
    <div class="admin-kpi"><div class="admin-kpi__label">Doanh thu tháng này</div><div class="admin-kpi__value">${fmt.vnd(k.revenueMonth)}</div><div class="admin-kpi__note">Tính theo thời điểm thu tiền</div></div>
    <div class="admin-kpi"><div class="admin-kpi__label">Phòng đang mở</div><div class="admin-kpi__value">${k.openRooms}</div><div class="admin-kpi__note">Đang tuyển thành viên</div></div>
    <div class="admin-kpi"><div class="admin-kpi__label">Tỷ lệ lấp đầy</div><div class="admin-kpi__value">${k.fillRate}%</div><div class="admin-kpi__note">Ghế đã đặt / tổng ghế các phòng sắp diễn ra</div></div>
  </div>
  ${alerts ? `<div class="admin-grid" style="margin-bottom:18px">${alerts}</div>` : ''}
  <section class="admin-card">
    <h2 class="admin-card__title">Doanh thu 7 ngày gần nhất</h2>
    <div class="admin-chart">
      ${k.revenue7d.map((d, i) => `
      <div class="admin-chart__col">
        <span class="admin-chart__val">${d.amount ? Math.round(d.amount / 1000) + 'k' : '0'}</span>
        <div class="admin-chart__bar ${i === 6 ? 'admin-chart__bar--today' : ''}" style="height:${Math.round((d.amount / peak) * 100)}%"></div>
        <span class="admin-chart__label">${esc(d.label)}</span>
      </div>`).join('')}
    </div>
  </section>`;
}

function dmCell(row) {
  const a = row.assignment;
  if (!a || a.status === 'REJECTED') {
    const why = a?.status === 'REJECTED' ? `<div class="admin-muted" style="font-size:12px" title="${esc(a.reject_reason)}">${esc(P.find.user(a.dm_id)?.full_name)} từ chối: ${esc(a.reject_reason)}</div>` : '';
    return `${badge(PFX, a ? 'DM từ chối — cần phân công lại' : 'Chưa phân công', 'bad')}${why}`;
  }
  const tone = a.status === 'ACCEPTED' ? 'ok' : 'warn';
  return `<div>${esc(row.dm.full_name)}</div>${badge(PFX, a.status === 'ACCEPTED' ? 'DM đã nhận ca' : 'Chờ DM phản hồi', tone)}`;
}

function viewDispatch() {
  let rows = P.listRooms();
  if (ui.dispatchFilter === 'needsDm') rows = rows.filter((r) => r.needsDm);
  if (ui.dispatchFilter === 'reopen') rows = rows.filter((r) => r.canReopen);
  const filterBtn = (id, label) => `<button type="button" class="admin-btn ${ui.dispatchFilter === id ? 'admin-btn--gold' : 'admin-btn--ghost'} admin-btn--sm" data-dispatch-filter="${id}">${label}</button>`;
  return `
  <h1 class="admin-h1">Điều phối phòng & phân công Quản trò</h1>
  <p class="admin-sub">Gán DM và phòng vật lý cho từng phòng chơi; mở lại đợt tuyển bổ sung khi có khách hủy sát giờ (trong 24 giờ trước giờ chơi).</p>
  <div class="admin-filter">${filterBtn('all', 'Tất cả')}${filterBtn('needsDm', 'Cần phân công DM')}${filterBtn('reopen', 'Có thể mở tuyển bổ sung')}</div>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Kịch bản / Giờ chơi</th><th>Thành viên</th><th>Quản trò</th><th>Phòng vật lý</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>
    ${rows.length ? rows.map((r) => {
      const [label, tone] = ROOM_STATUS[r.room.status] || [r.room.status, 'neutral'];
      return `
      <tr>
        <td><strong>${esc(r.game.name)}</strong> ${r.game.is_18plus ? '<span class="stamp-badge">18+</span>' : ''}<div class="admin-muted" style="font-size:13px">${esc(fmt.dateTime(r.room.scheduled_start_at))}</div></td>
        <td><span class="admin-mono">${r.count}/${r.room.capacity}</span>${r.room.recruit_reopened ? ` ${badge(PFX, 'Đã mở tuyển bổ sung', 'gold')}` : ''}</td>
        <td>${dmCell(r)}</td>
        <td>${r.physicalRoom ? esc(r.physicalRoom.name) : '<span class="admin-muted">—</span>'}</td>
        <td>${badge(PFX, label, tone)}</td>
        <td><div class="admin-actions">
          ${['OPEN', 'FULL', 'CONFIRMED'].includes(r.room.status) ? `<button type="button" class="admin-btn admin-btn--primary admin-btn--sm" data-action="assign" data-id="${r.room.id}">${r.needsDm ? 'Phân công DM' : 'Đổi DM / phòng'}</button>` : ''}
          ${r.canReopen ? `<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-action="reopen" data-id="${r.room.id}">Mở tuyển bổ sung</button>` : ''}
        </div></td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="admin-empty">Không có phòng nào khớp bộ lọc.</td></tr>'}
    </tbody></table></div>`;
}

function openAssignModal(roomId) {
  const row = P.listRooms().find((r) => r.room.id === Number(roomId));
  if (!row) return;
  const dms = P.dmUsers();
  const rooms = P.physicalRooms().filter((r) => r.is_active);
  const cur = row.assignment && row.assignment.status !== 'REJECTED' ? row.assignment : null;
  openModal(PFX, {
    title: `Phân công — ${row.game.name}`,
    submitLabel: 'Gửi phân công',
    body: `
      <p class="admin-muted" style="margin-top:0;font-size:13px">${esc(fmt.dateTime(row.room.scheduled_start_at))} · ${row.count} thành viên · Thời lượng khoảng ${(P.currentConfig(row.game.id)?.duration_min || 180) / 60} giờ</p>
      ${field(PFX, 'Quản trò', `<select name="dmId" required>${dms.map((d) => `<option value="${d.id}" ${cur?.dm_id === d.id ? 'selected' : ''}>${esc(d.full_name)}</option>`).join('')}</select>`, 'Hệ thống chặn nếu DM đã có ca trùng giờ.')}
      ${field(PFX, 'Phòng vật lý', `<select name="roomId" required>${rooms.map((r) => `<option value="${r.id}" ${cur?.physical_room_id === r.id ? 'selected' : ''}>${esc(r.name)} (chứa ${r.capacity} người)</option>`).join('')}</select>`, 'Sức chứa phải ≥ số thành viên hiện tại.')}`,
    onSubmit: async (data, key) => {
      P.assignDm({ roomId: row.room.id, dmId: data.dmId, physicalRoomId: data.roomId, key });
      toastDone('Đã gửi phân công, chờ Quản trò phản hồi.');
    },
  });
}

function viewRefunds() {
  const all = P.listRefunds();
  const rows = ui.refundFilter === 'PENDING' ? all.filter((r) => r.refund.status === 'PENDING') : all;
  const filterBtn = (id, label) => `<button type="button" class="admin-btn ${ui.refundFilter === id ? 'admin-btn--gold' : 'admin-btn--ghost'} admin-btn--sm" data-refund-filter="${id}">${label}</button>`;
  const statusBadge = (s) => badge(PFX, { PENDING: 'Chờ duyệt', APPROVED: 'Đã hoàn', REJECTED: 'Từ chối' }[s], { PENDING: 'warn', APPROVED: 'ok', REJECTED: 'bad' }[s]);
  return `
  <h1 class="admin-h1">Duyệt hoàn cọc 24 giờ</h1>
  <p class="admin-sub">Chỉ hoàn 100% cọc (${fmt.vnd(P.DEPOSIT_AMOUNT)}) khi người chơi hủy trước giờ chơi hơn ${P.REFUND_MIN_HOURS} giờ. Hủy sát giờ sẽ mất cọc.</p>
  <div class="admin-filter">${filterBtn('PENDING', 'Chờ duyệt')}${filterBtn('ALL', 'Tất cả')}</div>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Người chơi</th><th>Kịch bản / Giờ chơi</th><th>Gửi yêu cầu lúc</th><th>Hủy trước giờ chơi</th><th>Điều kiện</th><th class="admin-num">Số tiền</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>
    ${rows.length ? rows.map((r) => `
      <tr>
        <td><strong>${esc(r.user.full_name)}</strong><div class="admin-muted" style="font-size:12px">${esc(r.user.email)}</div></td>
        <td>${esc(r.game.name)}<div class="admin-muted" style="font-size:13px">${esc(fmt.dateTime(r.room.scheduled_start_at))}</div></td>
        <td>${esc(fmt.dateTime(r.refund.requested_at))}</td>
        <td class="admin-mono">${r.hoursBefore.toFixed(1)} giờ</td>
        <td>${badge(PFX, r.eligible ? `Đủ điều kiện (> ${P.REFUND_MIN_HOURS}h)` : `Không đủ (≤ ${P.REFUND_MIN_HOURS}h)`, r.eligible ? 'ok' : 'bad')}</td>
        <td class="admin-num">${fmt.vnd(r.refund.amount)}</td>
        <td>${statusBadge(r.refund.status)}${r.refund.reason && r.refund.status !== 'PENDING' ? `<div class="admin-muted" style="font-size:12px">${esc(r.refund.reason)}</div>` : ''}</td>
        <td>${r.refund.status === 'PENDING' ? `<div class="admin-actions">
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" data-action="refund-approve" data-id="${r.refund.id}" ${r.eligible ? '' : 'disabled title="Hủy chưa tới 24 giờ — không đủ điều kiện hoàn cọc"'}>Duyệt hoàn</button>
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-action="refund-reject" data-id="${r.refund.id}">Từ chối</button></div>` : '<span class="admin-muted">—</span>'}</td>
      </tr>`).join('') : '<tr><td colspan="8" class="admin-empty">Không có yêu cầu hoàn cọc nào.</td></tr>'}
    </tbody></table></div>`;
}

function openRefundModal(refundId, approve) {
  const row = P.listRefunds().find((r) => r.refund.id === Number(refundId));
  if (!row) return;
  const summary = `<p style="margin-top:0"><strong>${esc(row.user.full_name)}</strong> — ${esc(row.game.name)}, ${esc(fmt.dateTime(row.room.scheduled_start_at))}<br><span class="admin-muted">Hủy trước giờ chơi ${row.hoursBefore.toFixed(1)} giờ · ${fmt.vnd(row.refund.amount)}</span></p>`;
  if (approve) {
    openModal(PFX, {
      title: 'Duyệt hoàn cọc', submitLabel: 'Xác nhận hoàn cọc',
      body: `${summary}<div class="admin-rule admin-rule--ok">Đủ điều kiện hoàn 100% cọc. Thành viên sẽ được gỡ khỏi phòng.</div>`,
      onSubmit: async (_d, key) => { P.approveRefund({ refundId: row.refund.id, key }); toastDone('Đã duyệt hoàn cọc.'); },
    });
  } else {
    openModal(PFX, {
      title: 'Từ chối hoàn cọc', submitLabel: 'Từ chối', danger: true,
      body: `${summary}${field(PFX, 'Lý do từ chối (bắt buộc)', '<textarea name="reason" required placeholder="Ví dụ: Hủy sát giờ, mất cọc theo quy định"></textarea>')}`,
      onSubmit: async (d, key) => { P.rejectRefund({ refundId: row.refund.id, reason: d.reason, key }); toastDone('Đã từ chối yêu cầu.'); },
    });
  }
}

function viewGames() {
  const total = P.listGames().length;
  let rows = P.listGames();
  const q = ui.gameQuery.trim().toLowerCase();
  if (q) rows = rows.filter((r) => r.game.name.toLowerCase().includes(q) || r.game.genre.toLowerCase().includes(q));
  if (ui.gameFilter === 'active') rows = rows.filter((r) => r.game.is_active);
  if (ui.gameFilter === 'inactive') rows = rows.filter((r) => !r.game.is_active);
  if (ui.gameFilter === '18') rows = rows.filter((r) => r.game.is_18plus);
  return `
  <div class="admin-row admin-row--between"><div><h1 class="admin-h1">Kho kịch bản</h1><p class="admin-sub">${total} kịch bản · bảng giá theo khung giờ · phiên bản cấu hình. Dữ liệu gốc lấy từ <code>scripts.data.js</code>, chỉnh sửa lưu riêng cho Admin.</p></div>
    <button type="button" class="btn btn-gold" data-action="game-new">+ Thêm kịch bản</button></div>
  <div class="admin-filter">
    <input type="search" id="admin-game-q" placeholder="Tìm theo tên hoặc thể loại..." value="${esc(ui.gameQuery)}" aria-label="Tìm kịch bản">
    <select id="admin-game-f" aria-label="Lọc kịch bản">
      ${[['all', 'Tất cả'], ['active', 'Đang bán'], ['inactive', 'Đã tắt'], ['18', 'Chỉ 18+']].map(([v, l]) => `<option value="${v}" ${ui.gameFilter === v ? 'selected' : ''}>${l}</option>`).join('')}
    </select>
  </div>
  <div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Kịch bản</th><th>Độ khó</th><th>Đội hình</th><th>Thời lượng</th><th class="admin-num">Giá ban ngày</th><th class="admin-num">Giá buổi tối</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>
    ${rows.length ? rows.map(({ game, config, prices }) => `
      <tr style="${game.is_active ? '' : 'opacity:.55'}">
        <td><strong>${esc(game.name)}</strong> ${game.is_18plus ? '<span class="stamp-badge">18+</span>' : ''}<div class="admin-muted" style="font-size:12px">${esc(game.genre)}</div></td>
        <td>${badge(PFX, game.difficulty, DIFF_TONE[game.difficulty] || 'neutral')}</td>
        <td>${game.max_players} người <span class="admin-muted" style="font-size:12px">(${game.male} Nam / ${game.female} Nữ)</span></td>
        <td>${config ? `${config.duration_min / 60}h <span class="admin-muted" style="font-size:12px">(v${config.version})</span>` : '—'}</td>
        <td class="admin-num">${fmt.vnd(prices.DAY)}</td><td class="admin-num">${fmt.vnd(prices.NIGHT)}</td>
        <td>${badge(PFX, game.is_active ? 'Đang bán' : 'Đã tắt', game.is_active ? 'ok' : 'neutral')}</td>
        <td><div class="admin-actions">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-action="game-edit" data-id="${esc(game.id)}">Sửa</button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-action="game-history" data-id="${esc(game.id)}">Phiên bản</button>
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-action="game-toggle" data-id="${esc(game.id)}">${game.is_active ? 'Tắt' : 'Bật'}</button>
        </div></td>
      </tr>`).join('') : '<tr><td colspan="8" class="admin-empty">Không có kịch bản nào khớp.</td></tr>'}
    </tbody></table></div>`;
}

function openGameModal(gameId) {
  const row = gameId ? P.listGames().find((g) => String(g.game.id) === String(gameId)) : null;
  const g = row?.game, c = row?.config, pr = row?.prices;
  openModal(PFX, {
    title: row ? `Sửa kịch bản — ${g.name}` : 'Thêm kịch bản', wide: true, submitLabel: row ? 'Lưu thay đổi' : 'Thêm kịch bản',
    body: `
      <div class="admin-form-grid">
        <div style="grid-column:1/-1">${field(PFX, 'Tên kịch bản', `<input name="name" required value="${esc(g?.name || '')}">`)}</div>
        <div style="grid-column:1/-1">${field(PFX, 'Thể loại', `<input name="genre" value="${esc(g?.genre || '')}" placeholder="Suy luận phá án, Kinh dị tâm linh...">`)}</div>
        ${field(PFX, 'Độ khó', `<select name="difficulty" required>${P.DIFFICULTIES.map((d) => `<option ${((g?.difficulty || 'Trung bình') === d) ? 'selected' : ''}>${esc(d)}</option>`).join('')}</select>`)}
        ${field(PFX, 'Tổng số người (2–8)', `<input name="maxPlayers" type="number" min="2" max="8" required value="${g?.max_players ?? 8}">`, 'Đổi số người/thời lượng sẽ tạo phiên bản cấu hình mới.')}
        ${field(PFX, 'Số Nam', `<input name="male" type="number" min="0" max="8" required value="${g?.male ?? 4}">`)}
        ${field(PFX, 'Số Nữ', `<input name="female" type="number" min="0" max="8" required value="${g?.female ?? 4}">`, 'Nam + Nữ phải bằng tổng số người.')}
        ${field(PFX, 'Thời lượng (phút)', `<input name="durationMin" type="number" min="30" max="480" step="15" required value="${c?.duration_min ?? 240}">`)}
        <div class="admin-check" style="align-self:end"><label class="admin-check"><input type="checkbox" name="is18Plus" ${g?.is_18plus ? 'checked' : ''}> Giới hạn 18+ (bật Modal xác nhận tuổi ở trang khách)</label></div>
        ${field(PFX, 'Giá ban ngày (₫/người)', `<input name="priceDay" type="number" min="1000" step="1000" required value="${pr?.DAY ?? 220000}">`, P.PRICE_SLOTS[0].label)}
        ${field(PFX, 'Giá buổi tối (₫/người)', `<input name="priceNight" type="number" min="1000" step="1000" required value="${pr?.NIGHT ?? 240000}">`, P.PRICE_SLOTS[1].label)}
        <div style="grid-column:1/-1">${field(PFX, 'Tóm tắt vụ án', `<textarea name="summary" placeholder="Mô tả ngắn hiển thị ở trang khách...">${esc(g?.summary || '')}</textarea>`)}</div>
      </div>`,
    onSubmit: async (d, key) => {
      P.saveGame({ id: g?.id, name: d.name, genre: d.genre, difficulty: d.difficulty, maxPlayers: d.maxPlayers, male: d.male, female: d.female, is18Plus: d.is18Plus === 'on', durationMin: d.durationMin, priceDay: d.priceDay, priceNight: d.priceNight, summary: d.summary, key });
      toastDone(row ? 'Đã lưu kịch bản.' : 'Đã thêm kịch bản.');
    },
  });
}

function openHistoryModal(gameId) {
  const g = P.find.game(gameId);
  const hist = P.configHistory(gameId);
  openModal(PFX, {
    title: `Phiên bản cấu hình — ${g.name}`,
    body: `<ul class="admin-history">${hist.map((c) => `<li><span><strong>v${c.version}</strong> ${c.is_current ? badge(PFX, 'Hiện hành', 'gold') : ''}<br><span class="admin-muted" style="font-size:12px">${esc(c.notes)} · ${esc(fmt.dateTime(c.created_at))}</span></span><span>${c.duration_min / 60}h · ${c.max_players} người</span></li>`).join('')}</ul>`,
  });
}

function viewVenue() {
  const v = P.venue();
  const rooms = P.physicalRooms();
  const cards = P.studentCards();
  const cardBadge = { PENDING: ['Chờ duyệt', 'warn'], VERIFIED: ['Đã xác minh', 'ok'], REJECTED: ['Bị từ chối', 'bad'] };
  return `
  <h1 class="admin-h1">Địa điểm, phòng vật lý & thẻ HSSV</h1>
  <p class="admin-sub">Sức chứa phòng và xác minh thẻ sinh viên (ưu đãi HSSV giảm 20.000đ/người, Thứ 2–5, 09:00–19:59).</p>
  <div class="admin-grid admin-grid--2" style="margin-bottom:22px">
    <section class="admin-card"><h2 class="admin-card__title">Địa điểm</h2>
      <div class="admin-venue"><strong>${esc(v.name)}</strong><span>${esc(v.address)}</span><span>Hotline: ${esc(v.phone)}</span><span>Giờ mở cửa: ${esc(v.open_time)} – ${esc(v.close_time)} mỗi ngày</span></div></section>
    <section class="admin-card"><h2 class="admin-card__title">Tóm tắt</h2>
      <div class="admin-venue"><span>${rooms.filter((r) => r.is_active).length}/${rooms.length} phòng đang hoạt động</span><span>Tổng sức chứa: ${rooms.filter((r) => r.is_active).reduce((s, r) => s + r.capacity, 0)} người</span><span>${cards.filter((u) => u.student_card_status === 'PENDING').length} thẻ HSSV chờ duyệt</span></div></section>
  </div>

  <div class="admin-row admin-row--between" style="margin-bottom:10px"><h2 class="admin-card__title" style="margin:0">Phòng vật lý</h2>
    <button type="button" class="btn btn-gold admin-btn--sm" data-action="room-new">+ Thêm phòng</button></div>
  <div class="admin-table-wrap" style="margin-bottom:26px"><table class="admin-table" style="min-width:520px">
    <thead><tr><th>Tên phòng</th><th>Sức chứa</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>${rooms.map((r) => `
      <tr style="${r.is_active ? '' : 'opacity:.55'}"><td><strong>${esc(r.name)}</strong></td><td>${r.capacity} người</td>
        <td>${badge(PFX, r.is_active ? 'Hoạt động' : 'Đã tắt', r.is_active ? 'ok' : 'neutral')}</td>
        <td><div class="admin-actions">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-action="room-edit" data-id="${r.id}">Sửa</button>
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-action="room-toggle" data-id="${r.id}">${r.is_active ? 'Tắt' : 'Bật'}</button></div></td></tr>`).join('')}
    </tbody></table></div>

  <h2 class="admin-card__title">Xác minh thẻ HSSV</h2>
  <div class="admin-table-wrap"><table class="admin-table" style="min-width:600px">
    <thead><tr><th>Người dùng</th><th>Thẻ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>${cards.length ? cards.map((u) => {
      const [label, tone] = cardBadge[u.student_card_status];
      return `<tr><td><strong>${esc(u.full_name)}</strong><div class="admin-muted" style="font-size:12px">${esc(u.email)}</div></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-action="card-view" data-id="${u.id}">Xem ảnh thẻ</button></td>
        <td>${badge(PFX, label, tone)}${u.student_card_reject_reason && u.student_card_status === 'REJECTED' ? `<div class="admin-muted" style="font-size:12px">${esc(u.student_card_reject_reason)}</div>` : ''}${u.student_verified_at ? `<div class="admin-muted" style="font-size:12px">${esc(fmt.dateTime(u.student_verified_at))}</div>` : ''}</td>
        <td>${u.student_card_status === 'PENDING' ? `<div class="admin-actions">
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" data-action="card-approve" data-id="${u.id}">Xác minh</button>
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-action="card-reject" data-id="${u.id}">Từ chối</button></div>` : '<span class="admin-muted">—</span>'}</td></tr>`;
    }).join('') : '<tr><td colspan="4" class="admin-empty">Chưa có thẻ HSSV nào được gửi.</td></tr>'}</tbody></table></div>`;
}

function openRoomModal(roomId) {
  const r = roomId ? P.find.physicalRoom(roomId) : null;
  openModal(PFX, {
    title: r ? `Sửa ${r.name}` : 'Thêm phòng vật lý', submitLabel: r ? 'Lưu' : 'Thêm phòng',
    body: `${field(PFX, 'Tên phòng', `<input name="name" required value="${esc(r?.name || '')}">`)}${field(PFX, 'Sức chứa (người)', `<input name="capacity" type="number" min="2" max="20" required value="${r?.capacity ?? 8}">`)}`,
    onSubmit: async (d, key) => { P.savePhysicalRoom({ id: r?.id, name: d.name, capacity: d.capacity, key }); toastDone('Đã lưu phòng.'); },
  });
}

function openCardModal(userId) {
  const u = P.find.user(userId);
  openModal(PFX, {
    title: `Thẻ HSSV — ${u.full_name}`,
    body: u.student_card_url ? `<img class="admin-card-img" src="${esc(u.student_card_url)}" alt="Ảnh thẻ sinh viên của ${esc(u.full_name)}"><p class="admin-muted" style="text-align:center;margin:0;font-size:13px">Đối chiếu họ tên và hạn thẻ trước khi xác minh.</p>` : '<p class="admin-muted">Người dùng chưa tải ảnh thẻ.</p>',
  });
}

function openCardRejectModal(userId) {
  const u = P.find.user(userId);
  openModal(PFX, {
    title: `Từ chối thẻ — ${u.full_name}`, submitLabel: 'Từ chối', danger: true,
    body: field(PFX, 'Lý do (bắt buộc)', '<textarea name="reason" required placeholder="Ví dụ: Ảnh thẻ bị mờ, hãy chụp lại"></textarea>'),
    onSubmit: async (d, key) => { P.rejectStudentCard({ userId: u.id, reason: d.reason, key }); toastDone('Đã từ chối thẻ HSSV.'); },
  });
}

function toastDone(message) { toast(PFX, message); }

$tabs.addEventListener('click', (e) => {
  const t = e.target.closest('[data-tab]');
  if (!t) return;
  ui.tab = t.dataset.tab;
  history.replaceState(null, '', `#${ui.tab}`);
  render();
});

$main.addEventListener('click', (e) => {
  const go = e.target.closest('[data-go]');
  if (go) { e.preventDefault(); ui.tab = go.dataset.go; history.replaceState(null, '', `#${ui.tab}`); render(); return; }
  const df = e.target.closest('[data-dispatch-filter]');
  if (df) { ui.dispatchFilter = df.dataset.dispatchFilter; render(); return; }
  const rf = e.target.closest('[data-refund-filter]');
  if (rf) { ui.refundFilter = rf.dataset.refundFilter; render(); return; }

  const btn = e.target.closest('[data-action]');
  if (!btn || btn.disabled) return;
  const id = btn.dataset.id;
  switch (btn.dataset.action) {
    case 'assign': openAssignModal(id); break;
    case 'reopen':
      runAction(PFX, btn, (key) => P.reopenRecruitment({ roomId: id, key }), (r) => `Đã mở tuyển bổ sung, còn thiếu ${r.missing} người.`);
      break;
    case 'refund-approve': openRefundModal(id, true); break;
    case 'refund-reject': openRefundModal(id, false); break;
    case 'game-new': openGameModal(null); break;
    case 'game-edit': openGameModal(id); break;
    case 'game-history': openHistoryModal(id); break;
    case 'game-toggle': runAction(PFX, btn, (key) => P.toggleGame({ gameId: id, key }), (r) => (r.isActive ? 'Đã bật kịch bản.' : 'Đã tắt kịch bản.')); break;
    case 'room-new': openRoomModal(null); break;
    case 'room-edit': openRoomModal(id); break;
    case 'room-toggle': runAction(PFX, btn, (key) => P.togglePhysicalRoom({ roomId: id, key }), (r) => (r.isActive ? 'Đã bật phòng.' : 'Đã tắt phòng.')); break;
    case 'card-view': openCardModal(id); break;
    case 'card-approve': runAction(PFX, btn, (key) => P.verifyStudentCard({ userId: id, key }), 'Đã xác minh thẻ HSSV.'); break;
    case 'card-reject': openCardRejectModal(id); break;
    default: break;
  }
});

$main.addEventListener('input', (e) => {
  if (e.target.id !== 'admin-game-q') return;
  ui.gameQuery = e.target.value;
  const pos = e.target.selectionStart;
  render();
  const input = document.getElementById('admin-game-q');
  input.focus();
  input.setSelectionRange(pos, pos);
});
$main.addEventListener('change', (e) => {
  if (e.target.id === 'admin-game-f') { ui.gameFilter = e.target.value; render(); }
});

document.getElementById('admin-reset')?.addEventListener('click', () => {
  if (window.confirm('Xoá toàn bộ dữ liệu demo và tạo lại từ đầu?')) { P.resetDemo(); toast(PFX, 'Đã đặt lại dữ liệu demo.'); }
});

window.addEventListener('hashchange', () => { if (chrome.allowed) { ui.tab = location.hash.slice(1) || 'kpi'; render(); } });

if (chrome.allowed) {
  P.subscribe(render);
  ui.tab = location.hash.slice(1) || 'kpi';
  render();
}
