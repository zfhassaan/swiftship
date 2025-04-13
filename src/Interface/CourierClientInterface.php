<?php

namespace Zfhassaan\SwiftShip\Interface;


use Illuminate\Http\Request;

interface CourierClientInterface
{
    public function trackShipment(string $trackingNumber);
    public function createBooking(array $data);
    public function cancelBooking(string $consignmentNumber);
    public function ReverseLogistics($request);
    public function CountriesList();
    public function OriginsList();
    public function GetCities();
    public function BatchBookPackets(array $orders);
}
