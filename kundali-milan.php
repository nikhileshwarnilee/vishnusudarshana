<?php include '/header.php'; ?>
<div class="page-container">
<main class="main-content">
  <section class="service-form">
    <h1 class="form-title">Kundali Milan Form</h1>
    <form class="service-form" method="post" action="#">
      <fieldset>
        <legend>Groom Details</legend>
        <div class="form-group">
          <label for="groom_name">Groom Name *</label>
          <input type="text" id="groom_name" name="groom_name" required>
        </div>
        <div class="form-group">
          <label for="groom_dob">Date of Birth *</label>
          <input type="date" id="groom_dob" name="groom_dob" required>
        </div>
        <div class="form-group">
          <label for="groom_tob_h">Time of Birth *</label>
          <div style="display:flex;gap:6px;align-items:center;">
            <select id="groom_tob_h" required style="width:60px;">
              <option value="">HH</option>
              <?php for($h=1;$h<=12;$h++): ?><option value="<?= sprintf('%02d',$h) ?>"><?= sprintf('%02d',$h) ?></option><?php endfor; ?>
            </select> :
            <select id="groom_tob_m" required style="width:60px;">
              <option value="">MM</option>
              <?php for($m=0;$m<60;$m+=1): ?><option value="<?= sprintf('%02d',$m) ?>"><?= sprintf('%02d',$m) ?></option><?php endfor; ?>
            </select>
            <select id="groom_tob_ampm" required style="width:60px;">
              <option value="AM">AM</option>
              <option value="PM">PM</option>
            </select>
            <input type="hidden" id="groom_tob" name="groom_tob" required>
          </div>
        </div>
        <div class="form-group">
          <label for="groom_place">Place of Birth *</label>
          <input type="text" id="groom_place" name="groom_place" required>
        </div>
      </fieldset>
      <fieldset>
        <legend>Bride Details</legend>
        <div class="form-group">
          <label for="bride_name">Bride Name *</label>
          <input type="text" id="bride_name" name="bride_name" required>
        </div>
        <div class="form-group">
          <label for="bride_dob">Date of Birth *</label>
          <input type="date" id="bride_dob" name="bride_dob" required>
        </div>
        <div class="form-group">
          <label for="bride_tob_h">Time of Birth *</label>
          <div style="display:flex;gap:6px;align-items:center;">
            <select id="bride_tob_h" required style="width:60px;">
              <option value="">HH</option>
              <?php for($h=1;$h<=12;$h++): ?><option value="<?= sprintf('%02d',$h) ?>"><?= sprintf('%02d',$h) ?></option><?php endfor; ?>
            </select> :
            <select id="bride_tob_m" required style="width:60px;">
              <option value="">MM</option>
              <?php for($m=0;$m<60;$m+=1): ?><option value="<?= sprintf('%02d',$m) ?>"><?= sprintf('%02d',$m) ?></option><?php endfor; ?>
            </select>
            <select id="bride_tob_ampm" required style="width:60px;">
              <option value="AM">AM</option>
              <option value="PM">PM</option>
            </select>
            <input type="hidden" id="bride_tob" name="bride_tob" required>
          </div>
        </div>
        <div class="form-group">
          <label for="bride_place">Place of Birth *</label>
          <input type="text" id="bride_place" name="bride_place" required>
        </div>
      </fieldset>
      <fieldset>
        <legend>Contact</legend>
        <div class="form-group">
          <label for="mobile">Mobile Number *</label>
          <input type="tel" id="mobile" name="mobile" required pattern="[0-9]{10,15}">
        </div>
        <div class="form-group">
          <label for="notes">Additional Information (Optional)</label>
          <textarea id="notes" name="notes" rows="3"></textarea>
        </div>
      </fieldset>
      <button type="submit" class="submit-btn">Submit for Kundali Milan</button>
    </form>
  </section>
</main>
</div>

<script>
function updateTimeField(prefix) {
  var h = document.getElementById(prefix+'_h').value;
  var m = document.getElementById(prefix+'_m').value;
  var ampm = document.getElementById(prefix+'_ampm').value;
  if(h && m && ampm) {
    document.getElementById(prefix).value = h+":"+m+" "+ampm;
  } else {
    document.getElementById(prefix).value = '';
  }
}
['groom_tob','bride_tob'].forEach(function(prefix){
  ['_h','_m','_ampm'].forEach(function(suffix){
    var el = document.getElementById(prefix+suffix);
    if(el) el.addEventListener('change', function(){ updateTimeField(prefix); });
  });
});
</script>

<?php include '/footer.php'; ?>
