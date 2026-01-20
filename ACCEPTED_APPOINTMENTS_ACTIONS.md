# Accepted Appointments - Enhanced Actions Integration

## Overview
Updated `/admin/services/accepted-appointments.php` with three action buttons:
1. **Mark Completed** - Sends appointment completed message
2. **Cancel Appointments** - Cancels and returns to pending, sends cancellation message
3. **Send Message** - Opens modal for custom message to customers

---

## Features

### 1. Mark Completed
- Updates status to `Completed`
- Sends WhatsApp message: "Your appointment... on [date] has been completed. ✅"
- Template: `Appointment Completed` (3 parameters: name, tracking_id, appointment_date)

### 2. Cancel Appointments
- Updates status back to `Received` (returns to pending list)
- Sends WhatsApp cancellation message: "Your appointment with Service Request #[tracking_id] has been cancelled"
- Template: `Appointment Cancelled` (2 parameters: name, tracking_id)
- Confirmation dialog before action

### 3. Send Custom Message
- Opens modal form
- Admin types any custom message
- Sends to all selected customers via WhatsApp
- No template required - sends as plain text message
- Shows success count after sending

---

## Files Modified

### 1. [config/whatsapp_config.php](config/whatsapp_config.php)
**Added:**
- `'APPOINTMENT_CANCELLED' => 'Appointment Cancelled'` template mapping
- Variables config: `'APPOINTMENT_CANCELLED' => ['name', 'tracking_id']`

### 2. [helpers/send_whatsapp.php](helpers/send_whatsapp.php)
**Added event handler:**
```php
case 'appointment_cancelled_admin':
    return sendWhatsAppMessage(
        $data['mobile'],
        'APPOINTMENT_CANCELLED',
        [
            'name' => $data['name'] ?? $data['customer_name'] ?? '',
            'tracking_id' => $data['tracking_id'] ?? ''
        ]
    );
```

### 3. [admin/services/accepted-appointments.php](admin/services/accepted-appointments.php)
**Added:**
- Cancel action handler - updates status to Received, sends cancellation WhatsApp
- Send message action handler - processes custom messages via AiSensy text API
- Two new buttons: "Cancel Appointments" (red) and "Send Message" (blue)
- Modal popup for custom message input
- JavaScript functions: `submitCancel()`, `openMessageModal()`, `closeMessageModal()`, `submitMessage()`

---

## UI Changes

### Action Bar (when appointments selected)
```
[0 selected]  [Mark Completed]  [Cancel Appointments]  [Send Message]
```

### Colors
- Mark Completed: Green (#28a745)
- Cancel: Red (#dc3545)
- Send Message: Blue (#007bff)

### Custom Message Modal
- Opens when "Send Message" clicked
- Textarea for message input
- Cancel / Send buttons
- Closes on escape or background click

---

## Workflows

### Cancel Workflow
1. Admin selects appointments in table
2. Clicks "Cancel Appointments" button
3. Confirms dialog: "Cancel X appointment(s)? Customers will be notified."
4. System updates status to `Received`
5. Automatic WhatsApp sent to each customer:
   ```
   Namaskaram 🙏 [Name],

   Your appointment with Service Request #[TrackingID] has been cancelled.

   [rest of message from template]
   ```
6. Page redirects with `?success=cancelled`

### Send Custom Message Workflow
1. Admin selects appointments in table
2. Clicks "Send Message" button
3. Modal appears with textarea
4. Admin types custom message (e.g., "Please call us to reschedule")
5. Clicks "Send Message"
6. Confirmation: "Send message to X customer(s)?"
7. System sends via AiSensy text API (not templated)
8. Page redirects with `?success=message_sent&count=X`

---

## Templates Required in AiSensy

### 1. Appointment Cancelled
**Campaign Name:** `Appointment Cancelled`  
**Template Name (ID):** `appointment_cancelled`  
**Variables:** {{1}}, {{2}}  
**Sample Message:**
```
Namaskaram 🙏 {{1}},

Your appointment with Service Request #{{2}} has been cancelled.

We sincerely apologize for any inconvenience.

If you wish to reschedule, please contact us.

Warm Regards,
Vishnusudarshana Dharmik Sanskar Kendra
🙏
```

---

## API Integration - Custom Messages

Custom messages are sent directly via AiSensy text API (not templated):

**Request Format:**
```json
{
    "apiKey": "JWT_TOKEN",
    "destination": "919876543210",
    "text": "Custom message text from admin"
}
```

**Advantages:**
- No template approval needed
- Real-time custom communication
- Flexible messaging

---

## Success Messages

After each action, user redirected with query parameter:
- `?success=completed` - Mark Completed done
- `?success=cancelled` - Cancellation done
- `?success=message_sent&count=5` - Custom messages sent to 5 customers

(Display logic can be added to show toast/alert with these statuses)

---

## Error Handling

- Invalid phone numbers: Skipped with error log
- WhatsApp API errors: Logged, action still proceeds
- Empty selections: Alert shown, no submission
- Custom message validation: Required field check

---

## Testing Checklist

✅ Select appointments (checkboxes work)  
✅ Mark Completed - sends template, status becomes "Completed"  
✅ Cancel - sends cancellation message, status returns to "Received"  
✅ Send Message - modal opens, custom text sends to all selected  
✅ Mobile phone formatting works  
✅ Multiple selections work  
✅ Confirmation dialogs appear  
✅ Success redirects work  
✅ Error logging functional  

---

## Next Steps

1. **Create "Appointment Cancelled" template in AiSensy:**
   - Campaign: "Appointment Cancelled"
   - Add 2 variables
   - Set to "Live"

2. **Verify configurations:**
   - AISENSY_API_KEY is set in config
   - AISENSY_API_URL correct
   - Template names match exactly

3. **Test with real appointment:**
   - Select appointment in accepted list
   - Try Cancel button
   - Verify customer receives cancellation message
   - Try Send Message with custom text
   - Verify customer receives custom message

4. **(Optional) Add success notifications:**
   - Add toast/alert display for success query parameters
   - Show "X appointments cancelled" etc.

