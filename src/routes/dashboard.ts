import { Router, Request, Response } from 'express';
import {
  store,
  THERAPISTS,
  CASE_STATUSES,
  BOOKING_STATUSES,
  SESSION_TYPES,
  BOOKING_SOURCES,
  QUIZZES,
  getEncryptionStatus,
} from '../store.js';
import {
  formatJalali,
  formatJalaliAscii,
  formatJalaliDateTime,
  parseJalaliInputToIso,
  toPersianDigits,
  maskMobile,
  todayIso,
  todayJalali,
  todayJalaliAscii,
  gregorianToJalali,
  JALALI_MONTH_NAMES,
} from '../jalali.js';
import { renderSvg, NAV_GROUPS, MODULE_META } from '../icons.js';

export const dashboardRouter = Router();

interface FlashMessage {
  type: 'success' | 'warning' | 'error' | 'info';
  message: string;
}

const flashQueue: FlashMessage[] = [];

function pushFlash(type: FlashMessage['type'], message: string): void {
  flashQueue.push({ type, message });
}

function consumeFlash(): FlashMessage[] {
  return flashQueue.splice(0, flashQueue.length);
}

function buildNav(activeId: string) {
  return NAV_GROUPS.map((group) => ({
    label: group.label,
    isCurrent: group.ids.includes(activeId),
    items: group.ids
      .map((id) => {
        const meta = MODULE_META[id];
        if (!meta) return null;
        return {
          id,
          label: meta.label,
          url: `/dashboard?module=${id}`,
          active: id === activeId,
          iconSvg: renderSvg(id, 18),
        };
      })
      .filter(Boolean),
  }));
}

function buildRecentJalaliMonths(count = 6) {
  const today = todayIso();
  const [gy, gm, gd] = today.split('-').map(Number);
  const [jy, jm] = gregorianToJalali(gy, gm, gd);
  const months: Array<{ key: string; label: string; short: string }> = [];

  for (let i = count - 1; i >= 0; i--) {
    let m = jm - i;
    let y = jy;
    while (m <= 0) {
      m += 12;
      y--;
    }
    const name = JALALI_MONTH_NAMES[m - 1];
    months.push({
      key: `${String(y).padStart(4, '0')}/${String(m).padStart(2, '0')}`,
      label: `${name} ${toPersianDigits(y)}`,
      short: name,
    });
  }
  return months;
}

