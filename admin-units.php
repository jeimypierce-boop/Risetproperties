<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

require_login();

$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$furnishedFilter = isset($_GET['furnished']) ? trim($_GET['furnished']) : '';
$parkingFilter = isset($_GET['parking']) ? trim($_GET['parking']) : '';
$tenantFilter = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;

if (!$property_id) {
    header('Location: admin-properties.php');
    exit;
}

// Get property details
$property_stmt = $conn->prepare("SELECT p.*, COUNT(u.id) as total_units FROM properties p LEFT JOIN units u ON p.id = u.property_id WHERE p.id = ?");
$property_stmt->bind_param('i', $property_id);
$property_stmt->execute();
$property_result = $property_stmt->get_result();

if ($property_result->num_rows === 0) {
    header('Location: admin-properties.php');
    exit;
}

$property = $property_result->fetch_assoc();
$property_stmt->close();

// Check if user has access to this property
$landlord_id = get_landlord_id();
if ($landlord_id && $property['landlord_id'] != $landlord_id) {
    header('Location: admin-properties.php');
    exit;
}

// Build filter conditions

$filters = ["u.property_id = " . intval($property_id)];

if ($statusFilter) {
    $filters[] = "u.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

if ($furnishedFilter !== '') {
    if ($furnishedFilter === '1') $filters[] = "u.furnished = 1"; elseif ($furnishedFilter === '0') $filters[] = "u.furnished = 0";
}

if ($parkingFilter !== '') {
    if ($parkingFilter === '1') $filters[] = "u.parking = 1"; elseif ($parkingFilter === '0') $filters[] = "u.parking = 0";
}

if ($tenantFilter) {
    $filters[] = "u.tenant_id = " . intval($tenantFilter);
}

if ($searchQuery) {
    $escapedSearch = $conn->real_escape_string($searchQuery);
    $filters[] = "(u.unit_name LIKE '%" . $escapedSearch . "%' OR u.unit_number LIKE '%" . $escapedSearch . "%' OR u.description LIKE '%" . $escapedSearch . "%' OR u.features LIKE '%" . $escapedSearch . "%')";
}

$whereClause = implode(' AND ', $filters);

// Fetch units
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM leases WHERE unit_id = u.id AND status = 'Active') as active_leases
        FROM units u 
        WHERE $whereClause 
        ORDER BY u.unit_number ASC";

$result = $conn->query($sql);

if ($result === false) {
    die('Database query error: ' . $conn->error);
}

$units = [];
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}

