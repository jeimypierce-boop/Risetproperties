# Landlord Account Management System

## Overview
This document describes the new admin-enabled landlord account management system for the Riset Properties platform. It allows administrators to create, manage, and control independent landlord accounts within the system.

## Features

### 1. **Create Independent Landlord Accounts**
- Admin users can create new landlord accounts directly from the admin panel
- Each landlord gets a unique username, email, and password
- Landlords can own properties and manage tenants
- Each landlord account is completely independent

### 2. **Manage Landlord Accounts**
- View all landlord accounts in a centralized dashboard
- Monitor properties owned by each landlord
- Track monthly revenue for each landlord
- Activate/deactivate landlord accounts
- Delete landlord accounts (only if they have no active properties)

### 3. **Role-Based Access Control**
- Only users with `admin` role can create/manage landlords
- Landlords cannot create other landlords
- Landlords can only see their own properties and tenants
- Clear separation of administrative and landlord functions

## Database Structure

### Users Table
The `users` table stores all admin, landlord, staff, and teacher accounts:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'landlord', 'staff', 'teacher') DEFAULT 'staff',
    status VARCHAR(50) DEFAULT 'Active',
    avatar VARCHAR(255) DEFAULT 'images/user/1.png',
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Relationship with Properties
Each property is linked to a landlord via the `landlord_id` foreign key:

```sql
ALTER TABLE properties ADD COLUMN landlord_id INT;
ALTER TABLE properties ADD FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE SET NULL;
```

## Setup Instructions

### Step 1: Update Database Schema
Run the database updates to ensure all necessary columns exist:

```bash
mysql -u root riset_properties < database-updates-landlord-management.sql
```

Or use phpMyAdmin:
1. Open phpMyAdmin
2. Select the `riset_properties` database
3. Go to the SQL tab
4. Paste the contents of `database-updates-landlord-management.sql`
5. Click Execute

### Step 2: Create Admin Account
If you don't have an admin account yet, create one:

```sql
INSERT INTO users (username, email, first_name, last_name, phone, password, role, status)
VALUES ('admin', 'admin@risetproperties.com', 'Admin', 'User', '254712345678', 
        '$2y$10$...', 'admin', 'Active');
```

Note: Generate the password hash using PHP:
```php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

### Step 3: Access Landlord Management
1. Login to the admin panel with your admin credentials
2. Click "Landlords" in the sidebar menu
3. Click "Add New Landlord" to create a new landlord account

## Usage Guide

### Creating a New Landlord Account

1. Navigate to: **Admin Dashboard → Landlords → Add New Landlord**
2. Fill in the following information:
   - **Username**: Unique identifier for login (min. 3 characters)
   - **Email**: Landlord's email address (must be unique)
   - **First Name**: Landlord's first name
   - **Last Name**: Landlord's last name
   - **Phone Number**: Contact phone number
   - **Password**: Strong password (min. 6 characters)
   - **Confirm Password**: Verify password
   - **Account Status**: Active or Inactive

3. Click "Create Landlord Account"
4. The landlord can now login with their username and password

### Managing Landlord Accounts

1. Navigate to: **Admin Dashboard → Landlords**
2. View all landlord accounts with their details:
   - Properties owned
   - Monthly revenue
   - Account status
   - Creation date

3. Available actions:
   - **Activate/Deactivate**: Toggle landlord account status
   - **Delete**: Remove landlord account (only if no active properties)

### Landlord Login

Landlords can login using:
- Their username
- Their email address
- Their phone number

After login, they will see the admin dashboard filtered to show only their properties and tenants.

## Access Control

### Admin Permissions
- ✅ Create landlord accounts
- ✅ View all landlords
- ✅ Edit landlord accounts
- ✅ Activate/deactivate landlords
- ✅ Delete landlords (if no properties)
- ✅ View all properties globally
- ✅ Create properties for any landlord

### Landlord Permissions
- ✅ Login to admin panel
- ✅ Create/edit own properties
- ✅ Add/manage own tenants
- ✅ View own leases
- ✅ View own rent payments
- ✅ View maintenance tasks for own properties
- ❌ Create other landlords
- ❌ View other landlords' data
- ❌ Access admin settings

## API Endpoints / Admin Pages

### Admin Landlord Management Pages

- **`admin-landlord-all.php`**: View all landlord accounts
  - Access: Admin only
  - Features: List, activate/deactivate, delete landlords
  
- **`admin-landlord-add.php`**: Create new landlord account
  - Access: Admin only
  - Features: Form to create new landlord with validation

- **`admin-dashboard-modern.php`**: Updated dashboard
  - Now includes "Landlords" menu item for admins
  - Menu only visible to admin users (not landlords)

## Security Features

1. **Password Hashing**: All passwords are hashed using PHP's `PASSWORD_DEFAULT` algorithm
2. **Input Validation**: All inputs are validated and sanitized
3. **SQL Injection Prevention**: All database queries use prepared statements
4. **Role-Based Access**: Access to landlord management is restricted to admin users
5. **Account Status**: Landlords can be deactivated without deleting their data
6. **Property Protection**: Landlords cannot be deleted if they have active properties

## Error Handling

### Common Errors and Solutions

**Error: "Username already exists"**
- Solution: Choose a different, unique username for the new landlord

**Error: "Email already exists"**
- Solution: Use a unique email address not already in the system

**Error: "Cannot delete landlord with active properties"**
- Solution: Archive or delete all properties owned by the landlord first

**Error: "Password must be at least 6 characters"**
- Solution: Enter a longer password (minimum 6 characters)

## Database Queries Reference

### Get all landlords
```sql
SELECT * FROM users WHERE role = 'landlord' ORDER BY created_at DESC;
```

### Get landlord with property count
```sql
SELECT u.*, COUNT(p.id) as property_count 
FROM users u 
LEFT JOIN properties p ON u.id = p.landlord_id 
WHERE u.role = 'landlord' 
GROUP BY u.id;
```

### Get landlord with monthly revenue
```sql
SELECT u.id, u.username, u.email,
  COALESCE(SUM(l.monthly_rent), 0) as monthly_revenue
