# BloodBank – Presentation Outline

Plan for about 10 minutes: 4 minutes of slides, 5–6 minutes of live demo, then questions.

## Slides (10, short – one idea each)
1. **Title** – BloodBank: Blood Management System. Your name, ID, course, teacher.
2. **The problem** – finding blood donors in an emergency is slow and done by phone calls; no record of requests or donations.
3. **The solution** – one website: requesters ask, an admin checks, donors respond, everything is recorded. *(Say: it never decides medical fitness.)*
4. **Three panels** – Admin · Donor · Requester, one line each (what they can do).
5. **Request workflow** – draw `Pending → Under Review → Approved → Donor Matched → Completed` (+ Rejected / Cancelled). Key rule: **only the admin completes**.
6. **Technology** – HTML, CSS, JavaScript, Bootstrap 5, core PHP (PDO), MySQL, XAMPP.
7. **Database** – ER diagram: 11 tables; point at the one-to-one, one-to-many and the many-to-many (`donor_responses`). Say "3NF".
8. **Security** – bcrypt passwords · prepared statements · XSS escaping · CSRF tokens · role checks · safe uploads · private documents.
9. **Testing** – 129-page crawl with no errors, upload attack tests, workflow tests, phone layout (use your own checklist result).
10. **Limits and future work** – email/SMS, compatible groups, forgot-password. Thank you / questions.

## Live demo script (practise it twice; about 6 minutes)
Make the demo request **A+ in Dhaka**, so that Rahim (A+, Dhaka) appears in the donor list. Use **three browser windows** (admin, requester, donor), all already open and logged out. Fresh data imported.

| Min | Who | Do | Say |
|---|---|---|---|
| 0:00 | Visitor | Home → **Find Donors** (A+): no phone shown | "Phone numbers stay private until login." |
| 0:45 | Requester `farhana@example.com` | **New request** → blood group **A+**, district **Dhaka** → Emergency → upload a PDF → send | "Emergency needs no date. The file type, size and real content are checked." |
| 1:45 | Admin | **Blood requests** → open the new one → **View** document → **Start review** → **Approve** | "Every step is saved in the history; the document view is logged." |
| 2:45 | Admin | **Ask a donor** → choose one | "Only same blood group, active and available donors are shown. Flags are for humans to check." |
| 3:15 | Donor `rahim@example.com` | **Notifications** → Matching requests → **Accept** | "Contact details appear only after accepting. Status is Donor Matched, not Completed." |
| 4:15 | Admin | **Complete this request** → 1 bag → confirm | "Only now it is Completed, and the donation is recorded. A trigger updated the donor's last donation date." |
| 5:00 | Requester | **My requests / Notifications** | "The requester sees the whole timeline." |
| 5:30 | Admin | **Reports** (Not enough flags) → open phpMyAdmin → run 2 queries from `queries_demo.sql` (JOIN and GROUP BY) | "The same numbers come from one SQL query." |

**If something breaks:** stay calm, say "let me show the next step", refresh with Ctrl+F5, and if needed re-import `bloodbank.sql` (you practised this).
**Before you start:** `test_db.php` deleted · `APP_DEBUG` = false · Apache + MySQL running · data re-imported · uploads folder empty · phone/laptop charged · a printed copy of the ER diagram.
