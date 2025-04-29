<?php
require_once 'includes/connection.php';
require_once 'includes/modal/logoutModal.php';

// Get pagination parameters
$activePage = isset($_GET['active_page']) ? (int)$_GET['active_page'] : 1;
$expiredPage = isset($_GET['expired_page']) ? (int)$_GET['expired_page'] : 1;
$records_per_page = 5;

// Get search and filter parameters for active budgets
$activeSearch = isset($_GET['active_search']) ? $_GET['active_search'] : '';
$activeSearchType = isset($_GET['active_search_type']) ? $_GET['active_search_type'] : 'name';
$allowedSortColumns = [
    'budget_name',
    'department_name',
    'amount_allocated',
    'amount_remaining',
    'start_date',
    'end_date'
];
$activeSort = isset($_GET['active_sort']) && in_array($_GET['active_sort'], $allowedSortColumns) 
    ? $_GET['active_sort'] 
    : 'end_date';
$activeOrder = isset($_GET['active_order']) && in_array(strtoupper($_GET['active_order']), ['ASC', 'DESC']) 
    ? strtoupper($_GET['active_order']) 
    : 'ASC';
$activeStartDate = isset($_GET['active_start_date']) ? $_GET['active_start_date'] : '';
$activeEndDate = isset($_GET['active_end_date']) ? $_GET['active_end_date'] : '';

// Get search and filter parameters for expired budgets
$expiredSearch = isset($_GET['expired_search']) ? $_GET['expired_search'] : '';
$expiredSearchType = isset($_GET['expired_search_type']) ? $_GET['expired_search_type'] : 'name';
$expiredSort = isset($_GET['expired_sort']) && in_array($_GET['expired_sort'], $allowedSortColumns) 
    ? $_GET['expired_sort'] 
    : 'end_date';
$expiredOrder = isset($_GET['expired_order']) && in_array(strtoupper($_GET['expired_order']), ['ASC', 'DESC']) 
    ? strtoupper($_GET['expired_order']) 
    : 'DESC';
$expiredStartDate = isset($_GET['expired_start_date']) ? $_GET['expired_start_date'] : '';
$expiredEndDate = isset($_GET['expired_end_date']) ? $_GET['expired_end_date'] : '';

// Calculate offsets
$activeOffset = ($activePage - 1) * $records_per_page;
$expiredOffset = ($expiredPage - 1) * $records_per_page;

// Build active budgets query
$activeQuery = "
    SELECT b.*, d.department_name,
           (b.amount_allocated - b.amount_remaining) as amount_spent,
           ((b.amount_allocated - b.amount_remaining) / b.amount_allocated * 100) as utilization_percentage
    FROM budget b
    LEFT JOIN department d ON b.department_id = d.department_id
    WHERE b.status = 'active'
";

$activeParams = array();
$activeTypes = "";

if ($activeSearch) {
    if (is_numeric($activeSearch)) {
        $activeQuery .= " AND b.budget_id = ?";
        $activeParams[] = $activeSearch;
        $activeTypes .= "i";
    } else {
        $activeQuery .= " AND b.budget_name LIKE ?";
        $activeParams[] = "%$activeSearch%";
        $activeTypes .= "s";
    }
}

if ($activeStartDate) {
    $activeQuery .= " AND b.start_date >= ?";
    $activeParams[] = $activeStartDate;
    $activeTypes .= "s";
}

if ($activeEndDate) {
    $activeQuery .= " AND b.end_date <= ?";
    $activeParams[] = $activeEndDate;
    $activeTypes .= "s";
}

$activeQuery .= " ORDER BY b.$activeSort $activeOrder LIMIT ? OFFSET ?";
$activeParams[] = $records_per_page;
$activeParams[] = $activeOffset;
$activeTypes .= "ii";

// Build expired budgets query
$expiredQuery = "
    SELECT b.*, d.department_name,
           (b.amount_allocated - b.amount_remaining) as amount_spent,
           ((b.amount_allocated - b.amount_remaining) / b.amount_allocated * 100) as utilization_percentage
    FROM budget b
    LEFT JOIN department d ON b.department_id = d.department_id
    WHERE b.status = 'expired'
