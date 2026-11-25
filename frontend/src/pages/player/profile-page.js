import { playerApi, logout } from '../../api/player-api.js';
import { mountChrome } from '../../components/player-chrome.js';
import { h } from '../../components/dom.js';
import { renderState, toast, withBusy } from '../../components/player-ui.js';
import { errorMessage, formatClock, formatDate, safeUrl, statusLabel, statusStampClass } from '../../logic/format.js';
import { extractList, normalizeBooking, normalizeProfile } from '../../logic/normalize.js';
import { paymentLabel, startPaymentPoll } from '../../logic/payment.js';

const TABS = [
  { id: 'upcoming', label: 'Sắp chơi', empty: 'Bạn chưa có phòng sắp chơi.' },
  { id: 'awaiting_deposit', label: 'Chờ cọc', empty: 'Không có khoản cọc đang chờ.' },
  { id: 'completed', label: 'Đã hoàn thành', empty: 'Chưa có phiên đã hoàn thành.' },
];

const root = document.getElementById('player-root');
const params = new URLSearchParams(window.location.search);
const state = {
  profile: null,
  profileError: '',
  tab: TABS.some((tab) => tab.id === params.get('tab')) ? params.get('tab') : 'upcoming',
  bookings: [],
  bookingError: '',
  bookingLoading: true,
  previewUrl: '',
  guest: false,
};

function setTab(id) {
  state.tab = id;
  const next = new URLSearchParams(window.location.search);
  next.set('tab', id);
  window.history.replaceState(null, '', `?${next.toString()}`);
  paintHistory();
  if (!state.guest) loadBookings();
}

function bookingCard(item) {
  const ticketHref = item.roomId
    ? `./booking-status.html?room=${encodeURIComponent(item.roomId)}`
    : `./booking-status.html?ticket=${encodeURIComponent(item.ticketCode || item.roomCode)}`;
  const roomHref = item.roomId
    ? `./room-detail.html?id=${encodeURIComponent(item.roomId)}`
    : ticketHref;
  const pay = item.paymentStatus ? paymentLabel(item.paymentStatus) : '';
  return h('article', { class: 'profile-room' }, [
    h('div', {}, [
      h('p', { class: 'profile-room-line' }, [
        h('span', { class: 'profile-room-code' }, item.roomCode || 'Chưa có mã'),
      ]),
      h('h3', { class: 'profile-room-title' }, item.title),
      h('p', { class: 'profile-room-meta' }, [
        `${formatDate(item.playDate)} · ${formatClock(item.playTime)} · `,
        h('span', { class: statusStampClass(item.status) }, statusLabel(item.status)),
      ]),
      pay ? h('p', { class: 'profile-room-meta' }, `Thanh toán: ${pay}`) : null,
    ]),
    h('div', { class: 'player-actions' }, [
      h('a', { class: 'profile-room-link', href: ticketHref }, 'Vé / thanh toán'),
      h('a', { class: 'profile-room-link', href: roomHref }, 'Chi tiết phòng'),
    ]),
  ]);
}

function paintHistory() {
  const host = document.getElementById('profile-history');
  if (!host) return;
  const tab = TABS.find((item) => item.id === state.tab) || TABS[0];
  const tabs = h('div', { class: 'profile-tabs', role: 'tablist' }, TABS.map((item) => (
    h('button', {
      class: `profile-tab${item.id === state.tab ? ' is-active' : ''}`,
      type: 'button',
      role: 'tab',
      'aria-selected': item.id === state.tab ? 'true' : 'false',
      onClick: () => setTab(item.id),
    }, item.label)
  )));
  let body;
  if (state.guest) {
    body = h('div', { class: 'profile-empty' }, [
      h('p', { class: 'profile-empty-title' }, 'Cần đăng nhập'),
      h('p', {}, 'Đăng nhập ở cột bên trái để xem lịch sử đặt phòng từ máy chủ.'),
    ]);
  } else if (state.bookingLoading) {
    body = h('div', { id: 'profile-booking-state' });
  } else if (state.bookingError) {
    body = h('div', { id: 'profile-booking-state' });
  } else if (!state.bookings.length) {
    body = h('div', { class: 'profile-empty' }, [
      h('p', { class: 'profile-empty-title' }, 'Bạn chưa có lượt đặt phòng nào.'),
      h('p', {}, tab.empty),
      h('a', { class: 'btn btn-gold', href: './lobby.html' }, 'KHÁM PHÁ KỊCH BẢN'),
    ]);
  } else {
    body = h('div', {}, state.bookings.map(bookingCard));
  }
  host.replaceChildren(
    h('h2', { class: 'profile-history-title' }, 'Lịch sử đặt phòng của bạn'),
    tabs,
    body
  );
  const slot = document.getElementById('profile-booking-state');
  if (!slot) return;
  if (state.bookingLoading) {
    renderState(slot, { kind: 'loading', title: 'Đang tải lịch sử', message: 'Đang hỏi các phòng của bạn.' });
  } else if (state.bookingError) {
    renderState(slot, {
      kind: 'error',
      title: 'Không tải được lịch sử',
      message: state.bookingError,
      actionLabel: 'Thử lại',
      onAction: () => loadBookings(),
    });
  }
}

