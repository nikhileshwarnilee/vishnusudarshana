# WhatsApp Business API Integration Guide

## ✅ Integration Status: **COMPLETE**

The WhatsApp Business API is fully integrated and centralized for both admin panel and website.

---

## 📁 File Structure

```
vishnusudarshana/
├── config/
│   └── whatsapp_config.php          # Central configuration
├── helpers/
│   └── send_whatsapp.php            # Core WhatsApp functions
├── logs/
│   └── whatsapp.log                 # Activity log (auto-created)
└── [various PHP files using WhatsApp]
```

---

## 🔧 Configuration

### File: `config/whatsapp_config.php`

**Key Settings:**
- `WHATSAPP_PHONE_NUMBER_ID` - Your Meta Phone Number ID
- `WHATSAPP_ACCESS_TOKEN` - Your WhatsApp API token
- `WHATSAPP_TEST_MODE` - Set to `true` for testing (logs instead of sending)
- `WHATSAPP_TEMPLATES` - All approved template names
- `WHATSAPP_AUTO_NOTIFICATIONS` - Enable/disable auto notifications

**To change credentials:**
```php
define('WHATSAPP_PHONE_NUMBER_ID', 'YOUR_PHONE_NUMBER_ID');
define('WHATSAPP_ACCESS_TOKEN', 'YOUR_ACCESS_TOKEN');
```

---

## 📝 Current Implementation

### Automatic Notifications (Already Working)

1. **Service Requests:**
   - ✅ Service Received (when user submits request)
   - ✅ Service Accepted (when admin accepts)
   - ✅ Service In Progress (status change)
   - ✅ Service Completed (status change)
   - ✅ File Uploaded (when admin uploads file)

2. **Appointments:**
   - ✅ Appointment Accepted (when admin accepts)
   - ✅ Appointment Completed (status change)
   - ✅ Appointment Missed (auto-rollback)

3. **Payments:**
   - ✅ Payment Received (payment-success.php)

### Files Using WhatsApp:
- `admin/services/view.php` - Status updates & file uploads
- `admin/services/appointments.php` - Appointment acceptance
- `admin/services/accepted-appointments.php` - Completion
- `admin/services/auto_rollback_appointments.php` - Missed appointments
- `payment-success.php` - Payment confirmation

---

## 🚀 How to Use

### Method 1: Simple Send (Current Usage)
```php
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

sendWhatsAppMessage(
    '919999999999',              // Phone number
    'service_accepted_notification',  // Template name
    [
        'name' => 'John Doe',
        'tracking_code' => 'VDSK12345'
    ]
);
```

### Method 2: Event-Based (New - Recommended)
```php
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

$result = sendWhatsAppNotification('service_accepted', [
    'mobile' => '919999999999',
    'customer_name' => 'John Doe',
    'tracking_id' => 'VDSK12345'
]);

if ($result['success']) {
    echo "Message sent: " . $result['data']['message_id'];
} else {
    echo "Error: " . $result['message'];
}
```

### Method 3: Direct Template Send
```php
$result = sendWhatsAppMessage(
    '919999999999',
    'APPOINTMENT_REMINDER',
    [
        'name' => 'John Doe',
        'tracking_code' => 'VDSK12345',
        'appointment_date' => '2026-01-25',
        'appointment_time' => '10:00 AM'
    ]
);
```

---

## 📋 Available Events for `sendWhatsAppNotification()`

| Event Type | Auto-Sends | Description |
|------------|-----------|-------------|
| `service_received` | ✅ Yes | When service request is created |
| `service_accepted` | ✅ Yes | When admin accepts service |
| `service_completed` | ✅ Yes | When service is completed |
| `file_uploaded` | ✅ Yes | When admin uploads file |
| `appointment_accepted` | ✅ Yes | When appointment is accepted |
| `appointment_missed` | ✅ Yes | When appointment is auto-rolled back |
| `payment_received` | ✅ Yes | When payment is successful |

---

## 🎯 Next Steps - Where to Add Notifications

Tell me where you want to add WhatsApp notifications. Here are common scenarios:

### 1. **Service Request Scenarios**
- [ ] When service status changes to "In Progress"
- [ ] When service is cancelled
- [ ] When admin adds internal notes
- [ ] Daily/Weekly service summary to customer

### 2. **Appointment Scenarios**
- [ ] Appointment reminder (24 hours before)
- [ ] Appointment reminder (1 hour before)
- [ ] When appointment is rescheduled
- [ ] When appointment is cancelled
- [ ] Appointment follow-up after completion

