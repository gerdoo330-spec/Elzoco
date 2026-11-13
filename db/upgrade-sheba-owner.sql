-- اختیاری: برنامه خودش این ستون‌ها را هنگام اولین استفاده می‌سازد (fin_schedule_ensure).
ALTER TABLE employees ADD COLUMN sheba_owner VARCHAR(190) NOT NULL DEFAULT '' AFTER sheba;
ALTER TABLE advance_requests ADD COLUMN sheba_owner VARCHAR(190) NOT NULL DEFAULT '' AFTER sheba;
