<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
try {
  require_once '../../database/database.php';
  if ($userId) {
    $stmt = $pdo->prepare("INSERT INTO audit_trail (table_name, record_id, action, performed_by_user_id, old_values, new_values) VALUES (:table_name, :record_id, :action, :uid, :old_values, :new_values)");
    $stmt->execute([
      ':table_name' => 'auth',
      ':record_id' => (string)$userId,
      ':action' => 'LOGOUT',
      ':uid' => $userId,
      ':old_values' => null,
      ':new_values' => null,
    ]);
  }
} catch (Throwable $e) {
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: /SDMS/index.php');
exit;
?>