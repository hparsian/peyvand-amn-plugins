import { Router, Request, Response } from 'express';
import { store, SESSION_TYPES } from '../store.js';
import { formatJalali, parseJalaliInputToIso, toPersianDigits, todayIso, todayJalaliAscii } from '../jalali.js';

export const portalRouter = Router();

let portalLoggedIn = true;

portalRouter.get('/', (req: Request, res: Response) => {
  const activeTab = typeof req.query.tab === 'string' ? req.query.tab : 'appointments';
  const clientPhone = '09121234567';
  const clientBookings = store.bookings.filter((b) => b.mobile === clientPhone);
  const clientInvites = store.invitations.filter((i) => i.client_phone === clientPhone);
  const nextBooking = clientBookings.find(
    (b) => b.booking_date >= todayIso() && b.status === 'تایید شده'
  );

  res.render('portal', {
    loggedIn: portalLoggedIn,
    activeTab,
    clientName: 'مریم رضایی',
    clientMobile: '۰۹۱۲۱۲۳۴۵۶۷',
    nextBooking,
    bookings: clientBookings,
    invitations: clientInvites,
    pendingAssessments: clientInvites.filter((i) => i.status !== 'completed' && i.status !== 'revoked').length,
    messages: store.portalMessages,
    surveyDone: store.portalSurveyDone,
    sessionTypes: SESSION_TYPES,
    todayJalaliAscii: todayJalaliAscii(),
    h: {
      jalali: formatJalali,
      persianNum: toPersianDigits,
    },
  });
});

portalRouter.post('/login', (_req: Request, res: Response) => {
  portalLoggedIn = true;
  res.redirect('/portal');
});

portalRouter.get('/logout', (_req: Request, res: Response) => {
  portalLoggedIn = false;
  res.redirect('/portal');
});

portalRouter.post('/book', (req: Request, res: Response) => {
  const jalaliDate = String(req.body.booking_date_jalali || '');
  const time = String(req.body.booking_time || '16:30');
  const sessionType = String(req.body.session_type || 'individual');
  const isoDate = parseJalaliInputToIso(jalaliDate) ?? todayIso();

  store.createBooking({
    full_name: 'مریم رضایی',
    mobile: '09121234567',
    booking_date: isoDate,
    booking_time: time,
    session_type: sessionType,
    booking_source: 'portal',
  });
  res.redirect('/portal?tab=appointments');
});

portalRouter.post('/message', (req: Request, res: Response) => {
  const body = String(req.body.body || '').trim();
  if (body) {
    store.addPortalMessage(body);
  }
  res.redirect('/portal?tab=messages');
});

portalRouter.post('/survey', (_req: Request, res: Response) => {
  store.portalSurveyDone = true;
  res.redirect('/portal?tab=survey');
});
