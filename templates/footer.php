  </div><!-- /#content -->
</div><!-- /#main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Check local storage for sidebar state on load
document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth >= 992) {
        document.body.classList.add('sidebar-collapsed');
    }
});

function toggleDesktopSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed', isCollapsed);
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sbOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('open');
}
</script>
</body>
</html>
