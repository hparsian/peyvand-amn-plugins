import crypto from 'node:crypto';
import { todayIso } from './jalali.js';

export interface CaseFile {
  id: number;
  full_name: string;
  phone: string;
  age: string;
  marital_status: string;
  education: string;
  occupation: string;
  residence: string;
  marriage_type: string;
  children_count: string;
  topic: string;
  status: string;
  assigned_therapist: number;
  assigned_therapist_name: string;
  partner_case_id: number | null;
  partner_phone: string;
  main_conflict_topics: string;
  reminder_channel: 'sms' | 'email';
  reminder_consent: boolean;
  reminder_last_sent_at: string | null;
  next_session_date: string | null;
  next_session_number: number;
  medical_history: string;
  counseling_history: string;
  self_harm_history: string;
  substance_use_history: string;
  symptom_mood_depression: string;
  symptom_anxiety: string;
  q_schema_attachment: string;
  spouse_full_name: string;
  spouse_age: string;
  spouse_education: string;
  spouse_occupation: string;
  spouse_phone: string;
  spouse_failed_marriage_history: string;
  spouse_medical_history: string;
  spouse_counseling_history: string;
  spouse_self_harm_history: string;
  spouse_substance_use_history: string;
  q_main_reason: string;
  q_positive_negative_traits: string;
  q_conflict_resolution: string;
  q_reason_staying: string;
  q_therapy_goals: string;
  relationship_satisfaction_pre: string;
  relationship_satisfaction_post: string;
  prior_therapy: string;
  failed_marriage_history: string;
  created_at: string;
  updated_at: string;
}

export interface ClinicalNote {
  id: number;
  case_id: number;
  session_date: string;
  session_number: number;
  content: string;
  encrypted_payload: string;
  author_id: number;
  author_name: string;
  status: string;
  updated_at: string;
}

export interface NoteHistory {
  id: number;
  note_id: number;
  case_id: number;
  action: string;
  previous_content: string;
  previous_session_date: string;
  previous_session_number: number;
  changed_at: string;
  changed_by: string;
}

export interface ClientIdentity {
  client_id: string;
  display_name: string;
  status: 'active' | 'review' | 'inactive';
  conflict_count: number;
  sources_count: number;
  identifiers: Array<{ type: 'mobile' | 'email' | 'case_id'; value: string }>;
}

export interface QuizDefinition {
  id: string;
  name: string;
  category: string;
  max_score: number;
}

export interface AssessmentInvitation {
  id: number;
  quiz_id: string;
  quiz_name: string;
  client_name: string;
  client_phone: string;
  client_email: string;
  status: 'unused' | 'processing' | 'completed' | 'revoked';
  token: string;
  attempt_id: number;
  is_expired: boolean;
  created_at: string;
  expires_at: string;
}

export interface AssessmentScore {
  attempt_id: number;
  invitation_id: number;
  quiz_id: string;
  label: string;
  client_phone: string;
  client_name: string;
  total: number;
  max_score: number;
  severity: string;
  subscales: Array<{ name: string; score: number; max: number }>;
  interpretation: string;
  completed_at: string;
}

export interface Booking {
  id: number;
  full_name: string;
  mobile: string;
  booking_date: string;
  booking_time: string;
  session_type: string;
  service_name: string;
  booking_source: string;
  status: string;
  ref_id: string;
  therapist_note: string;
}

export interface PortalMessage {
  id: number;
  sender: 'client' | 'therapist';
  sender_name: string;
  body: string;
  sent_at: string;
}

export interface NotificationItem {
  id: number;
  title: string;
  body: string;
  level: 'info' | 'warn' | 'good';
  read: boolean;
  created_at: string;
  module_link: string;
}

export interface AuditEntry {
  id: number;
  action: string;
  entity_type: string;
  entity_id: number;
  actor: string;
  status: 'ok' | 'denied' | 'error';
  detail: string;
  created_at: string;
}

export interface SmsLogEntry {
  id: number;
  mobile: string;
  pattern: string;
  success: boolean;
  reason: string;
  created_at: string;
}

export interface DashboardSettings {
  per_page: number;
  default_range_days: number;
  enable_notes: boolean;
  enable_notifications: boolean;
  delete_data_on_uninstall: boolean;
}

export const THERAPISTS = [
  { id: 1, name: 'دکتر سارا محمدی' },
  { id: 2, name: 'دکتر امین بهرامی' },
  { id: 3, name: 'دکتر نوشین فرهادی' },
];

export const CASE_STATUSES = ['فعال', 'موقتاً متوقف', 'پایان‌یافته', 'بسته', 'آرشیو'];

export const BOOKING_STATUSES = [
  'تایید شده',
  'در انتظار بررسی',
  'در انتظار تایید واریز',
  'انجام شده',
  'لغو شده',
  'عدم حضور',
];

export const SESSION_TYPES: Record<string, string> = {
  individual: 'مشاورهٔ فردی',
  couple: 'زوج‌درمانی',
  online: 'مشاورهٔ آنلاین',
  intake: 'جلسهٔ ارزیابی اولیه',
};

export const BOOKING_SOURCES: Record<string, string> = {
  portal: 'پنل مراجع',
  phone: 'تماس تلفنی',
  clinic: 'پذیرش حضوری',
  whatsapp: 'هماهنگی پیام‌رسان',
};

