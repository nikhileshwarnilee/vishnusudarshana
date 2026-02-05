// Firebase Cloud Messaging Service
// This file handles FCM initialization and push notification subscription

let messaging = null;
let serviceWorkerRegistration = null;

/**
 * Initialize Firebase Cloud Messaging
 */
async function initializeFirebaseCM() {
  try {
    // Check if browser supports service workers
    if (!('serviceWorker' in navigator)) {
      console.warn('Service Workers not supported in this browser');
      return false;
    }

    // Register service worker
    serviceWorkerRegistration = await navigator.serviceWorker.register('/service-worker.js', {
      scope: '/'
    });
    console.log('Service Worker registered successfully');

    // Initialize Firebase
    if (!firebase.apps.length) {
      firebase.initializeApp(firebaseConfig);
    }

    // Get Firebase Messaging instance
    messaging = firebase.messaging();

    // Request notification permission
    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
      console.log('Notification permission granted');
      
      // Get FCM token
      const token = await messaging.getToken({
        vapidKey: VAPID_KEY
      });
      
      if (token) {
        console.log('FCM Token obtained:', token);
        // Store token on server or in localStorage
        await storeFCMToken(token);
        return true;
      } else {
        console.warn('No FCM token available');
        return false;
      }
    } else {
      console.log('Notification permission denied');
      return false;
    }
  } catch (error) {
    console.error('Error initializing Firebase CM:', error);
    return false;
  }
}

/**
 * Store FCM Token on the server
 */
async function storeFCMToken(token) {
  try {
    const response = await fetch('/ajax/store_fcm_token.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        token: token
      })
    });

    const data = await response.json();
    if (data.success) {
      console.log('FCM token stored on server');
      localStorage.setItem('fcmToken', token);
      localStorage.setItem('fcmTokenTimestamp', new Date().getTime());
    } else {
      console.error('Failed to store FCM token:', data.message);
    }
  } catch (error) {
    console.error('Error storing FCM token:', error);
  }
}

/**
 * Handle incoming foreground messages
 */
function handleForegroundMessages() {
  if (!messaging) return;

  messaging.onMessage((payload) => {
    console.log('Message received in foreground:', payload);

    const notificationTitle = payload.notification?.title || 'New Notification';
    const notificationOptions = {
      body: payload.notification?.body || '',
      icon: payload.notification?.icon || '/assets/images/logo/icon-iconpwa192.png',
      badge: '/assets/images/logo/icon-iconpwa192.png',
      tag: 'vishnusudarshana-notification',
      data: payload.data || {}
    };

    // Show notification
    if (serviceWorkerRegistration) {
      serviceWorkerRegistration.showNotification(notificationTitle, notificationOptions);
    }
  });
}

/**
 * Check if FCM token needs refresh
 */
async function refreshFCMTokenIfNeeded() {
  try {
    const lastTokenTime = localStorage.getItem('fcmTokenTimestamp');
    const currentTime = new Date().getTime();
    
    // Refresh token every 7 days
    if (!lastTokenTime || (currentTime - parseInt(lastTokenTime)) > 7 * 24 * 60 * 60 * 1000) {
      if (messaging && Notification.permission === 'granted') {
        const token = await messaging.getToken({
          vapidKey: VAPID_KEY
        });
        if (token) {
          await storeFCMToken(token);
        }
      }
    }
  } catch (error) {
    console.error('Error refreshing FCM token:', error);
  }
}

/**
 * Subscribe user to a specific topic
 */
async function subscribeToTopic(topic) {
  try {
    if (!messaging) {
      console.error('Firebase Messaging not initialized');
      return false;
    }

    const token = localStorage.getItem('fcmToken');
    if (!token) {
      console.error('No FCM token available');
      return false;
    }

    const response = await fetch('/ajax/subscribe_topic.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        token: token,
        topic: topic
      })
    });

    const data = await response.json();
    if (data.success) {
      console.log('Subscribed to topic:', topic);
      return true;
    } else {
      console.error('Failed to subscribe to topic:', data.message);
      return false;
    }
  } catch (error) {
    console.error('Error subscribing to topic:', error);
    return false;
  }
}

/**
 * Unsubscribe user from a specific topic
 */
async function unsubscribeFromTopic(topic) {
  try {
    const token = localStorage.getItem('fcmToken');
    if (!token) {
      console.error('No FCM token available');
      return false;
    }

    const response = await fetch('/ajax/unsubscribe_topic.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        token: token,
        topic: topic
      })
    });

    const data = await response.json();
    if (data.success) {
      console.log('Unsubscribed from topic:', topic);
      return true;
    } else {
      console.error('Failed to unsubscribe from topic:', data.message);
      return false;
    }
  } catch (error) {
    console.error('Error unsubscribing from topic:', error);
    return false;
  }
}

/**
 * Get stored FCM token
 */
function getFCMToken() {
  return localStorage.getItem('fcmToken');
}

/**
 * Initialize on page load
 */
document.addEventListener('DOMContentLoaded', () => {
  // Initialize Firebase CM if not already done
  if (!getFCMToken()) {
    initializeFirebaseCM();
  } else {
    // Refresh token if needed and setup message handling
    refreshFCMTokenIfNeeded();
    handleForegroundMessages();
  }
});

// Setup message handling once Firebase is loaded
if (typeof firebase !== 'undefined') {
  handleForegroundMessages();
}
