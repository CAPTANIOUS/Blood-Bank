# BloodBank – Team Contribution

Three members, three parts. **Every file has exactly one owner**, so nobody edits the same file and Git never shows conflicts. Member 1 (team leader) carries the technically hardest part.

| | Member 1 | Member 2 | Member 3 |
|---|---|---|---|
| **Name** | ________ | ________ | ________ |
| **GitHub username** | ________ | ________ | ________ |
| **Part** | Core, database, security and workflow engine | Donor module and donor administration | Requester module, admin request screens, design and reports |
| **Files** | 43 | 20 | 15 |
| **Lines of code** | 3370 (43%) | 2296 (29%) | 2212 (28%) |
| **Pull Requests** | 7 | 7 | 7 |
| **Commits planned** | 26 | 17 | 16 |

---

## Member 1 – Core, database, security and workflow engine

**You must be able to explain:**
- The ER design, keys, one-to-one / one-to-many / many-to-many, and why it is in 3NF (`docs/DATABASE_DESIGN.md`).
- How `bloodbank.sql` is built: constraints, indexes, 3 views, 2 triggers, the stored procedure.
- Login: `password_hash` / `password_verify`, session regeneration, the 5-attempt lock, the idle time-out.
- `require_role()`, CSRF tokens and `e()`: which attack each one stops.
- Transactions and `SELECT ... FOR UPDATE` in `donor_actions.php` and `admin_requests.php`; the workflow rules in `allowed_next_statuses()`.
- Secure upload: real file type, random name, locked folder, `document.php`.
- The advanced SQL file (A–L) and how to run it in the demo.

| Day | Branch | What it adds |
|---|---|---|
| 1 | `feature/database-and-config` | Create the repository, the database and the settings |
| 2 | `feature/core-security` | Sessions, role guards and helper functions |
| 3 | `feature/layout-and-auth-pages` | Page layout, Bootstrap, registration and login |
| 4 | `feature/public-pages` | Home page and public pages |
| 5 | `feature/workflow-engine` | The workflow engine: validation, donors, admin steps, documents |
| 6 | `feature/advanced-sql-and-security` | Upgrades: advanced SQL, database notes, idle time-out, password bar, home numbers |
| 7 | `docs/final-readme` | Final README, team page, tag v1.1 and the final check |

**Files:**

- `.gitignore`
- `.htaccess`
- `404.php` (24 lines)
- `README.md`
- `about.php` (50 lines)
- `assets/css/bootstrap.min.css`
- `assets/js/bootstrap.bundle.min.js`
- `assets/js/main.js` (140 lines)
- `become_donor.php` (63 lines)
- `config/.htaccess`
- `config/config.php` (40 lines)
- `config/database.php` (36 lines)
- `database/.htaccess`
- `database/advanced_queries.sql` (107 lines)
- `database/bloodbank.sql` (500 lines)
- `database/queries_demo.sql` (186 lines)
- `docs/DATABASE_DESIGN.md`
- `docs/GITHUB_GUIDE.md`
- `docs/SQL_REQUIREMENTS_MAP.md`
- `docs/TEAM_CONTRIBUTION.md`
- `document.php` (78 lines)
- `includes/.htaccess`
- `includes/admin_requests.php` (227 lines)
- `includes/auth.php` (141 lines)
- `includes/dash_footer.php` (5 lines)
- `includes/dash_header.php` (85 lines)
- `includes/donor_actions.php` (183 lines)
- `includes/footer.php` (42 lines)
- `includes/form_options.php` (25 lines)
- `includes/functions.php` (125 lines)
- `includes/head.php` (33 lines)
- `includes/header.php` (28 lines)
- `includes/menus.php` (36 lines)
- `includes/navbar.php` (63 lines)
- `includes/request_fields.php` (385 lines)
- `includes/scripts.php` (15 lines)
- `index.php` (152 lines)
- `login.php` (124 lines)
- `logout.php` (16 lines)
- `register.php` (390 lines)
- `request_blood.php` (71 lines)
- `uploads/.htaccess`
- `uploads/medical_documents/.gitkeep`

---

## Member 2 – Donor module and donor administration

