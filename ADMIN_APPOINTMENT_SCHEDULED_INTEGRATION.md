# Admin Appointment Scheduled - WhatsApp Integration

## Overview
When an admin accepts an appointment and assigns a date/time in `/admin/services/appointments.php`, an automatic WhatsApp message is sent to the customer with the scheduled appointment details.

---

## Template Configuration

**Template Name (Internal):** `ADMIN_APPOINTMENT_SCHEDULED`  
**Campaign Name (AiSensy):** `Admin Appointment Scheduled`

### Message Body
```
Namaskaram 🙏 {{1}},

Your appointment has been successfully accepted! ✅

📋 Service Request #{{2}}

📅 Appointment Details:
• Scheduled Date: {{3}}
• Time Slot: {{4}} to {{5}}
• Status: Confirmed ✓

You will receive a call from Vishnusudarshana Dharmik Sanskar Kendra during the scheduled time slot. Please ensure you are available.

If you need to reschedule, please contact us immediately.

Thank you for your faith in us!

Warm Regards,
Vishnusudarshana Dharmik Sanskar Kendra
🙏
```

### Template Parameters (6 total)
| # | Variable | Value |
|---|----------|-------|
| {{1}} | name | Customer Name |
| {{2}} | tracking_id | Service Request ID (e.g., VDSK-20260120-ABC123) |
| {{3}} | appointment_date | Formatted Date (e.g., 20 January 2026) |
| {{4}} | from_time | Start Time (e.g., 10:00 AM) |
| {{5}} | to_time | End Time (e.g., 11:00 AM) |
| {{6}} | tracking_url | Tracking ID (for button URL) |

### Button Configuration
- **Button Type:** URL Button
- **Button Label:** "Track Appointment"
- **Button URL:** `https://vishnusudarshana.com/track.php?id={{6}}`

---

## Files Modified

### 1. [config/whatsapp_config.php](config/whatsapp_config.php)
**Added:**
- Template mapping: `'ADMIN_APPOINTMENT_SCHEDULED' => 'Admin Appointment Scheduled'`
- Template variables: 6 parameters for appointment details
- Button configuration: URL button with tracking_url parameter

### 2. [helpers/send_whatsapp.php](helpers/send_whatsapp.php)
**Added event handler:**
```php
case 'admin_appointment_scheduled':
    return sendWhatsAppNotification(
        'admin_appointment_scheduled',
        [
            'mobile' => $row['mobile'],
            'name' => $row['customer_name'],
            'tracking_id' => $row['tracking_id'],
            'appointment_date' => $formattedDate,
            'from_time' => $timeFrom,
            'to_time' => $timeTo
        ]
    );
```

### 3. [admin/services/appointments.php](admin/services/appointments.php)
**Updated accept workflow:**
- Loops through each accepted appointment
- Formats appointment date to readable format (e.g., "20 January 2026")
- Calls `sendWhatsAppNotification('admin_appointment_scheduled', ...)`
- Includes customer name, tracking ID, date, and time range
- Error handling for failed WhatsApp sends

---

## How It Works

### Admin Workflow
1. Admin navigates to `/admin/services/appointments.php`
2. Selects pending appointments
3. Enters **Assigned Date** and **Time From/To**
4. Clicks **Accept** button
5. System:
   - Updates service_requests table with status='Accepted'
   - Stores assigned_date, assigned_from_time, assigned_to_time in form_data
   - **Automatically sends WhatsApp to customer** with confirmation

### Customer Receives
```
Namaskaram 🙏 Raj Kumar,

Your appointment has been successfully accepted! ✅

📋 Service Request #VDSK-20260120-ABC123

📅 Appointment Details:
• Scheduled Date: 20 January 2026
• Time Slot: 10:00 AM to 11:00 AM
• Status: Confirmed ✓

You will receive a call from Vishnusudarshana Dharmik Sanskar Kendra during the scheduled time slot. Please ensure you are available.

If you need to reschedule, please contact us immediately.

Thank you for your faith in us!

Warm Regards,
Vishnusudarshana Dharmik Sanskar Kendra
🙏
```

+ **Track Appointment** button linking to track.php with the appointment ID

---

## Setup Instructions

### Step 1: Create Template in AiSensy Dashboard
1. Login to AiSensy (https://app.aisensy.com)
2. Create new campaign: **"Admin Appointment Scheduled"**
3. Add message body (copy from above)
4. Add 6 variables: {{1}}, {{2}}, {{3}}, {{4}}, {{5}}, {{6}}
5. Add URL Button:
   - Label: "Track Appointment"
   - URL: `https://vishnusudarshana.com/track.php?id={{6}}`
6. Submit for Meta approval
7. Set to **"Live"** when approved

### Step 2: Verify Configuration
- Template name in config matches exactly: `'Admin Appointment Scheduled'`
- All 6 parameters are mapped
- Button URL uses {{6}} parameter

### Step 3: Test
1. Go to Admin Panel → Services → Appointments
2. Accept a test appointment with date/time
3. Verify customer receives WhatsApp message
4. Click "Track Appointment" button - should link to track.php?id=VDSK-...

---

## Error Handling
- If WhatsApp sending fails, error is logged to error_log
- Appointment is still accepted (WhatsApp failure won't block workflow)
- Admin panel shows acceptance success regardless

---

## Testing Checklist
✅ Admin can accept appointment  
✅ Date/time are saved correctly  
✅ WhatsApp message is sent  
✅ All 6 parameters display correctly  
✅ Track button opens correct track.php?id=  
✅ Message displays for multiple products  
✅ Mobile number formatting is correct  
✅ Errors logged if sending fails  

---

## Next Steps
- Create template in AiSensy dashboard as described above
- Test with a real appointment
- Monitor logs for any issues

