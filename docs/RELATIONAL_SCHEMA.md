# BloodBank – Relational Schema

**What it is:** the list of tables with their columns, **primary keys (PK)** and **foreign keys (FK)**.
The real SQL is in `database/bloodbank.sql`. This page was made from the real database.

## admin_logs

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| log_id | int(10) unsigned | no | PK |  |
| admin_id | int(10) unsigned | yes | FK | `users.user_id` |
| action | varchar(100) | no |  |  |
| table_name | varchar(50) | yes |  |  |
| record_id | int(10) unsigned | yes |  |  |
| details | varchar(255) | yes |  |  |
| ip_address | varchar(45) | yes |  |  |
| created_at | timestamp | no |  |  |

## blood_groups

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| blood_group_id | tinyint(3) unsigned | no | PK |  |
| group_name | varchar(3) | no | UNIQUE |  |

## blood_requests

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| request_id | int(10) unsigned | no | PK |  |
| requester_id | int(10) unsigned | no | FK | `users.user_id` |
| patient_name | varchar(100) | no |  |  |
| requester_name | varchar(100) | no |  |  |
| contact_phone | varchar(20) | no |  |  |
| blood_group_id | tinyint(3) unsigned | no | FK | `blood_groups.blood_group_id` |
| bags_required | tinyint(3) unsigned | no |  |  |
| medical_condition | varchar(255) | no |  |  |
| hospital_name | varchar(150) | no |  |  |
| hospital_address | varchar(255) | no |  |  |
| district_id | smallint(5) unsigned | no | FK | `districts.district_id` |
| area | varchar(100) | no |  |  |
| urgency | enum('Normal','Emergency') | no |  |  |
| required_date | date | no |  |  |
| required_time | time | yes |  |  |
| additional_info | text | yes |  |  |
| status | enum('Pending','Under Review','Approved','Donor Matched','Completed','Rejected','Cancelled') | no |  |  |
| admin_remark | varchar(255) | yes |  |  |
| reviewed_by | int(10) unsigned | yes | FK | `users.user_id` |
| reviewed_at | datetime | yes |  |  |
| completed_at | datetime | yes |  |  |
| created_at | timestamp | no |  |  |
| updated_at | timestamp | no |  |  |

## districts

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| district_id | smallint(5) unsigned | no | PK |  |
| district_name | varchar(50) | no | UNIQUE |  |

## donations

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| donation_id | int(10) unsigned | no | PK |  |
| request_id | int(10) unsigned | yes | FK | `blood_requests.request_id` |
| donor_id | int(10) unsigned | no | FK | `donors.user_id` |
| bags_donated | tinyint(3) unsigned | no |  |  |
| donation_date | date | no |  |  |
| recorded_by | int(10) unsigned | yes | FK | `users.user_id` |
| notes | varchar(255) | yes |  |  |
| created_at | timestamp | no |  |  |

## donors

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| user_id | int(10) unsigned | no | PK, FK | `users.user_id` |
| date_of_birth | date | no |  |  |
| gender | enum('Male','Female','Other') | no |  |  |
| blood_group_id | tinyint(3) unsigned | no | FK | `blood_groups.blood_group_id` |
| address | varchar(255) | no |  |  |
| district_id | smallint(5) unsigned | no | FK | `districts.district_id` |
| area | varchar(100) | no |  |  |
| last_donation_date | date | yes |  |  |
| medical_issues | text | yes |  |  |
| is_available | tinyint(1) | no |  |  |
| created_at | timestamp | no |  |  |
| updated_at | timestamp | no |  |  |

## donor_responses

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| response_id | int(10) unsigned | no | PK |  |
| request_id | int(10) unsigned | no | FK, UNIQUE | `blood_requests.request_id` |
| donor_id | int(10) unsigned | no | FK, UNIQUE | `donors.user_id` |
| response_status | enum('Pending','Accepted','Declined') | no |  |  |
| assigned_by | int(10) unsigned | yes | FK | `users.user_id` |
| donor_note | varchar(255) | yes |  |  |
| created_at | timestamp | no |  |  |
| responded_at | datetime | yes |  |  |

## medical_documents

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| document_id | int(10) unsigned | no | PK |  |
| request_id | int(10) unsigned | no | FK | `blood_requests.request_id` |
| original_name | varchar(255) | no |  |  |
| stored_name | varchar(255) | no | UNIQUE |  |
| file_ext | varchar(5) | no |  |  |
| file_size | int(10) unsigned | no |  |  |
| uploaded_at | timestamp | no |  |  |

## notifications

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| notification_id | int(10) unsigned | no | PK |  |
| user_id | int(10) unsigned | no | FK | `users.user_id` |
| title | varchar(120) | no |  |  |
| message | varchar(255) | no |  |  |
| link | varchar(255) | yes |  |  |
| is_read | tinyint(1) | no |  |  |
| created_at | timestamp | no |  |  |

## request_status_history

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| history_id | int(10) unsigned | no | PK |  |
| request_id | int(10) unsigned | no | FK | `blood_requests.request_id` |
| old_status | varchar(20) | yes |  |  |
| new_status | varchar(20) | no |  |  |
| changed_by | int(10) unsigned | yes | FK | `users.user_id` |
| remark | varchar(255) | yes |  |  |
| changed_at | timestamp | no |  |  |

## users

| Column | Type | Null | Key | References |
|---|---|---|---|---|
| user_id | int(10) unsigned | no | PK |  |
| full_name | varchar(100) | no |  |  |
| email | varchar(120) | no | UNIQUE |  |
| password_hash | varchar(255) | no |  |  |
| phone | varchar(20) | no | UNIQUE |  |
| role | enum('admin','donor','requester') | no |  |  |
| is_active | tinyint(1) | no |  |  |
| created_at | timestamp | no |  |  |
| updated_at | timestamp | no |  |  |

**Totals:** 11 tables · 18 foreign keys · 6 unique rules · 2 CHECK rules · 26 extra indexes · 3 views · 2 triggers · 1 stored procedure.
