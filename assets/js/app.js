// Tutor Tracking System - Common JS
// (Most JS is inline for simplicity; this file for shared utilities)

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss flash messages after 5 seconds
    document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"]').forEach(function(el) {
        if (el.closest('main') || el.closest('.max-w-6xl')) {
            setTimeout(function() {
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(function() { el.remove(); }, 500);
            }, 5000);
        }
    });
});