function jalaliMonthKey(isoDate: string): string {
  const m = String(isoDate || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return '';
  const [jy, jm] = gregorianToJalali(Number(m[1]), Number(m[2]), Number(m[3]));
  return `${String(jy).padStart(4, '0')}/${String(jm).padStart(2, '0')}`;
}

dashboardRouter.get('/', (req: Request, res: Response) => {
  const requested = typeof req.query.module === 'string' ? req.query.module : 'overview';
  const moduleId = MODULE_META[requested] ? requested : 'overview';
  const meta = MODULE_META[moduleId];
  const cryptoStatus = getEncryptionStatus();
  const cases = Array.from(store.cases.values()).sort((a, b) => b.id - a.id);
  const today = todayIso();

  // Shared view helpers
  const helpers = {
    svg: renderSvg,
    jalali: formatJalali,
    jalaliAscii: formatJalaliAscii,
    jalaliDateTime: formatJalaliDateTime,
    persianNum: toPersianDigits,
    maskMobile,
    todayJalali: todayJalali(),
    todayJalaliAscii: todayJalaliAscii(),
  };

  let moduleData: Record<string, unknown> = {};

  if (moduleId === 'overview') {
    const upcoming = cases
      .filter((c) => c.next_session_date && c.next_session_date >= today)
      .sort((a, b) => (a.next_session_date || '').localeCompare(b.next_session_date || ''))
      .slice(0, 5);

    const todaySessionsCount = cases.filter((c) => c.next_session_date === today).length;
    const reviewCount = Array.from(store.clients.values()).filter((c) => c.status === 'review').length;
    const casesWithoutNext = cases.filter(
      (c) => !['بسته', 'آرشیو', 'پایان‌یافته'].includes(c.status) && !c.next_session_date
    ).length;

    moduleData = {
      casesCount: cases.length,
      todaySessionsCount,
      registryCount: store.clients.size,
      reviewCount,
      casesWithoutNext,
      upcoming,
      recent: cases.slice(0, 6),
      smsReady: Boolean(process.env.PAS_SMS_USERNAME),
    };
  } else if (moduleId === 'patients') {
    const clientIdParam = typeof req.query.client_id === 'string' ? req.query.client_id.trim() : '';
    const query = typeof req.query.q === 'string' ? req.query.q.trim() : '';
    const searchType = typeof req.query.type === 'string' ? req.query.type : 'client_id';
    const selectedClient = clientIdParam ? store.clients.get(clientIdParam) ?? null : null;

    let searchResult = null;
    if (query) {
      if (searchType === 'client_id') {
        searchResult = store.clients.get(query.toUpperCase()) ?? store.clients.get(query) ?? null;
      } else {
        for (const cl of store.clients.values()) {
          if (cl.identifiers.some((id) => id.type === searchType && id.value.includes(query))) {
            searchResult = cl;
            break;
          }
        }
      }
    }

    moduleData = {
      selectedClient,
      query,
      searchType,
      searchResult,
      clients: Array.from(store.clients.values()),
    };
  } else if (moduleId === 'case_files') {
    const isNew = req.query.new === '1';
    const caseId = Number(req.query.case_id || 0);
    const selectedCase = caseId > 0 ? store.cases.get(caseId) ?? null : null;
    const caseNotes = selectedCase ? store.notes.get(selectedCase.id) ?? [] : [];
    const noteHistories: Record<number, ReturnType<typeof store.noteHistory.get>> = {};
    for (const n of caseNotes) {
      noteHistories[n.id] = store.noteHistory.get(n.id) ?? [];
    }
    const partnerCase =
      selectedCase?.partner_case_id ? store.cases.get(selectedCase.partner_case_id) ?? null : null;
    const intakeRows = selectedCase ? store.intakeQueue.get(selectedCase.id) ?? [] : [];

    moduleData = {
      isNew,
      caseId,
      selectedCase,
      cases: cases.map((c) => {
        const notes = store.notes.get(c.id) ?? [];
        return {
          ...c,
          last_session_date: notes[0]?.session_date ?? '',
        };
      }),
      caseNotes,
      noteHistories,
      partnerCase,
      intakeRows,
      therapists: THERAPISTS,
      statuses: CASE_STATUSES,
    };
  } else if (moduleId === 'assessments') {
    const statusFilter = typeof req.query.status === 'string' ? req.query.status : '';
    const invitationId = Number(req.query.invitation_id || 0);
    const attemptId = Number(req.query.attempt_id || 0);

    const invitation = invitationId > 0 ? store.invitations.find((i) => i.id === invitationId) ?? null : null;
    const report =
      invitation && attemptId > 0
        ? store.scores.find((s) => s.attempt_id === attemptId && s.client_phone === invitation.client_phone) ?? null
        : null;

    const trendScores = invitation
      ? store.scores.filter((s) => s.client_phone === invitation.client_phone)
      : [];

    const filteredInvitations = statusFilter
      ? store.invitations.filter((i) => i.status === statusFilter)
      : store.invitations;

    const newInviteLink = store.lastCreatedInviteLink;
    store.lastCreatedInviteLink = null;

    moduleData = {
      quizzes: QUIZZES,
      statusFilter,
      invitations: filteredInvitations,
      totalInvitations: store.invitations.length,
      pendingCount: store.invitations.filter(
        (i) => ['unused', 'processing'].includes(i.status) && !i.is_expired
      ).length,
      completedCount: store.invitations.filter((i) => i.status === 'completed').length,
      invitation,
      trendScores,
      report,
      newInviteLink,
    };
  } else if (moduleId === 'appointments') {
    const search = typeof req.query.s === 'string' ? req.query.s.trim() : '';
    const filteredBookings = search
      ? store.bookings.filter(
          (b) =>
            b.full_name.includes(search) ||
            b.mobile.includes(search) ||
            b.ref_id.toLowerCase().includes(search.toLowerCase())
        )
      : store.bookings;

    const upcomingCases = cases
      .filter((c) => c.next_session_date)
      .sort((a, b) => (a.next_session_date || '').localeCompare(b.next_session_date || ''));

    moduleData = {
      search,
      bookings: filteredBookings,
      stats: {
        total: filteredBookings.length,
        today: filteredBookings.filter((b) => b.booking_date === today).length,
        upcoming: filteredBookings.filter(
          (b) =>
            b.booking_date >= today &&
            !['لغو شده', 'ناموفق', 'انجام شده', 'عدم حضور'].includes(b.status)
        ).length,
        pending: filteredBookings.filter((b) =>
          ['در انتظار پرداخت', 'در انتظار تایید واریز', 'در انتظار بررسی'].includes(b.status)
        ).length,
      },
      sessionTypes: SESSION_TYPES,
      sources: BOOKING_SOURCES,
      statuses: BOOKING_STATUSES,
      upcomingCases,
    };
  } else if (moduleId === 'reports') {
    const months = buildRecentJalaliMonths(6).map((m) => ({
      ...m,
      cases: 0,
      bookings: 0,
      invites: 0,
    }));
    const monthMap = new Map(months.map((m) => [m.key, m]));

    for (const c of cases) {
      const k = jalaliMonthKey(c.created_at);
      const row = monthMap.get(k);
      if (row) row.cases++;
    }
    for (const b of store.bookings) {
      const k = jalaliMonthKey(b.booking_date);
      const row = monthMap.get(k);
      if (row) row.bookings++;
    }
    for (const inv of store.invitations) {
      const k = jalaliMonthKey(inv.created_at);
      const row = monthMap.get(k);
      if (row) row.invites++;
    }

    const maxTrend = Math.max(
      1,
      ...months.map((m) => Math.max(m.cases, m.bookings, m.invites))
    );

    const caseStatusCounts: Record<string, number> = {};
    for (const c of cases) {
      caseStatusCounts[c.status] = (caseStatusCounts[c.status] || 0) + 1;
    }

    const bookingStatusCounts: Record<string, number> = {};
    for (const b of store.bookings) {
      bookingStatusCounts[b.status] = (bookingStatusCounts[b.status] || 0) + 1;
    }

    const cancelledCount = store.bookings.filter((b) =>
      ['لغو شده', 'عدم حضور', 'ناموفق'].includes(b.status)
    ).length;
    const cancelRate =
      store.bookings.length > 0
        ? Math.round((cancelledCount / store.bookings.length) * 100)
        : 0;

    const workloadCounts: Record<string, number> = {};
    for (const c of cases) {
      workloadCounts[c.assigned_therapist_name] =
        (workloadCounts[c.assigned_therapist_name] || 0) + 1;
    }
    const maxWorkload = Math.max(1, ...Object.values(workloadCounts));

    moduleData = {
      kpis: [
        { value: toPersianDigits(cases.length), label: 'پروندهٔ مجاز', hint: 'پرونده‌هایی که در دامنهٔ دسترسی شما هستند.' },
        { value: toPersianDigits(cases.filter((c) => c.status === 'فعال').length), label: 'پروندهٔ فعال', hint: 'پرونده‌های در حال پیگیری مستمر.' },
        { value: toPersianDigits(store.bookings.filter((b) => b.booking_date >= today).length), label: 'نوبت پیش‌رو', hint: 'نوبت‌های ثبت‌شده از امروز به بعد.' },
        { value: `${toPersianDigits(cancelRate)}٪`, label: 'نرخ لغو/عدم حضور', hint: `${toPersianDigits(cancelledCount)} از ${toPersianDigits(store.bookings.length)} نوبت` },
        { value: toPersianDigits(store.scores.length), label: 'آزمون تکمیل‌شده', hint: 'نتایج ارزیابی ثبت‌شده در سامانه.' },
        { value: toPersianDigits(store.clients.size), label: 'مراجع در رجیستری', hint: 'هویت‌های یکتای ثبت‌شده در هسته.' },
      ],
      months,
      maxTrend,
      caseStatusCounts,
      totalCases: cases.length,
      bookingStatusCounts,
      totalBookings: store.bookings.length,
      workloadCounts,
      maxWorkload,
    };
  } else if (moduleId === 'notifications') {
    moduleData = {
      notifications: store.notifications,
    };
  } else if (moduleId === 'communications') {
    moduleData = {
      smsLog: store.smsLog,
      smsReady: Boolean(process.env.PAS_SMS_USERNAME),
      smtpReady: Boolean(process.env.ZSM_SMTP_HOST || process.env.ZSM_BREVO_API_KEY),
    };
  } else if (moduleId === 'followup') {
    const needsFollowup = cases.filter(
      (c) => !['بسته', 'آرشیو', 'پایان‌یافته'].includes(c.status) && !c.next_session_date
    );
    moduleData = {
      needsFollowup,
      allActive: cases.filter((c) => c.status === 'فعال'),
    };
  } else if (moduleId === 'ecosystem') {
    moduleData = {
      plugins: [
        { key: 'pivand-amn-core', label: 'هستهٔ مشترک پیوند امن', active: true, version: '2.1.0', tables: '3 از 3', status: 'ok' },
        { key: 'peyvand-amn-unified-dashboard', label: 'داشبورد یکپارچه درمانگر', active: true, version: '2.1.0', tables: '2 از 2', status: 'ok' },
        { key: 'pivand-amn-case-file', label: 'پرونده‌های بالینی رمزنگاری‌شده', active: true, version: '2.4.0', tables: '3 از 3', status: 'ok' },
        { key: 'pivand-amn-client-portal', label: 'پنل مراجعان و نوبت‌دهی', active: true, version: '1.11.1', tables: '3 از 3', status: 'ok' },
        { key: 'pivand-amn-quiz-enterprise', label: 'سامانهٔ آزمون‌های روان‌شناختی', active: true, version: '2.9.5', tables: '3 از 3', status: 'ok' },
        { key: 'pivand-amn-sms-bridge', label: 'پل پیامک و الگوهای OTP', active: Boolean(process.env.PAS_SMS_USERNAME), version: '1.4.0', tables: '1 از 1', status: process.env.PAS_SMS_USERNAME ? 'ok' : 'warn' },
      ],
      smsLog: store.smsLog,
    };
  } else if (moduleId === 'system_health') {
    moduleData = {
      checks: [
        { label: 'محیط اجرا (Node.js)', ok: true, detail: `${process.version} (Express + EJS)` },
        { label: 'ذخیره‌سازی نشست', ok: true, detail: 'In-Memory Store (Map/Array)' },
        { label: 'کلید رمزنگاری بالینی', ok: cryptoStatus.configured, detail: cryptoStatus.source },
        { label: 'پرونده‌های بالینی', ok: true, detail: `${store.cases.size} پرونده بارگذاری‌شده` },
        { label: 'آزمون‌های روان‌شناختی', ok: true, detail: `${QUIZZES.length} آزمون فعال` },
        { label: 'پنل مراجعان', ok: true, detail: 'مسیر /portal در دسترس' },
        { label: 'پل پیامک', ok: Boolean(process.env.PAS_SMS_USERNAME), detail: process.env.PAS_SMS_USERNAME ? 'متصل' : 'حالت پیش‌نمایش محلی' },
      ],
    };
  } else if (moduleId === 'settings') {
    moduleData = {
      settings: store.settings,
      roles: [
        { slug: 'administrator', label: 'مدیر کلینیک', access: 'تمام پرونده‌ها، تنظیمات و ممیزی', modules: 'همهٔ ماژول‌ها' },
        { slug: 'pas_therapist', label: 'درمانگر', access: 'فقط پرونده‌ها و نوبت‌های مراجعان مجاز', modules: 'نمای کلی، مراجعان، پرونده‌ها، آزمون‌ها، نوبت‌ها، گزارش‌ها' },
        { slug: 'pas_reception', label: 'پذیرش (داشبورد)', access: 'بدون دسترسی به محتوای بالینی رمزنگاری‌شده', modules: 'نوبت‌ها، ارتباطات، اعلان‌ها' },
      ],
    };
  } else if (moduleId === 'audit') {
    moduleData = {
      entries: store.auditLog,
    };
  }

  res.render('dashboard', {
    moduleId,
    meta,
    navGroups: buildNav(moduleId),
    unread: store.unreadNotificationsCount(),
    cryptoReady: cryptoStatus.configured,
    cryptoSource: cryptoStatus.source,
    user: {
      name: 'دکتر سارا محمدی',
      initial: 'د',
    },
    flash: consumeFlash(),
    h: helpers,
    data: moduleData,
  });
});

// Case create / update
dashboardRouter.post('/api/cases', (req: Request, res: Response) => {
  const caseId = Number(req.body.case_id || 0);
  const payload = {
    full_name: String(req.body.full_name || ''),
    phone: String(req.body.phone || ''),
    age: String(req.body.age || ''),
    marital_status: String(req.body.marital_status || ''),
    education: String(req.body.education || ''),
    occupation: String(req.body.occupation || ''),
    residence: String(req.body.residence || ''),
    marriage_type: String(req.body.marriage_type || ''),
    children_count: String(req.body.children_count || ''),
    topic: String(req.body.topic || ''),
    status: (CASE_STATUSES as readonly string[]).includes(String(req.body.status || 'فعال'))
      ? String(req.body.status || 'فعال')
      : 'فعال',
    assigned_therapist: Number(req.body.assigned_therapist || 1),
    partner_phone: String(req.body.partner_phone || ''),
    main_conflict_topics: String(req.body.main_conflict_topics || ''),
    reminder_channel: (req.body.reminder_channel === 'email' ? 'email' : 'sms') as 'sms' | 'email',
    reminder_consent: Boolean(req.body.reminder_consent),
    medical_history: String(req.body.medical_history || ''),
    counseling_history: String(req.body.counseling_history || ''),
    self_harm_history: String(req.body.self_harm_history || ''),
    substance_use_history: String(req.body.substance_use_history || ''),
    symptom_mood_depression: String(req.body.symptom_mood_depression || ''),
    symptom_anxiety: String(req.body.symptom_anxiety || ''),
    q_schema_attachment: String(req.body.q_schema_attachment || ''),
    spouse_full_name: String(req.body.spouse_full_name || ''),
    spouse_age: String(req.body.spouse_age || ''),
    spouse_education: String(req.body.spouse_education || ''),
    spouse_occupation: String(req.body.spouse_occupation || ''),
    spouse_phone: String(req.body.spouse_phone || ''),
    spouse_failed_marriage_history: String(req.body.spouse_failed_marriage_history || ''),
    spouse_medical_history: String(req.body.spouse_medical_history || ''),
    spouse_counseling_history: String(req.body.spouse_counseling_history || ''),
    spouse_self_harm_history: String(req.body.spouse_self_harm_history || ''),
    spouse_substance_use_history: String(req.body.spouse_substance_use_history || ''),
    q_main_reason: String(req.body.q_main_reason || ''),
    q_positive_negative_traits: String(req.body.q_positive_negative_traits || ''),
    q_conflict_resolution: String(req.body.q_conflict_resolution || ''),
    q_reason_staying: String(req.body.q_reason_staying || ''),
    q_therapy_goals: String(req.body.q_therapy_goals || ''),
    relationship_satisfaction_pre: String(req.body.relationship_satisfaction_pre || ''),
    relationship_satisfaction_post: String(req.body.relationship_satisfaction_post || ''),
    prior_therapy: String(req.body.prior_therapy || ''),
    failed_marriage_history: String(req.body.failed_marriage_history || ''),
  };

  if (caseId > 0) {
    const updated = store.updateCase(caseId, payload);
    if (updated) {
      pushFlash('success', 'تغییرات پرونده با موفقیت ذخیره شد.');
      return res.redirect(`/dashboard?module=case_files&case_id=${updated.id}`);
    }
    pushFlash('error', 'پرونده یافت نشد.');
    return res.redirect('/dashboard?module=case_files');
  }

  if (!payload.phone.trim()) {
    pushFlash('error', 'شماره موبایل مراجع برای ثبت پرونده الزامی است.');
    return res.redirect('/dashboard?module=case_files&new=1');
  }
  const created = store.createCase(payload);
  pushFlash('success', `پروندهٔ جدید #${toPersianDigits(created.id)} ثبت شد.`);
  return res.redirect(`/dashboard?module=case_files&case_id=${created.id}`);
});

// Apply intake mapping
dashboardRouter.post('/api/cases/:id/intake', (req: Request, res: Response) => {
  const caseId = Number(req.params.id);
  const overwrite = Boolean(req.body.overwrite);
  const count = store.applyIntake(caseId, overwrite);
  pushFlash('success', `${toPersianDigits(count)} فیلد از فرم ارزیابی روی پرونده اعمال شد.`);
  res.redirect(`/dashboard?module=case_files&case_id=${caseId}#intake`);
});

// Send case reminder
dashboardRouter.post('/api/cases/:id/reminder', (req: Request, res: Response) => {
  const caseId = Number(req.params.id);
  const c = store.cases.get(caseId);
  if (!c) {
    pushFlash('error', 'پرونده یافت نشد.');
    return res.redirect('/dashboard?module=case_files');
  }
  c.reminder_last_sent_at = new Date().toISOString().replace('T', ' ').slice(0, 19);
  store.logSms(c.phone, 'Session-Reminder', `یادآور پرونده #${caseId}`);
  store.recordAudit('case.reminder', 'case', caseId, 'ok', 'ارسال یادآور جلسه');
  pushFlash('success', 'یادآور جلسه با موفقیت ارسال و در لاگ ثبت شد.');
  res.redirect(`/dashboard?module=case_files&case_id=${caseId}#reminder`);
});

// Clinical notes AJAX endpoints
dashboardRouter.post('/api/notes/save', (req: Request, res: Response) => {
  const caseId = Number(req.body.case_id || 0);
  const noteId = Number(req.body.note_id || 0);
  const sessionDateRaw = String(req.body.session_date || '');
  const sessionNumber = Number(req.body.session_number || 0);
  const content = String(req.body.content || '').trim();

  if (!content) {
    return res.status(400).json({ success: false, data: { message: 'متن یادداشت نمی‌تواند خالی باشد.' } });
  }
  const isoDate = parseJalaliInputToIso(sessionDateRaw) ?? todayIso();
  const saved = store.saveNote(caseId, noteId, isoDate, sessionNumber, content);
  if (!saved) {
    return res.status(404).json({ success: false, data: { message: 'پرونده یا یادداشت یافت نشد.' } });
  }
  return res.json({
    success: true,
    data: {
      message: noteId > 0 ? 'یادداشت ویرایش و رمزنگاری شد.' : 'یادداشت جدید رمزنگاری و ثبت شد.',
      note: saved,
    },
  });
});

dashboardRouter.post('/api/notes/delete', (req: Request, res: Response) => {
  const caseId = Number(req.body.case_id || 0);
  const noteId = Number(req.body.note_id || 0);
  const ok = store.deleteNote(caseId, noteId);
  if (!ok) {
    return res.status(404).json({ success: false, data: { message: 'یادداشت یافت نشد.' } });
  }
  return res.json({ success: true, data: { message: 'یادداشت با موفقیت حذف شد.' } });
});

dashboardRouter.post('/api/notes/restore', (req: Request, res: Response) => {
  const caseId = Number(req.body.case_id || 0);
  const historyId = Number(req.body.history_id || 0);
  const ok = store.restoreNote(caseId, historyId);
  if (!ok) {
    return res.status(404).json({ success: false, data: { message: 'نسخهٔ انتخاب‌شده یافت نشد.' } });
  }
  return res.json({ success: true, data: { message: 'نسخهٔ قبلی یادداشت بازگردانی شد.' } });
});

// Assessments actions
dashboardRouter.post('/api/assessments/invite', (req: Request, res: Response) => {
  const quizId = String(req.body.quiz_id || 'phq9');
  const clientName = String(req.body.client_name || '').trim();
  const clientPhone = String(req.body.client_phone || '').trim();
  const clientEmail = String(req.body.client_email || '').trim();
  const expiryDays = Number(req.body.expiry_days ?? 7);

  if (!clientName || !clientPhone) {
    pushFlash('error', 'نام و شماره موبایل مراجع برای ساخت دعوت‌نامه الزامی است.');
    return res.redirect('/dashboard?module=assessments');
  }

  store.createInvitation(quizId, clientName, clientPhone, clientEmail, expiryDays);
  if (req.body.notify) {
    store.logSms(clientPhone, 'Assessment-Invite', `ارسال دعوت آزمون ${quizId}`);
  }
  pushFlash('success', 'دعوت‌نامهٔ آزمون با موفقیت ساخته شد.');
  return res.redirect('/dashboard?module=assessments');
});

dashboardRouter.post('/api/assessments/:id/notify', (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const inv = store.invitations.find((i) => i.id === id);
  if (!inv) {
    pushFlash('error', 'دعوت‌نامه مورد نظر یافت نشد.');
    return res.redirect('/dashboard?module=assessments');
  }
  store.logSms(inv.client_phone, 'Assessment-Invite', `ارسال مجدد دعوت #${id}`);
  pushFlash('success', `دعوت‌نامه دوباره برای ${inv.client_name} ارسال شد.`);
  return res.redirect('/dashboard?module=assessments');
});

dashboardRouter.post('/api/assessments/:id/revoke', (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const inv = store.invitations.find((i) => i.id === id);
  if (!inv) {
    pushFlash('error', 'دعوت‌نامه مورد نظر یافت نشد.');
    return res.redirect('/dashboard?module=assessments');
  }
  inv.status = 'revoked';
  store.recordAudit('assessment.revoke', 'assessment', id, 'ok', 'ابطال دعوت‌نامهٔ آزمون');
  pushFlash('warning', 'دعوت‌نامه باطل شد.');
  return res.redirect('/dashboard?module=assessments');
});

// Appointments actions
dashboardRouter.post('/api/bookings', (req: Request, res: Response) => {
  const fullName = String(req.body.full_name || '').trim();
  const mobile = String(req.body.mobile || '').trim();
  const jalaliDate = String(req.body.booking_date_jalali || '').trim();
  const bookingTimeRaw = String(req.body.booking_time || '09:00').trim();
  const bookingTime = /^([01]\d|2[0-3]):[0-5]\d$/.test(bookingTimeRaw) ? bookingTimeRaw : '09:00';
  const sessionTypeRaw = String(req.body.session_type || 'individual');
  const sessionType = Object.prototype.hasOwnProperty.call(SESSION_TYPES, sessionTypeRaw)
    ? sessionTypeRaw
    : 'individual';
  const bookingSourceRaw = String(req.body.booking_source || 'clinic');
  const bookingSource = Object.prototype.hasOwnProperty.call(BOOKING_SOURCES, bookingSourceRaw)
    ? bookingSourceRaw
    : 'clinic';
  const therapistNote = String(req.body.therapist_note || '').slice(0, 2000);

  const isoDate = parseJalaliInputToIso(jalaliDate) ?? todayIso();
  if (!fullName || !mobile) {
    pushFlash('error', 'نام و موبایل مراجع الزامی است.');
    return res.redirect('/dashboard?module=appointments');
  }
  store.createBooking({
    full_name: fullName,
    mobile,
    booking_date: isoDate,
    booking_time: bookingTime,
    session_type: sessionType,
    booking_source: bookingSource,
    therapist_note: therapistNote,
  });
  pushFlash('success', 'نوبت جدید با موفقیت ثبت شد.');
  return res.redirect('/dashboard?module=appointments');
});

dashboardRouter.post('/api/bookings/:id/status', (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const status = String(req.body.status || '');
  const b = store.bookings.find((item) => item.id === id);
  if (!b) {
    pushFlash('error', 'نوبت مورد نظر یافت نشد.');
    return res.redirect('/dashboard?module=appointments');
  }
  if (!(BOOKING_STATUSES as readonly string[]).includes(status)) {
    pushFlash('error', 'وضعیت انتخاب‌شده معتبر نیست.');
    return res.redirect('/dashboard?module=appointments');
  }
  b.status = status;
  store.recordAudit('booking.status', 'booking', id, 'ok', `تغییر وضعیت نوبت به ${status}`);
  pushFlash('success', 'وضعیت نوبت به‌روزرسانی شد.');
  return res.redirect('/dashboard?module=appointments');
});

dashboardRouter.post('/api/bookings/:id/update', (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const b = store.bookings.find((item) => item.id === id);
  if (!b) {
    pushFlash('error', 'نوبت مورد نظر یافت نشد.');
    return res.redirect('/dashboard?module=appointments');
  }
  const iso = parseJalaliInputToIso(String(req.body.booking_date_jalali || ''));
  if (iso) b.booking_date = iso;
  if (req.body.booking_time) {
    const t = String(req.body.booking_time).trim().slice(0, 5);
    if (/^([01]\d|2[0-3]):[0-5]\d$/.test(t)) {
      b.booking_time = t;
    }
  }
  if (req.body.session_type) {
    const st = String(req.body.session_type);
    if (Object.prototype.hasOwnProperty.call(SESSION_TYPES, st)) {
      b.session_type = st;
      b.service_name = SESSION_TYPES[st] ?? b.service_name;
    }
  }
  if (req.body.booking_source) {
    const bs = String(req.body.booking_source);
    if (Object.prototype.hasOwnProperty.call(BOOKING_SOURCES, bs)) {
      b.booking_source = bs;
    }
  }
  if (req.body.status) {
    const st = String(req.body.status);
    if ((BOOKING_STATUSES as readonly string[]).includes(st)) {
      b.status = st;
    }
  }
  if (req.body.therapist_note !== undefined) {
    b.therapist_note = String(req.body.therapist_note).slice(0, 2000);
  }
  store.recordAudit('booking.update', 'booking', id, 'ok', 'ویرایش جزئیات نوبت');
  pushFlash('success', 'جزئیات نوبت ذخیره شد.');
  return res.redirect('/dashboard?module=appointments');
});

dashboardRouter.post('/api/bookings/:id/reminder', (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const b = store.bookings.find((item) => item.id === id);
  if (!b) {
    pushFlash('error', 'نوبت مورد نظر یافت نشد.');
    return res.redirect('/dashboard?module=appointments');
  }
  store.logSms(b.mobile, 'Booking-Reminder', `یادآور نوبت ${b.ref_id}`);
  pushFlash('success', `یادآور نوبت برای ${b.full_name} ارسال شد.`);
  return res.redirect('/dashboard?module=appointments');
});

dashboardRouter.post('/api/bookings/:id/delete', (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const idx = store.bookings.findIndex((item) => item.id === id);
  if (idx === -1) {
    pushFlash('error', 'نوبت مورد نظر یافت نشد.');
    return res.redirect('/dashboard?module=appointments');
  }
  store.bookings.splice(idx, 1);
  store.recordAudit('booking.delete', 'booking', id, 'ok', 'حذف نوبت');
  pushFlash('warning', 'نوبت حذف شد.');
  return res.redirect('/dashboard?module=appointments');
});

// Notifications read
dashboardRouter.post('/api/notifications/read', (req: Request, res: Response) => {
  const id = Number(req.body.notification_id || 0);
  store.markNotificationRead(id > 0 ? id : undefined);
  res.json({ success: true });
});

// Settings save
dashboardRouter.post('/api/settings', (req: Request, res: Response) => {
  const perPage = Math.max(5, Math.min(100, Number(req.body.per_page || 20)));
  const range = Math.max(1, Math.min(365, Number(req.body.default_range_days || 30)));
  store.settings = {
    per_page: perPage,
    default_range_days: range,
    enable_notes: Boolean(req.body.enable_notes),
    enable_notifications: Boolean(req.body.enable_notifications),
    delete_data_on_uninstall: Boolean(req.body.delete_data_on_uninstall),
  };
  store.recordAudit('settings.save', 'settings', 0, 'ok', 'ذخیرهٔ تنظیمات داشبورد');
  pushFlash('success', 'تنظیمات داشبورد ذخیره شد.');
  res.redirect('/dashboard?module=settings');
});

function csvCell(value: unknown): string {
  let str = String(value ?? '').replace(/\r\n|\r|\n/g, ' ');
  if (/^[\s\x00-\x1F]*[=+\-@\t]/.test(str)) {
    str = `'${str}`;
  }
  return `"${str.replace(/"/g, '""')}"`;
}

// CSV export (de-identified clinical metrics)
dashboardRouter.post('/api/export/cases', (_req: Request, res: Response) => {
  const header = 'case_id,status,therapist,notes_count,updated_at\n';
  const rows = Array.from(store.cases.values())
    .map((c) => {
      const notesCount = (store.notes.get(c.id) ?? []).length;
      return `${c.id},${csvCell(c.status)},${csvCell(c.assigned_therapist_name)},${notesCount},${csvCell(c.updated_at)}`;
    })
    .join('\n');

  store.recordAudit('export.cases', 'report', 0, 'ok', 'دریافت خروجی CSV پرونده‌ها');
  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', 'attachment; filename="peyvand-amn-cases.csv"');
  res.send(`\uFEFF${header}${rows}\n`);
});
