<?php

namespace App\Billing;

use App\Models\TopUpPurchase;

interface PaymentGateway
{
    public function createTopUpCheckout(TopUpPurchase $purchase, TopUpPack $pack): CheckoutSession;
}
