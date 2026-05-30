<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$filters = [];
if ($landlord_id) {
    $filters[] = "p.landlord_id = " . intval($landlord_id);
}
if ($statusFilter) {
    $allowedUnitStatuses = ['Available', 'Occupied', 'Maintenance', 'Reserved'];
    $escapedStatus = $conn->real_escape_string($statusFilter);
    if (in_array($statusFilter, $allowedUnitStatuses, true)) {
        $filters[] = "EXISTS (SELECT 1 FROM units u WHERE u.property_id = p.id AND u.status = '" . $escapedStatus . "')";
    } else {
        $filters[] = "p.status = '" . $escapedStatus . "'";
    }
}
if ($searchQuery) {
    $escapedSearch = $conn->real_escape_string($searchQuery);
    $filters[] = "(p.title LIKE '%" . $escapedSearch . "%' OR p.city LIKE '%" . $escapedSearch . "%' OR p.location LIKE '%" . $escapedSearch . "%' OR p.description LIKE '%" . $escapedSearch . "%')";
}
$whereClause = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';

// Fetch properties with occupancy data from units
$sql = "SELECT p.*, 
    IFNULL(t.total_units, 0) AS total_units,
    IFNULL(t.occupied_units, 0) AS occupied_units,
    IFNULL(t.vacant_units, 0) AS vacant_units
FROM properties p
LEFT JOIN (
    SELECT property_id,
        COUNT(*) AS total_units,
        SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) AS occupied_units,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS vacant_units
    FROM units
    GROUP BY property_id
) t ON p.id = t.property_id" . $whereClause . " ORDER BY p.created_at DESC";
$result = $conn->query($sql);

if ($result === false) {
    die('Database query error: ' . $conn->error);
}

function fetch_count($conn, $sql) {
    $result = $conn->query($sql);
    if ($result === false) {
        die('Database query error: ' . $conn->error . ' SQL: ' . $sql);
    }
    $row = $result->fetch_assoc();
    return $row ? intval($row['count']) : 0;
}

