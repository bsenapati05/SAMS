document.addEventListener("DOMContentLoaded", () => {
    const themeBtn = document.querySelector(".theme-btn");
    
    // 1. Initial Icon/State Sync
    const currentTheme = localStorage.getItem("theme") || "light";
    document.documentElement.setAttribute('data-theme', currentTheme);

    // 2. Toggle Logic (only if button exists on the page)
    if (themeBtn) {
        themeBtn.addEventListener("click", () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            
            // Update HTML attribute
            document.documentElement.setAttribute('data-theme', newTheme);
            // Save to LocalStorage
            localStorage.setItem('theme', newTheme);
            
            // Add/Remove .dark class to body if you still use it for specific CSS overrides
            if (newTheme === 'dark') {
                document.body.classList.add('dark');
            } else {
                document.body.classList.remove('dark');
            }
        });
    }
});