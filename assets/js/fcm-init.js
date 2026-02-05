// Firebase Cloud Messaging (Web Push) initialization
// Replace the config values with your Firebase project settings.

(function () {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  // Firebase configuration (placeholders)
  const firebaseConfig = {
    apiKey: 'AIzaSyAl8eZocTQsAVzXa9IOppZIovNerPi1txg',
    authDomain: 'vishnusudarshana-cfcf7.firebaseapp.com',
    projectId: 'vishnusudarshana-cfcf7',
    storageBucket: 'vishnusudarshana-cfcf7.firebasestorage.app',
    messagingSenderId: '1031851262508',
    appId: '1:1031851262508:web:7eb9b5c9313e045c928789',
    measurementId: 'G-E5HSH49XJ2'
  };

  try {
    firebase.initializeApp(firebaseConfig);
  } catch (e) {
    // Ignore if already initialized
  }

  const messaging = firebase.messaging();

  function sendTokenToServer(token) {
    return fetch('/save_token.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token })
    });
  }

  function requestPermissionAndToken() {
    if (!('Notification' in window)) {
      return;
    }

    if (Notification.permission === 'denied') {
      // Permission denied - do nothing silently
      return;
    }

    Notification.requestPermission().then(function (permission) {
      if (permission !== 'granted') {
        return;
      }

      navigator.serviceWorker.ready.then(function (registration) {
        messaging.getToken({
          vapidKey: 'BCGCb6Am8B21JPu7-PJ5HNsFMYka5CcHX24c5AI2EJKaoJ2VgXueWPa0izHnflXWJiOuknHXCuxjC3gNbiQBtp4',
          serviceWorkerRegistration: registration
        }).then(function (currentToken) {
          if (currentToken) {
            sendTokenToServer(currentToken);
          }
        }).catch(function () {
          // Token retrieval failed
        });
      });
    });
  }

  // Start the flow on load
  window.addEventListener('load', requestPermissionAndToken);
})();
