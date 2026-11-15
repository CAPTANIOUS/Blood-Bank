-- =====================================================================
--  BloodBank - DBMS concept demo queries (for the viva)
--  Run them one by one in phpMyAdmin > SQL tab (select `bloodbank` first)
-- =====================================================================
USE bloodbank;

-- ---------- 1. CREATE (DDL) ------------------------------------------
-- Example: a small extra table created only for the demo
CREATE TABLE IF NOT EXISTS demo_feedback (
    feedback_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    comment     VARCHAR(200) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- 2. INSERT -------------------------------------------------
INSERT INTO demo_feedback (user_id, comment) VALUES (9, 'Great platform!');

-- ---------- 3. UPDATE -------------------------------------------------
UPDATE demo_feedback SET comment = 'Great and fast platform!' WHERE user_id = 9;

-- ---------- 4. DELETE -------------------------------------------------
DELETE FROM demo_feedback WHERE user_id = 9;
DROP TABLE demo_feedback;

-- ---------- 5. SELECT + WHERE + ORDER BY ------------------------------
-- All emergency requests that are still waiting, newest first
SELECT request_id, patient_name, status, required_date
FROM   blood_requests
WHERE  urgency = 'Emergency' AND status IN ('Pending','Under Review')
ORDER BY created_at DESC;

-- ---------- 6. JOIN (3 tables) ----------------------------------------
-- Available donors with their blood group and district
SELECT u.full_name, bg.group_name, d.district_name, dn.area, u.phone
FROM   donors dn
JOIN   users u         ON u.user_id        = dn.user_id
JOIN   blood_groups bg ON bg.blood_group_id = dn.blood_group_id
JOIN   districts d     ON d.district_id    = dn.district_id
WHERE  dn.is_available = 1 AND u.is_active = 1
ORDER BY bg.group_name, d.district_name;

-- ---------- 7. GROUP BY + aggregate (COUNT) ---------------------------
-- How many donors for each blood group?
SELECT bg.group_name, COUNT(*) AS total_donors
FROM   donors dn
JOIN   blood_groups bg ON bg.blood_group_id = dn.blood_group_id
GROUP BY bg.group_name
ORDER BY total_donors DESC;

-- ---------- 8. GROUP BY + HAVING --------------------------------------
-- Blood groups that have more than one donor
SELECT bg.group_name, COUNT(*) AS total_donors
FROM   donors dn
JOIN   blood_groups bg ON bg.blood_group_id = dn.blood_group_id
GROUP BY bg.group_name
HAVING COUNT(*) > 1;

-- ---------- 9. Aggregate functions: SUM, AVG, MAX, MIN ----------------
SELECT COUNT(*)            AS total_requests,
       SUM(bags_required)  AS total_bags_requested,
       AVG(bags_required)  AS avg_bags_per_request,
       MAX(bags_required)  AS biggest_request
FROM   blood_requests;

-- Requests per status (for the admin dashboard cards)
SELECT status, COUNT(*) AS total
FROM   blood_requests
GROUP BY status;

-- ---------- 10. LEFT JOIN ---------------------------------------------
-- Every donor and how many times they have donated (0 if never)
SELECT u.full_name, COUNT(dt.donation_id) AS times_donated, MAX(dt.donation_date) AS last_donated
FROM   donors dn
JOIN   users u ON u.user_id = dn.user_id
LEFT JOIN donations dt ON dt.donor_id = dn.user_id
GROUP BY u.user_id, u.full_name;

-- ---------- 11. SUBQUERIES --------------------------------------------
-- (a) Donors who have NEVER donated  (NOT IN subquery)
SELECT u.full_name, u.phone
FROM   users u
WHERE  u.role = 'donor'
  AND  u.user_id NOT IN (SELECT donor_id FROM donations);

-- (b) Requests asking for MORE bags than the average (scalar subquery)
SELECT request_id, patient_name, bags_required
FROM   blood_requests
WHERE  bags_required > (SELECT AVG(bags_required) FROM blood_requests);

-- (c) Matching donors for request #3: same blood group + same district + available
SELECT u.full_name, u.phone, dn.area
FROM   donors dn
JOIN   users u ON u.user_id = dn.user_id
WHERE  dn.is_available = 1
  AND  dn.blood_group_id = (SELECT blood_group_id FROM blood_requests WHERE request_id = 3)
  AND  dn.district_id    = (SELECT district_id    FROM blood_requests WHERE request_id = 3)
  AND  dn.user_id NOT IN (SELECT donor_id FROM donor_responses WHERE request_id = 3);

-- ---------- 12. Many-to-many: requests <-> donors ---------------------
SELECT r.request_id, r.patient_name, u.full_name AS donor, dr.response_status
FROM   donor_responses dr
JOIN   blood_requests r ON r.request_id = dr.request_id
JOIN   users u          ON u.user_id    = dr.donor_id
ORDER BY r.request_id;

-- ---------- 13. Status history of one request (audit trail) -----------
SELECT h.changed_at, h.old_status, h.new_status, u.full_name AS changed_by, h.remark
FROM   request_status_history h
LEFT JOIN users u ON u.user_id = h.changed_by
WHERE  h.request_id = 1
ORDER BY h.changed_at;

-- ---------- 14. Using the VIEW ----------------------------------------
SELECT * FROM v_request_overview WHERE status = 'Approved';

-- ---------- 15. Trigger demo ------------------------------------------
-- Before:
SELECT last_donation_date FROM donors WHERE user_id = 4;
-- Record a donation -> trigger updates donors.last_donation_date automatically
INSERT INTO donations (request_id, donor_id, bags_donated, donation_date, recorded_by, notes)
VALUES (3, 4, 1, CURDATE(), 1, 'Trigger demo');
-- After:
SELECT last_donation_date FROM donors WHERE user_id = 4;

-- =====================================================================
--  MORE DEMO QUERIES (added in the upgrade round)
-- =====================================================================

-- ---------- 16. ALTER TABLE (DDL) -------------------------------------
CREATE TABLE IF NOT EXISTS demo_camp (
    camp_id   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    camp_name VARCHAR(100) NOT NULL
);
ALTER TABLE demo_camp ADD COLUMN camp_date DATE NULL;                 -- add a column
ALTER TABLE demo_camp MODIFY camp_name VARCHAR(150) NOT NULL;         -- change a column
ALTER TABLE demo_camp ADD INDEX idx_camp_date (camp_date);            -- add an index
DESCRIBE demo_camp;
DROP TABLE demo_camp;

-- ---------- 17. RIGHT JOIN --------------------------------------------
-- Every blood group, even those that no request has asked for (NULL = none)
SELECT bg.group_name, COUNT(r.request_id) AS requests
FROM   blood_requests r
RIGHT JOIN blood_groups bg ON bg.blood_group_id = r.blood_group_id
GROUP BY bg.blood_group_id, bg.group_name
ORDER BY requests DESC, bg.group_name;

-- ---------- 18. CORRELATED subquery -----------------------------------
-- Requests that need more bags than the average of THEIR OWN blood group.
-- (The inner query uses r.blood_group_id from the outer row.)
SELECT r.request_id, r.patient_name, r.bags_required
FROM   blood_requests r
WHERE  r.bags_required > (SELECT AVG(x.bags_required)
                          FROM   blood_requests x
                          WHERE  x.blood_group_id = r.blood_group_id);

-- ---------- 19. EXISTS / NOT EXISTS -----------------------------------
-- Donors who have accepted at least one request
SELECT u.full_name
FROM   users u
WHERE  EXISTS (SELECT 1 FROM donor_responses dr
               WHERE dr.donor_id = u.user_id AND dr.response_status = 'Accepted');

-- ---------- 20. The two extra VIEWS -----------------------------------
SELECT * FROM v_donor_directory WHERE blood_group = 'B+';
SELECT * FROM v_blood_group_summary ORDER BY bags_needed DESC;

-- ---------- 21. STORED PROCEDURE --------------------------------------
-- Donors that match request #2 and have not been asked yet
CALL sp_matching_donors(2);

-- ---------- 22. Trigger 2 (should FAIL with the message) --------------
-- Expected: ERROR 1644 "A donation cannot be dated in the future."
INSERT INTO donations (request_id, donor_id, bags_donated, donation_date, recorded_by, notes)
VALUES (3, 4, 1, CURDATE() + INTERVAL 2 DAY, 1, 'Future date test');

-- ---------- 23. TRANSACTION (all or nothing) --------------------------
START TRANSACTION;
UPDATE blood_requests SET status = 'Cancelled' WHERE request_id = 1;
SELECT request_id, status FROM blood_requests WHERE request_id = 1;   -- changed inside the transaction
ROLLBACK;                                                             -- undo it
SELECT request_id, status FROM blood_requests WHERE request_id = 1;   -- back to the old value

-- ---------- 24. EXPLAIN: does a query use an index? -------------------
EXPLAIN SELECT * FROM donations WHERE donor_id = 4 AND donation_date >= '2026-01-01';
