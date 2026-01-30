<!-- Floating Modal Example -->
<div id="floatingModal" style="display:none; position:fixed; bottom:32px; right:32px; z-index:99999; background:#fff; box-shadow:0 4px 24px #80000033; border-radius:16px; min-width:320px; max-width:95vw; width:400px; padding:28px 24px 18px 24px;">
    <div style="font-size:1.15em; color:#800000; font-weight:700; margin-bottom:10px;">Floating Modal</div>
    <div id="floatingModalContent" style="color:#444; margin-bottom:18px;">This is a floating modal. You can customize its content.</div>
    <div style="text-align:right;">
        <button onclick="document.getElementById('floatingModal').style.display='none'" style="background:#800000; color:#fff; padding:8px 22px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Close</button>
    </div>
</div>
<!-- Floating Writing Icon Button -->
<button id="floatingWriteBtn" onclick="openFloatingPopup()" style="position:fixed; bottom:32px; right:32px; z-index:99998; background:#800000; border:none; box-shadow:0 2px 12px #80000033; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s;">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="display:block;" xmlns="http://www.w3.org/2000/svg">
        <path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z" fill="#fff"/>
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
