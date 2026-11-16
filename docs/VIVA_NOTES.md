# BloodBank – Viva Notes (simple answers you can say aloud)

## 1. One-minute introduction
"BloodBank is a web-based blood management system. A person who needs blood sends a request with a medical document.
An admin checks it and approves or rejects it. Matching donors are asked to help, and when blood is really given the admin
completes the request and records the donation. There are three panels: Admin, Donor and Requester.
I used HTML, CSS, JavaScript, Bootstrap, core PHP with PDO and MySQL on XAMPP. The system never decides if a donor is medically fit."

## 2. Database (11 tables, 3 views, 2 triggers, 1 stored procedure, 18 foreign keys)
| Table | Purpose |
|---|---|
| users | one login table for all roles (`role` column) |
| donors | donor profile; **one-to-one** with users |
| blood_groups, districts | lookup tables (no repeated text) |
| blood_requests | the requests, with status and urgency |
| medical_documents | uploaded files, **one-to-many** from a request |
| donor_responses | **many-to-many** between requests and donors (junction table) |
| donations | every blood donation recorded |
| request_status_history | audit trail of every status change |
| notifications | messages inside the site |
| admin_logs | what admins did (viewed a document, changed a status…) |

**Relationships:** one-to-one `users`–`donors` · one-to-many `users`→`blood_requests`, `blood_requests`→`medical_documents`
· many-to-many `blood_requests`–`donors` through `donor_responses` with `UNIQUE(request_id, donor_id)`.

**Normalization (to 3NF):**
- 1NF: one value per column; documents live in their own table, not `doc1, doc2, doc3`.
- 2NF: no column depends on only part of a key (the junction table has its own key).
- 3NF: a donor's name/email/phone are stored once in `users`; group and district names once in lookup tables.

**On purpose, not a mistake:** `requester_name` and `contact_phone` are copied into each request, because the person to call
may not be the account owner (an admin can enter a request for a walk-in). Users are **deactivated, never deleted**, so
history never breaks (`ON DELETE RESTRICT`).

**Trigger** `trg_donation_update_last_date`: when a row is inserted into `donations`, the donor's `last_donation_date` updates by itself.
**Views:** `v_request_overview` (joined request list), `v_donor_directory` (donors without phone/email), `v_blood_group_summary` (donors available vs bags needed per group; the Reports page reads it).
**Trigger** `trg_donation_before_insert`: refuses a donation dated in the future.
**Stored procedure** `sp_matching_donors(request_id)`: lists donors who match a request (same group and district, available, not yet asked).
**Indexes:** `idx_donor_search (blood_group_id, district_id, is_available)` makes Find Donors and matching fast.
**Transactions:** creating a request, answering as a donor, changing a status and completing a request each run in one
transaction. `SELECT … FOR UPDATE` locks the request row so two people cannot change it at the same moment.

## 3. SQL concepts → where to show them
All in `queries_demo.sql` (all verified to run): CREATE/INSERT/UPDATE/DELETE (queries 1-4) · WHERE + ORDER BY (5) · 3-table JOIN (6)
· GROUP BY + COUNT (7) · HAVING (8) · SUM/AVG/MAX (9) · LEFT JOIN (10) · subqueries NOT IN and scalar (11) · many-to-many (12)
· audit trail (13) · view (14) · trigger (15). The Reports page uses GROUP BY, AVG, subqueries and LEFT JOIN on live data.

## 4. Request workflow – the rules to remember
`Pending → Under Review → Approved → Donor Matched → Completed`, plus `Rejected` / `Cancelled`.
- Donor accepts → **Donor Matched**. Never Completed. **Only the admin completes**, after blood was given, and must record bags (1-5) and a date.
- Last accepted donor withdraws → back to **Approved**, requester is told.
- Requester may edit only while **Pending**. Requester can cancel until Completed. Admin cancels from Approved / Donor Matched; early requests are **rejected** with a written reason.
- All the allowed steps live in one function: `allowed_next_statuses()` in `includes/admin_requests.php`.

