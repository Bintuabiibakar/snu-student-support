# SNU Student Support Team

A responsive, working PHP 8.2 / MySQL 8+ administrator management application for student laptop support. No fake dashboard statistics or mock database records.

## Quick local installation — Windows / XAMPP

1. Install XAMPP with PHP **8.2 or newer** and MySQL/MariaDB. Enable the PHP extensions `pdo_mysql` and `mbstring` (normally included). Start **Apache** and **MySQL**.
2. Extract this project into `C:\xampp\htdocs\snu-student-support` (ensure `public`, `app`, and `sql` are immediately inside that folder).
3. Open `http://localhost/phpmyadmin`, choose **Import**, select `sql/schema.sql` and run it. The SQL creates the database and tables. If your phpMyAdmin does not allow creating databases via import, first create `snu_student_support`, select it, then import the SQL.
4. Default local database settings are in `app/config.php`: host `127.0.0.1`, database `snu_student_support`, user `root`, and empty password. Adjust them to your actual database credentials. Prefer environment variables for production.
5. Open **http://localhost/snu-student-support/public/index.php**.
6. The first page is **Set up administrator**. Register one administrator with a unique username and password of at least 12 characters. Public registration closes immediately after the first successful account creation.
7. Open **Register Student** and enter student, laptop, accessories, backup checklist, problem and both statuses, then press **Save Student**. The student, laptop and first support visit are saved atomically in MySQL.

**Alternative dev server:** From the project directory, run `php -S localhost:8000 -t public` and open `http://localhost:8000`. MySQL must already be running.

## IMPORTANT: Updating an existing database (keep all records)

This is an **upgrade** of the existing project. Do not re-import a blank database or delete existing tables/rows.

1. Export/backup your existing database in phpMyAdmin and securely preserve the backup.
2. Stop writes to the app, then run **`sql/migrate_add_laptop_status.sql` once** against your existing `snu_student_support` database using phpMyAdmin SQL/Import. This uses a single `ALTER TABLE support_records ... ADD COLUMN` and creates no duplicate tables.
3. Existing support records will start with Laptop Status = **In Store**. Review and correct historical collected cases manually; the previous version did not record this information.
4. Replace the existing project PHP/CSS files with those in this ZIP, preserving your configured `app/config.php` settings and existing database.
5. **Do not run the migration again** (doing so produces a duplicate-column error). On a fresh installation, run only `sql/schema.sql`, which already includes the new column; no migration needed.
6. Verify students, existing visits, all historical backups, registration form, latest-case student list and both status fields.

**Data design:** Laptop Status (`In Store` / `Collected`) belongs to each **support_records** row and is independent of Work Status (`Pending` / `In Progress` / `Done`). This preserves collection state per support visit. The students list shows the latest non-archived support visit's two statuses.

**Edit behavior:** The combined **Edit Student Registration** form updates the student, laptop and **earliest non-archived support case** (usually the registration visit) in a transaction. Other later support cases are not changed. For a new return visit, use **New Support Record** on Student Details; it inserts an independent case, not an overwrite. An old student with no active support case gains their first case through the combined edit form.

## Online hosting — phone + computer access

To access the same data from all devices independently of your laptop, deploy to an always-on PHP 8.2+/MySQL hosting service with **HTTPS**, regular backups, and a supported PHP runtime. This is not achieved just by using XAMPP.

1. Create a hosted MySQL database and dedicated database account with access only to that database. Import `sql/schema.sql` (if the provider already created the database, remove the first two lines `CREATE DATABASE` and `USE` from the SQL file before import).
2. **Set your hosting document root to the `public/` directory.** Keep `app/`, `sql/`, and `docs/` outside the public document root. Do not upload `.env`, passwords, SQL dumps, or backups into web-accessible locations.
3. Configure the host's environment variables: `SNU_DB_HOST`, `SNU_DB_PORT`, `SNU_DB_NAME`, `SNU_DB_USER`, `SNU_DB_PASS`, and **`SNU_FORCE_HTTPS=true`**. On hosts without environment configuration, provide credentials only in the protected `app/config.php` file outside the document root.
4. Configure SSL/TLS certificates; redirect all HTTP requests to HTTPS at your reverse proxy or web server. Set secure, private access to the hosting account and its MySQL service. Never expose MySQL directly to the Internet.
5. Visit the new HTTPS domain, create the *single* admin account if the database is empty, then log in from your laptop and phone using the same site and account.
6. Both devices use the **same hosted database**. New or changed data becomes visible to the other device on the **next page request or refresh**. This application does **not** have live WebSocket/push updates, and does **not** merge separate local and cloud databases automatically.
7. If migrating existing local records, export the existing local database as SQL and import into the hosted database securely instead of re-importing a blank schema. Test migration on a copy first.

> **Production security note:** The application has CSRF protection, prepared statements, password hashing, session cookies and basic session-based login throttling. For a production deployment handling real personal data, configure infrastructure-level login rate limiting, access monitoring, backups, a restore plan, email-based account recovery or an administrator-managed recovery process, updates and HTTPS correctly. Session-based throttling alone is not a substitute for IP/account-level controls. Arrange privacy notices, retention rules and administrator access policies before live use.

