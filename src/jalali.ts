export function toEnglishDigits(input: string): string {
  return String(input || '')
    .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
}

export function toPersianDigits(input: string | number): string {
  return String(input ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

export function maskMobile(mobile: string): string {
  const clean = String(mobile || '').trim();
  if (clean.length <= 6) return clean;
  return `${clean.slice(0, 4)}${'•'.repeat(Math.max(0, clean.length - 7))}${clean.slice(-3)}`;
}

// Accurate Gregorian to Jalali (Solar Hijri) algorithm
export function gregorianToJalali(gyInput: number, gm: number, gd: number): [number, number, number] {
  const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  const gy2 = gm > 2 ? gyInput + 1 : gyInput;
  let days =
    355666 +
    365 * gyInput +
    Math.floor((gy2 + 3) / 4) -
    Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) +
    gd +
    gdm[gm - 1];

  let jy = -1595 + 33 * Math.floor(days / 12053);
  days %= 12053;
  jy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days > 365) {
    jy += Math.floor((days - 1) / 365);
    days = (days - 1) % 365;
  }

  let jm: number;
  let jd: number;
  if (days < 186) {
    jm = 1 + Math.floor(days / 31);
    jd = 1 + (days % 31);
  } else {
    jm = 7 + Math.floor((days - 186) / 30);
    jd = 1 + ((days - 186) % 30);
  }
  return [jy, jm, jd];
}

export function jalaliToGregorian(jyInput: number, jm: number, jd: number): [number, number, number] {
  const jy = jyInput + 1595;
  let days =
    -355668 +
    365 * jy +
    Math.floor(jy / 33) * 8 +
    Math.floor(((jy % 33) + 3) / 4) +
    jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);

  let gy = 400 * Math.floor(days / 146097);
  days %= 146097;
  if (days > 36524) {
    gy += 100 * Math.floor(--days / 36524);
    days %= 36524;
    if (days >= 365) days++;
  }
  gy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days > 365) {
    gy += Math.floor((days - 1) / 365);
    days = (days - 1) % 365;
  }

  let gd = days + 1;
  const salA = [0, 31, (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  let gm = 0;
  for (gm = 0; gm < 13 && gd > salA[gm]; gm++) {
    gd -= salA[gm];
  }
  return [gy, gm, gd];
}

export function formatJalali(isoDate: string | undefined | null): string {
  if (!isoDate) return '—';
  const str = String(isoDate).trim();
  const match = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return str;
  const [jy, jm, jd] = gregorianToJalali(Number(match[1]), Number(match[2]), Number(match[3]));
  const formatted = `${String(jy).padStart(4, '0')}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`;
  return toPersianDigits(formatted);
}

export function formatJalaliAscii(isoDate: string | undefined | null): string {
  if (!isoDate) return '';
  const str = String(isoDate).trim();
  const match = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return str;
  const [jy, jm, jd] = gregorianToJalali(Number(match[1]), Number(match[2]), Number(match[3]));
  return `${String(jy).padStart(4, '0')}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`;
}

export function formatJalaliDateTime(isoDateTime: string | undefined | null): string {
  if (!isoDateTime) return '—';
  const str = String(isoDateTime).trim();
  const datePart = formatJalali(str);
  const timeMatch = str.match(/[T\s](\d{2}:\d{2})/);
  if (!timeMatch) return datePart;
  return `${datePart} — ${toPersianDigits(timeMatch[1])}`;
}

export function parseJalaliInputToIso(jalaliInput: string): string | null {
  const clean = toEnglishDigits(jalaliInput).trim();
  const m = clean.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
  if (!m) {
    if (/^\d{4}-\d{2}-\d{2}$/.test(clean)) return clean;
    return null;
  }
  const jy = Number(m[1]);
  const jm = Number(m[2]);
  const jd = Number(m[3]);
  if (jy < 1300 || jy > 1500 || jm < 1 || jm > 12 || jd < 1 || jd > 31) {
    return null;
  }
  const [gy, gm, gd] = jalaliToGregorian(jy, jm, jd);
  return `${String(gy).padStart(4, '0')}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
}

export function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

export function todayJalali(): string {
  return formatJalali(todayIso());
}

export function todayJalaliAscii(): string {
  return formatJalaliAscii(todayIso());
}

export const JALALI_MONTH_NAMES = [
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