## 5. Security – what, and in which file
| Topic | What we did | File |
|---|---|---|
| Passwords | `password_hash()` / `password_verify()` (bcrypt); same message for wrong email and wrong password | `register.php`, `login.php` |
| SQL injection | PDO prepared statements, real (not emulated) prepares. The few `query()` calls have no user input | everywhere |
| XSS | every printed value goes through `e()` (`htmlspecialchars`) | `includes/functions.php` |
| CSRF | hidden token in every POST form, checked on the server | `csrf_field()`, `verify_csrf()` |
| Sessions | HttpOnly + SameSite cookie, new session id at login, private pages `no-store` | `includes/auth.php` |
| Roles | `require_role()` at the top of each protected page; the database `is_active` flag is checked on **every** request, so a deactivated user is logged out at the next click | `includes/auth.php` |
| Login protection | 5 wrong passwords → wait 60 seconds | `login.php` |
| Uploads | PDF/JPG/PNG only, 5 MB, 3 files; real type read with `finfo`; images checked with `getimagesize`; random stored name; hostile names cleaned; folder locked by `.htaccess` | `includes/request_fields.php`, `uploads/.htaccess` |
| Medical documents | opened only through `document.php`: admin any (and it is logged), requester own, donor/visitor never; "not yours" and "does not exist" give the **same** answer | `document.php` |
| Privacy | Find Donors hides phone numbers; donor sees patient name and contact only after accepting | `find_donors.php`, `donor/requests.php` |
| Medical safety | no "eligible / not eligible" anywhere: only reminders and flags for the admin | `donor/requests.php`, `admin/request_view.php` |
| Audit | `request_status_history` and `admin_logs` | `includes/admin_requests.php` |

## 6. Decisions I can defend
- **One account, one role.** Simple to build and explain. A donor who also needs blood registers a second account.
- **Pending → Approved directly only for Emergency requests.** Normal requests must be reviewed first (matches the required workflow). The blueprint allowed any direct approval; I chose the stricter rule.
- **Exact blood group + same district** for matching. Simple and explainable; compatible-group matching is a future upgrade.
- **No blood-stock table.** BloodBank matches donors to requests; it is not a blood-bank inventory.
- **Admin enters walk-in requests under the admin account** (or any requester's account); the contact fields hold the real person.
- **ENUM for status/role/urgency** (simple); a lookup table would be purer but harder for a beginner.
- **Bootstrap saved locally** so the demo works without internet.
- Files differ slightly from the first blueprint: merged `match_donors` into `request_view.php`, one `document.php` instead of `view_documents.php`, one `menus.php` instead of three sidebar files.

## 7. Questions teachers may ask
**Why PDO instead of mysqli?** Cleaner prepared statements with named/positional parameters, works with more databases, throws exceptions.
**What is a prepared statement?** The SQL and the data are sent separately, so typed text can never become SQL.
**Why hash and not encrypt passwords?** Hashing is one-way; even the admin cannot read a password. Encryption can be reversed.
**What stops one user opening another user's request?** Every query includes the owner (`requester_id = logged-in user`); a wrong id gives "not found".
**What if two donors accept at once?** The request row is locked inside a transaction; the second answer waits and sees the new status.
**Why is Completed admin-only?** A donor saying yes is a promise, not blood received. The admin confirms and records the real donation.
**Why store history in a separate table?** To keep a full audit trail and to show a timeline without losing old statuses.
**What happens to a deactivated user's data?** Kept. Only login is blocked.
**What would you add next?** Email/SMS alerts, compatible-group matching, forgot-password, IP-based login limits, blood-stock inventory, a map.

## 8. Be honest about the limits
In-site notifications only · exact group match only · login lock is per browser session · no forgot-password page ·
only donors can change their own password · documents are checked by a human, not by the program.
