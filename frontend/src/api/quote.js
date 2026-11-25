import { calculateQuote } from './player-api.js';

const STUDENT_DISCOUNT = 20000;
const DEPOSIT_RATE = 0.3;

function parseStart(iso) {
  const text = String(iso || '');
  const d = new Date(text.includes('T') ? text : text.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? null : d;
}

export function buildLocalQuote({ game, sessionStart, playerCount, studentVerified = false }) {
  const start = parseStart(sessionStart);
  const count = Number(playerCount) || game?.min_players || 1;
  if (!game || !start) {
    throw new Error('Thiếu kịch bản hoặc thời gian để lập bảng giá.');
  }

  const minutes = start.getHours() * 60 + start.getMinutes();
  const isNight = minutes >= 20 * 60;
  const base = Number(isNight ? game.price_night : game.price_day) || 0;
  const original = base * count;
  const day = start.getDay();
  const advanceHours = (start.getTime() - Date.now()) / 3600000;
  const promoWindow = minutes >= 9 * 60 && minutes <= 19 * 60;
  const tuesday = day === 2 && advanceHours >= 24 && promoWindow;
  const student = studentVerified && day >= 1 && day <= 4 && promoWindow;

  let discount = 0;
  let discountLabel = 'Không có ưu đãi';
  if (tuesday && student) {
    discountLabel = 'Hai ưu đãi cùng điều kiện — hệ thống không cộng dồn, chưa áp dụng giảm';
  } else if (tuesday) {
    discount = Math.round(original * 0.3);
    discountLabel = 'Ưu đãi Thứ Ba (-30%)';
  } else if (student) {
    discount = STUDENT_DISCOUNT * count;
    discountLabel = 'Ưu đãi HSSV (-20.000đ/người)';
  }

  const total = Math.max(0, original - discount);
  const deposit = Math.round(total * DEPOSIT_RATE);
  const remaining = Math.max(0, total - deposit);

  return {
    time_window: isNight ? 'Khung tối (từ 20:00)' : 'Khung ngày (09:00–19:59)',
    player_count: count,
    base_price_per_player: base,
    original_amount: original,
    discount_amount: discount,
    discount_label: discountLabel,
    total_amount: total,
    deposit_amount: deposit,
    remaining_amount: remaining,
    source: 'local-from-game',
  };
}

function normalizeQuote(raw, fallback) {
  const data = raw?.pricing_snapshot || raw || {};
  const original = Number(data.raw_total_vnd ?? data.subtotal ?? data.original_amount ?? fallback.original_amount);
  const discount = Number(data.total_discount_vnd ?? data.discount_amount ?? fallback.discount_amount);
  const total = Number(data.final_total_vnd ?? data.total ?? data.total_amount ?? Math.max(0, original - discount));
  const deposit = Number(
    data.total_deposit ?? data.total_deposit_vnd ?? data.deposit_amount ?? Math.round(total * DEPOSIT_RATE)
  );
  return {
    time_window: data.time_slot || data.time_window || fallback.time_window,
    player_count: Number(data.player_count ?? fallback.player_count),
    base_price_per_player: Number(data.base_price_per_player ?? data.base_price_per_person ?? fallback.base_price_per_player),
    original_amount: original,
    discount_amount: discount,
    discount_label: data.applied_promotion?.name || data.discount_code || fallback.discount_label,
    total_amount: total,
    deposit_amount: deposit,
    remaining_amount: Number(data.remaining_amount ?? data.remaining_vnd ?? Math.max(0, total - deposit)),
    source: 'api',
  };
}

export async function getQuotePreview(input) {
  const local = buildLocalQuote(input);
  try {
    const remote = await calculateQuote({
      game_id: input.game.backend_id || input.game.id,
      session_start_time: input.sessionStart,
      player_count: input.playerCount || input.game.min_players,
      is_student_verified: Boolean(input.studentVerified),
    });
    return normalizeQuote(remote, local);
  } catch {
    return local;
  }
}
