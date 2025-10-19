`<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$query = $_GET['q'] ?? '';
$results = [];

if (strlen($query) >= 2) {
    $search = "%$query%";
    $stmt = $pdo->prepare("SELECT id, student_number, first_name, middle_name, last_name 
                          FROM students 
                          WHERE student_number LIKE ? 
                          OR CONCAT(first_name, ' ', last_name) LIKE ? 
                          OR CONCAT(last_name, ', ', first_name) LIKE ?
                          LIMIT 10");
    $stmt->execute([$search, $search, $search]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($results);
