# Riset Properties - SQL Quick Reference

## Quick Setup Commands

### 1. Create Database and All Tables
```bash
# From terminal
mysql -u root -p < database-setup.sql

# OR manually in phpMyAdmin
```

### 2. Test Connection
```sql
USE riset_properties;
SELECT COUNT(*) FROM users;
```

## Common Queries for Data Collection

### User Management

#### Add New Admin User
```sql
INSERT INTO users (username, email, first_name, last_name, phone, password, role, status) 
VALUES ('newadmin', 'admin@company.com', 'Admin', 'Name', '+254700000000', SHA2('password', 256), 'admin', 'Active');
```

#### View All Active Users
```sql
SELECT id, username, email, role, status FROM users WHERE status = 'Active' ORDER BY created_at DESC;
```

#### Update User Status
```sql
UPDATE users SET status = 'Inactive' WHERE id = 1;
```

### Tenant Management

#### Add New Tenant
```sql
INSERT INTO tenants (first_name, last_name, phone, email, city, country, tenant_id, date_of_birth, status) 
VALUES ('John', 'Doe', '+254700111111', 'john@example.com', 'Nairobi', 'Kenya', 'TN001', '1990-01-01', 'Active');
```

#### View All Tenants
```sql
SELECT * FROM tenants WHERE status = 'Active' ORDER BY last_name, first_name;
```

#### Find Tenant by Email
```sql
SELECT * FROM tenants WHERE email = 'john@example.com';
```

#### Count Tenants by City
```sql
SELECT city, COUNT(*) as total FROM tenants GROUP BY city;
```

### Property Management

#### Add New Property
```sql
INSERT INTO properties (title, description, property_type, bedrooms, bathrooms, price, currency, location, city, country, landlord_id, status, contact_person, contact_phone) 
VALUES ('3-bed Apartment', 'Modern apartment', 'Apartment', 3, 2, 85000, 'KES', 'Westlands', 'Nairobi', 'Kenya', 2, 'Available', 'James Wambua', '+254701234567');
```

#### List All Available Properties
```sql
SELECT id, title, bedrooms, bathrooms, price, location, status FROM properties WHERE status = 'Available' ORDER BY price DESC;
```

#### Properties by City
```sql
SELECT city, COUNT(*) as total, AVG(price) as avg_price FROM properties WHERE status = 'Available' GROUP BY city;
```

#### Update Property Status
```sql
UPDATE properties SET status = 'Occupied' WHERE id = 1;
```

#### Soft Delete Property
```sql
UPDATE properties SET deleted_at = NOW() WHERE id = 1;
```

### Viewing/Event Management

#### Schedule New Viewing
```sql
INSERT INTO viewings (property_id, tenant_id, viewing_date, viewing_time, status, organizer_id) 
VALUES (1, 1, '2026-06-20', '10:00:00', 'Scheduled', 2);
```

#### Get Scheduled Viewings
```sql
SELECT v.id, p.title, t.first_name, t.last_name, v.viewing_date, v.viewing_time, v.status 
FROM viewings v 
JOIN properties p ON v.property_id = p.id 
JOIN tenants t ON v.tenant_id = t.id 
WHERE v.viewing_date >= CURDATE() 
ORDER BY v.viewing_date, v.viewing_time;
```

#### Mark Viewing as Completed
```sql
UPDATE viewings SET status = 'Completed' WHERE id = 1;
```

### Enquiry Management

#### Get All New Enquiries
```sql
SELECT id, enquiry_type, name, email, subject, created_at FROM enquiries WHERE status = 'New' ORDER BY created_at DESC;
```

#### Get Property Enquiries
```sql
SELECT e.id, e.name, e.email, p.title, e.subject, e.created_at 
FROM enquiries e 
LEFT JOIN properties p ON e.property_id = p.id 
WHERE e.enquiry_type = 'property' 
ORDER BY e.created_at DESC;
```

#### Mark Enquiry as In Progress
```sql
UPDATE enquiries SET status = 'In Progress', updated_at = NOW() WHERE id = 1;
```

#### Reply to Enquiry
```sql
UPDATE enquiries 
SET status = 'Resolved', replied = 1, reply_message = 'Thank you for your enquiry...', replied_by = 1, replied_at = NOW() 
WHERE id = 1;
```

### Maintenance Management

#### Create Maintenance Task
```sql
INSERT INTO maintenance_tasks (property_id, title, description, task_type, priority, status, assigned_to, scheduled_date, created_by) 
VALUES (1, 'Fix broken window', 'Master bedroom window', 'Repair', 'Medium', 'Pending', 3, '2026-06-15', 2);
```

#### Get Pending Maintenance
```sql
SELECT m.id, p.title, m.title as task, m.priority, m.status, u.first_name as assigned_to 
FROM maintenance_tasks m 
JOIN properties p ON m.property_id = p.id 
LEFT JOIN users u ON m.assigned_to = u.id 
WHERE m.status = 'Pending' 
ORDER BY m.priority DESC, m.scheduled_date;
```

