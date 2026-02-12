-- שאילתא לתורי משתמש (אזור אישי) – לשימוש ב-JetEngine Query Builder
-- כולל: שמות מרפאה/רופא, מיקום, תאריך/שעה מפוצלים, ימים מהיום, טווח שעות (התחלה-סיום)
--
-- העתק את כל השאילתא ל-JetEngine > Query Builder > SQL Query
-- החלף {prefix} ב-macro של JetEngine אם נדרש; השתמש ב-%current_user_id% למשתמש נוכחי

SELECT 
  a.*,
  clinic_post.post_title AS clinic_name,
  doctor_post.post_title AS doctor_name,
  clinic_location_meta.meta_value AS clinic_location,
  DATE_FORMAT(STR_TO_DATE(LEFT(a.appointment_datetime, 10), '%Y-%m-%d'), '%d/%m/%Y') AS appointment_date,
  SUBSTRING(a.appointment_datetime, 12, 5) AS appointment_time,
  DATEDIFF(LEFT(a.appointment_datetime, 10), CURDATE()) AS days_from_today,
  CONCAT(
    SUBSTRING(a.appointment_datetime, 12, 5),
    '-',
    DATE_FORMAT(
      DATE_ADD(
        STR_TO_DATE(CONCAT(LEFT(a.appointment_datetime, 10), ' ', SUBSTRING(a.appointment_datetime, 12, 5)), '%Y-%m-%d %H:%i'),
        INTERVAL a.duration MINUTE
      ),
      '%H:%i'
    )
  ) AS time_range
FROM {prefix}clinic_queue_appointments a
LEFT JOIN {prefix}posts clinic_post ON a.wp_clinic_id = clinic_post.ID
LEFT JOIN {prefix}posts doctor_post ON a.wp_doctor_id = doctor_post.ID
LEFT JOIN {prefix}postmeta clinic_location_meta 
  ON clinic_post.ID = clinic_location_meta.post_id 
  AND clinic_location_meta.meta_key = 'clinic_location'
WHERE a.created_by = %current_user_id%
ORDER BY a.created_at DESC;