";

$expiredParams = array();
$expiredTypes = "";

if ($expiredSearch) {
    if (is_numeric($expiredSearch)) {
        $expiredQuery .= " AND b.budget_id = ?";
        $expiredParams[] = $expiredSearch;
        $expiredTypes .= "i";
    } else {
        $expiredQuery .= " AND b.budget_name LIKE ?";
        $expiredParams[] = "%$expiredSearch%";
        $expiredTypes .= "s";
    }
}

if ($expiredStartDate) {
    $expiredQuery .= " AND b.start_date >= ?";
    $expiredParams[] = $expiredStartDate;
    $expiredTypes .= "s";
}

if ($expiredEndDate) {
    $expiredQuery .= " AND b.end_date <= ?";
    $expiredParams[] = $expiredEndDate;
    $expiredTypes .= "s";
}

$expiredQuery .= " ORDER BY b.$expiredSort $expiredOrder LIMIT ? OFFSET ?";
$expiredParams[] = $records_per_page;
$expiredParams[] = $expiredOffset;
$expiredTypes .= "ii";

// Get total counts with filters
$activeCountQuery = "SELECT COUNT(*) as total FROM budget b WHERE b.status = 'active'";
$expiredCountQuery = "SELECT COUNT(*) as total FROM budget b WHERE b.status = 'expired'";

if ($activeSearch) {
    if (is_numeric($activeSearch)) {
        $activeCountQuery .= " AND b.budget_id = '$activeSearch'";
    } else {
        $activeCountQuery .= " AND b.budget_name LIKE '%$activeSearch%'";
    }
}
if ($activeStartDate) {
    $activeCountQuery .= " AND b.start_date >= '$activeStartDate'";
}
if ($activeEndDate) {
    $activeCountQuery .= " AND b.end_date <= '$activeEndDate'";
}

if ($expiredSearch) {
    if (is_numeric($expiredSearch)) {
        $expiredCountQuery .= " AND b.budget_id = '$expiredSearch'";
    } else {
        $expiredCountQuery .= " AND b.budget_name LIKE '%$expiredSearch%'";
    }
}
if ($expiredStartDate) {
    $expiredCountQuery .= " AND b.start_date >= '$expiredStartDate'";
}
if ($expiredEndDate) {
    $expiredCountQuery .= " AND b.end_date <= '$expiredEndDate'";
}

$activeCount = $conn->query($activeCountQuery)->fetch_assoc()['total'];
$expiredCount = $conn->query($expiredCountQuery)->fetch_assoc()['total'];

$totalActivePages = ceil($activeCount / $records_per_page);
$totalExpiredPages = ceil($expiredCount / $records_per_page);

// Execute queries
$activeStmt = $conn->prepare($activeQuery);
if (!empty($activeParams)) {
    $activeStmt->bind_param($activeTypes, ...$activeParams);
}
$activeStmt->execute();
$activeBudgets = $activeStmt->get_result();

$expiredStmt = $conn->prepare($expiredQuery);
if (!empty($expiredParams)) {
    $expiredStmt->bind_param($expiredTypes, ...$expiredParams);
}
$expiredStmt->execute();
$expiredBudgets = $expiredStmt->get_result();

// Calculate totals - these should be independent of search filters
$totalActiveBudget = 0;
$totalActiveSpent = 0;
$totalExpiredBudget = 0;
$totalExpiredSpent = 0;
$expiringSoonTotal = 0;
$departmentBudgets = [];

// Get totals for active budgets (unfiltered)
$activeTotalsQuery = "
    SELECT 
        SUM(amount_allocated) as total_allocated,
        SUM(amount_allocated - amount_remaining) as total_spent
    FROM budget 
    WHERE status = 'active'
";
$activeTotals = $conn->query($activeTotalsQuery)->fetch_assoc();
$totalActiveBudget = $activeTotals['total_allocated'] ?? 0;
$totalActiveSpent = $activeTotals['total_spent'] ?? 0;

// Get totals for expired budgets (unfiltered)
$expiredTotalsQuery = "
    SELECT 
        SUM(amount_allocated) as total_allocated,
        SUM(amount_allocated - amount_remaining) as total_spent
    FROM budget 
    WHERE status = 'expired'