export const QUIZZES: QuizDefinition[] = [
  { id: 'phq9', name: 'PHQ-9 — غربالگری افسردگی', category: 'بالینی فردی', max_score: 27 },
  { id: 'gad7', name: 'GAD-7 — اضطراب فراگیر', category: 'بالینی فردی', max_score: 21 },
  { id: 'bdi2', name: 'BDI-II — افسردگی بک', category: 'بالینی فردی', max_score: 63 },
  { id: 'bai', name: 'BAI — اضطراب بک', category: 'بالینی فردی', max_score: 63 },
  { id: 'dass21', name: 'DASS-21 — افسردگی، اضطراب و استرس', category: 'بالینی فردی', max_score: 63 },
  { id: 'ecr', name: 'ECR-R — سبک دلبستگی بزرگسالان', category: 'زوج و رابطه', max_score: 126 },
  { id: 'csi', name: 'CSI-16 — رضایت زناشویی', category: 'زوج و رابطه', max_score: 81 },
  { id: 'psqi', name: 'PSQI — کیفیت خواب پیتسبورگ', category: 'سلامت عمومی', max_score: 21 },
];

// AES-256-GCM encryption helper for clinical notes (falls back to an ephemeral session key if env var is unset)
const FALLBACK_KEY = crypto.scryptSync('peyvand-amn-demo-clinical-key', 'pas-salt', 32);

export function getEncryptionStatus(): { configured: boolean; source: string } {
  const envKey = process.env.PAS_CORE_ENCRYPTION_KEY;
  if (envKey && envKey.trim().length >= 16) {
    return { configured: true, source: 'PAS_CORE_ENCRYPTION_KEY (env)' };
  }
  return { configured: true, source: 'کلید نشست محلی (حالت پیش‌نمایش)' };
}

