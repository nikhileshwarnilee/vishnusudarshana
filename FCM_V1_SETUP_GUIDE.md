# Firebase Cloud Messaging v1 API Setup Guide

## ✅ What Changed?

Your FCM implementation has been **upgraded from Legacy API to FCM HTTP v1 API**:

| Before (Legacy) | After (v1 API) |
|----------------|----------------|
| ❌ Required FCM Server Key | ✅ Uses OAuth 2.0 (more secure) |
| ❌ Deprecated endpoint | ✅ Latest recommended API |
| ❌ `Authorization: key=SERVER_KEY` | ✅ `Authorization: Bearer ACCESS_TOKEN` |
| ⚠️ Will be deprecated by Google | ✅ Future-proof |

---

## 📋 Required Setup Steps

### **Step 1: Download Firebase Service Account JSON**

1. **Go to Firebase Console**: https://console.firebase.google.com/
2. **Select your project**: `vishnusudarshana-cfcf7`
3. **Click the gear icon** (⚙️) next to "Project Overview" 
4. Select **"Project settings"**
5. Go to the **"Service accounts"** tab
6. Click **"Generate new private key"** button
7. Click **"Generate key"** to confirm
8. A JSON file will download automatically (e.g., `vishnusudarshana-cfcf7-firebase-adminsdk-xxxxx-xxxxxxxxxx.json`)

**⚠️ IMPORTANT: Keep this file secure! It contains sensitive credentials.**

---

### **Step 2: Place the JSON File in Your Project**

1. **Rename** the downloaded file to: `firebase-service-account.json`
2. **Move** it to: `c:\xampp\htdocs\vishnusudarshana\vishnusudarshana\config\`
3. **Final path should be**: `config\firebase-service-account.json`

**Security Note**: Add this to `.gitignore` if using version control:
```
config/firebase-service-account.json
```

---

### **Step 3: Verify Configuration**

The following files have been updated automatically:

#### **config/fcm_config.php** ✓
```php
define('FCM_PROJECT_ID', 'vishnusudarshana-cfcf7');
define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/firebase-service-account.json');
```

#### **send_notification.php** ✓
- Now uses OAuth 2.0 tokens
- Sends to FCM v1 API endpoint
- Auto-generates access tokens
- Better error handling

---

## 🧪 Testing Your Setup

### **Option 1: Via Admin Panel**
1. Login to admin panel
2. Go to **Settings → Push Notifications**
3. Enter a test title and message
4. Click **"Send Notification"**

### **Option 2: Via Direct API Call**
```bash
curl -X POST http://localhost/send_notification.php \
  -d "title=Test Notification" \
  -d "message=Hello from FCM v1 API!"
```

---

## 📦 Dependencies Installed

The following packages have been automatically installed via Composer:

- **google/auth** (v1.50.0) - OAuth 2.0 token generation
- **firebase/php-jwt** (v7.0.2) - JWT signing
- **guzzlehttp/guzzle** (7.10.0) - HTTP client
- And supporting PSR libraries

---

## 🔍 Troubleshooting

### Error: "Firebase service account file not found"
**Solution**: Make sure `firebase-service-account.json` exists in the `config/` folder.

### Error: "Failed to generate access token"
**Solutions**:
1. Verify the JSON file is valid (not corrupted during download)
2. Check that the service account has "Firebase Cloud Messaging API Admin" role
3. Enable "Firebase Cloud Messaging API" in Google Cloud Console:
   - Go to: https://console.cloud.google.com/
   - Select project: `vishnusudarshana-cfcf7`
   - Search for "Firebase Cloud Messaging API"
   - Click **"Enable"**

### Error: "NOT_FOUND" or "INVALID_ARGUMENT"
**Solution**: The FCM token is invalid/expired. The system will automatically deactivate it.

---

## 🔐 Security Best Practices

1. ✅ **Never commit** `firebase-service-account.json` to version control
2. ✅ **Restrict file permissions** to 600/644 on production servers
3. ✅ **Enable Firebase App Check** for additional security
4. ✅ **Rotate service accounts** periodically
5. ✅ **Monitor usage** in Firebase Console

---

## 🎯 What You Get Now

✅ **More Secure** - OAuth 2.0 instead of static API keys  
✅ **Future-Proof** - Uses Google's recommended API  
✅ **Better Error Handling** - Automatically removes invalid tokens  
✅ **Auto-Expiring Tokens** - Access tokens regenerated as needed  
✅ **No Manual Key Management** - System handles authentication  

---

## 📊 Monitoring

Check notification delivery in **Firebase Console**:
1. Go to **Cloud Messaging** section
2. View **"Send history"** and **"Reports"**
3. Monitor delivery rates and errors

---

## ℹ️ Need Help?

- Firebase FCM v1 API Docs: https://firebase.google.com/docs/cloud-messaging/migrate-v1
- Service Account Setup: https://firebase.google.com/docs/admin/setup#initialize-sdk
- OAuth 2.0 for Server Apps: https://developers.google.com/identity/protocols/oauth2/service-account
