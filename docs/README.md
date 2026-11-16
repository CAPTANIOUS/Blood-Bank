# BloodBank – Blood Management System

A web application that connects people who **need blood** with **donors**, with an **admin** who reviews and
controls every request. Built for a university DBMS project.

**BloodBank does not decide who is medically fit to donate.** It only shows reminders and flags. A doctor decides.

## Technology
HTML5 · CSS3 · JavaScript · Bootstrap 5.3 (saved inside the project, works offline) · core PHP 8 with PDO ·
MySQL / MariaDB · phpMyAdmin · Apache (XAMPP) · VS Code. No framework, no npm, no Composer.

## Install (5 steps)
1. Open **XAMPP Control Panel**. Start **Apache** and **MySQL**.
2. Copy the `BloodBank` folder to `C:\xampp\htdocs\`  (final path: `C:\xampp\htdocs\BloodBank\index.php`).
3. Open `http://localhost/phpmyadmin` → **Import** → choose `bloodbank.sql` → press **Import** at the bottom.
   (This creates the database `bloodbank` with sample data.)
4. Open `http://localhost/BloodBank/` – the home page should appear.
5. If your folder name or port is different, change `BASE_URL` in `config/config.php`.

**Reset the demo data:** import `bloodbank.sql` again (it deletes and re-creates the database), then empty the folder
`BloodBank/uploads/medical_documents/` (keep the folder itself).

## Test accounts (for testing only)
| Role | Email | Password |
|---|---|---|
| Admin | admin@bloodbank.com | Admin@123 |
| Donor | rahim@example.com (also karima@, sabbir@, nusrat@, tanvir@, imran@, mahmud@) | Donor@123 |
| Requester | farhana@example.com (also jahid@, shirin@) | Requester@123 |

Change these passwords before any real use.

## What each role can do
**Visitor:** Home, About, Find Donors (name, blood group, district, area only – no phone), Become a Donor, Register, Login.

**Donor:** profile and availability · password change · matching requests (answer Accept / Decline, or volunteer)
· donation history · notifications. Patient name and contact are shown **only after accepting**.

**Requester:** create a request with medical documents (PDF / JPG / PNG, max 5 MB, up to 3) · edit while Pending ·
cancel · status timeline · see accepted donors · notifications.

**Admin:** dashboard · requests (filter, review, approve / reject / cancel) · ask donors to help and watch answers ·
complete a request and record the donation · add a request for a walk-in · donors (search, edit, deactivate) ·
users (deactivate / reactivate) · donation records (including camp donations) · printable reports · admin log.

## Request workflow
`Pending → Under Review → Approved → Donor Matched → Completed`   (or `Rejected` / `Cancelled`)

- A donor accepting moves the request to **Donor Matched** – never to Completed.
- **Only an admin can mark Completed**, after the blood was really given, and must record the donation.
- Every change is saved in `request_status_history`.

## Folder map
```
BloodBank/
  index.php about.php find_donors.php become_donor.php request_blood.php login.php register.php logout.php
  document.php            the ONLY way to open a medical document (login + ownership check)
  config/                 config.php (settings) · database.php (PDO connection)
  includes/               auth (sessions, roles, CSRF) · functions · validators · menus · shared page parts
                          donor_actions.php (donor answers) · admin_requests.php (admin workflow)
  admin/ donor/ requester/   the three panels
  assets/                 css/style.css (theme) · js/main.js · Bootstrap (local)
  uploads/medical_documents/   uploaded files (browser access blocked by .htaccess)
  docs/                   this file, test checklist, viva notes, presentation outline
```

## Before you show the project
- [ ] **Delete `test_db.php`** (a temporary connection test).
- [ ] In `config/config.php` set `APP_DEBUG` to `false` (hides technical error messages from the audience).
- [ ] Re-import `bloodbank.sql` and empty `uploads/medical_documents/` so the demo starts clean.
- [ ] Check `http://localhost/BloodBank/uploads/medical_documents/` shows **403 Forbidden**.
- [ ] Start Apache and MySQL before the demo. Open the pages you will show in tabs.

## Known limits (be honest about them in the viva)
- Notifications are inside the website only – no email or SMS.
- Donor matching uses the **exact** blood group and district. Compatible groups (for example O- for anyone) are not used.
- Login lock (5 wrong passwords → wait 60 seconds) is per browser session. A real system would also limit by IP address.
- There is no "forgot password" page. Only donors can change their own password.
- Documents are checked by a human admin. The system does not read or verify them.
