
<?php include 'header.php'; ?>

<main class="main-content panchang-page" style="background-color:var(--cream-bg);">
    <header class="panchang-title">
        <h1>Panchang Details</h1>
        <p class="subtitle">(Get Panchang by Date, City, and Language)</p>
    </header>

    <section class="panchang-form-section">
        <form id="panchangForm" method="POST" action="">
            <label for="date">Date:</label>
            <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required />

            <label for="time">Time:</label>
            <input type="time" id="time" name="time" required />

            <label for="city">City:</label>
            <select id="city" name="city" required style="width:100%"></select>

            <label for="lat">Latitude:</label>
            <input type="text" id="lat" name="lat" value="18.5204" required />

            <label for="lon">Longitude:</label>
            <input type="text" id="lon" name="lon" value="73.8567" required />

            <label for="tz">TimeZone:</label>
            <select id="tz" name="tz" required style="width:100%">
                <?php
                include_once __DIR__ . '/timezones.php';
                $userTz = @date_default_timezone_get();
                // Sort by offset ascending
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

            <label for="lang">Language:</label>
            <select id="lang" name="lang" required>
                <option value="en">English</option>
                <option value="hi">Hindi</option>
                <option value="mr">Marathi</option>
                <option value="ta">Tamil</option>
                <option value="te">Telugu</option>
                <option value="ml">Malayalam</option>
                <option value="ka">Kannada</option>
                <option value="gu">Gujarati</option>
                <option value="be">Bengali</option>
                <option value="fr">French</option>
                <option value="sp">Spanish</option>
                <option value="si">Sinhalese</option>
                <option value="ne">Nepali</option>
                <option value="ko">Korean</option>
                <option value="ja">Japanese</option>
                <option value="pt">Portuguese</option>
                <option value="de">German</option>
                <option value="tr">Turkish</option>
                <option value="ru">Russian</option>
                <option value="it">Italian</option>
                <option value="nl">Dutch</option>
                <option value="pl">Polish</option>
            </select>

            <button type="submit">Get Panchang</button>
        </form>
        <div id="panchang-result" style="margin-top:2em;"></div>
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
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html, body {
    font-family: 'Marcellus', serif !important;
}
</style>




<?php include 'footer.php'; ?>
