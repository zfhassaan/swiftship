<?php

namespace Zfhassaan\Swiftship\Couriers\LCS;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Zfhassaan\SwiftShip\Interface\CourierClientInterface;
use Zfhassaan\SwiftShip\Utility\Helper;

class LCSClient implements CourierClientInterface
{
    protected string $url;
    protected string $mode;
    protected string $password;
    protected string $api;
    protected string $format;

    /**
     * LCSClient constructor.
     *
     * Initializes the LCSClient with configuration settings.
     * Sets the mode, URL, password, and API key based on the configuration.
     * Defaults to 'sandbox' mode if no mode is specified in the configuration.
     */
    public function __construct(){
        $this->mode = config('swiftship.lcs.lcs_mode') ?? 'sandbox';
        $this->url = $this->mode == 'sandbox' ? config('swiftship.lcs.lcs_staging_url') : config('swiftship.lcs.lcs_production_url');
        $this->password = config('swiftship.lcs.lcs_password');
        $this->api = config('swiftship.lcs.lcs_api_key');
        $this->format = '/format/json/';
    }

    /**
     * Retrieves a list of cities from the LCS API.
     *
     * This method sends a POST request to the LCS API to fetch all available cities.
     * The response is returned in JSON format.
     *
     * @param string $format The format in which the cities data should be returned. Default is '/format/json/'.
     * @return JsonResponse A JSON response containing the list of cities.
     */
    public function GetCities(string $format = '/format/json/'): JsonResponse
    {
        $cities_api = 'getAllCities'.$format;
        $response = Http::post($this->url . $cities_api, [
            'api_key' => $this->api,
            'api_password' => $this->password
        ]);
        $data = $response->json();
        $statusCode = $response->status();

        if ($statusCode !== 200) {
            $response = [
                'message' => 'Service Down or Invalid API Key',
                'code' => $statusCode,
            ];
            return Helper::failure('Service Response', $response, Response::HTTP_BAD_REQUEST);
        }
        return Helper::success('Cities Response', $data['city_list'], Response::HTTP_OK);
    }

    /**
     * {
     * "booked_packet_weight": 1500,
     * "booked_packet_no_piece": 2,
     * "booked_packet_collect_amount": 300,
     * "booked_packet_order_id": "INV-999",
     * "origin_city": "self",
     * "destination_city": 123,
     * "shipment_id": 321,
     * "shipment_name_eng": "My Company",
     * "shipment_email": "info@myco.com",
     * "shipment_phone": "03001234567",
     * "shipment_address": "1 Company Rd",
     * "consignment_name_eng": "Test Order",
     * "consignment_phone": "03211234567",
     * "consignment_address": "456 Consignee St",
     * "special_instructions": "Deliver by evening", // remarks
     *
     * "return_address": "",
     * "return_city": null,
     * "is_vpc": 0
     * }
     *
     * $response = $swiftShip->createBooking($payload);
     *
     * @throws ConnectionException
     */
    public function createBooking(array $data, $format = '/format/json/'): JsonResponse
    {
        $validate = $this->validateCreateBooking($data);
        if($validate['status'] === false){
            return Helper::failure($validate['message'], $validate['error']);
        }

        $createBooking = $this->url . 'bookPacket' . $format;

        // Inject credentials
        $data = array_merge([
            'api_key' => $this->api,
            'api_password' => $this->password,
        ], $data);

        // If 'custom_data' is an array, encode it
        if (isset($data['custom_data']) && is_array($data['custom_data'])) {
            $data['custom_data'] = json_encode($data['custom_data']);
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post($createBooking, $data);

        if($response->json()['status'] == 1) {
            return Helper::success('Booking Response', [
                'track_number' => $response->json()['track_number'],
                'slip_link' => $response->json()['slip_link']
            ], Response::HTTP_CREATED);
        }
        return Helper::failure('Unable to Create Booking',$response->json(), Response::HTTP_BAD_REQUEST);
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
        if(!$consignmentNumber) {
            return Helper::failure('Invalid Consignment Number');
        }

        $cancel_booking = $this->url.'cancelBookedPackets'.$this->format;
        $data = [
            'api_key' => $this->api,
            'api_password' => $this->password,
            'cn_numbers' => $consignmentNumber
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post($cancel_booking, $data)->json();

        if($response['status'] == 0) {
            return Helper::failure('Unable to Cancel Booking',$response['error'], Response::HTTP_BAD_REQUEST);
        }
        return Helper::success('Booking Response', $response);
    }

    /**
     * @throws ConnectionException
     */
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        if(!$trackingNumber) {
            return Helper::failure('Invalid Tracking Number');
        }

        $trackShipment = $this->url.'trackBookedPacket'.$this->format;

        $data = [
            'api_key' => $this->api,
            'api_password' => $this->password,
            'track_numbers' => $trackingNumber
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($trackShipment,$data)->json();


        if($response['status'] == 0 || $response == null) {
            return Helper::failure('Service Down. Error Tracking Shipment',$response ?? '', Response::HTTP_BAD_REQUEST);
        }
        return Helper::success('Tracking Response', $response['packet_list']);
    }



    public function ReverseLogistics($request)
    {
        // TODO: Implement ReverseLogistics() method.
    }

    public function CountriesList(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Only Pakistan is supported for LCS Delivery.',
            'data' => [
                [
                    'short_code' => 'PK',
                    'code' => '92',
                    'name' => 'Pakistan',
                    'currency' => 'PKR'
                ]
            ]
        ]);
    }

    public function OriginsList()
    {
        // TODO: Implement OriginsList() method.
    }



    public function getSelectedCourierClient()
    {
        // TODO: Implement getSelectedCourierClient() method.
    }