## Application pages and functionality

| Page | Description |
| --- | --- |
| Register | Initial one-admin setup; only if zero admins exist |
| Login / Logout | Password-hashed authentication, protected routes, CSRF-protected logout |
| Dashboard | Live totals: active students, laptops, Pending, In Progress, Done, hardware-related and software-related support cases; latest cases |
| Students | Student table with both Work Status and Laptop Status; combined search/filter by name/number, department, brand, latest problem type and both statuses |
| Student Form | Combined student, laptop, accessories, first support, backup checklist, work status and laptop status, all saved in one transaction |
| Student Details | Student, laptop, accessories, complete active support history |
| Support Records | List and search all active cases, filter by status and date range |
| Support Form | New or edit case, problem type, optional categories, custom description, backup checklist, Work Status, Laptop Status, completion date |
| Support Details | Full case and backup information; edit/archive actions |

**Business decisions:**
- One student may have multiple support records; there is one current laptop entry per student. To track multiple laptops per student in future, change the database and form flow accordingly.
- Work statuses are exactly **Pending**, **In Progress**, and **Done**. Separate Laptop Status values are exactly **In Store** and **Collected**.
- **Done** auto-fills completion date with the current date if not supplied. Returning a case to Pending/In Progress clears completion date. The date cannot precede the support date.
- Backup checklist is recorded **per support visit** with values Yes / No / Not Needed for Drive C, Drive D, Desktop and Documents.
- Software/hardware dashboard counts include **Both** in both corresponding totals, so they can sum to more than total cases.
- Delete is a **soft delete / archive**, so historical records remain stored. Archived records are excluded from normal lists and dashboard counters. If a student's record is archived, its support history remains stored in the database but is not accessible from the normal Student Details page. Database access or a future restoration interface is required to view archived students.
- Student filters on problem type/status inspect the student's **most recent non-archived case**, not all historical cases.
- Student and support listing pages cap results at 500 rows. Add pagination for larger organizations.

## Database relationship structure

```text
admins (single row id=1)
students (id PK, student_number UNIQUE)
  ├── laptops (student_id UNIQUE FK -> students.id)
  │     └── laptop_accessories (laptop_id FK -> laptops.id; PK laptop_id+accessory)
  └── support_records (student_id FK -> students.id, laptop_id FK -> laptops.id)
        └── backup_records (support_id PK and FK -> support_records.id)
```

Primary and foreign keys are in `sql/schema.sql`. Support records have separate historical entries. `backup_records` belongs to the visit rather than to the student. All user-supplied SQL values use PDO prepared statements.

## Feature testing checklist

- [ ] On a fresh database, first visit opens admin registration; try mismatched/short passwords; create one admin; verify registration no longer accessible.
- [ ] Log out and verify protected pages redirect to login; log back in; test incorrect password attempts.
- [ ] Register student with number, phone, department, semester, laptop brand, accessories, custom problem description, problem type, each backup choice, work status and laptop status. Press **Save Student**; verify student, laptop, initial support record and backup record all exist.
- [ ] Attempt invalid form submission (including missing problem description) and duplicate student number; verify **no partial student, laptop or support rows** are committed.
- [ ] Set Work Status = Done and Laptop Status = In Store; verify they remain different fields and persist independently.
- [ ] For existing databases, run migration once and confirm all old support rows and backups still exist. Repeat migration only in a test copy to verify duplicate-column rejection, not as a regular step.
- [ ] Try duplicate student number; verify the application rejects it.
- [ ] Edit student/laptop details and refresh; confirm changed values persist.
- [ ] Add support visit with Hardware Only, custom description, tags, backups, Pending status.
- [ ] Create a second visit for the same student; verify Student Support History contains both.
- [ ] Update one case to In Progress then Done; verify date and dashboard counts automatically change after page load.
- [ ] Change a Done case back to Pending; verify completion date clears.
- [ ] Filter students by name/number, department, brand, latest support type, latest work status, and combinations.
- [ ] Search support records, filter by date range and work status.
- [ ] Archive a support record and student; verify confirmation prompts and normal-list removal while raw DB rows remain (deleted_at set).
- [ ] Open from phone in responsive mode; check navigation, forms and horizontal scroll of wide tables.
- [ ] On HTTPS hosting, sign in from desktop and phone; save a new case on one device, refresh on the other, and verify same record.
- [ ] Take a verified database backup and restore it to a test database.

## Important limitations

This is a complete runnable project source package, but **it has not been installed on your machine or hosted to a domain**, and the source package alone cannot confirm your personal PHP/MySQL setup. No cloud service, domain or credentials are bundled. It requires local setup or deployment, real-world security review, and feature testing before production use. Local XAMPP use is offline-capable once PHP and MySQL are installed; cloud deployment depends on internet connectivity.
