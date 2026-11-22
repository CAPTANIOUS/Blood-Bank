# BloodBank – Database Design Notes

Short answers to "why is the database built like this?". The real SQL is in `database/bloodbank.sql`;
the diagram is in `docs/ER_DIAGRAM.md`; the column list is in `docs/RELATIONAL_SCHEMA.md`.

## 1. Normalization (no repeated data)
| Form | Rule in simple words | How BloodBank follows it |
|---|---|---|
| **1NF** | One value per cell, no lists in a cell | A donor has one blood group id, one district id. Documents are rows in `medical_documents`, not a list in the request row. |
| **2NF** | Every column depends on the **whole** key | The junction table `donor_responses` has the key (request_id, donor_id); its columns (status, note, time) describe that pair only. |
| **3NF** | No column depends on another normal column | Names of blood groups and districts live once in `blood_groups` and `districts`; other tables keep only the id. Phone and email live once, in `users`. |

Two small copies are kept **on purpose**:
- `blood_requests` keeps its own contact name and phone, so the contact on an old request does not change if an account is edited later.
- `request_status_history.old_status / new_status` are saved as text, because a history row must never change after it was written.

## 2. Constraints (the database refuses wrong data)
| Kind | Examples |
|---|---|
| **Primary key** | every table has one; `donors.user_id` is both PK and FK (one-to-one with `users`) |
| **Foreign keys (18)** | e.g. `blood_requests.requester_id → users.user_id` |
| **ON DELETE rules** | `CASCADE` for private children (a deleted user takes their notifications), `RESTRICT` for lookup tables (a blood group in use cannot be deleted), `SET NULL` for "who did it" columns (history stays when an admin account is removed) |
| **UNIQUE (6)** | `users.email`, `users.phone`, `blood_groups.group_name`, `districts.district_name`, `medical_documents.stored_name`, and (request_id, donor_id) in `donor_responses` |
| **CHECK (2)** | `chk_req_bags` and `chk_don_bags`: bags must be in the allowed range |
| **ENUM / NOT NULL** | `role`, `status`, `urgency`, `response_status` accept only listed values |
| **Trigger** | `trg_donation_before_insert` refuses a donation dated in the future (a CHECK cannot use `CURDATE()`) |

## 3. Indexes (finding rows fast)
26 extra indexes, for example:
- `idx_donor_search (blood_group_id, district_id, is_available)` – the Find Donors search. Run `EXPLAIN` in `database/advanced_queries.sql` (part L): the result shows this index in the `key` column.
- `idx_req_status`, `idx_req_match`, `idx_req_requester` – admin filters, matching and "my requests".
- `idx_hist_request (request_id, changed_at)` – the status timeline of one request.
- `idx_notif_user (user_id, is_read)` – the unread bell count.

Why not index everything? Each index makes INSERT and UPDATE a little slower and uses disk space, so we index the columns that pages really search or join on.

## 4. Transactions (all or nothing)
A transaction groups several SQL steps so that **either all happen or none**. We use them where two tables must change together:
| Where | Steps inside one transaction |
|---|---|
| `donor_respond()` in `includes/donor_actions.php` | lock the request, save the donor's answer, change the status, write history, notify |
| `admin_change_status()` in `includes/admin_requests.php` | change status, write history, notify, write admin log |
| `admin_complete_request()` in `includes/admin_requests.php` | insert donations, set Completed, write history, notify |

`SELECT ... FOR UPDATE` locks the request row, so two people pressing a button at the same moment cannot break the status.
Part K of `database/advanced_queries.sql` shows `START TRANSACTION … ROLLBACK` by hand.

## 5. Views, trigger, procedure
| Object | Purpose | Used by |
|---|---|---|
| `v_request_overview` | joined list of requests | demo queries |
| `v_donor_directory` | donors **without** phone and email | demo queries |
| `v_blood_group_summary` | donors available vs bags needed per blood group | `admin/reports.php` |
| `trg_donation_update_last_date` | after a donation is saved, update `donors.last_donation_date` | automatic |
| `trg_donation_before_insert` | refuse a future donation date | automatic |
| `sp_matching_donors(request_id)` | donors who match a request | `CALL sp_matching_donors(2);` |

## 6. Audit trail
`request_status_history` (every status change) and `admin_logs` (every important admin action, including CSV exports) are **append-only**: the application only inserts into them.
Accounts are **deactivated, not deleted**, so old requests and donations keep their meaning.