";
$expiredTotals = $conn->query($expiredTotalsQuery)->fetch_assoc();
$totalExpiredBudget = $expiredTotals['total_allocated'] ?? 0;
$totalExpiredSpent = $expiredTotals['total_spent'] ?? 0;

// Get expiring soon total (unfiltered)
$expiringSoonQuery = "
    SELECT SUM(amount_allocated) as total
    FROM budget 
    WHERE status = 'active'
    AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
";
$expiringSoonResult = $conn->query($expiringSoonQuery)->fetch_assoc();
$expiringSoonTotal = $expiringSoonResult['total'] ?? 0;

// Get department budgets (unfiltered)
$departmentBudgetsQuery = "
    SELECT 
        d.department_name,
        SUM(b.amount_allocated) as total_allocated
    FROM department d
    LEFT JOIN budget b ON d.department_id = b.department_id
    WHERE b.status = 'active'
    GROUP BY d.department_id, d.department_name
    ORDER BY total_allocated DESC
";
$departmentBudgetsResult = $conn->query($departmentBudgetsQuery);
while ($dept = $departmentBudgetsResult->fetch_assoc()) {
    $departmentBudgets[$dept['department_name']] = $dept['total_allocated'];
}

// Find the department with highest budget
$highestDepartment = array_search(max($departmentBudgets), $departmentBudgets);
$highestDepartmentAmount = $departmentBudgets[$highestDepartment] ?? 0;

// Reset pointers for the filtered queries
$activeBudgets->data_seek(0);
$expiredBudgets->data_seek(0);

