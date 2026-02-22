// Firebase Cloud Messaging Service
// Handles initialization, token storage, and topic subscription.

let messaging = null;
let serviceWorkerRegistration = null;
let foregroundHandlerAttached = false;

function getAppBasePath() {
  try {
    const scriptEl =
      document.currentScript ||
      document.querySelector('script[src*="assets/js/firebase-messaging.js"]');

    if (scriptEl && scriptEl.src) {
      const scriptUrl = new URL(scriptEl.src, window.location.origin);
      const suffix = '/assets/js/firebase-messaging.js';
      if (scriptUrl.pathname.endsWith(suffix)) {
        return scriptUrl.pathname.slice(0, -suffix.length);
      }
    }

    const path = window.location.pathname || '';
    const adminMarker = '/admin/';
    const formsMarker = '/forms/';

    if (path.includes(adminMarker)) {
      return path.slice(0, path.indexOf(adminMarker));
    }

    if (path.includes(formsMarker)) {
      return path.slice(0, path.indexOf(formsMarker));
    }

    const lastSlash = path.lastIndexOf('/');
    return lastSlash > 0 ? path.slice(0, lastSlash) : '';
  } catch (error) {
    console.warn('Unable to resolve app base path:', error);
    return '';
  }
}

const APP_BASE_PATH = getAppBasePath();

function appUrl(path) {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${APP_BASE_PATH}${normalizedPath}`;
}

function getServiceWorkerScope() {
  return APP_BASE_PATH ? `${APP_BASE_PATH}/` : '/';
}

const DEFAULT_NOTIFICATION_ICON = appUrl('/assets/images/logo/icon-iconpwa192.png');

async function ensureMessagingInitialized() {
  try {
    if (!('serviceWorker' in navigator)) {
      console.warn('Service Workers not supported in this browser');
      return false;
    }

    if (typeof firebase === 'undefined') {
      console.warn('Firebase SDK is not loaded');
      return false;
    }

    if (!serviceWorkerRegistration) {
      serviceWorkerRegistration = await navigator.serviceWorker.register(
        appUrl('/service-worker.js'),
        { scope: getServiceWorkerScope() }
      );
      console.log('Service Worker registered successfully');
    }

    if (!firebase.apps.length) {
      firebase.initializeApp(firebaseConfig);
    }

    messaging = firebase.messaging();
    return true;
  } catch (error) {
    console.error('Error preparing Firebase CM:', error);
    return false;
  }
}

/**
 * Initialize Firebase Cloud Messaging
 */
async function initializeFirebaseCM() {
  try {
    if (!('Notification' in window)) {
      console.warn('Notification API not supported in this browser');
      return false;
    }

    const initialized = await ensureMessagingInitialized();
    if (!initialized) {
      return false;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      console.log('Notification permission denied');
      return false;
    }

    console.log('Notification permission granted');

    const token = await messaging.getToken({
      vapidKey: VAPID_KEY
    });

    if (!token) {
      console.warn('No FCM token available');
      return false;
    }

    console.log('FCM Token obtained:', token);
    await storeFCMToken(token);
    handleForegroundMessages();
    return true;
  } catch (error) {
    console.error('Error initializing Firebase CM:', error);
    return false;
  }
}

/**
 * Store FCM token on the server
 */
async function storeFCMToken(token) {
  try {
    const response = await fetch(appUrl('/ajax/store_fcm_token.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ token })
    });

    const data = await response.json();
    if (data.success) {
      console.log('FCM token stored on server');
      localStorage.setItem('fcmToken', token);
      localStorage.setItem('fcmTokenTimestamp', String(Date.now()));
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
  if (!messaging || foregroundHandlerAttached) {
    return;
  }

  messaging.onMessage((payload) => {
    console.log('Message received in foreground:', payload);

    const notificationTitle = payload.notification?.title || 'New Notification';
    const notificationOptions = {
      body: payload.notification?.body || '',
      icon: payload.notification?.icon || DEFAULT_NOTIFICATION_ICON,
      badge: DEFAULT_NOTIFICATION_ICON,
      tag: 'vishnusudarshana-notification',
      data: payload.data || {}
    };

    if (serviceWorkerRegistration) {
      serviceWorkerRegistration.showNotification(notificationTitle, notificationOptions);
    }
  });

  foregroundHandlerAttached = true;
}

/**
 * Check if FCM token needs refresh
 */
async function refreshFCMTokenIfNeeded() {
  try {
    const initialized = await ensureMessagingInitialized();
    if (!initialized) {
      return;
    }

    if (!('Notification' in window) || Notification.permission !== 'granted') {
      return;
    }

    const lastTokenTime = localStorage.getItem('fcmTokenTimestamp');
    const currentTime = Date.now();
    const needsRefresh =
      !lastTokenTime || currentTime - Number(lastTokenTime) > 7 * 24 * 60 * 60 * 1000;

    if (needsRefresh) {
      const token = await messaging.getToken({
        vapidKey: VAPID_KEY
      });
      if (token) {
        await storeFCMToken(token);
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
      const initialized = await ensureMessagingInitialized();
      if (!initialized) {
        console.error('Firebase Messaging not initialized');
        return false;
      }
    }

    const token = localStorage.getItem('fcmToken');
    if (!token) {
      console.error('No FCM token available');
      return false;
    }

    const response = await fetch(appUrl('/ajax/subscribe_topic.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token,
        topic
      })
    });

    const data = await response.json();
    if (data.success) {
      console.log('Subscribed to topic:', topic);
      return true;
    }

    console.error('Failed to subscribe to topic:', data.message);
    return false;
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

    const response = await fetch(appUrl('/ajax/unsubscribe_topic.php'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token,
        topic
      })
    });

    const data = await response.json();
    if (data.success) {
      console.log('Unsubscribed from topic:', topic);
      return true;
    }

    console.error('Failed to unsubscribe from topic:', data.message);
    return false;
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
document.addEventListener('DOMContentLoaded', async () => {
  const initialized = await ensureMessagingInitialized();
  if (!initialized) {
    return;
  }

  if (!getFCMToken()) {
    await initializeFirebaseCM();
  } else {
    await refreshFCMTokenIfNeeded();
    handleForegroundMessages();
  }
});
