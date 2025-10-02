<?php
  $current = basename($_SERVER['PHP_SELF']);
  // Helper to check active link
  function isActive($file, $current) {
    return $file === $current ? 'bg-white/10 border-l-4 border-white text-white' : 'text-white/80 hover:text-white';
  }
?>
<aside id="parentSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-primary text-white border-r border-primary transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-out">
  <div class="h-16 flex items-center px-4 border-b border-white/20">
    <a href="dashboard.php" class="flex items-center gap-3">
      <img src="../../src/images/Logo.png" alt="ZPPSU" class="h-8 w-8">
      <span class="text-white font-bold">Parent Portal</span>
    </a>
    <button id="parentSidebarToggle" class="ml-auto md:hidden text-white text-xl" aria-label="Toggle Sidebar">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>

  <nav class="py-4">
    <ul class="space-y-1">
      <li>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('dashboard.php', $current); ?>">
          <i class="fa-solid fa-gauge w-5 text-inherit"></i>
          <span class="font-medium">Dashboard</span>
        </a>
      </li>
      <li>
        <a href="appeal.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('appeal.php', $current); ?>">
          <i class="fa-solid fa-scale-balanced w-5"></i>
          <span class="font-medium">Appeal</span>
        </a>
      </li>
      <li>
        <a href="message.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('message.php', $current); ?>">
          <i class="fa-solid fa-message w-5"></i>
          <span class="font-medium">Messages</span>
        </a>
      </li>
      <li>
        <a href="notifications.php" class="flex items-center gap-3 px-4 py-3 <?php echo isActive('notifications.php', $current); ?>">
          <i class="fa-solid fa-bell w-5"></i>
          <span class="font-medium">Notifications</span>
        </a>
      </li>
      <li>
        <hr class="my-2 border-white/20 ">
      </li>
      <li>
        <a href="../Auth/logout.php" class="flex items-center gap-3 px-4 py-20 text-white/80 hover:text-white">
          <i class="fa-solid fa-right-from-bracket w-5"></i>
          <span class="font-medium">Logout</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>