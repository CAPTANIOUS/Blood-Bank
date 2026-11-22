# BloodBank – Final Test Checklist

Start with a **fresh import** of `bloodbank.sql` and an empty `uploads/medical_documents/` folder.
Tick each box when the result matches. If something differs, write the page name and send me a screenshot.
Use **three different browsers** for the three roles (for example Chrome, Chrome private window, Edge): private windows of the same browser share the login.

## A. Setup
- [ ] `http://localhost/BloodBank/` shows the home page, no PHP error text.
- [ ] `http://localhost/BloodBank/uploads/medical_documents/` shows **403 Forbidden**.
- [ ] `http://localhost/BloodBank/config/database.php` shows a blank page (no passwords visible).

## B. Public pages (not logged in)
- [ ] Home, About, Become a Donor, Request Blood open. Menu links work.
- [ ] Find Donors: choose **A+** → donors appear, with **no phone number**. Search with a strange text like `' OR 1=1` → no error, no results.
- [ ] Open `…/admin/dashboard.php` → you are sent to Login.

## C. Register and login
- [ ] Register a **donor**: wrong data (bad phone, short password) → clear messages. Correct data → success, then login works.
- [ ] Register a **requester** with an email that exists → "already used" message.
- [ ] Login with a wrong password 5 times → "Too many wrong attempts, wait…".
- [ ] Each role lands on its own dashboard. Logout works. Browser **Back** after logout does not show private pages.
- [ ] Donor opens `…/admin/dashboard.php` → **Access denied**.

## D. Requester (farhana@example.com / Requester@123)
- [ ] **New request**: leave the document empty → refused. Upload a `.txt` or a file over 5 MB → refused with a message.
- [ ] Choose **Emergency** → the date and time boxes disappear. Send with a PDF → status **Pending**, history shows 1 entry.
- [ ] **My requests**: tabs All / Open / Completed / Rejected-Cancelled show the right counts.
- [ ] **Edit** a Pending request → saved. Edit button is missing on a Donor Matched request.
- [ ] **View** a document → it opens. Try opening another person's document address → "not found".
- [ ] **Cancel** a Pending request: confirm box → Cancelled, your reason is in the history.

## E. Admin (admin@bloodbank.com / Admin@123) – the main story
- [ ] **Blood requests** list: open emergencies first; filters and search work.
- [ ] Open the new request → read the document (**View**) → **Start review** → **Approve**.
- [ ] On a **Pending Normal** request there is no **Approve** button (it must be reviewed first). A **Completed** request shows no action buttons.
- [ ] **Ask a donor** (same blood group): only available, active donors listed; flags show "Gave blood recently" / "Medical note".
- [ ] **Reject** needs a written reason, and the requester sees it.
- [ ] **Add a request** (walk-in, no document) → saved as Pending under your account.

## F. Donor (use the donor you asked above, e.g. rahim@example.com / Donor@123)
- [ ] Notifications shows "New donation request". **Open** goes to Matching requests.
- [ ] Before accepting: patient name and phone are **hidden**. After **Accept**: they appear.
- [ ] Request status is now **Donor Matched** (not Completed).
- [ ] **Withdraw** → request goes back to **Approved**, requester is told "Searching again".
- [ ] Set yourself **Unavailable** in My profile → you cannot accept or volunteer.
- [ ] A recent donor sees a reminder to ask the blood bank. Nothing says "eligible" or "not eligible".

## G. Finish the story (Admin)
- [ ] After a donor accepts again, open the request → **Complete this request** → enter bags (1–5) → confirm.
- [ ] Status **Completed**. Requester and donor both get a notification.
- [ ] **Donation records** shows the donation. **Donors → that donor** shows an updated *last donation date*.
- [ ] A Completed request cannot be changed or cancelled.
- [ ] **Donation records → Record a walk-in donation** works; bags 0 or 6 are refused.
- [ ] **Users → Deactivate** a donor; in another window that donor is logged out at the next click and cannot log in.
- [ ] **Reports** open, numbers look right, **Print this page** hides the menu.

## H. Safety checks
- [ ] Type `<script>alert(1)</script>` as a patient name → shown as plain text, no pop-up.
- [ ] Upload a PHP file renamed to `.jpg` → refused.
- [ ] Open a request page of another requester by changing `?id=` → "not found" or redirect.

## I. Phone view
- [ ] Browser zoom / DevTools (F12 → phone icon): home, login, a dashboard and a request page have **no sideways scrolling**; the **Menu** button opens the sidebar.

## J. New in version 1.1
- [ ] **Home page:** a strip under the banner shows three live numbers (donors ready, requests completed, bags donated).
- [ ] **Register:** typing a password shows a strength bar (red = weak, green = strong).
- [ ] Open `http://localhost/BloodBank/nothing-here.php` → the friendly **404** page (not the Apache error page).
- [ ] Login, then leave the site idle for the time set in `SESSION_IDLE_SECONDS` (set it to `10` for this test, then back to `30 * 60`) → next click goes to the login page with a "logged out because you did nothing" message.
- [ ] **Admin → Donors → Download CSV** gives a file that opens in Excel; with a filter set (for example blood group A+) the file holds only those donors. A donor or requester opening the same address gets "Access denied".
- [ ] **Donor → Donation history** shows *Last donation* and *Days since last donation* (only when a donation exists).
- [ ] **Admin → Reports** shows the **Emergency and normal requests** table, and the *Blood needed* table still matches the earlier numbers.
- [ ] **Requester → a request → Print** opens the print preview without the menu.
- [ ] phpMyAdmin → SQL tab → paste `database/advanced_queries.sql` part by part: every part runs (the *future date* part must show an error on purpose).

## K. Before the viva
- [ ] `test_db.php` deleted. `APP_DEBUG` set to `false`. Demo data re-imported. Uploads folder emptied.

Result: ______ / ______ boxes ticked.   Problems found: ______________________________
