# ✅ NOTIFICATION #3: FILE DOWNLOAD OTP VERIFICATION

## 📋 Requirement Summary
**When:** When user clicks "Download" button on track.php for a file  
**Who:** Service request customer  
**Type:** Manual (triggered by user action)  
**Flow:** User requests → OTP sent → User enters → File downloads

---

## 🎯 Template Details for AiSensy/Facebook

### Template Name
```
otp_verification_code
```

### Category
```
OTP_TRANSACTIONAL
```

### Required Variables (Parameters)
```
1. {{1}} - OTP Code (4 digits)
2. {{2}} - Validity in Minutes
```

### Message Template
```
Your OTP for file download is: {{1}}

This OTP is valid for {{2}} minutes.

Do not share this OTP with anyone.

Warm Regards,
Vishnu Sudarshana Dharmik Sanskrit Kendra
```

---

## ✅ Software Integration (COMPLETED)

### Changes Made:

1. **config/whatsapp_config.php**
   - ✅ Added `OTP_VERIFICATION` template definition: `'otp_verification_code'`
   - ✅ Added template variables: `['otp_code', 'validity_minutes']`

2. **api/verify_download_otp.php** (NEW FILE)
   - ✅ Created complete OTP verification API
   - ✅ Two endpoints:
     - `action=send_otp` → Generates 4-digit OTP, sends via WhatsApp, stores in session
     - `action=verify_otp` → Verifies entered OTP, generates download token
   - ✅ Security features:
     - 10-minute OTP expiry
     - 5-attempt limit before lockout
     - 5-minute download token validity
     - Phone number verification against tracking record
     - Proper error logging

3. **track.php**
   - ✅ Added OTP modal with beautiful UI (gold theme)
   - ✅ Download buttons changed from links to JavaScript function calls
   - ✅ Added OTP input form with auto-formatting for digits
   - ✅ Added timer display (10-minute countdown)
   - ✅ Added "Resend OTP" button (disabled for 30 seconds)
   - ✅ Added error/success message displays
   - ✅ Mobile-responsive modal design
   - ✅ JavaScript functions:
     - `requestDownloadOTP()` - Initiates OTP request
     - `verifyOTP()` - Verifies entered OTP
     - `resendOTP()` - Resends OTP
     - `startOTPTimer()` - Countdown timer
     - `disableResendButton()` - 30-second resend cooldown
     - `closeOTPModal()` - Closes modal

4. **download.php**
   - ✅ Added token verification on download initiation
   - ✅ Validates token matches tracking ID
   - ✅ Checks token expiry (5 minutes)
   - ✅ Logs all download attempts
   - ✅ Cleans up token after use

---

## 🔄 How It Works

### Step 1: User Clicks Download
```
User sees file in track.php → Clicks "Download" button
```

### Step 2: OTP Request
```
Frontend calls: api/verify_download_otp.php?action=send_otp
- Validates tracking ID & mobile
- Generates 4-digit OTP
- Sends via WhatsApp
- Stores in session (10-min expiry)
- Shows modal popup
```

### Step 3: User Enters OTP
```
Modal shows with:
- OTP input field
- 10-minute countdown timer
- "Resend OTP" button (30-second cooldown)
- Error/Success messages
```

### Step 4: OTP Verification
```
Frontend calls: api/verify_download_otp.php?action=verify_otp
- Verifies OTP matches
- Checks expiry
- Checks attempt count (max 5)
- Generates download token
- Returns token to frontend
```

### Step 5: File Download
```
Frontend redirects to: download.php?tracking_id=...&file=...&token=...
- download.php validates token
- Serves file
- Cleans up session token
```

---

## 📱 Message Template Example

```
Your OTP for file download is: 7824

This OTP is valid for 10 minutes.

Do not share this OTP with anyone.

Warm Regards,
Vishnu Sudarshana Dharmik Sanskrit Kendra
```

---

## 🔒 Security Features

✅ **OTP Security:**
- 4-digit randomly generated code
- 10-minute expiry time
- 5-attempt limit before lockout
- Session-stored (not in database)
- Cleared after verification

✅ **Download Token:**
- 32-byte random token
- 5-minute validity
- Single-use (deleted after download)
- Tied to specific tracking ID

✅ **Mobile Verification:**
- Phone number must match service record
- Prevents unauthorized file access

✅ **Audit Logging:**
- All OTP generation logged
- All verification attempts logged
- All downloads logged to error_log

---

## 📋 YOUR ACTION ITEMS

1. **Go to AiSensy/Facebook** → Create new template:
   - Name: `otp_verification_code`
   - Category: `OTP_TRANSACTIONAL`
   - Variables: `{{1}}` (OTP) and `{{2}}` (Minutes)
   - Body: Use message template above

2. **Submit for Meta Approval** (usually faster for OTP templates - 1-6 hours)

3. **Confirm to me** when approved

---

## ✅ Status

- ✅ **Backend Code:** READY
- ✅ **OTP Generation:** READY
- ✅ **OTP Verification:** READY
- ✅ **Download Token System:** READY
- ✅ **Frontend Modal:** READY
- ✅ **Security:** READY
- ⏳ **Template:** AWAITING CREATION IN AISENSY
- ⏳ **Approval:** AWAITING META APPROVAL

---

## 🧪 Testing Once Template Approved

1. **Manual Download Test:**
   - Go to track.php
   - Enter tracking ID or mobile
   - Click "Download" on a file
   - OTP modal should appear
   - Check WhatsApp for OTP message
   - Enter OTP (should be 4 digits)
   - File should download

2. **Test Cases:**
   - ✓ Correct OTP → File downloads
   - ✓ Wrong OTP → Error message, can retry
   - ✓ After 5 wrong attempts → Lockout message
   - ✓ After 10 minutes → OTP expires
   - ✓ Resend → New OTP sent (30-sec cooldown)

3. **Check Logs:**
   - `/logs/whatsapp.log` - OTP messages sent
   - PHP error_log - Download attempts and verifications

---

## 🔗 Related Files

- [api/verify_download_otp.php](api/verify_download_otp.php) - OTP API endpoint
- [track.php](track.php) - Track page with download buttons & modal
- [download.php](download.php) - File download with token verification
- [config/whatsapp_config.php](config/whatsapp_config.php) - OTP template config

---

## 📝 Technical Notes

**Session-Based Storage:**
- OTP stored in `$_SESSION['download_otp']` with metadata
- Download token stored in `$_SESSION['download_token_' . hash]`
- No database writes needed for OTP (cleaner, faster)

**Error Handling:**
- Invalid tracking ID → Error message
- Mobile mismatch → Error message
- OTP expired → Auto-logout with resend option
- Too many attempts → Automatic lockout
- Invalid token → Download denied

**User Experience:**
- Clean, intuitive modal design
- Real-time countdown timer
- Helpful error messages
- One-click resend option
- Mobile-responsive layout

---

## 🔗 Complete Notification System

You now have 3 notifications configured:

1. ✅ **Appointment Booked** → `appointment_booked_payment_successful`
2. ✅ **Service Received** → `service_request_received`
3. ⏳ **Download OTP** → `otp_verification_code`

Ready for Notification #4 or more?
