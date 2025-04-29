<?php
require_once 'includes/connection.php';
require_once 'includes/modal/logoutModal.php';

// Get search and sort parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total count of records
$countQuery = "SELECT COUNT(*) as total FROM budget_history bh 
               JOIN budget b ON bh.budget_id = b.budget_id 
               WHERE b.budget_name LIKE ? OR b.budget_id LIKE ?";
$countStmt = $conn->prepare($countQuery);
$searchParam = "%$search%";
$countStmt->bind_param("ss", $searchParam, $searchParam);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $records_per_page);

// Build the query with search, sort, and pagination
$query = "SELECT bh.*, b.budget_name 
          FROM budget_history bh 
          JOIN budget b ON bh.budget_id = b.budget_id 
          WHERE b.budget_name LIKE ? OR b.budget_id LIKE ?
          ORDER BY bh.date " . ($sort === 'asc' ? 'ASC' : 'DESC') . ", bh.history_id DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $searchParam, $searchParam, $records_per_page, $offset);
$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget History</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
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
                    <div class="card">
                        <div class="card-header text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="h5 mb-0">Budget Log</h2>
                                <div class="d-flex gap-2 align-items-center">
                                    <form method="GET" class="d-flex my-2">
                                        <input type="text" name="search" class="form-control me-1" placeholder="Search by ID or name" value="<?php echo htmlspecialchars($search); ?>">
                                        <button class="btn btn-light" type="submit">
                                            <i class="bx bx-search"></i>
                                        </button>
                                    </form>
                                    <div class="btn-group">
                                        <a href="?search=<?php echo urlencode($search); ?>&sort=desc" class="btn btn-light <?php echo $sort === 'desc' ? 'active' : ''; ?>">
                                            <i class='bx bx-sort-down'></i> Newest
                                        </a>
                                        <a href="?search=<?php echo urlencode($search); ?>&sort=asc" class="btn btn-light <?php echo $sort === 'asc' ? 'active' : ''; ?>">
                                            <i class='bx bx-sort-up'></i> Oldest
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Budget ID</th>
                                            <th>Budget Name</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($record = $history->fetch_assoc()): ?>
                                            <tr class="budget-row" data-budget-id="<?php echo $record['budget_id']; ?>" style="cursor: pointer;">
                                                <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['budget_id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['budget_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['description']); ?></td>
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
                                        <a class="page-link" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($search) . '&sort=' . $sort . '&page=1">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="?search=' . urlencode($search) . '&sort=' . $sort . '&page=' . $i . '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($search) . '&sort=' . $sort . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

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

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <script>
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

        // Initialize Bootstrap modals
        const detailsModal = new bootstrap.Modal(document.getElementById('budgetDetailsModal'));

        // Add click event to budget rows
        document.querySelectorAll('.budget-row').forEach(row => {
            row.addEventListener('click', function() {
                const budgetId = this.getAttribute('data-budget-id');
                showBudgetDetails(budgetId);
            });
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
    </script>
</body>
</html>
