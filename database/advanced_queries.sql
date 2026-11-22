-- =====================================================================
--  BloodBank - ADVANCED SQL demo queries (for the viva / final demo)
--  Run them one by one in phpMyAdmin > SQL tab (click `bloodbank` first).
--  Covers: ALTER, LIKE, MIN, RIGHT JOIN, correlated subquery, EXISTS,
--          views, stored procedure, trigger, transaction, index (EXPLAIN).
--  Run the beginner queries first: database/queries_demo.sql
-- =====================================================================
USE bloodbank;

-- ---------- A. DDL: ALTER TABLE (add / change / remove a column) ------
CREATE TABLE IF NOT EXISTS demo_hospitals (
    hospital_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL
);
ALTER TABLE demo_hospitals ADD COLUMN phone VARCHAR(20) NULL AFTER name;          -- add
ALTER TABLE demo_hospitals MODIFY COLUMN name VARCHAR(150) NOT NULL;               -- change type/size
ALTER TABLE demo_hospitals ADD UNIQUE INDEX uq_demo_hospital_name (name);          -- add a rule
DESCRIBE demo_hospitals;
ALTER TABLE demo_hospitals DROP COLUMN phone;                                      -- remove
DROP TABLE demo_hospitals;                                                         -- DDL: DROP

-- ---------- B. LIKE (pattern search) ----------------------------------
-- Donors whose name starts with "A", or whose area contains "pur"
SELECT u.full_name, dn.area
FROM   donors dn JOIN users u ON u.user_id = dn.user_id
WHERE  u.full_name LIKE 'A%' OR dn.area LIKE '%pur%';

-- ---------- C. MIN / MAX with GROUP BY --------------------------------
-- Oldest and newest request in each status
SELECT status, COUNT(*) AS total, MIN(created_at) AS first_request, MAX(created_at) AS last_request
FROM   blood_requests
GROUP BY status;

-- ---------- D. RIGHT JOIN ---------------------------------------------
-- Every blood group, even a group that has no donor yet (shows 0)
SELECT bg.group_name, COUNT(dn.user_id) AS donors
FROM   donors dn
RIGHT JOIN blood_groups bg ON bg.blood_group_id = dn.blood_group_id
GROUP BY bg.blood_group_id, bg.group_name
ORDER BY bg.blood_group_id;

-- ---------- E. CORRELATED SUBQUERY ------------------------------------
-- (the inner query uses a value of the outer row, so it runs once per outer row)
-- (a) Each donor with their own last donation date
SELECT u.full_name,
       (SELECT MAX(dt.donation_date) FROM donations dt WHERE dt.donor_id = dn.user_id) AS last_donated
FROM   donors dn JOIN users u ON u.user_id = dn.user_id;

-- (b) Requests that asked for more bags than the average of THEIR OWN district
SELECT r.request_id, r.patient_name, r.district_id, r.bags_required
FROM   blood_requests r
WHERE  r.bags_required > (SELECT AVG(r2.bags_required)
                          FROM   blood_requests r2
                          WHERE  r2.district_id = r.district_id);

-- ---------- F. EXISTS / NOT EXISTS ------------------------------------
-- Donors who have accepted at least one request
SELECT u.full_name
FROM   users u
WHERE  EXISTS (SELECT 1 FROM donor_responses dr
               WHERE dr.donor_id = u.user_id AND dr.response_status = 'Accepted');

-- Donors who were never asked for any request
SELECT u.full_name
FROM   users u
WHERE  u.role = 'donor'
  AND  NOT EXISTS (SELECT 1 FROM donor_responses dr WHERE dr.donor_id = u.user_id);

-- ---------- G. Derived table (subquery in FROM) + HAVING --------------
-- Districts that have MORE requests than the average district
SELECT t.district_name, t.n
FROM  (SELECT di.district_name, COUNT(*) AS n
       FROM   blood_requests r JOIN districts di ON di.district_id = r.district_id
       GROUP BY di.district_id, di.district_name) AS t
WHERE t.n > (SELECT COUNT(*) / COUNT(DISTINCT district_id) FROM blood_requests);

-- ---------- H. VIEWS ---------------------------------------------------
SELECT * FROM v_blood_group_summary;                                  -- supply and demand per group
SELECT * FROM v_donor_directory WHERE is_available = 1 LIMIT 5;       -- no phone / email shown
SELECT * FROM v_request_overview WHERE urgency = 'Emergency';

-- Groups where open demand is higher than the donors available (a "shortage")
SELECT group_name, bags_needed, donors_available
FROM   v_blood_group_summary
WHERE  bags_needed > donors_available;

-- ---------- I. STORED PROCEDURE ---------------------------------------
-- Donors who match request #2 (same blood group + district, available, not yet asked)
CALL sp_matching_donors(2);

-- ---------- J. TRIGGERS ------------------------------------------------
-- 1) trg_donation_before_insert refuses a donation dated in the future (an error is expected):
INSERT INTO donations (request_id, donor_id, bags_donated, donation_date, recorded_by, notes)
VALUES (3, 4, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 1, 'Future date - must fail');
-- 2) trg_donation_update_last_date: see demo 15 in database/queries_demo.sql

-- ---------- K. TRANSACTION (all or nothing) ----------------------------
START TRANSACTION;
    UPDATE blood_requests SET status = 'Rejected', admin_remark = 'Demo' WHERE request_id = 3;
    SELECT request_id, status FROM blood_requests WHERE request_id = 3;   -- changed inside the transaction
ROLLBACK;                                                                  -- ...undo everything
SELECT request_id, status FROM blood_requests WHERE request_id = 3;       -- back to the old status

-- ---------- L. INDEX: how MySQL finds rows -----------------------------
-- "key" in the result shows idx_donor_search is used (search by group + district + availability)
EXPLAIN SELECT user_id FROM donors WHERE blood_group_id = 1 AND district_id = 1 AND is_available = 1;
SHOW INDEX FROM donors;
