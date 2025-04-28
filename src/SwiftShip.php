<?php

namespace Zfhassaan\SwiftShip;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use JetBrains\PhpStorm\NoReturn;
use Zfhassaan\SwiftShip\Interface\CourierClientInterface;
use Zfhassaan\SwiftShip\Utility\Helper;

/**
 * This package uses the third party apis integration and all the integration process is combined in a single package
 * to remove the time-consuming api integration. Now the package can be used with the builtin functionalities to
 * configure the package and also call the required shipping service.
 */
class SwiftShip implements CourierClientInterface
{

    protected mixed $selectedCourierClient; // Initialize as null

    public static function make(string $courier): CourierClientInterface
    {
        $class = __NAMESPACE__ . "\\Couriers\\" . strtoupper($courier) . "\\" . strtoupper($courier) . "Client";

        if (!class_exists($class)) {
            throw new \Exception("Courier [$courier] is not supported.");
        }

        return (new self())->setCourierClient(new $class());
    }


    /**
     * Set the selected courier service client.
     *
     * @param mixed $courierClient
     * @return mixed
     */
    public function setCourierClient(mixed $courierClient)
    {
        return $this->selectedCourierClient = $courierClient;
    }

    /**
     * Get the selected courier service client.
     */
    public function getSelectedCourierClient()
    {
        return $this->selectedCourierClient;
    }

    /**
     * Get shipment tracking information for a given tracking number.
     * Updates the list where the package has been and shares the updated result.
     *
     * @param string $trackingNumber
     * @return JsonResponse
     */
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        return $this->selectedCourierClient->trackShipment($trackingNumber);
    }

    /**
     * Used to create booking on the service.
     *
     * @param array $data
     * @return JsonResponse
     */
    public function createBooking(array $data): JsonResponse
    {
        return $this->selectedCourierClient->createBooking($data);
    }

    /**
     * Cancel a booking for a given consignment number.
     *
     * This method sends a request to cancel a booked consignment using the provided
     * consignment number. It constructs the request with necessary authentication
     * details and sends it to the designated endpoint. The response is processed
     * to determine the success or failure of the cancellation request.
     * payload:
     *  {
     *      "consignment": "MM795354722,MM795354733,MM795354736,MM795354740"
     *  }
     * @param string $consignmentNumber The consignment number to be canceled.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure
     *                      of the cancellation request. If the consignment number is
     *                      invalid, or if the cancellation fails, a failure response
     *                      is returned. Otherwise, a success response is returned.
     * @throws ConnectionException
     */
    public function cancelBooking(string $consignmentNumber): JsonResponse
    {
        return $this->selectedCourierClient->cancelBooking($consignmentNumber);
    }

    public function ReverseLogistics($request)
    {
        return $this->selectedCourierClient->ReverseLogistics($request);
    }

    public function CountriesList()
    {
        return $this->selectedCourierClient->CountriesList();
    }

    /**
     * @param array $data
     */
    public function downloadLoadSheet(array $data)
    {
        return $this->selectedCourierClient->downloadLoadSheet($data);
    }

    public function OriginsList()
    {
        return $this->selectedCourierClient->OriginsList();
    }

    /**
     * Retrieves a list of cities from the LCS API.
     *
     * This method sends a POST request to the LCS API to fetch all available cities.
     * The response is returned in JSON format.
     *
     * @return JsonResponse A JSON response containing the list of cities.
     */
    public function GetCities(): JsonResponse
    {
        return $this->selectedCourierClient->getCities();
    }

    /**
     * @param array $orders
     */
    public function BatchBookPackets(array $orders): JsonResponse
    {
        return $this->selectedCourierClient->BatchBookPackets($orders);
    }

    /**
     * @param array $cn_numbers
     * @return JsonResponse
     */
    public function GenerateLoadSheet(array $cn_numbers): JsonResponse
    {
        return $this->selectedCourierClient->GenerateLoadSheet($cn_numbers);
    }

    public function createShipper(array $data)
    {
        return $this->selectedCourierClient->createShipper($data);
    }


    /**
     * @param string $fromDate
     * @param string $toDate
     * @return JsonResponse
     */
    public function getBookedPacketLastStatuses(string $fromDate, string $toDate): JsonResponse
    {
        return $this->selectedCourierClient->getBookedPacketLastStatuses($fromDate, $toDate);
    }

    /**
     * @param array $filters
     */
    public function getShipperAdviceList(array $filters): JsonResponse
    {
        return $this->selectedCourierClient->getShipperAdviceList($filters);
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getShipmentDetailsByOrderID(array $filters): mixed
    {
        return $this->selectedCourierClient->getShipmentDetailsByOrderID($filters);
    }

    public function getAllBanks()
    {
        return $this->selectedCourierClient->getAllBanks();
    }

    public function getPaymentDetails(array $cn_numbers)
    {
        return $this->selectedCourierClient->getPaymentDetails($cn_numbers);
    }

    public function getTariffDetails(array $filters)
    {
        return $this->selectedCourierClient->getTariffDetails($filters);
    }

    public function getShippingCharges(array $filters)
    {
        return $this->selectedCourierClient->getShippingCharges($filters);
    }

    public function getShipperDetails(array $filters)
    {
        return $this->selectedCourierClient->getShipperDetails($filters);
    }

    public function getElectronicPOD(array $cn_numbers)
    {
        return $this->selectedCourierClient->getElectronicPOD($cn_numbers);
    }

    public function getShipperAdviceListPaginated(array $filters)
    {
        return $this->selectedCourierClient->getShipperAdviceListPaginated($filters);
    }

    public function getActivityLog(array $filters)
    {
        return $this->selectedCourierClient->getActivityLog($filters);
    }

    public function updateShipperAdvice(array $payload)
    {
        return $this->selectedCourierClient->updateShipperAdvice($payload);
    }
}
