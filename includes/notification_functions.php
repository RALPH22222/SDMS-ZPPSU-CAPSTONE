<?php
require_once __DIR__ . '/../database/database.php';

/**
 * Send a notification to one or more users
 * 
 * @param array|int $userIds Single user ID or array of user IDs
 * @param string $message Notification message
 * @param int $methodId Notification method ID (default: 1 for system notification)
 * @param int|null $caseId Optional case ID to link the notification to
 * @return bool True on success, false on failure
 */
function sendNotification($userIds, string $message, int $methodId = 1, ?int $caseId = null): bool {
    global $pdo;
    
    error_log("Sending notification: $message");
    error_log("Method ID: $methodId, Case ID: " . ($caseId ?? 'NULL'));
    
    if (!is_array($userIds)) {
        $userIds = [$userIds];
    }
    
    if (empty($userIds)) {
        error_log("No user IDs provided for notification");
        return false;
    }
    
    error_log("Sending to user IDs: " . implode(', ', $userIds));
    
    $success = true;
    
    try {
        // Check database connection
        if (!$pdo) {
            error_log("Database connection is not available");
            return false;
        }
        
        $isInTransaction = $pdo->inTransaction();
        
        try {
            if (!$isInTransaction) {
                $pdo->beginTransaction();
            }
            
            // Prepare the SQL statement
            $sql = 'INSERT INTO notifications (user_id, case_id, method_id, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())';
            error_log("Preparing SQL: $sql");
            
            $stmt = $pdo->prepare($sql);
            $success = true;
            
            foreach ($userIds as $userId) {
                if (!empty($userId)) {
                    $params = [$userId, $caseId, $methodId, $message];
                    error_log("Executing with params: " . json_encode($params));
                    
                    $result = $stmt->execute($params);
                    
                    if (!$result) {
                        $error = $stmt->errorInfo();
                        $errorMsg = $error[2] ?? 'Unknown error';
                        error_log("Failed to insert notification for user $userId: $errorMsg");
                        $success = false;
                        if (!$isInTransaction) {
                            // Only break if we're not in a transaction, let the parent handle the rollback
                            break;
                        }
                    } else {
                        $lastId = $pdo->lastInsertId();
                        error_log("Successfully inserted notification with ID: $lastId for user $userId");
                    }
                }
            }
            
            if ($success) {
                if (!$isInTransaction) {
                    $pdo->commit();
                }
                error_log("Successfully processed all notifications" . ($isInTransaction ? ' (within existing transaction)' : ''));
                return true;
            } else {
                if (!$isInTransaction) {
                    $pdo->rollBack();
                    error_log("Failed to send some notifications, transaction rolled back");
                } else {
                    error_log("Failed to send some notifications, but continuing with parent transaction");
                }
                return false;
            }
            
        } catch (PDOException $e) {
            if (!$isInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e; // Re-throw to be caught by outer try-catch
        }
        
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Send notification for appeal submission/update
 * 
 * @param int $appealId The ID of the appeal
 * @param string $action The action performed (e.g., 'submitted', 'updated')
 * @param int $submittedById The ID of the user who performed the action
 * @return bool True on success, false on failure
 */
function notifyAppealAction(int $appealId, string $action, int $submittedById): bool {
    global $pdo;
    
    try {
        // Get appeal details
        $stmt = $pdo->prepare('SELECT a.*, c.case_number, u.username as submitted_by 
                              FROM appeals a 
                              JOIN cases c ON a.case_id = c.id 
                              JOIN users u ON a.submitted_by = u.id 
                              WHERE a.id = ?');
        $stmt->execute([$appealId]);
        $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appeal) {
            error_log("Appeal not found: $appealId");
            return false;
        }
        
        // Get admin users who should be notified
        $adminIds = getAdminRecipients();
        if (empty($adminIds)) {
            error_log("No admin users found to notify about appeal");
            return false;
        }
        
        // Create notification message
        $actionText = ucfirst($action);
        $message = "Appeal $actionText: Case #{$appeal['case_number']} by {$appeal['submitted_by']}";
        
        // Send notifications to all admins
        $success = true;
        foreach ($adminIds as $adminId) {
            // Skip notifying the user who performed the action
            if ($adminId == $submittedById) {
                continue;
            }
            
            $result = sendNotification(
                $adminId,
                $message,
                1, // System notification
                $appeal['case_id']
            );
            
            if (!$result) {
                $success = false;
                error_log("Failed to send notification to admin ID: $adminId");
            }
        }
        
        return $success;
        
    } catch (PDOException $e) {
        error_log("Error in notifyAppealAction: " . $e->getMessage());
        return false;
    }
}

/**
 * Get admin users who should receive notifications
 * 
 * @return array Array of admin user IDs
 */
function getAdminRecipients(): array {
    global $pdo;
    
    $adminIds = [];
    
    try {
        $stmt = $pdo->query("
            SELECT id FROM users 
            WHERE role_id IN (SELECT id FROM roles WHERE name = 'admin' OR name = 'administrator')
            AND is_active = 1
        
        ");
        
        $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting admin recipients: " . $e->getMessage());
    }
    
    return $adminIds;
}

/**
 * Notify parents of a student about a new disciplinary report
 * 
 * @param int $studentId The ID of the student
 * @param int $caseId The ID of the case
 * @param string $caseNumber The case number
 * @param string $violationType The type of violation
 * @return bool True if notifications were sent successfully, false otherwise
 */
function notifyParentsOfReportedStudent(int $studentId, int $caseId, string $caseNumber, string $violationType): bool {
    global $pdo;
    
    error_log("Preparing to notify parents for student ID: $studentId, case #$caseNumber");
    
    try {
        // Get parent/guardian IDs associated with the student
        $stmt = $pdo->prepare("\n            SELECT DISTINCT u.id \n            FROM users u\n            JOIN student_guardians sg ON sg.guardian_id = u.id\n            WHERE sg.student_id = ? AND u.is_active = 1\n        
        ");
        $stmt->execute([$studentId]);
        $parentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($parentIds)) {
            error_log("No active parents/guardians found for student ID: $studentId");
            return false;
        }
        
        error_log("Found " . count($parentIds) . " parents/guardians to notify");
        
        // Prepare notification message
        $message = "Your child has been reported for $violationType (Case #$caseNumber). Please log in to the system for more details.";
        
        // Send notification to each parent/guardian
        $success = true;
        foreach ($parentIds as $parentId) {
            $result = sendNotification(
                $parentId,
                $message,
                1,  // System notification
                $caseId
            );
            
            if (!$result) {
                error_log("Failed to send notification to parent ID: $parentId");
                $success = false;
            }
        }
        
        return $success;
        
    } catch (PDOException $e) {
        error_log("Error in notifyParentsOfReportedStudent: " . $e->getMessage());
        return false;
    }
}
