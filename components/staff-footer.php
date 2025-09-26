
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toggleBtn = document.getElementById('staffSidebarToggle');
      const sidebar = document.getElementById('staffSidebar');
      if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
          sidebar.classList.toggle('-translate-x-full');
        });
      }
    });
  </script>
</body>
</html>