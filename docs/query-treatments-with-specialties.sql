-- טיפולים + התמחויות – לשימוש ב-JetEngine Query Builder
-- עמודות: treatment_id, treatment_name, specialty_id, specialty_name, unified_name (התמחות | טיפול)
-- מקורות שיוך: specialty_id או specialty_ids (מערך מסודר PHP). החלף {prefix} אם נדרש.

SELECT
  t.term_id   AS treatment_id,
  t.name      AS treatment_name,
  s.term_id   AS specialty_id,
  s.name      AS specialty_name,
  CONCAT(IFNULL(CONCAT(s.name, ' | '), ''), t.name) AS unified_name
FROM {prefix}terms t
INNER JOIN {prefix}term_taxonomy tt
  ON tt.term_id = t.term_id AND tt.taxonomy = 'treatment_types'
LEFT JOIN {prefix}termmeta tm
  ON tm.term_id = t.term_id AND tm.meta_key = 'specialty_id'
LEFT JOIN {prefix}termmeta tm2
  ON tm2.term_id = t.term_id AND tm2.meta_key = 'specialty_ids'
LEFT JOIN {prefix}term_taxonomy stax
  ON stax.taxonomy = 'specialties'
  AND stax.term_id = COALESCE(
    NULLIF(CAST(tm.meta_value AS UNSIGNED), 0),
    NULLIF(CAST(TRIM(TRAILING '}' FROM SUBSTRING_INDEX(SUBSTRING_INDEX(tm2.meta_value, 'i:0;i:', -1), ';', 1)) AS UNSIGNED), 0)
  )
LEFT JOIN {prefix}terms s ON s.term_id = stax.term_id
ORDER BY (s.term_id IS NULL), s.name ASC, t.name ASC;
