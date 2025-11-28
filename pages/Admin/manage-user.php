<?php
  session_start();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf = $_SESSION['csrf_token'];

  require_once __DIR__ . '/../../database/database.php';
  try {
    $countStmt = $pdo->query('SELECT COUNT(*) FROM roles');
    $roleCount = (int)$countStmt->fetchColumn();
    if ($roleCount === 0) {
      $seed = [
        ['Admin', 'Full access to admin panel'],
        ['Student', 'Student portal access'],
      ];
      $ins = $pdo->prepare('INSERT INTO roles (id, name, description, created_at) VALUES (NULL, ?, ?, CURRENT_TIMESTAMP())');
      foreach ($seed as $r) { $ins->execute([$r[0], $r[1]]); }
    }
  } catch (Exception $e) {
  }
  
  $flash = ['type' => null, 'msg' => null];
  if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
  }

  function flash($type, $msg) {
    return ['type' => $type, 'msg' => $msg];
  }

  function ensure_csrf() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
      throw new Exception('Invalid CSRF token.');
    }
  }

  try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ensure_csrf();
      $action = $_POST['action'] ?? '';

      if ($action === 'create_user') {
        header('Content-Type: application/json');
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->beginTransaction();
            
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '') ?: null;
            $contact = trim($_POST['contact_number'] ?? '') ?: null;
            $role_id = (int)($_POST['role_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $plain = trim($_POST['password'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            
            error_log("Creating user with data: " . print_r($_POST, true));
          
            // Basic required checks for create
            if ($role_id <= 0 || $plain === '' || $first_name === '' || $last_name === '') {
                throw new Exception('Name, Role and Password are required.');
            }

            // If username not provided in the create form, generate one from name or email
            if ($username === '') {
                $base = '';
                if ($first_name !== '' || $last_name !== '') {
                    $base = strtolower(preg_replace('/[^a-z0-9]+/', '', $first_name . '.' . $last_name));
                }
                if ($base === '' && $email) {
                    $parts = explode('@', $email);
                    $base = strtolower(preg_replace('/[^a-z0-9]+/', '', $parts[0]));
                }
                if ($base === '') {
                    $base = 'user';
                }

                // Ensure uniqueness by appending numbers if necessary
                $candidate = $base;
                $i = 0;
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                while (true) {
                    $checkStmt->execute([$candidate]);
                    if (!$checkStmt->fetch()) break;
                    $i++;
                    $candidate = $base . $i;
                    if ($i > 1000) { // safety fallback
                        throw new Exception('Unable to generate unique username, please provide one.');
                    }
                }
                $username = $candidate;
                error_log("Auto-generated username: $username");
            }
            
            // Now validate username uniqueness
            $userCheck = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $userCheck->execute([$username]);
            if ($userCheck->fetch()) {
                throw new Exception('Username already exists. Please choose a different username.');
            }
            
            // For parent role, validate student relationship
            if ($role_id == 4) { // Parent role ID is 4
                $student_id = (int)($_POST['student_id'] ?? 0);
                $relationship = trim($_POST['relationship'] ?? '');
                
                if ($student_id <= 0 || empty($relationship)) {
                    throw new Exception('Please select a student and specify your relationship.');
                }
                
                // Verify student exists and get their details
                $studentCheck = $pdo->prepare('SELECT id, first_name, last_name FROM students WHERE id = ?');
                $studentCheck->execute([$student_id]);
                $student = $studentCheck->fetch(PDO::FETCH_ASSOC);
                
                if (!$student) {
                    throw new Exception('Selected student not found. Please try again.');
                }
                
                // Set parent's last name to match student's last name if not provided
                if (empty($last_name)) {
                    $last_name = $student['last_name'];
                }
            }
            
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            
            // Insert the user
            $sql = 'INSERT INTO users (username, password_hash, email, contact_number, role_id, is_active, last_login, created_at) ' . 
                   'VALUES (?, ?, ?, ?, ?, ?, NULL, CURRENT_TIMESTAMP())';
            
            $params = [
                $username, 
                $hash, 
                $email, 
                $contact, 
                $role_id, 
                $is_active
            ];
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new Exception('Failed to create user. Database error: ' . implode(' ', $stmt->errorInfo()));
            }
            
            $user_id = $pdo->lastInsertId();
            
            // Handle role-specific data
            if ($role_id == 3) { // Student role ID is 3
                try {
                    // First, ensure the user was created successfully
                    if (!$user_id) {
                        throw new Exception('Failed to create user account. Cannot create student record.');
                    }
                    
                    // Validate required student fields
                    $requiredFields = [
                        'birthdate' => 'Birthdate',
                        'address' => 'Address',
                        'course_id' => 'Course',
                        'sex' => 'Sex'
                    ];
                    
                    $missingFields = [];
                    foreach ($requiredFields as $field => $name) {
                        if (empty($_POST[$field])) {
                            $missingFields[] = $name;
                        }
                    }
                    
                    if (!empty($missingFields)) {
                        throw new Exception('The following student fields are required: ' . implode(', ', $missingFields));
                    }
                    
                    // Generate a student number (format: YY-XXXX)
                    $current_year = date('y');
                    $random_number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $student_number = $current_year . '-' . $random_number;
                    
                    // Format and validate birthdate
                    $birthdate = date('Y-m-d', strtotime($_POST['birthdate']));
                    if ($birthdate === '1970-01-01' || $birthdate === false) {
                        throw new Exception('Invalid birthdate format. Please use YYYY-MM-DD.');
                    }
                    
                    $address = trim($_POST['address']);
                    $course_id = (int)$_POST['course_id'];
                    $sex = trim($_POST['sex']);
                    
                    // Log the data we're about to insert
                    error_log("Creating student with data: " . print_r([
                        'user_id' => $user_id,
                        'student_number' => $student_number,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'course_id' => $course_id,
                        'birthdate' => $birthdate,
                        'sex' => $sex,
                        'address' => $address
                    ], true));
                    
                    // Insert into students table
                    $studentStmt = $pdo->prepare('INSERT INTO students (
                        user_id, student_number, first_name, middle_name, last_name, 
                        suffix, birthdate, address, course_id, sex, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    
                    $result = $studentStmt->execute([
                        $user_id, 
                        $student_number, 
                        $first_name,
                        !empty($middle_name) ? $middle_name : null,
                        $last_name,
                        !empty($suffix) ? $suffix : null,
                        $birthdate,
                        $address,
                        $course_id > 0 ? $course_id : null,
                        $sex
                    ]);
                    
                    if (!$result) {
                        $errorInfo = $studentStmt->errorInfo();
                        throw new Exception('Database error when creating student: ' . ($errorInfo[2] ?? 'Unknown error'));
                    }
                    
                    // Update the user's username to match the student number
                    $updateUser = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $updateResult = $updateUser->execute([$student_number, $user_id]);
                    
                    if (!$updateResult) {
                        throw new Exception('Failed to update username with student number.');
                    }
                    
                    error_log("Successfully created student record for user_id: $user_id, student_number: $student_number");
                    
                } catch (Exception $e) {
                    // Log the error
                    error_log("Error creating student: " . $e->getMessage());
                    
                    // Re-throw the exception to be caught by the outer try-catch
                    throw $e;
                }
            } elseif ($role_id === 2 || $role_id === 5 || $role_id === 6) {
                // Handle Staff (2), Marshal (5), or Teacher (6) roles
                $staff_number = trim($_POST['staff_number'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                if (empty($staff_number) || empty($department) || empty($position)) {
                    throw new Exception('Staff number, department, and position are required for staff/teacher/marshal accounts.');
                }
                
                // Update user with staff/teacher/marshal information
                // Combine first and last name into username if both are provided
                $username = trim("$first_name $last_name");
                $updateUser = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                $updateUser->execute([$username, $user_id]);
                
                // Handle Marshal-specific insertion into marshal table
                if ($role_id === 5) {
                    // Look up department_id from department_name
                    $deptStmt = $pdo->prepare('SELECT id FROM departments WHERE department_name = ?');
                    $deptStmt->execute([$department]);
                    $deptResult = $deptStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$deptResult) {
                        throw new Exception('Department not found. Please select a valid department.');
                    }
                    
                    $department_id = (int)$deptResult['id'];
                    
                    // Check if department already has a marshal (unique constraint)
                    $existingMarshalStmt = $pdo->prepare('SELECT id FROM marshal WHERE department_id = ?');
                    $existingMarshalStmt->execute([$department_id]);
                    if ($existingMarshalStmt->fetch()) {
                        throw new Exception('This department already has a marshal assigned. Please select a different department.');
                    }
                    
                    // Insert into marshal table
                    $marshalStmt = $pdo->prepare('INSERT INTO marshal (
                        user_id, staff_number, first_name, middle_name, last_name, 
                        suffix, position, department_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())');
                    
                    $marshalResult = $marshalStmt->execute([
                        $user_id,
                        $staff_number,
                        $first_name,
                        !empty($middle_name) ? $middle_name : null,
                        $last_name,
                        !empty($suffix) ? $suffix : null,
                        $position,
                        $department_id
                    ]);
                    
                    if (!$marshalResult) {
                        $errorInfo = $marshalStmt->errorInfo();
                        throw new Exception('Database error when creating marshal: ' . ($errorInfo[2] ?? 'Unknown error'));
                    }
                    
                    error_log("Created marshal record for user_id: $user_id, staff_number: $staff_number, department_id: $department_id");
                }
                
                error_log("Updated staff/teacher/marshal user with user_id: $user_id, name: $first_name $last_name, role_id: $role_id");
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
            error_log("User created successfully with ID: $user_id");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            throw new Exception('Database error: ' . $e->getMessage());
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error creating user: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'update_user') {
        header('Content-Type: application/json');
        $pdo->beginTransaction();
        try {
            $id = (int)($_POST['id'] ?? 0);
            // username removed from form; do not require or update it here
            $email = trim($_POST['email'] ?? '') ?: null;
            $contact = trim($_POST['contact_number'] ?? '') ?: null;
            $role_id = (int)($_POST['role_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // get posted names for role-change handling
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');

            if ($id <= 0 || $role_id <= 0) {
                throw new Exception('User ID and Role are required.');
            }
            
            // Get current user data
            $userStmt = $pdo->prepare('SELECT role_id FROM users WHERE id = ?');
            $userStmt->execute([$id]);
            $currentData = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentData) {
                throw new Exception('User not found.');
            }
            
            $current_role_id = (int)$currentData['role_id'];
            $is_student_now = ($role_id == 3); // Student role ID is 3
            $was_student = ($current_role_id == 3);
            $is_teacher_now = ($role_id == 2); // Staff/Teacher role ID is 2
            $was_teacher = ($current_role_id == 2);
            $is_marshal_now = ($role_id == 5); // Marshal role ID is 5
            $was_marshal = ($current_role_id == 5);
            
            // Handle role changes using posted first_name/last_name instead of username parsing
            if ($is_teacher_now && !$was_teacher) {
                // Role changed to teacher - update username
                $username = trim("$first_name $last_name");
                if (empty($username)) {
                    $username = 'Teacher' . $id;  // Fallback to Teacher + ID if no name provided
                }
                $updateUser = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                $updateUser->execute([$username, $id]);
                error_log("Updated teacher user with user_id: $id, username: $username");
            } 
            // Handle role change to student
            elseif ($is_student_now && !$was_student) {
                // Role changed to student - create student record
                $s_first = $first_name !== '' ? $first_name : 'Student';
                $s_last = $last_name !== '' ? $last_name : 'Name';
                $birthdate = !empty($_POST['birthdate']) ? date('Y-m-d', strtotime($_POST['birthdate'])) : null;
                $address = trim($_POST['address'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                $sex = trim($_POST['sex'] ?? '');
                
                $current_year = date('y');
                $random_number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $student_number = $current_year . '-' . $random_number;
                
                $studentStmt = $pdo->prepare('INSERT INTO students (user_id, student_number, first_name, middle_name, last_name, suffix, birthdate, address, course_id, sex, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())');
                $studentStmt->execute([
                    $id, 
                    $student_number, 
                    $s_first,
                    $middle_name ?: null,
                    $s_last,
                    $suffix ?: null,
                    $birthdate,
                    $address,
                    $course_id,
                    $sex
                ]);
                error_log("Created student record for user_id: $id, student_number: $student_number, name: $s_first $s_last, course_id: $course_id");
            }
            // Handle role change from teacher to non-teacher
            elseif (!$is_teacher_now && $was_teacher) {
                // Role changed from teacher - no need to clean up as we're not using a separate staff table
                error_log("User with id $id is no longer a teacher");
            }
            // Handle role change from student to non-student
            elseif (!$is_student_now && $was_student) {
                // Role changed from student - clean up student record
                $pdo->prepare('DELETE FROM students WHERE user_id = ?')->execute([$id]);
                error_log("Removed student record for user_id: $id");
            }
            // Handle role change to marshal
            elseif ($is_marshal_now && !$was_marshal) {
                // Role changed to marshal - create marshal record
                $staff_number = trim($_POST['staff_number'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                if (empty($staff_number) || empty($department) || empty($position)) {
                    throw new Exception('Staff number, department, and position are required for marshal accounts.');
                }
                
                // Look up department_id from department_name
                $deptStmt = $pdo->prepare('SELECT id FROM departments WHERE department_name = ?');
                $deptStmt->execute([$department]);
                $deptResult = $deptStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$deptResult) {
                    throw new Exception('Department not found. Please select a valid department.');
                }
                
                $department_id = (int)$deptResult['id'];
                
                // Check if department already has a marshal (unique constraint)
                $existingMarshalStmt = $pdo->prepare('SELECT id FROM marshal WHERE department_id = ?');
                $existingMarshalStmt->execute([$department_id]);
                if ($existingMarshalStmt->fetch()) {
                    throw new Exception('This department already has a marshal assigned. Please select a different department.');
                }
                
                // Insert into marshal table
                $marshalStmt = $pdo->prepare('INSERT INTO marshal (
                    user_id, staff_number, first_name, middle_name, last_name, 
                    suffix, position, department_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())');
                
                $marshalStmt->execute([
                    $id,
                    $staff_number,
                    $first_name ?: 'Marshal',
                    $middle_name ?: null,
                    $last_name ?: 'Name',
                    $suffix ?: null,
                    $position,
                    $department_id
                ]);
                
                error_log("Created marshal record for user_id: $id, staff_number: $staff_number, department_id: $department_id");
            }
            // Handle role change from marshal to non-marshal
            elseif (!$is_marshal_now && $was_marshal) {
                // Role changed from marshal - clean up marshal record
                $pdo->prepare('DELETE FROM marshal WHERE user_id = ?')->execute([$id]);
                error_log("Removed marshal record for user_id: $id");
            }
            
            // Update user with new data (username is not updated from the modal)
            $stmt = $pdo->prepare('UPDATE users SET email = ?, contact_number = ?, role_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
            $stmt->execute([$email, $contact, $role_id, $is_active, $id]);
            
            // If the user is a student, update their student record
            if ($is_student_now) {
                $birthdate = !empty($_POST['birthdate']) ? date('Y-m-d', strtotime($_POST['birthdate'])) : null;
                $address = trim($_POST['address'] ?? '');
                $course_id = (int)($_POST['course_id'] ?? 0);
                $sex = trim($_POST['sex'] ?? '');
                
                // Check if student record exists
                $studentCheck = $pdo->prepare('SELECT id FROM students WHERE user_id = ?');
                $studentCheck->execute([$id]);
                
                if ($studentCheck->fetch()) {
                    // Update existing student record
                    $studentStmt = $pdo->prepare('UPDATE students SET 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        suffix = ?, 
                        birthdate = ?, 
                        address = ?,
                        course_id = ?,
                        sex = ?,
                        updated_at = CURRENT_TIMESTAMP() 
                        WHERE user_id = ?');
                    $studentStmt->execute([
                        $first_name,
                        $middle_name ?: null,
                        $last_name,
                        $suffix ?: null,
                        $birthdate,
                        $address,
                        $course_id > 0 ? $course_id : null,
                        $sex,
                        $id
                    ]);
                    error_log("Updated student record for user_id: $id, course_id: $course_id");
                } else {
                    // Create new student record if it doesn't exist
                    $current_year = date('y');
                    $random_number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $student_number = $current_year . '-' . $random_number;
                    
                    $studentStmt = $pdo->prepare('INSERT INTO students 
                        (user_id, student_number, first_name, middle_name, last_name, suffix, birthdate, address, course_id, sex, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())');
                    $studentStmt->execute([
                        $id, 
                        $student_number, 
                        $first_name,
                        $middle_name ?: null,
                        $last_name,
                        $suffix ?: null,
                        $birthdate,
                        $address,
                        $course_id > 0 ? $course_id : null,
                        $sex
                    ]);
                    error_log("Created new student record for user_id: $id, course_id: $course_id");
                }
            }
            
            // If the user is a marshal, update their marshal record
            if ($is_marshal_now) {
                $staff_number = trim($_POST['staff_number'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                // Look up department_id from department_name
                $department_id = null;
                if (!empty($department)) {
                    $deptStmt = $pdo->prepare('SELECT id FROM departments WHERE department_name = ?');
                    $deptStmt->execute([$department]);
                    $deptResult = $deptStmt->fetch(PDO::FETCH_ASSOC);
                    if ($deptResult) {
                        $department_id = (int)$deptResult['id'];
                    }
                }
                
                // Check if marshal record exists
                $marshalCheck = $pdo->prepare('SELECT id, department_id FROM marshal WHERE user_id = ?');
                $marshalCheck->execute([$id]);
                $existingMarshal = $marshalCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($existingMarshal) {
                    // Update existing marshal record
                    // If department changed, check if new department already has a marshal
                    $old_dept_id = (int)$existingMarshal['department_id'];
                    if ($department_id && $department_id !== $old_dept_id) {
                        $existingMarshalStmt = $pdo->prepare('SELECT id FROM marshal WHERE department_id = ? AND user_id != ?');
                        $existingMarshalStmt->execute([$department_id, $id]);
                        if ($existingMarshalStmt->fetch()) {
                            throw new Exception('This department already has a marshal assigned. Please select a different department.');
                        }
                    }
                    
                    $marshalStmt = $pdo->prepare('UPDATE marshal SET 
                        staff_number = ?, 
                        first_name = ?, 
                        middle_name = ?, 
                        last_name = ?, 
                        suffix = ?, 
                        position = ?,
                        department_id = ?,
                        updated_at = CURRENT_TIMESTAMP() 
                        WHERE user_id = ?');
                    $marshalStmt->execute([
                        $staff_number,
                        $first_name,
                        $middle_name ?: null,
                        $last_name,
                        $suffix ?: null,
                        $position,
                        $department_id,
                        $id
                    ]);
                    error_log("Updated marshal record for user_id: $id, department_id: $department_id");
                } else {
                    // Create new marshal record if it doesn't exist
                    if (empty($staff_number) || empty($department) || empty($position)) {
                        throw new Exception('Staff number, department, and position are required for marshal accounts.');
                    }
                    
                    if (!$department_id) {
                        throw new Exception('Department not found. Please select a valid department.');
                    }
                    
                    // Check if department already has a marshal
                    $existingMarshalStmt = $pdo->prepare('SELECT id FROM marshal WHERE department_id = ?');
                    $existingMarshalStmt->execute([$department_id]);
                    if ($existingMarshalStmt->fetch()) {
                        throw new Exception('This department already has a marshal assigned. Please select a different department.');
                    }
                    
                    $marshalStmt = $pdo->prepare('INSERT INTO marshal 
                        (user_id, staff_number, first_name, middle_name, last_name, suffix, position, department_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP())');
                    $marshalStmt->execute([
                        $id,
                        $staff_number,
                        $first_name ?: 'Marshal',
                        $middle_name ?: null,
                        $last_name ?: 'Name',
                        $suffix ?: null,
                        $position,
                        $department_id
                    ]);
                    error_log("Created new marshal record for user_id: $id, department_id: $department_id");
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'toggle_user_status') {
        $id = (int)($_POST['id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0) ? 1 : 0;
        if ($id <= 0) throw new Exception('Invalid user.');
        $stmt = $pdo->prepare('UPDATE users SET is_active = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$is_active, $id]);
        $_SESSION['flash'] = flash('success', 'User status updated.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $new_plain = $_POST['new_password'] ?? '';
        if ($id <= 0 || $new_plain === '') throw new Exception('Invalid password reset request.');
        $hash = password_hash($new_plain, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?');
        $stmt->execute([$hash, $id]);
        $_SESSION['flash'] = flash('success', 'Password reset successfully.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'create_role') {
        $name = trim($_POST['role_name'] ?? '');
        $desc = trim($_POST['role_desc'] ?? '') ?: null;
        if ($name === '') throw new Exception('Role name is required.');
        $stmt = $pdo->prepare('INSERT INTO roles (id, name, description, created_at) VALUES (NULL, ?, ?, CURRENT_TIMESTAMP())');
        $stmt->execute([$name, $desc]);
        $_SESSION['flash'] = flash('success', 'Role created.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'update_role') {
        $id = (int)($_POST['role_id'] ?? 0);
        $name = trim($_POST['role_name'] ?? '');
        $desc = trim($_POST['role_desc'] ?? '') ?: null;
        if ($id <= 0 || $name === '') throw new Exception('Role and name are required.');
        $stmt = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $desc, $id]);
        $_SESSION['flash'] = flash('success', 'Role updated.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }

      if ($action === 'delete_role') {
        $id = (int)($_POST['role_id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid role.');
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) {
          throw new Exception('Cannot delete role: it is assigned to existing users.');
        }
        $stmt = $pdo->prepare('DELETE FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = flash('success', 'Role deleted.');
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
      }
    }
  } catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $_SESSION['flash'] = flash('error', $e->getMessage());
      header('Location: ' . basename($_SERVER['PHP_SELF']));
      exit;
    } else {
      $flash = flash('error', $e->getMessage());
    }
  }
  $q = trim($_GET['q'] ?? '');
  $filter_role = (int)($_GET['role'] ?? 0);
  $filter_status = ($_GET['status'] ?? '') !== '' ? (int)$_GET['status'] : null;
  $roles = $pdo->query('SELECT id, name, description FROM roles ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
  $where = [];
  $params = [];
  if ($q !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
  if ($filter_role > 0) { $where[] = 'u.role_id = ?'; $params[] = $filter_role; }
  if ($filter_status !== null) { $where[] = 'u.is_active = ?'; $params[] = $filter_status; }
  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  // previous simple users query replaced with left joins to include role-specific fields
  $usersStmt = $pdo->prepare("
    SELECT 
      u.id,
      u.username,
      u.email,
      u.contact_number,
      u.role_id,
      u.is_active,
      u.last_login,
      r.name AS role_name,
      -- Get name from students table if available, then marshal table, otherwise from username
      COALESCE(s.first_name, m.first_name, '') AS first_name,
      COALESCE(s.middle_name, m.middle_name, '') AS middle_name,
      COALESCE(s.last_name, m.last_name, u.username) AS last_name,
      COALESCE(s.suffix, m.suffix, '') AS suffix,
      -- student fields (if any)
      s.student_number AS student_number,
      s.course_id AS course_id,
      s.birthdate AS birthdate,
      s.address AS address,
      s.sex AS sex,
      -- staff/teacher/marshal fields (if any)
      COALESCE(m.staff_number, '') AS staff_number,
      COALESCE(d.department_name, '') AS department,
      COALESCE(m.position, '') AS position,
      -- Marshal fields
      m.department_id AS marshal_department_id,
      -- Parent relation removed as parent_student table doesn't exist
      NULL AS student_id,
      NULL AS relationship
    FROM users u
    JOIN roles r ON r.id = u.role_id
    LEFT JOIN students s ON s.user_id = u.id
    LEFT JOIN marshal m ON m.user_id = u.id
    LEFT JOIN departments d ON d.id = m.department_id
    -- Removed parent_student join as it doesn't exist
    $whereSql
    ORDER BY u.created_at DESC
  ");
  $usersStmt->execute($params);
  $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php $pageTitle = 'Manage Users - SDMS'; include_once __DIR__ . '/../../components/admin-head.php'; ?>

<div class="md:ml-64">
  <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="h-16 flex items-center px-4">
      <button id="adminSidebarToggle" class="text-primary text-2xl mr-3">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="text-primary font-bold">Manage Users</div>
    </div>
  </div>

  <?php include_once __DIR__ . '/../../components/admin-sidebar.php'; ?>

  <main class="px-4 md:px-8 py-6">
    <div class="max-w-7xl mx-auto">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-dark">Manage Users</h1>
        <div class="flex flex-wrap items-center justify-end gap-2 sm:flex-nowrap">
          <button id="btnOpenCreateUser" class="w-full sm:w-auto px-4 py-2 bg-primary text-white rounded hover:opacity-90 transition-colors"><i class="fa fa-plus mr-2"></i>Create User</button>
        </div>
      </div>

      <?php if ($flash['type']): ?>
        <div class="hidden" id="flashData"
             data-type="<?php echo $flash['type']==='success'?'success':'error'; ?>"
             data-msg="<?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>"></div>
      <?php endif; ?>

      <form method="get" class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search username, email, contact" class="border rounded px-3 py-2 w-full" />
        <select name="role" class="border rounded px-3 py-2 w-full">
          <option value="0">All Roles</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?php echo (int)$r['id']; ?>" <?php echo $filter_role===(int)$r['id']?'selected':''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="border rounded px-3 py-2 w-full">
          <option value="" <?php echo $filter_status===null?'selected':''; ?>>All Status</option>
          <option value="1" <?php echo $filter_status===1?'selected':''; ?>>Active</option>
          <option value="0" <?php echo $filter_status===0?'selected':''; ?>>Deactivated</option>
        </select>
        <button class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-700">Filter</button>
      </form>

      <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg">
        <table class="w-full whitespace-nowrap">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php if (!$users): ?>
              <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No users found.</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-dark"><?php echo htmlspecialchars($u['username']); ?></td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['contact_number'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['role_name']); ?></td>
                  <td class="px-4 py-3 text-sm">
                    <?php if ((int)$u['is_active'] === 1): ?>
                      <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Active</span>
                    <?php else: ?>
                      <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">Deactivated</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($u['last_login'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-end gap-2 sm:flex-nowrap">
                      <button class="text-blue-600 hover:underline" onclick='openEditUser(<?php echo json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                      <form method="post" class="inline confirm-toggle">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                        <input type="hidden" name="action" value="toggle_user_status" />
                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>" />
                        <input type="hidden" name="is_active" value="<?php echo (int)$u['is_active']===1?0:1; ?>" />
                        <button class="text-orange-600 hover:underline"><?php echo (int)$u['is_active']===1?'Deactivate':'Activate'; ?></button>
                      </form>
                      <button class="text-purple-700 hover:underline" onclick="openResetPassword(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>')">Reset Password</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-4 text-sm text-gray-500">Showing <?php echo count($users); ?> result(s).</div>
    </div>
  </main>
</div>
<div id="userModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 overflow-y-auto">
  <div class="bg-white w-[95%] max-w-lg rounded-lg shadow-lg p-4 sm:p-6 max-h-[80vh] overflow-hidden">
    <div class="flex items-center justify-between mb-4">
      <h3 id="userModalTitle" class="text-lg font-semibold"></h3>
      <button type="button" onclick="closeUserModal()" class="text-gray-400 hover:text-gray-500">
        <i class="fa fa-times"></i>
      </button>
    </div>

    <form method="post" id="userForm" class="space-y-4">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="" />
      <input type="hidden" name="id" value="" />
      
      <div class="modal-body max-h-[60vh] overflow-y-auto pr-2 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Basic Information for All Users -->
          <!-- username removed from form; do not require or update it here -->
          
          <div>
            <label class="block text-sm font-medium mb-1">First Name <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" id="first_name" class="w-full border rounded px-3 py-2" required />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Last Name <span class="text-red-500">*</span></label>
            <input type="text" name="last_name" id="last_name" class="w-full border rounded px-3 py-2" required />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Middle Name</label>
            <input type="text" name="middle_name" id="middle_name" class="w-full border rounded px-3 py-2" />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Suffix</label>
            <input type="text" name="suffix" id="suffix" placeholder="e.g., Jr., Sr., III" class="w-full border rounded px-3 py-2" />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" id="email" class="w-full border rounded px-3 py-2" required />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Contact Number</label>
            <input type="text" name="contact_number" id="contact_number" class="w-full border rounded px-3 py-2" />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Role <span class="text-red-500">*</span></label>
            <select name="role_id" id="role_id" onchange="toggleRoleFields()" class="w-full border rounded px-3 py-2" required>
              <option value="">Select Role</option>
              <?php 
              $roleMap = [];
              foreach ($roles as $r) {
                  $roleMap[strtolower($r['name'])] = $r['id'];
                  echo '<option value="' . (int)$r['id'] . '">' . htmlspecialchars($r['name']) . '</option>';
              }
              ?>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <div class="mt-1">
              <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-primary focus:ring-primary" checked />
                <span class="ml-2">Active</span>
              </label>
            </div>
          </div>
          
          <div id="passwordRow" class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Password <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
              <!-- removed `required` attribute here; JS will set required when opening Create User -->
              <input type="text" name="password" id="password" class="flex-1 border rounded px-3 py-2" placeholder="Enter password" />
              <button type="button" onclick="genPwd('#password')" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">
                <i class="fa fa-refresh"></i>
              </button>
            </div>
          </div>
        </div>
        
        <!-- Student Specific Fields -->
        <div id="studentFieldsContainer" class="hidden border-t pt-4 mt-4 space-y-4">
          <h4 class="font-medium text-gray-900">Student Information</h4>
          <!-- Student Fields -->
          <div id="studentFields">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="student_number" class="block text-sm font-medium mb-1">Student Number <span class="text-red-500">*</span></label>
                <input type="text" name="student_number" id="student_number" class="w-full border rounded px-3 py-2" />
              </div>
              <div>
                <label for="birthdate" class="block text-sm font-medium mb-1">Birthdate <span class="text-red-500">*</span></label>
                <input type="date" name="birthdate" id="birthdate" class="w-full border rounded px-3 py-2" onchange="calculateAge()" />
                <input type="hidden" name="age" id="age" />
                <p id="ageDisplay" class="text-sm text-gray-500 mt-1">Age: -</p>
              </div>
              <div>
                <label for="sex" class="block text-sm font-medium mb-1">Sex <span class="text-red-500">*</span></label>
                <select name="sex" id="sex" class="w-full border rounded px-3 py-2" required>
                  <option value="">Select Sex</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label for="address" class="block text-sm font-medium mb-1">Address <span class="text-red-500">*</span></label>
                <textarea name="address" id="address" class="w-full border rounded px-3 py-2" rows="2"></textarea>
              </div>
              <div class="sm:col-span-2">
                <label for="course_id" class="block text-sm font-medium mb-1">Course <span class="text-red-500">*</span></label>
                <select name="course_id" id="course_id" class="w-full border rounded px-3 py-2" required>
                  <option value="">Select Course</option>
                  <?php
                  $courses = $pdo->query('SELECT id, CONCAT(course_code, " - ", course_name) as course_display FROM courses ORDER BY course_code');
                  foreach ($courses as $course) {
                    echo "<option value='" . htmlspecialchars($course['id']) . "'>" . htmlspecialchars($course['course_display']) . "</option>";
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Staff/Teacher Common Fields -->
        <div id="staffTeacherFields" class="hidden border-t pt-4 mt-4 space-y-4">
          <h4 class="font-medium text-gray-900">Employment Information</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Staff/Teacher ID <span class="text-red-500">*</span></label>
              <input type="text" name="staff_number" id="staff_number" class="w-full border rounded px-3 py-2 bg-gray-100" readonly />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Department <span class="text-red-500">*</span></label>
              <select name="department" id="department" class="w-full border rounded px-3 py-2" required>
                <option value="">Select Department</option>
                <?php
                // Fetch departments that don't have a marshal assigned yet
                try {
                  $deptQuery = "SELECT d.department_name
                                FROM departments d
                                LEFT JOIN marshal m ON d.id = m.department_id
                                WHERE m.id IS NULL
                                ORDER BY d.department_name ASC";
                  $deptStmt = $pdo->query($deptQuery);
                  $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
                  
                  // If no departments found, use default list
                  if (empty($departments)) {
                    $departments = [
                      'No department found'
                    ];
                  }
                  
                  // Output department options
                  foreach ($departments as $dept) {
                    echo "<option value=\"" . htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') . "</option>";
                  }
                } catch (Exception $e) {
                  // Fallback to default departments if query fails
                  error_log("Error fetching departments: " . $e->getMessage());
                  $defaultDepartments = [
                    'No department found.'
                  ];
                  foreach ($defaultDepartments as $dept) {
                    echo "<option value=\"" . htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') . "</option>";
                  }
                }
                ?>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium mb-1">Position <span class="text-red-500">*</span></label>
              <input type="text" name="position" id="position" class="w-full border rounded px-3 py-2" placeholder="e.g., Teacher, Head, Coordinator" />
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button type="button" onclick="closeUserModal()" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:opacity-90">Save</button>
      </div>
    </form>
  </div>
</div>
<div id="resetModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
  <div class="bg-white w-[95%] max-w-md rounded-lg shadow-lg p-4 sm:p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Reset Password</h3>
      <button type="button" onclick="closeReset()" class="text-gray-400 hover:text-gray-500">
        <i class="fa fa-times"></i>
      </button>
    </div>
    <form method="post" id="resetForm" class="space-y-4">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
      <input type="hidden" name="action" value="reset_password" />
      <input type="hidden" name="id" id="resetUserId" />
      
      <div>
        <label class="block text-sm font-medium mb-1">New Password <span class="text-red-500">*</span></label>
        <div class="flex gap-2">
          <input type="text" name="new_password" id="new_password" class="flex-1 border rounded px-3 py-2" required />
          <button type="button" onclick="genPwd('#new_password')" class="px-3 py-2 bg-gray-100 rounded hover:bg-gray-200">
            <i class="fa fa-refresh"></i>
          </button>
        </div>
      </div>
      
      <div class="flex justify-end gap-2">
        <button type="button" onclick="closeReset()" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-primary text-white rounded hover:opacity-90">Reset Password</button>
      </div>
    </form>
  </div>
</div>
<div id="rolesModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-lg shadow-lg p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Manage Roles</h2>
      <button onclick="closeRoles()" class="text-gray-500 hover:text-gray-700"><i class="fa fa-times"></i></button>
    </div>

    <div class="mb-4">
      <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
        <input type="hidden" name="action" value="create_role" />
        <div>
          <label class="block text-sm font-medium mb-1">Role Name</label>
          <input type="text" name="role_name" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <input type="text" name="role_desc" class="w-full border rounded px-3 py-2" />
        </div>
        <div class="md:col-span-3 text-right">
          <button class="px-4 py-2 bg-primary text-white rounded">Add Role</button>
        </div>
      </form>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded max-h-[60vh]">
      <table class="w-full whitespace-nowrap">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach ($roles as $r): ?>
            <tr>
              <td class="px-4 py-2 text-sm font-medium"><?php echo htmlspecialchars($r['name']); ?></td>
              <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
              <td class="px-4 py-2 text-sm text-right">
                <div class="inline-flex gap-2">
                  <button class="text-blue-600 hover:underline" onclick='openEditRole(<?php echo json_encode($r, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>Edit</button>
                  <form method="post" class="inline confirm-delete-role">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                    <input type="hidden" name="action" value="delete_role" />
                    <input type="hidden" name="role_id" value="<?php echo (int)$r['id']; ?>" />
                    <button class="text-red-600 hover:underline">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="editRoleWrap" class="mt-4 hidden">
      <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
        <input type="hidden" name="action" value="update_role" />
        <input type="hidden" name="role_id" id="editRoleId" value="" />
        <div>
          <label class="block text-sm font-medium mb-1">Role Name</label>
          <input type="text" name="role_name" id="editRoleName" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <input type="text" name="role_desc" id="editRoleDesc" class="w-full border rounded px-3 py-2" />
        </div>
        <div class="md:col-span-3 text-right">
          <button class="px-4 py-2 bg-primary text-white rounded">Update Role</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Initialize form submission
  document.addEventListener('DOMContentLoaded', function() {
    const userForm = document.getElementById('userForm');
    if (userForm) {
      userForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = userForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        try {
          // Get form data
          const formData = new FormData(userForm);
          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
          });
          
          const result = await response.text();
          
          // Check if response is HTML (error page) or JSON
          let isJson = false;
          let data = {};
          try {
            data = JSON.parse(result);
            isJson = true;
          } catch (e) {
            // Not JSON, treat as HTML response
            window.location.reload();
            return;
          }
          
          if (response.ok) {
            // Show success message
            await Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: data.message || 'Operation completed successfully',
              confirmButtonColor: '#3b82f6',
            });
            
            // Reload the page to show updated data
            window.location.reload();
          } else {
            throw new Error(data.message || 'An error occurred');
          }
        } catch (error) {
          // Show error message
          await Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.message || 'An error occurred while processing your request',
            confirmButtonColor: '#ef4444',
          });
        } finally {
          // Reset button state
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        }
      });
    }
  });

  // Calculate age from birthdate
  function calculateAge() {
    const birthdate = document.getElementById('birthdate');
    const ageDisplay = document.getElementById('ageDisplay');
    
    if (!birthdate || !ageDisplay) return;
    
    const birthDate = new Date(birthdate.value);
    if (isNaN(birthDate.getTime())) {
      ageDisplay.textContent = 'Age: -';
      return;
    }
    
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    
    document.getElementById('age').value = age;
    ageDisplay.textContent = `Age: ${age} years`;
  }

  // Initialize all required elements
  const userModal = document.getElementById('userModal');
  const rolesModal = document.getElementById('rolesModal');
  const resetModal = document.getElementById('resetModal');
  const userForm = document.getElementById('userForm');
  const userModalTitle = document.getElementById('userModalTitle');
  const passwordRow = document.getElementById('passwordRow');
  const btnOpenCreateUser = document.getElementById('btnOpenCreateUser');
  const rolesBtn = document.getElementById('btnOpenRoles');

  // Add event listeners
  if (btnOpenCreateUser) {
    btnOpenCreateUser.addEventListener('click', openCreateUser);
  }
  
  if (rolesBtn) {
    rolesBtn.addEventListener('click', openRoles);
  }

  function openCreateUser() {
    // Check for required elements
    if (!userModal || !userForm || !userModalTitle || !passwordRow) {
      console.error('Required elements not found');
      return;
    }
    
    try {
      // Set modal title
      userModalTitle.textContent = 'Create User';
      
      // Reset the form
      userForm.reset();
      
      // Set form action
      const actionInput = userForm.querySelector('input[name="action"]');
      if (actionInput) {
        actionInput.value = 'create_user';
      }
      
      // Set default values for form fields
      const fieldsToReset = {
        'first_name': '',
        'middle_name': '',
        'last_name': '',
        'suffix': '',
        'email': '',
        'contact_number': '',
        'role_id': '',
        'is_active': true,
        'password': '',
        'student_number': '',
        'birthdate': '',
        'address': '',
        'course_id': '',
        'sex': '',
        'staff_number': '',
        'department': '',
        'position': ''
      };
      
      // Safely set field values
      Object.entries(fieldsToReset).forEach(([field, value]) => {
        const element = userForm.elements[field] || document.getElementById(field);
        if (element) {
          if (element.type === 'checkbox') {
            element.checked = value;
          } else {
            element.value = value;
          }
        }
      });
      
      // Set password as required and show the password field
      if (userForm.password) {
        userForm.password.required = true;
      }
      passwordRow.classList.remove('hidden');
      
      // Show the modal (ensure flex centering)
      show(userModal);
      
      // Initialize role fields
    if (typeof toggleRoleFields === 'function') {
      try {
        toggleRoleFields();
      } catch (error) {
        console.error('Error initializing role fields:', error);
      }
    }
    
    // Hide all role-specific sections and reset required fields
    (function() {
      try {
        const roleSections = ['studentFieldsContainer', 'staffTeacherFields'];
        roleSections.forEach(id => {
          const section = document.getElementById(id);
          if (section) {
            section.classList.add('hidden');
            try {
              const formFields = section.querySelectorAll('input, select, textarea');
              formFields.forEach(field => {
                field.required = false;
              });
            } catch (e) {
              console.error('Error updating form fields:', e);
            }
          }
        });
      } catch (error) {
        console.error('Error in role-specific section handling:', error);
      }
    })();
    
    // Set required for basic fields
    ['first_name', 'last_name', 'email'].forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field) field.required = true;
    });

    // Ensure password is NOT auto-generated on create; just show placeholder and empty value
    const pwdInput = document.getElementById('password');
    if (pwdInput) {
      pwdInput.value = '';
      pwdInput.placeholder = 'Enter password';
    }
    
    show(userModal);
    
    // Initialize role fields based on default selection
    toggleRoleFields();
    } catch (error) {
      console.error('Error in openCreateUser:', error);
    }
  }

  function openEditUser(u) {
    if (!userModal || !userForm || !userModalTitle || !passwordRow) {
      console.error('Required elements not found');
      return;
    }
    
    try {
      userModalTitle.textContent = 'Edit User';
      
      // Set form action and ID
      const actionInput = userForm.querySelector('input[name="action"]');
      const idInput = userForm.querySelector('input[name="id"]');
      if (actionInput) {
        actionInput.value = 'update_user';
      }
      if (idInput) {
        idInput.value = u.id;
      }
      
      // Parse username if first_name/last_name are not available
      let firstName = u.first_name || '';
      let lastName = u.last_name || '';
      
      // If names are not available, try to parse from username
      if (!firstName && !lastName && u.username) {
        const nameParts = u.username.trim().split(/\s+/);
        if (nameParts.length >= 2) {
          firstName = nameParts[0];
          lastName = nameParts.slice(1).join(' ');
        } else if (nameParts.length === 1) {
          lastName = nameParts[0];
        }
      }
      
      // Set user data
      const userData = {
        'first_name': firstName,
        'middle_name': u.middle_name || '',
        'last_name': lastName,
        'suffix': u.suffix || '',
        'email': u.email || '',
        'contact_number': u.contact_number || '',
        'role_id': u.role_id || '',
        'is_active': parseInt(u.is_active) === 1,
        'password': '',
        'student_number': u.student_number || '',
        'birthdate': u.birthdate || '',
        'address': u.address || '',
        'course_id': u.course_id || '',
        'sex': u.sex || '',
        'staff_number': u.staff_number || '',
        'department': u.department || '',
        'position': u.position || ''
      };
      
      // Safely set field values
      Object.entries(userData).forEach(([field, value]) => {
        const element = userForm.elements[field] || document.getElementById(field);
        if (element) {
          if (element.type === 'checkbox') {
            element.checked = value;
          } else {
            element.value = value;
          }
        }
      });
      
      // Handle password field
      if (userForm.password) {
        userForm.password.required = false;
        userForm.password.value = '';
      }
      passwordRow.classList.add('hidden');
      
      // Show the modal
      userModal.classList.remove('hidden');
      
      // Initialize role fields
      if (typeof toggleRoleFields === 'function') {
        toggleRoleFields();
      }
    
      // Set role-specific fields if they exist in the user object
      const roleFields = {
        // Student fields
        student_number: u.student_number,
        birthdate: u.birthdate,
        address: u.address,
        course_id: u.course_id,
        sex: u.sex,
        // Staff/Teacher fields
        staff_number: u.staff_number,
        department: u.department,
        position: u.position
      };
      
      Object.entries(roleFields).forEach(([fieldId, value]) => {
        const field = document.getElementById(fieldId);
        if (field) {
          field.value = value !== undefined && value !== null ? value : '';
        }
      });
    } catch (error) {
      console.error('Error in openEditUser:', error);
    }
  }

  // Role ID mappings
  const roleIds = {
    admin: <?php echo $roleMap['admin'] ?? 1; ?>,
    student: <?php echo $roleMap['student'] ?? 3; ?>,
    staff: <?php echo $roleMap['staff'] ?? 2; ?>,
    teacher: <?php echo $roleMap['teacher'] ?? ($roleMap['staff'] ?? 2); ?>
  };

  // Function to generate the next staff number
  async function generateNextStaffNumber() {
    try {
      const response = await fetch('get_next_staff_number.php');
      const data = await response.json();
      if (data.success && data.staff_number) {
        document.getElementById('staff_number').value = data.staff_number;
      } else {
        // Fallback: Generate client-side if API fails
        const currentYear = new Date().getFullYear();
        const randomNum = Math.floor(1000 + Math.random() * 9000); // Random 4-digit number
        document.getElementById('staff_number').value = `${currentYear}-${randomNum}`;
      }
    } catch (error) {
      console.error('Error generating staff number:', error);
      // Fallback in case of error
      const currentYear = new Date().getFullYear();
      const randomNum = Math.floor(1000 + Math.random() * 9000);
      document.getElementById('staff_number').value = `${currentYear}-${randomNum}`;
    }
  }
  
  // Role IDs mapping for better maintainability
  const ROLE_IDS = {
    ADMIN: 1,
    STAFF: 2,
    STUDENT: 3,
    MARSHAL: 5,
    TEACHER: 6
  };

  function toggleRoleFields() {
    const roleSelect = document.getElementById('role_id');
    if (!roleSelect) return;
    
    const roleId = parseInt(roleSelect.value);
    if (isNaN(roleId)) return;
    
    // Get all role-specific containers
    const roleContainers = {
      student: document.getElementById('studentFieldsContainer'),
      staff: document.getElementById('staffTeacherFields'),
      teacher: document.getElementById('staffTeacherFields')
    };           
    
    
    // Hide all role-specific fields first
    Object.values(roleContainers).forEach(container => {
      if (container) container.classList.add('hidden');
    });
    
    // Reset all role-specific required fields
    const allRoleFields = [
      'student_number', 'birthdate', 'address', 'course_id', // Student
      'department', 'position'                              // Staff/Teacher
    ];
    
    allRoleFields.forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field) field.required = false;
    });
    
    // Show and set required fields based on role
    switch (roleId) {
      case ROLE_IDS.STUDENT:
        if (roleContainers.student) {
          roleContainers.student.classList.remove('hidden');
          const studentFields = ['student_number', 'birthdate', 'address', 'course_id', 'first_name', 'last_name'];
          studentFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
              field.required = true;
              // set placeholder for student number in YY-XXXX format
              if (fieldId === 'student_number') {
                const yy = new Date().getFullYear().toString().slice(-2);
                field.placeholder = `${yy}-0001`;
              }
            }
          });
        }
        break;
        
      case ROLE_IDS.STAFF:
      case ROLE_IDS.TEACHER:
      case ROLE_IDS.MARSHAL:
        if (roleContainers.staff) roleContainers.staff.classList.remove('hidden');
        if (roleId === ROLE_IDS.TEACHER && roleContainers.teacher) {
          roleContainers.teacher.classList.remove('hidden');
        }
        
        generateNextStaffNumber();
        
        const staffFields = ['department', 'position', 'first_name', 'last_name'];
        staffFields.forEach(fieldId => {
          const field = document.getElementById(fieldId);
          if (field) field.required = true;
        });
        break;
    }
    
    // Always require first_name and last_name
    ['first_name', 'last_name'].forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field) field.required = true;
    });
  }

  function resetFormFields() {
    document.getElementById('staff_number').value = '';
    document.getElementById('department').value = '';
    document.getElementById('position').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('middle_name').value = '';
    document.getElementById('suffix').value = '';
    document.getElementById('studentFields').classList.add('hidden');
    document.getElementById('parentFields').classList.add('hidden');
    document.getElementById('staffTeacherFields').classList.add('hidden');
  }

  // Store the original openEditUser function if it exists
  const originalOpenEditUser = window.openEditUser || function() {};
  
  // Override the openEditUser function
  window.openEditUser = function(u) {
    // Call the original function if it exists
    if (typeof originalOpenEditUser === 'function') {
      originalOpenEditUser(u);
    }
    
    // Update user details
    const fields = ['first_name', 'last_name', 'middle_name', 'suffix'];
    fields.forEach(field => {
      if (u[field]) {
        const el = document.getElementById(field);
        if (el) el.value = u[field];
      }
    });
    
    // Initialize role fields after a small delay to ensure DOM is updated
    setTimeout(() => {
      toggleRoleFields();
      // Ensure modal is centered and visible after all updates
      show(userModal);
      if (userModal) { userModal.scrollTop = 0; }
    }, 50);
  };

  function closeUserModal() { hide(userModal); }

  function openRoles() { show(rolesModal); }
  function closeRoles() { hide(rolesModal); document.getElementById('editRoleWrap').classList.add('hidden'); }

  function openEditRole(r) {
    document.getElementById('editRoleWrap').classList.remove('hidden');
    document.getElementById('editRoleId').value = r.id;
    document.getElementById('editRoleName').value = r.name || '';
    document.getElementById('editRoleDesc').value = r.description || '';
  }

  function openResetPassword(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('new_password').value = genPassword();
    show(resetModal);
  }
  function closeReset() { hide(resetModal); }

  function show(el) { el.classList.remove('hidden'); el.classList.add('flex'); el.classList.add('items-center'); el.classList.add('justify-center'); }
  function hide(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  function genPwd(selector) {
    const pwd = genPassword();
    const input = document.querySelector(selector);
    if (input) input.value = pwd;
  }
  function genPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()_+-=';
    let pwd = '';
    for (let i = 0; i < 12; i++) {
      pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pwd;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashData');
    if (flash && window.Swal) {
      const type = flash.getAttribute('data-type') || 'info';
      const msg = flash.getAttribute('data-msg') || '';
      Swal.fire({ icon: type, title: type === 'success' ? 'Success' : 'Error', text: msg });
    }
    document.querySelectorAll('form.confirm-toggle').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        Swal.fire({
          title: 'Are you sure?',
          text: 'This will change the user\'s active status.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, proceed'
        }).then((result) => { if (result.isConfirmed) f.submit(); });
      });
    });
    document.querySelectorAll('form.confirm-delete-role').forEach((f) => {
      f.addEventListener('submit', (e) => {
        e.preventDefault();
        Swal.fire({
          title: 'Delete role?',
          text: 'This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it'
        }).then((result) => { if (result.isConfirmed) f.submit(); });
      });
    });
  });
</script>

<?php include_once __DIR__ . '/../../components/admin-footer.php'; ?>