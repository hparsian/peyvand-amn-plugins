گام ۱۴.۴ — مرحلهٔ ۱۴: بررسی بصری صفحات ادمین پس از حذف استایل درون‌خطی
Staging: http://127.0.0.1:8090 — ویوپورت ۱۴۴۰×۱۰۰۰، Firefox/Playwright، locale=fa-IR
نسخهٔ افزونه در زمان شکار: v3.8.13

debug-tracker.png    admin.php?page=psy-quiz-debug&tab=tracker — ۵۹ کلاس تُن؛ نوار «درصد کامل»: psy-w--60 → ۲۹۸px از ۴۹۶px (۶۰٪) با برچسب «۵۹٪ کامل»
debug-logs.png       admin.php?page=psy-quiz-debug&tab=logs — چیپ‌های سطح warning/error با تُن درست (زمینهٔ تُن + متن سفید)
couples-love-chart.png admin.php?page=psy-quiz-couples&action=compare&couple_id=3 — پس از رفع باگ نمودار خالی: ۲۵ نقطهٔ سبک با ۶ رنگ تُن، ۵۰ نوار مقایسه با عرض درست (psy-w--80 → ۱۰۹px از ۱۳۶px) و نمودار میله‌ای Chart.js با ۲۵ جفت میله (بوم ۱۱۹۰×۵۹۵، ۲۹۸٫۱۰۹ پیکسل رندرشده)
progress-compare.png admin.php?page=psy-quiz-progress&action=compare&attempt1=1&attempt2=2 — جدول خلاصهٔ نمره‌ها

دادهٔ آزمایشی: یک زوج CSI و یک زوج قصه عشق + دو attempt دستی برای قصه عشق، و option ابزارک دیباگ
موقتاً روی ۶۰٪ «دیباگ‌شده» تنظیم شد تا نوار درصد پر دیده شود. همه پس از شکار حذف/بازگردانی شد
(couples=0, attempts=3, invitations=3, tracker option=0 entries).


── بررسی نمودارهای صفحهٔ گزارش‌ها (۳۴ آزمون، یک attempt ساختگی برای هر آزمون) ──
روش: باز کردن admin.php?page=psy-quiz-reports&attempt=<ID> در Firefox، انتظار ۱٫۵ ثانیه،
شمارش canvas و پیکسل‌های غیرشفاف هر بوم و تعداد نمونهٔ Chart.

quiz        attempt  canvases  painted            وضعیت
ace         8        1         313170             رندر شد
audit       9        1         201178             رندر شد
bai         10       1         329809             رندر شد
bdi         11       1         329809             رندر شد
bis11       12       1         351248             رندر شد
csi         13       1         279484             رندر شد
cts2        14       1         278064             رندر شد
das         15       1         269216             رندر شد
dass21      16       1         192679             رندر شد
pss10       17       0         -                  بدون نمودار (طراحی)
ecr         18       1         4138               رندر شد
fms         19       1         419311             رندر شد
ghq28       20       1         311982             رندر شد
gse         21       1         263870             رندر شد
ibt         22       1         229009             رندر شد
las         23       1         239411             رندر شد
love_story  24       1         259217             رندر شد
mdq         25       1         466867             رندر شد
epds        26       1         287114             رندر شد
ders        27       1         315606             رندر شد
neo         28       1         192698             رندر شد
ocir        29       0         -                  بدون نمودار (طراحی)
pcl5        30       0         -                  بدون نمودار (طراحی)
bpaq        31       0         -                  بدون نمودار (طراحی)
pam         32       1         405491             رندر شد
phq9        33       0         -                  بدون نمودار (طراحی)
gad7        34       0         -                  بدون نمودار (طراحی)
psqi        35       1         358276             رندر شد
ras         36       0         -                  بدون نمودار (طراحی)
rses        37       1         263901             رندر شد
scl90r      38       1         242802             رندر شد
stai        39       1         247528             رندر شد
swls        40       1         238869             رندر شد
ysq         41       2         279264,249517      رندر شد

یادداشت‌ها:
- ۷ آزمون (phq9، gad7، pss10، ras، ocir، pcl5، bpaq) طبق طراحی canvas ندارند.
- پیش از رفع باگ: fms با وجود canvas، صفر پیکسل و inst=0 بود؛ علت یک TypeError مهلک در
  بلوک «تفسیر ترکیبی FMS + PAM» بود: ستون answers رمزنگاری‌شده با json_decode خام خوانده
  می‌شد، null می‌داد و calculate(null) خطا می‌کرد → صفحه نیمه‌کاره می‌ماند و Chart.js فوتر
  هرگز لود نمی‌شد. پس از اصلاح با pa_decrypt_json: ۴۱۹٫۳۱۱ پیکسل و inst=1.
