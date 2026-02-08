// Welcome Intro functionality
document.addEventListener('DOMContentLoaded', function() {
    const welcomeOverlay = document.getElementById('welcome-intro-overlay');
    const welcomePopup = document.getElementById('welcome-intro-popup');
    const welcomeBtn = document.getElementById('welcome-intro-btn');
    const langButtons = document.querySelectorAll('.welcome-lang-btn');
    const langMessages = document.querySelectorAll('.welcome-intro-message');
    const preferredLanguage = localStorage.getItem('preferred_language') || 'en';

    function setWelcomeLanguage(lang) {
        langButtons.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
        });
        langMessages.forEach(message => {
            message.classList.toggle('active', message.getAttribute('data-lang') === lang);
        });
    }
    
    // Check if user has seen the welcome popup before
    const hasSeenWelcome = localStorage.getItem('hasSeenWelcome');
    
    // Show welcome popup on first visit
    if (!hasSeenWelcome) {
        setTimeout(() => {
            welcomeOverlay.classList.add('active');
            welcomePopup.classList.add('active');
        }, 500); // Show after 500ms delay
    }

    setWelcomeLanguage(preferredLanguage);

    langButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const lang = btn.getAttribute('data-lang');
            setWelcomeLanguage(lang);
        });
    });
    
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
