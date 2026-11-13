-- ترمیم یک‌باره‌ی اتصال درخواست‌های یتیم (اجرا پیش از بارگذاری مجدد اکسل)
-- ۱) نسخه‌ی پشتیبان
CREATE TABLE IF NOT EXISTS advance_requests_backup AS SELECT * FROM advance_requests;
CREATE TABLE IF NOT EXISTS leave_requests_backup AS SELECT * FROM leave_requests;
CREATE TABLE IF NOT EXISTS promotion_requests_backup AS SELECT * FROM promotion_requests;

-- ۲) اتصال بر اساس کد ملی (اولویت با پرونده‌ی جاری/فعال)
UPDATE advance_requests a SET a.employee_id = (
  SELECT e.id FROM employees e WHERE e.national_code = a.national_code
  ORDER BY e.in_latest DESC, e.inactive ASC, e.id DESC LIMIT 1)
WHERE a.employee_id NOT IN (SELECT id FROM employees)
  AND a.national_code NOT IN ('', '0000000000')
  AND EXISTS (SELECT 1 FROM employees e2 WHERE e2.national_code = a.national_code);

UPDATE leave_requests a SET a.employee_id = (
  SELECT e.id FROM employees e WHERE e.national_code = a.national_code
  ORDER BY e.in_latest DESC, e.inactive ASC, e.id DESC LIMIT 1)
WHERE a.employee_id NOT IN (SELECT id FROM employees)
  AND a.national_code NOT IN ('', '0000000000')
  AND EXISTS (SELECT 1 FROM employees e2 WHERE e2.national_code = a.national_code);

UPDATE promotion_requests a SET a.employee_id = (
  SELECT e.id FROM employees e WHERE e.national_code = a.national_code
  ORDER BY e.in_latest DESC, e.inactive ASC, e.id DESC LIMIT 1)
WHERE a.employee_id NOT IN (SELECT id FROM employees)
  AND a.national_code NOT IN ('', '0000000000')
  AND EXISTS (SELECT 1 FROM employees e2 WHERE e2.national_code = a.national_code);

-- ۳) درخواست‌های هنوز یتیم (برای اتصال دستی از پنل: دکمه «اتصال به کارمند»)
SELECT id, tracking, name, national_code, personnel_code FROM advance_requests
WHERE employee_id NOT IN (SELECT id FROM employees);
