/* ابزارهای مشترک: ارتباط با API، اعلان، قالب‌بندی و نمایش فیش */
(function () {
  const token = () => (document.querySelector('meta[name="csrf"]') || {}).content || '';

  window.api = async function (endpoint, action, data = {}) {
    let res;
    try {
      res = await fetch((window.API_BASE ?? '../') + 'api/' + endpoint + '.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token() },
        body: JSON.stringify(Object.assign({ action }, data)),
        credentials: 'same-origin',
      });
    } catch (e) {
      return { ok: false, message: 'اتصال به سرور برقرار نشد. اینترنت یا سرور را بررسی کنید.' };
    }
    let raw = '';
    let json;
    try {
      raw = await res.text();
      json = JSON.parse(raw);
    } catch (e) {
      const preview = String(raw || '').replace(/\s+/g, ' ').trim().slice(0, 260);
      console.error('[API response error]', { endpoint, action, status: res.status, statusText: res.statusText, url: res.url, response: raw, parseError: e });
      return {
        ok: false,
        message: `پاسخ نامعتبر از سرور (HTTP ${res.status}). ${preview ? 'ابتدای پاسخ: ' + preview : 'سرور پاسخ خالی فرستاد.'}`
      };
    }
    if (!res.ok && !json.message) json.message = `خطای سرور (HTTP ${res.status})`;
    if (json.logout) {
      toast(json.message, 'bad');
      setTimeout(() => { location.href = 'index.php'; }, 900);
    }
    return json;
  };

  window.toast = function (msg, type) {
    const el = document.createElement('div');
    el.className = 'toast ' + (type || '');
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.4s'; }, 2600);
    setTimeout(() => el.remove(), 3100);
  };

  const nf = new Intl.NumberFormat('fa-IR');
  /* شماره شبا: IR + ۲ رقم کنترل + ۳ رقم کد بانک + … ؛ کد بانک از همان رقم‌های شبا خوانده می‌شود */
  const SHEBA_BANKS = {
    '010': 'بانک مرکزی', '011': 'بانک صنعت و معدن', '012': 'بانک ملت', '013': 'بانک رفاه کارگران',
    '014': 'بانک مسکن', '015': 'بانک سپه', '016': 'بانک کشاورزی', '017': 'بانک ملی ایران',
    '018': 'بانک تجارت', '019': 'بانک صادرات ایران', '020': 'بانک توسعه صادرات', '021': 'پست بانک ایران',
    '053': 'بانک کارآفرین', '054': 'بانک پارسیان', '055': 'بانک اقتصاد نوین', '056': 'بانک سامان', '057': 'بانک پاسارگاد',
    '058': 'بانک سرمایه', '059': 'بانک سینا', '060': 'بانک مهر اقتصاد', '061': 'بانک شهر',
    '062': 'بانک آینده', '063': 'بانک توسعه تعاون', '064': 'بانک گردشگری'
  };
  const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
  const toEnDigits = (s) => String(s || '').replace(/[۰-۹]/g, (c) => String(FA_DIGITS.indexOf(c))).replace(/[٠-٩]/g, (c) => String(c.charCodeAt(0) - 0x0660)).replace(/\D/g, '');
  window.shebaBankName = function (sheba) {
    const d = toEnDigits(sheba);
    if (d.length < 5) return '';
    const code = d.substr(2, 3);
    return SHEBA_BANKS[code] || ('بانک نامشخص (کد ' + code + ')');
  };
  /* برای کنار گزینه‌ی شبای ثبت‌شده */
  window.shebaBankHint = function (sheba) {
    const name = window.shebaBankName(sheba);
    return name ? ` <span class="hint">(${esc(name)})</span>` : '';
  };
  /* فیلد شبا: فقط رقم، شمارنده‌ی ۲۴ رقم و نمایش بانک بعد از ۵ رقم */
  window.bindSheba = function (label) {
    const input = label.querySelector('input[name=sheba]');
    const bank = label.querySelector('[data-bank]');
    const count = label.querySelector('[data-count]');
    const update = () => {
      const clean = toEnDigits(input.value).slice(0, 24);
      if (clean !== input.value) input.value = clean;
      count.textContent = fnum(clean.length) + ' از ۲۴';
      const name = window.shebaBankName(clean);
      bank.textContent = name || 'بعد از ۵ رقم، نام بانک نمایش داده می‌شود';
      bank.classList.toggle('on', !!name);
    };
    input.addEventListener('input', update);
    update();
  };

  window.money = function (v) {
    const n = Math.round(Number(v) || 0);
    if (Math.abs(n) < 1) return '-';
    return nf.format(n);
  };
  window.fnum = (v) => nf.format(Number(v) || 0);
  window.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  };

  window.setBusy = function (btn, busy, label) {
    if (!btn) return;
    if (busy) { btn.dataset.label = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span>در حال انجام…</span>'; }
    else { btn.disabled = false; btn.innerHTML = label || btn.dataset.label || btn.innerHTML; }
  };

  /* برگه‌ی چاپ: همان طرح جدول شیت اکسل (فقط هنگام چاپ دیده می‌شود) */
  const xl = (n) => new Intl.NumberFormat('en-US').format(Math.round(Number(n) || 0));
  const printSheet = (slip) => {
    const h = slip.header || {};
    const parts = String(slip.period.label || '').trim().split(/\s+/);
    const year = parts.length > 1 ? parts.pop() : '';
    const month = parts.join(' ');
    const rows = Math.max(slip.pay.length, slip.ded.length);
    let body = '';
    for (let i = 0; i < rows; i++) {
      const p = slip.pay[i], d = slip.ded[i];
      body += `<tr><td class="l">${p ? esc(p.label) : ''}</td><td class="n">${p ? xl(p.amount) : ''}</td><td class="l">${d ? esc(d.label) : ''}</td><td class="n">${d ? xl(d.amount) : ''}</td></tr>`;
    }
    const t = slip.totals;
    return `
    <div class="xl-print">
      <table class="xl-head"><tr>
        <td class="xl-logo"><img src="${window.API_BASE || ''}assets/logo-elzoco.png" alt="EL ZOCO"></td>
        <td class="xl-title">صورتحساب پرداخت حقوق</td>
      </tr></table>
      <div class="xl-period"><span>ماه: <b>${esc(month)}</b></span><span>سال: <b>${esc(year)}</b></span></div>
      <table class="xl-grid">
        <tr><th>نام و نام خانوادگی</th><td>${esc(h.name)}</td><th>کد ملی</th><td class="n">${esc(h.national)}</td><th>شماره پرسنلی</th><td class="n">${esc(h.personnel)}</td></tr>
        <tr><th>عنوان پست</th><td>${esc(h.post || '-')}</td><th>ایام کارکرد</th><td class="n">${xl(h.days)}</td><th>شماره حساب (شبا)</th><td class="n">${esc(h.sheba || '-')}</td></tr>
      </table>
      <table class="xl-money">
        <tr><th colspan="2">پرداخت‌ها (ریال)</th><th colspan="2">کسورات (ریال)</th></tr>
        ${body}
        <tr class="sum"><td class="l">جمع حقوق و مزایا</td><td class="n">${xl(t.pay)}</td><td class="l">جمع کسورات</td><td class="n">${xl(t.ded)}</td></tr>
      </table>
      <div class="xl-net"><span>جمع کل خالص پرداختی</span><span class="n">${xl(t.net)}</span><span>ریال</span></div>
    </div>`;
  };

  /* چاپ: برگه‌ی فیش به انتهای صفحه منتقل می‌شود و بقیه‌ی صفحه هنگام چاپ پنهان می‌شود */
  window.prepPrint = function () {
    const src = document.querySelector('#slip .xl-print');
    if (!src) return false;
    document.getElementById('print-root')?.remove();
    const root = document.createElement('div');
    root.id = 'print-root';
    root.innerHTML = src.outerHTML;
    document.body.appendChild(root);
    document.body.classList.add('printing');
    return true;
  };
  window.cleanupPrint = function () {
    document.getElementById('print-root')?.remove();
    document.body.classList.remove('printing');
  };
  window.printSlip = function () {
    if (!window.prepPrint()) { window.toast('فیشی برای چاپ وجود ندارد.', 'bad'); return; }
    window.addEventListener('afterprint', window.cleanupPrint, { once: true });
    window.print();
  };

  /* قالب فیش: همان ترتیب و برچسب‌های شیت «فیش حقوقی» */
  window.renderSlip = function (slip) {
    if (!slip || !slip.has_work) {
      return `<div class="alert warn">${esc((slip && slip.message) || 'کارکرد این ماه ثبت نشده است.')}</div>`;
    }
    const h = slip.header || {};
    const pay = slip.pay.map(r => `<tr><td>${esc(r.label)}</td><td class="amt ${r.amount ? '' : 'zero'}">${money(r.amount)}</td></tr>`).join('');
    const ded = slip.ded.map(r => `<tr><td>${esc(r.label)}</td><td class="amt ${r.amount ? '' : 'zero'}">${money(r.amount)}</td></tr>`).join('');
    const t = slip.totals;
    return `
    <div class="slip">
      <div class="slip-head">
        <div><div class="muted" style="color:#cfe0f5">صورتحساب پرداخت حقوق</div><h2>${esc(slip.period.label)}</h2></div>
        <div class="row no-print"><button class="btn ghost sm" onclick="printSlip()">چاپ فیش</button></div>
      </div>
      <div class="slip-meta">
        <div><small>نام و نام خانوادگی</small><b>${esc(h.name)}</b></div>
        <div><small>کد ملی</small><b class="ltr">${esc(h.national)}</b></div>
        <div><small>کد پرسنلی</small><b class="ltr">${esc(h.personnel)}</b></div>
        <div><small>عنوان پست</small><b>${esc(h.post || '-')}</b></div>
        <div><small>ایام کارکرد</small><b class="ltr">${fnum(h.days)}</b></div>
        <div><small>شماره شبا</small><b class="ltr" style="font-size:.85rem">${esc(h.sheba || '-')}</b></div>
      </div>
      <div class="slip-cols">
        <div class="slip-col pay"><table><tr><th colspan="2">پرداخت‌ها (ریال)</th></tr>${pay}</table></div>
        <div class="slip-col ded"><table><tr><th colspan="2">کسورات (ریال)</th></tr>${ded}</table></div>
      </div>
      <div class="slip-tot">
        <div class="p"><span>جمع حقوق و مزایا</span><span class="ltr">${money(t.pay)}</span></div>
        <div class="d"><span>جمع کسورات</span><span class="ltr">${money(t.ded)}</span></div>
      </div>
      <div class="slip-net"><span>خالص پرداختی</span><span class="amt">${money(t.net)} ریال</span></div>
      <div class="slip-note">مانده وام در جمع کسورات محاسبه نشده است؛ فقط برای اطلاع نمایش داده می‌شود.</div>
    </div>
    ${printSheet(slip)}`;
  };
})();