### 3. **Payment Scenarios**
- [ ] Payment reminder for pending invoices
- [ ] Invoice generated notification
- [ ] Payment receipt with details
- [ ] Due date approaching reminder

### 4. **CIF (Customer Information File)**
- [ ] New enquiry added notification
- [ ] Follow-up reminder
- [ ] Birthday/Anniversary wishes
- [ ] Custom notifications

### 5. **Admin Notifications**
- [ ] New service request alert to admin
- [ ] Payment received alert to admin
- [ ] Daily summary to admin
- [ ] Low inventory alerts (if applicable)

### 6. **Manual Notifications**
- [ ] Send custom message from service view page
- [ ] Bulk notifications to customers
- [ ] Announcement broadcasts

---

## 🔄 How to Add New Notification

**Example: Add "Service Cancelled" notification**

### Step 1: Add Template to Config
Edit `config/whatsapp_config.php`:
```php
define('WHATSAPP_TEMPLATES', [
    // ...existing templates...
    'SERVICE_CANCELLED' => 'service_cancelled_template',
]);

define('WHATSAPP_TEMPLATE_VARIABLES', [
    // ...existing...
    'SERVICE_CANCELLED' => ['name', 'tracking_code', 'reason'],
]);
```

### Step 2: Add Event Handler
In `helpers/send_whatsapp.php`, add case in `sendWhatsAppNotification()`:
```php
case 'service_cancelled':
    return sendWhatsAppMessage(
        $data['mobile'],
        'SERVICE_CANCELLED',
        [
            'name' => $data['customer_name'],
            'tracking_code' => $data['tracking_id'],
            'reason' => $data['cancellation_reason'] ?? 'No reason provided'
        ]
    );
```

### Step 3: Trigger in Your Code
In `admin/services/view.php` or wherever status is updated:
```php
if ($newStatus === 'Cancelled') {
    require_once __DIR__ . '/../../helpers/send_whatsapp.php';
    sendWhatsAppNotification('service_cancelled', [
        'mobile' => $serviceData['mobile'],
        'customer_name' => $serviceData['customer_name'],
        'tracking_id' => $serviceData['tracking_id'],
        'cancellation_reason' => $_POST['reason'] ?? ''
    ]);
}
```

---

## 🧪 Testing

### Enable Test Mode
Edit `config/whatsapp_config.php`:
```php
define('WHATSAPP_TEST_MODE', true);
```

When test mode is enabled:
- No actual messages are sent
- All notifications are logged to console and `logs/whatsapp.log`
- Check logs: `tail -f logs/whatsapp.log`

### Disable Test Mode (Production)
```php
define('WHATSAPP_TEST_MODE', false);
```

---

## 📊 Monitoring

### View Logs
```bash
# View recent logs
tail -f logs/whatsapp.log

# Search for specific phone number
grep "919999999999" logs/whatsapp.log

# Check errors
grep "ERROR" logs/whatsapp.log
```

### Log Format
```
[2026-01-19 10:30:45] TO: 919999999999 | TEMPLATE: service_accepted_notification | STATUS: SUCCESS | DETAILS: {"message_id":"wamid.xxx"}
```

---

## 🔐 Security Best Practices

1. **Never commit credentials to Git**
   - Add `config/whatsapp_config.php` to `.gitignore`
   - Use environment variables in production

2. **Rotate tokens regularly**
   - Update `WHATSAPP_ACCESS_TOKEN` every 60 days

3. **Validate phone numbers**
   - Always use `formatWhatsAppPhone()` before sending

4. **Rate limiting**
   - WhatsApp has rate limits (check Meta docs)
   - Current implementation includes 30s timeout

---

## 🆘 Troubleshooting

### Message not sending?
1. Check `logs/whatsapp.log` for errors
2. Verify credentials in `config/whatsapp_config.php`
3. Ensure template is approved in Meta Business Manager
4. Check phone number format (should be 919999999999)

### Template not found?
1. Verify template name in Meta Business Manager
2. Update `WHATSAPP_TEMPLATES` array in config
3. Ensure template is approved and active

### Variable mismatch?
1. Check template variables in Meta Business Manager
2. Update `WHATSAPP_TEMPLATE_VARIABLES` in config
3. Ensure variables are passed in correct order

---

## 📞 Support

For template creation/approval:
- Go to Meta Business Manager → WhatsApp → Message Templates
- Create template with required variables
- Wait for approval (usually 24-48 hours)

---

## ✨ Ready to Proceed!

**The integration is complete and centralized. Tell me:**
1. Which notification scenario you want to implement first
2. When it should trigger (automatic or manual)
3. What information should be included in the message

I'll help you implement it step by step!