function hssvLabel(status) {
  const key = String(status || 'missing').toLowerCase();
  if (['approved', 'verified'].includes(key)) return 'Đã xác minh';
  if (['submitted', 'sent'].includes(key)) return 'Đã gửi';
  if (key === 'pending') return 'Đang chờ Admin duyệt';
  if (['rejected', 'denied'].includes(key)) return 'Bị từ chối';
  if (['resubmit', 'need_resubmit'].includes(key)) return 'Cần gửi lại';
  return 'Chưa gửi';
}

function hssvStatusClass(status) {
  const key = String(status || 'missing').toLowerCase();
  if (['approved', 'verified'].includes(key)) return 'profile-status profile-status--verified';
  if (key === 'pending') return 'profile-status profile-status--pending';
  if (key === 'rejected') return 'profile-status profile-status--rejected';
  return 'profile-status profile-status--none';
}

function passwordField(id, label, autocomplete) {
  const input = h('input', {
    class: 'profile-input',
    id,
    type: 'password',
    autocomplete,
  });
  const eye = h('button', { class: 'profile-eye', type: 'button' }, 'Hiện');
  eye.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    eye.textContent = show ? 'Ẩn' : 'Hiện';
  });
  return h('label', { class: 'profile-field' }, [
    h('span', { class: 'profile-label' }, label),
    h('div', { class: 'profile-password' }, [input, eye]),
  ]);
}

function pageWrap(left, extra) {
  return h('div', { class: 'container profile-page' }, [
    extra || null,
    h('p', { class: 'profile-kicker' }, 'Tài khoản thành viên'),
    h('h1', { class: 'profile-title' }, 'Hồ Sơ & Lịch Sử Đặt Phòng'),
    h('div', { id: 'profile-banner' }),
    h('div', { class: 'profile-layout' }, [
      h('section', { class: 'profile-panel profile-panel--paper', id: 'profile-left' }, left),
      h('section', { class: 'profile-panel profile-panel--dark', id: 'profile-history' }),
    ]),
  ]);
}

function paintGuest(err) {
  state.guest = true;
  const email = h('input', {
    class: 'profile-input',
    id: 'profile-login-email',
    type: 'email',
    autocomplete: 'username',
    required: true,
  });
  const form = h('form', { class: 'profile-form', id: 'profile-login' }, [
    h('p', { class: 'profile-badge' }, 'Thông tin cá nhân'),
    h('h2', { class: 'profile-panel-title' }, 'Đăng nhập để mở hồ sơ'),
    h('label', { class: 'profile-field' }, [
      h('span', { class: 'profile-label' }, 'Email'),
      email,
    ]),
    passwordField('profile-login-password', 'Mật khẩu', 'current-password'),
    h('p', { class: 'profile-field-error', id: 'profile-login-error' }, err && err.status !== 401 ? errorMessage(err) : ''),
    h('button', { class: 'profile-btn profile-btn--red', type: 'submit', id: 'profile-login-submit' }, 'Đăng nhập'),
  ]);
  form.addEventListener('submit', submitLogin);
  root.replaceChildren(pageWrap([form]));
  paintHistory();
}

