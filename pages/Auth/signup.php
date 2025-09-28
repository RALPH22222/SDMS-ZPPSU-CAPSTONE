<?php
session_start();
require_once __DIR__ . '/../../database/database.php';
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $relationship = trim($_POST['relationship'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $student_last_name = trim($_POST['student_last_name'] ?? '');
    $student_birthdate = trim($_POST['student_birthdate'] ?? '');
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '') $errors[] = 'Last name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if ($relationship === '') $errors[] = 'Relationship is required.';
    if ($student_number === '') $errors[] = 'Student number is required.';
    if ($student_last_name === '') $errors[] = 'Student last name is required.';
    if ($student_birthdate === '') $errors[] = 'Student birthdate is required.';
    $student_row = null;
    if (empty($errors)) {
        try {
            $stu = $pdo->prepare('SELECT id, student_number, last_name, birthdate FROM students WHERE student_number = :sn AND last_name = :ln AND birthdate = :bd LIMIT 1');
            $stu->execute([
                ':sn' => $student_number,
                ':ln' => $student_last_name,
                ':bd' => $student_birthdate,
            ]);
            $student_row = $stu->fetch(PDO::FETCH_ASSOC);
            if (!$student_row) {
                $errors[] = 'Student details not found.';
            }
        } catch (Exception $e) {
            $errors[] = 'Unable to verify student at this time. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $errors[] = 'Email is already registered.';
            } else {
                $pdo->beginTransaction();
                $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
                $roleStmt->execute([':name' => 'Parent']);
                $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
                if (!$role) {
                    $insRole = $pdo->prepare('INSERT INTO roles (name, description, created_at) VALUES (\'Parent\', \'Parent user\', NOW())');
                    $insRole->execute();
                    $parent_role_id = (int)$pdo->lastInsertId();
                } else {
                    $parent_role_id = (int)$role['id'];
                }
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $pdo->prepare('INSERT INTO users (username, password_hash, email, contact_number, role_id, is_active, created_at) VALUES (:username, :hash, :email, :contact, :role_id, 1, NOW())');
                $insUser->execute([
                    ':username' => $email,
                    ':contact' => $contact_number !== '' ? $contact_number : null,
                    ':hash' => $password_hash,
                    ':role_id' => $parent_role_id,
                    ':email' => $email,
                ]);
                $user_id = (int)$pdo->lastInsertId();
                try {
                    $audit = $pdo->prepare('INSERT INTO audit_trail (table_name, record_id, action, performed_by_user_id, old_values, new_values, created_at) VALUES ("users", :rid, "CREATE", :uid, NULL, :newvals, NOW())');
                    $audit->execute([
                        ':rid' => (string)$user_id,
                        ':uid' => $user_id,
                        ':newvals' => json_encode(['username' => $email, 'email' => $email, 'role_id' => $parent_role_id]),
                    ]);
                } catch (Exception $e) { }
                $hasStatus = false;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM parent_student LIKE 'status'");
                    $hasStatus = (bool)$col->fetch();
                } catch (Exception $e) { $hasStatus = false; }

                if ($hasStatus) {
                    $ps = $pdo->prepare('INSERT INTO parent_student (parent_user_id, student_id, relationship, status, created_at) VALUES (:puid, :sid, :rel, :status, NOW())');
                    $ps->execute([
                        ':puid' => $user_id,
                        ':sid' => (int)$student_row['id'],
                        ':rel' => $relationship,
                        ':status' => 'pending',
                    ]);
                } else {
                    $ps = $pdo->prepare('INSERT INTO parent_student (parent_user_id, student_id, relationship, created_at) VALUES (:puid, :sid, :rel, NOW())');
                    $ps->execute([
                        ':puid' => $user_id,
                        ':sid' => (int)$student_row['id'],
                        ':rel' => $relationship,
                    ]);
                }
                try {
                    $audit2 = $pdo->prepare('INSERT INTO audit_trail (table_name, record_id, action, performed_by_user_id, old_values, new_values, created_at) VALUES ("parent_student", :rid, "LINK", :uid, NULL, :newvals, NOW())');
                    $audit2->execute([
                        ':rid' => (string)$user_id . '-' . (string)$student_row['id'],
                        ':uid' => $user_id,
                        ':newvals' => json_encode(['parent_user_id' => $user_id, 'student_id' => (int)$student_row['id'], 'relationship' => $relationship]),
                    ]);
                } catch (Exception $e) { }
                $upd = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
                $upd->execute([':id' => $user_id]);
                $sendEmails = false;
                if ($sendEmails) {
                    @mail($email, 'ZPPSU SDMS - Parent Registration', "Hello, your parent account has been created and is pending verification.");
                }

                $pdo->commit();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $email;
                $_SESSION['email'] = $email;
                $_SESSION['role_id'] = $parent_role_id;

                header('Location: /SDMS/pages/Parent/dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Parent Signup - ZPPSU SDMS</title>
    <link rel="stylesheet" href="/SDMS/css/output.css" />
    <link rel="stylesheet" href="/SDMS/css/brand-fallback.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-white text-dark min-h-screen">
    <div class="flex min-h-screen">
        <div class="w-full md:w-1/2 flex items-start justify-center p-6 pt-10 md:pt-12 md:h-screen md:overflow-y-auto">
            <div class="w-full max-w-xl bg-white rounded-xl shadow-lg border border-gray-100 p-8">
                <div class="mb-6">
                    <a href="/SDMS/index.php" class="text-primary"><i class="fa fa-arrow-left mr-2"></i>Back to Home</a>
                </div>
                <div class="mb-6 text-center">
                    <img src="/SDMS/src/images/Logo.png" alt="ZPPSU Logo" class="h-14 mx-auto mb-3" />
                    <h2 class="text-3xl font-extrabold text-primary">Create Parent Account</h2>
                    <p class="text-gray">Fill in the details below</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block font-semibold mb-1">First Name</label>
                        <input type="text" name="first_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Middle Name (optional)</label>
                        <input type="text" name="middle_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Middle Name" value="<?php echo htmlspecialchars($middle_name ?? ''); ?>" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Last Name</label>
                        <input type="text" name="last_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Surename" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Suffix (optional)</label>
                        <input type="text" name="suffix" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Jr., Sr., III" value="<?php echo htmlspecialchars($suffix ?? ''); ?>" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block font-semibold mb-1">Email</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Enter Your Email" value="<?php echo htmlspecialchars($email ?? ''); ?>" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Contact Number (optional)</label>
                        <input type="text" name="contact_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Contact Number" value="<?php echo htmlspecialchars($contact_number ?? ''); ?>" />
                    </div>
                    <div></div>
                    <div>
                        <label class="block font-semibold mb-1">Password</label>
                        <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Create a password" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Confirm Password</label>
                        <input type="password" name="confirm_password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Confirm your password" />
                    </div>
                    <div class="md:col-span-2 pt-2">
                        <h3 class="text-lg font-bold text-primary">Relationship to Student</h3>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Relationship</label>
                        <select name="relationship" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="" <?php echo ($relationship ?? '') === '' ? 'selected' : ''; ?>>Select</option>
                            <option value="Mother" <?php echo ($relationship ?? '') === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                            <option value="Father" <?php echo ($relationship ?? '') === 'Father' ? 'selected' : ''; ?>>Father</option>
                            <option value="Guardian" <?php echo ($relationship ?? '') === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                        </select>
                    </div>
                    <div></div>
                    <div class="md:col-span-2 pt-2">
                        <h3 class="text-lg font-bold text-primary">Verify Your Child</h3>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Student Number</label>
                        <input type="text" name="student_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" value="<?php echo htmlspecialchars($student_number ?? ''); ?>" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Student Last Name</label>
                        <input type="text" name="student_last_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" value="<?php echo htmlspecialchars($student_last_name ?? ''); ?>" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block font-semibold mb-1">Student Birthdate</label>
                        <input type="date" name="student_birthdate" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" value="<?php echo htmlspecialchars($student_birthdate ?? ''); ?>" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="w-full py-3 bg-primary text-white font-semibold rounded-lg shadow hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition">
                            Create Account
                        </button>
                    </div>
                    <div class="md:col-span-2 text-center">
                        <p class="text-sm">Already have an account? <a href="/SDMS/pages/Auth/login.php" class="text-primary font-semibold">Log in</a></p>
                    </div>
                </form>
            </div>
        </div>
        <div class="hidden md:block md:w-1/2 md:h-screen md:sticky md:top-0 relative flex-none overflow-hidden">
            <img src="/SDMS/src/images/4.jpg" alt="Signup Visual" class="w-full h-full object-cover" />
            <div class="absolute inset-0 bg-black/30"></div>
        </div>
    </div>
</body>
<script>

</script>
</html>