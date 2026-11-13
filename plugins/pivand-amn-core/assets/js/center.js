/**
 * پیوند امن — مرکز مدیریت (فاز ۱۲ / گام ۵)
 *
 * نمودارها فقط از داده‌ای ساخته می‌شوند که PHP در window.PasCenterCharts
 * گذاشته است (اعداد واقعی همان درخواست). ApexCharts سلف‌هاست است و در همین
 * صفحه با همین اسکریپت لود می‌شود؛ اگر به هر دلیلی کتابخانه نبود، صفحه بدون
 * نمودار ولی کامل کار می‌کند و پیام جایگزین نشان داده می‌شود.
 */
(function () {
  'use strict';

  var data = window.PasCenterCharts;
  var palette = ['#3F6B4F', '#8A5A18', '#9B3A2E', '#3E5A75'];
  var brand = { brown: '#51483F', gold: '#C8B48A', goldSoft: '#EFE6D3', line: '#DDD5C8', muted: '#857A6C' };

  function fallback(el, message) {
    if (!el) return;
    el.innerHTML = '<p class="pas-center-empty">' + message + '</p>';
  }

  function mount() {
    if (!data) return;

    if (typeof window.ApexCharts === 'undefined') {
      fallback(document.getElementById('pas-center-health-chart'), 'کتابخانهٔ نمودار در دسترس نیست؛ اعداد همان جدول‌های پایین صفحه است.');
      fallback(document.getElementById('pas-center-tables-chart'), 'کتابخانهٔ نمودار در دسترس نیست؛ اعداد همان جدول‌های پایین صفحه است.');
      return;
    }

    var healthEl = document.getElementById('pas-center-health-chart');
    var tablesEl = document.getElementById('pas-center-tables-chart');

    var healthTotal = (data.health || []).reduce(function (a, b) { return a + b; }, 0);

    if (healthEl && healthTotal > 0) {
      new window.ApexCharts(healthEl, {
        chart: { type: 'donut', height: 250, fontFamily: 'Vazirmatn, Tahoma, sans-serif' },
        series: data.health,
        labels: ['سالم', 'نیازمند بررسی', 'مشکل', 'اطلاع'],
        colors: palette,
        legend: { position: 'bottom', fontSize: '12px', labels: { colors: brand.brown } },
        dataLabels: { enabled: false },
        stroke: { width: 2, colors: ['#fff'] },
        tooltip: { y: { formatter: function (v) { return v + ' افزونه'; } } },
        noData: { text: 'داده‌ای نیست' }
      }).render();
    } else if (healthEl) {
      fallback(healthEl, 'هنوز داده‌ای برای نمایش نیست.');
    }

    var rows = data.plugins || [];
    if (tablesEl && rows.length > 0) {
      new window.ApexCharts(tablesEl, {
        chart: { type: 'bar', height: 250, stacked: true, fontFamily: 'Vazirmatn, Tahoma, sans-serif', toolbar: { show: false } },
        series: [
          { name: 'موجود', data: rows.map(function (r) { return r.present; }) },
          { name: 'غایب', data: rows.map(function (r) { return r.missing; }) }
        ],
        colors: [brand.gold, '#9B3A2E'],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
        xaxis: {
          categories: rows.map(function (r) { return r.name; }),
          labels: { style: { colors: brand.muted, fontSize: '11px' } },
          axisBorder: { color: brand.line },
          axisTicks: { color: brand.line }
        },
        yaxis: { labels: { style: { colors: brand.brown, fontSize: '11px' } } },
        legend: { position: 'bottom', fontSize: '12px', labels: { colors: brand.brown } },
        grid: { borderColor: brand.line },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: function (v) { return v + ' جدول'; } } }
      }).render();
    } else if (tablesEl) {
      fallback(tablesEl, 'هیچ افزونه‌ای جدولی اعلام نکرده است.');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
