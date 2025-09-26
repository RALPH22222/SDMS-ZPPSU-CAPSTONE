
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('parentSidebar');
      const toggles = [
        document.getElementById('parentSidebarToggle'),
        document.getElementById('parentSidebarToggle2'),
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