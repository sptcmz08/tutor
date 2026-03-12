</main>
</div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    // Close sidebar on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            document.getElementById('sidebar').classList.remove('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.add('hidden');
        }
    });
</script>
</body>

</html>