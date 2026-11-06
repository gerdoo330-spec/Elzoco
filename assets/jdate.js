/* تقویم شمسی مشترک: هر ورودی با data-jdate به تقویم وصل می‌شود.
   مقدار به شکل 1405/07/20 (ارقام انگلیسی) فقط از داخل تقویم نوشته می‌شود. */
(function () {
  const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  const WEEK = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']; // هفته از شنبه شروع می‌شود؛ جمعه (ج) قرمز است
  const FA = '۰۱۲۳۴۵۶۷۸۹';
  const fa = (n) => String(n).replace(/\d/g, (d) => FA[+d]);
  const pad = (n) => String(n).padStart(2, '0');
  const DAY = 86400000;
  const DF = new Intl.DateTimeFormat('en-u-ca-persian-nu-latn', { year: 'numeric', month: 'numeric', day: 'numeric' });

  /* تاریخ میلادی ← شمسی [y, m, d] با همان API مرورگر */
  const toJ = (date) => {
    const p = {};
    DF.formatToParts(date).forEach((x) => { p[x.type] = +x.value; });
    return [p.year, p.month, p.day];
  };
  const key = (j) => j[0] * 10000 + j[1] * 100 + j[2];

  /* میلادیِ اولین روز سال شمسی (نوروز)، ظهر برای جلوگیری از مشکل ساعت تابستانی */
  const nowruz = (jy) => {
    let t = new Date(jy + 621, 2, 21, 12);
    for (let i = 0; i < 20 && key(toJ(t)) !== key([jy, 1, 1]); i++) {
      t = new Date(t.getTime() + (key([jy, 1, 1]) > key(toJ(t)) ? DAY : -DAY));
    }
    return t;
  };
  /* اولین روز یک ماه شمسی */
  const monthStart = (jy, jm) => {
    const days = jm <= 7 ? (jm - 1) * 31 : (jm - 1) * 30 + 6;
    return new Date(nowruz(jy).getTime() + days * DAY);
  };
  const daysInMonth = (jy, jm) => {
    const a = monthStart(jy, jm);
    const b = jm === 12 ? monthStart(jy + 1, 1) : monthStart(jy, jm + 1);
    return Math.round((b - a) / DAY);
  };
  /* '1405/07/20' ← ورودی؛ null اگر معتبر نباشد */
  const parse = (s) => {
    const m = String(s || '').replace(/[۰-۹]/g, (c) => String(FA.indexOf(c))).match(/^\s*(\d{4})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*$/);
    if (!m) return null;
    const j = [+m[1], +m[2], +m[3]];
    if (j[1] < 1 || j[1] > 12 || j[2] < 1 || j[2] > daysInMonth(j[0], j[1])) return null;
    return j;
  };

  let pop = null, target = null, view = [0, 0], selected = null;

  function build() {
    if (pop) return pop;
    pop = document.createElement('div');
    pop.className = 'jd-pop';
    pop.style.display = 'none';
    pop.addEventListener('mousedown', (e) => e.preventDefault()); // فوکوس ورودی حفظ شود
    pop.addEventListener('click', onClick);
    document.body.appendChild(pop);
    return pop;
  }

  function render() {
    const [y, m] = view;
    const today = toJ(new Date());
    const offset = (monthStart(y, m).getDay() + 1) % 7;
    let cells = '';
    for (let i = 0; i < offset; i++) cells += '<span></span>';
    for (let d = 1; d <= daysInMonth(y, m); d++) {
      const cls = ['jd-d'];
      if (selected && key(selected) === key([y, m, d])) cls.push('sel');
      if (key(today) === key([y, m, d])) cls.push('today');
      if ((offset + d - 1) % 7 === 6) cls.push('fri');
      cells += `<button type="button" class="${cls.join(' ')}" data-d="${d}">${fa(d)}</button>`;
    }
    const monthOpts = MONTHS.map((n, i) => `<option value="${i + 1}"${i + 1 === m ? ' selected' : ''}>${n}</option>`).join('');
    build().innerHTML = `
      <div class="jd-h">
        <button type="button" class="jd-nav" data-nav="-1" aria-label="ماه قبل">‹</button>
        <div class="jd-t">${MONTHS[m - 1]} ${fa(y)}</div>
        <button type="button" class="jd-nav" data-nav="1" aria-label="ماه بعد">›</button>
      </div>
      <div class="jd-sel">
        <select data-role="month">${monthOpts}</select>
        <input type="number" data-role="year" value="${y}" min="1300" max="1600">
      </div>
      <div class="jd-grid">${WEEK.map((w, i) => `<span class="jd-wd${i === 6 ? ' fri' : ''}">${w}</span>`).join('')}${cells}</div>
      <div class="jd-f">
        <button type="button" data-act="today">امروز</button>
        <button type="button" data-act="clear">پاک کردن</button>
      </div>`;
    pop.querySelector('[data-role="month"]').addEventListener('change', (e) => { view = [view[0], +e.target.value]; render(); });
    pop.querySelector('[data-role="year"]').addEventListener('change', (e) => {
      const yy = parseInt(e.target.value, 10);
      if (yy >= 1300 && yy <= 1600) { view = [yy, view[1]]; render(); }
    });
  }

  function onClick(e) {
    const dayBtn = e.target.closest('[data-d]');
    if (dayBtn) return choose([view[0], view[1], +dayBtn.dataset.d]);
    const nav = e.target.closest('[data-nav]');
    if (nav) {
      let [y, m] = view;
      m += +nav.dataset.nav;
      if (m < 1) { m = 12; y--; }
      if (m > 12) { m = 1; y++; }
      view = [y, m];
      return render();
    }
    const act = e.target.closest('[data-act]');
    if (act) {
      if (act.dataset.act === 'today') return choose(toJ(new Date()));
      if (act.dataset.act === 'clear') return choose(null);
    }
  }

  function choose(j) {
    if (target) {
      target.value = j ? `${j[0]}/${pad(j[1])}/${pad(j[2])}` : '';
      target.dispatchEvent(new Event('input', { bubbles: true }));
      target.dispatchEvent(new Event('change', { bubbles: true }));
    }
    close();
  }

  function open(input) {
    target = input;
    selected = parse(input.value);
    view = selected ? [selected[0], selected[1]] : toJ(new Date()).slice(0, 2);
    render();
    const p = build();
    p.style.display = 'block';
    const r = input.getBoundingClientRect();
    const w = p.offsetWidth || 290;
    let left = r.left + window.scrollX;
    if (left + w > window.scrollX + window.innerWidth - 8) left = window.scrollX + window.innerWidth - w - 8;
    p.style.left = Math.max(8, left) + 'px';
    p.style.top = (r.bottom + window.scrollY + 6) + 'px';
  }

  function close() {
    if (pop) pop.style.display = 'none';
    target = null;
  }

  function attach(input) {
    if (input.dataset.jd) return;
    input.dataset.jd = '1';
    input.classList.add('jd-input');
    input.setAttribute('autocomplete', 'off');
    input.readOnly = true; // تاریخ فقط از تقویم انتخاب می‌شود (تایپ دستی ندارد)
    input.addEventListener('focus', () => open(input));
    input.addEventListener('click', () => open(input));
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
  }

  document.addEventListener('mousedown', (e) => {
    if (!pop || pop.style.display === 'none') return;
    if (pop.contains(e.target) || (target && target.contains(e.target))) return;
    close();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
  window.addEventListener('resize', () => { if (target && pop && pop.style.display !== 'none') open(target); });

  const scan = (root) => {
    (root.querySelectorAll ? root.querySelectorAll('input[data-jdate]') : []).forEach(attach);
  };
  window.JDate = { attach, parse, toJ, nowruz, monthStart, daysInMonth };

  const start = () => {
    scan(document);
    new MutationObserver((list) => {
      list.forEach((rec) => rec.addedNodes.forEach((n) => {
        if (n.nodeType !== 1) return;
        if (n.matches && n.matches('input[data-jdate]')) attach(n);
        scan(n);
      }));
    }).observe(document.body, { childList: true, subtree: true });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
