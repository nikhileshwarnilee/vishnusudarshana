<?php

$pageTitle = 'Service Form | Vishnusudarshana';
require_once 'header.php';
$category = $_GET['category'] ?? '';

$categories = [
    'appointment' => 'Book an Appointment',
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

if (!$category || !isset($categories[$category])) {
    echo '<h2>Invalid or missing category</h2>';
    echo '<a href="services.php">&larr; Back to Services</a>';
    require_once 'footer.php';
    exit;
}

// Field definitions
$commonFields = [
    ['label' => 'Full Name', 'name' => 'full_name', 'type' => 'text', 'required' => true],
    ['label' => 'Mobile Number', 'name' => 'mobile', 'type' => 'tel', 'required' => true],
    ['label' => 'Email', 'name' => 'email', 'type' => 'email', 'required' => false],
    ['label' => 'City / Location', 'name' => 'city', 'type' => 'text', 'required' => true],
];

$categoryFields = [
    'appointment' => [
        ['label' => 'Preferred Date', 'name' => 'preferred_date', 'type' => 'date', 'required' => true],
        ['label' => 'Preferred Time Slot', 'name' => 'preferred_time', 'type' => 'text', 'required' => true],
        ['label' => 'Consultation Type', 'name' => 'consultation_type', 'type' => 'select', 'options' => ['Online', 'In-person'], 'required' => true],
        ['label' => 'Topic', 'name' => 'topic', 'type' => 'select', 'options' => ['Astrology', 'Vastu', 'Rituals', 'General Guidance', 'Other'], 'required' => true],
    ],
    'birth-child' => [
        ['label' => 'Child Name', 'name' => 'child_name', 'type' => 'text', 'required' => false],
        ['label' => 'Date of Birth', 'name' => 'dob', 'type' => 'date', 'required' => true],
        ['label' => 'Time of Birth', 'name' => 'tob', 'type' => 'time', 'required' => true],
        ['label' => 'Place of Birth', 'name' => 'pob', 'type' => 'text', 'required' => true],
        ['label' => 'Gender', 'name' => 'gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Other'], 'required' => true],
    ],
    'marriage-matching' => [
        ['label' => 'Men Date of Birth', 'name' => 'boy_dob', 'type' => 'date', 'required' => true],
        ['label' => 'Men Time of Birth', 'name' => 'boy_tob', 'type' => 'time', 'required' => true],
        ['label' => 'Men Place of Birth', 'name' => 'boy_pob', 'type' => 'text', 'required' => true],
        ['label' => 'Women Date of Birth', 'name' => 'girl_dob', 'type' => 'date', 'required' => true],
        ['label' => 'Women Time of Birth', 'name' => 'girl_tob', 'type' => 'time', 'required' => true],
        ['label' => 'Women Place of Birth', 'name' => 'girl_pob', 'type' => 'text', 'required' => true],
    ],
    'astrology-consultation' => [
        ['label' => 'Date of Birth', 'name' => 'dob', 'type' => 'date', 'required' => false],
        ['label' => 'Time of Birth', 'name' => 'tob', 'type' => 'time', 'required' => false],
        ['label' => 'Place of Birth', 'name' => 'pob', 'type' => 'text', 'required' => false],
    ],
    'muhurat-event' => [
        ['label' => 'Event Type', 'name' => 'event_type', 'type' => 'select', 'options' => ['Marriage', 'Griha Pravesh', 'Vehicle Purchase', 'Business Start', 'Other'], 'required' => true],
        ['label' => 'Preferred Date or Month', 'name' => 'preferred_date', 'type' => 'text', 'required' => false],
        ['label' => 'City', 'name' => 'event_city', 'type' => 'text', 'required' => false],
    ],
    'pooja-vastu-enquiry' => [
        ['label' => 'Service Topic', 'name' => 'service_topic', 'type' => 'select', 'options' => ['Pooja & Ritual', 'Shanti & Dosh Nivaran', 'Yagya & Havan', 'Vastu Consultation', 'Other'], 'required' => true],
        ['label' => 'Problem Description', 'name' => 'problem_desc', 'type' => 'textarea', 'required' => false],
        ['label' => 'City', 'name' => 'enquiry_city', 'type' => 'text', 'required' => false],
    ],
];

// Add Describe your questions textarea to all categories
foreach ($categoryFields as $catKey => &$fields) {
    $fields[] = [
        'label' => 'Additional Questions / Details',
        'name' => 'questions',
        'type' => 'textarea',
        'required' => false,
    ];
}
unset($fields);

