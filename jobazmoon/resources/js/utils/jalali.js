/** Jalali (Persian) calendar helpers — no external deps */

const breaks = [
  -61, 9, 38, 199, 426, 686, 756, 818, 1116, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
];

function div(a, b) {
  return Math.trunc(a / b);
}

function mod(a, b) {
  return a - Math.trunc(a / b) * b;
}

function jalCal(jy) {
  const bl = breaks.length;
  const gy = jy + 621;
  let leapJ = -14;
  let jp = breaks[0];
  let jump = 0;
  let jm;
  let n;
  let i;

  if (jy < jp || jy >= breaks[bl - 1]) {
    throw new Error('Invalid Jalali year ' + jy);
  }

  for (i = 1; i < bl; i += 1) {
    jm = breaks[i];
    jump = jm - jp;
    if (jy < jm) break;
    leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
    jp = jm;
  }
  n = jy - jp;
  leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
  if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;

  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  const march = 20 + leapJ - leapG;

  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
  let leap = mod(mod(n + 1, 33) - 1, 4);
  if (leap === -1) leap = 4;

  return { leap, gy, march };
}

function g2d(gy, gm, gd) {
  let d =
    div((gy + div(gm - 8, 6) + 100100) * 1461, 4) +
    div(153 * mod(gm + 9, 12) + 2, 5) +
    gd -
    34840408;
  d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
  return d;
}

function d2g(jdn) {
  let j = 4 * jdn + 139361631;
  j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
  const i = div(mod(j, 1461), 4) * 5 + 308;
  const gd = div(mod(i, 153), 5) + 1;
  const gm = mod(div(i, 153), 12) + 1;
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
  return { gy, gm, gd };
}

export function toJalali(gy, gm, gd) {
  const gdn = g2d(gy, gm, gd);
  const jy = gy - 621;
  const r = jalCal(jy);
  const jdn1f = g2d(gy, 3, r.march);
  let jd;
  let jy2 = jy;
  let jm;
  let gd2;

  if (gdn >= jdn1f) {
    jd = gdn - jdn1f + 1;
  } else {
    jy2 = jy - 1;
    const r2 = jalCal(jy2);
    jd = gdn - g2d(gy - 1, 3, r2.march) + 1;
  }

  if (jd <= 186) {
    jm = 1 + div(jd - 1, 31);
    gd2 = mod(jd - 1, 31) + 1;
  } else {
    jm = 7 + div(jd - 187, 30);
    gd2 = mod(jd - 187, 30) + 1;
  }

  return { jy: jy2, jm, jd: gd2 };
}

export function toGregorian(jy, jm, jd) {
  const r = jalCal(jy);
  const gdn = g2d(r.gy, 3, r.march) + (jm <= 6 ? (jm - 1) * 31 : (jm - 7) * 30 + 186) + jd - 1;
  return d2g(gdn);
}

export function jalaliMonthLength(jy, jm) {
  if (jm <= 6) return 31;
  if (jm <= 11) return 30;
  return jalCal(jy).leap === 0 ? 29 : 30;
}

export const JALALI_MONTHS = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند',
];

export const JALALI_WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

export function parseIsoDate(value) {
  if (!value) return null;
  const s = String(value).slice(0, 10);
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
  if (!m) return null;
  return { gy: Number(m[1]), gm: Number(m[2]), gd: Number(m[3]) };
}

export function toIsoDate(gy, gm, gd) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${gy}-${pad(gm)}-${pad(gd)}`;
}

export function gregorianIsoToJalaliParts(iso) {
  const g = parseIsoDate(iso);
  if (!g) return null;
  return toJalali(g.gy, g.gm, g.gd);
}

export function jalaliPartsToIso({ jy, jm, jd }) {
  if (!jy || !jm || !jd) return '';
  const g = toGregorian(Number(jy), Number(jm), Number(jd));
  return toIsoDate(g.gy, g.gm, g.gd);
}

export function formatJalaliDate(value, { withWeekday = false } = {}) {
  if (!value) return '—';
  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  const { jy, jm, jd } = toJalali(d.getFullYear(), d.getMonth() + 1, d.getDate());
  const fa = (n) => String(n).replace(/\d/g, (x) => '۰۱۲۳۴۵۶۷۸۹'[x]);
  const base = `${fa(jy)}/${fa(String(jm).padStart(2, '0'))}/${fa(String(jd).padStart(2, '0'))}`;
  if (!withWeekday) return base;
  return `${JALALI_WEEKDAYS[d.getDay()]} ${base}`;
}

export function formatJalaliDateTime(value) {
  if (!value) return '—';
  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  const date = formatJalaliDate(d, { withWeekday: true });
  const fa = (n) => String(n).replace(/\d/g, (x) => '۰۱۲۳۴۵۶۷۸۹'[x]);
  const time = `${fa(String(d.getHours()).padStart(2, '0'))}:${fa(String(d.getMinutes()).padStart(2, '0'))}`;
  return `${date} — ${time}`;
}

export function nowJalaliClock() {
  return formatJalaliDateTime(new Date());
}
