<?php
  session_start();
  // Basic auth guard
  if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
  }

  require_once '../../database/database.php';
  require_once '../../includes/helpers.php';

  $currentUserId = (int)$_SESSION['user_id'];

  $currentStaffId = null;
  try {
    $stf = $pdo->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
    $stf->execute([$currentUserId]);
    $row = $stf->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) { 
      $currentStaffId = (int)$row['id']; 
    } else {
      // If not a staff, redirect to home
      header('Location: /SDMS/index.php');
      exit;
    }
  } catch (Throwable $e) { 
    // Log error and redirect
    error_log('Error checking staff status: ' . $e->getMessage());
    header('Location: /SDMS/index.php');
    exit;
  }

  $pageTitle = 'Manage Classes';

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
      // Remove student from class
      if (isset($_POST['delete_student'])) {
        $studentId = (int)$_POST['student_id'];
        $classId = (int)$_POST['class_id'];
        
        try {
          // Start a transaction
          $pdo->beginTransaction();
          
          // Verify the student is in the selected class
          $stmt = $pdo->prepare('SELECT id FROM students WHERE id = ? AND class_id = ?');
          $stmt->execute([$studentId, $classId]);
          
          if ($stmt->rowCount() > 0) {
            // Update the student's class_id to NULL to remove them from the class
            $updateStmt = $pdo->prepare('UPDATE students SET class_id = NULL WHERE id = ?');
            $updateStmt->execute([$studentId]);
            
            if ($updateStmt->rowCount() > 0) {
              $pdo->commit();
              $_SESSION['success'] = 'Student successfully removed from the class';
            } else {
              $pdo->rollBack();
              $_SESSION['error'] = 'Failed to remove student from class';
            }
          } else {
            $_SESSION['error'] = 'Student not found in this class';
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
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?class_id=' . $classId);
        exit;
      }
      if (isset($_POST['add_student'])) {
        error_log('Add student form submitted: ' . print_r($_POST, true));
        
        try {
          $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null;
          $classId = (int)$_POST['class_id'];
          
          // Validate required fields
          if (empty($_POST['student_number']) || empty($_POST['first_name']) || 
              empty($_POST['last_name']) || empty($_POST['class_id'])) {
              throw new Exception("All fields are required");
          }

          // Start transaction
          $pdo->beginTransaction();
          
          // Check if student already exists in any class
          $checkStmt = $pdo->prepare('SELECT class_id FROM students WHERE student_number = ?');
          $checkStmt->execute([trim($_POST['student_number'])]);
          $existingStudent = $checkStmt->fetch(PDO::FETCH_ASSOC);
          
          if ($existingStudent) {
              // Update existing student's class
              $updateStmt = $pdo->prepare('UPDATE students SET class_id = ? WHERE student_number = ?');
              $updateStmt->execute([$classId, trim($_POST['student_number'])]);
          } else {
              // Insert new student
              $insertStmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, class_id) 
                                         VALUES (?, ?, ?, ?)');
              $insertStmt->execute([
                  trim($_POST['student_number']),
                  trim($_POST['first_name']),
                  trim($_POST['last_name']),
                  $classId
              ]);
          }
          
          $pdo->commit();
          $_SESSION['success'] = 'Student successfully added to class';
          
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error in add student: ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?class_id=' . $classId);
        exit;
      }
      if (isset($_POST['update_student'])) {
        $stmt = $pdo->prepare('UPDATE students SET student_number = ?, first_name = ?, last_name = ?, class_id = ? WHERE id = ?');
        $stmt->execute([
          e($_POST['student_number']),
          e($_POST['first_name']),
          e($_POST['last_name']),
          (int)$_POST['class_id'],
          (int)$_POST['student_id']
        ]);
        
        $_SESSION['success'] = 'Student updated successfully';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?class_id=' . (int)$_POST['class_id']);
        exit;
      }
      
      // Delete student
      if (isset($_POST['delete_student'])) {
        $stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
        $stmt->execute([(int)$_POST['student_id']]);
        
        $_SESSION['success'] = 'Student deleted successfully';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?class_id=' . (int)$_POST['class_id']);
        exit;
      }
    } catch (PDOException $e) {
      error_log('Database error: ' . $e->getMessage());
      $_SESSION['error'] = 'An error occurred. Please try again.';
      header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['class_id']) ? '?class_id=' . (int)$_GET['class_id'] : ''));
      exit;
    }
  }

  // Get staff's assigned classes
  $classes = [];
  try {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE adviser_staff_id = ?');
    $stmt->execute([$currentStaffId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log('Error fetching classes: ' . $e->getMessage());
    $error = 'Failed to load classes. Please try again.';
  }

  // Get students in the selected class
  $selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
  $students = [];
  
  if ($selectedClassId) {
    try {
      $stmt = $pdo->prepare('SELECT * FROM students WHERE class_id = ?');
      $stmt->execute([$selectedClassId]);
      $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log('Error fetching students: ' . $e->getMessage());
      $error = 'Failed to load students. Please try again.';
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
          
          <!-- Class Selection -->
          <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Select Class</h2>
            <div class="flex flex-wrap gap-4">
              <?php foreach ($classes as $class): ?>
                <a href="?class_id=<?php echo e($class['id']); ?>" 
                   class="px-4 py-2 rounded-md text-sm font-medium <?php echo ($selectedClassId == $class['id']) ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                  <?php echo e($class['class_name']); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

                    <?php if ($selectedClassId): ?>
                        <div class="bg-white shadow rounded-lg p-6 mb-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Add New Student</h2>
                            <form method="POST" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div class="relative md:col-span-2">
                                <label for="student_search" class="block text-sm font-medium text-gray-700">Search Student</label>
                                <input type="text" id="student_search" autocomplete="off"
                                       class="mt-1 block w-full h-[38px] px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                       placeholder="Type to search..."
                                       style="min-width: 250px;">
                                <div id="student_suggestions" class="hidden absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-sm ring-1 ring-black ring-opacity-5 overflow-auto">
                                </div>
                            </div>
                            <input type="hidden" name="student_id" id="student_id">
                            <div class="relative">
                                <label for="student_number" class="block text-sm font-medium text-gray-700">Student Number</label>
                                <div class="mt-1 relative rounded-md">
                                    <input type="text" name="student_number" id="student_number" required readonly
                                           class="block w-full rounded-md border-gray-300 bg-gray-100 pl-3 pr-10 py-2 text-gray-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                           placeholder="Auto-filled"
                                           style="cursor: not-allowed;">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                                <div class="mt-1 relative rounded-md">
                                    <input type="text" name="first_name" id="first_name" required readonly
                                           class="block w-full rounded-md border-gray-300 bg-gray-100 pl-3 pr-10 py-2 text-gray-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                           placeholder="Auto-filled"
                                           style="cursor: not-allowed;">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                                <div class="mt-1 relative rounded-md">
                                    <input type="text" name="last_name" id="last_name" required readonly
                                           class="block w-full rounded-md border-gray-300 bg-gray-100 pl-3 pr-10 py-2 text-gray-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                           placeholder="Auto-filled"
                                           style="cursor: not-allowed;">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fa-solid fa-lock text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                                    <input type="hidden" name="class_id" value="<?php echo e($selectedClassId); ?>">
                                    <div class="flex items-end">
                                        <button type="submit" name="add_student"
                                                class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            Add Student
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Students List -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">Students in Class</h2>
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
                                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No students found in this class.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo e($student['student_number']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo e($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                        <button onclick="editStudent(<?php echo e(str_replace('"', '&quot;', json_encode($student))); ?>)" 
                                                                class="text-primary hover:text-primary-700">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button type="button" onclick="confirmDelete(<?php echo e($student['id']); ?>, '<?php echo e(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')" 
                                                                class="text-red-600 hover:text-red-900 ml-4">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                        <form id="delete-form-<?php echo e($student['id']); ?>" action="" method="POST" class="hidden">
                                                            <input type="hidden" name="student_id" value="<?php echo e($student['id']); ?>">
                                                            <input type="hidden" name="class_id" value="<?php echo e($selectedClassId); ?>">
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
                        <input type="hidden" name="class_id" value="<?php echo e($selectedClassId); ?>">
                        
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
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_student_number').value = student.student_number;
        document.getElementById('edit_first_name').value = student.first_name;
        document.getElementById('edit_last_name').value = student.last_name;
        
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
</script>

<?php include_once '../../components/staff-footer.php'; ?>

