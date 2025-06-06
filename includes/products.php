<?php
session_start();
require '../config/database.php';
require 'activity_tracker.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Track page view activity
if (isset($_SESSION['user_id'])) {
    trackPageView($_SESSION['user_id'], 'Products Management');
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {            case 'add':
                try {                    $stmt = $conn->prepare("INSERT INTO membershipplans (plan_type, session_count, duration_months, name, price, description, features, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['plan_type'],
                        $_POST['plan_type'] == 'session' ? $_POST['session_count'] : null,
                        $_POST['plan_type'] == 'monthly' ? $_POST['duration_months'] : null,
                        $_POST['name'],
                        $_POST['price'],
                        $_POST['description'],
                        $_POST['features'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['sort_order']
                    ]);
                    $success_message = "Product added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding product: " . $e->getMessage();
                }
                break;
                  case 'edit':
                try {                    $stmt = $conn->prepare("UPDATE membershipplans SET plan_type = ?, session_count = ?, duration_months = ?, name = ?, price = ?, description = ?, features = ?, is_active = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['plan_type'],
                        $_POST['plan_type'] == 'session' ? $_POST['session_count'] : null,
                        $_POST['plan_type'] == 'monthly' ? $_POST['duration_months'] : null,
                        $_POST['name'],
                        $_POST['price'],
                        $_POST['description'],
                        $_POST['features'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['sort_order'],
                        $_POST['id']
                    ]);
                    $success_message = "Product updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating product: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $stmt = $conn->prepare("DELETE FROM membershipplans WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = "Product deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting product: " . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                try {
                    $stmt = $conn->prepare("UPDATE membershipplans SET is_active = ? WHERE id = ?");
                    $stmt->execute([$_POST['is_active'], $_POST['id']]);
                    $success_message = "Product status updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating product status: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all products
try {
    $stmt = $conn->prepare("SELECT * FROM membershipplans ORDER BY sort_order ASC, name ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching products: " . $e->getMessage();
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Membership Plans | Fitness Academy</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="../assets/js/auto-logout.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #ff6b6b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-color: #f3f4f6;
            --dark-color: #111827;
            --gray-color: #6b7280;
            --sidebar-width: 280px;
            --header-height: 72px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: var(--dark-color);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .sidebar-menu-header {
            padding: 0 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
            margin-top: 1.25rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--secondary-color);
        }

        .sidebar a i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .user-profile {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            margin-top: auto;
        }

        .user-profile img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            margin-right: 0.75rem;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: white;
            font-size: 0.95rem;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }

        /* Main Content Styles */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-search {
            display: flex;
            align-items: center;
        }

        .header-search input {
            border: none;
            background: #f3f4f6;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border-radius: 8px;
            width: 300px;
            font-size: 0.9rem;
            color: var(--dark-color);
        }

        .header-search i {
            position: absolute;
            left: 3rem;
            color: var(--gray-color);
        }

        .header-actions {
            display: flex;
            align-items: center;
        }        .main-content {
            padding: 2rem;
            flex: 1;
        }

        .page-title {
            color: var(--dark-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-color);
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--secondary-color), #ff4757);
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn-primary {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-primary:hover {            background: #ff4757;
            border-color: #ff4757;
        }        .modal-content {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, var(--secondary-color), #ff4757);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-footer {
            border-top: 1px solid #e5e7eb;
        }

        .form-control {
            background: white;
            border: 1px solid #e5e7eb;
            color: var(--dark-color);
        }.form-control:focus {
            background: var(--light-color);
            border-color: var(--secondary-color);
            color: var(--dark-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
        }        .form-select {
            background: white;
            border: 1px solid #e5e7eb;
            color: var(--dark-color);
        }

        .badge {
            font-size: 0.75rem;
        }

        .package-badge-a {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .package-badge-b {
            background: linear-gradient(135deg, #007bff, #6f42c1);
        }

        .alert {
            border: none;
            border-radius: 8px;
        }        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }

            .sidebar-header h2,
            .sidebar a span,
            .user-info {
                display: none;
            }

            .sidebar a i {
                margin-right: 0;
            }

            .sidebar a {
                justify-content: center;
            }

            .user-profile {
                justify-content: center;
            }

            .main-wrapper {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .header-search input {
                width: 200px;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 1rem;
            }

            .header-search {
                display: none;
            }

            .main-content {
                padding: 1.5rem;
            }
        }

        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #4b5563;
            border-bottom-width: 1px;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f9fafb;
        }

        /* Alert and message styles */
        .alert-success {
            background-color: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #b91c1c;
        }
        
        /* Pagination styles */
        .pagination {
            margin-top: 1.5rem;
            justify-content: center;
        }
        
        .page-link {
            color: var(--dark-color);
            border: 1px solid #e5e7eb;
        }
        
        .page-link:hover {
            background-color: #f9fafb;
            color: var(--secondary-color);
        }
        
        .page-item.active .page-link {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }        /* Large screens optimization */
        @media (min-width: 1400px) {
            .card {
                max-width: 1400px;
                margin: 0 auto 2rem auto;
            }
        }
        
        .table {
            margin-bottom: 0;
            border-color: #e5e7eb;
        }
        
        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
            padding: 0.75rem;
        }
        
        .table td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }
          /* Card styling */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(to right, #1e40af, #3b82f6);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
            border-bottom: none;
        }
        
        .card-title {
            margin-bottom: 0;
        }
        
        /* Table styling */
        .table {
            margin-bottom: 0;
            border-color: #e5e7eb;
        }
        
        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
            padding: 0.75rem;
            border-top: none;
        }
        
        .table td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        
        /* Plan type badges */
        .badge-plan-session {
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: 500;
        }
        
        .badge-plan-monthly {
            background-color: #dcfce7;
            color: #047857;
            font-weight: 500;
        }
        
        /* Form controls */
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
            border-color: #93c5fd;
        }
        
        /* Button styling */
        .btn-primary {
            background-color: #1e40af;
            border-color: #1e40af;
        }
        
        .btn-primary:hover {
            background-color: #1c3879;
            border-color: #1c3879;
        }
        
        .btn-success {
            background-color: #10b981;
            border-color: #10b981;
        }
        
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }
        
        /* Feature pills */
        .feature-pill {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background-color: #f3f4f6;
            border-radius: 999px;
            font-size: 0.75rem;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
            color: #4b5563;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Fitness Academy</h2>
        </div>

        <nav class="sidebar-menu">
            <div class="sidebar-menu-header">Dashboard</div>
            <a href="admin_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Overview</span>
            </a>
            
            <div class="sidebar-menu-header">Management</div>
            <a href="manage_users.php">
                <i class="fas fa-users-cog"></i>
                <span>Manage Users</span>
            </a>
            <a href="member_list.php">
                <i class="fas fa-users"></i>
                <span>Member List</span>
            </a>
            <a href="coach_applications.php">
                <i class="fas fa-user-tie"></i>
                <span>Coach Applications</span>
            </a>
            <a href="admin_video_approval.php">
                <i class="fas fa-video"></i>
                <span>Video Approval</span>
            </a>
            <a href="products.php" class="active">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="employee_list.php">
                <i class="fas fa-id-card"></i>
                <span>Employee List</span>
            </a>
            
            <div class="sidebar-menu-header">Reports</div>
            <a href="attendance_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Attendance Reports</span>
            </a>
            <a href="report_generation.php">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
            <a href="transaction_history.php">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            <a href="audit_trail.php">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>

            <div class="sidebar-menu-header">Database</div>
            <a href="database_management.php">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>

            <div class="sidebar-menu-header">Account</div>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>

        <div class="user-profile">
            <img src="../assets/images/avatar.jpg" alt="Admin" onerror="this.src='../assets/images/fa_logo.png'">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </aside>    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h2>Products Management</h2>
            </div>
            <div class="header-actions">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            </div>
        </header>

        <!-- Content -->
        <div class="content">            <div class="row mb-4">
                <div class="col-12">
                    <div class="card welcome-card mb-0">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">Membership Plans Management</h4>
                                <p class="text-muted mb-0">Create and manage membership plans available for purchase</p>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fas fa-plus me-2"></i>Add New Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($success_message || $error_message): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-id-card me-2"></i>Membership Plans
                    </h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2"><?= count($products) ?> Plans</span>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="#" onclick="printTable()"><i class="fas fa-print me-2"></i>Print List</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportToCSV()"><i class="fas fa-file-csv me-2"></i>Export to CSV</a></li>
                            </ul>
                        </div>
                    </div>
                </div>                <div class="card-body">
                    <!-- Search and filters -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="planSearch" placeholder="Search plans..." onkeyup="filterPlans()">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="planTypeFilter" onchange="filterPlans()">
                                <option value="">All Plan Types</option>
                                <option value="session">Session-Based</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter" onchange="filterPlans()">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover"><thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Plan Type</th>
                                    <th>Sessions/Duration</th>
                                    <th>Price</th>
                                    <th>Description</th>
                                    <th>Features</th>
                                    <th>Status</th>
                                    <th>Sort Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No products found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>                                        <tr>                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="plan-icon me-3 rounded-circle bg-light p-2 text-center" style="width: 40px; height: 40px;">
                                                        <?php if ($product['plan_type'] === 'session'): ?>
                                                            <i class="fas fa-ticket-alt text-info"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-calendar-alt text-success"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                                                        <small class="text-muted">ID: <?= $product['id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill badge-plan-<?= $product['plan_type'] ?>">
                                                    <?= ucfirst($product['plan_type'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($product['plan_type'] === 'session'): ?>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-info text-dark me-2"><?= $product['session_count'] ?? 0 ?></span>
                                                        Sessions
                                                    </div>
                                                <?php elseif ($product['plan_type'] === 'monthly'): ?>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-success text-white me-2"><?= $product['duration_months'] ?? 0 ?></span>
                                                        Months
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <h6 class="mb-0 text-primary">₱<?= number_format($product['price'], 2) ?></h6>
                                            </td>
                                            <td>
                                                <div data-bs-toggle="tooltip" title="<?= htmlspecialchars($product['description']) ?>">
                                                    <?= htmlspecialchars(strlen($product['description']) > 30 ? substr($product['description'], 0, 30) . '...' : $product['description']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $features = explode('|', $product['features']);
                                                foreach (array_slice($features, 0, 2) as $feature) {
                                                    echo '<span class="feature-pill">' . htmlspecialchars($feature) . '</span> ';
                                                }
                                                if (count($features) > 2) echo '<span class="badge bg-light text-dark">+' . (count($features) - 2) . ' more</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                                    <input type="hidden" name="is_active" value="<?= $product['is_active'] ? 0 : 1 ?>">
                                                    <button type="submit" class="btn btn-sm btn-light border <?= $product['is_active'] ? 'border-success text-success' : 'border-secondary text-secondary' ?>" title="Click to toggle status">
                                                        <i class="fas <?= $product['is_active'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                                        <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark"><?= $product['sort_order'] ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex">
                                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>            <?php if (count($products) > 10): ?>
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">
                            <i class="fas fa-chevron-left me-1"></i> Previous
                        </a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">
                            Next <i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addProductModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add New Membership Plan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Create a new membership plan for customers to purchase.
                        </div><div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_type" class="form-label">Plan Type</label>
                                    <select class="form-select" id="plan_type" name="plan_type" required onchange="togglePlanFields()">
                                        <option value="session">Session-Based</option>
                                        <option value="monthly">Time-Based (Monthly)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sort_order" class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" required>
                                </div>
                            </div>
                        </div>                        <div class="row">
                            <div class="col-md-6" id="session_count_div">
                                <div class="mb-3">
                                    <label for="session_count" class="form-label">Number of Sessions</label>
                                    <input type="number" class="form-control" id="session_count" name="session_count" min="1">
                                </div>
                            </div>
                            <div class="col-md-6" id="duration_months_div" style="display: none;">
                                <div class="mb-3">
                                    <label for="duration_months" class="form-label">Duration (Months)</label>
                                    <input type="number" class="form-control" id="duration_months" name="duration_months" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="price" class="form-label">Price (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="features" class="form-label">Features (separate with |)</label>
                            <textarea class="form-control" id="features" name="features" rows="3" placeholder="Feature 1|Feature 2|Feature 3"></textarea>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editProductModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Membership Plan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Changes will apply to all future purchases. Existing memberships will not be affected.
                        </div><div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_plan_type" class="form-label">Plan Type</label>
                                    <select class="form-select" id="edit_plan_type" name="plan_type" required onchange="toggleEditPlanFields()">
                                        <option value="session">Session-Based</option>
                                        <option value="monthly">Time-Based (Monthly)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sort_order" class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" id="edit_sort_order" name="sort_order" required>
                                </div>
                            </div>
                        </div>                        <div class="row">
                            <div class="col-md-6" id="edit_session_count_div">
                                <div class="mb-3">
                                    <label for="edit_session_count" class="form-label">Number of Sessions</label>
                                    <input type="number" class="form-control" id="edit_session_count" name="session_count" min="1">
                                </div>
                            </div>
                            <div class="col-md-6" id="edit_duration_months_div" style="display: none;">
                                <div class="mb-3">
                                    <label for="edit_duration_months" class="form-label">Duration (Months)</label>
                                    <input type="number" class="form-control" id="edit_duration_months" name="duration_months" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price" name="price" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="edit_features" class="form-label">Features (separate with |)</label>
                            <textarea class="form-control" id="edit_features" name="features" rows="3" placeholder="Feature 1|Feature 2|Feature 3"></textarea>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active
                            </label>
                        </div>
                    </div>                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteProductModalLabel">
                        <i class="fas fa-trash-alt me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you sure you want to delete the following membership plan?</p>
                    <div class="card mt-3 mb-3">
                        <div class="card-body text-center">
                            <h5 id="deleteProductName" class="mb-0"></h5>
                        </div>
                    </div>
                    <p class="mb-0">This will permanently remove this membership plan from the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" id="deleteProductForm" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Delete Plan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Initialize any other components here
        });

        function togglePlanFields() {
            const planType = document.getElementById('plan_type').value;
            const sessionDiv = document.getElementById('session_count_div');
            const monthsDiv = document.getElementById('duration_months_div');
            const sessionInput = document.getElementById('session_count');
            const monthsInput = document.getElementById('duration_months');
            
            if (planType === 'session') {
                sessionDiv.style.display = 'block';
                monthsDiv.style.display = 'none';
                sessionInput.required = true;
                monthsInput.required = false;
            } else {
                sessionDiv.style.display = 'none';
                monthsDiv.style.display = 'block';
                sessionInput.required = false;
                monthsInput.required = true;
            }
        }

        function toggleEditPlanFields() {
            const planType = document.getElementById('edit_plan_type').value;
            const sessionDiv = document.getElementById('edit_session_count_div');
            const monthsDiv = document.getElementById('edit_duration_months_div');
            const sessionInput = document.getElementById('edit_session_count');
            const monthsInput = document.getElementById('edit_duration_months');
            
            if (planType === 'session') {
                sessionDiv.style.display = 'block';
                monthsDiv.style.display = 'none';
                sessionInput.required = true;
                monthsInput.required = false;
            } else {
                sessionDiv.style.display = 'none';
                monthsDiv.style.display = 'block';
                sessionInput.required = false;
                monthsInput.required = true;
            }
        }
        
        // Print table function
        function printTable() {
            const printContents = document.querySelector('.table-responsive').innerHTML;
            const originalContents = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="padding: 20px;">
                    <h1 style="text-align: center; margin-bottom: 20px;">Membership Plans</h1>
                    ${printContents}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }
          // Export table to CSV function
        function exportToCSV() {
            const table = document.querySelector('.table');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Replace HTML with text content only and escape commas
                    let text = cols[j].innerText.replace(/,/g, ';');
                    row.push(text);
                }
                
                csv.push(row.join(','));
            }
            
            // Download CSV file
            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            
            downloadLink.download = 'membership_plans_' + new Date().toISOString().slice(0, 10) + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        // Filter plans function
        function filterPlans() {
            const searchInput = document.getElementById('planSearch').value.toLowerCase();
            const planTypeFilter = document.getElementById('planTypeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            const rows = document.querySelectorAll('table tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const planType = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                // Check status - Active or Inactive (get from the status button text)
                const status = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
                
                const nameMatch = name.includes(searchInput);
                const planTypeMatch = planTypeFilter === '' || planType.includes(planTypeFilter.toLowerCase());
                const statusMatch = statusFilter === '' || status.includes(statusFilter.toLowerCase());
                
                if (nameMatch && planTypeMatch && statusMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show a message if no plans match the filter
            const noResultsRow = document.getElementById('noResultsRow');
            if (visibleCount === 0) {
                if (!noResultsRow) {
                    const tbody = document.querySelector('table tbody');
                    const newRow = document.createElement('tr');
                    newRow.id = 'noResultsRow';
                    newRow.innerHTML = `<td colspan="9" class="text-center py-3">
                        <i class="fas fa-search me-2"></i>No matching plans found. Try adjusting your filters.
                    </td>`;
                    tbody.appendChild(newRow);
                } else {
                    noResultsRow.style.display = '';
                }
            } else if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_plan_type').value = product.plan_type;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_features').value = product.features || '';
            document.getElementById('edit_sort_order').value = product.sort_order;
            document.getElementById('edit_is_active').checked = product.is_active == 1;
            
            // Set plan-specific fields
            if (product.plan_type === 'session') {
                document.getElementById('edit_session_count').value = product.session_count || '';
                document.getElementById('edit_duration_months').value = '';
            } else {
                document.getElementById('edit_session_count').value = '';
                document.getElementById('edit_duration_months').value = product.duration_months || '';
            }
            
            // Toggle fields based on plan type
            toggleEditPlanFields();
            
            const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editModal.show();
        }

        function deleteProduct(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteProductName').textContent = name;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
            deleteModal.show();
        }

        // Initialize the form on page load
        document.addEventListener('DOMContentLoaded', function() {
            togglePlanFields();
        });
    </script>
      <!-- Initialize tooltips -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>
