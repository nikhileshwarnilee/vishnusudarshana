// Welcome Intro functionality
document.addEventListener('DOMContentLoaded', function() {
    const welcomeOverlay = document.getElementById('welcome-intro-overlay');
    const welcomePopup = document.getElementById('welcome-intro-popup');
    const welcomeBtn = document.getElementById('welcome-intro-btn');
    
    // Check if user has seen the welcome popup before
    const hasSeenWelcome = localStorage.getItem('hasSeenWelcome');
    
    // Show welcome popup on first visit
    if (!hasSeenWelcome) {
        setTimeout(() => {
            welcomeOverlay.classList.add('active');
            welcomePopup.classList.add('active');
        }, 500); // Show after 500ms delay
    }
    
    // Close welcome popup when button is clicked
    if (welcomeBtn) {
        welcomeBtn.addEventListener('click', function() {
            welcomeOverlay.classList.remove('active');
            welcomePopup.classList.remove('active');
            localStorage.setItem('hasSeenWelcome', 'true');
        });
    }
    
    // Close when clicking on overlay
    if (welcomeOverlay) {
        welcomeOverlay.addEventListener('click', function() {
            welcomeOverlay.classList.remove('active');
            welcomePopup.classList.remove('active');
            localStorage.setItem('hasSeenWelcome', 'true');
        });
    }
});
