
<meta name="theme-color" content="#1b2b34">
<script>
    (function () {
        var modo = localStorage.getItem('mssc-theme');
        var oscuro = modo === 'dark' || (modo === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', oscuro);
    })();
</script>
