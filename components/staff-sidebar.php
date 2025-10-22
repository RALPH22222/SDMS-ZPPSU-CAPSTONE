<?php
  $current = basename($_SERVER['PHP_SELF']);
    // Helper to check active link
  function staffIsActive($file, $current) {
    return $file === $current ? 'bg-primary/10 border-l-4 border-primary text-primary' : 'text-dark hover:text-primary';
  }
?>
<style>
  @media (max-width: 767px) {
    body.sidebar-open {
      overflow: hidden;
    }
    #staffSidebar {
      -webkit-overflow-scrolling: touch;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
  }
</style>

<aside id="staffSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col">
  <div class="h-16 flex items-center px-4 border-b border-gray-200 bg-white">
    <a href="dashboard.php" class="flex items-center gap-3">
      <img src="../../src/images/Logo.png" alt="ZPPSU" class="h-8 w-8">
      <span class="text-primary font-bold">Staff Panel</span>
    </a>
    <button id="staffSidebarToggle" class="ml-auto md:hidden text-primary text-xl focus:outline-none" aria-label="Toggle Sidebar">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('staffSidebar');
    const toggleButton = document.getElementById('staffSidebarToggle');
    const menuIcon = toggleButton?.querySelector('i');
    
    if (!toggleButton || !sidebar) return;
    
    const toggleSidebar = (e) => {
      e?.stopPropagation();
      const isOpen = sidebar.classList.contains('translate-x-0');
      
      if (isOpen) {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        document.body.classList.remove('sidebar-open');
        if (menuIcon) menuIcon.className = 'fa-solid fa-bars';
      } else {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        document.body.classList.add('sidebar-open');
        if (menuIcon) menuIcon.className = 'fa-solid fa-times';
      }
    };
    
    const handleOutsideClick = (e) => {
      if (window.innerWidth >= 768) return;
      if (!sidebar.contains(e.target) && !toggleButton.contains(e.target)) {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        document.body.classList.remove('sidebar-open');
        if (menuIcon) menuIcon.className = 'fa-solid fa-bars';
      }
    };
    
    const handleResize = () => {
      if (window.innerWidth >= 768) {
        sidebar.classList.remove('-translate-x-full', 'translate-x-0');
        document.body.classList.remove('sidebar-open');
        if (menuIcon) menuIcon.className = 'fa-solid fa-bars';
      } else {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
      }
    };
    
 
    toggleButton.addEventListener('click', toggleSidebar);
    document.addEventListener('click', handleOutsideClick);
    window.addEventListener('resize', handleResize);
 
    handleResize();
  });
  </script>

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
        <a href="manage-class.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('manage-class.php', $current); ?>">
          <i class="fa-solid fa-chalkboard-user w-5"></i>
          <span class="font-medium">Manage Class</span>
        </a>
      </li>
      <li>
        <a href="send-message.php" class="flex items-center gap-3 px-4 py-3 <?php echo staffIsActive('send-message.php', $current); ?>">
          <i class="fa-solid fa-message w-5"></i>
          <span class="font-medium">Send Message</span>
        </a>
      </li>
      <li>
        <hr class="my-2 border-gray-200 ">
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