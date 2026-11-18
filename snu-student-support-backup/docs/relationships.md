# Database design and relationships

- `admins`: one possible row, with primary key `id=1`; password stored with PHP `password_hash`.
- `students`: unique student number and student contact/academic details; has zero or one `deleted_at` value to indicate archive.
- `laptops`: each laptop record belongs to one student (`student_id` is both foreign key and unique). A student has one current laptop profile.
- `laptop_accessories`: repeated predefined accessory names stored relationally, one per `(laptop_id, accessory)`.
- `support_records`: one student and their laptop per service visit; many visits per student. Includes dates, type, optional categories JSON text and unrestricted description, status, completion and archive timestamps.
- `backup_records`: one-to-one per support record, four enum fields capturing the data backup decisions.

The student/laptop/support backup data are decomposed to avoid repeating student identity on each service visit. `support_records.student_id` and `laptop_id` allow convenient service-to-student history, and the form ensures the laptop belongs to that student. For stricter DB-only enforcement, a composite foreign key could be added in a later schema migration. MySQL's internal primary/foreign keys enforce reference integrity, and application transactions keep multi-table writes consistent.
