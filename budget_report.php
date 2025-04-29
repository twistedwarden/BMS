<?php
require_once 'includes/connection.php';
require_once 'includes/modal/logoutModal.php';
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agora</title>
    <!-- <link href="https://cdn.lineicons.com/4.0/lineicons.css" rel="stylesheet" /> -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/theme-toggle.css">
</head>

<body>
    <div class="wrapper">

        <?php include 'includes/sidebar.php'; ?>


        <!-- MAIN HEADER SECTION -->
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


            <!-- MAIN SECTION -->
            <main class="content px-3 py-4">
                <div class="container-fluid">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="fw-bold fs-4">Budget Report</h3>
                            <div class="btn-group">
                                <a href="includes/generate_pdf.php?status=active" class="btn btn-success">
                                    <i class='bx bx-check-circle'></i> Active Budgets
                                </a>
                                <a href="includes/generate_pdf.php?status=expired" class="btn btn-warning">
                                    <i class='bx bx-time'></i> Expired Budgets
                                </a>
                                <a href="includes/generate_pdf.php?status=deleted" class="btn btn-danger">
                                    <i class='bx bx-trash'></i> Deleted Budgets
                                </a>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <?php
                            // Get statistics for each status
                            $stats = [
                                'active' => ['icon' => 'bx-check-circle', 'color' => 'success', 'title' => 'Active Budgets'],
                                'expired' => ['icon' => 'bx-time', 'color' => 'warning', 'title' => 'Expired Budgets'],
                                'deleted' => ['icon' => 'bx-trash', 'color' => 'danger', 'title' => 'Deleted Budgets']
                            ];

                            foreach ($stats as $status => $info) {
                                $sql = "SELECT 
                                    COUNT(*) as count,
                                    SUM(amount_allocated) as total,
                                    AVG(amount_allocated) as average
                                    FROM budget WHERE 1=1";
                                
                                if ($status == 'active') {
                                    $sql .= " AND status = 'active' AND end_date >= CURDATE()";
                                } elseif ($status == 'expired') {
                                    $sql .= " AND (status = 'expired' OR (status = 'active' AND end_date < CURDATE()))";
                                } else {
                                    $sql .= " AND status = 'deleted'";
                                }
                                
                                $result = $conn->query($sql);
                                $data = $result->fetch_assoc();
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-<?php echo $info['color']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="text-muted mb-2"><?php echo $info['title']; ?></h6>
                                                    <h3 class="mb-0"><?php echo $data['count']; ?></h3>
                                                </div>
                                                <div class="bg-<?php echo $info['color']; ?> bg-opacity-10 p-3 rounded">
                                                    <i class='bx <?php echo $info['icon']; ?> fs-1 text-<?php echo $info['color']; ?>'></i>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <p class="mb-1"><small>Total: $<?php echo number_format($data['total'], 2); ?></small></p>
                                                <p class="mb-0"><small>Average: $<?php echo number_format($data['average'], 2); ?></small></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <!-- Recent Budgets Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Budgets</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Budget Name</th>
                                                <th>Department</th>
                                                <th>Allocated</th>
                                                <th>Remaining</th>
                                                <th>Status</th>
                                                <th>End Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $sql = "SELECT b.*, d.department_name 
                                                    FROM budget b 
                                                    LEFT JOIN department d ON b.department_id = d.department_id 
                                                    ORDER BY b.start_date DESC LIMIT 5";
                                            $result = $conn->query($sql);
                                            
                                            if ($result->num_rows > 0) {
                                                while($row = $result->fetch_assoc()) {
                                                    $status_class = '';
                                                    if ($row['status'] == 'active' && $row['end_date'] >= date('Y-m-d')) {
                                                        $status_class = 'success';
                                                    } elseif ($row['status'] == 'expired' || ($row['status'] == 'active' && $row['end_date'] < date('Y-m-d'))) {
                                                        $status_class = 'warning';
                                                    } else {
                                                        $status_class = 'danger';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $row['budget_id']; ?></td>
                                                        <td><?php echo $row['budget_name']; ?></td>
                                                        <td><?php echo $row['department_name']; ?></td>
                                                        <td>$<?php echo number_format($row['amount_allocated'], 2); ?></td>
                                                        <td>$<?php echo number_format($row['amount_remaining'], 2); ?></td>
                                                        <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                                                        <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">No budgets found</td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
        </div>
    </div>
    
    <?php include 'includes/modal/logoutModal.php'; ?>

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
    </script>
</body>

</html>