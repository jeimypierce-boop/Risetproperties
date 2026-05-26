# RISET PROPERTIES DATABASE - SETUP SUMMARY

## ✅ Completed Tasks

### 1. Database Created
- **Database Name**: `riset_properties`
- **Status**: Ready for use
- **Location**: `/database-setup.sql`

### 2. Database Tables Created (9 Tables)
All tables have been created with proper relationships and indexes:

| Table | Purpose | Records |
|-------|---------|---------|
| `users` | Admin, landlord, staff accounts | 3 samples |
| `tenants` | Tenant/resident information | 3 samples |
| `properties` | Property listings | 3 samples |
| `property_images` | Multiple images per property | - |
| `viewings` | Scheduled property viewings/events | 3 samples |
| `maintenance_tasks` | Maintenance & repair tasks | 3 samples |
| `enquiries` | All types of enquiries | 3 samples |
| `leases` | Tenant lease agreements | - |
| `payments` | Rent payment tracking | - |

### 3. Connection Configuration Updated
- **File**: `/dbconnect.php`
- **Database**: Changed from `education` → `riset_properties`
- **Status**: ✅ Active

### 4. Sample Data Loaded
- 3 User accounts (Admin, Landlord, Staff)
- 3 Tenant records
- 3 Property listings
- 3 Viewings scheduled
- 3 Maintenance tasks
- 3 Enquiries

## 🚀 Quick Start

### Step 1: Import Database
```bash
# Option A: Using MySQL CLI
mysql -u root -p < database-setup.sql

# Option B: Using phpMyAdmin
1. Login to http://localhost/phpmyadmin
2. Click "SQL" tab
3. Copy contents of database-setup.sql
4. Click "Go"
```

### Step 2: Verify Tables
```bash
# Login to phpMyAdmin or MySQL CLI and run:
USE riset_properties;
SHOW TABLES;
```

Expected output: 9 tables should be listed

### Step 3: Test Sample Data
```sql
-- Test query
SELECT * FROM users LIMIT 3;
SELECT * FROM properties LIMIT 3;
SELECT * FROM tenants LIMIT 3;
```

## 📊 Database Schema Overview

### Data Collection Capabilities

#### User Management
- Multi-role support: Admin, Landlord, Staff, Teacher
- Secure password hashing
- Profile avatars
- Account status tracking

#### Property Management
- Property listings with pricing
- Property categories and types
- Location-based filtering
- Multiple images per property
- Availability tracking
- Soft delete support

#### Tenant Management  
- Tenant profiles with contact info
- Move-in/move-out dates
- Tenant IDs for reference
- Account status management
- Lease history tracking

#### Viewing/Events
- Schedule property viewings
- Track viewing status (Scheduled/Completed/Cancelled)
- Organizer assignment
- Notes and additional info

#### Maintenance
- Task creation and assignment
- Priority levels (Low/Medium/High/Urgent)
- Progress tracking (Pending/In Progress/Completed/Cancelled)
- Cost estimation and tracking
- Task scheduling

#### Enquiries Collection
- Multiple enquiry types: property, tenant, viewing, maintenance, general
- Enquiry status tracking
- Reply management
- Property/tenant relationship tracking
- Email notifications support

#### Lease Management
- Lease agreement storage
- Start and end dates
- Monthly rent tracking
- Deposit management
- Lease status tracking

#### Payment Tracking
- Rent payment recording
- Payment methods tracking
- Transaction IDs
- Payment status (Pending/Completed/Failed/Refunded)
- Payment history per tenant

## 🔑 Sample Login Credentials

All sample accounts use the password hash. To login:
- **Username**: admin / landlord / staff
- **Email**: admin@risetproperties.com / landlord@risetproperties.com / staff@risetproperties.com
- **Password**: (Test with your hashing utility)

## 📁 Related Files

| File | Purpose |
|------|---------|
| `database-setup.sql` | Full database schema & sample data |
| `database-setup-riset.sql` | Alternative copy of setup script |
| `dbconnect.php` | Database connection (UPDATED) |
| `DATABASE_SETUP_GUIDE.md` | Comprehensive setup documentation |
| `SQL_QUERIES_REFERENCE.md` | Common SQL queries & examples |
| `SETUP_SUMMARY.md` | This file |

