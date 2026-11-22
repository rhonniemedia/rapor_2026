-- Run in the my-apps database after `php artisan migrate`.
-- 1) Expected: 23 rapor_* tables (19 + class_overrides + 3 extracurricular tables).
SELECT COUNT(*) AS rapor_tables FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'rapor\_%';

-- 2) Expected: 8 rows (the core_schools columns that are kept).
SELECT table_name, column_name FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'core_schools'
  AND column_name IN ('nis','nds','education_level','accreditation_status','accreditation_year','operational_permit_decree','operational_permit_date','local_government_logo_path')
ORDER BY column_name;

-- 3) Expected: 0 rows (columns removed again by the revert migration).
SELECT table_name, column_name FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND ((table_name = 'acd_students' AND column_name = 'family_status')
    OR (table_name = 'acd_extracurriculars' AND column_name IN ('description','status')));

-- 4) Expected: supervisor_id -> users, and ONLY the original unique index on (semester_id, name).
SELECT REFERENCED_TABLE_NAME AS supervisor_fk_target FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'acd_extracurriculars' AND CONSTRAINT_NAME = 'acd_extracurriculars_supervisor_id_foreign';
SELECT DISTINCT index_name FROM information_schema.statistics
WHERE table_schema = DATABASE() AND table_name = 'acd_extracurriculars' AND non_unique = 0 AND index_name <> 'PRIMARY';
