
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('studentSidebar');
      const toggles = [
        document.getElementById('studentSidebarToggle'),
        document.getElementById('studentSidebarToggle2'),
      ].filter(Boolean);
      if (sidebar && toggles.length) {
        toggles.forEach(btn => btn.addEventListener('click', () => {
          sidebar.classList.toggle('-translate-x-full');
        }));
      }
    });
  </script>
</body>
</html>