$baseWhere = $landlord_id ? ' WHERE p.landlord_id = ' . intval($landlord_id) : '';
$totalOccupied = fetch_count($conn, "SELECT COUNT(DISTINCT p.id) as count FROM properties p JOIN units u ON u.property_id = p.id AND u.status = 'Occupied'" . $baseWhere);
$totalVacant = fetch_count($conn, "SELECT COUNT(DISTINCT p.id) as count FROM properties p JOIN units u ON u.property_id = p.id AND u.status = 'Available'" . $baseWhere);
$totalProperties = fetch_count($conn, "SELECT COUNT(*) as count FROM properties p" . $baseWhere);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Properties - Riset Property Management</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Manage property listings in Riset Property Management System">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/style-mob.css" rel="stylesheet" />
    <style>
        .property-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 20px;
        }
        .property-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .property-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .property-content {
            padding: 15px;
        }
        .property-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 8px 0;
            color: #333;
        }
        .property-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .property-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .property-price {
            font-size: 18px;
            font-weight: bold;
            color: #2f80ed;
            margin-bottom: 10px;
        }
        .property-actions {
            display: flex;
            gap: 8px;
        }
        .property-actions a {
            flex: 1;
            padding: 8px 12px;
            border-radius: 4px;
            text-align: center;
            font-size: 12px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .property-actions .btn-edit {
            background: #2f80ed;
            color: white;
        }
        .property-actions .btn-edit:hover {
            background: #1e5cc4;
        }
        .property-actions .btn-delete {
            background: #e53935;
            color: white;
        }
        .property-actions .btn-delete:hover {
            background: #c62828;
        }
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 8px;
        }
        .status-badge.available {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-badge.occupied {
            background: #fff3e0;
            color: #e65100;
        }
        .status-badge.maintenance {
            background: #e3f2fd;
            color: #1565c0;
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
            <div class="col-md-6 col-sm-6 mob-hide">
                <form class="app-search">
                    <input type="text" placeholder="Search properties..." class="form-control">
                    <a href="#"><i class="fa fa-search"></i></a>
                </form>
            </div>
            <div class="col-md-2 tab-hide">
                <div class="top-not-cen">
                    <a class='waves-effect btn-noti' href="admin-communications.php" title="Messages"><i class="fa fa-envelope-o" aria-hidden="true"></i><span>3</span></a>
                    <a class='waves-effect btn-noti' href="admin-maintenance.php" title="Maintenance"><i class="fa fa-wrench" aria-hidden="true"></i><span>2</span></a>
                </div>
            </div>
            <?php include 'top_user_menu.php'; ?>
        </div>
    </div>

    <div class="container-fluid sb2">
        <div class="row">
            <div class="sb2-1">
                <div class="sb2-12">
                    <ul>
                        <li><img src="images/placeholder.jpg" alt=""></li>
                        <li><h5>Property Management <span>Riset Properties</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-properties.php" class="menu-active"><i class="fa fa-building" aria-hidden="true"></i> Properties</a></li>
                        <li><a href="admin-user-all.php"><i class="fa fa-users" aria-hidden="true"></i> Tenants</a></li>
                        <li><a href="admin-leases.php"><i class="fa fa-file-contract" aria-hidden="true"></i> Leases</a></li>
                        <li><a href="admin-rent-payments.php"><i class="fa fa-money" aria-hidden="true"></i> Payments</a></li>
                        <li><a href="admin-maintenance.php"><i class="fa fa-wrench" aria-hidden="true"></i> Maintenance</a></li>
                        <li><a href="admin-reports.php"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Reports</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Properties</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3" style="padding: 20px;">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px;">
                        <div>
                            <div style="font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 0.08em;">Portfolio</div>
                            <h2 style="margin: 0;">Properties</h2>
                            <p style="color: #999; margin: 5px 0 0; max-width: 560px;">Monitor occupancy health across every building and act fast where it matters.</p>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                            <a href="admin-add-property.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;"><i class="fa fa-plus"></i> New Property</a>
                            <a href="admin-properties.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">Import data</a>
                            <a href="admin-leases.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">Link tenant to unit</a>
                            <a href="#" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">Branches</a>
                            <a href="#" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">Utility accounts</a>
                        </div>
                    </div>

                    <div class="filters-section" style="margin-bottom: 25px;">
                        <form method="get" action="admin-properties.php">
                            <div class="row" style="align-items: center; gap: 12px;">
                                <div class="col-md-4">
                                    <label style="font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; margin-bottom: 6px; display: block;">Search property</label>
                                    <input type="text" name="search" placeholder="Search property" class="form-control" style="height: 42px;" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label style="font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; margin-bottom: 6px; display: block;">Search landlord</label>
                                    <input type="text" name="landlord" placeholder="Search landlord" class="form-control" style="height: 42px;" disabled>
                                </div>
                                <div class="col-md-3">
                                    <label style="font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; margin-bottom: 6px; display: block;">Status</label>
                                    <select name="status" class="form-control" style="height: 42px;">
                                        <option value="">All status</option>
                                        <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="Occupied" <?php echo $statusFilter === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                                        <option value="Maintenance" <?php echo $statusFilter === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    </select>
                                </div>
                                <div class="col-md-1" style="display: flex; align-items: flex-end;">
                                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px;">Search</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
                        <a href="admin-properties.php?status=Occupied" class="btn btn-secondary metrics-card" style="flex: 1; min-width: 180px; justify-content: space-between; padding: 16px; display: inline-flex; align-items: center;">
                            <div>
                                <div style="font-size: 12px; color: #777; text-transform: uppercase;">Total occupied</div>
                                <div style="font-size: 22px; font-weight: 700;"><?php echo intval($totalOccupied); ?></div>
                            </div>
                            <span style="background: #eef7ff; color: #1d4ed8; padding: 6px 10px; border-radius: 20px; font-size: 12px;">Filter</span>
                        </a>
                        <a href="admin-properties.php?status=Available" class="btn btn-secondary metrics-card" style="flex: 1; min-width: 180px; justify-content: space-between; padding: 16px; display: inline-flex; align-items: center;">
                            <div>
                                <div style="font-size: 12px; color: #777; text-transform: uppercase;">Total vacant</div>
                                <div style="font-size: 22px; font-weight: 700;"><?php echo intval($totalVacant); ?></div>
                            </div>
                            <span style="background: #eef7ff; color: #1d4ed8; padding: 6px 10px; border-radius: 20px; font-size: 12px;">Filter</span>
                        </a>
                    </div>

                    <div class="row">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($property = $result->fetch_assoc()): ?>
                                <?php
                                    $occupied = intval($property['occupied_units']);
                                    $totalUnits = intval($property['total_units']);
                                    $vacantUnits = max(0, $totalUnits - $occupied);
                                    $occupancyRate = $totalUnits > 0 ? round($occupied / $totalUnits * 100) : 0;
                                    $statusLabel = htmlspecialchars($property['status']);
                                ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="property-card">
                                        <img src="<?php echo !empty($property['featured_image']) ? htmlspecialchars($property['featured_image']) : 'images/Property/sm-1.jpg'; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" class="property-image">
                                        <div class="property-content">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                                                <div>
                                                    <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: .08em;"><?php echo htmlspecialchars($property['property_type'] ?: 'Property'); ?></div>
                                                    <h5 class="property-title"><?php echo htmlspecialchars($property['title']); ?></h5>
                                                    <div style="font-size: 13px; color: #666; margin-top: 4px;"><?php echo htmlspecialchars($property['location'] ?: $property['city']); ?></div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <a href="admin-edit-property.php?id=<?php echo urlencode($property['id']); ?>" class="btn btn-primary" style="font-size: 11px; padding: 8px 12px;">View property</a>
                                                </div>
                                            </div>

                                            <div style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 12px;">
                                                <div style="background: #f5f8ff; padding: 12px; border-radius: 8px; flex: 1;">
                                                    <div style="font-size: 11px; color: #777; margin-bottom: 4px;">Total units</div>
                                                    <div style="font-size: 18px; font-weight: 700;"><?php echo $totalUnits; ?></div>
                                                </div>
                                                <div style="background: #f5f8ff; padding: 12px; border-radius: 8px; flex: 1;">
                                                    <div style="font-size: 11px; color: #777; margin-bottom: 4px;">Occupied</div>
                                                    <div style="font-size: 18px; font-weight: 700;"><?php echo $occupied; ?></div>
                                                </div>
                                            </div>

                                            <div style="margin-bottom: 12px;">
                                                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #777; margin-bottom: 6px;">
                                                    <span>Occupancy</span>
                                                    <span><?php echo $occupancyRate; ?>%</span>
                                                </div>
                                                <div style="background: #e5e7eb; width: 100%; height: 8px; border-radius: 999px; overflow: hidden;">
                                                    <div style="width: <?php echo $occupancyRate; ?>%; height: 100%; background: #3b82f6;"></div>
                                                </div>
                                                <div style="font-size: 12px; color: #777; margin-top: 8px;"><?php echo $vacantUnits; ?> vacant of <?php echo $totalUnits; ?></div>
                                            </div>

                                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <a href="admin-edit-property.php?id=<?php echo urlencode($property['id']); ?>" class="btn btn-primary" style="flex: 1; font-size: 12px; padding: 10px 12px;">View property</a>
                                                <a href="admin-units.php?property_id=<?php echo urlencode($property['id']); ?>" class="btn btn-secondary" style="flex: 1; font-size: 12px; padding: 10px 12px;"><i class="fa fa-th"></i> Manage units</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-md-12">
                                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px;">
                                    <i class="fa fa-home" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                                    <p style="color: #999; font-size: 16px;">No properties found. <a href="admin-add-property.php">Create one now</a></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script src="js/admin-delete.js"></script>
</body>
</html>
<?php $conn->close(); ?>




