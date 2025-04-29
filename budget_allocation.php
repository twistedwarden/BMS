<?php
require_once 'includes/connection.php';
require_once 'includes/modal/logoutModal.php';

// Function to record budget history
function recordBudgetHistory($conn, $budget_id, $description) {
    $stmt = $conn->prepare("INSERT INTO budget_history (budget_id, description, date) VALUES (?, ?, CURDATE())");
    $stmt->bind_param("is", $budget_id, $description);
    $stmt->execute();
    $stmt->close();
}

// Function to check and update budget status based on dates
function updateBudgetStatus($conn) {
    // Use MySQL's CURDATE() for consistent date comparison
    $stmt = $conn->prepare("UPDATE budget SET status = 'Expired' 
                          WHERE status = 'Active' 
                          AND DATE(end_date) < CURDATE()");
    $stmt->execute();
    $stmt->close();
    
    // Get affected budgets for history
    $stmt = $conn->prepare("SELECT budget_id, budget_name FROM budget 
                          WHERE status = 'Expired' 
                          AND DATE(end_date) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($budget = $result->fetch_assoc()) {
        recordBudgetHistory($conn, $budget['budget_id'], 
                          "Budget expired: " . $budget['budget_name']);
    }
    $stmt->close();
}

// Check and update budget statuses
updateBudgetStatus($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $conn->prepare("INSERT INTO budget (budget_name, department_id, amount_allocated, amount_remaining, start_date, end_date, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                $stmt->bind_param("siddss", 
                    $_POST['budget_name'],
                    $_POST['department_id'],
                    $_POST['amount_allocated'],
                    $_POST['amount_allocated'], // Initial amount_remaining equals amount_allocated
                    $_POST['start_date'],
                    $_POST['end_date']
                );
                if ($stmt->execute()) {
                    $budget_id = $conn->insert_id;
                    recordBudgetHistory($conn, $budget_id, "Budget created: " . $_POST['budget_name']);
                    $success_message = "Budget created successfully!";
                    // Check status after creation
                    updateBudgetStatus($conn);
                }
                $stmt->close();
                break;

            case 'update':
                // First get the current budget details
                $stmt = $conn->prepare("SELECT amount_allocated, amount_remaining FROM budget WHERE budget_id = ?");
                $stmt->bind_param("i", $_POST['budget_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_budget = $result->fetch_assoc();
                $stmt->close();

                // Calculate the difference in allocated amount
                $old_allocated = $current_budget['amount_allocated'];
                $new_allocated = $_POST['amount_allocated'];
                $difference = $new_allocated - $old_allocated;

                // Update the budget with new amount_remaining
                $stmt = $conn->prepare("UPDATE budget SET 
                    budget_name = ?,
                    department_id = ?,
                    amount_allocated = ?,
                    amount_remaining = amount_remaining + ?,
                    start_date = ?,
                    end_date = ?,
                    status = ?
                    WHERE budget_id = ?");
                $stmt->bind_param("siddsssi", 
                    $_POST['budget_name'],
                    $_POST['department_id'],
                    $_POST['amount_allocated'],
                    $difference,
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['status'],
                    $_POST['budget_id']
                );
                if ($stmt->execute()) {
                    recordBudgetHistory($conn, $_POST['budget_id'], "Budget updated: " . $_POST['budget_name']);
                    $success_message = "Budget updated successfully!";
                    // Check status after update
                    updateBudgetStatus($conn);
                }
                $stmt->close();
                break;

            case 'delete':
                $stmt = $conn->prepare("UPDATE budget SET status = 'Deleted' WHERE budget_id = ?");
                $stmt->bind_param("i", $_POST['budget_id']);
                if ($stmt->execute()) {
                    recordBudgetHistory($conn, $_POST['budget_id'], "Budget deleted");
                    $success_message = "Budget deleted successfully!";
                    // Store the deleted budget ID in session for potential undo
                    $_SESSION['last_deleted_budget'] = $_POST['budget_id'];
                }
                $stmt->close();
                break;

            case 'restore':
                $stmt = $conn->prepare("UPDATE budget SET status = 'Active' WHERE budget_id = ?");
                $stmt->bind_param("i", $_POST['budget_id']);
                if ($stmt->execute()) {
                    recordBudgetHistory($conn, $_POST['budget_id'], "Budget restored");
                    $success_message = "Budget restored successfully!";
                    unset($_SESSION['last_deleted_budget']);
                }
                $stmt->close();
                break;

            case 'delete_all_expired':
                $stmt = $conn->prepare("UPDATE budget SET status = 'Deleted' WHERE status = 'Expired'");
                if ($stmt->execute()) {
                    $success_message = "All expired budgets have been deleted successfully!";
                }
                $stmt->close();
                break;
        }
    }
}

// Fetch departments for dropdown
$departments = $conn->query("SELECT * FROM department ORDER BY department_name");

// Get filter values from GET parameters
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Get sort parameters
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'start_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Validate sort parameters
$allowed_columns = ['amount_allocated', 'amount_remaining', 'start_date'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'start_date';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'DESC';
}

// Build the count query for total records
$countQuery = "SELECT COUNT(*) as total 
               FROM budget b 
               LEFT JOIN department d ON b.department_id = d.department_id 
               WHERE b.status != 'Deleted' AND b.status != 'Expired'";

$countParams = array();
$countTypes = "";

if ($search_query) {
    $countQuery .= " AND b.budget_name LIKE ?";
    $countParams[] = "%$search_query%";
    $countTypes .= "s";
}

if ($selected_department) {
    $countQuery .= " AND b.department_id = ?";
    $countParams[] = $selected_department;
    $countTypes .= "i";
}

if ($selected_status) {
    $countQuery .= " AND b.status = ?";
    $countParams[] = $selected_status;
    $countTypes .= "s";
}

// Get total records
$countStmt = $conn->prepare($countQuery);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $records_per_page);

// Build the query with filters
$query = "SELECT b.*, d.department_name 
          FROM budget b 
          LEFT JOIN department d ON b.department_id = d.department_id 
          WHERE b.status != 'Deleted' AND b.status != 'Expired'";

$params = array();
$types = "";

if ($search_query) {
    $query .= " AND b.budget_name LIKE ?";
    $params[] = "%$search_query%";
    $types .= "s";
}

if ($selected_department) {
    $query .= " AND b.department_id = ?";
    $params[] = $selected_department;
    $types .= "i";
}

if ($selected_status) {
    $query .= " AND b.status = ?";
    $params[] = $selected_status;
    $types .= "s";
}

$query .= " ORDER BY b.$sort_column $sort_order LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the query with filters
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$budgets = $stmt->get_result();

// Function to generate sort URL
function getSortUrl($column) {
    global $sort_column, $sort_order, $search_query, $selected_department, $selected_status, $page;
    $params = array();
    
    if ($search_query) $params[] = "search=" . urlencode($search_query);
    if ($selected_department) $params[] = "department=" . urlencode($selected_department);
    if ($selected_status) $params[] = "status=" . urlencode($selected_status);
    if ($page > 1) $params[] = "page=" . $page;
    
    $new_order = ($sort_column == $column && $sort_order == 'ASC') ? 'DESC' : 'ASC';
    $params[] = "sort=" . urlencode($column);
    $params[] = "order=" . urlencode($new_order);
    
    return "?" . implode("&", $params);
}

// Function to generate pagination URL
function getPaginationUrl($pageNum) {
    global $sort_column, $sort_order, $search_query, $selected_department, $selected_status;
    $params = array();
    
    if ($search_query) $params[] = "search=" . urlencode($search_query);
    if ($selected_department) $params[] = "department=" . urlencode($selected_department);
    if ($selected_status) $params[] = "status=" . urlencode($selected_status);
    if ($sort_column != 'start_date') $params[] = "sort=" . urlencode($sort_column);
    if ($sort_order != 'DESC') $params[] = "order=" . urlencode($sort_order);
    $params[] = "page=" . $pageNum;
    
    return "?" . implode("&", $params);
}

// Function to get sort icon
function getSortIcon($column) {
    global $sort_column, $sort_order;
    if ($sort_column != $column) return '<i class="bx bx-sort"></i>';
    return $sort_order == 'ASC' ? '<i class="bx bx-sort-up"></i>' : '<i class="bx bx-sort-down"></i>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Allocation</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/budget.css">
    <link rel="stylesheet" href="styles/theme-toggle.css">
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <a class="navbar-brand" href="#">Budget Management</a>
                    <div class="theme-toggle">
                        <i class='bx bx-sun'></i>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="themeToggle">
                        </div>
                        <i class='bx bx-moon'></i>
                    </div>
                </div>
            </nav>

            <main class="content px-3 py-4">
                <div class="container-fluid">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <?php if (isset($_SESSION['last_deleted_budget']) && strpos($success_message, 'deleted') !== false): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="budget_id" value="<?php echo $_SESSION['last_deleted_budget']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning ms-2">
                                        <i class="bx bx-undo"></i> Undo
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h2 class="h5 mb-0">Current Budgets</h2>
                                    <div>
                                        <button type="button" class="btn btn-light border-dark btn-sm me-2" data-bs-toggle="modal" data-bs-target="#expiredBudgetsModal">
                                            <i class="bx bx-history"></i> View Expired Budgets
                                        </button>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createBudgetModal">
                                            <i class="bx bx-plus"></i> Create New Budget
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Search and Filter section -->
                                    <div class="row mb-3 align-items-center">
                                        <div class="col-md-6">
                                            <form method="GET" class="d-flex align-items-center h-100">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="search" placeholder="Search budget name..." value="<?php echo htmlspecialchars($search_query); ?>">
                                                    <button class="btn btn-outline-secondary border-dark" type="submit">
                                                        <i class="bx bx-search"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="col-md-6">
                                            <form method="GET" class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="department_filter" class="form-label">Filter by Department</label>
                                                    <select class="form-select" id="department_filter" name="department" onchange="this.form.submit()">
                                                        <option value="">All Departments</option>
                                                        <?php 
                                                        $departments->data_seek(0);
                                                        while ($dept = $departments->fetch_assoc()): 
                                                        ?>
                                                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $selected_department == $dept['department_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="status_filter" class="form-label">Filter by Status</label>
                                                    <select class="form-select" id="status_filter" name="status" onchange="this.form.submit()">
                                                        <option value="">All Statuses</option>
                                                        <option value="Active" <?php echo $selected_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="Inactive" <?php echo $selected_status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="Expired" <?php echo $selected_status == 'Expired' ? 'selected' : ''; ?>>Expired</option>
                                                    </select>
                                                </div>
                                                <!-- Hidden input to preserve search query when using filters -->
                                                <?php if ($search_query): ?>
                                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Budget Name</th>
                                                    <th>Department</th>
                                                    <th>
                                                        <a href="<?php echo getSortUrl('amount_allocated'); ?>" class="text-decoration-none text-dark">
                                                            Amount <?php echo getSortIcon('amount_allocated'); ?>
                                                        </a>
                                                    </th>
                                                    <th>
                                                        <a href="<?php echo getSortUrl('amount_remaining'); ?>" class="text-decoration-none text-dark">
                                                            Remaining <?php echo getSortIcon('amount_remaining'); ?>
                                                        </a>
                                                    </th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($budget = $budgets->fetch_assoc()): ?>
                                                    <tr class="budget-row" data-budget-id="<?php echo $budget['budget_id']; ?>" style="cursor: pointer;">
                                                        <td><?php echo htmlspecialchars($budget['budget_id']); ?></td>
                                                        <td><?php echo htmlspecialchars($budget['budget_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($budget['department_name']); ?></td>
                                                        <td>$<?php echo number_format($budget['amount_allocated'], 2); ?></td>
                                                        <td>$<?php echo number_format($budget['amount_remaining'], 2); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $budget['status'] === 'Active' ? 'success' : ($budget['status'] === 'Inactive' ? 'warning' : 'danger'); ?>">
                                                                <?php echo htmlspecialchars($budget['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <button class="btn btn-sm mx-1 text-white" style="background-color: var(--secondary-color);" onclick="event.stopPropagation(); editBudget(<?php echo $budget['budget_id']; ?>)">
                                                                    <i class="bx bx-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger mx-1" onclick="event.stopPropagation(); deleteBudget(<?php echo $budget['budget_id']; ?>)">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Page navigation" class="mt-4">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo getPaginationUrl($page - 1); ?>">Previous</a>
                                            </li>
                                            
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            
                                            if ($startPage > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl(1) . '">1</a></li>';
                                                if ($startPage > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++) {
                                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                                echo '<a class="page-link" href="' . getPaginationUrl($i) . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            
                                            if ($endPage < $totalPages) {
                                                if ($endPage < $totalPages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="' . getPaginationUrl($totalPages) . '">' . $totalPages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo getPaginationUrl($page + 1); ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Create Budget Modal -->
            <div class="modal fade" id="createBudgetModal" tabindex="-1" aria-labelledby="createBudgetModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createBudgetModalLabel">Create New Budget</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="POST" class="budget-form">
                                <input type="hidden" name="action" value="create">
                                
                                <div class="mb-3">
                                    <label for="budget_name" class="form-label">Budget Name</label>
                                    <input type="text" class="form-control" id="budget_name" name="budget_name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="department_id" class="form-label">Department</label>
                                    <select class="form-select" id="department_id" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php 
                                        $departments->data_seek(0);
                                        while ($dept = $departments->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $dept['department_id']; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="amount_allocated" class="form-label">Amount Allocated</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="amount_allocated" name="amount_allocated" step="0.01" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" class="form-control date-picker" id="start_date" name="start_date" required>
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" class="form-control date-picker" id="end_date" name="end_date" required>
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Create Budget</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Details Modal -->
            <div class="modal fade" id="budgetDetailsModal" tabindex="-1" aria-labelledby="budgetDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="budgetDetailsModalLabel">Budget Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="budget-details">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Budget Name:</strong>
                                        <p id="detail-budget-name"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Department:</strong>
                                        <p id="detail-department"></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Amount Allocated:</strong>
                                        <p id="detail-amount-allocated"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Amount Remaining:</strong>
                                        <p id="detail-amount-remaining"></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Start Date:</strong>
                                        <p id="detail-start-date"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>End Date:</strong>
                                        <p id="detail-end-date"></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Status:</strong>
                                        <p id="detail-status"></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Utilization:</strong>
                                        <div class="progress mt-2" style="height: 25px;">
                                            <div id="detail-utilization" class="progress-bar" role="progressbar" 
                                                style="width: 0%; font-size: 14px; line-height: 25px;"
                                                aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Budget Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel">Edit Budget</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editBudgetForm" method="POST">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="budget_id" id="edit_budget_id">
                                
                                <div class="mb-3">
                                    <label for="edit_budget_name" class="form-label">Budget Name</label>
                                    <input type="text" class="form-control" id="edit_budget_name" name="budget_name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_department_id" class="form-label">Department</label>
                                    <select class="form-select" id="edit_department_id" name="department_id" required>
                                        <?php 
                                        $departments->data_seek(0);
                                        while ($dept = $departments->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $dept['department_id']; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_amount_allocated" class="form-label">Amount Allocated</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="edit_amount_allocated" name="amount_allocated" step="0.01" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_start_date" class="form-label">Start Date</label>
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" class="form-control date-picker" id="edit_start_date" name="start_date" required>
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_end_date" class="form-label">End Date</label>
                                        <div class="date-picker-container">
                                            <i class="bx bx-calendar date-icon"></i>
                                            <input type="text" class="form-control date-picker" id="edit_end_date" name="end_date" required>
                                            <i class="bx bx-x clear-date" onclick="clearDate(this)"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="status" required>
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Update Budget</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Single Budget Confirmation Modal -->
            <div class="modal fade" id="deleteSingleBudgetModal" tabindex="-1" aria-labelledby="deleteSingleBudgetModalLabel" aria-hidden="true" style="z-index: 1060;">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteSingleBudgetModalLabel">
                                <i class='bx bx-trash text-danger me-2'></i>Confirm Delete
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-error-circle text-warning fs-4 me-2'></i>
                                <span>Are you sure you want to delete this budget? This action cannot be undone.</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class='bx bx-x me-1'></i>Cancel
                            </button>
                            <form id="deleteSingleBudgetForm" method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="budget_id" id="delete_single_budget_id">
                                <button type="submit" class="btn btn-danger">
                                    <i class='bx bx-trash me-1'></i>Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expired Budgets Modal -->
            <div class="modal fade" id="expiredBudgetsModal" tabindex="-1" aria-labelledby="expiredBudgetsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="expiredBudgetsModalLabel">Expired Budgets</h5>
                            <div>
                                <button type="button" class="btn btn-danger me-2" onclick="deleteAllExpiredBudgets()">
                                    <i class="bx bx-trash"></i> Delete All Expired
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                        </div>
                        <div class="modal-body">
                            <!-- Search and Filter section for expired budgets -->
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center h-100">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="expired_search" placeholder="Search budget name or ID..." onkeypress="handleExpiredSearchKeyPress(event)">
                                            <button class="btn btn-outline-secondary" type="button" onclick="updateExpiredTable()">
                                                <i class="bx bx-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="expired_department_filter" class="form-label">Filter by Department</label>
                                            <select class="form-select" id="expired_department_filter" onchange="updateExpiredTable()">
                                                <option value="">All Departments</option>
                                                <?php 
                                                $departments->data_seek(0);
                                                while ($dept = $departments->fetch_assoc()): 
                                                ?>
                                                    <option value="<?php echo $dept['department_id']; ?>">
                                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="expired_sort" class="form-label">Sort by</label>
                                            <select class="form-select" id="expired_sort" onchange="updateExpiredTable()">
                                                <option value="end_date">End Date (Recent First)</option>
                                                <option value="end_date_asc">End Date (Oldest First)</option>
                                                <option value="amount_allocated">Amount Allocated (High to Low)</option>
                                                <option value="amount_allocated_asc">Amount Allocated (Low to High)</option>
                                                <option value="amount_remaining">Amount Remaining (High to Low)</option>
                                                <option value="amount_remaining_asc">Amount Remaining (Low to High)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Budget Name</th>
                                            <th>Department</th>
                                            <th>Amount Allocated</th>
                                            <th>Amount Remaining</th>
                                            <th>End Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="expiredBudgetsTableBody">
                                        <!-- Initial content will be loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete All Expired Confirmation Modal -->
            <div class="modal fade" id="deleteAllExpiredModal" tabindex="-1" aria-labelledby="deleteAllExpiredModalLabel" aria-hidden="true" style="z-index: 1060;">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteAllExpiredModalLabel">
                                <i class='bx bx-trash text-danger me-2'></i>Confirm Delete All
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex align-items-center">
                                <i class='bx bx-error-circle text-warning fs-4 me-2'></i>
                                <span>Are you sure you want to delete all expired budgets? This action cannot be undone.</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class='bx bx-x me-1'></i>Cancel
                            </button>
                            <form id="deleteAllExpiredForm" method="POST">
                                <input type="hidden" name="action" value="delete_all_expired">
                                <button type="submit" class="btn btn-danger">
                                    <i class='bx bx-trash me-1'></i>Delete All
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER SECTION -->
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row text-body-secondary">
                        <div class="col-6 text-start ">
                            <a class="text-body-secondary" href=" #">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        // Initialize Flatpickr for both create and edit modals
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d",
            allowInput: true,
            theme: "material_blue",
            disableMobile: "true",
            defaultDate: "today"
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

        // Initialize Bootstrap modals
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteSingleBudgetModal'));
        const detailsModal = new bootstrap.Modal(document.getElementById('budgetDetailsModal'));
        const createModal = new bootstrap.Modal(document.getElementById('createBudgetModal'));

        // Add click event to budget rows
        document.querySelectorAll('.budget-row').forEach(row => {
            row.addEventListener('click', function() {
                const budgetId = this.getAttribute('data-budget-id');
                showBudgetDetails(budgetId);
            });
        });

        // Set current date as default for start date when create modal opens
        document.getElementById('createBudgetModal').addEventListener('show.bs.modal', function() {
            const startDateInput = document.getElementById('start_date');
            startDateInput._flatpickr.setDate('today');
        });

        function showBudgetDetails(budgetId) {
            fetch(`get_budget.php?id=${budgetId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('detail-budget-name').textContent = data.budget_name;
                    document.getElementById('detail-department').textContent = data.department_name;
                    document.getElementById('detail-amount-allocated').textContent = '$' + parseFloat(data.amount_allocated).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('detail-amount-remaining').textContent = '$' + parseFloat(data.amount_remaining).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById('detail-start-date').textContent = data.start_date;
                    document.getElementById('detail-end-date').textContent = data.end_date;
                    
                    // Set status with appropriate badge
                    const statusElement = document.getElementById('detail-status');
                    statusElement.innerHTML = `<span class="badge bg-${data.status === 'Active' ? 'success' : (data.status === 'Inactive' ? 'warning' : 'danger')}">${data.status}</span>`;
                    
                    // Calculate and display utilization percentage
                    const allocated = parseFloat(data.amount_allocated);
                    const remaining = parseFloat(data.amount_remaining);
                    const used = allocated - remaining;
                    const utilization = (used / allocated) * 100;
                    
                    const utilizationBar = document.getElementById('detail-utilization');
                    utilizationBar.style.width = utilization + '%';
                    utilizationBar.textContent = utilization.toFixed(1) + '%';
                    utilizationBar.className = 'progress-bar ' + 
                        (utilization < 50 ? 'bg-success' : 
                         utilization < 80 ? 'bg-warning' : 'bg-danger');
                    
                    detailsModal.show();
                });
        }

        function editBudget(budgetId) {
            // Fetch budget details and populate form
            fetch(`get_budget.php?id=${budgetId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_budget_id').value = data.budget_id;
                    document.getElementById('edit_budget_name').value = data.budget_name;
                    document.getElementById('edit_department_id').value = data.department_id;
                    document.getElementById('edit_amount_allocated').value = data.amount_allocated;
                    
                    // Set date values for Flatpickr
                    const startDateInput = document.getElementById('edit_start_date');
                    const endDateInput = document.getElementById('edit_end_date');
                    startDateInput._flatpickr.setDate(data.start_date);
                    endDateInput._flatpickr.setDate(data.end_date);
                    
                    document.getElementById('edit_status').value = data.status;
                    editModal.show();
                });
        }

        function deleteBudget(budgetId) {
            document.getElementById('delete_single_budget_id').value = budgetId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteSingleBudgetModal'));
            deleteModal.show();
        }
        
        function deleteExpiredBudget(budgetId) {
            document.getElementById('delete_single_budget_id').value = budgetId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteSingleBudgetModal'));
            deleteModal.show();
        }

        function deleteAllExpiredBudgets() {
            const deleteAllModal = new bootstrap.Modal(document.getElementById('deleteAllExpiredModal'));
            deleteAllModal.show();
        }

        // Theme Toggle Functionality
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            html.setAttribute('data-theme', savedTheme);
            themeToggle.checked = savedTheme === 'dark';
        }

        // Toggle theme on switch click
        themeToggle.addEventListener('change', () => {
            if (themeToggle.checked) {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });

        function updateExpiredTable() {
            const search = document.getElementById('expired_search').value;
            const department = document.getElementById('expired_department_filter').value;
            const sort = document.getElementById('expired_sort').value;

            // Show loading state
            const tableBody = document.getElementById('expiredBudgetsTableBody');
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

            // Make AJAX request
            fetch('get_expired_budgets.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `search=${encodeURIComponent(search)}&department=${encodeURIComponent(department)}&sort=${encodeURIComponent(sort)}`
            })
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>';
            });
        }

        // Initialize expired table when modal is shown
        const expiredModal = document.getElementById('expiredBudgetsModal');
        if (expiredModal) {
            expiredModal.addEventListener('shown.bs.modal', function () {
                updateExpiredTable();
            });
        }

        function handleExpiredSearchKeyPress(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                updateExpiredTable();
            }
        }
    </script>
</body>
</html>
