# BloodBank – Blood Management System

A web application that connects people who **need blood** with **blood donors**. An **admin** reviews every request.
Built for a university DBMS project by a team of three.

> BloodBank does **not** decide who is medically fit to donate. It only shows reminders and flags. A doctor decides.

## Features
- **Three panels:** Admin, Donor, Requester.
- **Request workflow:** Pending → Under Review → Approved → Donor Matched → Completed (or Rejected / Cancelled), with a full status history.
  Only an admin can mark a request Completed, after the blood was really given.
- **Donor matching:** by blood group and district; donors accept, decline or volunteer.
- **Secure medical documents:** PDF / JPG / PNG, checked for real type and size, stored under random names, opened only through a login check.
- **Normalized database:** 11 tables, 18 foreign keys, 3 views, 2 triggers, 1 stored procedure.
- **Security:** hashed passwords, prepared statements (PDO), XSS escaping, CSRF tokens, role checks, login lock, audit logs.

## Technology
HTML5 · CSS3 · JavaScript · Bootstrap 5 · core PHP 8 (PDO) · MySQL / MariaDB · phpMyAdmin · Apache (XAMPP)

## Run it on your computer
1. Install **XAMPP**. Start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\` so the final path is `C:\xampp\htdocs\BloodBank\index.php` (the folder must be named `BloodBank`).
3. Open `http://localhost/phpmyadmin` → **Import** → choose `database/bloodbank.sql` → press **Import**.
4. Open `http://localhost/BloodBank/`.

Test accounts (testing only): `admin@bloodbank.com` / `Admin@123` · donors `Donor@123` (for example `rahim@example.com`) · requesters `Requester@123` (for example `farhana@example.com`).

More help is in the `docs/` folder: test checklist, viva notes, presentation outline.

## Team and contributions
| Member | Part | Branch |
|---|---|---|
| _Member 1 name_ | Database, security core, design system, public pages, login and registration | `feature/foundation-auth` |
| _Member 2 name_ | Donor module and donor / user administration | `feature/donor-module` |
| _Member 3 name_ | Blood requests, secure documents and admin request workflow | `feature/request-admin` |

See `docs/TEAM_CONTRIBUTION.md` for the exact files of each part.
