# Riset Properties Database Setup Guide

## Overview
Your Riset Properties system has been successfully configured to use the **`riset_properties`** MySQL database with all necessary tables for property management, tenant management, enquiries, viewings, and maintenance tracking.

## Database Configuration

### Database Details
- **Database Name**: `riset_properties`
- **Host**: `localhost`
- **User**: `root`
- **Password**: (empty by default)
- **Port**: `3306` (default MySQL port)

### Updated Configuration File
The `dbconnect.php` file has been updated to use the new database:

```php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'riset_properties';
```

## Installation Steps

### Step 1: Create the Database
1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
2. Login with your MySQL credentials
3. In the SQL tab, copy and paste the contents of **`database-setup.sql`**
4. Execute the SQL query

Alternatively, using MySQL CLI:
```bash
mysql -u root -p < database-setup.sql
```

### Step 2: Verify Installation
After running the SQL, verify all tables were created:

```sql
USE riset_properties;
SHOW TABLES;
```

You should see these tables:
- `users` - Admin, landlord, staff, and teacher accounts
- `tenants` - Property tenants/residents
- `properties` - Property listings
- `property_images` - Multiple images per property
- `viewings` - Scheduled property viewings/events
- `maintenance_tasks` - Property maintenance tasks
- `enquiries` - All types of inquiries
- `leases` - Tenant lease agreements
- `payments` - Rent payment tracking

## Database Tables Structure

### 1. **users** Table
Stores admin, landlord, staff, and teacher accounts.
```sql
Columns: id, username, email, first_name, last_name, phone, password, role, status, avatar, created_at, updated_at
```

### 2. **tenants** Table
Stores tenant/resident information.
```sql
Columns: id, first_name, last_name, phone, email, city, country, tenant_id, date_of_birth, password, status, avatar, move_in_date, move_out_date, created_at, updated_at
```

### 3. **properties** Table
Stores property listings with pricing and availability.
```sql
Columns: id, title, description, property_type, category, bedrooms, bathrooms, square_feet, price, currency, location, city, country, landlord_id, status, image_url, featured_image, contact_person, contact_phone, contact_email, availability_date, created_at, updated_at, deleted_at
```

### 4. **property_images** Table
Stores multiple images for each property.
```sql
Columns: id, property_id, image_path, image_title, display_order, created_at
```

### 5. **viewings** Table
Stores scheduled property viewings/events.
```sql
Columns: id, property_id, tenant_id, viewing_date, viewing_time, status, notes, organizer_id, created_at, updated_at
```

### 6. **maintenance_tasks** Table
Stores property maintenance and repair tasks.
```sql
Columns: id, property_id, title, description, task_type, priority, status, assigned_to, scheduled_date, completion_date, estimated_cost, actual_cost, created_by, created_at, updated_at
```

### 7. **enquiries** Table
Stores all types of enquiries (property, tenant, viewing, maintenance, general).
```sql
Columns: id, enquiry_type, name, email, phone, subject, message, property_id, tenant_id, related_to, status, replied, reply_message, replied_by, replied_at, created_at, updated_at
```

### 8. **leases** Table
Stores tenant lease agreements.
```sql
Columns: id, tenant_id, property_id, lease_start_date, lease_end_date, monthly_rent, deposit, status, lease_document, notes, created_at, updated_at
```

### 9. **payments** Table
Tracks rent payments and transactions.
```sql
Columns: id, lease_id, tenant_id, property_id, amount, payment_date, payment_method, transaction_id, status, notes, created_at, updated_at
```

## Sample Data Included

The database setup script includes sample data for testing:

### Sample Users (with password hash `password123`)
- **Admin**: admin / admin@risetproperties.com (Admin role)
- **Landlord**: landlord / landlord@risetproperties.com (Landlord role)
- **Staff**: staff / staff@risetproperties.com (Staff role)

### Sample Tenants
- John Smith (ID: TN001)
- Jane Doe (ID: TN002)
- Michael Johnson (ID: TN003)

### Sample Properties
- 3-bed Apartment Westlands (KES 85,000)
- 2-bed Townhouse Kilimani (KES 65,000)
- 4-bed Villa Upper Hill (KES 150,000)

## Next Steps for PHP Integration

### Update PHP Files
The PHP files are already configured to use the new database through `dbconnect.php`. However, ensure the following:

1. **Verify admin-user-add.php** queries use the correct table names
2. **Update forms** to properly reference the new table columns
3. **Add validation** for data consistency

### Recommended PHP Updates

For files working with tenants, ensure they reference the `tenants` table:
```php
// Example: admin-user-add.php
INSERT INTO tenants (first_name, last_name, phone, email, city, country, tenant_id, password, status, avatar) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
```

## Backup and Maintenance

### Regular Backups
To backup the database:
```bash
mysqldump -u root -p riset_properties > backup_riset_properties.sql
```

### Restore from Backup
```bash
mysql -u root -p riset_properties < backup_riset_properties.sql
```

## Troubleshooting

### Connection Error
If you see "Database connection failed", verify:
1. MySQL is running
2. Database name is correct: `riset_properties`
3. Credentials in `dbconnect.php` are correct
4. Database exists in phpMyAdmin

### Table Not Found
If a table is missing:
1. Check phpMyAdmin to see if the table exists
2. Run the SQL setup script again
3. Verify no SQL errors occurred during execution

### Permission Issues
If you get permission errors:
```bash
# Grant all privileges to root user
mysql -u root -p -e "GRANT ALL PRIVILEGES ON riset_properties.* TO 'root'@'localhost'; FLUSH PRIVILEGES;"
```

## Data Types and Indexes

All tables use:
- **InnoDB** engine for better transaction support
- **utf8mb4** collation for Unicode support
- **Indexes** on frequently searched columns for better performance
- **Foreign keys** for data integrity (where applicable)

## Important Notes

1. The `tenants` table replaces the old `students` table - update your PHP code accordingly
2. All passwords in sample data are hashed with bcrypt (PHP password_hash)
3. Timestamps automatically track creation and updates
4. Soft deletes are supported via `deleted_at` field in properties table
5. Status fields control record visibility and workflow

## Support

For issues or questions:
1. Check that all tables were created successfully
2. Verify sample data was inserted
3. Test a simple SELECT query: `SELECT * FROM users LIMIT 1`
4. Review error logs in `VSCODE_TARGET_SESSION_LOG` directory

---
**Database Setup Complete!** Your Riset Properties system is ready for data collection.