function paintProfile() {
  state.guest = false;
  const profile = state.profile;
  const previewSrc = state.previewUrl || safeUrl(profile?.hssvPreview || '');
  const nameInput = h('input', {
    class: 'profile-input',
    id: 'profile-name',
    name: 'full_name',
    autocomplete: 'name',
    required: true,
    value: profile?.fullName || '',
  });
  const phoneInput = h('input', {
    class: 'profile-input',
    id: 'profile-phone',
    name: 'phone',
    type: 'tel',
    autocomplete: 'tel',
    required: true,
    value: profile?.phone || '',
  });
  const form = h('form', { class: 'profile-form', id: 'profile-form' }, [
    h('p', { class: 'profile-badge' }, 'Thông tin cá nhân'),
    h('h2', { class: 'profile-panel-title' }, 'Cài đặt & Xác minh HSSV'),
    h('label', { class: 'profile-field' }, [
      h('span', { class: 'profile-label' }, 'Họ tên'),
      nameInput,
    ]),
    h('label', { class: 'profile-field' }, [
      h('span', { class: 'profile-label' }, 'Số điện thoại'),
      phoneInput,
    ]),
    passwordField('profile-password-optional', 'Mật khẩu', 'new-password'),
    h('p', { class: 'profile-notice-text' }, 'Để trống mật khẩu nếu không đổi. Nếu đổi, nhập mật khẩu hiện tại bên dưới rồi bấm Lưu thông tin.'),
    passwordField('profile-current-password', 'Mật khẩu hiện tại (khi đổi)', 'current-password'),
    h('p', { class: 'profile-field-error', id: 'profile-form-error' }),
    h('button', { class: 'profile-btn profile-btn--red', type: 'submit', id: 'profile-save' }, 'Lưu thông tin'),
  ]);

  const file = h('input', {
    class: 'profile-file-input',
    id: 'profile-card',
    type: 'file',
    accept: 'image/jpeg,image/png,image/webp',
  });
  const fileName = h('span', { class: 'profile-file-name', id: 'profile-file-name' }, 'Chưa chọn tệp');
  const preview = h('div', { class: 'profile-preview' }, [
    h('img', { id: 'profile-preview', alt: 'Xem trước ảnh thẻ học sinh sinh viên' }),
  ]);
  const previewImg = preview.querySelector('img');
  if (!previewSrc) preview.hidden = true;
  else previewImg.src = previewSrc;
  file.addEventListener('change', () => {
    const selected = file.files?.[0];
    if (!selected) {
      fileName.textContent = 'Chưa chọn tệp';
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > 5 * 1024 * 1024) {
      file.value = '';
      fileName.textContent = 'Chưa chọn tệp';
      toast('Chỉ nhận ảnh JPG, PNG hoặc WEBP, tối đa 5MB.', 'error');
      return;
    }
    fileName.textContent = selected.name;
    const reader = new FileReader();
    reader.onload = () => {
      state.previewUrl = String(reader.result || '');
      previewImg.src = state.previewUrl;
      preview.hidden = false;
    };
    reader.readAsDataURL(selected);
  });
  const student = h('form', { class: 'profile-form', id: 'profile-student' }, [
    h('h3', { class: 'profile-hssv-title' }, 'Thẻ Học Sinh — Sinh Viên (Giảm 20.000đ)'),
    h('span', { class: hssvStatusClass(profile?.hssvStatus) }, hssvLabel(profile?.hssvStatus)),
    profile?.hssvNote ? h('p', { class: 'profile-notice-text' }, profile.hssvNote) : null,
    h('label', { class: 'profile-file' }, [
      file,
      h('span', { class: 'profile-file-btn' }, 'Choose File'),
      fileName,
    ]),
    preview,
    h('button', { class: 'profile-btn profile-btn--gold', type: 'submit', id: 'profile-upload' }, 'Gửi ảnh xét duyệt'),
  ]);

  const signOut = h('button', { class: 'profile-btn profile-btn--gold', type: 'button', id: 'profile-logout' }, 'Đăng xuất');
  signOut.addEventListener('click', async () => {
    try {
      await logout();
    } catch {
      /* phiên có thể đã hết */
    }
    toast('Đã đăng xuất.');
    mountChrome('profile');
    paintGuest();
  });

  form.addEventListener('submit', saveProfile);
  student.addEventListener('submit', uploadCard);

  root.replaceChildren(pageWrap([
    form,
    h('hr', { class: 'profile-divider' }),
    student,
    h('hr', { class: 'profile-divider' }),
    signOut,
  ]));
  paintHistory();
}

async function submitLogin(event) {
  event.preventDefault();
  const email = document.getElementById('profile-login-email').value.trim();
  const password = document.getElementById('profile-login-password').value;
  const error = document.getElementById('profile-login-error');
  if (!email || !password) {
    error.textContent = 'Nhập email và mật khẩu.';
    return;
  }
  error.textContent = '';
  const button = document.getElementById('profile-login-submit');
  try {
    await withBusy(button, async () => {
      button.textContent = 'Đang đăng nhập...';
      await playerApi.login({ email, password });
      state.profile = normalizeProfile(await playerApi.getMe());
    });
    toast('Đăng nhập thành công.');
    mountChrome('profile');
    paintProfile();
    loadBookings();
  } catch (err) {
    error.textContent = errorMessage(err);
    toast(errorMessage(err), 'error');
  }
}