FROM users u 
LEFT JOIN properties p ON u.id = p.landlord_id 
LEFT JOIN leases l ON p.id = l.property_id AND l.status = 'Active'
WHERE u.role = 'landlord' 
GROUP BY u.id;
```

### Deactivate a landlord
```sql
UPDATE users SET status = 'Inactive' WHERE id = ? AND role = 'landlord';
```

### Delete a landlord (with property check)
```sql
DELETE FROM users 
WHERE id = ? AND role = 'landlord' AND 
  id NOT IN (SELECT DISTINCT landlord_id FROM properties WHERE deleted_at IS NULL);
```

## Important Notes

1. **Independent Accounts**: Each landlord is completely independent and can only see their own data
2. **No Property Sharing**: Properties cannot be shared between landlords
3. **Cascading Delete**: When a landlord is deleted, their properties' landlord_id is set to NULL
4. **Status vs Delete**: Use deactivation instead of deletion to preserve data history
5. **Revenue Tracking**: Monthly revenue is automatically calculated based on active leases

## Troubleshooting

### Landlord cannot login
- Check if the account status is "Active"
- Verify username, email, or phone is correct
- Ensure password is correct (case-sensitive)
- Check if the user role is set to "landlord"

### Landlord sees empty dashboard
- Check if they have created any properties
- Verify properties are assigned to their user ID
- Ensure the landlord's account is active

### Menu item "Landlords" not showing
- Verify you are logged in as an admin user (role = 'admin')
- Check the admin-dashboard-modern.php for the menu condition
- Try refreshing the page

## Files Included

1. **admin-landlord-add.php** - Create new landlord accounts
2. **admin-landlord-all.php** - Manage all landlord accounts
3. **database-updates-landlord-management.sql** - Database schema updates
4. **LANDLORD_MANAGEMENT_GUIDE.md** - This file

## Future Enhancements

Potential features to add:
- Landlord profile editing by admin
- Landlord performance analytics
- Commission/revenue sharing settings
- Landlord bulk operations
- Landlord export/import functionality
- Landlord activity logs
- Automated email notifications for new landlords
- API endpoints for landlord management

## Support

For issues or questions about the landlord management system, please review:
1. This documentation
2. Database error logs
3. Application error logs
4. Browser console for JavaScript errors

## Version History

- **v1.0** (Current) - Initial landlord management system
  - Admin can create landlord accounts
  - Admin can manage landlord accounts
  - Landlords can login independently
  - Role-based access control implemented
