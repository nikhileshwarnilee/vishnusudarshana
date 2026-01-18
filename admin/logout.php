<?php
// admin/logout.php
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
<style>@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}</style>
