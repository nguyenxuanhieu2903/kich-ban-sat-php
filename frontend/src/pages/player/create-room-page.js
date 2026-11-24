import { playerApi } from '../../api/player-api.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { confirmDialog, renderState, toast, withBusy } from '../../components/player-ui.js';
import { errorMessage, formatClock, formatDate, formatVnd, todayIso } from '../../logic/format.js';
import { extractList, normalizeGame, normalizeProfile, normalizeQuote } from '../../logic/normalize.js';
import { quotePrice } from '../../logic/pricing.engine.js';
import { createChecklist, validateCreateRoom, NOTE_LIMIT } from '../../logic/room-rules.js';

const root = document.getElementById('player-root');
const presetGame = new URLSearchParams(window.location.search).get('game') || '';
const view = {
  games: [],
  profile: null,
  adultConfirmed: false,
  quote: null,
  quoteNote: 'Chọn kịch bản, ngày và giờ để xem bảng giá dự kiến.',
  error: '',
  loading: true,
};

function selectedGame() {
  const id = document.getElementById('booking-game')?.value;
  return view.games.find((game) => game.id === id) || null;
}

function formValues() {
  return {
    game: selectedGame(),
    playDate: document.getElementById('booking-date')?.value || '',
    playTime: document.getElementById('booking-time')?.value || '',
    notes: document.getElementById('booking-notes')?.value || '',
    adultConfirmed: view.adultConfirmed,
  };
}

function priceRows(quote) {
  if (!quote) {
    return h('p', {}, view.quoteNote);
  }
  const row = (label, value, extra) => h('tr', { class: extra || '' }, [
    h('th', { scope: 'row' }, label),
    h('td', {}, value),
  ]);
  return h('table', { class: 'booking-price-table' }, [
    h('tbody', {}, [
      row('Giá gốc', formatVnd(quote.base)),
      row('Ưu đãi', `${quote.discountLabel} · ${formatVnd(quote.discount)}`),
      row('Thành tiền', formatVnd(quote.total), 'booking-total'),
      row('Tiền cọc', formatVnd(quote.deposit)),
      row('Khoản còn lại', formatVnd(quote.remaining)),
    ]),
  ]);
}

function paintPreview() {
  const host = document.getElementById('booking-preview');
  const values = formValues();
  const checks = createChecklist(values);
  host.replaceChildren(
    h('article', { class: 'ticket-card' }, [
      h('span', { class: values.game?.is18Plus ? 'stamp-badge' : 'stamp-badge open' }, values.game?.is18Plus ? '18+' : 'Vé tạo phòng'),
      h('h2', {}, values.game?.title || 'Chưa chọn kịch bản'),
      h('p', {}, values.game?.summary || 'Thông tin trò chơi sẽ hiện sau khi bạn chọn kịch bản từ máy chủ.'),
      h('hr', { class: 'ticket-divider' }),
      h('dl', { class: 'player-definition' }, [
        h('dt', {}, 'Thể loại'),
        h('dd', {}, values.game?.genre || '—'),
        h('dt', {}, 'Thời lượng'),
        h('dd', {}, values.game?.duration || '—'),
        h('dt', {}, 'Độ khó'),
        h('dd', {}, values.game?.difficulty || '—'),
        h('dt', {}, 'Ngày chơi'),
        h('dd', {}, values.playDate ? formatDate(values.playDate) : '—'),
        h('dt', {}, 'Giờ chơi'),
        h('dd', {}, values.playTime ? formatClock(values.playTime) : '—'),
        h('dt', {}, 'Ghi chú'),
        h('dd', {}, values.notes.trim() || 'Không có'),
      ]),
      h('hr', { class: 'ticket-divider' }),
      h('h3', {}, 'Bảng giá dự kiến'),
      priceRows(view.quote),
      h('p', { class: 'player-label' }, view.quoteNote),
      h('ul', { class: 'booking-checklist' }, checks.map((item) => (
        h('li', { class: item.ok ? 'is-ok' : '' }, `${item.ok ? '✓' : '•'} ${item.label}`)
      ))),
    ])
  );
}

