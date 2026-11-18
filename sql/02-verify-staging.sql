-- Verification: the difference column must be 0 on every row.
SELECT table_name, source_rows, staging_rows, source_rows - staging_rows AS difference FROM (
SELECT 'catatan_wali_kelas' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`catatan_wali_kelas`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_catatan_wali_kelas`) AS staging_rows
UNION ALL
SELECT 'data_sekolahs' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`data_sekolahs`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_data_sekolahs`) AS staging_rows
UNION ALL
SELECT 'ekskul_pelajars' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`ekskul_pelajars`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_ekskul_pelajars`) AS staging_rows
UNION ALL
SELECT 'ekstrakurikulers' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`ekstrakurikulers`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_ekstrakurikulers`) AS staging_rows
UNION ALL
SELECT 'jurusans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`jurusans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_jurusans`) AS staging_rows
UNION ALL
SELECT 'jurusan_mata_pelajarans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`jurusan_mata_pelajarans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_jurusan_mata_pelajarans`) AS staging_rows
UNION ALL
SELECT 'kehadirans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`kehadirans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_kehadirans`) AS staging_rows
UNION ALL
SELECT 'kelas' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`kelas`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_kelas`) AS staging_rows
UNION ALL
SELECT 'kokurikulers' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`kokurikulers`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_kokurikulers`) AS staging_rows
UNION ALL
SELECT 'kurikulums' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`kurikulums`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_kurikulums`) AS staging_rows
UNION ALL
SELECT 'kurikulum_mata_pelajarans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`kurikulum_mata_pelajarans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_kurikulum_mata_pelajarans`) AS staging_rows
UNION ALL
SELECT 'mata_pelajarans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`mata_pelajarans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_mata_pelajarans`) AS staging_rows
UNION ALL
SELECT 'mata_pelajaran_kelompoks' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`mata_pelajaran_kelompoks`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_mata_pelajaran_kelompoks`) AS staging_rows
UNION ALL
SELECT 'model_has_permissions' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`model_has_permissions`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_model_has_permissions`) AS staging_rows
UNION ALL
SELECT 'model_has_roles' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`model_has_roles`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_model_has_roles`) AS staging_rows
UNION ALL
SELECT 'nilais' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`nilais`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_nilais`) AS staging_rows
UNION ALL
SELECT 'orang_tua_walis' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`orang_tua_walis`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_orang_tua_walis`) AS staging_rows
UNION ALL
SELECT 'pelajars' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`pelajars`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_pelajars`) AS staging_rows
UNION ALL
SELECT 'pengaturans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`pengaturans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_pengaturans`) AS staging_rows
UNION ALL
SELECT 'permissions' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`permissions`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_permissions`) AS staging_rows
UNION ALL
SELECT 'roles' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`roles`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_roles`) AS staging_rows
UNION ALL
SELECT 'role_has_permissions' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`role_has_permissions`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_role_has_permissions`) AS staging_rows
UNION ALL
SELECT 'rombels' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`rombels`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_rombels`) AS staging_rows
UNION ALL
SELECT 'rombel_pelajars' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`rombel_pelajars`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_rombel_pelajars`) AS staging_rows
UNION ALL
SELECT 'rombel_pengajars' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`rombel_pengajars`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_rombel_pengajars`) AS staging_rows
UNION ALL
SELECT 'sekolahs' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`sekolahs`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_sekolahs`) AS staging_rows
UNION ALL
SELECT 'semesters' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`semesters`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_semesters`) AS staging_rows
UNION ALL
SELECT 'tahun_ajarans' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`tahun_ajarans`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_tahun_ajarans`) AS staging_rows
UNION ALL
SELECT 'tahun_ajaran_kurikulums' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`tahun_ajaran_kurikulums`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_tahun_ajaran_kurikulums`) AS staging_rows
UNION ALL
SELECT 'tahun_ajaran_semesters' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`tahun_ajaran_semesters`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_tahun_ajaran_semesters`) AS staging_rows
UNION ALL
SELECT 'template_ekstrakurikuler_deskripsis' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`template_ekstrakurikuler_deskripsis`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_template_ekstrakurikuler_deskripsis`) AS staging_rows
UNION ALL
SELECT 'template_kokurikuler_capaians' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`template_kokurikuler_capaians`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_template_kokurikuler_capaians`) AS staging_rows
UNION ALL
SELECT 'template_nilai_capaians' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`template_nilai_capaians`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_template_nilai_capaians`) AS staging_rows
UNION ALL
SELECT 'users' AS table_name, (SELECT COUNT(*) FROM `rapor_smt1`.`users`) AS source_rows, (SELECT COUNT(*) FROM `my-apps`.`old_users`) AS staging_rows
) x ORDER BY table_name;
