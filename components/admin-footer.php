 <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toggleBtn = document.getElementById('adminSidebarToggle');
      const sidebar = document.getElementById('adminSidebar');
      if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
          sidebar.classList.toggle('-translate-x-full');
        });
      }
    });
  </script>
</body>
</html>
