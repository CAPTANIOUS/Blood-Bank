-- =====================================================================
--  BloodBank  -  Blood Management System
--  Database creation script + sample data
--  Works on: XAMPP (MariaDB 10.4+) / MySQL 5.7+ / MySQL 8
--
--  HOW TO USE (phpMyAdmin):
--    1. Open http://localhost/phpmyadmin
--    2. Click the "Import" tab  ->  choose this file  ->  click "Go"
--    (This script creates the database itself. Running it again will
--     DELETE and re-create the `bloodbank` database - good for resetting.)
--
--  Sample login passwords (for testing only):
--    admin@bloodbank.com   ->  Admin@123
--    every donor           ->  Donor@123
--    every requester       ->  Requester@123
-- =====================================================================

DROP DATABASE IF EXISTS bloodbank;
CREATE DATABASE bloodbank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bloodbank;

SET NAMES utf8mb4;

-- =====================================================================
--  1. LOOKUP TABLES  (small fixed lists - avoids typing the same text
--     again and again, which is what normalization is about)
-- =====================================================================

CREATE TABLE blood_groups (
    blood_group_id  TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_name      VARCHAR(3)       NOT NULL,
    PRIMARY KEY (blood_group_id),
    UNIQUE KEY uq_blood_group_name (group_name)
) ENGINE=InnoDB;

CREATE TABLE districts (
    district_id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    district_name   VARCHAR(50)       NOT NULL,
    PRIMARY KEY (district_id),
    UNIQUE KEY uq_district_name (district_name)
) ENGINE=InnoDB;

-- =====================================================================
--  2. USERS  (ONE table for every login: admin, donor, requester)
-- =====================================================================

CREATE TABLE users (
    user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,              -- result of password_hash(), never plain text
    phone          VARCHAR(20)  NOT NULL,
    role           ENUM('admin','donor','requester') NOT NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,    -- 0 = deactivated by admin (cannot login)
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),                 -- no duplicate accounts
    UNIQUE KEY uq_users_phone (phone),
    KEY idx_users_role (role)
) ENGINE=InnoDB;

-- =====================================================================
--  3. DONORS  (extra details only for users whose role = 'donor')
--     ONE-TO-ONE with users: donors.user_id is both PK and FK.
--     Name / email / phone live in `users` only (no duplicate data).
-- =====================================================================