let quoteTimer = 0;
function scheduleQuote() {
  window.clearTimeout(quoteTimer);
  quoteTimer = window.setTimeout(refreshQuote, 350);
  paintPreview();
}

async function refreshQuote() {
  const values = formValues();
  if (!values.game || values.game.price == null || !values.playDate || !values.playTime) {
    view.quote = null;
    view.quoteNote = 'Bảng giá xuất hiện khi kịch bản có giá và bạn đã chọn ngày, giờ.';
    paintPreview();
    return;
  }
  const local = quotePrice({
    basePrice: values.game.price,
    playerCount: 1,
    playDate: values.playDate,
    playTime: values.playTime,
    hssvApproved: Boolean(view.profile?.hssvApproved),
  });
  try {
    const server = normalizeQuote(await playerApi.quoteRoom({
      game_id: values.game.id,
      play_date: values.playDate,
      play_time: values.playTime,
      player_count: 1,
    }));
    if (server) {
      view.quote = server;
      view.quoteNote = 'Bảng giá do máy chủ xác nhận cho 1 suất của bạn. Ưu đãi không cộng dồn.';
      paintPreview();
      return;
    }
  } catch {
    /* Máy chủ chưa có /rooms/quote thì dùng ước tính từ giá kịch bản. */
  }
  view.quote = local;
  view.quoteNote = local
    ? 'Ước tính trên giá kịch bản từ API, cho 1 suất. Máy chủ là nguồn chốt khi tạo phòng.'
    : 'Kịch bản chưa có giá để tính.';
  paintPreview();
}

async function onGameChange() {
  const game = selectedGame();
  if (game?.is18Plus && !view.adultConfirmed) {
    const ok = await confirmDialog({
      title: 'Nội dung 18+',
      message: `“${game.title}” có yếu tố giới hạn độ tuổi. Xác nhận bạn đã đủ 18 tuổi trước khi xem và đặt phòng.`,
      confirmText: 'Tôi đã đủ 18 tuổi',
    });
    if (!ok) {
      document.getElementById('booking-game').value = '';
      view.adultConfirmed = false;
    } else {
      view.adultConfirmed = true;
    }
  }
  if (!game?.is18Plus) view.adultConfirmed = true;
  scheduleQuote();
}

