<?php
// admin/logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
<style>@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}</style>


