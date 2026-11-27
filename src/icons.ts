const ICON_PATHS: Record<string, string> = {
  overview: '<circle cx="12" cy="12" r="8.6"/><path d="M15.6 8.4l-2.1 5.1-5.1 2.1 2.1-5.1z"/>',
  patients:
    '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5v-1.2A4.3 4.3 0 0 1 7.8 14h2.4a4.3 4.3 0 0 1 4.3 4.3v1.2"/><path d="M16.5 5.4a3 3 0 0 1 0 5.6"/><path d="M17.4 14.2a4 4 0 0 1 3.1 3.9v1.4"/>',
  case_files:
    '<path d="M3.4 7.6A2.6 2.6 0 0 1 6 5h3.1l2.1 2.6h6.8A2.6 2.6 0 0 1 20.6 10v6.6a2.6 2.6 0 0 1-2.6 2.6H6a2.6 2.6 0 0 1-2.6-2.6z"/>',
  assessments:
    '<path d="M9 4.6h6"/><rect x="5" y="6" width="14" height="14.4" rx="2.4"/><path d="M9.2 13.4l1.9 1.9 3.7-3.9"/>',
  appointments:
    '<rect x="3.6" y="5.4" width="16.8" height="14.6" rx="2.6"/><path d="M8 3.4v4M16 3.4v4M3.6 10.2h16.8"/>',
  reports: '<path d="M4 19V10M9.4 19V5M14.7 19v-6.4M20 19v-3.4"/>',
  communications:
    '<rect x="3.4" y="6" width="17.2" height="12" rx="2.4"/><path d="M4 7.4l8 5.6 8-5.6"/>',
  notifications:
    '<path d="M12 4.2a5 5 0 0 1 5 5v3.1l1.5 2.7H5.5L7 12.3V9.2a5 5 0 0 1 5-5z"/><path d="M10 18.6a2 2 0 0 0 4 0"/>',
  system_health: '<path d="M3.2 12h3.9l1.9-4.6 3.2 9.2 2-4.6h6.6"/>',
  settings:
    '<path d="M4 7.4h16M4 16.6h16"/><circle cx="9.2" cy="7.4" r="2.1"/><circle cx="15.2" cy="16.6" r="2.1"/>',
  followup:
    '<path d="M12 4.2a7.8 7.8 0 1 0 7.8 7.8"/><path d="M12 8.2a3.8 3.8 0 1 0 3.8 3.8"/><path d="M15.8 12l4.6-4.6"/>',
  ecosystem:
    '<circle cx="12" cy="5.4" r="2.1"/><circle cx="5.6" cy="17.4" r="2.1"/><circle cx="18.4" cy="17.4" r="2.1"/><path d="M12 7.5v4.4M12 11.9L7 15.6M12 11.9l5 3.7"/>',
  updates:
    '<path d="M20 6.6v4.8h-4.8"/><path d="M4 17.4v-4.8h4.8"/><path d="M18.1 10.2A6.8 6.8 0 0 0 6.2 8.4L4 10.2"/><path d="M5.9 13.8a6.8 6.8 0 0 0 11.9 1.8l2.2-1.8"/>',
  audit:
    '<path d="M12 3.4l6.6 2.8v5.6c0 3.9-2.7 6.9-6.6 8.8-3.9-1.9-6.6-4.9-6.6-8.8V6.2z"/><path d="M9.4 12.1l1.9 1.9 3.5-3.7"/>',
  arrow: '<path d="M14.5 6.5L9 12l5.5 5.5"/>',
  lock: '<rect x="4.8" y="10.4" width="14.4" height="9.4" rx="2.2"/><path d="M8.2 10.4V8.2a3.8 3.8 0 0 1 7.6 0v2.2"/>',
};

export function renderSvg(id: string, size = 18, className = ''): string {
  const path = ICON_PATHS[id];
  if (!path) return '';
  const cls = ['pvn-icon', className].filter(Boolean).join(' ');
  return `<svg class="${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${path}</svg>`;
}

