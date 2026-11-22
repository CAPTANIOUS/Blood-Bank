# Where to find each SQL requirement (ICE 3324, section 2.5)

Use this page in the demo: the teacher names a concept, you open the file and run the query.

## Run the demo files
phpMyAdmin → click the `bloodbank` database → **SQL** tab → paste one part → **Go**.
- `database/queries_demo.sql` – the basics, numbered 1–15.
- `database/advanced_queries.sql` – the advanced parts, lettered A–L.

## Requirement → query
| Requirement | File and part | The same idea inside the application |
|---|---|---|
| **DDL – CREATE** | `queries_demo.sql` 1 · and all 11 tables in `bloodbank.sql` | the whole schema |
| **DDL – ALTER** | `advanced_queries.sql` A | – |
| **DDL – DROP** | `queries_demo.sql` 4 · `advanced_queries.sql` A | `DROP DATABASE` at the top of `bloodbank.sql` (the reset) |
| **DML – INSERT** | `queries_demo.sql` 2, 15 | register (`register.php`), create request, record donation |
| **DML – UPDATE** | `queries_demo.sql` 3 | donor profile (`donor/profile.php`), change status (`includes/admin_requests.php`) |
| **DML – DELETE** | `queries_demo.sql` 4 | admin takes back an unanswered question (`admin_remove_pending_ask()`); accounts are deactivated instead of deleted, on purpose |
| **SELECT, WHERE, ORDER BY** | `queries_demo.sql` 5 | every list page |
| **LIKE** | `advanced_queries.sql` B | Find Donors search box, admin donor search |
| **COUNT / SUM / AVG** | `queries_demo.sql` 7, 9 | `admin/reports.php`, admin dashboard |
| **MIN / MAX** | `advanced_queries.sql` C · `queries_demo.sql` 9, 10 | – |
| **GROUP BY** | `queries_demo.sql` 7, 9 · `advanced_queries.sql` C | Reports: requests by status |
| **HAVING** | `queries_demo.sql` 8 | – |
| **INNER JOIN** | `queries_demo.sql` 6, 12 | almost every page |
| **LEFT JOIN** | `queries_demo.sql` 10, 13 | donation history, request timeline |
| **RIGHT JOIN** | `advanced_queries.sql` D | – |
| **Subquery** | `queries_demo.sql` 11 | admin request page (matching donors) |
| **Correlated subquery** | `advanced_queries.sql` E | – |
| **EXISTS / NOT EXISTS** | `advanced_queries.sql` F | `admin/request_view.php` (donors not yet asked) |
| **Derived table** | `advanced_queries.sql` G | – |
| **View** | `queries_demo.sql` 14 · `advanced_queries.sql` H | `admin/reports.php` reads `v_blood_group_summary` |
| **Stored procedure** | `advanced_queries.sql` I | `CALL sp_matching_donors(2);` |
| **Trigger** | `queries_demo.sql` 15 · `advanced_queries.sql` J | automatic when a donation is saved |
| **Transaction** | `advanced_queries.sql` K | `donor_respond()`, `admin_complete_request()` |
| **Index** | `advanced_queries.sql` L | `idx_donor_search` |

## Other course requirements
| Requirement | Where |
|---|---|
| ER diagram and relational schema | `docs/ER_DIAGRAM.md`, `docs/er_diagram.png`, `docs/RELATIONAL_SCHEMA.md` |
| Normalization, constraints, indexing, transactions | `docs/DATABASE_DESIGN.md` |
| README with overview, features, stack, database setup, run guide | `README.md` |
| Each member's meaningful commits | GitHub → Insights → Contributors, `docs/TEAM_CONTRIBUTION.md` |
