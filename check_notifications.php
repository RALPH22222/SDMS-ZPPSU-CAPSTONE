<?php
session_start();
require_once __DIR__ . '/database/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
//     die('Access denied. Admin only.');
// }

// Get total notifications count
$stmt = $pdo->query('SELECT COUNT(*) as total FROM notifications');
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get latest 10 notifications with details
$stmt = $pdo->query('SELECT n.*, u.username, c.case_number, c.title as case_title 
                    FROM notifications n 
                    LEFT JOIN users u ON n.user_id = u.id 
                    LEFT JOIN cases c ON n.case_id = c.id 
                    ORDER BY n.created_at DESC LIMIT 10');
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification methods
$stmt = $pdo->query('SELECT * FROM notification_method');
$methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notification Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow">
        <h1 class="text-2xl font-bold mb-6">Notification Debug</h1>
        
        <div class="mb-6 p-4 bg-blue-50 rounded">
            <h2 class="text-lg font-semibold mb-2">Database Info</h2>
            <p>Total notifications in database: <span class="font-bold"><?php echo $total; ?></span></p>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-2">Latest Notifications</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-2 px-4 border">ID</th>
                            <th class="py-2 px-4 border">User</th>
                            <th class="py-2 px-4 border">Message</th>
                            <th class="py-2 px-4 border">Case #</th>
                            <th class="py-2 px-4 border">Created At</th>
                            <th class="py-2 px-4 border">Read</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notif): ?>
                        <tr>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($notif['id']); ?></td>
                            <td class="py-2 px-4 border">
                                <?php 
                                echo $notif['username'] ? 
                                    htmlspecialchars($notif['username']) : 
                                    'User #' . $notif['user_id']; 
                                ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($notif['message']); ?></td>
                            <td class="py-2 px-4 border">
                                <?php if ($notif['case_number']): ?>
                                    <a href="manage-cases.php?view=<?php echo $notif['case_id']; ?>" 
                                       class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($notif['case_number']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($notif['created_at']); ?></td>
                            <td class="py-2 px-4 border"><?php echo $notif['is_read'] ? 'Yes' : 'No'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-2">Notification Methods</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($methods as $method): ?>
                <div class="p-4 border rounded">
                    <h3 class="font-semibold"><?php echo htmlspecialchars($method['name']); ?></h3>
                    <p class="text-sm text-gray-600">ID: <?php echo $method['id']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="p-4 bg-gray-100 rounded">
            <h2 class="text-lg font-semibold mb-2">Debug Info</h2>
            <pre class="bg-gray-200 p-4 rounded overflow-auto text-sm"><?php 
                echo "PHP Version: " . PHP_VERSION . "\n";
                echo "Database: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " " . 
                     $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
                echo "Last Error: " . json_encode($pdo->errorInfo(), JSON_PRETTY_PRINT) . "\n\n";
                echo "Session: " . print_r($_SESSION, true) . "\n";
            ?></pre>
        </div>
    </div>
</body>
</html>