    /**
     * Validates the data for creating a booking.
     *
     * This method checks the provided data against a set of rules to ensure
     * that all required fields are present and correctly formatted. If the
     * validation fails, it returns an array containing the status, message,
     * and the first error encountered. If the validation passes, it returns true.
     *
     * @param array $data The data to be validated for creating a booking.
     *
     * @return true|array Returns true if validation passes, or an array with
     *                    'status', 'message', and 'error' keys if validation fails.
     */
    private function validateCreateBooking(array $data): true|array
    {
        $rules = [
            'booked_packet_weight' => 'required|integer|min:1',
            'booked_packet_no_piece' => 'required|integer|min:1',
            'booked_packet_collect_amount' => 'required|numeric|min:0',
            'booked_packet_order_id' => 'required|string',
            'origin_city' => 'required',
            'destination_city' => 'required',
            'shipment_id' => 'required|integer',
            'shipment_name_eng' => 'required|string',
            'shipment_email' => 'required|email',
            'shipment_phone' => 'required|string',
            'shipment_address' => 'required|string',
            'consignment_name_eng' => 'required|string',
            'consignment_phone' => 'required|string',
            'consignment_address' => 'required|string',
            'special_instructions' => 'nullable|string',
            'shipment_type' => 'nullable|string',
            'custom_data' => 'nullable|array',
            'return_address' => 'nullable|string',
            'return_city' => 'nullable|integer',
            'is_vpc' => 'nullable|integer|in:0,1',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => $validator->errors()->all(),
                'error' => $validator->errors()->first(),
            ];
        }

        // Fetch the list of cities
        $citiesResponse = self::GetCities(); // returns JsonResponse
        $citiesData = $citiesResponse->getOriginalContent();

        if (!isset($citiesData['data']) || !is_array($citiesData['data'])) {
            return [
                'status' => false,
                'message' => 'Unable to fetch cities data',
                'error' => 'Cities data fetch error',
            ];
        }

        $originCity = collect($citiesData['data'])->firstWhere('id', $data['origin_city']);


        if (!$originCity || !$originCity['allow_as_origin']) {
            return [
                'status' => false,
                'message' => 'Invalid origin city',
                'error' => 'Selected origin city is not allowed for shipping',
            ];
        }

        $destinationCity = collect($citiesData['data'])->firstWhere('id', $data['destination_city']);

        if (!$destinationCity || !$destinationCity['allow_as_destination']) {
            return [
                'status' => false,
                'message' => 'Invalid destination city',
                'error' => 'Selected destination city is not allowed for delivery',
            ];
        }

        return [
            'status' => true,
            'message' => 'valid',
            'error' => ''
        ];
    }


    /**
     * @throws ConnectionException
     */
    public function BatchBookPackets(array $orders): JsonResponse
    {
        $packets = collect($orders)->map(function ($order, $index) {
            $this->validatePacket($order, $index);
            return $this->mapPacket($order);
        })->all();

        $url = $this->url.'batchBookPacket'.$this->format;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'api_key' => $this->api,
            'api_password' => $this->password,
            'packets' => $packets
        ]);


        if($response->failed()) return Helper::failure('Batch Book Packet Error', $response->json() ?? '');

        return Helper::success('Booking Response', $response->json());
    }

    protected function validatePacket(array $order, int $index): array
    {
        $rules = [
            'weight' => 'required|numeric|min:1',
            'collect_amount' => 'required|numeric|min:0',
            'order_id' => 'required|string',
            'destination_city' => 'required|numeric',
            'consignee_name' => 'required|string',
            'consignee_phone' => 'required|string',
            'consignee_address' => 'required|string',
        ];

        $validator = Validator::make($order, $rules);

        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => "Validation failed at packet index $index",
                'details' => $validator->errors()
            ];
        }
        return [
            'status' => true,
            'message' => 'Validation Success'
        ];
    }

    protected function mapPacket(array $order): array
    {

        return [
            'booked_packet_weight' => $order['booked_packet_weight'],
            'booked_packet_vol_weight_w' => $order['booked_packet_vol_weight_w'] ?? 0,
            'booked_packet_vol_weight_h' => $order['booked_packet_vol_weight_h'] ?? 0,
            'booked_packet_vol_weight_l' => $order['booked_packet_vol_weight_l'] ?? 0,
            'booked_packet_no_piece' => $order['booked_packet_no_piece'] ?? 1,
            'booked_packet_collect_amount' => $order['booked_packet_collect_amount'],
            'booked_packet_order_id' => $order['booked_packet_order_id'],
            'origin_city' => $order['origin_city'] ?? 'self',
            'destination_city' => $order['destination_city'],
            'shipment_id' => $order['shipment_id'] ?? 101,
            'shipment_name_eng' => $order['shipment_name_eng'] ?? 'self',
            'shipment_email' => $order['shipment_email'] ?? 'self',
            'shipment_phone' => $order['shipment_phone'] ?? 'self',
            'shipment_address' => $order['shipment_address'] ?? 'self',
            'consignment_name_eng' => $order['consignment_name_eng'],
            'consignment_email' => $order['consignment_email'] ?? '',
            'consignment_phone' => $order['consignment_phone'],
            'consignment_phone_two' => $order['consignment_phone_two'] ?? '',
            'consignment_phone_three' => $order['consignment_phone_three'] ?? '',
            'consignment_address' => $order['consignment_address'],
            'special_instructions' => $order['special_instructions'] ?? '',
            'shipment_type' => $order['shipment_type'] ?? 'overnight',
            'custom_data' => $order['custom_data'] ?? [],
            'return_address' => $order['return_address'] ?? '',
            'return_city' => $order['return_city'] ?? '',
            'is_vpc' => $order['is_vpc'] ?? 0,
        ];
    }
}