#### Update Task Status
```sql
UPDATE maintenance_tasks SET status = 'Completed', completion_date = NOW() WHERE id = 1;
```

### Lease Management

#### Create New Lease
```sql
INSERT INTO leases (tenant_id, property_id, lease_start_date, lease_end_date, monthly_rent, deposit, status) 
VALUES (1, 1, '2026-06-01', '2027-06-01', 85000, 85000, 'Active');
```

#### Get Active Leases
```sql
SELECT l.id, t.first_name, t.last_name, p.title, l.lease_start_date, l.lease_end_date, l.monthly_rent, l.status 
FROM leases l 
JOIN tenants t ON l.tenant_id = t.id 
JOIN properties p ON l.property_id = p.id 
WHERE l.status = 'Active' 
ORDER BY l.lease_end_date;
```

#### Leases Expiring Soon (30 days)
```sql
SELECT l.id, t.first_name, t.last_name, p.title, l.lease_end_date 
FROM leases l 
JOIN tenants t ON l.tenant_id = t.id 
JOIN properties p ON l.property_id = p.id 
WHERE l.status = 'Active' 
AND l.lease_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
ORDER BY l.lease_end_date;
```

### Payment Management

#### Record Rent Payment
```sql
INSERT INTO payments (lease_id, tenant_id, property_id, amount, payment_date, payment_method, status) 
VALUES (1, 1, 1, 85000, CURDATE(), 'Bank Transfer', 'Completed');
```

#### Get Payment History
```sql
SELECT p.id, t.first_name, t.last_name, pr.title, p.amount, p.payment_date, p.status 
FROM payments p 
JOIN tenants t ON p.tenant_id = t.id 
JOIN properties pr ON p.property_id = pr.id 
ORDER BY p.payment_date DESC;
```

#### Pending Payments
```sql
SELECT p.id, t.first_name, t.last_name, pr.title, l.monthly_rent, DATEDIFF(CURDATE(), p.payment_date) as days_overdue 
FROM payments p 
JOIN tenants t ON p.tenant_id = t.id 
JOIN properties pr ON p.property_id = pr.id 
JOIN leases l ON p.lease_id = l.id 
WHERE p.status = 'Pending' 
ORDER BY p.payment_date;
```

## Reporting Queries

### Dashboard Statistics

#### Total Statistics
```sql
SELECT 
    (SELECT COUNT(*) FROM properties WHERE status = 'Available') as available_properties,
    (SELECT COUNT(*) FROM properties WHERE status = 'Occupied') as occupied_properties,
    (SELECT COUNT(*) FROM tenants WHERE status = 'Active') as active_tenants,
    (SELECT COUNT(*) FROM enquiries WHERE status = 'New') as new_enquiries;
```

#### Revenue Report
```sql
SELECT 
    DATE_FORMAT(payment_date, '%Y-%m') as month,
    SUM(amount) as total_collected,
    COUNT(*) as transactions
FROM payments 
WHERE status = 'Completed'
GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
ORDER BY month DESC;
```

#### Property Occupancy Rate
```sql
SELECT 
    (SELECT COUNT(*) FROM properties WHERE status = 'Occupied') * 100.0 / COUNT(*) as occupancy_rate
FROM properties;
```

## Data Maintenance Queries

### Delete Old Enquiries (>1 year, mark as Closed)
```sql
UPDATE enquiries SET deleted_at = NOW() WHERE status = 'Closed' AND created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Archive Completed Tasks
```sql
UPDATE maintenance_tasks SET status = 'Cancelled' WHERE status = 'Completed' AND completion_date < DATE_SUB(NOW(), INTERVAL 3 MONTH);
```

### Backup Database
```bash
mysqldump -u root -p riset_properties > riset_backup_$(date +%Y%m%d).sql
```

## Useful Views (Optional)

### Create Property Statistics View
```sql
CREATE VIEW property_statistics AS
SELECT 
    property_type,
    COUNT(*) as count,
    AVG(price) as avg_price,
    MIN(price) as min_price,
    MAX(price) as max_price
FROM properties
WHERE deleted_at IS NULL
GROUP BY property_type;
```

### Create Tenant Lease Summary View
```sql
CREATE VIEW tenant_lease_summary AS
SELECT 
    t.tenant_id,
    CONCAT(t.first_name, ' ', t.last_name) as tenant_name,
    COUNT(l.id) as active_leases,
    SUM(l.monthly_rent) as total_rent
FROM tenants t
LEFT JOIN leases l ON t.id = l.tenant_id AND l.status = 'Active'
GROUP BY t.id;
```

## Connection Info for PHP Scripts

```php
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset('utf8mb4');

// Execute query
$result = $conn->query($sql);

// Error handling
if (!$result) {
    die('Database error: ' . $conn->error);
}
```

---
**Note**: Replace table/column names as needed for your specific implementation. All timestamps are stored in UTC/CURRENT_TIMESTAMP format.
