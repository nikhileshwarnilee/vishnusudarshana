// Firebase Messaging Service Worker
// This worker handles FCM background message notifications

// Safely import existing PWA service worker if it exists
try {
    importScripts('/service-worker.js');
    console.log('[SW] Imported existing service-worker.js');
} catch (e) {
    console.log('[SW] No existing service-worker.js found, continuing with FCM only');
}

// Import Firebase dependencies
try {
    importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');
    console.log('[SW] Firebase scripts imported successfully');
} catch (e) {
    console.error('[SW] Failed to import Firebase scripts:', e);
}

// Initialize Firebase in service worker
try {
    firebase.initializeApp({
        apiKey: 'AIzaSyAl8eZocTQsAVzXa9IOppZIovNerPi1txg',
        authDomain: 'vishnusudarshana-cfcf7.firebaseapp.com',
        projectId: 'vishnusudarshana-cfcf7',
        storageBucket: 'vishnusudarshana-cfcf7.firebasestorage.app',
        messagingSenderId: '1031851262508',
        appId: '1:1031851262508:web:7eb9b5c9313e045c928789',
        measurementId: 'G-E5HSH49XJ2'
    });
    console.log('[SW] Firebase initialized in service worker');
} catch (e) {
    console.error('[SW] Firebase initialization in SW failed:', e);
}

// Handle background messages
if (typeof firebase !== 'undefined' && firebase.messaging) {
    const messaging = firebase.messaging();
    
    messaging.onBackgroundMessage((payload) => {
        console.log('[SW] Background message received:', payload);
        const notification = payload.notification || {};
        const title = notification.title || 'New Notification';
        const options = {
            body: notification.body || '',
            icon: notification.icon || '/assets/images/logo/logo-iconpwa192.png',
            badge: '/assets/images/logo/logo-iconpwa192.png',
            data: payload.data || {}
        };
        self.registration.showNotification(title, options);
    });
} else {
    console.warn('[SW] Firebase Messaging not available in service worker');
}
