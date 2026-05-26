# Landlord Management System - Quick Start Guide

## 🚀 Quick Implementation Steps

### Step 1: Update Your Database
Copy and run this SQL to add missing columns to the users table:

```sql
-- Add last_login column if missing
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL;

-- Verify status column exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Active';

-- Verify avatar column exists  
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT 'images/user/1.png';
```

**How to run:**
- **Via phpMyAdmin**: Select database → SQL tab → Paste SQL → Execute
- **Via Command Line**: `mysql -u root riset_properties < database-updates-landlord-management.sql`

### Step 2: Create Admin Account (if needed)
If you don't have an admin account, create one:

```sql
-- Generate password hash first
-- In PHP: echo password_hash('admin123', PASSWORD_DEFAULT);
-- This will give you a hash like: $2y$10$...

INSERT INTO users (username, email, first_name, last_name, phone, password, role, status)
VALUES ('admin', 'admin@example.com', 'Admin', 'User', '254712345678', 
        '$2y$10$...[your_hash_here]...', 'admin', 'Active');
```

### Step 3: Verify Files Are in Place
Check that these files exist in your main directory:
- ✅ `admin-landlord-add.php` - Create landlords
- ✅ `admin-landlord-all.php` - Manage landlords
- ✅ `LANDLORD_MANAGEMENT_GUIDE.md` - Full documentation

### Step 4: Login and Test
1. Go to `http://localhost/RisetProperties-Website-and-AdminPanel/login.php`
2. Login with admin credentials (username: `admin`, password: `admin123`)
3. You should see "Landlords" in the left sidebar menu
4. Click "Landlords" → "Add New Landlord" to create your first landlord

## 📋 Create Your First Landlord

1. Click **Landlords** in sidebar
2. Click **Add New Landlord**
3. Fill in the form:
   - Username: `landlord1` (must be unique)
   - Email: `landlord1@example.com` (must be unique)
   - First Name: `John`
   - Last Name: `Doe`
   - Phone: `254712345678`
   - Password: `password123` (min 6 characters)
   - Confirm Password: `password123`
   - Status: `Active`
4. Click **Create Landlord Account**

## 🔐 Landlord Login Test

1. Go to login page
2. Enter landlord username: `landlord1`
3. Enter password: `password123`
4. Click Login
5. You should see the admin dashboard (filtered for their properties)

## 👥 Manage Landlords

### View All Landlords
1. Click **Landlords** in sidebar
2. See all landlords with:
   - Username and contact info
   - Number of properties
   - Monthly revenue
   - Account status
   - Creation date

### Activate/Deactivate Landlord
1. Go to **Landlords** page
2. Click **Lock/Unlock** button for any landlord
3. Confirm the action

### Delete Landlord
1. Go to **Landlords** page
2. Click **Delete** button (Trash icon)
3. Note: Can only delete if landlord has NO active properties
4. Confirm deletion

## 📊 What Landlords Can See

When a landlord logs in, they can:
- ✅ Add properties (only visible to them)
- ✅ Add tenants (only for their properties)
- ✅ Manage leases (only for their properties)
- ✅ View rent payments (only for their properties)
- ❌ See other landlords' data
- ❌ Create other landlord accounts
- ❌ Access admin settings

## 🎯 Key Features

### 1. Independent Accounts
Each landlord has a completely separate account:
- Their own properties (only they see)
- Their own tenants (only they see)
- Their own rent payments (only they see)
- Their own revenue tracking

### 2. Easy Management
Admins can:
- Create multiple landlords
- Activate/deactivate accounts
- Monitor properties per landlord
- Track monthly revenue

### 3. Secure
- Passwords are hashed
- Role-based access control
- Admin-only landlord creation
- Input validation on all forms

## 🆘 Troubleshooting

### "Cannot delete landlord with active properties"
**Problem**: Trying to delete a landlord who owns properties
**Solution**: 
1. Delete/archive their properties first
2. Then delete the landlord account

### Landlord can't login
**Problem**: Login fails with correct credentials
**Solution**:
1. Check account status is "Active" (not "Inactive")
2. Check email is unique in the users table
3. Verify role is set to "landlord"

### "Landlords" menu not showing
**Problem**: Don't see Landlords in menu
**Solution**:
1. Make sure you're logged in as ADMIN (not landlord)
2. Admin role must be exactly "admin" in database

## 📚 Database Schema

### Users Table (Relevant Fields)
```
id           - Unique ID
username     - Login username (unique)
email        - Email address (unique)
role         - 'admin', 'landlord', 'staff', or 'teacher'
password     - Hashed password
status       - 'Active' or 'Inactive'
created_at   - When account was created
```

### Properties Table (Relevant Field)
```
landlord_id  - References users.id (the property owner)
```

## 🔗 Links to Management Pages

After setup, access these pages:
- Admin Dashboard: `http://localhost/.../admin-dashboard-modern.php`
- All Landlords: `http://localhost/.../admin-landlord-all.php`
- Add Landlord: `http://localhost/.../admin-landlord-add.php`

## ✅ Verification Checklist

- [ ] Database tables updated
- [ ] Admin account created
- [ ] `admin-landlord-add.php` file in place
- [ ] `admin-landlord-all.php` file in place
- [ ] Can login as admin
- [ ] "Landlords" menu visible in admin dashboard
- [ ] Can create new landlord account
- [ ] Can see landlords in list
- [ ] Landlord can login successfully
- [ ] Landlord sees empty dashboard (no properties yet)

## 🎓 Next Steps

After setup:

1. **Create Properties**
   - Have each landlord login
   - Create properties under their account
   - Only that landlord will see their properties

2. **Add Tenants**
   - Landlords can add tenants to their properties
   - Create leases for tenants
   - Track rent payments

3. **Monitor Performance**
   - Use Admin dashboard to see all landlords
   - Monitor properties and revenue
   - Manage accounts as needed

## 📞 Support

For detailed information, see: `LANDLORD_MANAGEMENT_GUIDE.md`

For database schema details, see: `DATABASE_SETUP_GUIDE.md`

---

**System**: Riset Properties - Landlord Management System v1.0
**Last Updated**: 2026-05-20
