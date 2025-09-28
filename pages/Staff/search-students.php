<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!$isAjax) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request']);
    exit;
}
if (!isset($_GET['q'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Search term is required']);
    exit;
}

try {
    require_once '../../database/database.php';
    require_once '../../includes/helpers.php';
    
    $searchTerm = '%' . trim($_GET['q']) . '%';
    
    $stmt = $pdo->prepare('SELECT id, student_number, first_name, last_name 
                          FROM students 
                          WHERE (student_number LIKE ? 
                             OR first_name LIKE ? 
                             OR last_name LIKE ?)
                             AND (class_id IS NULL OR class_id = 0)
                          LIMIT 10');
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('Search for "' . $_GET['q'] . '" returned ' . count($results) . ' results');
    
    $json = json_encode($results);
    if ($json === false) {
        throw new Exception('Failed to encode results as JSON');
    }
    
    echo $json;
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
    
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}

exit;
