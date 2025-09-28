<?php
  $current = basename($_SERVER['PHP_SELF']);
  function isActive($file, $current) {
    return $file === $current ? 'bg-primary/10 border-l-4 border-primary text-primary' : 'text-dark hover:text-primary';
  }
?>
<!-- Mobile menu overlay (hidden by default) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 md:hidden hidden"></div>

<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto h-full">
  <div class="h-16 flex items-center px-4 border-b border-gray-200 sticky top-0 bg-white z-10">
    <a href="dashboard.php" class="flex items-center gap-3">
      <img src="../../src/images/Logo.png" alt="ZPPSU" class="h-8 w-8">
      <span class="text-primary font-bold">Admin Panel</span>
    </a>
    <button id="adminSidebarToggle" class="ml-auto md:hidden text-primary text-xl focus:outline-none" aria-label="Toggle Sidebar">
      <i class="fa-solid fa-xmark text-xl"></i>
    </button>
  </div>

  <nav class="py-4 px-2">
    <ul class="space-y-1">
      <li>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('dashboard.php', $current); ?>">
          <i class="fa-solid fa-gauge w-5 text-inherit"></i>
          <span class="font-medium">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="manage-user.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('manage-user.php', $current); ?>">
          <i class="fa-solid fa-users w-5"></i>
          <span class="font-medium">Manage Users</span>
        </a>
      </li>
      <li>
        <a href="manage-violations.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('manage-violations.php', $current); ?>">
          <i class="fa-solid fa-gavel w-5"></i>
          <span class="font-medium">Manage Violations</span>
        </a>
      </li>
      <li>
        <a href="manage-cases.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('manage-cases.php', $current); ?>">
          <i class="fa-solid fa-folder-open w-5"></i>
          <span class="font-medium">Manage Cases</span>
        </a>
      </li>
      <li>
        <a href="manage-teachers.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('manage-teachers.php', $current); ?>">
          <i class="fa-solid fa-chalkboard-user w-5"></i>
          <span class="font-medium">Manage Teachers</span>
        </a>
      </li>
      <li>
        <a href="message.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('message.php', $current); ?>">
          <i class="fa-solid fa-message w-5"></i>
          <span class="font-medium">Message</span>
        </a>
      </li>
      <li>
        <a href="manage-notification.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('manage-notification.php', $current); ?>">
          <i class="fa-solid fa-bell w-5"></i>
          <span class="font-medium">Notifications</span>
        </a>
      </li>
      <li>
        <a href="activity-log.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('activity-log.php', $current); ?>">
          <i class="fa-solid fa-logs w-5"></i>
          <span class="font-medium">Activity Log</span>
        </a>
      </li>
      <li>
        <hr class="my-2 border-gray-200 py-20">
      </li>
      <li class="mt-auto">
        <a href="../Auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-dark hover:text-primary">
          <i class="fa-solid fa-right-from-bracket w-5"></i>
          <span class="font-medium">Logout</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('adminSidebar');
  const sidebarToggle = document.getElementById('adminSidebarToggle');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  function toggleSidebar() {
    sidebar.classList.toggle('-translate-x-full');
    sidebarOverlay.classList.toggle('hidden');
    document.body.classList.toggle('overflow-hidden');
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
  }
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
  }
  const navLinks = document.querySelectorAll('#adminSidebar a');
  navLinks.forEach(link => {
    link.addEventListener('click', function() {
      if (window.innerWidth < 768) {
        toggleSidebar();
      }
    });
  });

  function handleResize() {
    if (window.innerWidth >= 768) {
      sidebar.classList.remove('-translate-x-full');
      sidebarOverlay.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }
  }
  window.addEventListener('resize', handleResize);
});
</script>