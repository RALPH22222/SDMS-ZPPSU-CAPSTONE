<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';

try {    
    $stmt = $pdo->query("SELECT staff_number FROM marshal ORDER BY id DESC LIMIT 1");
    $lastStaff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $currentYear = date('Y');
    $nextNumber = 1;
    
    if ($lastStaff && !empty($lastStaff['staff_number'])) {
        if (preg_match('/^(\d{4})-(\d{4})$/', $lastStaff['staff_number'], $matches)) {
            $lastYear = $matches[1];
            $lastNumber = (int)$matches[2];
            
            if ($lastYear == $currentYear) {
                $nextNumber = $lastNumber + 1;
            }
        }
    }
    
    $nextStaffNumber = sprintf('%s-%04d', $currentYear, $nextNumber);
    
    echo json_encode([
        'success' => true,
        'staff_number' => $nextStaffNumber
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate staff number: ' . $e->getMessage()
    ]);
}
