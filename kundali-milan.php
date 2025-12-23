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
          <label for="groom_tob">Time of Birth *</label>
          <input type="time" id="groom_tob" name="groom_tob" required>
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
          <label for="bride_tob">Time of Birth *</label>
          <input type="time" id="bride_tob" name="bride_tob" required>
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
</style>

<?php include '/footer.php'; ?>