export function encryptClinicalText(plain: string): string {
  const rawKey = process.env.PAS_CORE_ENCRYPTION_KEY;
  const key =
    rawKey && rawKey.trim().length >= 16
      ? crypto.scryptSync(rawKey, 'pas-salt', 32)
      : FALLBACK_KEY;
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
  const enc = Buffer.concat([cipher.update(plain, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return `v2:${iv.toString('hex')}:${tag.toString('hex')}:${enc.toString('hex')}`;
}

function addDaysIso(baseIso: string, deltaDays: number): string {
  const d = new Date(`${baseIso}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + deltaDays);
  return d.toISOString().slice(0, 10);
}

const baseToday = todayIso();

export class InMemoryStore {
  cases = new Map<number, CaseFile>();
  notes = new Map<number, ClinicalNote[]>();
  noteHistory = new Map<number, NoteHistory[]>();
  clients = new Map<string, ClientIdentity>();
  invitations: AssessmentInvitation[] = [];
  scores: AssessmentScore[] = [];
  bookings: Booking[] = [];
  portalMessages: PortalMessage[] = [];
  portalSurveyDone = false;
  notifications: NotificationItem[] = [];
  auditLog: AuditEntry[] = [];
  smsLog: SmsLogEntry[] = [];
  settings: DashboardSettings = {
    per_page: 20,
    default_range_days: 30,
    enable_notes: true,
    enable_notifications: true,
    delete_data_on_uninstall: false,
  };
  intakeQueue = new Map<
    number,
    Array<{
      target: string;
      current: string;
      value: string;
      status: 'fill' | 'same' | 'conflict';
      sensitivity: 'demographic' | 'clinical';
    }>
  >();

  private nextCaseId = 1043;
  private nextNoteId = 505;
  private nextHistoryId = 903;
  private nextInviteId = 306;
  private nextBookingId = 706;
  private nextNotifId = 4;
  private nextAuditId = 6;
  private nextMsgId = 6;
  private nextSmsId = 4;
  lastCreatedInviteLink: { name: string; link: string } | null = null;

  constructor() {
    this.seed();
  }

  private seed(): void {
    const c1042: CaseFile = {
      id: 1042,
      full_name: 'مریم رضایی',
      phone: '09121234567',
      age: '۳۲',
      marital_status: 'متأهل',
      education: 'کارشناسی ارشد روانشناسی تربیتی',
      occupation: 'مشاور مدرسه',
      residence: 'تهران',
      marriage_type: 'آشنایی قبلی (۴ سال)',
      children_count: '۱',
      topic: 'اضطراب رابطه‌ای و تنظیم هیجان',
      status: 'فعال',
      assigned_therapist: 1,
      assigned_therapist_name: 'دکتر سارا محمدی',
      partner_case_id: null,
      partner_phone: '',
      main_conflict_topics: 'عدم هم‌زمانی در گفت‌وگوهای روزانه و فشار کاری',
      reminder_channel: 'sms',
      reminder_consent: true,
      reminder_last_sent_at: `${addDaysIso(baseToday, -2)} 10:15:00`,
      next_session_date: baseToday,
      next_session_number: 5,
      medical_history: 'سابقهٔ کم‌خونی خفیف؛ بدون داروی روان‌پزشکی',
      counseling_history: '۶ جلسه مشاورهٔ پیش از ازدواج (سال ۱۴۰۱)',
      self_harm_history: 'ندارد',
      substance_use_history: 'ندارد',
      symptom_mood_depression: 'افت انرژی عصرگاهی و نگرانی خفیف نسبت به تعادل کار و خانواده',
      symptom_anxiety: 'تنش عضلانی در موقعیت‌های تعارض بین‌فردی',
      q_schema_attachment: 'گرایش مضطرب در دلبستگی؛ طرحوارهٔ رهاشدگی خفیف',
      spouse_full_name: 'حسین مرادی',
      spouse_age: '۳۵',
      spouse_education: 'کارشناسی مهندسی صنایع',
      spouse_occupation: 'مدیر پروژه',
      spouse_phone: '09123334455',
      spouse_failed_marriage_history: 'ندارد',
      spouse_medical_history: 'سالم',
      spouse_counseling_history: 'همراه در جلسات پیش از ازدواج',
      spouse_self_harm_history: 'ندارد',
      spouse_substance_use_history: 'ندارد',
      q_main_reason: 'بهبود کیفیت گفت‌وگوی هیجانی و کاهش سوءتفاهم‌ها در خانه',
      q_positive_negative_traits: 'مثبت: مسئولیت‌پذیری و حمایت مالی / منفی: سکوت هنگام خشم',
      q_conflict_resolution: 'تلاش برای گفت‌وگو، گاهی کناره‌گیری موقت',
      q_reason_staying: 'علاقه متقابل، ارزش‌های مشترک و فرزند',
      q_therapy_goals: 'یادگیری مهارت‌های گوش‌دادن فعال و بیان امن نیازها',
      relationship_satisfaction_pre: '۶ از ۱۰',
      relationship_satisfaction_post: '—',
      prior_therapy: 'مشاوره پیش از ازدواج',
      failed_marriage_history: 'ندارد',
      created_at: addDaysIso(baseToday, -45),
      updated_at: addDaysIso(baseToday, -1),
    };

    const c1039: CaseFile = {
      ...c1042,
      id: 1039,
      full_name: 'علی کریمی',
      phone: '09129876543',
      age: '۳۸',
      marital_status: 'مجرد',
      education: 'کارشناسی مدیریت',
      occupation: 'توسعه‌دهنده نرم‌افزار',
      residence: 'تهران',
      marriage_type: '—',
      children_count: '۰',
      topic: 'فرسودگی شغلی و اختلال خواب',
      status: 'فعال',
      assigned_therapist: 1,
      assigned_therapist_name: 'دکتر سارا محمدی',
      main_conflict_topics: 'مرزبندی کار و استراحت، کمال‌گرایی',
      next_session_date: addDaysIso(baseToday, 1),
      next_session_number: 4,
      symptom_mood_depression: 'خستگی مزمن و کاهش انگیزه در پروژه‌های جدید',
      symptom_anxiety: 'نشخوار فکری شبانه پیش از خواب',
      q_schema_attachment: 'طرحوارهٔ معیارهای سخت‌گیرانه',
      spouse_full_name: '',
      spouse_age: '',
      spouse_education: '',
      spouse_occupation: '',
      spouse_phone: '',
      q_main_reason: 'مدیریت استرس شغلی و بهبود کیفیت خواب',
      created_at: addDaysIso(baseToday, -32),
      updated_at: addDaysIso(baseToday, -2),
    };

    const c1031: CaseFile = {
      ...c1042,
      id: 1031,
      full_name: 'زوج: سحر و امید',
      phone: '09351112233',
      age: '۲۹',
      marital_status: 'متأهل',
      topic: 'بازسازی اعتماد و توافق مالی',
      status: 'موقتاً متوقف',
      assigned_therapist: 1,
      assigned_therapist_name: 'دکتر سارا محمدی',
      partner_case_id: 1032,
      partner_phone: '09354445566',
      next_session_date: addDaysIso(baseToday, 3),
      next_session_number: 7,
      created_at: addDaysIso(baseToday, -72),
      updated_at: addDaysIso(baseToday, -5),
    };

    const c1032: CaseFile = {
      ...c1042,
      id: 1032,
      full_name: 'امید ناصری',
      phone: '09354445566',
      age: '۳۱',
      marital_status: 'متأهل',
      topic: 'همراه پروندهٔ زوجی سحر و امید',
      status: 'فعال',
      assigned_therapist: 1,
      assigned_therapist_name: 'دکتر سارا محمدی',
      partner_case_id: 1031,
      partner_phone: '09351112233',
      next_session_date: addDaysIso(baseToday, 3),
      next_session_number: 7,
      created_at: addDaysIso(baseToday, -72),
      updated_at: addDaysIso(baseToday, -5),
    };

    const c1024: CaseFile = {
      ...c1042,
      id: 1024,
      full_name: 'نگار حسینی',
      phone: '09195556677',
      age: '۲۷',
      marital_status: 'مجرد',
      topic: 'اضطراب اجتماعی و مهارت جرأت‌ورزی',
      status: 'پایان‌یافته',
      assigned_therapist: 1,
      assigned_therapist_name: 'دکتر سارا محمدی',
      next_session_date: null,
      next_session_number: 12,
      created_at: addDaysIso(baseToday, -120),
      updated_at: addDaysIso(baseToday, -9),
    };

    const c1017: CaseFile = {
      ...c1042,
      id: 1017,
      full_name: 'پویا اکبری',
      phone: '09127778899',
      age: '۴۱',
      marital_status: 'متأهل',
      topic: 'مدیریت خشم و تنظیم واکنش‌ها',
      status: 'فعال',
      assigned_therapist: 1,
      assigned_therapist_name: 'دکتر سارا محمدی',
      next_session_date: addDaysIso(baseToday, 6),
      next_session_number: 3,
      created_at: addDaysIso(baseToday, -20),
      updated_at: addDaysIso(baseToday, -3),
    };

    [c1042, c1039, c1031, c1032, c1024, c1017].forEach((c) => this.cases.set(c.id, c));

    const n1Text =
      'مراجع در جلسهٔ چهارم ثبات هیجانی بهتری گزارش کرد. تمرین گفت‌وگوی بدون سرزنش در منزل دو بار اجرا شده و تنش عصرگاهی کاهش یافته است. برنامهٔ جلسهٔ بعدی: مرور الگوی دلبستگی و تمرین بیان نیازهای امن.';
    const n2Text =
      'بررسی نتایج PHQ-9 و GAD-7؛ نمرهٔ اضطراب از ۱۴ به ۹ کاهش یافته است. مراجع تکنیک تنفس دیافراگمی را پیش از موقعیت‌های کاری استرس‌زا به کار برده است.';

    this.notes.set(1042, [
      {
        id: 501,
        case_id: 1042,
        session_date: addDaysIso(baseToday, -6),
        session_number: 4,
        content: n1Text,
        encrypted_payload: encryptClinicalText(n1Text),
        author_id: 1,
        author_name: 'دکتر سارا محمدی',
        status: 'done',
        updated_at: `${addDaysIso(baseToday, -6)} 18:10:00`,
      },
      {
        id: 502,
        case_id: 1042,
        session_date: addDaysIso(baseToday, -13),
        session_number: 3,
        content: n2Text,
        encrypted_payload: encryptClinicalText(n2Text),
        author_id: 1,
        author_name: 'دکتر سارا محمدی',
        status: 'done',
        updated_at: `${addDaysIso(baseToday, -13)} 17:45:00`,
      },
    ]);

    this.noteHistory.set(501, [
      {
        id: 901,
        note_id: 501,
        case_id: 1042,
        action: 'ویرایش اولیه',
        previous_content:
          'مراجع در جلسهٔ چهارم ثبات هیجانی بهتری گزارش کرد. تمرین گفت‌وگوی بدون سرزنش در منزل اجرا شده است.',
        previous_session_date: addDaysIso(baseToday, -6),
        previous_session_number: 4,
        changed_at: `${addDaysIso(baseToday, -6)} 17:55:00`,
        changed_by: 'دکتر سارا محمدی',
      },
    ]);

    this.notes.set(1039, [
      {
        id: 503,
        case_id: 1039,
        session_date: addDaysIso(baseToday, -7),
        session_number: 3,
        content: 'پایش بهداشت خواب: ساعت خواب به ۲۳:۳۰ منتقل شده و استفاده از نمایشگر ۱ ساعت قبل از خواب قطع شده است.',
        encrypted_payload: encryptClinicalText('پایش بهداشت خواب'),
        author_id: 1,
        author_name: 'دکتر سارا محمدی',
        status: 'done',
        updated_at: `${addDaysIso(baseToday, -7)} 16:00:00`,
      },
    ]);

    this.intakeQueue.set(1042, [
      {
        target: 'occupation',
        current: 'مشاور مدرسه',
        value: 'مشاور مدرسه',
        status: 'same',
        sensitivity: 'demographic',
      },
      {
        target: 'q_therapy_goals',
        current: 'یادگیری مهارت‌های گوش‌دادن فعال و بیان امن نیازها',
        value: 'تقویت ارتباط همدلانه با همسر و کاهش اضطراب روزانه',
        status: 'conflict',
        sensitivity: 'clinical',
      },
      {
        target: 'relationship_satisfaction_post',
        current: '—',
        value: '۷ از ۱۰',
        status: 'fill',
        sensitivity: 'clinical',
      },
    ]);

    [
      {
        client_id: 'CL-1042',
        display_name: 'مریم رضایی',
        status: 'active' as const,
        conflict_count: 0,
        sources_count: 3,
        identifiers: [
          { type: 'mobile' as const, value: '09121234567' },
          { type: 'email' as const, value: 'maryam.rezaei@example.ir' },
          { type: 'case_id' as const, value: '1042' },
        ],
      },
      {
        client_id: 'CL-1039',
        display_name: 'علی کریمی',
        status: 'active' as const,
        conflict_count: 0,
        sources_count: 2,
        identifiers: [
          { type: 'mobile' as const, value: '09129876543' },
          { type: 'email' as const, value: 'ali.karimi@example.ir' },
          { type: 'case_id' as const, value: '1039' },
        ],
      },
      {
        client_id: 'CL-1031',
        display_name: 'سحر و امید',
        status: 'review' as const,
        conflict_count: 1,
        sources_count: 3,
        identifiers: [
          { type: 'mobile' as const, value: '09351112233' },
          { type: 'case_id' as const, value: '1031' },
        ],
      },
      {
        client_id: 'CL-1024',
        display_name: 'نگار حسینی',
        status: 'active' as const,
        conflict_count: 0,
        sources_count: 2,
        identifiers: [
          { type: 'mobile' as const, value: '09195556677' },
          { type: 'email' as const, value: 'negar.hosseini@example.ir' },
          { type: 'case_id' as const, value: '1024' },
        ],
      },
      {
        client_id: 'CL-1017',
        display_name: 'پویا اکبری',
        status: 'review' as const,
        conflict_count: 2,
        sources_count: 2,
        identifiers: [
          { type: 'mobile' as const, value: '09127778899' },
          { type: 'case_id' as const, value: '1017' },
        ],
      },
    ].forEach((cl) => this.clients.set(cl.client_id, cl));

    this.invitations = [
      {
        id: 301,
        quiz_id: 'gad7',
        quiz_name: 'GAD-7 — اضطراب فراگیر',
        client_name: 'مریم رضایی',
        client_phone: '09121234567',
        client_email: 'maryam.rezaei@example.ir',
        status: 'completed',
        token: 'inv-gad7-1042',
        attempt_id: 801,
        is_expired: false,
        created_at: `${addDaysIso(baseToday, -14)} 09:20:00`,
        expires_at: `${addDaysIso(baseToday, -7)} 09:20:00`,
      },
      {
        id: 302,
        quiz_id: 'phq9',
        quiz_name: 'PHQ-9 — غربالگری افسردگی',
        client_name: 'مریم رضایی',
        client_phone: '09121234567',
        client_email: 'maryam.rezaei@example.ir',
        status: 'unused',
        token: 'inv-phq9-1042',
        attempt_id: 0,
        is_expired: false,
        created_at: `${addDaysIso(baseToday, -1)} 11:00:00`,
        expires_at: `${addDaysIso(baseToday, 6)} 11:00:00`,
      },
      {
        id: 303,
        quiz_id: 'psqi',
        quiz_name: 'PSQI — کیفیت خواب پیتسبورگ',
        client_name: 'علی کریمی',
        client_phone: '09129876543',
        client_email: 'ali.karimi@example.ir',
        status: 'completed',
        token: 'inv-psqi-1039',
        attempt_id: 802,
        is_expired: false,
        created_at: `${addDaysIso(baseToday, -10)} 14:30:00`,
        expires_at: `${addDaysIso(baseToday, -3)} 14:30:00`,
      },
      {
        id: 304,
        quiz_id: 'ecr',
        quiz_name: 'ECR-R — سبک دلبستگی بزرگسالان',
        client_name: 'زوج: سحر و امید',
        client_phone: '09351112233',
        client_email: '',
        status: 'processing',
        token: 'inv-ecr-1031',
        attempt_id: 0,
        is_expired: false,
        created_at: `${addDaysIso(baseToday, -2)} 16:40:00`,
        expires_at: `${addDaysIso(baseToday, 5)} 16:40:00`,
      },
    ];

    this.scores = [
      {
        attempt_id: 800,
        invitation_id: 301,
        quiz_id: 'gad7',
        label: 'GAD-7 — اضطراب فراگیر',
        client_phone: '09121234567',
        client_name: 'مریم رضایی',
        total: 14,
        max_score: 21,
        severity: 'متوسط',
        subscales: [
          { name: 'بی‌قراری و تنش', score: 8, max: 12 },
          { name: 'نگرانی کنترل‌ناپذیر', score: 6, max: 9 },
        ],
        interpretation: 'سطح اضطراب متوسط در ابتدای دوره درمان؛ نیازمند تمرین‌های تنظیم هیجان.',
        completed_at: `${addDaysIso(baseToday, -35)} 10:15:00`,
      },
      {
        attempt_id: 801,
        invitation_id: 301,
        quiz_id: 'gad7',
        label: 'GAD-7 — اضطراب فراگیر',
        client_phone: '09121234567',
        client_name: 'مریم رضایی',
        total: 9,
        max_score: 21,
        severity: 'خفیف',
        subscales: [
          { name: 'بی‌قراری و تنش', score: 5, max: 12 },
          { name: 'نگرانی کنترل‌ناپذیر', score: 4, max: 9 },
        ],
        interpretation: 'کاهش ۵ واحدی نمرهٔ کل نسبت به ارزیابی پایه؛ روند بهبود مطلوب است.',
        completed_at: `${addDaysIso(baseToday, -13)} 12:05:00`,
      },
      {
        attempt_id: 802,
        invitation_id: 303,
        quiz_id: 'psqi',
        label: 'PSQI — کیفیت خواب پیتسبورگ',
        client_phone: '09129876543',
        client_name: 'علی کریمی',
        total: 11,
        max_score: 21,
        severity: 'نامطلوب',
        subscales: [
          { name: 'تأخیر در به خواب رفتن', score: 3, max: 3 },
          { name: 'تداوم خواب', score: 2, max: 3 },
          { name: 'اختلال عملکرد روزانه', score: 3, max: 3 },
        ],
        interpretation: 'کیفیت خواب پایین به دلیل زمان‌بندی نامنظم خواب و کار شبانه.',
        completed_at: `${addDaysIso(baseToday, -9)} 19:20:00`,
      },
    ];

    this.bookings = [
      {
        id: 701,
        full_name: 'مریم رضایی',
        mobile: '09121234567',
        booking_date: baseToday,
        booking_time: '16:30',
        session_type: 'individual',
        service_name: 'مشاورهٔ فردی',
        booking_source: 'portal',
        status: 'تایید شده',
        ref_id: 'ZDP-8401',
        therapist_note: 'جلسه پنجم — مرور تمرین‌های ارتباطی',
      },
      {
        id: 702,
        full_name: 'علی کریمی',
        mobile: '09129876543',
        booking_date: addDaysIso(baseToday, 1),
        booking_time: '11:00',
        session_type: 'online',
        service_name: 'مشاورهٔ آنلاین',
        booking_source: 'portal',
        status: 'تایید شده',
        ref_id: 'ZDP-8402',
        therapist_note: 'پایش بهداشت خواب',
      },
      {
        id: 703,
        full_name: 'زوج: سحر و امید',
        mobile: '09351112233',
        booking_date: addDaysIso(baseToday, 3),
        booking_time: '18:00',
        session_type: 'couple',
        service_name: 'زوج‌درمانی',
        booking_source: 'clinic',
        status: 'در انتظار بررسی',
        ref_id: 'ZDP-8403',
        therapist_note: '',
      },
      {
        id: 704,
        full_name: 'پویا اکبری',
        mobile: '09127778899',
        booking_date: addDaysIso(baseToday, 6),
        booking_time: '10:00',
        session_type: 'individual',
        service_name: 'مشاورهٔ فردی',
        booking_source: 'phone',
        status: 'تایید شده',
        ref_id: 'ZDP-8404',
        therapist_note: '',
      },
      {
        id: 705,
        full_name: 'مریم رضایی',
        mobile: '09121234567',
        booking_date: addDaysIso(baseToday, -14),
        booking_time: '10:00',
        session_type: 'individual',
        service_name: 'مشاورهٔ فردی',
        booking_source: 'portal',
        status: 'لغو شده',
        ref_id: 'ZDP-8389',
        therapist_note: 'جابه‌جایی با هماهنگی قبلی',
      },
    ];

    this.portalMessages = [
      {
        id: 1,
        sender: 'therapist',
        sender_name: 'دکتر سارا محمدی',
        body: 'سلام مریم جان، لینک آزمون PHQ-9 پیش از جلسهٔ فردا برایتان فعال شد.',
        sent_at: `${addDaysIso(baseToday, -1)} 11:05:00`,
      },
      {
        id: 2,
        sender: 'client',
        sender_name: 'مریم رضایی',
        body: 'ممنونم خانم دکتر، امشب آزمون را تکمیل می‌کنم.',
        sent_at: `${addDaysIso(baseToday, -1)} 14:22:00`,
      },
    ];

    this.notifications = [
      {
        id: 1,
        title: 'نوبت امروز — مریم رضایی',
        body: 'ساعت ۱۶:۳۰ جلسهٔ مشاورهٔ فردی ثبت و تأیید شده است.',
        level: 'good',
        read: false,
        created_at: `${baseToday} 08:00:00`,
        module_link: '/dashboard?module=appointments',
      },
      {
        id: 2,
        title: '۲ پرونده نیازمند بررسی هویت',
        body: 'در رجیستری هویت مراجعان ۲ تعارض نیازمند بازبینی انسانی است.',
        level: 'warn',
        read: false,
        created_at: `${addDaysIso(baseToday, -1)} 17:30:00`,
        module_link: '/dashboard?module=patients',
      },
      {
        id: 3,
        title: 'نتیجهٔ جدید آزمون PSQI',
        body: 'آزمون کیفیت خواب علی کریمی تکمیل شد و آمادهٔ مشاهده است.',
        level: 'info',
        read: false,
        created_at: `${addDaysIso(baseToday, -2)} 19:25:00`,
        module_link: '/dashboard?module=assessments',
      },
    ];

    this.auditLog = [
      {
        id: 1,
        action: 'case.view',
        entity_type: 'case',
        entity_id: 1042,
        actor: 'دکتر سارا محمدی',
        status: 'ok',
        detail: 'مشاهدهٔ پروندهٔ مجاز',
        created_at: `${baseToday} 08:15:00`,
      },
      {
        id: 2,
        action: 'note.save',
        entity_type: 'note',
        entity_id: 501,
        actor: 'دکتر سارا محمدی',
        status: 'ok',
        detail: 'رمزنگاری AES-256-GCM و ثبت تاریخچه',
        created_at: `${addDaysIso(baseToday, -6)} 18:10:00`,
      },
      {
        id: 3,
        action: 'assessment.report',
        entity_type: 'assessment',
        entity_id: 801,
        actor: 'دکتر سارا محمدی',
        status: 'ok',
        detail: 'مشاهدهٔ گزارش GAD-7',
        created_at: `${addDaysIso(baseToday, -13)} 12:15:00`,
      },
    ];

    this.smsLog = [
      {
        id: 1,
        mobile: '09121234567',
        pattern: 'OTP-Login',
        success: true,
        reason: 'ارسال موفق (حالت محلی)',
        created_at: `${baseToday} 09:12:00`,
      },
      {
        id: 2,
        mobile: '09129876543',
        pattern: 'Session-Reminder',
        success: true,
        reason: 'یادآور جلسهٔ ۲۴ ساعت پیش',
        created_at: `${addDaysIso(baseToday, -1)} 10:00:00`,
      },
    ];
  }

  recordAudit(
    action: string,
    entityType: string,
    entityId: number,
    status: 'ok' | 'denied' | 'error',
    detail: string
  ): void {
    this.auditLog.unshift({
      id: this.nextAuditId++,
      action,
      entity_type: entityType,
      entity_id: entityId,
      actor: 'دکتر سارا محمدی',
      status,
      detail,
      created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
    });
    if (this.auditLog.length > 100) this.auditLog.pop();
  }

  createCase(data: Partial<CaseFile>): CaseFile {
    const id = this.nextCaseId++;
    const therapistId = Number(data.assigned_therapist ?? 1);
    const therapist = THERAPISTS.find((t) => t.id === therapistId)?.name ?? '— تخصیص‌نیافته —';
    const now = todayIso();

    const record: CaseFile = {
      id,
      full_name: (data.full_name ?? '').trim() || 'بدون نام',
      phone: (data.phone ?? '').trim(),
      age: data.age ?? '',
      marital_status: data.marital_status ?? '',
      education: data.education ?? '',
      occupation: data.occupation ?? '',
      residence: data.residence ?? '',
      marriage_type: data.marriage_type ?? '',
      children_count: data.children_count ?? '',
      topic: data.topic ?? '',
      status: data.status || 'فعال',
      assigned_therapist: therapistId,
      assigned_therapist_name: therapist,
      partner_case_id: null,
      partner_phone: (data.partner_phone ?? '').trim(),
      main_conflict_topics: data.main_conflict_topics ?? '',
      reminder_channel: data.reminder_channel === 'email' ? 'email' : 'sms',
      reminder_consent: Boolean(data.reminder_consent),
      reminder_last_sent_at: null,
      next_session_date: null,
      next_session_number: 1,
      medical_history: data.medical_history ?? '',
      counseling_history: data.counseling_history ?? '',
      self_harm_history: data.self_harm_history ?? '',
      substance_use_history: data.substance_use_history ?? '',
      symptom_mood_depression: data.symptom_mood_depression ?? '',
      symptom_anxiety: data.symptom_anxiety ?? '',
      q_schema_attachment: data.q_schema_attachment ?? '',
      spouse_full_name: data.spouse_full_name ?? '',
      spouse_age: data.spouse_age ?? '',
      spouse_education: data.spouse_education ?? '',
      spouse_occupation: data.spouse_occupation ?? '',
      spouse_phone: data.spouse_phone ?? '',
      spouse_failed_marriage_history: data.spouse_failed_marriage_history ?? '',
      spouse_medical_history: data.spouse_medical_history ?? '',
      spouse_counseling_history: data.spouse_counseling_history ?? '',
      spouse_self_harm_history: data.spouse_self_harm_history ?? '',
      spouse_substance_use_history: data.spouse_substance_use_history ?? '',
      q_main_reason: data.q_main_reason ?? '',
      q_positive_negative_traits: data.q_positive_negative_traits ?? '',
      q_conflict_resolution: data.q_conflict_resolution ?? '',
      q_reason_staying: data.q_reason_staying ?? '',
      q_therapy_goals: data.q_therapy_goals ?? '',
      relationship_satisfaction_pre: data.relationship_satisfaction_pre ?? '',
      relationship_satisfaction_post: data.relationship_satisfaction_post ?? '',
      prior_therapy: data.prior_therapy ?? '',
      failed_marriage_history: data.failed_marriage_history ?? '',
      created_at: now,
      updated_at: now,
    };

    this.cases.set(id, record);
    this.linkPartnerIfMatch(record, record.partner_phone);
    this.recordAudit('case.create', 'case', id, 'ok', `ثبت پروندهٔ جدید #${id}`);
    return record;
  }

  updateCase(id: number, data: Partial<CaseFile>): CaseFile | null {
    const existing = this.cases.get(id);
    if (!existing) return null;
    const therapistId = Number(data.assigned_therapist ?? existing.assigned_therapist);
    const therapist = THERAPISTS.find((t) => t.id === therapistId)?.name ?? '— تخصیص‌نیافته —';
    const updated: CaseFile = {
      ...existing,
      ...data,
      id: existing.id,
      phone: existing.phone, // immutable identity anchor
      assigned_therapist: therapistId,
      assigned_therapist_name: therapist,
      reminder_consent: Boolean(data.reminder_consent),
      updated_at: todayIso(),
    };
    this.cases.set(id, updated);
    if (typeof data.partner_phone === 'string') {
      this.linkPartnerIfMatch(updated, data.partner_phone.trim());
    }
    this.recordAudit('case.update', 'case', id, 'ok', `به‌روزرسانی مشخصات پرونده #${id}`);
    return updated;
  }

  private linkPartnerIfMatch(record: CaseFile, partnerPhone: string): void {
    if (!partnerPhone) {
      record.partner_case_id = null;
      record.partner_phone = '';
      return;
    }
    for (const candidate of this.cases.values()) {
      if (candidate.id !== record.id && candidate.phone === partnerPhone) {
        record.partner_case_id = candidate.id;
        record.partner_phone = candidate.phone;
        candidate.partner_case_id = record.id;
        candidate.partner_phone = record.phone;
        break;
      }
    }
  }

  saveNote(caseId: number, noteId: number, sessionDateIso: string, sessionNumber: number, content: string): ClinicalNote | null {
    if (!this.cases.has(caseId)) return null;
    const list = this.notes.get(caseId) ?? [];
    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);

    if (noteId > 0) {
      const existing = list.find((n) => n.id === noteId);
      if (!existing) return null;

      const hist = this.noteHistory.get(noteId) ?? [];
      hist.unshift({
        id: this.nextHistoryId++,
        note_id: noteId,
        case_id: caseId,
        action: 'ویرایش',
        previous_content: existing.content,
        previous_session_date: existing.session_date,
        previous_session_number: existing.session_number,
        changed_at: now,
        changed_by: 'دکتر سارا محمدی',
      });
      this.noteHistory.set(noteId, hist);

      existing.session_date = sessionDateIso;
      existing.session_number = sessionNumber;
      existing.content = content;
      existing.encrypted_payload = encryptClinicalText(content);
      existing.updated_at = now;
      this.recordAudit('note.update', 'note', noteId, 'ok', `ویرایش یادداشت پرونده #${caseId}`);
      return existing;
    }

    const note: ClinicalNote = {
      id: this.nextNoteId++,
      case_id: caseId,
      session_date: sessionDateIso,
      session_number: sessionNumber,
      content,
      encrypted_payload: encryptClinicalText(content),
      author_id: 1,
      author_name: 'دکتر سارا محمدی',
      status: 'done',
      updated_at: now,
    };
    list.unshift(note);
    this.notes.set(caseId, list);
    this.recordAudit('note.create', 'note', note.id, 'ok', `ثبت یادداشت جدید در پرونده #${caseId}`);
    return note;
  }

  deleteNote(caseId: number, noteId: number): boolean {
    const list = this.notes.get(caseId);
    if (!list) return false;
    const idx = list.findIndex((n) => n.id === noteId);
    if (idx === -1) return false;
    list.splice(idx, 1);
    this.recordAudit('note.delete', 'note', noteId, 'ok', `حذف یادداشت از پرونده #${caseId}`);
    return true;
  }

  restoreNote(caseId: number, historyId: number): boolean {
    const list = this.notes.get(caseId) ?? [];
    for (const note of list) {
      const hist = this.noteHistory.get(note.id) ?? [];
      const snap = hist.find((h) => h.id === historyId);
      if (snap) {
        const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
        hist.unshift({
          id: this.nextHistoryId++,
          note_id: note.id,
          case_id: caseId,
          action: 'پیش از بازگردانی',
          previous_content: note.content,
          previous_session_date: note.session_date,
          previous_session_number: note.session_number,
          changed_at: now,
          changed_by: 'دکتر سارا محمدی',
        });
        note.content = snap.previous_content;
        note.session_date = snap.previous_session_date;
        note.session_number = snap.previous_session_number;
        note.encrypted_payload = encryptClinicalText(snap.previous_content);
        note.updated_at = now;
        this.recordAudit('note.restore', 'note', note.id, 'ok', `بازگردانی نسخه #${historyId}`);
        return true;
      }
    }
    return false;
  }

  applyIntake(caseId: number, overwrite: boolean): number {
    const record = this.cases.get(caseId);
    const rows = this.intakeQueue.get(caseId);
    if (!record || !rows) return 0;
    let applied = 0;
    for (const row of rows) {
      if (row.status === 'fill' || (overwrite && row.status === 'conflict')) {
        (record as unknown as Record<string, unknown>)[row.target] = row.value;
        row.current = row.value;
        row.status = 'same';
        applied++;
      }
    }
    record.updated_at = todayIso();
    this.recordAudit('case.intake', 'case', caseId, 'ok', `اعمال ${applied} فیلد فرم ارزیابی`);
    return applied;
  }

  createInvitation(quizId: string, clientName: string, clientPhone: string, clientEmail: string, expiryDays: number): AssessmentInvitation {
    const quiz = QUIZZES.find((q) => q.id === quizId) ?? QUIZZES[0];
    const now = new Date();
    const exp = new Date(now.getTime() + Math.max(0, expiryDays) * 86400_000);
    const token = `inv-${quiz.id}-${crypto.randomBytes(4).toString('hex')}`;
    const inv: AssessmentInvitation = {
      id: this.nextInviteId++,
      quiz_id: quiz.id,
      quiz_name: quiz.name,
      client_name: clientName,
      client_phone: clientPhone,
      client_email: clientEmail,
      status: 'unused',
      token,
      attempt_id: 0,
      is_expired: false,
      created_at: now.toISOString().replace('T', ' ').slice(0, 19),
      expires_at: expiryDays > 0 ? exp.toISOString().replace('T', ' ').slice(0, 19) : '',
    };
    this.invitations.unshift(inv);
    this.lastCreatedInviteLink = {
      name: clientName,
      link: `/portal?tab=assessments&token=${token}`,
    };
    this.recordAudit('assessment.invite', 'assessment', inv.id, 'ok', `دعوت به ${quiz.name}`);
    return inv;
  }

  createBooking(payload: {
    full_name: string;
    mobile: string;
    booking_date: string;
    booking_time: string;
    session_type: string;
    booking_source: string;
    therapist_note?: string;
  }): Booking {
    const id = this.nextBookingId++;
    const booking: Booking = {
      id,
      full_name: payload.full_name,
      mobile: payload.mobile,
      booking_date: payload.booking_date,
      booking_time: payload.booking_time,
      session_type: payload.session_type,
      service_name: SESSION_TYPES[payload.session_type] ?? 'مشاورهٔ فردی',
      booking_source: payload.booking_source,
      status: 'تایید شده',
      ref_id: `ZDP-${8400 + id}`,
      therapist_note: payload.therapist_note ?? '',
    };
    this.bookings.unshift(booking);
    this.recordAudit('booking.create', 'booking', id, 'ok', `ثبت نوبت ${booking.ref_id}`);
    return booking;
  }

  addPortalMessage(body: string): PortalMessage {
    const msg: PortalMessage = {
      id: this.nextMsgId++,
      sender: 'client',
      sender_name: 'مریم رضایی',
      body,
      sent_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
    };
    this.portalMessages.push(msg);
    return msg;
  }

  unreadNotificationsCount(): number {
    return this.notifications.filter((n) => !n.read).length;
  }

  markNotificationRead(id?: number): void {
    for (const item of this.notifications) {
      if (!id || item.id === id) {
        item.read = true;
      }
    }
  }

  logSms(mobile: string, pattern: string, reason: string): void {
    this.smsLog.unshift({
      id: this.nextSmsId++,
      mobile,
      pattern,
      success: true,
      reason,
      created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
    });
  }
}

export const store = new InMemoryStore();
