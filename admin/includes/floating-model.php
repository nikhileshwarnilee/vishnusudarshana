<!-- Floating Modal Example -->
<div id="floatingModal" style="display:none; position:fixed; bottom:32px; right:32px; z-index:99999; background:#fff; box-shadow:0 4px 24px #80000033; border-radius:16px; min-width:320px; max-width:95vw; width:400px; padding:28px 24px 18px 24px;">
    <div style="font-size:1.15em; color:#800000; font-weight:700; margin-bottom:10px;">Floating Modal</div>
    <div id="floatingModalContent" style="color:#444; margin-bottom:18px;">This is a floating modal. You can customize its content.</div>
    <div style="text-align:right;">
        <button onclick="document.getElementById('floatingModal').style.display='none'" style="background:#800000; color:#fff; padding:8px 22px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Close</button>
    </div>
</div>
<!-- Floating Writing Icon Button -->
<button id="floatingWriteBtn" onclick="openFloatingPopup()" style="position:fixed; bottom:32px; right:32px; z-index:99998; background:#fff; border:none; box-shadow:0 2px 12px #80000033; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:box-shadow 0.2s;">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" style="display:block;" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="12" fill="#800000"/>
        <path d="M7 17.25V19h1.75l7.06-7.06a1.25 1.25 0 0 0 0-1.77l-2.98-2.98a1.25 1.25 0 0 0-1.77 0L7 15.23v2.02zm8.71-8.71a2.25 2.25 0 0 1 0 3.18l-1.06 1.06-3.18-3.18 1.06-1.06a2.25 2.25 0 0 1 3.18 0z" fill="#fff"/>
    </svg>
</button>
<script>
function showFloatingModal(content) {
    document.getElementById('floatingModalContent').innerHTML = content || 'This is a floating modal.';
    document.getElementById('floatingModal').style.display = 'block';
}
function openFloatingPopup() {
    window.open(window.location.origin + '/admin/popup-floating.php', 'FloatingPopup', 'width=600,height=600,scrollbars=yes,resizable=yes');
}
</script>
<!-- Usage: Call showFloatingModal('Your content here') from anywhere -->
