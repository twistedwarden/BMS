<?php
require_once 'includes/connection.php';

// Get filter values
$search = isset($_POST['search']) ? $_POST['search'] : '';
$department = isset($_POST['department']) ? $_POST['department'] : '';
$sort = isset($_POST['sort']) ? $_POST['sort'] : 'end_date';

// Build the query
$query = "SELECT b.*, d.department_name 
          FROM budget b 
          LEFT JOIN department d ON b.department_id = d.department_id 
          WHERE b.status = 'Expired'";

// Add search condition
if ($search) {
    $query .= " AND (b.budget_name LIKE ? OR b.budget_id LIKE ?)";
}

// Add department filter
if ($department) {
    $query .= " AND b.department_id = ?";
}

// Add sorting
switch ($sort) {
    case 'end_date_asc':
        $query .= " ORDER BY b.end_date ASC";
        break;
    case 'amount_allocated':
        $query .= " ORDER BY b.amount_allocated DESC";
        break;
    case 'amount_allocated_asc':
        $query .= " ORDER BY b.amount_allocated ASC";
        break;
    case 'amount_remaining':
        $query .= " ORDER BY b.amount_remaining DESC";
        break;
    case 'amount_remaining_asc':
        $query .= " ORDER BY b.amount_remaining ASC";
        break;
    default:
        $query .= " ORDER BY b.end_date DESC";
}

$stmt = $conn->prepare($query);

// Bind parameters if needed
if ($search && $department) {
    $search_param = "%$search%";
    $stmt->bind_param("ssi", $search_param, $search_param, $department);
} elseif ($search) {
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
} elseif ($department) {
    $stmt->bind_param("i", $department);
}

$stmt->execute();
$result = $stmt->get_result();

// Output the table rows
while ($budget = $result->fetch_assoc()): 
?>
    <tr>
        <td><?php echo $budget['budget_id']; ?></td>
        <td><?php echo htmlspecialchars($budget['budget_name']); ?></td>
        <td><?php echo htmlspecialchars($budget['department_name']); ?></td>
        <td>$<?php echo number_format($budget['amount_allocated'], 2); ?></td>
        <td>$<?php echo number_format($budget['amount_remaining'], 2); ?></td>
        <td><?php echo date('M d, Y', strtotime($budget['end_date'])); ?></td>
        <td>
            <button class="btn btn-sm btn-danger" onclick="deleteExpiredBudget(<?php echo $budget['budget_id']; ?>)">
                <i class="bx bx-trash"></i>
            </button>
        </td>
    </tr>
<?php endwhile; ?> 