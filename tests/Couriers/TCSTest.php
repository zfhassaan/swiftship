<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Request;
use zfhassaan\swiftship\Couriers\TCS\TCSClient;
use zfhassaan\swiftship\SwiftShip;

class TCSTest
{
    public function testTCSBooking()
    {
        $request = Request::create('/create-booking', 'POST', [
            'userName' =>'',
            'password' => '',
            'costCenterCode' => '01',
            'consigneeName' => 'Test Name',
            'consigneeAddress' => 'Test Address',
            'consigneeMobNo' => '03142437698',
            "consigneeEmail" => "test@tcs.com.pk",
            "originCityName" => "Lahore",
            "destinationCityName" => "Lahore",
            "weight" => 10,
            "pieces" => 1, // quantity
            "codAmount" => "100",
            "customerReferenceNo" => "1235465498",
            "services" => "O",
            "productDetails" => "product Description",
            "fragile" => "Yes",
            "remarks" => "FRAGILE! Handle with care, call before delivery",
            "insuranceValue" => 1
        ]);

        $swiftShip = new SwiftShip();
        $courier = $swiftShip->setCourierClient(new TCSClient());
        $response =  $swiftShip->createBooking($request->all());
        $response->assertJson();
        $result = $response->assertJsonFragment(['code' => '0200']);
        return $result;
    }

    public function testIndex()
    {
        // Create a mock request with a consignment number
        $request = Request::create('/track-shipment', 'GET', ['consignmentNo' => 'ABC123']);

        $swiftShip = new SwiftShip();
        $courier = $swiftShip->setCourierClient(new TCSClient());
        $response = $swiftShip->trackShipment($request->consignmentNo);

        // Assert that the response is valid JSON
        $response->assertJson();

        // Add more specific assertions based on your expected response data
        // Example: Assert that the response contains a specific key or value
        return $response->assertJsonFragment(['status' => 'delivered']);
    }
}
