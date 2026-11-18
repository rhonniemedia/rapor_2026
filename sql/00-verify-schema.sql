-- Run in the my-apps database after `php artisan migrate`. Expected: 19 tables and 11 columns.
SELECT COUNT(*) AS rapor_tables FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'rapor\_%';

SELECT table_name, column_name FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND ((table_name = 'core_schools' AND column_name IN ('nis','nds','education_level','accreditation_status','accreditation_year','operational_permit_decree','operational_permit_date','local_government_logo_path'))
    OR (table_name = 'acd_students' AND column_name = 'family_status')
    OR (table_name = 'acd_extracurriculars' AND column_name IN ('description','status')))
ORDER BY table_name, column_name;
