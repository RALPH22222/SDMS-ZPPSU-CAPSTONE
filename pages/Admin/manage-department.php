<?php
$pageTitle = 'Manage Departments & Courses';
require_once __DIR__ . '/../../components/admin-head.php';
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Initialize variables
$departments = [];
$courses = [];

// Handle Department CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        $name = trim($_POST['department_name']);
        $code = trim($_POST['department_code']);
        
        if (!empty($name)) {
            try {
                $sql = "INSERT INTO departments (department_name, abbreviation) VALUES (:name, :code)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $name, ':code' => $code]);
                
                if ($stmt->rowCount() > 0) {
                    set_alert('success', 'Department added successfully.');
                } else {
                    set_alert('error', 'Failed to add department.');
                }
                header('Location: manage-department.php');
                exit();
            } catch (PDOException $e) {
                set_alert('error', 'Database error: ' . $e->getMessage());
            }
        }
    } 
    elseif (isset($_POST['edit_department'])) {
        $id = (int)$_POST['department_id'];
        $name = trim($_POST['department_name']);
        $code = trim($_POST['department_code']);
        
        if (!empty($name) && $id > 0) {
            try {
                $sql = "UPDATE departments SET department_name = :name, abbreviation = :code WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':id' => $id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    set_alert('success', 'Department updated successfully.');
                } else {
                    set_alert('info', 'No changes were made to the department.');
                }
                header('Location: manage-department.php');
                exit();
            } catch (PDOException $e) {
                set_alert('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
    elseif (isset($_POST['delete_department'])) {
        $id = (int)$_POST['department_id'];
        
        if ($id > 0) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // First, set any courses in this department to NULL
                $sql = "UPDATE courses SET department_id = NULL WHERE department_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                // Then delete the department
                $sql = "DELETE FROM departments WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                // Commit the transaction
                $pdo->commit();
                
                if ($stmt->rowCount() > 0) {
                    set_alert('success', 'Department deleted successfully.');
                } else {
                    set_alert('error', 'Department not found or already deleted.');
                }
                header('Location: manage-department.php');
                exit();
            } catch (PDOException $e) {
                // Rollback the transaction if something went wrong
                $pdo->rollBack();
                set_alert('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
    // Course CRUD operations
    elseif (isset($_POST['add_course'])) {
        $name = trim($_POST['course_name']);
        $code = trim($_POST['course_code']);
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : NULL;
        
        if (!empty($name)) {
            try {
                $sql = "INSERT INTO courses (course_name, course_code, department_id) VALUES (:name, :code, :dept_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':dept_id' => $department_id ?: null
                ]);
                
                if ($stmt->rowCount() > 0) {
                    set_alert('success', 'Course added successfully.');
                } else {
                    set_alert('error', 'Failed to add course.');
                }
                header('Location: manage-department.php?tab=courses');
                exit();
            } catch (PDOException $e) {
                set_alert('error', 'Database error: ' . $e->getMessage());
            }
        }
    } 
    elseif (isset($_POST['edit_course'])) {
        $id = (int)$_POST['course_id'];
        $name = trim($_POST['course_name']);
        $code = trim($_POST['course_code']);
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : NULL;
        
        if (!empty($name) && $id > 0) {
            try {
                $sql = "UPDATE courses SET course_name = :name, course_code = :code, department_id = :dept_id WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':code' => $code,
                    ':dept_id' => $department_id ?: null,
                    ':id' => $id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    set_alert('success', 'Course updated successfully.');
                } else {
                    set_alert('info', 'No changes were made to the course.');
                }
                header('Location: manage-department.php?tab=courses');
                exit();
            } catch (PDOException $e) {
                set_alert('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
    elseif (isset($_POST['delete_course'])) {
        $id = (int)$_POST['course_id'];
        
        if ($id > 0) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // First, set any students in this course to NULL
                $sql = "UPDATE students SET course_id = NULL WHERE course_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                // Then delete the course
                $sql = "DELETE FROM courses WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                // Commit the transaction
                $pdo->commit();
                
                if ($stmt->rowCount() > 0) {
                    set_alert('success', 'Course deleted successfully.');
                } else {
                    set_alert('error', 'Course not found or already deleted.');
                }
                header('Location: manage-department.php?tab=courses');
                exit();
            } catch (PDOException $e) {
                // Rollback the transaction if something went wrong
                $pdo->rollBack();
                set_alert('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

// Get current tab
$current_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['departments', 'courses']) ? $_GET['tab'] : 'departments';

// Fetch departments
try {
    $sql = "SELECT 
                id,
                department_name AS name,
                abbreviation AS code
            FROM departments
            ORDER BY department_name";
    $stmt = $pdo->query($sql);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
    error_log("Error fetching departments: " . $e->getMessage());
    set_alert('error', 'Error loading departments. ' . $e->getMessage());
}

// Fetch courses
try {
    $sql = "SELECT 
                c.id,
                c.course_name,
                c.course_code,
                d.department_name AS department,
                c.department_id
            FROM courses c
            LEFT JOIN departments d ON c.department_id = d.id
            ORDER BY d.department_name, c.course_name";
    $stmt = $pdo->query($sql);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $courses = [];
    error_log("Error fetching courses: " . $e->getMessage());
    set_alert('error', 'Error loading courses. ' . $e->getMessage());
}

include '../../components/admin-sidebar.php';
?>

<div class="min-h-screen md:pl-64">
  <!-- Mobile Header -->
  <div class="md:hidden sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="h-16 flex items-center px-4">
      <button id="adminSidebarToggle" class="text-primary text-2xl mr-3">
        <i class="fas fa-bars"></i>
      </button>
      <h1 class="text-xl font-semibold text-primary">Manage Departments</h1>
    </div>
  </div>

  <!-- Main Content -->
  <main class="px-4 md:px-8 py-6">
    <div class="flex items-center gap-3 mb-6">
      <i class="fas fa-building text-primary text-2xl"></i>
      <h1 class="text-2xl md:text-3xl font-bold text-primary">Manage Departments & Courses</h1>
    </div>
        
        <?php display_alerts(); ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Departments</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= count($departments) ?></h3>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-building text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Courses</p>
                        <h3 class="text-2xl font-bold text-gray-900 mt-1"><?= count($courses) ?></h3>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-book text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="myTab" data-tabs-toggle="#myTabContent" role="tablist">
                <li class="me-2" role="presentation">
                    <button class="inline-block p-4 border-b-2 rounded-t-lg <?= $current_tab === 'departments' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-600 hover:border-gray-300' ?>" 
                            id="departments-tab" 
                            data-tabs-target="#departments" 
                            type="button" 
                            role="tab" 
                            aria-controls="departments" 
                            aria-selected="<?= $current_tab === 'departments' ? 'true' : 'false' ?>"
                            onclick="window.location.href='?tab=departments'">
                        Departments
                    </button>
                </li>
                <li class="me-2" role="presentation">
                    <button class="inline-block p-4 border-b-2 rounded-t-lg <?= $current_tab === 'courses' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-600 hover:border-gray-300' ?>" 
                            id="courses-tab" 
                            data-tabs-target="#courses" 
                            type="button" 
                            role="tab" 
                            aria-controls="courses" 
                            aria-selected="<?= $current_tab === 'courses' ? 'true' : 'false' ?>"
                            onclick="window.location.href='?tab=courses'">
                        Courses
                    </button>
                </li>
            </ul>
        </div>
        
        <div id="myTabContent">
            <!-- Departments Tab -->
            <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200 mb-6 <?= $current_tab === 'departments' ? '' : 'hidden' ?>" id="departments" role="tabpanel" aria-labelledby="departments-tab">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Departments</h3>
                    <button type="button" 
                            class="text-white bg-primary hover:bg-primary/90 focus:ring-4 focus:ring-primary/50 font-medium rounded-lg text-sm px-5 py-2.5 inline-flex items-center transition-colors duration-200"
                            onclick="showDepartmentModal()">
                        <i class="fas fa-plus mr-2"></i> Add Department
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table id="departmentsTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center">
                                        <span>Name</span>
                                        <button type="button" class="ml-1">
                                            <i class="fas fa-sort text-gray-400"></i>
                                        </button>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center">
                                        <span>Code</span>
                                        <button type="button" class="ml-1">
                                            <i class="fas fa-sort text-gray-400"></i>
                                        </button>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($departments as $dept): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        <?= htmlspecialchars($dept['code']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button type="button" 
                                            class="text-blue-600 hover:text-blue-900 p-1.5 rounded-full hover:bg-blue-50 transition-colors duration-200"
                                            title="Edit"
                                            onclick="editDepartment(<?= $dept['id'] ?>, '<?= addslashes($dept['name']) ?>', '<?= addslashes($dept['code']) ?>')">
                                        <i class="fas fa-edit w-4 h-4"></i>
                                    </button>
                                    <button type="button" 
                                            class="text-red-600 hover:text-red-900 p-1.5 rounded-full hover:bg-red-50 transition-colors duration-200 ml-1"
                                            title="Delete"
                                            onclick="confirmDeleteDepartment(<?= $dept['id'] ?>, '<?= addslashes($dept['name']) ?>')">
                                        <i class="fas fa-trash w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No departments found. Add your first department using the button above.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Courses Tab -->
            <div class="p-4 bg-white rounded-lg shadow-sm border border-gray-200 mb-6 <?= $current_tab === 'courses' ? '' : 'hidden' ?>" id="courses" role="tabpanel" aria-labelledby="courses-tab">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900">Courses</h3>
                    <button type="button" 
                            class="text-white bg-primary hover:bg-primary/90 focus:ring-4 focus:ring-primary/50 font-medium rounded-lg text-sm px-5 py-2.5 inline-flex items-center transition-colors duration-200"
                            onclick="showCourseModal()">
                        <i class="fas fa-plus mr-2"></i> Add Course
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table id="coursesTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Course Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Code
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Department
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($courses as $course): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-green-100 text-green-600">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($course['course_name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        <?= htmlspecialchars($course['course_code']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $course['department'] ? htmlspecialchars($course['department']) : '<span class="text-gray-400">No department</span>' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button type="button" 
                                            class="text-blue-600 hover:text-blue-900 p-1.5 rounded-full hover:bg-blue-50 transition-colors duration-200"
                                            title="Edit"
                                            onclick="editCourse(<?= $course['id'] ?>, '<?= addslashes($course['course_name']) ?>', '<?= addslashes($course['course_code']) ?>', '<?= $course['department_id'] ?>')">
                                        <i class="fas fa-edit w-4 h-4"></i>
                                    </button>
                                    <button type="button" 
                                            class="text-red-600 hover:text-red-900 p-1.5 rounded-full hover:bg-red-50 transition-colors duration-200 ml-1"
                                            title="Delete"
                                            onclick="confirmDeleteCourse(<?= $course['id'] ?>, '<?= addslashes($course['course_name']) ?>')">
                                        <i class="fas fa-trash w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No courses found. Add your first course using the button above.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<!-- Department Modal -->
<div id="departmentModal" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4" id="departmentModalTitle">Add Department</h3>
            <form id="departmentForm" method="POST">
                <input type="hidden" name="department_id" id="department_id">
                <div class="mb-4">
                    <label for="department_name" class="block text-sm font-medium text-gray-700 mb-1">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" id="department_name" name="department_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label for="department_code" class="block text-sm font-medium text-gray-700 mb-1">Department Code</label>
                    <input type="text" id="department_code" name="department_code"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" 
                            onclick="closeDepartmentModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" id="departmentSubmit" name="add_department"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 transition-colors duration-200">
                        Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Course Modal -->
<div id="courseModal" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4" id="courseModalTitle">Add Course</h3>
            <form id="courseForm" method="POST">
                <input type="hidden" name="course_id" id="course_id">
                <div class="mb-4">
                    <label for="course_name" class="block text-sm font-medium text-gray-700 mb-1">Course Name <span class="text-red-500">*</span></label>
                    <input type="text" id="course_name" name="course_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label for="course_code" class="block text-sm font-medium text-gray-700 mb-1">Course Code</label>
                    <input type="text" id="course_code" name="course_code"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label for="department_id_select" class="block text-sm font-medium text-gray-700 mb-1">Department (Optional)</label>
                    <select id="department_id_select" name="department_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" 
                            onclick="closeCourseModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" id="courseSubmit" name="add_course"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/50 transition-colors duration-200">
                        Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Department Modal -->
<div id="deleteDepartmentModal" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-exclamation text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2" id="deleteModalTitle">Delete Department</h3>
            <p class="text-gray-600 text-center mb-6" id="deleteModalText">Are you sure you want to delete this department? This action cannot be undone.</p>
            <div class="flex justify-center space-x-4">
                <button type="button" onclick="closeDeleteModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" class="inline">
                    <input type="hidden" name="department_id" id="delete_id">
                    <button type="submit" name="delete_department" 
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Course Delete Confirmation Modal -->
<div id="deleteCourseModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-exclamation text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Delete Course</h3>
            <p class="text-gray-600 text-center mb-6">Are you sure you want to delete this course? This action cannot be undone.</p>
            <div class="flex justify-center space-x-4">
                <button type="button" onclick="closeDeleteCourseModal()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <form id="deleteCourseForm" method="POST" class="inline">
                    <input type="hidden" name="course_id" id="delete_course_id">
                    <button type="submit" name="delete_course" 
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include DataTables CSS and JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>

<script>
// Initialize DataTables
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable for departments
    if (document.getElementById('departmentsTable')) {
        $('#departmentsTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: -1 } // Disable sorting on actions column
            ],
            order: [[0, 'asc']]
        });
    }

    if (document.getElementById('coursesTable')) {
        $('#coursesTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            order: [[0, 'asc']]
        });
    }
});

// Department Modal Functions
function showDepartmentModal(department = null) {
    const modal = document.getElementById('departmentModal');
    const form = document.getElementById('departmentForm');
    const title = document.getElementById('departmentModalTitle');
    const submitBtn = document.getElementById('departmentSubmit');

    if (department) {
        title.textContent = 'Edit Department';
        submitBtn.name = 'edit_department';
        submitBtn.textContent = 'Update Department';
        document.getElementById('department_id').value = department.id;
        document.getElementById('department_name').value = department.name;
        document.getElementById('department_code').value = department.code || '';
    } else {
        title.textContent = 'Add Department';
        submitBtn.name = 'add_department';
        submitBtn.textContent = 'Add Department';
        form.reset();
        document.getElementById('department_id').value = '';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeDepartmentModal() {
    const el = document.getElementById('departmentModal');
    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function editDepartment(id, name, code) {
    showDepartmentModal({ id: id, name: name, code: code });
}

// Course Modal Functions
function showCourseModal(course = null) {
    const modal = document.getElementById('courseModal');
    const form = document.getElementById('courseForm');
    const title = document.getElementById('courseModalTitle');
    const submitBtn = document.getElementById('courseSubmit');

    if (course) {
        title.textContent = 'Edit Course';
        submitBtn.name = 'edit_course';
        submitBtn.textContent = 'Update Course';
        document.getElementById('course_id').value = course.id;
        document.getElementById('course_name').value = course.name || course.course_name;
        document.getElementById('course_code').value = course.code || course.course_code || '';
        document.getElementById('department_id_select').value = course.department_id || '';
    } else {
        title.textContent = 'Add Course';
        submitBtn.name = 'add_course';
        submitBtn.textContent = 'Add Course';
        form.reset();
        document.getElementById('course_id').value = '';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeCourseModal() {
    const el = document.getElementById('courseModal');
    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function editCourse(id, course_name, course_code, department_id) {
    showCourseModal({ id: id, course_name: course_name, course_code: course_code, department_id: department_id });
}

// Delete Confirmation Functions
function confirmDeleteDepartment(id, name) {
    const modal = document.getElementById('deleteDepartmentModal');

    document.getElementById('deleteModalText').textContent =
        `Are you sure you want to delete the department "${name}"? This action cannot be undone.`;

    // Set the department ID in the delete form
    document.getElementById('delete_id').value = id;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    const el = document.getElementById('deleteDepartmentModal');
    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function confirmDeleteCourse(id, name) {
    const modal = document.getElementById('deleteCourseModal');

    document.querySelector('#deleteCourseModal p').textContent =
        `Are you sure you want to delete the course "${name}"? This action cannot be undone.`;

    // Set the course ID in the delete form
    document.getElementById('delete_course_id').value = id;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeDeleteCourseModal() {
    const el = document.getElementById('deleteCourseModal');
    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const departmentModal = document.getElementById('departmentModal');
    const courseModal = document.getElementById('courseModal');
    const deleteModal = document.getElementById('deleteDepartmentModal');
    const deleteCourseModal = document.getElementById('deleteCourseModal');
    
    if (event.target === departmentModal) {
        closeDepartmentModal();
    } else if (event.target === courseModal) {
        closeCourseModal();
    } else if (event.target === deleteModal) {
        closeDeleteModal();
    } else if (event.target === deleteCourseModal) {
        closeDeleteCourseModal();
    }
}

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDepartmentModal();
        closeCourseModal();
        closeDeleteModal();
        closeDeleteCourseModal();
    }
});
</script>

<?php include '../../components/admin-footer.php'; ?>