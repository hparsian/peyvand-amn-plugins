<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Quiz_Offline_Export
 * ─────────────────────────────────────────────────────────────
 * تولید فایل HTML آفلاین آزمون:
 *   ۱. مراجع فایل را بدون اینترنت باز می‌کند و پاسخ می‌دهد
 *   ۲. در پایان فایل یک دکمه "ارسال" وجود دارد که پاسخ‌ها را
 *      به ایمیل درمانگر می‌فرستد (از طریق mailto)
 *   ۳. درمانگر پاسخ‌ها را در پنل ادمین وارد می‌کند
 */
class Psy_Quiz_Offline_Export {

    /** ایمیل پیش‌فرض دریافت پاسخ‌های آفلاین — از تنظیمات خوانده می‌شود */
    public static function therapist_email(): string {
        return sanitize_email( get_option( 'psy_quiz_therapist_email', 'peyvandamn@gmail.com' ) );
    }

    /**
     * ساخت و دانلود فایل HTML آفلاین
     */
    public static function generate( string $quiz_id, string $client_name ): void {
        $test = \PsychAssessments\Core\Registry::get( $quiz_id );
        if ( ! $test ) {
            wp_die( 'آزمون یافت نشد.' );
        }

        $questions = $test->get_questions();
        $scale     = $test->get_scale();
        $q_scales  = method_exists( $test, 'get_question_scales' ) ? $test->get_question_scales() : [];
        $q_count   = count( $questions );
        $email     = self::therapist_email();

        $html = self::build_html( $client_name, $quiz_id, $q_count, $questions, $scale, $q_scales, $email );

        $filename = 'azmon-' . sanitize_title( $client_name ) . '-' . $quiz_id . '.html';
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache' );
        echo $html;
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    private static function build_html(
        string $client_name,
        string $quiz_id,
        int    $q_count,
        array  $questions,
        array  $scale,
        array  $q_scales,
        string $email
    ): string {

        // ── ساخت HTML سوالات ─────────────────────────────────────
        $questions_html = '';
        foreach ( $questions as $qnum => $qtext ) {
            $qs      = $q_scales[$qnum] ?? $scale;
            $options = '';
            foreach ( $qs['labels'] as $val => $lbl ) {
                $options .= '<label class="opt-label">
                    <input type="radio" name="q_' . $qnum . '" value="' . $val . '" onchange="markAnswered(' . $qnum . ')">
                    <span class="opt-box">
                        <span class="opt-val">' . $val . '</span>
                        <span class="opt-lbl">' . htmlspecialchars( $lbl, ENT_QUOTES ) . '</span>
                    </span>
                </label>';
            }
            $questions_html .= '
            <div class="q-card" id="qcard_' . $qnum . '" data-qnum="' . $qnum . '" hidden>
                <div class="q-num">سوال ' . $qnum . ' از ' . $q_count . '</div>
                <div class="progress-bar"><div class="progress-fill" id="prog_' . $qnum . '"></div></div>
                <div class="q-text">' . htmlspecialchars( $qtext, ENT_QUOTES ) . '</div>
                <div class="opts">' . $options . '</div>
                <div class="q-nav">
                    ' . ( $qnum > 1 ? '<button class="btn btn-prev" onclick="goTo(' . ($qnum-1) . ')">← قبلی</button>' : '<span></span>' ) . '
                    ' . ( $qnum < $q_count
                        ? '<button class="btn btn-next" id="next_' . $qnum . '" onclick="goTo(' . ($qnum+1) . ')" disabled>بعدی ←</button>'
                        : '<button class="btn btn-submit" id="next_' . $qnum . '" onclick="submitAnswers()" disabled>ثبت و ارسال ✓</button>'
                    ) . '
                </div>
            </div>';
        }

        $client_safe  = htmlspecialchars( $client_name, ENT_QUOTES );
        $quiz_id_safe = htmlspecialchars( $quiz_id,     ENT_QUOTES );

        // BUG-OFFLINE-2 fix: مقادیر json-encoded برای embed ایمن در JS
        $js_quiz_id     = wp_json_encode( $quiz_id );
        $js_client_name = wp_json_encode( $client_name );
        $js_email       = wp_json_encode( $email );

        // BUG-OFFLINE-3 fix: heredoc فقط "{$var}" را تفسیر می‌کند، نه "{self::method()}"
        // بنابراین باید خروجی متد را از قبل در یک متغیر ساده بریزیم.
        $js_global_scale = self::json_scale( $scale );
        $js_q_scales     = self::json_qscales( $q_scales, $scale );

        return <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>آزمون {$quiz_id_safe} — {$client_safe}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Tahoma', 'Segoe UI', Arial, sans-serif;
    background: #f0f4f8;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .wrap { max-width: 640px; width: 100%; }

  /* ── کارت‌های مختلف ── */
  .card {
    background: #fff;
    border-radius: 16px;
    padding: 36px 32px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
  }

  /* ── صفحه خوشامد ── */
  #screen-welcome .icon { font-size: 52px; text-align: center; margin-bottom: 16px; }
  #screen-welcome h1 { font-size: 1.4rem; text-align: center; color: #2d3748; margin-bottom: 8px; }
  .info-box {
    background: #ebf8ff;
    border-right: 4px solid #3182ce;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 20px;
    color: #2b6cb0;
    font-size: .9rem;
    line-height: 1.8;
  }
  .scale-box { background: #f7fafc; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; }
  .scale-box .scale-title { font-size: .85rem; color: #718096; margin-bottom: 10px; }
  .scale-items { display: flex; flex-wrap: wrap; gap: 8px; }
  .scale-item {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: .85rem;
    color: #4a5568;
  }
  .scale-item .sval { font-weight: bold; color: #3182ce; margin-left: 6px; }
  .btn-start {
    width: 100%;
    background: #3182ce;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 14px;
    font-size: 1rem;
    cursor: pointer;
    font-family: inherit;
    transition: background .2s;
  }
  .btn-start:hover { background: #2c5282; }

  /* ── کارت سوال ── */
  .q-card .q-num { font-size: .8rem; color: #a0aec0; margin-bottom: 6px; }
  .progress-bar { background: #e2e8f0; border-radius: 99px; height: 6px; margin-bottom: 24px; }
  .progress-fill { background: #3182ce; height: 6px; border-radius: 99px; transition: width .4s ease; }
  .q-text { font-size: 1.05rem; color: #2d3748; line-height: 1.8; margin-bottom: 24px; }
  .opts { display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px; }
  .opt-label { cursor: pointer; }
  .opt-label input[type=radio] { display: none; }
  .opt-box {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    transition: all .15s;
  }
  .opt-label input:checked + .opt-box {
    background: #ebf8ff;
    border-color: #3182ce;
  }
  .opt-label:hover .opt-box { border-color: #bee3f8; }
  .opt-val {
    width: 28px; height: 28px;
    background: #e2e8f0;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: bold; font-size: .9rem;
    flex-shrink: 0;
    color: #4a5568;
  }
  .opt-label input:checked + .opt-box .opt-val { background: #3182ce; color: #fff; }
  .opt-lbl { color: #4a5568; font-size: .95rem; }
  .q-nav { display: flex; justify-content: space-between; align-items: center; }
  .btn { border: none; border-radius: 8px; padding: 10px 22px; font-size: .95rem; cursor: pointer; font-family: inherit; transition: all .2s; }
  .btn-prev { background: #edf2f7; color: #4a5568; }
  .btn-prev:hover { background: #e2e8f0; }
  .btn-next, .btn-submit { background: #3182ce; color: #fff; }
  .btn-next:hover:not(:disabled), .btn-submit:hover:not(:disabled) { background: #2c5282; }
  .btn:disabled { opacity: .4; cursor: not-allowed; }
  .btn-submit { background: #38a169; }
  .btn-submit:hover:not(:disabled) { background: #276749; }

  /* ── صفحه پایان ── */
  #screen-done .done-icon { font-size: 60px; text-align: center; margin-bottom: 16px; }
  #screen-done h2 { text-align: center; color: #2d3748; margin-bottom: 12px; }
  #screen-done p { text-align: center; color: #718096; line-height: 1.8; margin-bottom: 8px; }
  .done-note {
    background: #f0fff4;
    border-right: 4px solid #38a169;
    border-radius: 8px;
    padding: 12px 16px;
    color: #276749;
    font-size: .9rem;
    margin-top: 20px;
    text-align: right;
  }

  [hidden] { display: none; }
  .mt-16 { margin-top: 16px; }
  .mt-20 { margin-top: 20px; }
  .psy-center { text-align: center; }
  .btn-block { width: 100%; padding: 14px; font-size: 1rem; }
  .link-inline { color: #3182ce; font-size: .9rem; text-decoration: underline; }

  @media (max-width: 480px) {
    .card { padding: 24px 18px; }
    .scale-items { gap: 6px; }
  }
</style>
</head>
<body>
<div class="wrap">

  <!-- صفحه خوشامد -->
  <div id="screen-welcome" class="card">
    <div class="icon">📋</div>
    <h1>سلام {$client_safe} عزیز</h1>
    <div class="info-box">
      این پرسشنامه شامل <strong>{$q_count} سوال</strong> است.<br>
      لطفاً در فضایی آرام و بدون عجله به سوالات پاسخ دهید.<br>
      پاسخ درست یا غلطی وجود ندارد — صادقانه پاسخ دهید.
    </div>
    <div class="scale-box">
      <div class="scale-title">راهنمای پاسخ‌دهی:</div>
      <div class="scale-items" id="global-scale"></div>
    </div>
    <button class="btn-start" onclick="startQuiz()">شروع آزمون ←</button>
  </div>

  <!-- سوالات -->
  {$questions_html}

  <!-- صفحه پایان -->
  <div id="screen-done" class="card" hidden>
    <div class="done-icon">✅</div>
    <h2>پاسخ‌های شما ثبت شد</h2>
    <p>فایل پاسخ‌ها را دانلود کنید و برای درمانگر خود بفرستید (واتساپ، تلگرام، ایمیل یا هر روش دیگر).</p>
    <p class="mt-16">
      <button class="btn btn-submit btn-block" onclick="downloadAnswersFile()">
        💾 دانلود فایل پاسخ‌ها
      </button>
    </p>
    <div class="done-note">
      یک فایل متنی (.txt) دانلود می‌شود. آن را برای درمانگر خود ارسال کنید؛ او فایل را باز کرده و پاسخ‌ها را در سیستم ثبت می‌کند.
    </div>
    <p class="mt-20 psy-center">
      <a href="javascript:void(0)" onclick="sendEmail()" class="link-inline">
        یا ارسال مستقیم با برنامه ایمیل (اگر روی دستگاه شما تنظیم شده)
      </a>
    </p>
  </div>

</div>


<script>
const QUIZ_ID     = {$js_quiz_id};
const CLIENT_NAME = {$js_client_name};
const Q_COUNT     = {$q_count};
const EMAIL_TO    = {$js_email};

const globalScale = {$js_global_scale};
const qScales     = {$js_q_scales};

let answered = {};
let currentQ = 1;

// ── راه‌اندازی مقیاس در صفحه خوشامد
document.getElementById('global-scale').innerHTML =
  Object.entries(globalScale).map(([v,l]) =>
    `<div class="scale-item"><span class="sval">\${v}</span>\${l}</div>`
  ).join('');

function startQuiz() {
  document.getElementById('screen-welcome').style.display = 'none';
  goTo(1);
}

function goTo(n) {
  // مخفی کردن کارت فعلی
  document.querySelectorAll('.q-card').forEach(c => c.style.display = 'none');
  const card = document.getElementById('qcard_' + n);
  if (!card) return;
  card.style.display = 'block';
  currentQ = n;
  // آپدیت progress
  const fill = document.getElementById('prog_' + n);
  if (fill) fill.style.width = Math.round((n-1)/Q_COUNT*100) + '%';
  window.scrollTo(0,0);
}

function markAnswered(qnum) {
  const radios = document.querySelectorAll(`input[name="q_\${qnum}"]`);
  let val = null;
  radios.forEach(r => { if (r.checked) val = parseInt(r.value); });
  if (val !== null) {
    answered[qnum] = val;
    const nextBtn = document.getElementById('next_' + qnum);
    if (nextBtn) nextBtn.disabled = false;
  }
}

function submitAnswers() {
  // بررسی همه سوالات پاسخ داده شده
  for (let i = 1; i <= Q_COUNT; i++) {
    if (answered[i] === undefined) {
      goTo(i);
      alert('لطفاً به همه سوالات پاسخ دهید. سوال ' + i + ' بی‌پاسخ است.');
      return;
    }
  }
  document.querySelectorAll('.q-card').forEach(c => c.style.display = 'none');
  document.getElementById('screen-done').style.display = 'block';
  window.scrollTo(0,0);
}

function buildAnswersText() {
  // ساخت متن پاسخ‌ها — همین فرمت توسط «ورود دستی» در پنل ادمین parse می‌شود
  let body = `آزمون آفلاین — پاسخ‌های مراجع\n`;
  body += `━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  body += `مراجع: \${CLIENT_NAME}\n`;
  body += `شناسه آزمون: \${QUIZ_ID}\n`;
  body += `تعداد سوال: \${Q_COUNT}\n`;
  body += `تاریخ: \${new Date().toLocaleDateString('fa-IR')}\n`;
  body += `━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
  body += `پاسخ‌ها (شماره سوال: مقدار):\n`;

  for (let i = 1; i <= Q_COUNT; i++) {
    const scale = qScales[i] || globalScale;
    const val   = answered[i];
    const label = scale[val] !== undefined ? scale[val] : val;
    body += `سوال \${i}: \${val} — \${label}\n`;
  }

  body += `\n━━━━━━━━━━━━━━━━━━━━━━━━\n`;
  body += `[این فایل توسط آزمون آفلاین Psy Quiz Enterprise تولید شده است]`;
  return body;
}

function downloadAnswersFile() {
  const body = buildAnswersText();
  const blob = new Blob([body], { type: 'text/plain;charset=utf-8' });
  const url  = URL.createObjectURL(blob);
  const safeClient = CLIENT_NAME.replace(/[^a-zA-Z0-9آ-ی_\-]+/g, '-');
  const a = document.createElement('a');
  a.href = url;
  a.download = `pasokh-\${safeClient}-\${QUIZ_ID}.txt`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

function sendEmail() {
  const body    = buildAnswersText();
  const subject = encodeURIComponent(`پاسخ آزمون آفلاین — \${CLIENT_NAME} — \${QUIZ_ID}`);
  const bodyEnc = encodeURIComponent(body);
  window.location.href = `mailto:\${EMAIL_TO}?subject=\${subject}&body=\${bodyEnc}`;
}

</script>
</body>
</html>
HTML;
    }

    /** تبدیل scale به JSON برای JS */
    private static function json_scale( array $scale ): string {
        $out = [];
        foreach ( $scale['labels'] as $v => $l ) {
            // fix XSS: wp_json_encode thay az addslashes — taminad mikanad break dar JS string nadashte bashim
            $out[] = '"' . intval($v) . '":' . wp_json_encode( (string)$l );
        }
        return '{' . implode( ',', $out ) . '}';
    }

    /** تبدیل per-question scales به JSON — fallback به global */
    private static function json_qscales( array $q_scales, array $global ): string {
        if ( empty( $q_scales ) ) return '{}';
        $out = [];
        foreach ( $q_scales as $qnum => $qs ) {
            $items = [];
            foreach ( $qs['labels'] as $v => $l ) {
                // fix XSS: wp_json_encode
                $items[] = '"' . intval($v) . '":' . wp_json_encode( (string)$l );
            }
            $out[] = '"' . intval($qnum) . '":{' . implode( ',', $items ) . '}';
        }
        return '{' . implode( ',', $out ) . '}';
    }

    /**
     * ثبت route ادمین برای دانلود HTML
     * فراخوانده می‌شود از admin_post_psy_offline_export
     */
    public static function register_hooks(): void {
        add_action( 'admin_post_psy_offline_export', function () {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز.', 403 );
            check_admin_referer( 'psy_offline_export' );
            $quiz_id     = sanitize_key( $_POST['quiz_id']     ?? '' );
            $client_name = sanitize_text_field( wp_unslash( $_POST['client_name'] ?? 'مراجع' ) );
            if ( ! $quiz_id ) wp_die( 'آزمون انتخاب نشده.' );
            self::generate( $quiz_id, $client_name );
        } );
    }
}