CREATE TABLE donors (
    user_id             INT UNSIGNED      NOT NULL,
    date_of_birth       DATE              NOT NULL,
    gender              ENUM('Male','Female','Other') NOT NULL,
    blood_group_id      TINYINT UNSIGNED  NOT NULL,
    address             VARCHAR(255)      NOT NULL,
    district_id         SMALLINT UNSIGNED NOT NULL,
    area                VARCHAR(100)      NOT NULL,
    last_donation_date  DATE              NULL,        -- NULL = never donated
    medical_issues      TEXT              NULL,        -- self-declared, for admin review only
    is_available        TINYINT(1)        NOT NULL DEFAULT 1,
    created_at          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    -- index used by "Find Donors" search (blood group + district + availability)
    KEY idx_donor_search (blood_group_id, district_id, is_available),
    KEY idx_donor_area (area),
    CONSTRAINT fk_donor_user     FOREIGN KEY (user_id)        REFERENCES users (user_id)               ON DELETE CASCADE,
    CONSTRAINT fk_donor_group    FOREIGN KEY (blood_group_id) REFERENCES blood_groups (blood_group_id) ON DELETE RESTRICT,
    CONSTRAINT fk_donor_district FOREIGN KEY (district_id)    REFERENCES districts (district_id)       ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================================
--  4. BLOOD REQUESTS
--     ONE user (requester/admin) -> MANY requests  (one-to-many)
-- =====================================================================

CREATE TABLE blood_requests (
    request_id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    requester_id      INT UNSIGNED      NOT NULL,      -- the account that submitted the request
    patient_name      VARCHAR(100)      NOT NULL,
    requester_name    VARCHAR(100)      NOT NULL,      -- contact person (may differ from account name)
    contact_phone     VARCHAR(20)       NOT NULL,
    blood_group_id    TINYINT UNSIGNED  NOT NULL,
    bags_required     TINYINT UNSIGNED  NOT NULL,
    medical_condition VARCHAR(255)      NOT NULL,
    hospital_name     VARCHAR(150)      NOT NULL,
    hospital_address  VARCHAR(255)      NOT NULL,
    district_id       SMALLINT UNSIGNED NOT NULL,
    area              VARCHAR(100)      NOT NULL,
    urgency           ENUM('Normal','Emergency') NOT NULL DEFAULT 'Normal',
    required_date     DATE              NOT NULL,
    required_time     TIME              NULL,          -- used when urgency = 'Normal'
    additional_info   TEXT              NULL,
    status            ENUM('Pending','Under Review','Approved','Donor Matched',
                           'Completed','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    admin_remark      VARCHAR(255)      NULL,          -- e.g. reason for rejection
    reviewed_by       INT UNSIGNED      NULL,          -- admin who approved / rejected
    reviewed_at       DATETIME          NULL,
    completed_at      DATETIME          NULL,
    created_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    KEY idx_req_status (status),
    KEY idx_req_match (blood_group_id, district_id),
    KEY idx_req_requester (requester_id),
    KEY idx_req_required_date (required_date),
    CONSTRAINT chk_req_bags CHECK (bags_required BETWEEN 1 AND 20),
    CONSTRAINT fk_req_requester FOREIGN KEY (requester_id)   REFERENCES users (user_id)               ON DELETE RESTRICT,
    CONSTRAINT fk_req_group     FOREIGN KEY (blood_group_id) REFERENCES blood_groups (blood_group_id) ON DELETE RESTRICT,
    CONSTRAINT fk_req_district  FOREIGN KEY (district_id)    REFERENCES districts (district_id)       ON DELETE RESTRICT,
    CONSTRAINT fk_req_reviewer  FOREIGN KEY (reviewed_by)    REFERENCES users (user_id)               ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  5. MEDICAL DOCUMENTS  (one request -> many uploaded files)
--     Only the SAFE random file name is stored on disk.
-- =====================================================================

CREATE TABLE medical_documents (
    document_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id     INT UNSIGNED NOT NULL,
    original_name  VARCHAR(255) NOT NULL,              -- shown to the user
    stored_name    VARCHAR(255) NOT NULL,              -- random name saved in uploads/
    file_ext       VARCHAR(5)   NOT NULL,              -- pdf, jpg, jpeg, png
    file_size      INT UNSIGNED NOT NULL,              -- bytes
    uploaded_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (document_id),
    UNIQUE KEY uq_doc_stored_name (stored_name),
    KEY idx_doc_request (request_id),
    CONSTRAINT fk_doc_request FOREIGN KEY (request_id) REFERENCES blood_requests (request_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  6. DONOR RESPONSES   (MANY-TO-MANY: requests <-> donors)
--     One request can be offered to many donors, one donor can
--     respond to many requests.  This is the junction table.
-- =====================================================================

CREATE TABLE donor_responses (
    response_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id       INT UNSIGNED NOT NULL,
    donor_id         INT UNSIGNED NOT NULL,
    response_status  ENUM('Pending','Accepted','Declined') NOT NULL DEFAULT 'Pending',
    assigned_by      INT UNSIGNED NULL,                -- admin who matched; NULL = donor volunteered
    donor_note       VARCHAR(255) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at     DATETIME     NULL,
    PRIMARY KEY (response_id),
    UNIQUE KEY uq_request_donor (request_id, donor_id), -- a donor can't be matched twice to one request
    KEY idx_resp_donor (donor_id, response_status),
    CONSTRAINT fk_resp_request  FOREIGN KEY (request_id)  REFERENCES blood_requests (request_id) ON DELETE CASCADE,
    CONSTRAINT fk_resp_donor    FOREIGN KEY (donor_id)    REFERENCES donors (user_id)            ON DELETE CASCADE,
    CONSTRAINT fk_resp_assigner FOREIGN KEY (assigned_by) REFERENCES users (user_id)             ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  7. DONATIONS   (record of blood that was REALLY given; entered by admin)
--     request_id can be NULL for a walk-in / past donation.
-- =====================================================================

CREATE TABLE donations (
    donation_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id     INT UNSIGNED NULL,
    donor_id       INT UNSIGNED NOT NULL,
    bags_donated   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    donation_date  DATE         NOT NULL,
    recorded_by    INT UNSIGNED NULL,
    notes          VARCHAR(255) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (donation_id),
    KEY idx_don_donor_date (donor_id, donation_date),
    KEY idx_don_request (request_id),
    CONSTRAINT chk_don_bags CHECK (bags_donated BETWEEN 1 AND 5),
    CONSTRAINT fk_don_request  FOREIGN KEY (request_id)  REFERENCES blood_requests (request_id) ON DELETE SET NULL,
    CONSTRAINT fk_don_donor    FOREIGN KEY (donor_id)    REFERENCES donors (user_id)            ON DELETE RESTRICT,
    CONSTRAINT fk_don_recorder FOREIGN KEY (recorded_by) REFERENCES users (user_id)             ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  8. REQUEST STATUS HISTORY  (audit trail of every status change)
-- =====================================================================

CREATE TABLE request_status_history (
    history_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id   INT UNSIGNED NOT NULL,
    old_status   VARCHAR(20)  NULL,                    -- NULL for the very first entry
    new_status   VARCHAR(20)  NOT NULL,
    changed_by   INT UNSIGNED NULL,
    remark       VARCHAR(255) NULL,
    changed_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_hist_request (request_id, changed_at),
    CONSTRAINT fk_hist_request FOREIGN KEY (request_id) REFERENCES blood_requests (request_id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_user    FOREIGN KEY (changed_by) REFERENCES users (user_id)             ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  9. NOTIFICATIONS  (messages shown inside each user's dashboard)
-- =====================================================================

CREATE TABLE notifications (
    notification_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    title            VARCHAR(120) NOT NULL,
    message          VARCHAR(255) NOT NULL,
    link             VARCHAR(255) NULL,
    is_read          TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notif_user (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  10. ADMIN LOGS  (who did what - useful for the viva & security)
-- =====================================================================

CREATE TABLE admin_logs (
    log_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    INT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    table_name  VARCHAR(50)  NULL,
    record_id   INT UNSIGNED NULL,
    details     VARCHAR(255) NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_log_admin (admin_id, created_at),
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  TRIGGER: when a donation is recorded, automatically update the
--  donor's "last_donation_date" (keeps two tables consistent).
-- =====================================================================

DELIMITER $$
CREATE TRIGGER trg_donation_update_last_date
AFTER INSERT ON donations
FOR EACH ROW
BEGIN
    UPDATE donors
       SET last_donation_date = NEW.donation_date
     WHERE user_id = NEW.donor_id
       AND (last_donation_date IS NULL OR last_donation_date < NEW.donation_date);
END$$
DELIMITER ;

-- =====================================================================
--  VIEW: ready-made joined list of requests (saves long JOIN queries)
-- =====================================================================

CREATE VIEW v_request_overview AS
SELECT  r.request_id, r.patient_name, bg.group_name AS blood_group,
        r.bags_required, r.urgency, r.status, r.required_date,
        r.hospital_name, d.district_name, r.area,
        u.full_name AS requester_account, r.created_at
FROM    blood_requests r
JOIN    blood_groups   bg ON bg.blood_group_id = r.blood_group_id
JOIN    districts      d  ON d.district_id     = r.district_id
JOIN    users          u  ON u.user_id         = r.requester_id;

-- =====================================================================
--  TRIGGER 2: stop a donation that is dated in the future.
--  (A CHECK rule cannot use CURDATE(), so a BEFORE INSERT trigger does it.)
-- =====================================================================

DELIMITER $$
CREATE TRIGGER trg_donation_before_insert
BEFORE INSERT ON donations
FOR EACH ROW
BEGIN
    IF NEW.donation_date > CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A donation cannot be dated in the future.';
    END IF;
END$$
DELIMITER ;

-- =====================================================================
--  VIEW 2: donor directory - donor details WITHOUT the private phone/email.
-- =====================================================================

CREATE VIEW v_donor_directory AS
SELECT  u.user_id, u.full_name, bg.group_name AS blood_group,
        di.district_name, d.area, d.is_available, d.last_donation_date
FROM    donors d
JOIN    users         u  ON u.user_id         = d.user_id
JOIN    blood_groups  bg ON bg.blood_group_id = d.blood_group_id
JOIN    districts     di ON di.district_id    = d.district_id
WHERE   u.is_active = 1;

-- =====================================================================
--  VIEW 3: per blood group - donors available and bags still needed.
--  Uses CORRELATED subqueries (each one looks at the current group row).
-- =====================================================================

CREATE VIEW v_blood_group_summary AS
SELECT  bg.group_name,
        (SELECT COUNT(*) FROM donors d JOIN users u ON u.user_id = d.user_id
          WHERE d.blood_group_id = bg.blood_group_id
            AND d.is_available = 1 AND u.is_active = 1)            AS donors_available,
        (SELECT COALESCE(SUM(r.bags_required), 0) FROM blood_requests r
          WHERE r.blood_group_id = bg.blood_group_id
            AND r.status IN ('Pending','Under Review','Approved','Donor Matched')) AS bags_needed
FROM    blood_groups bg;

-- =====================================================================
--  STORED PROCEDURE: donors who could help one request
--  (same blood group, same district, available, active, not yet asked).
--  Use it with:   CALL sp_matching_donors(3);
-- =====================================================================

DELIMITER $$
CREATE PROCEDURE sp_matching_donors(IN p_request_id INT UNSIGNED)
BEGIN
    SELECT u.user_id, u.full_name, u.phone, d.area, d.last_donation_date
    FROM   blood_requests r
    JOIN   donors d ON d.blood_group_id = r.blood_group_id
                   AND d.district_id    = r.district_id
    JOIN   users  u ON u.user_id = d.user_id
    WHERE  r.request_id = p_request_id
      AND  d.is_available = 1
      AND  u.is_active    = 1
      AND  NOT EXISTS (SELECT 1 FROM donor_responses dr
                       WHERE dr.request_id = r.request_id AND dr.donor_id = d.user_id)
    ORDER BY d.last_donation_date IS NULL DESC, d.last_donation_date ASC;
END$$
DELIMITER ;

-- =====================================================================
--  SEED DATA: fixed lists
-- =====================================================================

INSERT INTO blood_groups (group_name) VALUES
('A+'),('A-'),('B+'),('B-'),('AB+'),('AB-'),('O+'),('O-');

INSERT INTO districts (district_name) VALUES
('Bagerhat'),('Bandarban'),('Barguna'),('Barishal'),('Bhola'),('Bogura'),('Brahmanbaria'),
('Chandpur'),('Chapainawabganj'),('Chattogram'),('Chuadanga'),('Cox''s Bazar'),('Cumilla'),('Dhaka'),('Dinajpur'),
('Faridpur'),('Feni'),('Gaibandha'),('Gazipur'),('Gopalganj'),('Habiganj'),('Jamalpur'),
('Jashore'),('Jhalokathi'),('Jhenaidah'),('Joypurhat'),('Khagrachhari'),('Khulna'),
('Kishoreganj'),('Kurigram'),('Kushtia'),('Lakshmipur'),('Lalmonirhat'),('Madaripur'),
('Magura'),('Manikganj'),('Meherpur'),('Moulvibazar'),('Munshiganj'),('Mymensingh'),
('Naogaon'),('Narail'),('Narayanganj'),('Narsingdi'),('Natore'),('Netrokona'),('Nilphamari'),
('Noakhali'),('Pabna'),('Panchagarh'),('Patuakhali'),('Pirojpur'),('Rajbari'),('Rajshahi'),
('Rangamati'),('Rangpur'),('Satkhira'),('Shariatpur'),('Sherpur'),('Sirajganj'),('Sunamganj'),
('Sylhet'),('Tangail'),('Thakurgaon');

-- =====================================================================
--  SEED DATA: sample users  (password hashes created with PHP password_hash)
-- =====================================================================

INSERT INTO users (user_id, full_name, email, password_hash, phone, role, created_at) VALUES
(1,  'BloodBank Admin',  'admin@bloodbank.com',     '$2y$10$BygR4aem.RyFZgBWipIVU.5/r2PsWJ0CTjAD5jxxvUaSM5QZXUh4K', '01700000001', 'admin',     '2026-10-01 09:00:00'),
(2,  'Rahim Uddin',      'rahim@example.com',       '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000002', 'donor',     '2026-10-02 10:00:00'),
(3,  'Karima Akter',     'karima@example.com',      '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000003', 'donor',     '2026-10-02 11:00:00'),
(4,  'Sabbir Hossain',   'sabbir@example.com',      '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000004', 'donor',     '2026-10-03 09:30:00'),
(5,  'Nusrat Jahan',     'nusrat@example.com',      '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000005', 'donor',     '2026-10-03 14:00:00'),
(6,  'Tanvir Ahmed',     'tanvir@example.com',      '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000006', 'donor',     '2026-10-04 16:00:00'),
(7,  'Mahmud Hasan',     'mahmud@example.com',      '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000007', 'donor',     '2026-10-05 12:00:00'),
(8,  'Imran Hossain',    'imran@example.com',       '$2y$10$Or8/EATLwq9slQdrXS0OkOEf/1aRrV1AUpeeVDdzegTY/WzLwxHZy', '01711000008', 'donor',     '2026-10-06 18:00:00'),
(9,  'Farhana Islam',    'farhana@example.com',     '$2y$10$nlwf3pteqYN6KZ5hzAOEPOYYPbihk0Y2Nj5IWXly7SeEvYJ6IGsZy', '01811000009', 'requester', '2026-10-07 10:00:00'),
(10, 'Jahid Karim',      'jahid@example.com',       '$2y$10$nlwf3pteqYN6KZ5hzAOEPOYYPbihk0Y2Nj5IWXly7SeEvYJ6IGsZy', '01811000010', 'requester', '2026-10-08 10:00:00'),
(11, 'Shirin Sultana',   'shirin@example.com',      '$2y$10$nlwf3pteqYN6KZ5hzAOEPOYYPbihk0Y2Nj5IWXly7SeEvYJ6IGsZy', '01811000011', 'requester', '2026-10-09 10:00:00');

-- Blood group ids: 1=A+ 2=A- 3=B+ 4=B- 5=AB+ 6=AB- 7=O+ 8=O-
-- Districts are looked up by name using a sub-query (so we never guess an id).

INSERT INTO donors (user_id, date_of_birth, gender, blood_group_id, address, district_id, area, medical_issues, is_available) VALUES
(2, '1998-03-14', 'Male',   1, 'House 12, Road 5, Mirpur-10',       (SELECT district_id FROM districts WHERE district_name='Dhaka'),      'Mirpur',     NULL,                1),
(3, '1996-07-22', 'Female', 7, 'Flat 4B, Green Road, Dhanmondi',    (SELECT district_id FROM districts WHERE district_name='Dhaka'),      'Dhanmondi',  NULL,                1),
(4, '1995-11-05', 'Male',   3, '45 Agrabad Commercial Area',        (SELECT district_id FROM districts WHERE district_name='Chattogram'), 'Agrabad',    NULL,                1),
(5, '2000-01-30', 'Female', 8, 'Sector 7, Uttara',                  (SELECT district_id FROM districts WHERE district_name='Dhaka'),      'Uttara',     NULL,                1),
(6, '1993-09-18', 'Male',   1, 'Zindabazar Main Road',              (SELECT district_id FROM districts WHERE district_name='Sylhet'),     'Zindabazar', NULL,                1),
(7, '1999-05-09', 'Male',   5, 'Boalia, Near Rajshahi College',     (SELECT district_id FROM districts WHERE district_name='Rajshahi'),   'Boalia',     'Mild asthma',       0),
(8, '1997-12-12', 'Male',   3, 'Panchlaish Housing Society',        (SELECT district_id FROM districts WHERE district_name='Chattogram'), 'Panchlaish', NULL,                1);

-- =====================================================================
--  SEED DATA: sample blood requests (one for every status)
-- =====================================================================

INSERT INTO blood_requests
(request_id, requester_id, patient_name, requester_name, contact_phone, blood_group_id, bags_required,
 medical_condition, hospital_name, hospital_address, district_id, area, urgency, required_date, required_time,
 additional_info, status, admin_remark, reviewed_by, reviewed_at, completed_at, created_at)
VALUES
(1, 9,  'Abdul Karim',  'Farhana Islam', '01811000009', 7, 2, 'Heart bypass surgery',
 'Central Medical College Hospital', 'Shahbag, Dhaka',
 (SELECT district_id FROM districts WHERE district_name='Dhaka'), 'Shahbag', 'Emergency', '2026-11-11', NULL,
 'Surgery is scheduled for tomorrow morning.', 'Donor Matched', 'Document verified.', 1, '2026-11-10 09:15:00', NULL, '2026-11-10 08:30:00'),

(2, 9,  'Ayesha Begum', 'Farhana Islam', '01811000009', 1, 1, 'Severe anemia',
 'City Care Hospital', 'Panthapath, Dhaka',
 (SELECT district_id FROM districts WHERE district_name='Dhaka'), 'Panthapath', 'Normal', '2026-11-15', '10:00:00',
 NULL, 'Pending', NULL, NULL, NULL, NULL, '2026-11-10 11:00:00'),

(3, 10, 'Rafiq Mia',    'Jahid Karim',   '01811000010', 3, 3, 'Thalassemia (regular transfusion)',
 'Port City Medical Hospital', 'Panchlaish, Chattogram',
 (SELECT district_id FROM districts WHERE district_name='Chattogram'), 'Panchlaish', 'Normal', '2026-11-18', '09:00:00',
 'Patient needs 3 bags over two days.', 'Approved', 'Approved. Searching donors.', 1, '2026-11-09 15:00:00', NULL, '2026-11-09 12:00:00'),

(4, 10, 'Salma Khatun',  'Jahid Karim',  '01811000010', 8, 1, 'Road accident, blood loss',
 'Uttara General Hospital', 'Sector 9, Uttara, Dhaka',
 (SELECT district_id FROM districts WHERE district_name='Dhaka'), 'Uttara', 'Emergency', '2026-11-02', NULL,
 NULL, 'Completed', 'Blood received and confirmed.', 1, '2026-11-01 20:30:00', '2026-11-02 09:00:00', '2026-11-01 20:00:00'),

(5, 11, 'Kamal Hossain', 'Shirin Sultana', '01811000011', 5, 2, 'Cancer treatment',
 'Rajshahi Medical Hospital', 'Boalia, Rajshahi',
 (SELECT district_id FROM districts WHERE district_name='Rajshahi'), 'Boalia', 'Normal', '2026-11-20', '14:00:00',
 NULL, 'Rejected', 'Uploaded document is unclear. Please submit a new request with a clear prescription.', 1, '2026-11-08 17:00:00', NULL, '2026-11-08 10:00:00'),

(6, 11, 'Rina Das',      'Shirin Sultana', '01811000011', 1, 1, 'Dengue fever, low platelets',
 'Sylhet Central Hospital', 'Zindabazar, Sylhet',
 (SELECT district_id FROM districts WHERE district_name='Sylhet'), 'Zindabazar', 'Emergency', '2026-11-11', NULL,
 NULL, 'Under Review', NULL, NULL, NULL, NULL, '2026-11-10 07:45:00'),

(7, 9,  'Mizanur Rahman', 'Farhana Islam', '01811000009', 4, 1, 'Planned minor surgery',
 'City Care Hospital', 'Panthapath, Dhaka',
 (SELECT district_id FROM districts WHERE district_name='Dhaka'), 'Panthapath', 'Normal', '2026-11-25', '11:00:00',
 'Surgery postponed, blood no longer needed.', 'Cancelled', NULL, NULL, NULL, NULL, '2026-11-05 09:00:00');

-- =====================================================================
--  SEED DATA: donor responses (matching)
-- =====================================================================

INSERT INTO donor_responses (request_id, donor_id, response_status, assigned_by, donor_note, created_at, responded_at) VALUES
(1, 3, 'Accepted', 1, 'I can come to the hospital this evening.', '2026-11-10 09:30:00', '2026-11-10 10:05:00'),
(3, 4, 'Pending',  1, NULL,                                       '2026-11-10 08:00:00', NULL),
(3, 8, 'Declined', 1, 'Sorry, I am travelling that week.',        '2026-11-10 08:00:00', '2026-11-10 08:40:00'),
(4, 5, 'Accepted', 1, 'On my way.',                               '2026-11-01 20:45:00', '2026-11-01 21:00:00');

-- =====================================================================
--  SEED DATA: donations  (trigger updates donors.last_donation_date)
-- =====================================================================

INSERT INTO donations (request_id, donor_id, bags_donated, donation_date, recorded_by, notes) VALUES
(NULL, 6, 1, '2026-03-10', 1, 'Walk-in donation (past record)'),
(NULL, 3, 1, '2026-06-15', 1, 'Walk-in donation (past record)'),
(NULL, 2, 1, '2026-08-20', 1, 'Blood donation camp'),
(4,    5, 1, '2026-11-02', 1, 'Donated for request #4');

-- =====================================================================
--  SEED DATA: status history, notifications, admin logs
-- =====================================================================

INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remark, changed_at) VALUES
(1, NULL,           'Pending',       9, 'Request submitted',               '2026-11-10 08:30:00'),
(1, 'Pending',      'Under Review',  1, 'Checking medical document',       '2026-11-10 09:00:00'),
(1, 'Under Review', 'Approved',      1, 'Document verified',               '2026-11-10 09:15:00'),
(1, 'Approved',     'Donor Matched', 1, 'Donor accepted',                  '2026-11-10 10:05:00'),
(2, NULL,           'Pending',       9, 'Request submitted',               '2026-11-10 11:00:00'),
(3, NULL,           'Pending',      10, 'Request submitted',               '2026-11-09 12:00:00'),
(3, 'Pending',      'Approved',      1, 'Approved. Searching donors.',     '2026-11-09 15:00:00'),
(4, NULL,           'Pending',      10, 'Request submitted',               '2026-11-01 20:00:00'),
(4, 'Pending',      'Approved',      1, 'Emergency - approved quickly',    '2026-11-01 20:30:00'),
(4, 'Approved',     'Donor Matched', 1, 'Donor accepted',                  '2026-11-01 21:00:00'),
(4, 'Donor Matched','Completed',     1, 'Blood received and confirmed.',   '2026-11-02 09:00:00'),
(5, NULL,           'Pending',      11, 'Request submitted',               '2026-11-08 10:00:00'),
(5, 'Pending',      'Rejected',      1, 'Document unclear',                '2026-11-08 17:00:00'),
(6, NULL,           'Pending',      11, 'Request submitted',               '2026-11-10 07:45:00'),
(6, 'Pending',      'Under Review',  1, 'Checking document',               '2026-11-10 08:10:00'),
(7, NULL,           'Pending',       9, 'Request submitted',               '2026-11-05 09:00:00'),
(7, 'Pending',      'Cancelled',     9, 'Cancelled by requester',          '2026-11-06 09:00:00');

INSERT INTO notifications (user_id, title, message, link, is_read) VALUES
(3,  'New donation request',   'A patient needs O+ blood in Dhaka. Please respond.',   'donor/requests.php',        0),
(4,  'New donation request',   'A patient needs B+ blood in Chattogram.',              'donor/requests.php',        0),
(9,  'Donor matched',          'A donor accepted your request #1.',                    'requester/my_requests.php', 0),
(10, 'Request completed',      'Your request #4 has been marked as completed.',        'requester/my_requests.php', 1),
(11, 'Request rejected',       'Your request #5 was rejected. See the admin remark.',  'requester/my_requests.php', 0);

INSERT INTO admin_logs (admin_id, action, table_name, record_id, details, ip_address) VALUES
(1, 'Approved request',  'blood_requests', 3, 'Status changed to Approved', '127.0.0.1'),
(1, 'Rejected request',  'blood_requests', 5, 'Document unclear',           '127.0.0.1'),
(1, 'Matched donor',     'donor_responses', 1, 'Donor #3 matched to request #1', '127.0.0.1'),
(1, 'Completed request', 'blood_requests', 4, 'Blood received',             '127.0.0.1');

-- =====================================================================
--  END OF FILE
-- =====================================================================
