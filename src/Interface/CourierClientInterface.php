<?php

namespace zfhassaan\swiftship\Interface;


use Illuminate\Http\Request;

interface CourierClientInterface
{
    public function trackShipment($trackingNumber);
    public function createBooking(array $data);
    public function cancelBooking($consignmentNumber);
    public function ReverseLogistics($request);
    public function CountriesList();
    public function OriginsList();
    public function GetCities();
}
