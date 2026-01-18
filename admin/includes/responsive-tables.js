/* ========================================
   TABLE RESPONSIVE HELPER
   Automatically wraps all tables for mobile responsiveness
   ======================================== */

document.addEventListener('DOMContentLoaded', function() {
    // Find all tables that aren't already wrapped
    const tables = document.querySelectorAll('table:not(.no-responsive)');
    
    tables.forEach(function(table) {
        // Check if table is already wrapped
        if (table.parentElement.classList.contains('table-responsive-wrapper')) {
            return; // Skip already wrapped tables
        }
        
        // Create wrapper div
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive-wrapper';
        
        // Wrap the table
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });

    // Add scrolling indicator styling
    const addScrollIndicator = function() {
        const wrappers = document.querySelectorAll('.table-responsive-wrapper');
        
        wrappers.forEach(function(wrapper) {
            if (wrapper.scrollWidth > wrapper.clientWidth) {
                wrapper.style.position = 'relative';
                
                // Add visual indicator that table is scrollable on mobile
                if (window.innerWidth <= 768) {
                    wrapper.style.boxShadow = 'inset -10px 0 10px -10px rgba(0,0,0,0.1)';
                }
            }
        });
    };
    
    addScrollIndicator();
    
    // Recalculate on window resize
    window.addEventListener('resize', addScrollIndicator);

    // Optional: Add swipe hint on mobile
    if (window.innerWidth <= 768) {
        const wrappers = document.querySelectorAll('.table-responsive-wrapper');
        
        wrappers.forEach(function(wrapper) {
            if (wrapper.scrollWidth > wrapper.clientWidth) {
                // Add hint text
                const hint = document.createElement('div');
                hint.className = 'table-scroll-hint';
                hint.textContent = '← Swipe to scroll →';
                hint.style.cssText = `
                    text-align: center;
                    font-size: 11px;
                    color: #999;
                    padding: 4px 0;
                    margin-top: -4px;
                `;
                wrapper.parentNode.insertBefore(hint, wrapper.nextSibling);
            }
        });
    }
});