async function submit(event) {
  event.preventDefault();
  const values = formValues();
  const result = validateCreateRoom(values);
  paintErrors(result.errors);
  if (!result.ok) {
    toast('Hãy hoàn tất các điều kiện tạo phòng.', 'error');
    return;
  }
  const agreed = await confirmDialog({
    title: 'Tạo phòng mới',
    message: `Tạo phòng “${values.game.title}” lúc ${formatClock(values.playTime)} ngày ${formatDate(values.playDate)}?`,
    confirmText: 'Tạo phòng',
  });
  if (!agreed) return;
  const button = document.getElementById('booking-submit');
  try {
    await withBusy(button, async () => {
      button.textContent = 'Đang tạo phòng...';
      const data = await playerApi.createRoom({
        game_id: values.game.id,
        play_date: values.playDate,
        play_time: values.playTime,
        notes: values.notes.trim(),
        adult_confirmed: values.adultConfirmed,
      });
      const id = data?.id || data?.room_id || data?.room?.id;
      toast('Đã tạo phòng.');
      if (id) {
        window.location.href = `./room-detail.html?id=${encodeURIComponent(id)}&created=1`;
        return;
      }
      view.error = '';
      document.getElementById('booking-success').hidden = false;
      document.getElementById('booking-success').textContent = 'Máy chủ đã nhận yêu cầu tạo phòng nhưng chưa trả mã phòng.';
    });
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

function paintErrors(errors) {
  [['game', 'booking-game-error'], ['playDate', 'booking-date-error'], ['playTime', 'booking-time-error'], ['notes', 'booking-notes-error'], ['adult', 'booking-adult-error']]
    .forEach(([key, id]) => {
      const node = document.getElementById(id);
      if (node) node.textContent = errors[key] || '';
    });
}

function mountForm() {
  const gameSelect = h('select', { class: 'player-select', id: 'booking-game', required: true }, [
    h('option', { value: '' }, 'Chọn kịch bản'),
    ...view.games.map((game) => h('option', { value: game.id, selected: game.id === presetGame }, game.title)),
  ]);
  gameSelect.addEventListener('change', () => {
    onGameChange().catch((err) => toast(errorMessage(err), 'error'));
  });
  const date = h('input', { class: 'player-input', id: 'booking-date', type: 'date', min: todayIso(), required: true });
  const time = h('input', { class: 'player-input', id: 'booking-time', type: 'time', min: '09:00', max: '23:00', step: '1800', required: true });
  const notes = h('textarea', { class: 'player-textarea', id: 'booking-notes', maxlength: String(NOTE_LIMIT), placeholder: 'Lời nhắn cho người cùng phòng' });
  [date, time, notes].forEach((field) => field.addEventListener('input', scheduleQuote));

  const form = h('form', { class: 'player-stack', id: 'booking-form' }, [
    h('div', { id: 'booking-success', class: 'player-banner', hidden: true }),
    h('label', { class: 'player-field' }, [
      h('span', {}, 'Kịch bản'),
      gameSelect,
      h('small', { class: 'player-error', id: 'booking-game-error' }),
    ]),
    h('div', { class: 'player-grid-2' }, [
      h('label', { class: 'player-field' }, [
        h('span', {}, 'Ngày chơi'),
        date,
        h('small', { class: 'player-error', id: 'booking-date-error' }),
      ]),
      h('label', { class: 'player-field' }, [
        h('span', {}, 'Giờ chơi'),
        time,
        h('small', { class: 'player-error', id: 'booking-time-error' }),
      ]),
    ]),
    h('label', { class: 'player-field' }, [
      h('span', {}, 'Ghi chú'),
      notes,
      h('small', { class: 'player-error', id: 'booking-notes-error' }),
    ]),
    h('small', { class: 'player-error', id: 'booking-adult-error' }),
    h('button', { class: 'btn btn-primary', id: 'booking-submit', type: 'submit' }, 'Tạo phòng'),
  ]);
  form.addEventListener('submit', submit);
  return form;
}

function paintPage(banner) {
  root.replaceChildren(h('div', { class: 'container' }, [
    banner || null,
    h('header', { class: 'player-hero' }, [
      h('div', {}, [
        h('p', { class: 'player-kicker' }, 'Player Portal'),
        h('h1', { class: 'player-title' }, 'Tạo phòng'),
        h('p', { class: 'player-lead' }, 'Chọn kịch bản, ngày giờ và xem bảng giá trước khi mở phòng. Cọc giữ chỗ là 50.000đ trong 4 giờ.'),
      ]),
    ]),
    h('div', { class: 'player-grid-2' }, [
      h('section', { class: 'player-panel' }, [mountForm()]),
      h('section', { id: 'booking-preview' }),
    ]),
  ]));
  paintPreview();
}

async function boot() {
  mountChrome('create');
  renderState(root, { kind: 'loading', title: 'Đang mở phiếu tạo phòng', message: 'Đang tải danh sách kịch bản.' });
  try {
    const [gamesPayload, profileResult] = await Promise.all([
      playerApi.listGames(),
      playerApi.getMe().then((data) => normalizeProfile(data)).catch(() => null),
    ]);
    view.profile = profileResult;
    view.games = extractList(gamesPayload, ['items', 'games', 'data']).items.map(normalizeGame).filter((game) => game?.id);
    view.loading = false;
    if (!view.games.length) {
      paintPage(h('p', { class: 'player-banner player-banner-error' }, 'Máy chủ chưa trả danh sách kịch bản. Form bên dưới sẵn sàng khi API có dữ liệu.'));
      const empty = document.getElementById('booking-game');
      if (empty) empty.append(h('option', { value: '', disabled: true }, 'Chưa có kịch bản từ máy chủ'));
      return;
    }
    paintPage();
    if (presetGame && view.games.some((game) => game.id === presetGame)) {
      await onGameChange();
    }
  } catch (err) {
    view.loading = false;
    view.games = [];
    paintPage(h('p', { class: 'player-banner player-banner-error' }, errorMessage(err)));
  }
}

boot();
