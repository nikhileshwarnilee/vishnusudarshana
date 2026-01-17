<?php include 'header.php'; ?>

<main class="main-content panchang-page" style="background-color:var(--cream-bg);">
        <?php
        // Load latest Panchang JSON from DB
        require_once __DIR__ . '/config/db.php';
        $latestPanchang = null;
        try {
            $stmt = $pdo->query("SELECT panchang_json FROM panchang ORDER BY request_date DESC, id DESC LIMIT 1");
            $row = $stmt->fetch();
            if ($row && $row['panchang_json']) {
                $latestPanchang = $row['panchang_json'];
            }
        } catch (Exception $e) {
            $latestPanchang = null;
        }
        ?>
        <style>
        .panchang-form-section {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            background: rgba(255,255,255,0.7);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 24px 0 0 0;
            min-height: unset;
            width: 100%;
        }
        .panchang-form {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 18px 18px 10px 18px;
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0 auto;
        }
        .panchang-form-row {
            display: flex;
            flex-direction: row;
            gap: 18px;
            align-items: flex-end;
            justify-content: flex-start;
            width: 100%;
        }
        .panchang-form .form-group {
            display: flex;
            flex-direction: column;
            min-width: 140px;
            flex: 1 1 0;
        }
        .panchang-form label {
            font-weight: 600;
            margin-bottom: 7px;
            color: #2d2d2d;
            letter-spacing: 0.01em;
            font-size: 0.98rem;
        }
        .panchang-form input[type="date"],
        .panchang-form input[type="time"],
        .panchang-form select {
            padding: 8px 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background: #f9f9f9;
            transition: border 0.2s;
            outline: none;
            min-width: 120px;
        }
        .panchang-form input[type="date"]:focus,
        .panchang-form input[type="time"]:focus,
        .panchang-form select:focus {
            border: 1.5px solid #ffd700;
            background: #fffbe6;
        }
        /* Unified dropdown style for city, timezone, and language */
        .panchang-form select {
            background: #f9f9f9;
            border: 1.5px solid #e0e0e0;
            color: #2d2d2d;
            font-weight: 500;
            box-shadow: none;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 1rem;
            min-width: 120px;
            transition: border 0.2s, background 0.2s;
        }
        /* New modern style for city dropdown */
        .panchang-form select#city {
            background: linear-gradient(90deg, #f0f4ff 0%, #e0e7ff 100%);
            border: 2px solid #4f8cff;
            color: #1a237e;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(79,140,255,0.08);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.05rem;
            min-width: 140px;
            transition: border 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .panchang-form select#city:focus {
            border: 2px solid #1a73e8;
            background: #e3f0ff;
            box-shadow: 0 4px 16px rgba(79,140,255,0.13);
        }
        .panchang-form select:focus {
            border: 1.5px solid #ffd700;
            background-color: #fffbe6;
        }
        .panchang-form .btn-primary {
            background: linear-gradient(90deg, #ffd700 0%, #ffec80 100%);
            color: #2d2d2d;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            transition: background 0.2s, box-shadow 0.2s;
            margin-top: 18px;
        }
        .panchang-form .btn-primary:hover {
            background: linear-gradient(90deg, #ffec80 0%, #ffd700 100%);
            box-shadow: 0 4px 16px rgba(255,215,0,0.13);
        }
        /* Responsive styles */
        @media (max-width: 900px) {
            .panchang-form-section {
                padding: 12px 0 0 0;
            }
            .panchang-form {
                max-width: 98vw;
                padding: 12px 4vw 8px 4vw;
            }
            .panchang-form-row {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
        }
        @media (max-width: 600px) {
            .panchang-form-section {
                padding: 6px 0 0 0;
            }
            .panchang-form {
                max-width: 100vw;
                padding: 8px 2vw 6px 2vw;
                border-radius: 10px;
            }
            .panchang-form label {
                font-size: 1rem;
            }
            .panchang-form input[type="date"],
            .panchang-form input[type="time"],
            .panchang-form select {
                font-size: 0.98rem;
                padding: 8px 8px;
            }
            .panchang-form select#city {
                font-size: 1rem;
                padding: 9px 10px;
            }
            .panchang-form .btn-primary {
                font-size: 1rem;
                padding: 10px 10px;
            }
        }
        </style>
    <div class="panchang-form-toggle-wrap" style="margin-bottom:1.2em;">
        <button id="panchangFormToggle" type="button" style="background:linear-gradient(90deg,#ffd700 0%,#ffec80 100%);color:#2d2d2d;font-weight:bold;border:none;border-radius:8px;padding:12px 24px;font-size:1.1rem;cursor:pointer;box-shadow:0 2px 8px rgba(255,215,0,0.08);transition:background 0.2s,box-shadow 0.2s;outline:none;width:100%;text-align:left;display:flex;align-items:center;justify-content:space-between;">
            <span>Get Panchang by Date, City, and Language</span>
            <span id="panchangFormToggleIcon" style="font-size:1.3em;transition:transform 0.2s;">&#x25BC;</span>
        </button>
    </div>
    <form id="panchangForm" class="panchang-form" method="POST" action="" style="display:none;">
                    <script>
                    // Collapsible Panchang Form
                    document.addEventListener('DOMContentLoaded', function() {
                        var toggleBtn = document.getElementById('panchangFormToggle');
                        var form = document.getElementById('panchangForm');
                        var icon = document.getElementById('panchangFormToggleIcon');
                        if(toggleBtn && form && icon) {
                            toggleBtn.addEventListener('click', function() {
                                if(form.style.display === 'none' || form.style.display === '') {
                                    form.style.display = 'flex';
                                    icon.style.transform = 'rotate(180deg)';
                                } else {
                                    form.style.display = 'none';
                                    icon.style.transform = 'rotate(0deg)';
                                }
                            });
                        }
                    });
                    </script>
            <input type="hidden" id="lat" name="lat" value="18.5204" required />
            <input type="hidden" id="lon" name="lon" value="73.8567" required />
            <div class="panchang-form-row">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="time">Time</label>
                    <input type="time" id="time" name="time" required />
                </div>
                <div class="form-group">
                    <label for="city">City</label>
                    <select id="city" name="city" required style="width:100%"></select>
                </div>
            </div>
            <div class="panchang-form-row">
                <div class="form-group">
                    <label for="tz">TimeZone</label>
                    <select id="tz" name="tz" required style="width:100%">
                        <?php
                        include_once __DIR__ . '/timezones.php';
                        $userTz = 'Asia/Kolkata';
                        usort($timezones, function($a, $b) {
                            return $a['offset'] <=> $b['offset'];
                        });
                        foreach ($timezones as $tz) {
                            $selected = ($tz['name'] === $userTz) ? 'selected' : '';
                            $label = $tz['name'] . ' (UTC ' . $tz['offset_str'] . ')';
                            echo "<option value=\"{$tz['name']}\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="lang">Language</label>
                    <select id="lang" name="lang" required>
                    <option value="en">English (English)</option>
                    <option value="hi">Hindi (हिन्दी)</option>
                    <option value="mr">Marathi (मराठी)</option>
                    <option value="gu">Gujarati (ગુજરાતી)</option>
                    <option value="ka">Kannada (ಕನ್ನಡ)</option>
                    <option value="te">Telugu (తెలుగు)</option>
                    </select>
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Get Panchang</button>
                </div>
            </div>
        </form>
        <div class="todays-panchang-title-row" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;margin:1.5em 0 0.5em 0;">
            <div id="todays-panchang-title" style="font-size:1.35em;font-weight:bold;color:#800000;display:none;"></div>
            <div class="panchang-lang-static-select" style="min-width:160px;margin-top:0;">
                <select style="background-color: #f9f9f9; padding:7px 12px;border-radius:8px;border:1.5px solid #666666;font-size:1em;color:#b60a0a;">
                    <option value="en">English (English)</option>
                    <option value="hi">Hindi (हिन्दी)</option>
                    <option value="mr">Marathi (मराठी)</option>
                    <option value="gu">Gujarati (ગુજરાતી)</option>
                    <option value="ka">Kannada (ಕನ್ನಡ)</option>
                    <option value="te">Telugu (తెలుగు)</option>

                </select>
            </div>
        </div>
        <div id="panchang-result" style="margin-top:2em;"></div>

        <?php
// ...existing code...
        if ($latestPanchang) {
            echo '<script>document.addEventListener("DOMContentLoaded", function() {';
            echo 'var resultDiv = document.getElementById("panchang-result");';
            echo 'var titleDiv = document.getElementById("todays-panchang-title");'
                . 'var dateVal = "";'
                . 'if(json && json.date) {'
                . '  if (/^\\d{4}-\\d{2}-\\d{2}$/.test(json.date)) {'
                . '    dateVal = new Date(json.date).toDateString();'
                . '  } else if (/^\\d{2}\\/\\d{2}\\/\\d{4}$/.test(json.date)) {'
                . '    var parts = json.date.split("/");'
                . '    dateVal = new Date(parts[2], parts[1]-1, parts[0]).toDateString();'
                . '  } else {'
                . '    dateVal = new Date(json.date).toDateString();'
                . '  }'
                . '  if(dateVal === "Invalid Date") dateVal = "";'
                . '}'
                . 'if(!dateVal) {'
                . '  var today = new Date();'
                . '  dateVal = today.toDateString();'
                . '}'
                . 'if(titleDiv) {'
                . '  titleDiv.style.display = "block";'
                . '  titleDiv.textContent = "Todays Panchang : " + dateVal;'
                . '}';
            echo 'var json = ' . $latestPanchang . ";\n";
            echo 'function flatten(obj, prefix) {';
            echo '  var out = {};
                for (var k in obj) {
                    if (!obj.hasOwnProperty(k)) continue;
                    var v = obj[k];
                    var key = prefix ? prefix + "." + k : k;
                    if (v && typeof v === "object" && !Array.isArray(v)) {
                        var flat = flatten(v, key);
                        for (var fk in flat) out[fk] = flat[fk];
                    } else {
                        out[key] = v;
                    }
                }
                return out;
            };';
            echo 'var flat = flatten(json, "");';
            echo 'function formatTitle(key) {';
            echo '  return key.replace(/^Response[ ._]?/i, "").replace(/[._]/g, " ").replace(/\\b\\w/g, function(l) { return l.toUpperCase(); });';
            echo '};';
            echo 'var cleanedFlat = {};';
            echo 'for (var k in flat) { cleanedFlat[formatTitle(k)] = flat[k]; }';
            // Removed the Title/Value header row here:
            echo 'var html = "<style>"
                + ".panchang-table {"
                + "  width: auto; max-width: 100%; background: #fffdfa; border-radius: 14px; box-shadow: 0 4px 24px #0002; border-collapse: separate; border-spacing: 0; margin-bottom: 1.5em; }"
                + ".panchang-table tr:not(.cat-row):hover { background: #fff7e6; transition: background 0.2s; }"
                + ".panchang-table td { padding: 12px 20px; font-size: 1.04em; color: #2d2d2d; border-bottom: 1px solid #f3e6c4; vertical-align: top; }"
                + ".panchang-table tr:last-child td { border-bottom: none; }"
                + ".panchang-table .panchang-key { font-weight: 600; color: #4f3a1a; letter-spacing: 0.01em; }"
                + ".panchang-table .panchang-value { color: #2d2d2d; }"
                + ".panchang-table .date-row td { background: #f7f7d7; color: #7c5a00; font-weight: 600; border-radius: 10px 10px 0 0; }"
                + ".panchang-table .cat-row td { background: #800000; color: #fff; font-weight: bold; text-align: left; padding: 13px 20px; font-size: 1.08em; letter-spacing: 0.5px; border-radius: 0; }"
                + "</style>";
            html += "<table class=\"panchang-table\"><tbody>";';
            echo 'var dateVal = (json && json.date) ? new Date(json.date).toDateString() : "";';
            echo 'if(dateVal && dateVal !== "Invalid Date") {';
            echo '    html += "<tr class=\"date-row\"><td>Date</td><td>" + dateVal + "</td></tr>";';
            echo '}';
            echo 'var categories = ["Tithi", "Nakshatra", "Yoga", "Karana", "Sun", "Moon", "Rahukalam", "Gulika", "Yamaganda", "Abhijit", "Hora", "Auspicious", "Inauspicious", "Muhurta", "Panchang", "Choghadiya", "Festival", "Other"];';
            echo 'var grouped = {};';
            echo 'for (var k in cleanedFlat) {';
            echo '    if (k === "Status" || k === "Remaining Api Calls" || k === "Day") continue;';
            echo '    var found = false;';
            echo '    for (var i = 0; i < categories.length; i++) {';
            echo '        var cat = categories[i];';
            echo '        if (k.toLowerCase().startsWith(cat.toLowerCase())) {';
            echo '            if (!grouped[cat]) grouped[cat] = [];';
            echo '            grouped[cat].push({ key: k, value: cleanedFlat[k] });';
            echo '            found = true;';
            echo '            break;';
            echo '        }';
            echo '    }';
            echo '    if (!found) {';
            echo '        if (!grouped["Other"]) grouped["Other"] = [];';
            echo '        grouped["Other"].push({ key: k, value: cleanedFlat[k] });';
            echo '    }';
            echo '}';
            echo 'for (var i = 0; i < categories.length; i++) {';
            echo '    var cat = categories[i];';
            echo '    if (grouped[cat] && grouped[cat].length > 0) {';
            echo '        html += "<tr class=\"cat-row\"><td colspan=\"2\">" + cat + "</td></tr>";';
            echo '        for (var j = 0; j < grouped[cat].length; j++) {';
            echo '            var row = grouped[cat][j];';
            echo '            html += "<tr><td class=\"panchang-key\">" + row.key + "</td><td class=\"panchang-value\">" + (Array.isArray(row.value) ? JSON.stringify(row.value) : row.value) + "</td></tr>";';
            echo '        }';
            echo '    }';
            echo '}';
            echo 'resultDiv.innerHTML = html;';
            echo '});</script>';
        }
        // ...existing code...
        ?>

        <!-- jQuery and Select2 for searchable dropdown -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
        // Replace with your RapidAPI key
        const GEODB_API_KEY = 'a97887c188msh3ecd5008d8b0aeep16818ajsn8280d180ea53';
        $(document).ready(function() {
            // AJAX form submit for Panchang
            $('#panchangForm').on('submit', function(e) {
                e.preventDefault();
                var formDataArr = $(this).serializeArray();
                var formDataObj = {};
                formDataArr.forEach(function(item) {
                    if(item.name !== 'city') formDataObj[item.name] = item.value;
                });
                // If timezone is empty or not selected, set default to Asia/Kolkata
                if (!formDataObj.tz || formDataObj.tz === '') {
                    formDataObj.tz = 'Asia/Kolkata';
                }
                // Format date as DD/MM/YYYY
                if(formDataObj.date) {
                    var d = new Date(formDataObj.date);
                    var day = (d.getDate()).toString().padStart(2, '0');
                    var month = (d.getMonth()+1).toString().padStart(2, '0');
                    var year = d.getFullYear();
                    formDataObj.date = day + '/' + month + '/' + year;
                }
                // Convert timezone name to offset in 0.5 jumps using Intl API
                if(formDataObj.tz) {
                    try {
                        var tzName = formDataObj.tz;
                        // Get the offset in minutes for the selected timezone at the selected date
                        var dateForTz = formDataObj.date ? formDataObj.date.split('/').reverse().join('-') : undefined;
                        var refDate = dateForTz ? new Date(dateForTz + 'T12:00:00Z') : new Date();
                        // Use DateTimeFormat to get offset in minutes
                        var dtf = new Intl.DateTimeFormat('en-US', { timeZone: tzName, timeZoneName: 'short' });
                        var parts = dtf.formatToParts(refDate);
                        var tzPart = parts.find(function(p){return p.type==='timeZoneName'});
                        var match = tzPart && tzPart.value.match(/GMT([+-]\d{1,2})(?::(\d{2}))?/);
                        var offset = 0;
                        if(match) {
                            offset = parseInt(match[1],10);
                            if(match[2]) offset += parseInt(match[2],10)/60 * (offset<0?-1:1);
                        }
                        // Round to nearest 0.5
                        var offsetHalf = Math.round(offset * 2) / 2;
                        formDataObj.tz = offsetHalf;
                    } catch(e) {
                        formDataObj.tz = '';
                    }
                }
                alert('Values being sent to Panchang API:\n' + JSON.stringify(formDataObj, null, 2));
                $('#panchang-result').html('<em>Loading...</em>');
                $.ajax({
                    url: 'scripts/panchang3rdparty.php',
                    method: 'POST',
                    data: formDataObj,
                    dataType: 'json',
                    success: function(data) {
                        if (data.error) {
                            $('#panchang-result').html('<span style="color:red">'+data.error+'</span>');
                        } else {
                            $('#panchang-result').html('<pre style="white-space:pre-wrap;">'+JSON.stringify(data, null, 2)+'</pre>');
                        }
                    },
                    error: function(xhr) {
                        $('#panchang-result').html('<span style="color:red">Error fetching Panchang data.</span>');
                    }
                });
            });
            // Set time and timezone from user's system
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            // Always use 24-hour format
            var timeValue = hours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0');
            $('#time').val(timeValue);
            var tzOffset = -now.getTimezoneOffset() / 60;
            var tzRounded = Math.round(tzOffset * 4) / 4;
            $('#tz').val(tzRounded);

            $('#city').select2({
                placeholder: 'Search for a city',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: 'https://wft-geo-db.p.rapidapi.com/v1/geo/cities',
                    dataType: 'json',
                    delay: 250,
                    beforeSend: function (jqXHR) {
                        jqXHR.setRequestHeader('X-RapidAPI-Key', GEODB_API_KEY);
                        jqXHR.setRequestHeader('X-RapidAPI-Host', 'wft-geo-db.p.rapidapi.com');
                    },
                    data: function(params) {
                        return {
                            namePrefix: params.term,
                            limit: 10
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(city) {
                                return {
                                    id: city.id,
                                    text: city.city + ', ' + city.country,
                                    lat: city.latitude,
                                    lon: city.longitude,
                                    tz: city.timezone
                                };
                            })
                        };
                    },
                    cache: true,
                    error: function(xhr, status, error) {
                        if (error !== 'abort') {
                            alert('City search error: ' + error);
                        }
                    }
                }
            });
            // Set Solapur as default city on load

            // Set Solapur as default city and fetch its lat/lon from GeoDB API
            var solapurOption = new Option('Solapur, India', 'Solapur, India', true, true);
            $('#city').append(solapurOption).trigger('change');
            // Fetch lat/lon for Solapur, India
            $.ajax({
                url: 'https://wft-geo-db.p.rapidapi.com/v1/geo/cities',
                method: 'GET',
                data: { namePrefix: 'Solapur', countryIds: 'IN', limit: 1 },
                dataType: 'json',
                headers: {
                    'X-RapidAPI-Key': GEODB_API_KEY,
                    'X-RapidAPI-Host': 'wft-geo-db.p.rapidapi.com'
                },
                success: function(data) {
                    if (data.data && data.data.length > 0) {
                        var city = data.data[0];
                        $('#lat').val(city.latitude);
                        $('#lon').val(city.longitude);
                    }
                }
            });

            $('#city').on('select2:select', function(e) {
                var data = e.params.data;
                $('#lat').val(data.lat);
                $('#lon').val(data.lon);
                // Do NOT update timezone field
            });
            $('#city').on('select2:clear', function(e) {
                $('#lat').val('');
                $('#lon').val('');
                $('#tz').val('');
            });
            // Enable search for timezone dropdown
            $('#tz').select2({
                placeholder: 'Select a timezone',
                allowClear: true,
                matcher: function(params, data) {
                    // If there are no search terms, return all data
                    if ($.trim(params.term) === '') {
                        return data;
                    }
                    if (typeof data.text === 'undefined') {
                        return null;
                    }
                    // Case-insensitive contains match
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }
                    // Return `null` if the term should not be displayed
                    return null;
                }
            });
        });
        </script>
    </section>

    <!-- Panchang API response will be shown here after form submission -->
</main>

        <style>
        .todays-panchang-title-row { flex-wrap: wrap; }
        .todays-panchang-title-row > #todays-panchang-title { flex: 1 1 auto; }
        .panchang-lang-static-select { flex: 0 0 auto; margin-left: 1.5em; }
        .panchang-lang-static-select select {
            background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
            border: 2px solid #800000;
            color: #800000;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.05rem;
            min-width: 140px;
            transition: border 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .panchang-lang-static-select select:focus {
            border: 2px solid #ffd700;
            background: #fffbe6;
            outline: none;
        }
        @media (max-width: 700px) {
            .todays-panchang-title-row { flex-direction: column; align-items: flex-start; }
            .panchang-lang-static-select { margin-left: 0; margin-top: 0.7em; width: 100%; }
            .panchang-lang-static-select select { width: 100%; }
        }
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html, body {
    font-family: 'Marcellus', serif !important;
}
</style>




<?php include 'footer.php'; ?>