- ecr از نظر فنی رندر می‌شود (inst=1) اما رادار با ۲ محور به یک خط عمودی تباه می‌شود؛
  ایراد طراحی است، نه خرابی اجرا (به‌عنوان یافته گزارش شد، رفع نشد).

── نمودار ECR: از رادار به میله‌ای + قالب گزارش در ادمین ──
ecr-report-before.png  نمای تک‌گزارش پیش از رفع: گزارش بدون هیچ استایل (frontend.css در wp-admin بارگذاری
                       نمی‌شد)، بوم رادار ۱۱۷۶×۱۱۷۶ و رادار دو محوری به یک خط تبدیل شده بود.
ecr-report-after.png   پس از رفع: frontend.css در همین نما بارگذاری شد (کارت/تُن/جدول قالب‌دار) و
                       نمودار میله‌ای افقی با بوم ۳۶۸×۱۶۷ (max-height ۲۸۰px) رسم شد.
chart-ecr.png          تصویر فقط-بوم همان نمودار پس از تغییر (دو میله، محور ۰..۷، مبدأ در راست).


── بازبینی چیدمان و ارتفاع نمودارها در نمای تک‌گزارش ادمین (۳۴ آزمون) ──
روش: برای هر آزمون یک attempt دستی، باز کردن admin.php?page=psy-quiz-reports&attempt=<ID>،
اندازه‌گیری getBoundingClientRect بوم و والدش (تشخیص سرریز/کلیپ)، max-height محاسبه‌شده و
پیکسل‌های غیرشفاف. هیچ بومی از والدش بیرون نمی‌زند (clipY/clipX ≤ 0) و هیچ سرریز افقی صفحه نیست.

quiz        canvas id               w×h        max-height   painted
ace         pa-ace-bar             1134×180    180px        81442
audit       pa-audit-bar           1134×180    180px        56126
bai         pa-bai-chart           1134×300    300px        154272
bdi         pa-bdi-chart           1134×300    300px        154272
bis11       pa-bis-bar             1134×180    180px        91938
csi         pa-gauge-chart         200×200    200px        9101
cts2        pa-cts2-bar            1134×280    280px        122807
das         pa-bar-chart           368×184    280px        23759
dass21      pa-radar-chart         371×280    280px        9243
pss10       —                        (بدون نمودار طبق طراحی)
ecr         pa-ecr-chart           368×167    280px        11364
fms         fms_chart_54           560×280    280px        9329
ghq28       pa-bar-chart           368×184    280px        23250
gse         pa-gse-chart           400×200    200px        31443
ibt         pa-bar-chart           1134×320    320px        122269
las         pa-bar-chart           1134×320    320px        120863
love_story  pa-ls-radar            1134×440    440px        42302
mdq         pa-mdq-bar             1134×180    180px        118375
epds        pa-epds-bar            1134×200    200px        84165
ders        pa-ders-radar          1134×280    280px        17627
neo         pa-radar-chart         415×320    320px        17154
ocir        —                        (بدون نمودار طبق طراحی)
pcl5        —                        (بدون نمودار طبق طراحی)
bpaq        —                        (بدون نمودار طبق طراحی)
pam         pam_chart_67           560×260    260px        76625
phq9        —                        (بدون نمودار طبق طراحی)
gad7        —                        (بدون نمودار طبق طراحی)
psqi        pa-psqi-chart          200×200    200px        5519
ras         —                        (بدون نمودار طبق طراحی)
rses        pa-rses-chart          400×200    200px        31442
scl90r      pa-scl-bar             1134×320    320px        123447
stai        pa-stai-bar            1134×200    200px        75086
swls        pa-swls-chart          1134×200    200px        72109
ysq         pa-bar-chart           500×250    280px        40852
ysq         pa-radar-chart         500×280    280px        16441

اصلاح‌های این مرحله:
- fms: کلاس بوم از psy-report-chart--gauge (max-height 200/max-width 400 → بوم ۲۴۶×۱۹۹ و برچسب‌های
  فشرده) به psy-report-chart--bar تغییر کرد؛ اکنون ۵۶۰×۲۸۰ و میله‌ها خوانا هستند.
- pam: بوم هیچ کلاسی نداشت (max-height: none → ۵۵۹×۴۱۰ و لبه‌به‌لبه با کارت)؛ کلاس psy-report-chart
  اضافه شد؛ اکنون ۵۶۰×۲۶۰.
- csi و psqi عمداً گیج ۲۰۰×۲۰۰ هستند (نمودار دوناتی) و در پنل تمام‌عرض وسط‌چین می‌نشینند؛
  فقط فضای خالی زیاد است، خرابی چیدمان نیست.
- love_story رادار ۲۵ محوری ۱۱۳۴×۴۴۰ است (max-height 440) که برای ۲۵ برچسب منطقی است.
