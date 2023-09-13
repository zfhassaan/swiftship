<?php

namespace zfhassaan\swiftship;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use JetBrains\PhpStorm\NoReturn;
use zfhassaan\swiftship\Interface\CourierClientInterface;

/**
 * This package uses the third party apis integration and all the integration process is combined in a single package
 * to remove the time-consuming api integration. Now the package can be used with the builtin functionalities to
 * configure the package and also call the required shipping service.
 */
class SwiftShip
{

    protected mixed $selectedCourierClient; // Initialize as null

    /**
     * Set the selected courier service client.
     *
     * @param mixed $courierClient
     * @return string
     */
    public function setCourierClient(mixed $courierClient)
    {
        return $this->selectedCourierClient = $courierClient;
    }

    /**
     * Get the selected courier service client.
     *
     * @return CourierClientInterface
     */
    public function getSelectedCourierClient(): CourierClientInterface
    {
        return $this->selectedCourierClient;
    }

    /**
     * Get shipment tracking information for a given tracking number.
     *
     * @param string $trackingNumber
     * @return Response
     */
    public function trackShipment(string $trackingNumber): Response
    {
        return $this->selectedCourierClient->trackShipment($trackingNumber);
    }

    /**
     * Used to create booking on the service.
     *
     * @param array $request
     * @return mixed
     */
    public function createBooking(array $request): mixed
    {
        return $this->selectedCourierClient->createBooking($request);
    }
}
