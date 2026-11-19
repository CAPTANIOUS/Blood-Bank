# BloodBank – Entity-Relationship (ER) Diagram

**What it is:** a picture of the database. Each box is a **table** (entity). Each line is a **relationship**.
**Why we need it:** it shows how the data is organised before anyone writes code, and the course asks for it (ICE 3324, section 2.4).

Reading the symbols (crow's foot notation):

| Symbol | Meaning |
|---|---|
| `\|\|` | exactly one |
| `o\|` | zero or one |
| `o{` | zero or many |
| **PK** / **FK** / **UK** | primary key / foreign key / unique key |

```mermaid
erDiagram
    users ||--o| donors : "is a"
    blood_groups ||--o{ donors : "has group"
    districts ||--o{ donors : "lives in"
    users ||--o{ blood_requests : "makes"
    blood_groups ||--o{ blood_requests : "needs group"
    districts ||--o{ blood_requests : "in district"
    users |o--o{ blood_requests : "reviews"
    blood_requests ||--o{ medical_documents : "has"
    blood_requests ||--o{ donor_responses : "asks"
    donors ||--o{ donor_responses : "answers"
    blood_requests |o--o{ donations : "leads to"
    donors ||--o{ donations : "gives"
    users |o--o{ donations : "records"
    blood_requests ||--o{ request_status_history : "logs"
    users |o--o{ request_status_history : "changes"
    users ||--o{ notifications : "receives"
    users |o--o{ admin_logs : "writes"
    admin_logs {
        int log_id PK
        int admin_id FK
        varchar action
        varchar table_name
        int record_id
        varchar details
        varchar ip_address
        timestamp created_at
    }
    blood_groups {
        tinyint blood_group_id PK
        varchar group_name UK
    }
    blood_requests {
        int request_id PK
        int requester_id FK
        varchar patient_name
        varchar requester_name
        varchar contact_phone
        tinyint blood_group_id FK
        tinyint bags_required
        varchar medical_condition
        varchar hospital_name
        varchar hospital_address
        smallint district_id FK
        varchar area
        enum urgency
        date required_date
        time required_time
        text additional_info
        enum status
        varchar admin_remark
        int reviewed_by FK
        datetime reviewed_at
        datetime completed_at
        timestamp created_at
        timestamp updated_at
    }
    districts {
        smallint district_id PK
        varchar district_name UK
    }
    donations {
        int donation_id PK
        int request_id FK
        int donor_id FK
        tinyint bags_donated
        date donation_date
        int recorded_by FK
        varchar notes
        timestamp created_at
    }
    donors {
        int user_id PK,FK
        date date_of_birth
        enum gender
        tinyint blood_group_id FK
        varchar address
        smallint district_id FK
        varchar area
        date last_donation_date
        text medical_issues
        tinyint is_available
        timestamp created_at
        timestamp updated_at
    }
    donor_responses {
        int response_id PK
        int request_id FK,UK
        int donor_id FK,UK
        enum response_status
        int assigned_by FK
        varchar donor_note
        timestamp created_at
        datetime responded_at
    }
    medical_documents {
        int document_id PK
        int request_id FK
        varchar original_name
        varchar stored_name UK
        varchar file_ext
        int file_size
        timestamp uploaded_at
    }
    notifications {
        int notification_id PK
        int user_id FK
        varchar title
        varchar message
        varchar link
        tinyint is_read
        timestamp created_at
    }
    request_status_history {
        int history_id PK
        int request_id FK
        varchar old_status
        varchar new_status
        int changed_by FK
        varchar remark
        timestamp changed_at
    }
    users {
        int user_id PK
        varchar full_name
        varchar email UK
        varchar password_hash
        varchar phone UK
        enum role
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
```

GitHub draws the diagram above by itself. A picture for the slides is in `docs/er_diagram.png`.

## How to explain the relationships (simple English)
| Relationship | Type | In words |
|---|---|---|
| users – donors | one-to-one | A donor account has exactly one donor profile. The profile uses the **same id** as the user (`donors.user_id` is both PK and FK). |
| users – blood_requests | one-to-many | One requester can make many requests. |
| blood_groups / districts – donors / blood_requests | one-to-many | One group (or district) is used by many donors and many requests. These are **lookup tables**: the name is stored once. |
| blood_requests – medical_documents | one-to-many | One request can have up to 3 uploaded documents. |
| blood_requests – donors (through `donor_responses`) | **many-to-many** | One request can ask many donors, and one donor can be asked for many requests. `donor_responses` is the **junction table**; (request_id, donor_id) is unique. |
| blood_requests – donations | one-to-many | A completed request can have several donations (several donors or bags). |
| blood_requests – request_status_history | one-to-many | Every status change is saved as a new row: this is the audit trail. |
| users – notifications / admin_logs | one-to-many | Messages for a user; actions done by an admin. |

Links not drawn, to keep the picture clear: `donor_responses.assigned_by`, `donations.recorded_by` and `blood_requests.reviewed_by` point to the admin in `users`.
The three views (`v_request_overview`, `v_donor_directory`, `v_blood_group_summary`) are not tables, so they are not in the diagram.
