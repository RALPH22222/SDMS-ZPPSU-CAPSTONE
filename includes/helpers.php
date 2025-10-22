<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML special characters in a string
 * 
 * @param string $value The string to escape
 * @return string The escaped string
 */
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
}

/**
 * Set a flash message to be displayed on the next page load
 * 
 * @param string $type The type of alert (e.g., 'success', 'error', 'info')
 * @param string $message The message to display
 * @return void
 */
function set_alert($type, $message) {
    if (!isset($_SESSION['alerts'])) {
        $_SESSION['alerts'] = [];
    }
    $_SESSION['alerts'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display any pending alerts and clear them from the session
 * 
 * @return void
 */
function display_alerts() {
    if (empty($_SESSION['alerts'])) {
        return;
    }

    foreach ($_SESSION['alerts'] as $alert) {
        $type = e($alert['type']);
        $message = e($alert['message']);
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>";
        echo $message;
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
        echo "</div>";
    }
    
    // Clear the alerts after displaying them
    $_SESSION['alerts'] = [];
}

/**
 * Require user to be logged in and optionally check for specific role
 * 
 * @param string $required_role Optional role to check for (e.g., 'admin', 'staff')
 * @return void
 */
function require_login($required_role = null) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /SDMS/pages/Auth/login.php');
        exit();
    }

    // If a specific role is required, check if user has that role
    if ($required_role !== null) {
        $user_role = $_SESSION['role'] ?? '';
        if (strtolower($user_role) !== strtolower($required_role)) {
            // User doesn't have the required role
            header('HTTP/1.1 403 Forbidden');
            echo 'Access denied. You do not have permission to access this page.';
            exit();
        }
    }
}
