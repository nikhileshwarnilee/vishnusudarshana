# Offline Service Request WhatsApp Template

## Overview
When an admin creates an offline service request via the **Offline Service Request** form (`/admin/services/offlineservicerequest.php`), an automated WhatsApp message is sent to the customer with:
- Customer name
- Service category
- Selected products/services list
- Tracking ID
- "Track Service" button linking to tracking page

---

## Template Details

### Template Name
- **Internal ID**: `OFFLINE_SERVICE_REQUEST_RECEIVED`
- **AiSensy Campaign Name**: `Offline Service Request Received`
- **Template Code**: `office_service_request`

### Template Variables (4 Parameters)
1. `{{1}}` - Customer Name
2. `{{2}}` - Service Category
3. `{{3}}` - Products/Services List
4. `{{4}}` - Tracking ID

### Button
- **Button Name**: Track Service
- **Button Type**: URL
- **Button URL**: `https://vishnusudarshana.com/track.php?id={{4}}`

---

## Message Template (Final Version)

### AiSensy Message Format

**Title**: Offline Service Request Received

**Message Body**:
```
Namaskaram 🙏 {{1}},

Your service request has been successfully received! ✅

📋 Details:
• Service Category: {{2}}
• Service Requested: {{3}}
• Payment Status: Unpaid

We will review your request and update you with your requested service shortly.

Thank you for choosing us!

Warm Regards,
VishnuSudarshana Dharmik Sanskar Kendra
```

### Button Configuration
- **Button Label**: Track Service
- **Button URL**: `https://vishnusudarshana.com/track.php?id={{4}}`

### Example Output
```
Namaskaram 🙏 Raj Kumar,

Your service request has been successfully received! ✅

📋 Details:
• Service Category: Birth & Child Services
• Service Requested: Janma Patrika, Name Suggestion
• Payment Status: Unpaid

We will review your request and update you with your requested service shortly.

Thank you for choosing us!

Warm Regards,
VishnuSudarshana Dharmik Sanskar Kendra

[Track Service Button] → https://vishnusudarshana.com/track.php?id=SR20250120ABCD
```

---

## Implementation Details

### Configuration Files Modified

#### 1. `/config/whatsapp_config.php`
- Added template: `'OFFLINE_SERVICE_REQUEST_RECEIVED' => 'Offline Service Request Received'`
- Added variables: `'OFFLINE_SERVICE_REQUEST_RECEIVED' => ['name', 'category', 'products_list', 'tracking_id']`
- Added button config:
  ```php
  'OFFLINE_SERVICE_REQUEST_RECEIVED' => [
      ['type' => 'url', 'param' => 'tracking_id']  // Track Service button
  ]
  ```

#### 2. `/admin/services/offlineservicerequest.php`
- Imports `send_whatsapp.php` helper
- Sends WhatsApp after service request insertion
- Passes 4 parameters: name, category, products_list, tracking_id
- Handles errors gracefully

### Data Flow

1. Admin fills **Offline Service Request** form with:
   - Customer name
   - Mobile number
   - Service category
   - Service details
   - Products/services selection

2. Form submitted → Service request saved to database with unique tracking ID

3. WhatsApp notification sent with:
   - `{{1}}` = Customer Name
   - `{{2}}` = Service Category (from database)
   - `{{3}}` = Products list with quantities
   - `{{4}}` = Generated Tracking ID

4. Customer receives message with "Track Service" button

---

## AiSensy Setup Required

### Create Campaign in AiSensy Dashboard

**Step 1: Campaign Details**
- Campaign Type: Template-based
- Campaign Name: `Offline Service Request Received` (exact match)
- Template Type: Text with URL Button
- Language: English

**Step 2: Message Template**
```
Namaskaram 🙏 {{1}},

Your service request has been successfully received! ✅

📋 Details:
• Service Category: {{2}}
• Service Requested: {{3}}
• Payment Status: Unpaid

We will review your request and update you with your requested service shortly.

Thank you for choosing us!

Warm Regards,
VishnuSudarshana Dharmik Sanskar Kendra
```

**Step 3: Add 4 Parameters**
- {{1}} for Customer Name
- {{2}} for Service Category
- {{3}} for Products/Services List
- {{4}} for Tracking ID

**Step 4: Add URL Button**
- Button Label: `Track Service`
- Button URL: `https://vishnusudarshana.com/track.php?id={{4}}`
- Apply parameter {{4}} to the button URL

---

## Testing

### Test Mode
If `WHATSAPP_TEST_MODE` is enabled in `/config/whatsapp_config.php`:
- Messages logged instead of sent
- Check: `/logs/whatsapp.log`

### Production Mode
- Messages sent via AiSensy API
- Verify API key in `/config/whatsapp_config.php`
- Monitor: `/logs/whatsapp.log`

### Manual Test
```php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/send_whatsapp.php';

sendWhatsAppMessage(
    '919876543210',
    'OFFLINE_SERVICE_REQUEST_RECEIVED',
    [
        'name' => 'Raj Kumar',
        'category' => 'Birth & Child Services',
        'products_list' => 'Janma Patrika, Name Suggestion',
        'tracking_id' => 'SR20250120ABCD'
    ]
);
```

---

## Error Handling

- WhatsApp send failures logged to `/logs/whatsapp.log`
- Does not block service request creation
- Network, API, and validation errors all logged with timestamps

---

## Variable Reference

| Variable | Source | Example |
|----------|--------|---------|
| `name` {{1}} | Form field: Full Name | "Raj Kumar" |
| `category` {{2}} | Service Category dropdown | "Birth & Child Services" |
| `products_list` {{3}} | Selected products with quantities | "Janma Patrika, Name Suggestion" |
| `tracking_id` {{4}} | Auto-generated (SR + Date + Random) | "SR20250120ABCD" |

---

## Features

✅ Personalized greeting (Namaskaram 🙏)  
✅ Clear confirmation message  
✅ Service details display  
✅ "Track Service" button with direct tracking link  
✅ Professional sign-off  
✅ Automatic sending on form submission  
✅ Graceful error handling  
✅ Full logging capability  
✅ Test and production modes supported  
