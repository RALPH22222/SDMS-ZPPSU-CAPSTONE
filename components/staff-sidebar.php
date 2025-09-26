<?php
  $current = basename($_SERVER['PHP_SELF']);
  // Helper to check active link
  function staffIsActive($file, $current) {
    return $file === $current ? 'bg-primary/10 border-l-4 border-primary text-primary' : 'text-dark hover:text-primary';
  }
?>
<aside id="staffSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out flex flex-col">
  <div class="h-16 flex items-center px-4 border-b border-gray-200">
    <a href="dashboard.php" class="flex items-center gap-3">
      <img src="../../src/images/Logo.png" alt="ZPPSU" class="h-8 w-8">
      <span class="text-primary font-bold">Staff Panel</span>
    </a>
    <button id="staffSidebarToggle" class="ml-auto md:hidden text-primary text-xl" aria-label="Toggle Sidebar">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>

  <nav class="pt-4 pb-0 flex-1 flex flex-col">
    <ul class="space-y-1 flex-1 flex flex-col">
      <li>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('dashboard.php', $current); ?>">
          <i class="fa-solid fa-gauge w-5 text-inherit"></i>
          <span class="font-medium">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="manage-appeal.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('manage-appeal.php', $current); ?>">
          <i class="fa-solid fa-scale-balanced w-5"></i>
          <span class="font-medium">Manage Appeal</span>
        </a>
      </li>
      <li>
        <a href="manage-report.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('manage-report.php', $current); ?>">
          <i class="fa-solid fa-file-lines w-5"></i>
          <span class="font-medium">Manage Report</span>
        </a>
      </li>
      <li>
        <a href="manage-submission.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('manage-submission.php', $current); ?>">
          <i class="fa-solid fa-inbox w-5"></i>
          <span class="font-medium">Manage Submission</span>
        </a>
      </li>
      <li>
        <a href="send-message.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('send-message.php', $current); ?>">
          <i class="fa-solid fa-message w-5"></i>
          <span class="font-medium">Send Message</span>
        </a>
      </li>
      <li>
        <a href="notifications.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('notifications.php', $current); ?>">
          <i class="fa-solid fa-bell w-5"></i>
          <span class="font-medium">Notifications</span>
        </a>
      </li>
      <li>
        <hr class="my-2 border-gray-200 py-36">
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