async function saveProfile(event) {
  event.preventDefault();
  const name = document.getElementById('profile-name').value.trim();
  const phone = document.getElementById('profile-phone').value.trim();
  const nextPassword = document.getElementById('profile-password-optional').value;
  const current = document.getElementById('profile-current-password').value;
  const error = document.getElementById('profile-form-error');
  if (name.length < 2) {
    error.textContent = 'Họ tên cần ít nhất 2 ký tự.';
    return;
  }
  if (!/^(0|\+84)\d{8,10}$/.test(phone)) {
    error.textContent = 'Số điện thoại chưa đúng định dạng Việt Nam.';
    return;
  }
  if (nextPassword && nextPassword.length < 8) {
    error.textContent = 'Mật khẩu mới cần ít nhất 8 ký tự.';
    return;
  }
  if (nextPassword && !current) {
    error.textContent = 'Nhập mật khẩu hiện tại để đổi mật khẩu.';
    return;
  }
  error.textContent = '';
  const button = document.getElementById('profile-save');
  try {
    await withBusy(button, async () => {
      button.textContent = 'Đang lưu...';
      const data = await playerApi.updateMe({ full_name: name, phone });
      state.profile = normalizeProfile(data || { ...state.profile, full_name: name, phone });
      if (nextPassword) {
        await playerApi.changePassword({
          current_password: current,
          new_password: nextPassword,
          new_password_confirmation: nextPassword,
        });
      }
    });
    toast('Đã lưu thông tin.');
    showBanner(nextPassword ? 'Đã lưu hồ sơ và đổi mật khẩu.' : 'Đã lưu họ tên và số điện thoại.');
    document.getElementById('profile-password-optional').value = '';
    document.getElementById('profile-current-password').value = '';
    mountChrome('profile');
  } catch (err) {
    error.textContent = errorMessage(err);
    toast(errorMessage(err), 'error');
  }
}

async function uploadCard(event) {
  event.preventDefault();
  const input = document.getElementById('profile-card');
  const file = input.files?.[0];
  if (!file) {
    toast('Hãy chọn ảnh thẻ trước khi gửi.', 'error');
    return;
  }
  const button = document.getElementById('profile-upload');
  try {
    await withBusy(button, async () => {
      button.textContent = 'Đang gửi...';
      const data = await playerApi.uploadStudentCard(file);
      const next = normalizeProfile(data);
      if (next.fullName || next.hssvStatus !== 'missing') state.profile = { ...state.profile, ...next };
      else state.profile = { ...state.profile, hssvStatus: 'pending' };
    });
    toast('Đã gửi ảnh thẻ. Trạng thái chuyển sang chờ xét duyệt khi máy chủ ghi nhận.');
    paintProfile();
    showBanner('Đã gửi ảnh thẻ HSSV.');
  } catch (err) {
    toast(errorMessage(err), 'error');
  }
}

function showBanner(message, tone = 'ok') {
  const host = document.getElementById('profile-banner');
  if (!host) return;
  host.replaceChildren(h('p', {
    class: `profile-toast ${tone === 'err' ? 'profile-toast--err' : 'profile-toast--ok'}`,
  }, message));
}

async function loadBookings(silent = false) {
  if (!silent) {
    state.bookingLoading = true;
    state.bookingError = '';
    paintHistory();
  }
  try {
    const payload = await playerApi.listMyBookings(state.tab);
    const { items } = extractList(payload, ['items', 'bookings', 'rooms', 'data']);
    state.bookings = items.map(normalizeBooking).filter((item) => item.roomCode || item.title);
    state.bookingError = '';
  } catch (err) {
    if (!silent) {
      state.bookings = [];
      state.bookingError = errorMessage(err);
    }
  } finally {
    state.bookingLoading = false;
    paintHistory();
  }
}

let stopBookingPoll = null;

async function boot() {
  mountChrome('profile');
  renderState(root, { kind: 'loading', title: 'Đang mở hồ sơ', message: 'Đang lấy thông tin cá nhân.' });
  try {
    state.profile = normalizeProfile(await playerApi.getMe());
  } catch (err) {
    state.profileError = errorMessage(err);
    paintGuest(err);
    return;
  }
  paintProfile();
  loadBookings();
  stopBookingPoll = startPaymentPoll(async () => {
    const payload = await playerApi.listMyBookings(state.tab);
    const { items } = extractList(payload, ['items', 'bookings', 'rooms', 'data']);
    return items.map(normalizeBooking).filter((item) => item.roomCode || item.title);
  }, (items) => {
    state.bookings = items;
    state.bookingLoading = false;
    paintHistory();
  }, 12000);
}

window.addEventListener('pagehide', () => {
  if (stopBookingPoll) stopBookingPoll();
});

window.addEventListener('kbs:payment-changed', () => {
  if (!state.guest) loadBookings(true);
});

boot();
