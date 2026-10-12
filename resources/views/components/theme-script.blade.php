{{-- Applies the stored colour theme before first paint. Anything but light or dark means follow the system. --}}
<script>
    try {
        const theme = localStorage.getItem('theme');

        if (theme === 'light' || theme === 'dark') {
            document.documentElement.dataset.theme = theme;
        }
    } catch (error) {
        // Storage is blocked: follow the system setting.
    }
</script>
