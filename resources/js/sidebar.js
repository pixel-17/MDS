
const CLAVE = 'mssc-sidebar-collapsed';

document.addEventListener('alpine:init', () => {
    Alpine.store('sidebar', {
        collapsed: localStorage.getItem(CLAVE) === '1',
        mobileOpen: false,

        toggleCollapse() {
            this.collapsed = !this.collapsed;
            localStorage.setItem(CLAVE, this.collapsed ? '1' : '0');
        },

        openMobile() {
            this.mobileOpen = true;
        },

        closeMobile() {
            this.mobileOpen = false;
        },
    });
});
