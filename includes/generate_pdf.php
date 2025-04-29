<?php
require_once('tcpdf/tcpdf.php');
require_once 'connection.php';

class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'Budget Management Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Get status from URL parameter
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Budget Management System');
$pdf->SetTitle('Budget Report - ' . ucfirst($status));

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add a page
$pdf->AddPage();

// Get current date and time
$currentDateTime = date('Y-m-d H:i:s');

// Add report title and timestamp
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Budget Report - ' . ucfirst($status), 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Generated on: ' . $currentDateTime, 0, 1, 'C');
$pdf->Ln(10);

// Build the query based on status
$sql = "SELECT b.*, d.department_name 
        FROM budget b 
        LEFT JOIN department d ON b.department_id = d.department_id 
        WHERE 1=1";

switch($status) {
    case 'active':
        $sql .= " AND b.status = 'active' AND b.end_date >= CURDATE()";
        break;
    case 'expired':
        $sql .= " AND (b.status = 'expired' OR (b.status = 'active' AND b.end_date < CURDATE()))";
        break;
    case 'deleted':
        $sql .= " AND b.status = 'deleted'";
        break;
    default:
        // Show all budgets
        break;
}

$sql .= " ORDER BY b.start_date DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Table header
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(25, 10, 'ID', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Budget Name', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Department', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Allocated', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Remaining', 1, 0, 'C');
    $pdf->Cell(25, 10, 'Status', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('helvetica', '', 10);
    while($row = $result->fetch_assoc()) {
        $pdf->Cell(25, 10, $row['budget_id'], 1, 0, 'C');
        $pdf->Cell(40, 10, $row['budget_name'], 1, 0, 'L');
        $pdf->Cell(40, 10, $row['department_name'], 1, 0, 'L');
        $pdf->Cell(30, 10, '$' . number_format($row['amount_allocated'], 2), 1, 0, 'R');
        $pdf->Cell(30, 10, '$' . number_format($row['amount_remaining'], 2), 1, 0, 'R');
        $pdf->Cell(25, 10, $row['status'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(0, 10, 'No ' . $status . ' budget data available', 0, 1, 'C');
}

// Add summary section
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Summary', 0, 1, 'L');

// Get summary statistics
$sql = "SELECT 
    COUNT(*) as total_budgets,
    SUM(amount_allocated) as total_allocated,
    SUM(amount_remaining) as total_remaining,
    AVG(amount_allocated) as average_allocated
    FROM budget WHERE 1=1";

switch($status) {
    case 'active':
        $sql .= " AND status = 'active' AND end_date >= CURDATE()";
        break;
    case 'expired':
        $sql .= " AND (status = 'expired' OR (status = 'active' AND end_date < CURDATE()))";
        break;
    case 'deleted':
        $sql .= " AND status = 'deleted'";
        break;
    default:
        // Show all budgets
        break;
}

$result = $conn->query($sql);
$summary = $result->fetch_assoc();

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Total Budgets: ' . $summary['total_budgets'], 0, 1, 'L');
$pdf->Cell(0, 10, 'Total Allocated: $' . number_format($summary['total_allocated'], 2), 0, 1, 'L');
$pdf->Cell(0, 10, 'Total Remaining: $' . number_format($summary['total_remaining'], 2), 0, 1, 'L');
$pdf->Cell(0, 10, 'Average Allocation: $' . number_format($summary['average_allocated'], 2), 0, 1, 'L');

// Close and output PDF document
$pdf->Output('budget_report_' . $status . '_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
?> 