export interface NavModuleMeta {
  id: string;
  label: string;
  description: string;
  group: string;
}

export const NAV_GROUPS: Array<{ label: string; ids: string[] }> = [
  {
    label: 'کار بالینی',
    ids: ['overview', 'patients', 'case_files', 'assessments', 'appointments'],
  },
  {
    label: 'پیگیری',
    ids: ['reports', 'communications', 'followup', 'notifications'],
  },
  {
    label: 'مدیریت',
    ids: ['ecosystem', 'system_health', 'settings', 'audit'],
  },
];

export const MODULE_META: Record<string, NavModuleMeta> = {
  overview: {
    id: 'overview',
    label: 'نمای کلی',
    description: 'تصویر امروز شما: جلسه‌های پیش‌رو، موارد نیازمند رسیدگی و آخرین پرونده‌ها.',
    group: 'کار بالینی',
  },
  patients: {
    id: 'patients',
    label: 'مراجعان',
    description: 'جست‌وجو در رجیستری هویت مراجعان (شناسه‌ی یکتا، بدون پایگاه‌داده‌ی جداگانه).',
    group: 'کار بالینی',
  },
  case_files: {
    id: 'case_files',
    label: 'پرونده‌ها',
    description: 'فقط پرونده‌های تخصیص‌داده‌شده به شما؛ ثبت، ویرایش، فرم ارزیابی و یادآور در همین‌جا.',
    group: 'کار بالینی',
  },
  assessments: {
    id: 'assessments',
    label: 'آزمون‌ها',
    description: 'دعوت به آزمون، پیگیری وضعیت، مشاهدهٔ نتیجه و روند ارزیابی.',
    group: 'کار بالینی',
  },
  appointments: {
    id: 'appointments',
    label: 'نوبت‌ها',
    description: 'ثبت، ویرایش، تغییر وضعیت و یادآوری نوبت‌های پنل مراجعان.',
    group: 'کار بالینی',
  },
  reports: {
    id: 'reports',
    label: 'گزارش‌ها',
    description: 'شاخص‌های بالینی و عملیاتی، روند ماهانه، بار کاری و خروجی CSV — فقط روی دادهٔ مجاز شما.',
    group: 'پیگیری',
  },
  communications: {
    id: 'communications',
    label: 'ارتباطات',
    description: 'وضعیت پل پیامک، ایمیل و لاگ پیام‌های ارسالی.',
    group: 'پیگیری',
  },
  followup: {
    id: 'followup',
    label: 'پیگیری پرونده‌ها',
    description: 'پرونده‌های باز بدون جلسه در ۶۰ روز گذشته یا بدون تاریخ جلسهٔ بعدی.',
    group: 'پیگیری',
  },
  notifications: {
    id: 'notifications',
    label: 'اعلان‌ها',
    description: 'اعلان‌های سامانه، هشدارهای نوبت و یادآورهای بالینی.',
    group: 'پیگیری',
  },
  ecosystem: {
    id: 'ecosystem',
    label: 'مرکز اکوسیستم',
    description: 'وضعیت افزونه‌ها، سلامت جدول‌ها، کلید رمزنگاری و کانال‌های اطلاع‌رسانی.',
    group: 'مدیریت',
  },
  system_health: {
    id: 'system_health',
    label: 'سلامت سیستم',
    description: 'بررسی فقط‌خواندنی وابستگی‌ها، نسخه‌ها و تنظیمات رمزنگاری.',
    group: 'مدیریت',
  },
  settings: {
    id: 'settings',
    label: 'تنظیمات',
    description: 'تنظیمات ظاهری و رفتاری داشبورد.',
    group: 'مدیریت',
  },
  audit: {
    id: 'audit',
    label: 'گزارش ممیزی',
    description: 'ثبت رویدادهای دسترسی و تغییرات پرونده‌ها بدون افشای متن بالینی.',
    group: 'مدیریت',
  },
};
