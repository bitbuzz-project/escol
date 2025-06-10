<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggleSidebar');
    const closeButton = document.getElementById('closeSidebar');

    toggleButton.addEventListener('click', () => {
        sidebar.classList.add('show');
    });

    closeButton.addEventListener('click', () => {
        sidebar.classList.remove('show');
    });
</script>
</body>
</html>
