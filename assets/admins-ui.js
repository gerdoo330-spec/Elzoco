/* نمایش کارتی حساب‌های مدیریت. بعد از admin.js بارگذاری می‌شود و تابع loadAdmins را بازنویسی می‌کند. */
(function () {
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const ROLE = { admin: 'ادمین', senior: 'مدیر ارشد', manager: 'مدیر مستقیم' };
  const ROLE_CLS = { admin: 'b-info', senior: 'b-warn', manager: 'b-gray' };

  function card(a) {
    const active = Number(a.active) === 1;
    return `<article class="adm ${active ? '' : 'is-off'}" style="--d:${a.id % 8}">
      <div class="adm-top">
        <div class="adm-av">${esc(String(a.name || '؟').trim().charAt(0))}</div>
        <div class="adm-id"><b>${esc(a.name)}</b><span class="ltr muted">@${esc(a.username)}</span></div>
        <span class="badge ${ROLE_CLS[a.role] || 'b-gray'} adm-role">${esc(ROLE[a.role] || a.role)}</span>
      </div>
      <div class="adm-meta">
        <div><small>کد پرسنلی</small><span class="ltr">${esc(a.personnel_code || '—')}</span></div>
        <div><small>وضعیت</small><span class="badge ${active ? 'b-ok' : 'b-gray'}">${active ? 'فعال' : 'غیرفعال'}</span></div>
      </div>
      <footer class="adm-a">
        <button class="btn sm ghost" type="button" onclick="editAdmin(${a.id})"><span class="bi"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></span><span class="bt">ویرایش</span></button>
        <button class="btn sm ${active ? 'danger ghost' : ''}" type="button" onclick="toggleAdmin(${a.id})"><span class="bi"><svg viewBox="0 0 24 24" aria-hidden="true">${active ? '<path d="M18 6L6 18M6 6l12 12"/>' : '<path d="M20 6L9 17l-5-5"/>'}</svg></span><span class="bt">${active ? 'غیرفعال کردن' : 'فعال کردن'}</span></button>
      </footer>
    </article>`;
  }

  window.loadAdmins = async function () {
    if (!window.IS_ADMIN) return;
    const r = await window.api('admin', 'admins');
    if (!r.ok) return window.toast ? window.toast(r.message, 'bad') : alert(r.message);
    window._admins = r.admins;
    const box = document.getElementById('adminList');
    box.innerHTML = r.admins.length
      ? `<div class="adm-grid">${r.admins.map(card).join('')}</div>`
      : '<div class="rq-empty">هنوز حسابی ثبت نشده است.</div>';
  };
})();
