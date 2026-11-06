-- افزودن «شیفت فعلی کارمند» به درخواست ارتقا
-- یک‌بار اجرا شود (مثلاً در phpMyAdmin). اگر ستون از قبل وجود داشت، خطای «Duplicate column» طبیعی است.
ALTER TABLE promotion_requests
  ADD COLUMN current_shift VARCHAR(30) NOT NULL DEFAULT '' AFTER job_requested;
