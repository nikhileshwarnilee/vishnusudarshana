// Firebase Messaging Service Worker
// This worker imports the existing PWA cache worker and adds FCM background handling.
importScripts('/service-worker.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

// TODO: Replace with your Firebase project config
firebase.initializeApp({
  apiKey: 'AIzaSyAl8eZocTQsAVzXa9IOppZIovNerPi1txg',
  authDomain: 'vishnusudarshana-cfcf7.firebaseapp.com',
  projectId: 'vishnusudarshana-cfcf7',
  storageBucket: 'vishnusudarshana-cfcf7.firebasestorage.app',
  messagingSenderId: '1031851262508',
  appId: '1:1031851262508:web:7eb9b5c9313e045c928789',
  measurementId: 'G-E5HSH49XJ2'
});

const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage((payload) => {
  const notification = payload.notification || {};
  const title = notification.title || 'New Notification';
  const options = {
    body: notification.body || '',
    icon: notification.icon || '/assets/images/icon-192.png',
    data: payload.data || {}
  };
  self.registration.showNotification(title, options);
});
