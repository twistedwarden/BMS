<?php
require_once 'includes/connection.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Budget ID is required']);
    exit;
}

$budget_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT b.*, d.department_name 
                       FROM budget b 
                       LEFT JOIN department d ON b.department_id = d.department_id 
                       WHERE b.budget_id = ?");
$stmt->bind_param("i", $budget_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Budget not found']);
    exit;
}

$budget = $result->fetch_assoc();
echo json_encode($budget);

$stmt->close();
$conn->close();
?> 