**You must be able to explain:**
- Find Donors: filters, paging, why phone numbers are hidden, safe `LIKE` search.
- Donor validation rules and the donor profile (UPDATE, password hash).
- How a donor answers a request, and why it never completes a request.
- Why accounts are deactivated, not deleted; the donation trigger in action.
- The CSV export: same filters, admin log, protection against Excel formulas.
- The ER diagram and the relational schema: read any table aloud.

| Day | Branch | What it adds |
|---|---|---|
| 1 | `docs/viva-and-presentation` | Get ready, first small contribution: viva notes and presentation outline |
| 2 | `docs/er-diagram-and-schema` | ER diagram and relational schema (needed for the proposal) |
| 3 | `feature/find-donors` | Donor rules and the public Find Donors search |
| 4 | `feature/donor-dashboard-profile` | Donor dashboard and profile |
| 5 | `feature/donor-requests-and-history` | Matching requests, notifications and donation history |
| 6 | `feature/admin-donor-management` | Admin: donors, users and donation records |
| 7 | `feature/donor-export-and-history` | Upgrades: CSV export, donation history cards, viva notes |

**Files:**

- `admin/donations.php` (208 lines)
- `admin/donor_edit.php` (240 lines)
- `admin/donor_status.php` (66 lines)
- `admin/donor_view.php` (167 lines)
- `admin/export_donors.php` (84 lines)
- `admin/manage_donors.php` (247 lines)
- `admin/manage_users.php` (183 lines)
- `docs/ER_DIAGRAM.md`
- `docs/PRESENTATION_OUTLINE.md`
- `docs/RELATIONAL_SCHEMA.md`
- `docs/VIVA_NOTES.md`
- `docs/er_diagram.png`
- `donor/dashboard.php` (132 lines)
- `donor/donation_history.php` (60 lines)
- `donor/notifications.php` (4 lines)
- `donor/profile.php` (350 lines)
- `donor/requests.php` (170 lines)
- `find_donors.php` (195 lines)
- `includes/donor_fields.php` (83 lines)
- `includes/notifications_page.php` (107 lines)

---

## Member 3 – Requester module, admin request screens, design and reports

**You must be able to explain:**
- The request form, validation and the create / edit transaction.
- The requester pages and why nobody can see another person's request.
- The admin request list and review page; the full workflow in the demo.
- The reports page: each block is one SQL query (GROUP BY, SUM, AVG, subqueries, the view).
- The CSS theme and print rules.
- The test checklist: how you tested every role.

| Day | Branch | What it adds |
|---|---|---|
| 1 | `docs/test-checklist` | Get ready, first small contribution: checklist and guide |
| 2 | `feature/theme-css` | The BloodBank theme (design) |
| 3 | `feature/request-form` | The blood request form |
| 4 | `feature/requester-pages` | Requester dashboard, my requests and request details |
| 5 | `feature/admin-requests` | Admin: request list, review page and Add a request |
| 6 | `feature/admin-dashboard-reports` | Admin dashboard and reports |
| 7 | `feature/reports-and-print` | Upgrades: reports from a view, print button, checklist |

**Files:**

- `admin/add_request.php` (96 lines)
- `admin/dashboard.php` (108 lines)
- `admin/manage_requests.php` (196 lines)
- `admin/reports.php` (208 lines)
- `admin/request_view.php` (352 lines)
- `assets/css/style.css` (388 lines)
- `docs/README.md`
- `docs/TEST_CHECKLIST.md`
- `includes/request_form.php` (171 lines)
- `requester/create_request.php` (71 lines)
- `requester/dashboard.php` (101 lines)
- `requester/edit_request.php` (69 lines)
- `requester/my_requests.php` (138 lines)
- `requester/notifications.php` (4 lines)
- `requester/request_details.php` (310 lines)

---

## Dependencies between the parts
- Member 2 and Member 3 use the layout, helpers and guards of Member 1.
- `requester/notifications.php` (Member 3) and `donor/notifications.php` (Member 2) both use `includes/notifications_page.php` (Member 2).
- The pages of Member 2 and Member 3 call `includes/donor_actions.php` and `includes/admin_requests.php` (Member 1).
- `admin/reports.php` (Member 3) reads the view `v_blood_group_summary` from `database/bloodbank.sql` (Member 1).

If you need a change in someone else's file, **ask the owner**.

## How to see who did what
GitHub → **Insights → Contributors** · **Commits** · **Pull requests** (with reviews) · **Branches** · **Issues**.
