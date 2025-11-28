<?php
  session_start();
  
  // Basic auth guard
  if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
  }

  require_once '../../database/database.php';
  require_once '../../includes/helpers.php';
  
  // Get current user ID from session
  $currentUserId = (int)$_SESSION['user_id'];



  $pageTitle = 'Manage Departments';

  // Handle AJAX request for student search
  if (isset($_GET['search_students']) && isset($_GET['q'])) {
    header('Content-Type: application/json');
    try {
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
      
      // Debug log
      error_log('Search for: ' . $_GET['q'] . ' - Found: ' . count($results) . ' results');
      
      echo json_encode($results);
      exit;
    } catch (PDOException $e) {
      error_log('Search error: ' . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Error searching students: ' . $e->getMessage()]);
      exit;
    }
  }

  // Handle form submissions
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
      // Remove student from department
      if (isset($_POST['delete_student'])) {
        $studentId = (int)$_POST['student_id'];
        $deptId = (int)$_POST['dept_id'];
        
        try {
          // Start a transaction
          $pdo->beginTransaction();
          
          // Verify the student is in the selected department
          $stmt = $pdo->prepare('SELECT s.id 
                               FROM students s 
                               JOIN courses co ON s.course_id = co.id 
                               JOIN departments d ON co.department_id = d.id
                               JOIN marshal m ON d.id = m.department_id
                               WHERE s.id = ? AND m.user_id = ?');
          $stmt->execute([$studentId, $currentUserId]);
          
          if ($stmt->rowCount() > 0) {
            // Update the student's course_id to NULL to remove them from the department
            $updateStmt = $pdo->prepare('UPDATE students SET course_id = NULL WHERE id = ?');
            $updateStmt->execute([$studentId]);
            
            if ($updateStmt->rowCount() > 0) {
              $pdo->commit();
              $_SESSION['success'] = 'Student successfully removed from the department';
            } else {
              $pdo->rollBack();
              $_SESSION['error'] = 'Failed to remove student from department';
            }
          } else {
            $_SESSION['error'] = 'Student not found in this department';
          }
        } catch (PDOException $e) {
          $pdo->rollBack();
          error_log('Remove student from class error: ' . $e->getMessage());
          $_SESSION['error'] = 'Error removing student from class: ' . $e->getMessage();
        }
        
        // Return JSON response for AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
          header('Content-Type: application/json');
          echo json_encode([
            'success' => isset($_SESSION['success']),
            'message' => $_SESSION['success'] ?? $_SESSION['error'] ?? 'An error occurred'
          ]);
          exit;
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dept_id=' . $deptId);
        exit;
      }
      if (isset($_POST['add_student'])) {
        error_log('Add student form submitted: ' . print_r($_POST, true));
        
        try {
          $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null;
          $deptId = (int)$_POST['dept_id'];
          
          // Validate required fields
          if (empty($_POST['student_number']) || empty($_POST['first_name']) || 
              empty($_POST['last_name']) || empty($_POST['dept_id'])) {
              throw new Exception("All fields are required");
          }

          // Start transaction
          $pdo->beginTransaction();
          
          // Check if student already exists in any class
          $checkStmt = $pdo->prepare('SELECT class_id FROM students WHERE student_number = ?');
          $checkStmt->execute([trim($_POST['student_number'])]);
          $existingStudent = $checkStmt->fetch(PDO::FETCH_ASSOC);
          
          if ($existingStudent) {
              // Update existing student's department
              // First get a class from the selected department
              $classStmt = $pdo->prepare('SELECT id FROM classes WHERE department_id = ? LIMIT 1');
              $classStmt->execute([$deptId]);
              $class = $classStmt->fetch(PDO::FETCH_ASSOC);
              
              if ($class) {
                $updateStmt = $pdo->prepare('UPDATE students SET class_id = ? WHERE student_number = ?');
                $updateStmt->execute([$class['id'], trim($_POST['student_number'])]);
              } else {
                throw new Exception('No classes found in the selected department');
              }
          } else {
              // Insert new student with a class from the selected department
              // First get a class from the selected department
              $classStmt = $pdo->prepare('SELECT id FROM classes WHERE department_id = ? LIMIT 1');
              $classStmt->execute([$deptId]);
              $class = $classStmt->fetch(PDO::FETCH_ASSOC);
              
              if ($class) {
                $insertStmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, class_id) 
                                           VALUES (?, ?, ?, ?)');
                $insertStmt->execute([
                    trim($_POST['student_number']),
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    $class['id']
                ]);
              } else {
                throw new Exception('No classes found in the selected department');
              }
          }
          
          $pdo->commit();
          $_SESSION['success'] = 'Student successfully added to department';
          
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error in add student: ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dept_id=' . $deptId);
        exit;
      }
      if (isset($_POST['update_student'])) {
        // For department management, we don't update the class_id directly
        // as it's managed through the department selection
        $stmt = $pdo->prepare('UPDATE students SET student_number = ?, first_name = ?, last_name = ? WHERE id = ?');
        $stmt->execute([
          e($_POST['student_number']),
          e($_POST['first_name']),
          e($_POST['last_name']),
          (int)$_POST['student_id']
        ]);
        
        $_SESSION['success'] = 'Student updated successfully';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dept_id=' . (int)$_POST['dept_id']);
        exit;
      }
      
      // Delete student
      if (isset($_POST['delete_student'])) {
        // Instead of deleting, we'll just remove the student from the department
        $updateStmt = $pdo->prepare('UPDATE students SET class_id = NULL WHERE id = ?');
        $updateStmt->execute([(int)$_POST['student_id']]);
        
        $_SESSION['success'] = 'Student removed from department successfully';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dept_id=' . (int)$_POST['dept_id']);
        exit;
      }
    } catch (PDOException $e) {
      error_log('Database error: ' . $e->getMessage());
      $_SESSION['error'] = 'An error occurred. Please try again.';
      header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['dept_id']) ? '?dept_id=' . (int)$_GET['dept_id'] : ''));
      exit;
    }
  }

  // Debug: Log the current user ID
  error_log("Current user ID: " . $currentUserId);
  
  // Get the current user's department with department details in a single query
  $departments = [];
  try {
    // First, check if the marshal record exists
    $checkMarshal = $pdo->prepare("SELECT * FROM marshal WHERE user_id = ?");
    $checkMarshal->execute([$currentUserId]);
    $marshalData = $checkMarshal->fetch(PDO::FETCH_ASSOC);
    
    error_log("Marshal data: " . print_r($marshalData, true));
    
    if ($marshalData) {
      // Check if department_id is set
      if (isset($marshalData['department_id'])) {
        error_log("Department ID found: " . $marshalData['department_id']);
        
        // Get department details
        $query = "SELECT 
                    d.id AS department_id,
                    d.department_name,
                    d.abbreviation
                  FROM departments d 
                  WHERE d.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$marshalData['department_id']]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Department data: " . print_r($department, true));
        
        if ($department) {
          // Format the department data to match the expected structure
          $departments[] = [
            'id' => $department['department_id'],
            'department_name' => $department['department_name'],
            'abbreviation' => $department['abbreviation']
          ];
          error_log("Successfully loaded department: " . $department['department_name']);
        } else {
          $_SESSION['error'] = 'Your assigned department (ID: ' . $marshalData['department_id'] . ') was not found in the departments table.';
          error_log("Department not found with ID: " . $marshalData['department_id']);
        }
      } else {
        $_SESSION['error'] = 'You are not assigned to any department. Please contact the administrator to assign you to a department.';
        error_log("No department_id found for marshal with user_id: " . $currentUserId);
      }
    } else {
      $_SESSION['error'] = 'No marshal record found for your account. Please contact the administrator.';
      error_log("No marshal record found for user_id: " . $currentUserId);
    }
  } catch (PDOException $e) {
    error_log('Error fetching department information: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to load department information. Please try again or contact the administrator.';
    $error = 'Failed to load department information. Please try again.';
  }

  // Get students in the selected department
  $selectedDeptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
  $students = [];
  
  if ($selectedDeptId) {
    try {
      $query = "SELECT 
                  s.id,
                  s.student_number,
                  CONCAT(s.first_name, ' ', s.last_name) AS full_name,
                  co.course_name,
                  d.department_name
                FROM students s
                JOIN courses co ON s.course_id = co.id
                JOIN departments d ON co.department_id = d.id
                JOIN marshal m ON d.id = m.department_id
                WHERE m.user_id = ?
                ORDER BY s.last_name, s.first_name ASC";
      
      $stmt = $pdo->prepare($query);
      $stmt->execute([$currentUserId]);
      $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      // Debug: Log the query and results
      error_log("Student query executed. Found " . count($students) . " students for user ID: " . $currentUserId);
      
    } catch (PDOException $e) {
      error_log('Error fetching students: ' . $e->getMessage());
      $error = 'Failed to load students. Please try again. Error: ' . $e->getMessage();
    }
  }

  // Include header
  include_once '../../components/staff-head.php';
?>

<div class="min-h-screen bg-gray-50">
  <!-- Mobile sidebar toggle -->
  <button id="staffSidebarToggle" class="md:hidden fixed top-4 right-4 z-50 p-2 rounded-md text-gray-700 hover:bg-gray-100">
    <i class="fa-solid fa-bars text-xl"></i>
  </button>

  <?php include_once '../../components/staff-sidebar.php'; ?>

  <div class="md:pl-64 flex flex-col flex-1">
    <!-- Mobile header -->
    <div class="sticky top-0 z-10 md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-white">
      <button type="button" class="-ml-0.5 -mt-0.5 h-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500">
        <span class="sr-only">Open sidebar</span>
        <i class="fa-solid fa-bars h-6 w-6"></i>
      </button>
    </div>

    <main class="flex-1">
      <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
          <h1 class="text-2xl font-semibold text-gray-900">Manage Classes & Students</h1>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
          <?php 
          // Display flash messages
          if (isset($_SESSION['success'])) {
            echo '<div class="mb-4 p-4 bg-green-100 text-green-700 rounded">' . e($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
          }
          if (isset($_SESSION['error'])) {
            echo '<div class="mb-4 p-4 bg-red-100 text-red-700 rounded">' . e($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
          }
          if (isset($error)) {
            echo '<div class="mb-4 p-4 bg-red-100 text-red-700 rounded">' . e($error) . '</div>';
          }
          ?>
          
          <!-- Department Selection -->
          <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Select Department</h2>
            <div class="flex flex-wrap gap-4">
              <?php foreach ($departments as $dept): ?>
                <a href="?dept_id=<?php echo e($dept['id']); ?>" 
                   class="px-4 py-2 rounded-md text-sm font-medium <?php echo ($selectedDeptId == $dept['id']) ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                  <?php echo e($dept['department_name']); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

                    <?php if ($selectedDeptId): ?>
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-lg font-medium text-gray-900">Students in Department</h2>
                                <div class="w-64">
                                    <label for="student_search" class="sr-only">Search students</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="student_search" name="student_search" 
                                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm" 
                                               placeholder="Search students...">
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Number</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($students)): ?>
                                            <tr>
                                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No students found in this department.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo e($student['student_number']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo e($student['full_name'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo e($student['course_name'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                        <button onclick="editStudent(<?php echo e(str_replace('"', '&quot;', json_encode($student))); ?>)" 
                                                                class="text-primary hover:text-primary-700">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button type="button" onclick="confirmDelete(<?php echo e($student['id']); ?>, '<?php echo e(addslashes($student['full_name'] ?? 'this student')); ?>')" 
                                                                class="text-red-600 hover:text-red-900 ml-4">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                        <form id="delete-form-<?php echo e($student['id']); ?>" action="" method="POST" class="hidden">
                                                            <input type="hidden" name="student_id" value="<?php echo e($student['id']); ?>">
                                                            <input type="hidden" name="dept_id" value="<?php echo e($selectedDeptId); ?>">
                                                            <input type="hidden" name="delete_student" value="1">
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Edit Student Modal -->
<div id="editStudentModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="editStudentForm" method="POST">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">Edit Student</h3>
                    <div class="space-y-4">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        <input type="hidden" name="dept_id" value="<?php echo e($selectedDeptId); ?>">
                        
                        <div>
                            <label for="edit_student_number" class="block text-sm font-medium text-gray-700">Student Number</label>
                            <input type="text" name="student_number" id="edit_student_number" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="edit_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="edit_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" name="update_student"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to open the edit modal with student data
    window.editStudent = function(student) {
        // Set the student ID and number
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_student_number').value = student.student_number || '';
        
        // Handle full_name if first_name and last_name are not available
        if (student.first_name && student.last_name) {
            document.getElementById('edit_first_name').value = student.first_name;
            document.getElementById('edit_last_name').value = student.last_name;
        } else if (student.full_name) {
            // Split full_name into first and last names
            const names = student.full_name.trim().split(/\s+/);
            const lastName = names.pop() || '';
            const firstName = names.join(' ') || '';
            
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
        } else {
            // Fallback if no name data is available
            document.getElementById('edit_first_name').value = '';
            document.getElementById('edit_last_name').value = '';
        }
        
        // Show the modal
        const modal = document.getElementById('editStudentModal');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    // Function to close the edit modal
    window.closeEditModal = function() {
        const modal = document.getElementById('editStudentModal');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('editStudentModal');
        if (event.target === modal) {
            closeEditModal();
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        const modal = document.getElementById('editStudentModal');
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeEditModal();
        }
    });

    // Function to handle remove from class confirmation with SweetAlert
    window.confirmDelete = function(studentId, studentName) {
        Swal.fire({
            title: 'Remove Student from Class',
            text: `Are you sure you want to remove ${studentName} from this class? The student will remain in the system but will no longer be part of this class.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove from class',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Deleting...',
                    text: 'Please wait while we delete the student.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit the form
                const form = document.getElementById(`delete-form-${studentId}`);
                const formData = new FormData(form);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    // Reload the page to show the updated list
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'Error!',
                        'An error occurred while deleting the student.',
                        'error'
                    );
                });
            }
        });
    }

    // Student search functionality
    const searchInput = document.getElementById('student_search');
    const suggestionsContainer = document.getElementById('student_suggestions');
    let searchTimeout;
    let isRequestInProgress = false;

    // Function to show loading state
    function showLoading() {
        suggestionsContainer.innerHTML = '<div class="px-4 py-2 text-sm text-gray-500">Searching...</div>';
        suggestionsContainer.classList.remove('hidden');
    }

    // Function to show no results
    function showNoResults() {
        suggestionsContainer.innerHTML = '<div class="px-4 py-2 text-sm text-gray-500">No students found</div>';
        suggestionsContainer.classList.remove('hidden');
    }

    // Function to show error
    function showError(message) {
        suggestionsContainer.innerHTML = `<div class="px-4 py-2 text-sm text-red-500">${message}</div>`;
        suggestionsContainer.classList.remove('hidden');
    }

    // Function to fetch and display suggestions
    function fetchSuggestions(query) {
        if (isRequestInProgress) return;
        
        query = query.trim();
        if (query.length < 2) {
            suggestionsContainer.classList.add('hidden');
            return;
        }

        isRequestInProgress = true;
        showLoading();

        fetch(`${window.location.pathname}?search_students=1&q=${encodeURIComponent(query)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!Array.isArray(data)) {
                throw new Error(data.error || 'Invalid response format');
            }

            if (data.length === 0) {
                showNoResults();
                return;
            }

            suggestionsContainer.innerHTML = data.map(student => `
                <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-gray-700" 
                     data-id="${student.id}"
                     data-student-number="${student.student_number}"
                     data-first-name="${student.first_name}"
                     data-last-name="${student.last_name}">
                    <div class="font-medium">${student.student_number}</div>
                    <div class="text-gray-500">${student.first_name} ${student.last_name}</div>
                </div>
            `).join('');
            suggestionsContainer.classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error loading suggestions. Please try again.');
        })
        .finally(() => {
            isRequestInProgress = false;
        });
    }

    // Handle input with debounce
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetchSuggestions(e.target.value);
            }, 300);
        });

        searchInput.addEventListener('focus', (e) => {
            if (e.target.value.trim().length >= 2) {
                fetchSuggestions(e.target.value);
            }
        });
    }
    if (suggestionsContainer) {
        suggestionsContainer.addEventListener('click', (e) => {
            const suggestion = e.target.closest('[data-id]');
            if (!suggestion) return;

            document.getElementById('student_id').value = suggestion.dataset.id;
            document.getElementById('student_number').value = suggestion.dataset.studentNumber;
            document.getElementById('first_name').value = suggestion.dataset.firstName;
            document.getElementById('last_name').value = suggestion.dataset.lastName;
            
            suggestionsContainer.classList.add('hidden');
            searchInput.value = '';
        });
    }
    document.addEventListener('click', (e) => {
        if (!searchInput?.contains(e.target) && !suggestionsContainer?.contains(e.target)) {
            suggestionsContainer?.classList.add('hidden');
        }
    });
    // Function to filter students based on search input
    function filterStudents(searchTerm) {
        const searchTermLower = searchTerm.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const studentNumber = row.cells[0].textContent.toLowerCase();
            
            if (name.includes(searchTermLower) || studentNumber.includes(searchTermLower)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Form validation
    const form = document.querySelector('form[name="add_student"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const studentNumber = document.getElementById('student_number')?.value;
            const firstName = document.getElementById('first_name')?.value;
            const lastName = document.getElementById('last_name')?.value;
            const classId = document.querySelector('input[name="class_id"]')?.value;
            
            if (!studentNumber || !firstName || !lastName || !classId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please fill in all required fields',
                    confirmButtonColor: '#3b82f6',
                });
                return;
            }
            
            // Show loading state
            Swal.fire({
                title: 'Adding student...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit the form
            form.submit();
        });
    }
});

// Student search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('student_search');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                // Skip header rows if any
                if (row.querySelector('th')) return;
                
                const cells = row.querySelectorAll('td');
                if (cells.length >= 2) {
                    const studentNumber = cells[0]?.textContent?.toLowerCase() || '';
                    const name = cells[1]?.textContent?.toLowerCase() || '';
                    
                    if (studentNumber.includes(searchTerm) || name.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    }
});
</script>

<?php include_once '../../components/staff-footer.php'; ?>

