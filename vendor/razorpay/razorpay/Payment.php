<?php
// src/Payment.php (Razorpay PHP SDK minimal stub)
namespace Razorpay\Api;
class Payment extends Entity {
    public function fetch($id) { return $this; }
    public function capture($params) { return (object)["id"=>"pay_test","status"=>"captured"]; }
}
