<?php

namespace zfhassaan\swiftship\Interface;


use Illuminate\Http\Request;

interface CourierClientInterface
{
    public function trackShipment($trackingNumber);
    public function createBooking(array $data);
}
