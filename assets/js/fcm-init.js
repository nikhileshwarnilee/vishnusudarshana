// Firebase Cloud Messaging (Web Push) initialization
// Replace the config values with your Firebase project settings.

(function () {
  console.log('[FCM] Initialization starting...');
  
  if (!('serviceWorker' in navigator)) {
    console.warn('[FCM] Service Workers not supported in this browser');
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
    console.log('[FCM] Firebase initialized successfully');
  } catch (e) {
    // Ignore if already initialized
    console.log('[FCM] Firebase already initialized:', e.message);
  }

  const messaging = firebase.messaging();

  function sendTokenToServer(token) {
    console.log('[FCM] Sending token to server:', token.substring(0, 50) + '...');
    return fetch('/save_token.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token })
    }).then(res => res.json()).then(data => {
      console.log('[FCM] Token saved to server:', data);
    }).catch(err => {
      console.error('[FCM] Failed to save token:', err);
    });
  }

  function requestPermissionAndToken() {
    console.log('[FCM] Requesting permission and token...');
    
    if (!('Notification' in window)) {
      console.warn('[FCM] Notification API not available');
      return;
    }

    console.log('[FCM] Current permission status:', Notification.permission);
    
    if (Notification.permission === 'denied') {
      // Permission denied - do nothing silently
      console.warn('[FCM] Permission denied by user');
      return;
    }

    if (Notification.permission === 'granted') {
      console.log('[FCM] Permission already granted, getting token...');
      navigator.serviceWorker.ready.then(function (registration) {
        console.log('[FCM] Service Worker ready');
        messaging.getToken({
          vapidKey: 'BCGCb6Am8B21JPu7-PJ5HNsFMYka5CcHX24c5AI2EJKaoJ2VgXueWPa0izHnflXWJiOuknHXCuxjC3gNbiQBtp4',
          serviceWorkerRegistration: registration
        }).then(function (currentToken) {
          if (currentToken) {
            console.log('[FCM] Token obtained:', currentToken.substring(0, 50) + '...');
            sendTokenToServer(currentToken);
          }
        }).catch(function (err) {
          console.error('[FCM] Failed to get token:', err);
        });
      }).catch(err => {
        console.error('[FCM] Service Worker not ready:', err);
      });
      return;
    }

    console.log('[FCM] Requesting permission from user...');
    Notification.requestPermission().then(function (permission) {
      console.log('[FCM] Permission result:', permission);
      
      if (permission !== 'granted') {
        console.warn('[FCM] Permission not granted');
        return;
      }

      navigator.serviceWorker.ready.then(function (registration) {
        console.log('[FCM] Service Worker ready, getting token...');
        messaging.getToken({
          vapidKey: 'BCGCb6Am8B21JPu7-PJ5HNsFMYka5CcHX24c5AI2EJKaoJ2VgXueWPa0izHnflXWJiOuknHXCuxjC3gNbiQBtp4',
          serviceWorkerRegistration: registration
        }).then(function (currentToken) {
          if (currentToken) {
            console.log('[FCM] Token obtained:', currentToken.substring(0, 50) + '...');
            sendTokenToServer(currentToken);
          }
        }).catch(function (err) {
          console.error('[FCM] Failed to get token:', err);
        });
      });
    }).catch(err => {
      console.error('[FCM] Permission request failed:', err);
    });
  }

  // Start the flow on load
  window.addEventListener('load', function() {
    console.log('[FCM] Window load event fired');
    requestPermissionAndToken();
  });

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