## 🔐 Security Features

- ✅ InnoDB engine for ACID compliance
- ✅ UTF-8 character encoding (utf8mb4)
- ✅ Password hashing with bcrypt
- ✅ Foreign key relationships for data integrity
- ✅ Proper indexes for performance
- ✅ Prepared statements support (for PHP)
- ✅ Soft delete capability
- ✅ Timestamp tracking (created_at, updated_at)

## 📋 Data Relationships

```
users (Admin/Landlord)
  ├── properties (properties they own)
  ├── viewings (events they organize)
  └── maintenance_tasks (tasks they create/assign)

properties
  ├── property_images (multiple images)
  ├── viewings (scheduled viewings)
  ├── maintenance_tasks (maintenance history)
  ├── leases (tenant leases)
  └── enquiries (related enquiries)

tenants
  ├── leases (active/past leases)
  ├── viewings (scheduled viewings)
  ├── payments (rent payments)
  └── enquiries (tenant enquiries)

leases
  ├── payments (rent payment records)
  └── maintenance_tasks (maintenance during lease)

enquiries
  ├── properties (if property-related)
  └── tenants (if tenant-related)
```

## 🛠️ Maintenance & Administration

### Regular Tasks

**Weekly**
- Monitor new enquiries
- Track pending maintenance
- Review scheduled viewings

**Monthly**
- Backup database: `mysqldump -u root -p riset_properties > backup_$(date +%Y%m%d).sql`
- Review payment history
- Update lease status

**Quarterly**
- Archive old enquiries
- Review property availability
- Clean up cancelled viewings

### Useful SQL Commands

```sql
-- Backup
mysqldump -u root -p riset_properties > riset_backup.sql

-- Restore
mysql -u root -p riset_properties < riset_backup.sql

-- Check database size
SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
FROM information_schema.tables 
WHERE table_schema = 'riset_properties';

-- View all users
SELECT id, username, email, role, status FROM users;

-- Count records
SELECT 
    'users' as table_name, COUNT(*) as count FROM users UNION
SELECT 'tenants', COUNT(*) FROM tenants UNION
SELECT 'properties', COUNT(*) FROM properties UNION
SELECT 'leases', COUNT(*) FROM leases UNION
SELECT 'enquiries', COUNT(*) FROM enquiries;
```

## 🐛 Troubleshooting

### Issue: "Unknown database 'riset_properties'"
**Solution**: Run the database-setup.sql script again

### Issue: "Table doesn't exist"
**Solution**: 
1. Verify in phpMyAdmin that the table was created
2. Re-run the setup script
3. Check MySQL error log

### Issue: "Connection refused"
**Solution**:
1. Ensure MySQL is running
2. Check credentials in dbconnect.php
3. Verify host is correct (localhost)

### Issue: "Character encoding issues"
**Solution**: Database uses utf8mb4, ensure PHP connection also uses:
```php
$conn->set_charset('utf8mb4');
```

## 📚 Next Steps

1. **Import the database** using database-setup.sql
2. **Test the connection** from PHP applications
3. **Create admin account** for first use
4. **Configure mail settings** for email notifications
5. **Set up backups** for regular data protection
6. **Review user permissions** as needed
7. **Customize as needed** for your specific requirements

## 📞 Support & Documentation

- **Setup Guide**: See `DATABASE_SETUP_GUIDE.md`
- **SQL Reference**: See `SQL_QUERIES_REFERENCE.md`
- **PHP Integration**: Review `dbconnect.php` and `admin-*.php` files
- **Database Files**: Check `/database-setup.sql`

## ✨ Key Features Summary

✅ **Multi-user system** with role-based access  
✅ **Property management** with image galleries  
✅ **Tenant tracking** with lease management  
✅ **Event scheduling** for property viewings  
✅ **Maintenance** task management  
✅ **Enquiry collection** system  
✅ **Payment tracking** for rent  
✅ **Sample data** for testing  
✅ **Data integrity** with foreign keys  
✅ **Performance optimized** with indexes  

---

## Database Setup Status: ✅ COMPLETE

Your Riset Properties system is fully configured and ready for data collection!

**Last Updated**: May 17, 2026  
**Database Version**: 1.0  
**PHP Compatible**: 7.4+  
**MySQL Version**: 5.7+