// Get unit statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
    SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
    SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as reserved,
    SUM(CASE WHEN furnished = 1 THEN 1 ELSE 0 END) as furnished_count,
    SUM(CASE WHEN parking = 1 THEN 1 ELSE 0 END) as parking_count,
    SUM(monthly_rent) as total_monthly_revenue
    FROM units 
    WHERE property_id = " . intval($property_id);

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Units - <?php echo htmlspecialchars($property['title']); ?> - Riset Property Management</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Manage units for property in Riset Property Management System">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/style-mob.css" rel="stylesheet" />
    <style>
        .units-container {
            padding: 20px 0;
        }
        .unit-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 15px;
        }
        .unit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .unit-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .unit-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .unit-number {
            font-size: 13px;
            color: #666;
            margin-top: 3px;
        }
        .unit-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        .status-occupied {
            background: #cfe2ff;
            color: #084298;
        }
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        .status-reserved {
            background: #e2e3e5;
            color: #383d41;
        }
        .unit-body {
            padding: 15px;
        }
        .unit-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: #666;
            font-weight: 600;
        }
        .meta-value {
            color: #333;
            margin-top: 2px;
        }
        .unit-footer {
            padding: 12px 15px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .unit-footer a {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-edit {
            background: #007bff;
            color: white;
        }
        .btn-edit:hover {
            background: #0056b3;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2f80ed;
            margin-top: 8px;
        }
        .stat-value.occupied { color: #ff6b6b; }
        .stat-value.available { color: #51cf66; }
        .stat-value.maintenance { color: #ffa94d; }
        .stat-value.reserved { color: #a29bfe; }
        .no-units {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            color: #666;
        }
        .no-units i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container-fluid sb1">
        <div class="row">
            <div class="col-md-2 col-sm-3 col-xs-6 sb1-1">
                <a href="#" class="btn-close-menu"><i class="fa fa-times" aria-hidden="true"></i></a>
                <a href="#" class="atab-menu"><i class="fa fa-bars tab-menu" aria-hidden="true"></i></a>
                <a href="admin-dashboard-modern.php" class="logo"><img src="images/logo1.png" alt="Logo" /></a>
            </div>
            <div class="col-md-10 col-sm-9 col-xs-6">
                <div class="sb1-nav1">
                    <h4>Units</h4>
                    <ul>
                        <li><a href="admin-properties.php"><i class="fa fa-arrow-left"></i> Back to Properties</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid sb1-main">
        <div class="row">
            <div class="col-md-2 col-sm-3 col-xs-6 sb1-2">
                <?php include 'nav_helper.php'; ?>
            </div>
            <div class="col-md-10 col-sm-9 col-xs-12 sb1-3">
                <div style="padding: 20px;">
                    <!-- Property Header -->
                    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h2 style="margin: 0 0 8px 0;"><?php echo htmlspecialchars($property['title']); ?></h2>
                                <p style="margin: 0; color: #666; font-size: 13px;">
                                    <strong>Location:</strong> <?php echo htmlspecialchars($property['location']); ?>, <?php echo htmlspecialchars($property['city']); ?>
                                </p>
                            </div>
                            <a href="admin-add-unit.php?property_id=<?php echo $property_id; ?>" class="btn btn-primary" style="white-space: nowrap;">
                                <i class="fa fa-plus"></i> Add New Unit
                            </a>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Units</div>
                            <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Available</div>
                            <div class="stat-value available"><?php echo $stats['available'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Occupied</div>
                            <div class="stat-value occupied"><?php echo $stats['occupied'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Maintenance</div>
                            <div class="stat-value maintenance"><?php echo $stats['maintenance'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Reserved</div>
                            <div class="stat-value reserved"><?php echo $stats['reserved'] ?? 0; ?></div>
                        </div>
                            <div class="stat-card">
                                <div class="stat-label">Furnished</div>
                                <div class="stat-value"><?php echo $stats['furnished_count'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">With Parking</div>
                                <div class="stat-value"><?php echo $stats['parking_count'] ?? 0; ?></div>
                            </div>
                        <div class="stat-card">
                            <div class="stat-label">Monthly Revenue (Est.)</div>
                            <div class="stat-value" style="color: #2f80ed;">KES <?php echo number_format($stats['total_monthly_revenue'] ?? 0, 0); ?></div>
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <form method="GET" style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, number, features..." class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Status</label>
                                <select name="status" class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">All Status</option>
                                    <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="Occupied" <?php echo $statusFilter === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                                    <option value="Maintenance" <?php echo $statusFilter === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="Reserved" <?php echo $statusFilter === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Furnished</label>
                                <select name="furnished" class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">Any</option>
                                    <option value="1" <?php echo $furnishedFilter === '1' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="0" <?php echo $furnishedFilter === '0' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Parking</label>
                                <select name="parking" class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">Any</option>
                                    <option value="1" <?php echo $parkingFilter === '1' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="0" <?php echo $parkingFilter === '0' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Tenant</label>
                                <select name="tenant_id" class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="0">Any</option>
                                    <?php
                                    $tresult = $conn->query("SELECT id, first_name, last_name, email FROM tenants ORDER BY first_name");
                                    while ($tr = $tresult->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $tr['id']; ?>" <?php echo ($tenantFilter == $tr['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tr['first_name'] . ' ' . $tr['last_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1; display:flex; gap:8px; justify-content:flex-end;">
                                <button type="submit" class="btn btn-info" style="padding: 8px 16px; border: 0; border-radius: 4px;">
                                    <i class="fa fa-search"></i> Filter
                                </button>
                                <a href="?property_id=<?php echo $property_id; ?>" class="btn btn-default" style="padding: 8px 16px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; display: inline-block;">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Units List -->
                    <div class="units-container">
                        <?php if (empty($units)): ?>
                            <div class="no-units">
                                <i class="fa fa-inbox"></i>
                                <h3>No units found</h3>
                                <p>Start by adding your first unit to this property.</p>
                                <a href="admin-add-unit.php?property_id=<?php echo $property_id; ?>" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fa fa-plus"></i> Add First Unit
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($units as $unit): ?>
                                <div class="unit-card">
                                    <div class="unit-header">
                                        <div>
                                            <div class="unit-title"><?php echo htmlspecialchars($unit['unit_name']); ?></div>
                                            <div class="unit-number"><?php echo htmlspecialchars($unit['unit_number'] ?? 'N/A'); ?></div>
                                        </div>
                                        <span class="unit-status status-<?php echo strtolower($unit['status']); ?>">
                                            <?php echo htmlspecialchars($unit['status']); ?>
                                        </span>
                                    </div>

                                    <div class="unit-body">
                                        <div class="unit-meta">
                                            <div class="meta-item">
                                                <div class="meta-label">Type</div>
                                                <div class="meta-value"><?php echo htmlspecialchars($unit['unit_type'] ?? 'N/A'); ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Bedrooms</div>
                                                <div class="meta-value"><?php echo $unit['bedrooms'] ?? '0'; ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Bathrooms</div>
                                                <div class="meta-value"><?php echo $unit['bathrooms'] ?? '0'; ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Furnished</div>
                                                <div class="meta-value"><?php echo (!empty($unit['furnished']) ? 'Yes' : 'No'); ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Parking</div>
                                                <div class="meta-value"><?php echo (!empty($unit['parking']) ? 'Yes' : 'No'); ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Utilities</div>
                                                <div class="meta-value"><?php echo htmlspecialchars($unit['utilities_included'] ?? 'N/A'); ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Monthly Rent</div>
                                                <div class="meta-value">KES <?php echo number_format($unit['monthly_rent'] ?? 0, 0); ?></div>
                                            </div>
                                            <div class="meta-item">
                                                <div class="meta-label">Deposit</div>
                                                <div class="meta-value">KES <?php echo number_format($unit['deposit'] ?? 0, 0); ?></div>
                                            </div>
                                        </div>
                                        <?php if ($unit['description']): ?>
                                            <p style="margin: 10px 0 0 0; color: #666; font-size: 13px;">
                                                <?php echo htmlspecialchars(substr($unit['description'], 0, 100)); ?><?php echo strlen($unit['description']) > 100 ? '...' : ''; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="unit-footer">
                                        <a href="admin-edit-unit.php?unit_id=<?php echo $unit['id']; ?>&property_id=<?php echo $property_id; ?>" class="btn-edit">
                                            <i class="fa fa-edit"></i> Edit
                                        </a>
                                        <a href="#" onclick="deleteUnit(<?php echo $unit['id']; ?>, <?php echo $property_id; ?>)" class="btn-delete">
                                            <i class="fa fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function deleteUnit(unitId, propertyId) {
        if (confirm('Are you sure you want to delete this unit? This action cannot be undone.')) {
            fetch('api/delete_unit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'unit_id=' + encodeURIComponent(unitId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Unit deleted successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unable to delete unit'));
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    }
    </script>
</body>
</html>