// No PHP redirect logic here. Form will post directly to service-review.php.
?>
<main class="main-content" style="background-color:var(--cream-bg);">
    <h1 class="form-title"><?php echo htmlspecialchars($categories[$category]); ?> — Service Form</h1>
    <?php if ($category === 'appointment'): ?>
    <form method="post" action="service-review.php?category=<?php echo urlencode($category); ?>" class="service-form" autocomplete="off" id="appointmentForm">
        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
        <section class="form-section">
            <h2 class="form-section-title">Appointment Details</h2>
            <div class="form-group">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Mobile Number <span class="req">*</span></label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <select name="country_code" id="country_code_select" style="width:auto;">
                        <option value="+91" selected>+91 India</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="text" name="custom_country_code" id="custom_country_code_input" placeholder="+__" style="width:60px;display:none;">
                    <input type="tel" name="mobile" required style="flex:1;">
                </div>
            </div>
            <div class="form-group">
                <label>Email (optional)</label>
                <input type="email" name="email">
            </div>
            <div class="form-group">
                <label>Appointment Type <span class="req">*</span></label>
                <select name="appointment_type" required>
                    <option value="Online" selected>Online</option>
                </select>
            </div>
            <div class="form-group">
                <label>Preferred Date <span class="req">*</span></label>
                <input type="date" name="preferred_date" id="preferred_date_input" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Preferred Time Window</label>
                <input type="text" name="preferred_time">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3"></textarea>
            </div>
        </section>
        <button type="submit" class="form-submit-btn">Continue</button>
    </form>
    <script>
    // Set min date to today in IST and prevent past date selection
    document.addEventListener('DOMContentLoaded', function() {
        var dateInput = document.getElementById('preferred_date_input');
        if (dateInput) {
            function getISTToday() {
                var now = new Date();
                var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                var istOffset = 5.5 * 60 * 60 * 1000;
                var istNow = new Date(utc + istOffset);
                var yyyy = istNow.getFullYear();
                var mm = String(istNow.getMonth() + 1).padStart(2, '0');
                var dd = String(istNow.getDate()).padStart(2, '0');
                return yyyy + '-' + mm + '-' + dd;
            }
            var todayStr = getISTToday();
            dateInput.setAttribute('min', todayStr);
            dateInput.addEventListener('input', function() {
                if (dateInput.value < todayStr) {
                    dateInput.value = todayStr;
                }
            });
            // Prevent manual bypass on submit
            var form = document.getElementById('appointmentForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (dateInput.value < todayStr) {
                        alert('Please select today or a future date.');
                        dateInput.value = todayStr;
                        dateInput.focus();
                        e.preventDefault();
                        return false;
                    }
                });
            }
        }
    });
    </script>
    <?php else: ?>
    <form method="post" action="service-review.php?category=<?php echo urlencode($category); ?>" class="service-form" autocomplete="off">
        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
        <section class="form-section">
            <h2 class="form-section-title">Your Details</h2>
            <?php foreach ($commonFields as $field): ?>
                <div class="form-group">
                    <label><?php echo $field['label']; ?><?php if ($field['required']): ?><span class="req">*</span><?php endif; ?></label>
                    <?php if ($field['name'] === 'mobile'): ?>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <select name="country_code" class="country_code_select" style="width:auto;">
                                <option value="+91" selected>+91 India</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" name="custom_country_code" class="custom_country_code_input" placeholder="+__" style="width:60px;display:none;">
                            <input type="tel" name="mobile" required style="flex:1;">
                        </div>
                    <?php else: ?>
                        <input type="<?php echo $field['type']; ?>" name="<?php echo $field['name']; ?>" <?php if ($field['required']): ?>required<?php endif; ?> >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="form-section">
            <h2 class="form-section-title">Service Information</h2>
            <?php foreach ($categoryFields[$category] as $field): ?>
                <div class="form-group" id="group-<?php echo $field['name']; ?>">
                    <label><?php echo $field['label']; ?><?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?></label>
                    <?php if ($field['type'] === 'select'): ?>
                        <select name="<?php echo $field['name']; ?>" id="<?php echo $field['name']; ?>" <?php if (!empty($field['required'])): ?>required<?php endif; ?> onchange="handleSelectChange('<?php echo $field['name']; ?>')">
                            <option value="">-- Select --</option>
                            <?php foreach ($field['options'] as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($category === 'muhurat-event' && $field['name'] === 'event_type'): ?>
                        <input type="text" name="other_event_type" id="other_event_type" placeholder="Please specify other event type" style="display:none;margin-top:6px;" />
                        <?php endif; ?>
                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea name="<?php echo $field['name']; ?>" rows="3" <?php if (!empty($field['required'])): ?>required<?php endif; ?>></textarea>
                    <?php else: ?>
                    <?php if ($field['name'] === 'mobile'): ?>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <select name="country_code" class="country_code_select" style="width:auto;">
                                <option value="+91" selected>+91 India</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" name="custom_country_code" class="custom_country_code_input" placeholder="+__" style="width:60px;display:none;" pattern="^\\+\\d{1,4}$">
                            <input type="tel" name="mobile" required style="flex:1;">
                        </div>
                    <?php else: ?>
                        <?php if ($field['name'] === 'mobile'): ?>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <select name="country_code" class="country_code_select" style="width:auto;">
                                    <option value="+91" selected>+91 India</option>
                                    <option value="other">Other</option>
                                </select>
                                <input type="text" name="custom_country_code" class="custom_country_code_input" placeholder="+__" style="width:60px;display:none;" pattern="^\\+\\d{1,4}$">
                                <input type="tel" name="mobile" required style="flex:1;">
                            </div>
                        <?php else: ?>
                            <input type="<?php echo $field['type']; ?>" name="<?php echo $field['name']; ?>" <?php if (!empty($field['required'])): ?>required<?php endif; ?> >
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <button type="submit" class="form-submit-btn">Continue</button>
    </form>
    <?php endif; ?>
    <a href="services.php" class="form-back-link">&larr; Back to Services</a>
</main>
<?php require_once 'footer.php'; ?>
<script>
// Country code dropdown logic for all mobile fields
document.addEventListener('DOMContentLoaded', function() {
    function handleCountryCode(select, customInput) {
        select.addEventListener('change', function() {
            if (select.value === 'other') {
                customInput.style.display = 'inline-block';
                customInput.required = true;
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        });
    }
    // For main form
    var mainSelect = document.getElementById('country_code_select');
    var mainCustom = document.getElementById('custom_country_code_input');
    if (mainSelect && mainCustom) {
        handleCountryCode(mainSelect, mainCustom);
    }
    // For dynamic/other forms (category fields)
    var selects = document.querySelectorAll('.country_code_select');
    var customs = document.querySelectorAll('.custom_country_code_input');
    selects.forEach(function(sel, idx) {
        if (customs[idx]) handleCountryCode(sel, customs[idx]);
    });
});
function handleSelectChange(field) {
    if (field === 'event_type') {
        var select = document.getElementById('event_type');
        var otherInput = document.getElementById('other_event_type');
        if (select && otherInput) {
            if (select.value === 'Other') {
                otherInput.style.display = 'block';
                otherInput.required = true;
            } else {
                otherInput.style.display = 'none';
                otherInput.required = false;
            }
        }
    }
}
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important;}
body { background: #FFD700 !important; }
.main-content { max-width: 480px; margin: 0 auto; background: #FFD700; border-radius: 18px; box-shadow: 0 4px 24px #e0bebe33; padding: 18px 12px 28px 12px; }
.form-title { font-size: 1.18em; font-weight: bold; margin-bottom: 18px; text-align: center; color: #800000; }
.form-section { margin-bottom: 18px; }
.form-section-title { font-size: 1.05em; color: #800000; margin-bottom: 10px; font-weight: 600; }
.form-group { margin-bottom: 12px; }
label { display: block; font-size: 0.98em; color: #333; margin-bottom: 4px; }
input, select, textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #e0bebe;
    border-radius: 8px;
    font-size: 0.98em;
    background: #f9eaea;
    box-sizing: border-box;
    margin-bottom: 2px;
}
textarea { resize: vertical; }
.req { color: #b30000; margin-left: 2px; }
.form-submit-btn {
    width: 100%;
    background: #800000;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 0;
    font-size: 1.08em;
    font-weight: 600;
    margin-top: 10px;
    cursor: pointer;
    box-shadow: 0 2px 8px #80000022;
    transition: background 0.15s;
}
.form-submit-btn:active { background: #5a0000; }
.form-back-link {
    display: block;
    text-align: center;
    margin-top: 18px;
    color: #800000;
    font-size: 0.98em;
    text-decoration: none;
}
@media (max-width: 700px) {
    .main-content { padding: 8px 2px 16px 2px; border-radius: 0; }
}
</style>
