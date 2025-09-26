<?php
require_once __DIR__ . '/database/database.php';

header('Content-Type: text/plain');

try {
    // Enable error reporting
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if messages table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        die("The 'messages' table does not exist in the database.\n");
    }
    
    // Get table structure
    echo "=== Messages Table Structure ===\n";
    $stmt = $pdo->query("SHOW CREATE TABLE messages");
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $table['Create Table'] . "\n\n";
    
    // Check if users exist
    $usersExist = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
    if (!$usersExist) {
        die("No users found in the database. Messages require valid sender and recipient users.\n");
    }
    
    // Get first two users for testing
    $users = $pdo->query("SELECT id, username FROM users WHERE is_active = 1 LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) < 2) {
        die("Need at least 2 active users to test messaging. Found: " . count($users) . "\n");
    }
    
    $senderId = $users[0]['id'];
    $recipientId = $users[1]['id'];
    
    echo "\n=== Testing with users ===\n";
    echo "Sender: {$users[0]['username']} (ID: $senderId)\n";
    echo "Recipient: {$users[1]['username']} (ID: $recipientId)\n\n";
    
    // Try to insert a test message
    echo "=== Testing Message Insertion ===\n";
    echo "Attempting to insert a test message...\n";
    
    $testMessage = "Test message at " . date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO messages (sender_user_id, recipient_user_id, body, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    
    try {
        $stmt->execute([$senderId, $recipientId, $testMessage]);
        $lastId = $pdo->lastInsertId();
        echo "✅ Successfully inserted test message with ID: $lastId\n";
        
        // Verify the message was inserted
        $message = $pdo->query("SELECT * FROM messages WHERE id = $lastId")->fetch(PDO::FETCH_ASSOC);
        echo "\n=== Inserted Message ===\n";
        print_r($message);
        
        // Clean up
        $pdo->exec("DELETE FROM messages WHERE id = $lastId");
        echo "\n✅ Test message deleted.\n";
        
    } catch (PDOException $e) {
        echo "❌ Failed to insert test message. Error: " . $e->getMessage() . "\n";
        
        // Check for foreign key constraints
        if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
            echo "\n⚠️  Foreign key constraint failed. Check if the user IDs exist in the users table.\n";
        }
    }
    
    // Check recent messages for debugging
    echo "\n=== Recent Messages (if any) ===\n";
    $recentMessages = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if (count($recentMessages) > 0) {
        foreach ($recentMessages as $msg) {
            echo "[{$msg['created_at']}] From:{$msg['sender_user_id']} To:{$msg['recipient_user_id']}: " . 
                 substr($msg['body'], 0, 50) . (strlen($msg['body']) > 50 ? '...' : '') . "\n";
        }
    } else {
        echo "No recent messages found in the database.\n";
    }
    
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage() . "\n");
}
?>
