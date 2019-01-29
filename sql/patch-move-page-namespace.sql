-- change the number of the Schema namespace from 12300 to 640 (T213726)

UPDATE /*_*/page
SET page_namespace = 640 + (page_namespace - 12300)
WHERE page_namespace IN (12300, 12301);

UPDATE /*_*/archive
SET ar_namespace = 640 + (ar_namespace - 12300)
WHERE ar_namespace IN (12300, 12301);

UPDATE /*_*/pagelinks
SET pl_namespace = 640 + (pl_namespace - 12300)
WHERE pl_namespace IN (12300, 12301);
UPDATE /*_*/pagelinks
SET pl_from_namespace = 640 + (pl_from_namespace - 12300)
WHERE pl_from_namespace IN (12300, 12301);

UPDATE /*_*/templatelinks
SET tl_namespace = 640 + (tl_namespace - 12300)
WHERE tl_namespace IN (12300, 12301);
UPDATE /*_*/templatelinks
SET tl_from_namespace = 640 + (tl_from_namespace - 12300)
WHERE tl_from_namespace IN (12300, 12301);

UPDATE /*_*/imagelinks
SET il_from_namespace = 640 + (il_from_namespace - 12300)
WHERE il_from_namespace IN (12300, 12301);

UPDATE /*_*/recentchanges
SET rc_namespace = 640 + (rc_namespace - 12300)
WHERE rc_namespace IN (12300, 12301);

UPDATE /*_*/watchlist
SET wl_namespace = 640 + (wl_namespace - 12300)
WHERE wl_namespace IN (12300, 12301);

UPDATE /*_*/querycache
SET qc_namespace = 640 + (qc_namespace - 12300)
WHERE qc_namespace IN (12300, 12301);

UPDATE /*_*/logging
SET log_namespace = 640 + (log_namespace - 12300)
WHERE log_namespace IN (12300, 12301);

UPDATE /*_*/job
SET job_namespace = 640 + (job_namespace - 12300)
WHERE job_namespace IN (12300, 12301);

UPDATE /*_*/redirect
SET rd_namespace = 640 + (rd_namespace - 12300)
WHERE rd_namespace IN (12300, 12301);

UPDATE /*_*/querycachetwo
SET qcc_namespace = 640 + (qcc_namespace - 12300)
WHERE qcc_namespace IN (12300, 12301);
UPDATE /*_*/querycachetwo
SET qcc_namespacetwo = 640 + (qcc_namespacetwo - 12300)
WHERE qcc_namespacetwo IN (12300, 12301);

UPDATE /*_*/protected_titles
SET pt_namespace = 640 + (pt_namespace - 12300)
WHERE pt_namespace IN (12300, 12301);
