<?php

namespace Zfhassaan\Swiftship\Couriers\TCS;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Zfhassaan\SwiftShip\Interface\CourierClientInterface;
use Illuminate\Support\Facades\Validator;
use Zfhassaan\SwiftShip\Utility\Helper;

/*
 * Overview:
 * To test the complete set of product APIs and gain a better understanding of TCS API integration, visit:
 * https://ociconnect.tcscourier.com/ecom/index.html
 *
 * Available APIs:
 * Check the list of available APIs at: https://ociconnect.tcscourier.com/ecom/index.html
 *
 * Sandbox Access:
 * Below is the link for UAT and production portals:
 * https://devconnect.tcscourier.com/ecom/index.html
 *
 * Production Access:
 * If you need production access, you must proceed with the successful UAT phase.
 * Please note that production access approval/setup will take at least three working days.
 * https://ociconnect.tcscourier.com/ecom/index.html
 */
class TCSClient implements CourierClientInterface
{
    protected mixed $url;
    protected string $client_id;
    protected string $username;
    protected string $password;
    protected string $trackingUrl;
    public function __construct()
    {
        $this->client_id = config('swiftship.tcs_client_id','');
        $this->url = config('swiftship.tcs_base_url','');
        $this->username = config('swiftship.tcs_username','');
        $this->password = config('swiftship.tcs_password','');
        $this->trackingUrl = config('swiftship.tcs_tracking_url','');
    }