// Fetch department-wise budget summary
$departmentSummary = $conn->query("
    SELECT 
        d.department_name,
        COUNT(b.budget_id) as total_budgets,
        SUM(b.amount_allocated) as total_allocated,
        SUM(b.amount_remaining) as total_remaining,
        (SUM(b.amount_allocated) - SUM(b.amount_remaining)) as total_spent,
        ((SUM(b.amount_allocated) - SUM(b.amount_remaining)) / SUM(b.amount_allocated) * 100) as utilization_percentage
    FROM department d
    LEFT JOIN budget b ON d.department_id = b.department_id
    WHERE b.status = 'active'
    GROUP BY d.department_id, d.department_name
    ORDER BY total_allocated DESC
");

// Fetch budget timeline data
$timelineData = $conn->query("
    SELECT 
        b.budget_name,
        d.department_name,
        b.start_date,
        b.end_date,
        b.amount_allocated,
        b.amount_remaining,
        b.status
    FROM budget b
    LEFT JOIN department d ON b.department_id = d.department_id
    ORDER BY b.start_date ASC
");

// Function to generate sort URL
function getSortUrl($type, $column) {
    global $activeSort, $activeOrder, $activeSearch, $activeStartDate, $activeEndDate,
           $expiredSort, $expiredOrder, $expiredSearch, $expiredStartDate, $expiredEndDate,
           $activePage, $expiredPage;
    
    $params = array();
    
    if ($type === 'active') {
        if ($activeSearch) $params[] = "active_search=" . urlencode($activeSearch);
        if ($activeStartDate) $params[] = "active_start_date=" . urlencode($activeStartDate);
        if ($activeEndDate) $params[] = "active_end_date=" . urlencode($activeEndDate);
        if ($expiredPage > 1) $params[] = "expired_page=" . $expiredPage;
        $params[] = "section=active";
        
        $newOrder = ($activeSort == $column && $activeOrder == 'ASC') ? 'DESC' : 'ASC';
        $params[] = "active_sort=" . urlencode($column);
        $params[] = "active_order=" . urlencode($newOrder);
    } else {
        if ($expiredSearch) $params[] = "expired_search=" . urlencode($expiredSearch);
        if ($expiredStartDate) $params[] = "expired_start_date=" . urlencode($expiredStartDate);
        if ($expiredEndDate) $params[] = "expired_end_date=" . urlencode($expiredEndDate);
        if ($activePage > 1) $params[] = "active_page=" . $activePage;
        $params[] = "section=expired";
        
        $newOrder = ($expiredSort == $column && $expiredOrder == 'ASC') ? 'DESC' : 'ASC';
        $params[] = "expired_sort=" . urlencode($column);
        $params[] = "expired_order=" . urlencode($newOrder);
    }
    
    return "?" . implode("&", $params);
}

// Function to get sort icon
function getSortIcon($type, $column) {
    global $activeSort, $activeOrder, $expiredSort, $expiredOrder;
    
    if ($type === 'active') {
        if ($activeSort != $column) return '<i class="bx bx-sort"></i>';
        return $activeOrder == 'ASC' ? '<i class="bx bx-sort-up"></i>' : '<i class="bx bx-sort-down"></i>';
    } else {
        if ($expiredSort != $column) return '<i class="bx bx-sort"></i>';
        return $expiredOrder == 'ASC' ? '<i class="bx bx-sort-up"></i>' : '<i class="bx bx-sort-down"></i>';
    }
}

// Function to generate pagination URL
function getPaginationUrl($type, $pageNum) {
    global $activeSort, $activeOrder, $activeSearch, $activeStartDate, $activeEndDate,
           $expiredSort, $expiredOrder, $expiredSearch, $expiredStartDate, $expiredEndDate,
           $activePage, $expiredPage;
    
    $params = array();
    
    if ($type === 'active') {
        if ($activeSearch) $params[] = "active_search=" . urlencode($activeSearch);
        if ($activeStartDate) $params[] = "active_start_date=" . urlencode($activeStartDate);
        if ($activeEndDate) $params[] = "active_end_date=" . urlencode($activeEndDate);
        if ($activeSort != 'end_date') $params[] = "active_sort=" . urlencode($activeSort);
        if ($activeOrder != 'ASC') $params[] = "active_order=" . urlencode($activeOrder);
        if (isset($expiredPage) && $expiredPage > 1) $params[] = "expired_page=" . $expiredPage;
        $params[] = "section=active";
    } else {
        if ($expiredSearch) $params[] = "expired_search=" . urlencode($expiredSearch);
        if ($expiredStartDate) $params[] = "expired_start_date=" . urlencode($expiredStartDate);
        if ($expiredEndDate) $params[] = "expired_end_date=" . urlencode($expiredEndDate);
        if ($expiredSort != 'end_date') $params[] = "expired_sort=" . urlencode($expiredSort);
        if ($expiredOrder != 'DESC') $params[] = "expired_order=" . urlencode($expiredOrder);
        if (isset($activePage) && $activePage > 1) $params[] = "active_page=" . $activePage;
        $params[] = "section=expired";
    }
    
    $params[] = ($type === 'active' ? "active_page=" : "expired_page=") . $pageNum;
    
    return "?" . implode("&", $params);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management Dashboard</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/budget.css"> 
    <link rel="stylesheet" href="styles/theme-toggle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <a class="navbar-brand" href="#">Budget Management</a>
                    
                    <div class="d-flex align-items-center ms-auto"> <!-- ms-auto pushes it to the right -->
                        <div class="theme-toggle">
                        <i class='bx bx-sun'></i>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="themeToggle">
                        </div>
                        <i class='bx bx-moon'></i>
                        </div>
                        <label for="profile-upload" class="profile-picture-container">
                            <img id="profile-image" src="includes/shrek.jpg" alt="Profile Picture" class="profile-picture">
                        </label>
                        <input type="file" id="profile-upload" accept="image/*">
                    
                    </div>
                </div>
            </nav>

            <main class="content px-3 py-4">
                <div class="container-fluid">
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card summary-card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Budget Overview</h6>
                                    <div class="chart-container" style="position: relative; height:250px; width:100%">
                                        <canvas id="budgetDonutChart"></canvas>
                                    </div>
                                    <div class="text-center mt-3">
                                        <div class="d-flex justify-content-around">
                                            <div>
                                                <p class="mb-0 text-muted">Total Budget</p>
                                                <h5><?php echo number_format($totalActiveBudget, 2); ?></h5>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted">Total Spent</p>
                                                <h5><?php echo number_format($totalActiveSpent, 2); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card summary-card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Budget Distribution</h6>
                                    <div class="chart-container" style="position: relative; height:250px; width:100%">
                                        <canvas id="budgetDistributionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Department Budget Summary -->
                    <div class="card mb-4" data-section="department">
                        <div class="card-header" >
                            <h5 class="mb-0">Department Budget Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Total Budgets</th>
                                            <th>Total Allocated</th>
                                            <th>Total Spent</th>
                                            <th>Total Remaining</th>
                                            <th>Utilization</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($dept = $departmentSummary->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                <td><?php echo $dept['total_budgets']; ?></td>
                                                <td><?php echo number_format($dept['total_allocated'], 2); ?></td>
                                                <td><?php echo number_format($dept['total_spent'], 2); ?></td>
                                                <td><?php echo number_format($dept['total_remaining'], 2); ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar <?php echo $dept['utilization_percentage'] > 80 ? 'bg-danger' : 'bg-success'; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($dept['utilization_percentage'], 100); ?>%">
                                                            <?php echo number_format($dept['utilization_percentage'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <!-- Active Budgets Table -->
                    <div class="card mb-4" data-section="active">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Active Budgets</h5>
                                <div class="d-flex gap-2">
                                    <form method="GET" class="d-flex">
                                        <input type="hidden" name="section" value="active">
                                        <input type="text" name="active_search" class="form-control me-2" placeholder="Search by ID or name" value="<?php echo htmlspecialchars($activeSearch); ?>">
                                        <button type="submit" class="btn btn-light">
                                            <i class="bx bx-search"></i>
                                        </button>
                                    </form>
                                    <form method="GET" class="d-flex gap-2">
                                        <input type="hidden" name="section" value="active">
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" name="active_start_date" class="form-control date-picker" placeholder="Start Date" value="<?php echo htmlspecialchars($activeStartDate); ?>">
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" name="active_end_date" class="form-control date-picker" placeholder="End Date" value="<?php echo htmlspecialchars($activeEndDate); ?>">
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                        <button type="submit" class="btn btn-light">
                                            <i class="bx bx-filter"></i>
                                        </button>
                                        <?php if ($activeSearch || $activeStartDate || $activeEndDate): ?>
                                            <a href="?section=active" class="btn btn-light">
                                                <i class="bx bx-x"></i>
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'budget_name'); ?>" class="text-decoration-none text-dark">
                                                    Budget Name <?php echo getSortIcon('active', 'budget_name'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'department_name'); ?>" class="text-decoration-none text-dark">
                                                    Department <?php echo getSortIcon('active', 'department_name'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'amount_allocated'); ?>" class="text-decoration-none text-dark">
                                                    Allocated Amount <?php echo getSortIcon('active', 'amount_allocated'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'amount_spent'); ?>" class="text-decoration-none text-dark">
                                                    Spent Amount <?php echo getSortIcon('active', 'amount_spent'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'amount_remaining'); ?>" class="text-decoration-none text-dark">
                                                    Remaining <?php echo getSortIcon('active', 'amount_remaining'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'start_date'); ?>" class="text-decoration-none text-dark">
                                                    Start Date <?php echo getSortIcon('active', 'start_date'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('active', 'end_date'); ?>" class="text-decoration-none text-dark">
                                                    End Date <?php echo getSortIcon('active', 'end_date'); ?>
                                                </a>
                                            </th>
                                            <th>Utilization</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($budget = $activeBudgets->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($budget['budget_id']); ?></td>
                                                <td><?php echo htmlspecialchars($budget['budget_name']); ?></td>
                                                <td><?php echo htmlspecialchars($budget['department_name']); ?></td>
                                                <td><?php echo number_format($budget['amount_allocated'], 2); ?></td>
                                                <td><?php echo number_format($budget['amount_spent'], 2); ?></td>
                                                <td><?php echo number_format($budget['amount_remaining'], 2); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($budget['start_date'])); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($budget['end_date'])); ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar <?php echo $budget['utilization_percentage'] > 80 ? 'bg-danger' : 'bg-success'; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($budget['utilization_percentage'], 100); ?>%">
                                                            <?php echo number_format($budget['utilization_percentage'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Active Budgets Pagination -->
                            <?php if ($totalActivePages > 1): ?>
                            <nav aria-label="Active budgets pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $activePage <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl('active', $activePage - 1); ?>">Previous</a>
                                    </li>
                                    
                                    <?php
                                    $startPage = max(1, $activePage - 2);
                                    $endPage = min($totalActivePages, $activePage + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl('active', 1) . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($activePage == $i ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl('active', $i) . '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    
                                    if ($endPage < $totalActivePages) {
                                        if ($endPage < $totalActivePages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl('active', $totalActivePages) . '">' . $totalActivePages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <li class="page-item <?php echo $activePage >= $totalActivePages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl('active', $activePage + 1); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Expired Budgets Table -->
                    <div class="card" data-section="expired">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Expired Budgets</h5>
                                <div class="d-flex gap-2">
                                    <form method="GET" class="d-flex">
                                        <input type="hidden" name="section" value="expired">
                                        <input type="text" name="expired_search" class="form-control me-2" placeholder="Search by ID or name" value="<?php echo htmlspecialchars($expiredSearch); ?>">
                                        <button type="submit" class="btn btn-light">
                                            <i class="bx bx-search"></i>
                                        </button>
                                    </form>
                                    <form method="GET" class="d-flex gap-2">
                                        <input type="hidden" name="section" value="expired">
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" name="expired_start_date" class="form-control date-picker" placeholder="Start Date" value="<?php echo htmlspecialchars($expiredStartDate); ?>">
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" name="expired_end_date" class="form-control date-picker" placeholder="End Date" value="<?php echo htmlspecialchars($expiredEndDate); ?>">
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                        <button type="submit" class="btn btn-light">
                                            <i class="bx bx-filter"></i>
                                        </button>
                                        <?php if ($expiredSearch || $expiredStartDate || $expiredEndDate): ?>
                                            <a href="?section=expired" class="btn btn-light">
                                                <i class="bx bx-x"></i>
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'budget_name'); ?>" class="text-decoration-none text-dark">
                                                    Budget Name <?php echo getSortIcon('expired', 'budget_name'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'department_name'); ?>" class="text-decoration-none text-dark">
                                                    Department <?php echo getSortIcon('expired', 'department_name'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'amount_allocated'); ?>" class="text-decoration-none text-dark">
                                                    Allocated Amount <?php echo getSortIcon('expired', 'amount_allocated'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'amount_spent'); ?>" class="text-decoration-none text-dark">
                                                    Spent Amount <?php echo getSortIcon('expired', 'amount_spent'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'amount_remaining'); ?>" class="text-decoration-none text-dark">
                                                    Remaining <?php echo getSortIcon('expired', 'amount_remaining'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'start_date'); ?>" class="text-decoration-none text-dark">
                                                    Start Date <?php echo getSortIcon('expired', 'start_date'); ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortUrl('expired', 'end_date'); ?>" class="text-decoration-none text-dark">
                                                    End Date <?php echo getSortIcon('expired', 'end_date'); ?>
                                                </a>
                                            </th>
                                            <th>Utilization</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($budget = $expiredBudgets->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($budget['budget_id']); ?></td>
                                                <td><?php echo htmlspecialchars($budget['budget_name']); ?></td>
                                                <td><?php echo htmlspecialchars($budget['department_name']); ?></td>
                                                <td><?php echo number_format($budget['amount_allocated'], 2); ?></td>
                                                <td><?php echo number_format($budget['amount_spent'], 2); ?></td>
                                                <td><?php echo number_format($budget['amount_remaining'], 2); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($budget['start_date'])); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($budget['end_date'])); ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-secondary" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($budget['utilization_percentage'], 100); ?>%">
                                                            <?php echo number_format($budget['utilization_percentage'], 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">Expired</span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Expired Budgets Pagination -->
                            <?php if ($totalExpiredPages > 1): ?>
                            <nav aria-label="Expired budgets pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $expiredPage <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl('expired', $expiredPage - 1); ?>">Previous</a>
                                    </li>
                                    
                                    <?php
                                    $startPage = max(1, $expiredPage - 2);
                                    $endPage = min($totalExpiredPages, $expiredPage + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl('expired', 1) . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($expiredPage == $i ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="' . getPaginationUrl('expired', $i) . '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    
                                    if ($endPage < $totalExpiredPages) {
                                        if ($endPage < $totalExpiredPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl('expired', $totalExpiredPages) . '">' . $totalExpiredPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <li class="page-item <?php echo $expiredPage >= $totalExpiredPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl('expired', $expiredPage + 1); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="footer">
                <div class="container-fluid">
                    <div class="row text-body-secondary">
                        <div class="col-6 text-start">
                            <a class="text-body-secondary" href="#">
                                <strong>e-commerce system</strong>
                            </a>
                        </div>
                        <div class="col-6 text-end text-body-secondary d-none d-md-block">
                            <ul class="list-inline mb-0">
                                <li class="list-inline-item">
                                    <a class="text-body-secondary" href="#">Contact</a>
                                </li>
                                <li class="list-inline-item">
                                    <a class="text-body-secondary" href="#">About Us</a>
                                </li>
                                <li class="list-inline-item">
                                    <a class="text-body-secondary" href="#">Terms & Conditions</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize Flatpickr
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d",
            allowInput: true,
            theme: "material_blue",
            maxDate: "today",
            disableMobile: "true",
        });

        // Function to clear date input
        function clearDate(element) {
            const input = element.previousElementSibling;
            input.value = '';
            input.dispatchEvent(new Event('change'));
            element.style.display = 'none';
        }

        // Show/hide clear button based on input value
        document.querySelectorAll('.date-picker').forEach(input => {
            input.addEventListener('input', function() {
                const clearBtn = this.nextElementSibling;
                clearBtn.style.display = this.value ? 'block' : 'none';
            });
        });

       
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            html.setAttribute('data-theme', savedTheme);
            themeToggle.checked = savedTheme === 'dark';
        }

        // Initialize Budget Donut Chart
        const ctx = document.getElementById('budgetDonutChart').getContext('2d');
        let chart;

        // Initialize Budget Distribution Chart
        const distributionCtx = document.getElementById('budgetDistributionChart').getContext('2d');
        let distributionChart;

        function updateChartsTheme(isDark) {
            const textColor = isDark ? '#FFFFFF' : '#212529';
            
            // Update Donut Chart
            if (chart) {
                chart.destroy();
            }

            chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Spent', 'Remaining'],
                    datasets: [{
                        data: [
                            <?php echo $totalActiveSpent; ?>,
                            <?php echo $totalActiveBudget - $totalActiveSpent; ?>
                        ],
                        backgroundColor: [
                            '#dc3545',  // Red for spent
                            '#28a745'   // Green for remaining
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Budget Utilization',
                            color: textColor,
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });

            // Update Distribution Chart
            if (distributionChart) {
                distributionChart.destroy();
            }

            distributionChart = new Chart(distributionCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($departmentBudgets)); ?>,
                    datasets: [{
                        label: 'Budget Allocation',
                        data: <?php echo json_encode(array_values($departmentBudgets)); ?>,
                        backgroundColor: [
                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                            '#5a5c69', '#858796', '#6f42c1', '#fd7e14', '#20c9a6'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: textColor,
                                font: {
                                    size: 12
                                },
                                padding: 20
                            }
                        },
                        title: {
                            display: true,
                            text: 'Department Budget Distribution',
                            color: textColor,
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₱${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initial chart render
        updateChartsTheme(document.documentElement.getAttribute('data-theme') === 'dark');

        // Update charts when theme changes
        themeToggle.addEventListener('change', () => {
            if (themeToggle.checked) {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                updateChartsTheme(true);
            } else {
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
                updateChartsTheme(false);
            }
        });

        // Also update charts based on saved theme preference
        if (savedTheme) {
            updateChartsTheme(savedTheme === 'dark');
        }
    
        // Scroll to the correct section on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section');
            
            if (section) {
                const targetCard = document.querySelector(`[data-section="${section}"]`);
                if (targetCard) {
                    targetCard.scrollIntoView({ behavior: 'auto', block: 'start' });
                }
            }
        });
    </script>
</body>
</html>

