# Migrations

This folder contains SQL migration scripts for the Riset Properties system.

## New migration added

- `20260522_communications_and_maintenance_communications.sql`
  - Adds the `communications` table.
  - Adds the `communication_templates` table.
  - Adds the `maintenance_communications` table.
  - Adds a `comments` column to `maintenance_tasks` if it does not already exist.
- `20260522_communications_tenant_recipient_support.sql`
  - Adds `recipient_type` to `communications`.
  - Removes the strict `recipient_id` foreign key constraint to allow tenant recipients.

## How to run the migration

1. Open your MySQL client or phpMyAdmin.
2. Select the `RisetProperties` database (or the database used by your app).
3. Execute the SQL file contents from `migrations/20260522_communications_and_maintenance_communications.sql`.
4. If the `communications` table already exists and you need tenant support, also execute `migrations/20260522_communications_tenant_recipient_support.sql`.

If using the command line, run either:

```bash
mysql -u <username> -p <database_name> < migrations/20260522_communications_and_maintenance_communications.sql
```

or, for an existing communications table:

```bash
mysql -u <username> -p <database_name> < migrations/20260522_communications_tenant_recipient_support.sql
```

Replace `<username>` and `<database_name>` with your MySQL credentials.

## Notes

- This migration is safe to run on databases that already have the `maintenance_tasks` table.
- The `ALTER TABLE maintenance_tasks ADD COLUMN IF NOT EXISTS comments TEXT;` statement will only add the column if it is missing.
- Make sure the `users` table exists before applying this migration.