    /**
     * @param $trackingNumber
     * @return PromiseInterface|JsonResponse|\Illuminate\Http\Client\Response
     */
    public function trackShipment($trackingNumber): PromiseInterface|JsonResponse|\Illuminate\Http\Client\Response
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->get($this->trackingUrl.'?'.http_build_query([
                    'ConsignmentNo' => $trackingNumber
                ]));
            return (new Helper())->success('Success', $result);
        } catch(\Exception $e) {
            return (new Helper())->failure($e->getMessage());
        }
    }

    /**
     * To order a COD shipment and get a reference number from TCS Pakistan Service.
     * The service can return the following Responses:
     *
     * Code: 0200 => The Operation was successful
     * Code: 0401 => Unauthorized
     * Code: 0404 => Not Found
     * Code: 0405 => Method Not Allowed
     * Code: 0406 => Not Acceptable
     * Code: 0429 => Too Many Requests
     * Code: 0500 => Internal Server Error
     * Code: 0503 => Service Unavailable
     *
     * @param array $data
     * @return string|JsonResponse
     */
    public function createBooking(array $data): string|JsonResponse
    {
        try {
            $validator = Validator::make($data,
                [
                    'consigneeName' => 'required|string',
                    'consigneeAddress' => 'required|string',
                    'consigneeMobNo' => 'required|string|min:10|max:11',
                    'consigneeEmail' => 'required|string|email',
                    'originCityName' => 'required|string',
                    'destinationCityName' => 'required|string',
                    'weight' => 'required|integer',
                    'pieces' => 'required|integer|min:1',
                    'codAmount' => 'required|string',
                    'customerReferenceNo' => 'required|string',
                    'services' => 'required|string',
                    'productDetails' => 'required|string',
                ],
                [
                    'consigneeName.required' => 'Consignee Name is required',
                    'consigneeName.string' => 'Consignee Name should be string',
                    'consigneeAddress.required' => 'Consignee Address is required',
                    'consigneeAddress.string' => 'Consignee Address should be string',
                    'consigneeMobNo.required' => 'Consignee Mobile Number is required',
                    'consigneeMobNo.string' => 'Consignee Mobile Number should be string.',
                    'consigneeEmail.required' => 'Consignee Email is required',
                    'consigneeEmail.string' => 'Consignee Email should be of type string',
                    'consigneeEmail.email' => 'Consignee Email should be a valid email address',
                    'originCityName.required' => 'origin city or office city is required.',
                    'originCityName.string' => 'Origin City name should be string. ',
                    'destinationCityName.required' => 'Destination City is required',
                    'destinationCityName.string' => 'Destination City should be in string ',
                    'weight.required' => 'Weight is required',
                    'weight.integer' => 'Weight should be in integer format. ',
                    'pieces.required' => 'Pieces or Quantity is required ',
                    'pieces.integer' => 'Pieces or Quantity should be a valid integer',
                    'pieces.min' => 'Pieces or Quantity should be a non negative number',
                    'codAmount.required' => 'COD Amount is required ',
                    'codAmount.string' => 'COD Amount should be in string',
                    'customerReferenceNo.required' => 'Customer Reference Number is required',
                    'customerReferenceNo.string' => 'Customer Reference Number should be a valid string',
                    'services.required' => 'services is a required field. ',
                    'services.string' => 'services should be in string',
                    'productDetails.required' => 'Product Title or Description is required',
                    'productDetails.string' => 'Product Title or Description should be in string format.'
                ]);

            if ($validator->fails()) {
                return (new Helper())->failure('Validation Error', $validator->errors()->toArray());
            }

            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->post($this->url.'create-order',$data);

            return Helper::success('Booking Response: ', $result->json());
        } catch(\Exception $e)
        {
            return (new Helper())->failure($e->getMessage(), $e->getCode());
        }
    }

    /**
     * To cancel C.O.D shipments by your reference number.
     *
     * @param $consignmentNumber
     * @return PromiseInterface|JsonResponse|\Illuminate\Http\Client\Response
     */
    public function cancelBooking($consignmentNumber): PromiseInterface|JsonResponse|\Illuminate\Http\Client\Response
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->put($this->url.'cancel-order');

            return (new Helper())->success('success',$result);
        } catch(\Exception $e) {
            return (new Helper())->failure($e->getMessage(), $e->getCode());
        }
    }

    public function ReverseLogistics($request): JsonResponse
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->post($this->url.'reverse-logistics');

            (new Helper())->LogData('swiftship', 'Reverse Logistics', $result);

            return (new Helper())->success('Success',$result);
        } catch(\Exception $e)
        {
            return (new Helper())->failure($e->getMessage());
        }
    }

    /**
     * This API is used to get TCS shipment country codes list with description.
     * @return JsonResponse
     */
    public function CountriesList(): JsonResponse
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->get($this->url.'countries');

            return (new Helper())->success('Success',$result->json());
        } catch(\Exception $e)
        {
            return (new Helper())->failure($e->getMessage());
        }
    }

    /**
     * This API is used to get TCS shipment origin/station codes list with description.
     *
     * @return JsonResponse
     */
    public function OriginsList(): JsonResponse
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->get($this->url.'origins');

            return (new Helper())->success('success', $result);
        } catch(\Exception $e)
        {
            return (new Helper())->failure($e->getMessage(), $e->getCode());
        }
    }

    public function GetCities(): JsonResponse
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->get($this->url.'cities');

            (new Helper())->LogData('swiftship', 'Cities List', $result);

            return (new Helper())->success('Success',  $result);
        } catch(\Exception $e)
        {
            return (new Helper())->failure($e->getMessage(), $e->getCode());
        }
    }

    public function getSelectedCourierClient()
    {
        // TODO: Implement getSelectedCourierClient() method.
    }

    /**
     * @param array $orders
     * @return mixed
     */
    public function BatchBookPackets(array $orders)
    {
        // TODO: Implement BatchBookPackets() method.
    }

    /**
     * @param array $cn_numbers
     * @return mixed
     */
    public function GenerateLoadSheet(array $cn_numbers)
    {
        // TODO: Implement GenerateLoadSheet() method.
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function createShipper(array $data)
    {
        // TODO: Implement createShipper() method.
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function downloadLoadSheet(array $data)
    {
        // TODO: Implement downloadLoadSheet() method.
    }

    /**
     * @param string $fromDate
     * @param string $toDate
     * @return mixed
     */
    public function getBookedPacketLastStatuses(string $fromDate, string $toDate)
    {
        // TODO: Implement getBookedPacketLastStatuses() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getShipperAdviceList(array $filters)
    {
        // TODO: Implement getShipperAdviceList() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getShipmentDetailsByOrderID(array $filters)
    {
        // TODO: Implement getShipmentDetailsByOrderID() method.
    }

    /**
     * @return mixed
     */
    public function getAllBanks()
    {
        // TODO: Implement getAllBanks() method.
    }

    /**
     * @param array $cn_numbers
     * @return mixed
     */
    public function getPaymentDetails(array $cn_numbers)
    {
        // TODO: Implement getPaymentDetails() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getTariffDetails(array $filters)
    {
        // TODO: Implement getTariffDetails() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getShippingCharges(array $filters)
    {
        // TODO: Implement getShippingCharges() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getShipperDetails(array $filters)
    {
        // TODO: Implement getShipperDetails() method.
    }

    /**
     * @param array $cn_numbers
     * @return mixed
     */
    public function getElectronicPOD(array $cn_numbers)
    {
        // TODO: Implement getElectronicPOD() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getShipperAdviceListPaginated(array $filters)
    {
        // TODO: Implement getShipperAdviceListPaginated() method.
    }

    /**
     * @param array $filters
     * @return mixed
     */
    public function getActivityLog(array $filters)
    {
        // TODO: Implement getActivityLog() method.
    }

    /**
     * @param array $payload
     * @return mixed
     */
    public function updateShipperAdvice(array $payload)
    {
        // TODO: Implement updateShipperAdvice() method.
    }
}
