<?php
// src/Razorpay.php (Razorpay PHP SDK loader stub)
// This file is required to load the SDK classes manually
spl_autoload_register(function($class) {
    $prefix = 'Razorpay\\Api\\';
    $base_dir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});
