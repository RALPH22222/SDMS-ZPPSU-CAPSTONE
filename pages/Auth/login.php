<?php
session_start();
require_once __DIR__ . '/../../database/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role_id, is_active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                $upd = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
                $upd->execute([':id' => $user['id']]);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_id'] = (int)$user['role_id'];
                $role = (int)$user['role_id'];
                if ($role === 1) {
                    header('Location: /SDMS/pages/Admin/dashboard.php');
                    exit;
                } elseif ($role === 4) {
                    header('Location: /SDMS/pages/Parent/dashboard.php');
                    exit;
                } elseif ($role === 3) { 
                    header('Location: /SDMS/pages/Student/dashboard.php');
                    exit;
                } elseif ($role === 5) { 
                    header('Location: /SDMS/pages/Staff/dashboard.php');
                    exit;
                } elseif ($role === 6) {
                    header('Location: /SDMS/pages/Staff/dashboard.php');
                    exit;
                } else {
                    header('Location: /SDMS/index.php');
                    exit;
                }
            } else {
                $error = 'Invalid credentials or inactive account.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - ZPPSU SDMS</title>
    <link rel="stylesheet" href="/SDMS/css/output.css" />
    <link rel="stylesheet" href="/SDMS/css/brand-fallback.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
    <body class="bg-white text-dark min-h-screen">
        <div class="flex min-h-screen">
            <div class="hidden md:block md:w-1/2 md:h-screen md:sticky md:top-0 relative flex-none overflow-hidden">
                <img src="/SDMS/src/images/7.jpg" alt="Campus" class="w-full h-full object-cover" />
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <h1 class="text-white text-3xl font-bold">ZPPSU Student Disciplinary Management System</h1>
                </div>
            </div>
            <div class="w-full md:w-1/2 flex items-center justify-center p-6 pt-10 md:pt-12 md:h-screen md:overflow-y-auto">
                <div class="w-full max-w-md bg-white rounded-xl shadow-lg border border-gray-100 p-8">
                    <div class="mb-8 text-center">
                        <img src="/SDMS/src/images/Logo.png" alt="ZPPSU Logo" class="h-14 mx-auto mb-4" />
                        <h2 class="text-3xl font-extrabold text-primary">Welcome back</h2>
                        <p class="text-gray mt-1">Sign in to continue</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="email" class="block font-semibold mb-1">Email</label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Enter your email" />
                        </div>
                        <div>
                            <label for="password" class="block font-semibold mb-1">Password</label>
                            <input type="password" id="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Password" />
                        </div>
                        <button type="submit" class="w-full py-3 bg-primary text-white font-semibold rounded-lg shadow hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition">
                            Log In
                        </button>
                    </form>

                    <p class="mt-6 text-center text-sm">
                        Don't have an account? <a href="/SDMS/pages/Auth/signup.php" class="text-primary font-semibold">Create account</a>
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>