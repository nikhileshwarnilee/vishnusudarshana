// Firebase Cloud Messaging Configuration
const firebaseConfig = {
  apiKey: "AIzaSyAl8eZocTQsAVzXa9IOppZIovNerPi1txg",
  authDomain: "vishnusudarshana-cfcf7.firebaseapp.com",
  projectId: "vishnusudarshana-cfcf7",
  storageBucket: "vishnusudarshana-cfcf7.firebasestorage.app",
  messagingSenderId: "1031851262508",
  appId: "1:1031851262508:web:7eb9b5c9313e045c928789",
  measurementId: "G-E5HSH49XJ2"
};

// VAPID Key for Web Push Notifications
const VAPID_KEY = "BCGCb6Am8B21JPu7-PJ5HNsFMYka5CcHX24c5AI2EJKaoJ2VgXueWPa0izHnflXWJiOuknHXCuxjC3gNbiQBtp4";

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { firebaseConfig, VAPID_KEY };
}
