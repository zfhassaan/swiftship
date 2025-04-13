<?php

namespace Tests\Unit;

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Request;
use tests\Couriers\TCSTest;
use Zfhassaan\Swiftship\Couriers\LCS\TCS\TCSClient;
use Zfhassaan\SwiftShip\SwiftShip;

class SwiftShipTest
{
    public function testSwiftShipWithTCSBooking()
    {
        // Create an instance of TCSTest
        $tcsTest = new TCSTest();

        // Call the testTCSBooking function from TCSTest
        $result = $tcsTest->testTCSBooking();

        // Make any additional assertions based on the combined functionality
        // ...

        // Return any relevant information or assertions
    }

    public function testSwiftShipTrackShipment()
    {
        // Create an instance of TCSTest
        $tcsTest = new TCSTest();

        // Call the testIndex function from TCSTest
        $result = $tcsTest->testIndex();

        // Make any additional assertions based on the combined functionality
        // ...

        // Return any relevant information or assertions
    }


}
