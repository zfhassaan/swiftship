<?php

namespace Zfhassaan\Swiftship\Couriers\LCS;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
    public function __construct()
    {
        $this->mode = config('swiftship.lcs.lcs_mode') ?? 'sandbox';
        $this->url = $this->mode == 'sandbox' ? config('swiftship.lcs.lcs_staging_url') : config('swiftship.lcs.lcs_production_url');
        $this->password = config('swiftship.lcs.lcs_password');
        $this->api = config('swiftship.lcs.lcs_api_key');
        $this->format = '/format/json/';
    }

    /**
     * Sends an HTTP request to the LCS API.
     *
     * Constructs the request URL, prepares the payload, and handles the response.
     * Supports both JSON and non-JSON payloads and manages exceptions and error responses.
     *
     * @param string $endpoint The API endpoint to which the request is sent. This is appended to the base URL.
     * @param string $method The HTTP method to be used for the request (e.g., 'GET', 'POST').
     * @param array $data An associative array of data to be sent with the request. This data is merged with authentication credentials.
     * @param bool $withJson A flag indicating whether the payload should be sent as JSON. Defaults to true.
     *
     * @return JsonResponse Returns a JSON response object. On success, it returns a response with a success message and data. On failure, it returns a response with an error message and code.
     *
     * @throws \Exception Throws an exception if the API gateway times out (HTTP 504).
     */
    protected function send(string $endpoint, string $method, array $data, bool $withJson = true): JsonResponse
    {
        try {
            $url = $this->url . $endpoint . $this->format;
            $payload = array_merge(['api_key' => $this->api, 'api_password' => $this->password], $data);
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->{$method}($url, $withJson ? $payload : $data);

            if ($response->status() === Response::HTTP_GATEWAY_TIMEOUT) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            $statusCode = $response->status();

            if ($statusCode !== Response::HTTP_OK) {
                $response = [
                    'message' => 'Service Down or Invalid API Key',
                    'code' => $statusCode,
                ];
                return Helper::failure('Service Response', $response, Response::HTTP_BAD_REQUEST);
            }

            return Helper::success('Success', $response->json());
        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
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
        $cities_api = 'getAllCities' . $format;
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
     * Create a shipment booking with LCS.
     *
     * Example payload:
     * {
     *   "booked_packet_weight": 1500,
     *   "booked_packet_no_piece": 2,
     *   "booked_packet_collect_amount": 300,
     *   "booked_packet_order_id": "INV-999",
     *   "origin_city": "self",
     *   "destination_city": 123,
     *   "shipment_id": 321,
     *   "shipment_name_eng": "My Company",
     *   "shipment_email": "info@myco.com",
     *   "shipment_phone": "03001234567",
     *   "shipment_address": "1 Company Rd",
     *   "consignment_name_eng": "Test Order",
     *   "consignment_phone": "03211234567",
     *   "consignment_address": "456 Consignee St",
     *   "special_instructions": "Deliver by evening",
     *   "return_address": "",
     *   "return_city": null,
     *   "is_vpc": 0
     * }
     *
     * This method sends booking data to the LCS API after validating and merging credentials.
     * It returns a success response with tracking info or failure with error details.
     *
     * @param array $data The shipment details to be booked
     * @param string $format Optional format suffix, default is '/format/json/'
     *
     * @return JsonResponse
     *
     * @throws ConnectionException
     */
    public function createBooking(array $data, string $format = '/format/json/'): JsonResponse
    {
        $validate = $this->validateCreateBooking($data);
        if ($validate['status'] === false) {
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

        if ($response->json()['status'] == 1) {
            return Helper::success('Booking Response', [
                'track_number' => $response->json()['track_number'],
                'slip_link' => $response->json()['slip_link']
            ], Response::HTTP_CREATED);
        }
        return Helper::failure('Unable to Create Booking', $response->json(), Response::HTTP_BAD_REQUEST);
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
     *      "consignment": "MM123456789,MM132456789"
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
        if (!$consignmentNumber) {
            return Helper::failure('Invalid Consignment Number');
        }

        $cancel_booking = $this->url . 'cancelBookedPackets' . $this->format;
        $data = [
            'api_key' => $this->api,
            'api_password' => $this->password,
            'cn_numbers' => $consignmentNumber
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post($cancel_booking, $data)->json();

        if ($response['status'] == 0) {
            return Helper::failure('Unable to Cancel Booking', $response['error'], Response::HTTP_BAD_REQUEST);
        }
        return Helper::success('Booking Response', $response);
    }

    /**
     * Track a shipment using the provided tracking number.
     *
     * Sends a request to the LCS tracking API and returns the tracking status.
     * If the tracking number is invalid or the service is down, it responds accordingly.
     *
     * @param string $trackingNumber The tracking number to be queried.
     *
     * @return JsonResponse
     * @throws ConnectionException
     */
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        if (!$trackingNumber) {
            return Helper::failure('Invalid Tracking Number');
        }

        $trackShipment = $this->url . 'trackBookedPacket' . $this->format;

        $data = [
            'api_key' => $this->api,
            'api_password' => $this->password,
            'track_numbers' => $trackingNumber
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($trackShipment, $data)->json();


        if ($response['status'] == 0 || $response == null) {
            return Helper::failure('Service Down. Error Tracking Shipment', $response ?? '', Response::HTTP_BAD_REQUEST);
        }
        return Helper::success('Tracking Response', $response['packet_list']);
    }

    /**
     * Get the list of supported countries for LCS delivery.
     *
     * Currently, only Pakistan is supported by LCS. This endpoint returns
     * basic country information such as short code, dialing code, name, and currency.
     *
     * @return JsonResponse
     */
    public function CountriesList(): JsonResponse
    {
        return Helper::success('Supported countries for LCS delivery', [
            'countries' => [
                [
                    'short_code' => 'PK',
                    'dial_code' => '+92',
                    'name' => 'Pakistan',
                    'currency' => 'PKR',
                    'supported' => true
                ]
            ]
        ]);
    }

    /**
     * Example payload:
     * {
     * "api_key": "your_api_key",
     * "api_password": "your_api_password",
     * "shipment_name": "ABC Traders",
     * "shipment_email": "contact@abctraders.pk",
     * "shipment_phone": "03001234567",
     * "shipment_address": "Office 12, Business Tower, Lahore",
     * "bank_id": 3,
     * "bank_account_no": "01234567890123",
     * "bank_account_title": "ABC Traders",
     * "bank_branch": "Gulberg Branch",
     * "bank_account_iban_no": "PK36SCBL0000001123456702",
     * "city_id": "123",  // e.g. Lahore ID
     * "cnic": "35201-1234567-8",
     * "return_address": "Warehouse 4, Industrial Zone, Lahore"
     * }
     * @param array $data
     * @return JsonResponse
     * @throws ConnectionException
     */
    public function createShipper(array $data): JsonResponse
    {
        try {
            $url = $this->url . 'createShipper' . $this->format;

            $payload = array_merge([
                'api_key' => $this->api,
                'api_password' => $this->password
            ], $data);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, $payload);
            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }
            if ($response->successful()) {
                return Helper::success('Shipper Created Successfully', $response->json());
            }

            return Helper::failure('Failed to Create Shipper', $response->json(), $response->status());
        } catch(\Exception $e)
        {
            return Helper::failure('Failed to Create Shipper', $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Submit multiple shipment bookings to LCS via batch API.
     *
     * This method accepts an array of shipment orders, validates each,
     * maps them to the required LCS API structure, and submits them as a batch.
     * Before calling this api we need to make sure shipment_id is required and need to call create
     * Shipper first before we can use this api.
     *
     * ### Example Payload
     * [
     *     [
     *         "booked_packet_weight" => 2000,
     *         "booked_packet_vol_weight_w" => 0,
     *         "booked_packet_vol_weight_h" => 0,
     *         "booked_packet_vol_weight_l" => 0,
     *         "booked_packet_no_piece" => 1,
     *         "booked_packet_collect_amount" => 1500,
     *         "booked_packet_order_id" => "ORDER12345",
     *         "origin_city" => "self",
     *         "destination_city" => "456",
     *         "shipment_id" => 1835746,
     *         "shipment_name_eng" => "self",
     *         "shipment_email" => "self",
     *         "shipment_phone" => "self",
     *         "shipment_address" => "self",
     *         "consignment_name_eng" => "Ali Khan",
     *         "consignment_email" => "ali@example.com",
     *         "consignment_phone" => "03001234567",
     *         "consignment_phone_two" => "",
     *         "consignment_phone_three" => "",
     *         "consignment_address" => "House #1, Street 2, Islamabad",
     *         "special_instructions" => "Handle with care",
     *         "shipment_type" => "overnight",
     *         "custom_data" => [
     *             ["color" => "red", "fragile" => true]
     *         ],
     *         "return_address" => "Warehouse 7, Lahore",
     *         "return_city" => 789,
     *         "is_vpc" => 0
     *     ],
     *     ...
     * ]
     *
     * ### Example Usage:
     * ```php
     * $response = $swiftShip->BatchBookPackets($payload);
     * ```
     *
     * @param array $orders An array of associative arrays, each representing a packet to be booked.
     *                      Each packet must include required fields like `booked_packet_weight`,
     *                      `shipment_id`, `destination_city`, etc.
     *
     * @return JsonResponse Returns a success response with booking data or a failure response with error details.
     *
     * @throws ConnectionException
     */
    public function BatchBookPackets(array $orders): JsonResponse
    {
        $packets = collect($orders)->map(function ($order, $index) {
            $this->validatePacket($order, $index);
            return $this->mapPacket($order);
        })->all();

        $url = $this->url . 'batchBookPacket' . $this->format;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'api_key' => $this->api,
            'api_password' => $this->password,
            'packets' => $packets
        ]);

        if ($response->failed() || $response->json()['status'] == '0') return Helper::failure('Batch Book Packet Error', $response->json() ?? '');

        return Helper::success('Booking Response', $response->json());
    }

    /**
     * Generate a load sheet for the given CN numbers via LCS API.
     *
     * This method sends a list of consignment numbers to the LCS `generateLoadSheet` endpoint,
     * using the configured courier name and code. If the API responds with a failure status,
     * it returns a detailed error message related to the first CN number.
     *
     * ### Example Usage:
     * ```php
     * $response = $swiftShip->GenerateLoadSheet(['LE12345678', 'LE123456789']);
     * ```
     *
     * ### Expected Payload Sent:
     * - `api_key` (string): API authentication key.
     * - `api_password` (string): API password.
     * - `cn_numbers` (array): List of consignment numbers to be included in the load sheet.
     * - `courier_name` (string): Configured courier name (from `swiftship.lcs.lcs_courier_name`).
     * - `courier_code` (string): Configured courier code (from `swiftship.lcs.lcs_courier_code`).
     *
     * ### Error Handling:
     * If the API returns `status = 0`, it fetches the first CN number's associated error message
     * and returns a failure response with details.
     *
     * @param array $cn_numbers List of consignment numbers for which to generate the load sheet.
     *
     * @return JsonResponse Returns a JSON response with the load sheet data on success,
     *                      or an error message on failure.
     *
     */
    public function GenerateLoadSheet(array $cn_numbers): JsonResponse
    {
        try {
            $url = $this->url . 'generateLoadSheet' . $this->format;
            $payload = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'cn_numbers' => $cn_numbers,
                'courier_name' => config('swiftship.lcs.lcs_courier_name'),
                'courier_code' => config('swiftship.lcs.lcs_courier_code')
            ];
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response['status'] == 0) {
                $firstCN = $cn_numbers[0] ?? '';
                $error = $response['error'][$firstCN] ?? 'Unknown error';
                $cn_number = $response['cn_number'] = $firstCN ?? '';
                return Helper::failure('Unable to Generate Load Sheet', [
                    'error' => $error,
                    'cn_number' => $cn_number
                ], Response::HTTP_BAD_REQUEST);
            }

            return Helper::success('Load Sheet Response', $response);
        } catch(\Exception $e)
        {
            return Helper::failure('Load Sheet Error', $e->getMessage());
        }
    }

    /**
     * Download a load sheet PDF and save it to local storage.
     *
     * @param array $data Contains 'loadSheetId' and optional 'response_type'
     * @return BinaryFileResponse|JsonResponse Download URL to the saved PDF file
     * @throws \Exception
     */
    public function downloadLoadSheet(array $data): BinaryFileResponse|JsonResponse
    {
        try {
            $url = $this->url . 'downloadLoadSheet' . $this->format;
            $payload = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'load_sheet_id' => $data['loadSheetId'],
                'response_type' => $data['response_type'] ?? 'PDF'
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, $payload);


            if ($response->status() === 504) {
                return Helper::failure('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to download load sheet');
            }
            if($data['response_type'] == 'PDF') {
                $fileContent = $response->body();

                $fileName = 'load_sheets/' . Str::uuid() . '.pdf';
                Storage::disk('public')->put($fileName, $fileContent);
                $fullPath = Storage::disk('public')->path($fileName);
                $path =  config('app.url') . Storage::url($fileName);
                return Helper::success('Success',$path);
            }

            return Helper::success('Download Sheet Response', $response->json());
        } catch (\Exception $e) {
            return Helper::failure('Failed to download load sheet', $e->getMessage());
        }
    }

    /**
     * Fetch booked packet last statuses from LCS within the given date range.
     *
     * This method sends a GET request to the LCS API with `from_date` and `to_date` as parameters,
     * and returns the last known statuses for all booked packets during that range.
     *
     * ### Example Input:
     * ```json
     * {
     *   "fromDate": "10-04-2025",
     *   "toDate": "10-04-2025"
     * }
     * ```
     *
     * ### Example Usage:
     * ```php
     * $swiftShip->getBookedPacketLastStatuses('10-04-2025', '10-04-2025');
     * ```
     *
     * ### Validations:
     * - Dates must be in `Y-m-d` format.
     * - `from_date` must not be after `to_date`.
     *
     * ### Response Structure:
     * On success, returns:
     * ```json
     * {
     *   "status": true,
     *   "message": "Booked Packet Last Statuses",
     *   "data": [ { ...packet entries... } ]
     * }
     * ```
     *
     * On failure or timeout:
     * ```json
     * {
     *   "status": false,
     *   "message": "Error message",
     *   "data": ...
     * }
     * ```
     *
     * @param string $fromDate Date in `d-m-Y` format.
     * @param string $toDate Date in `d-m-Y` format.
     * @return JsonResponse
     *
     * @throws \Exception if the API gateway times out or if validation fails.
     */
    public function getBookedPacketLastStatuses(string $fromDate, string $toDate): JsonResponse
    {
        // Validate format and logical range
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $fromDate) || !preg_match('/^\d{2}-\d{2}-\d{4}$/', $toDate)) {
            return Helper::failure('Invalid date format. Use DD-MM-YYYY');
        }

        if (strtotime($fromDate) > strtotime($toDate)) {
            return Helper::failure('From date cannot be after To date.');
        }

        $url = $this->url . 'getBookedPacketLastStatus' . $this->format;

        try {
            $response = Http::get($url, [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('Booked Packet Last Statuses', $data);
            }

            $packetList = $data['packet_list'] ?? [];

            if (empty($packetList)) {
                return Helper::failure('No packets found in the given date range.');
            }

            return Helper::success('Booked Packet Last Statuses', $packetList);

        } catch (\Throwable $e) {
            return Helper::failure('Error fetching packet statuses', $e->getMessage());
        }
    }

    /**
     *
     * What Does the Leopard Courier shipperAdviceList API Do?
     * The shipperAdviceList API allows you to fetch a list of booked consignments (packets) that have been assigned advice texts—which represent comments, issues, or delivery status notes from Leopard Courier—for a specific date range, optionally filtered by origin and destination city.
     *
     * 📦 Use Case: Why Is It Required?
     * For businesses or merchants integrated with Leopard Courier Service, this API is essential to:
     *
     * 🔎 Track delivery issues or pending reasons (like incorrect addresses, customer not available, etc.)
     * 📄 View detailed packet information, including tracking number, booking date, cities, status, and comments
     * 📊 Monitor packet delivery statuses in bulk without manually checking each one
     * 🧾 Trigger follow-ups (customer calls, refunds, rebooking) based on advice text and pending reasons
     * 🔁 Automate dashboard reports or customer notifications with live courier feedback
     * 🧠 What Is "Advice"?
     *
     * In Leopard's system:
     * advice_text = a numeric code representing what action was taken or what issue occurred.
     * advice_date_created = when that action/comment was logged.
     * pending_reason = human-readable string like "Address not found" or "Refused by consignee".
     *
     * This lets merchants know exactly why a shipment is delayed or pending, directly from LCS operations.
     *
     * Example Scenarios for Using the Shipper Advice List API:
     *
     *  This API helps track, report, and handle delivery statuses and issues across multiple consignments.
     *  Below are practical use cases for integrating this API:
     *
     *  1. Displaying Delayed Shipments to Support Team
     *     - Filter `packet_list` where `pending_reason` is not empty or `booked_packet_status` is not "Delivered".
     *     - Helps support staff proactively resolve undelivered cases.
     *
     *  2. Generating Daily Report of Undelivered Orders
     *     - Call API using today's date as both `from_date` and `to_date`.
     *     - Use the result to generate daily exception reports for logistics review.
     *
     *  3. Syncing Packet Status with Customer Portal
     *     - Integrate the `packet_list` into your CRM or customer-facing portal.
     *     - Display tracking numbers, advice comments, and delivery statuses for full visibility.
     *
     *  4. Escalating Specific Failures
     *     - Analyze `advice_text` values to identify recurring issues like "Refused by consignee" or "Address not found".
     *     - Use this data to trigger alerts or workflows for escalation (e.g., supervisor follow-up).
     *
     * shipperAdviceList = "Give me all packets between these dates (optionally filtered by city), and tell me what problems or actions were taken on them."
     *
     * It’s a vital diagnostics and tracking API for post-booking visibility into delivery outcomes and exception handling.
     *
     * Fetch shipper advice list from LCS API within the specified date range and optional city filters.
     *
     * This function sends a POST request to the `shipperAdviceList` endpoint of the LCS API,
     * using the provided date range and optional origin/destination city filters. It validates
     * the input, handles HTTP response failures, and returns a structured JSON response.
     *
     * ### Example Payload:
     * ```json
     * {
     *   "from_date": "2025-04-01",
     *   "to_date": "2025-04-13",
     *   "origin_city": 592,
     *   "destination_city": 602
     * }
     * ```
     *
     * ### Input Parameters:
     * - `from_date` (string, required): Must be in `Y-m-d` format.
     * - `to_date` (string, required): Must be in `Y-m-d` format and equal to or after `from_date`.
     * - `origin_city` (int, optional): ID of the origin city.
     * - `destination_city` (int, optional): ID of the destination city.
     *
     * ### Validations:
     * - `from_date`: required, `Y-m-d` format.
     * - `to_date`: required, `Y-m-d` format, must be same or after `from_date`.
     * - `origin_city`: optional, must be an integer.
     * - `destination_city`: optional, must be an integer.
     *
     * ### Success Response:
     * ```json
     * {
     *   "status": true,
     *   "message": "Shipper Advice List",
     *   "data": [ { ...packet data... } ]
     * }
     * ```
     *
     * ### Failure Response:
     * - Validation errors
     * - HTTP 504 timeout
     * - API-level failure response
     *
     * @param array $filters Associative array containing 'from_date', 'to_date', and optional city filters.
     *
     * @return JsonResponse Structured JSON response via `Helper::success()` or `Helper::failure()`.
     *
     * @throws \Exception If LCS API times out or an unexpected error occurs.
     */
    public function getShipperAdviceList(array $filters): JsonResponse
    {
        // Validate inputs
        $validator = Validator::make($filters, [
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date',
            'origin_city' => 'nullable|integer',
            'destination_city' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url.'shipperAdviceList'.$this->format;

            $payload = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
            ];

            if (!empty($filters['origin_city'])) {
                $payload['origin_city'] = (int) $filters['origin_city'];
            }
            if (!empty($filters['destination_city'])) {
                $payload['destination_city'] = (int) $filters['destination_city'];
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch shipper advice list', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] == 0) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Shipper Advice List', $data['packet_list'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Fetch shipment details from Leopard Courier based on one or more shipment order IDs.
     *
     * This method sends a POST request to the LCS `getShipmentDetailsByOrderID` API endpoint
     * using the provided order IDs, and returns the associated shipment detail(s).
     *
     * ### Payload Example:
     * ```json
     * {
     *   "shipment_order_id": [
     *     "123446789086"
     *   ]
     * }
     * ```
     *
     * ### Input:
     * - `shipment_order_id` (array, required): An array of one or more order ID strings.
     *
     * ### Validations:
     * - `shipment_order_id` must be a non-empty array
     * - Each value in `shipment_order_id` must be a string and distinct
     *
     * ### Success Response:
     * Returns structured shipment detail(s) in the format:
     * ```json
     * {
     *   "status": true,
     *   "message": "Shipment Details Retrieved",
     *   "data": [ { ...shipment detail... } ]
     * }
     * ```
     *
     * ### Failure Response:
     * - Validation errors (missing or malformed input)
     * - HTTP 504 timeout (Gateway Timeout from LCS)
     * - LCS API returning status 0 or an error block
     *
     * ### Edge Cases:
     * - Handles single or multiple order IDs
     * - Throws specific error if API times out
     * - Uses Laravel JSON client, may require `json_decode()` fallback if payload is too large
     *
     * @param array $filters An array containing a `shipment_order_id` key with one or more values.
     * @return JsonResponse A success or failure JSON response containing shipment details or errors.
     */
    public function getShipmentDetailsByOrderID(array $filters): JsonResponse
    {
        // Validate input
        $validator = Validator::make($filters, [
            'shipment_order_id' => 'required|array|min:1',
            'shipment_order_id.*' => 'string|distinct'
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url.'getShipmentDetailsByOrderID'.$this->format;

            $payload = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'shipment_order_id' => $filters['shipment_order_id'],
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, $payload);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch shipment details', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] == 0) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Shipment Details Retrieved', $data['data'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Fetch the list of all banks supported by Leopard Courier.
     *
     * This method sends a GET request to the LCS `getBankList` API endpoint with the
     * merchant’s API credentials. It returns a list of banks that can be used when
     * registering or managing shipper bank accounts.
     *
     * ### No Input Required
     * The request only includes:
     * - `api_key` (string): Your Leopard Courier API key
     * - `api_password` (string): Your Leopard Courier API password
     *
     * ### Success Response:
     * ```json
     * {
     *   "status": true,
     *   "message": "Banks List",
     *   "data": [
     *     {
     *       "bank_id": 3,
     *       "bank_name": "ABC Bank"
     *     },
     *     ...
     *   ]
     * }
     * ```
     *
     * ### Failure Response:
     * - If the API returns `status != 1`
     * - If the HTTP request fails (e.g., 504 timeout)
     * - If an unexpected exception is thrown
     *
     * ### Exceptions Handled:
     * - HTTP 504: Gateway Timeout from LCS
     * - Any other network or runtime exceptions
     *
     * @return JsonResponse A structured JSON response containing the list of banks or an error message.
     */
    public function getAllBanks(): JsonResponse
    {
       try {
           $url = $this->url.'getBankList'.$this->format;
           $response = Http::get($url,[
               'api_key' => $this->api,
               'api_password' => $this->password
           ]);

           if($response->status() === 504) {
               throw new \Exception('LCS API Gateway Timeout (504)');
           }

           if(!isset($response->json()['status']) || $response->json()['status'] != 1) {
               return Helper::failure('Unable to fetch banks data', $response->json());
           }

           $data = $response->json();

           return Helper::success('Banks List', $data['bank_list'] ?? []);
       } catch(\Exception $e){
           return Helper::failure('API Exception', $e->getMessage());
       }
    }

    /**
     * Retrieves payment details from the LCS API for a list of consignment numbers.
     *
     * Sends a GET request with the specified CN numbers and returns payment-related information.
     *
     * Example Input:
     * {
     *   "cn_numbers": [
     *     "LE123456789",
     *     "LE234512352"
     *   ]
     * }
     *
     * @param array $cn_numbers Array with a key `cn_numbers` containing up to 50 unique consignment numbers.
     *
     * @return JsonResponse A JSON response with payment details or an error message.
     */
    public function getPaymentDetails(array $cn_numbers): JsonResponse
    {
        // ✅ Validate CN numbers input
        $validator = Validator::make($cn_numbers, [
            'cn_numbers' => 'required|array|min:1|max:50',
            'cn_numbers.*' => 'string|distinct'
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'getPaymentDetails' . $this->format;

            $queryParams = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'cn_numbers' => implode(',', $cn_numbers['cn_numbers']),
            ];

            $response = Http::get($url, $queryParams);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch payment details', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Payment Details Retrieved', $data['payment_list'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Fetches tariff details from the LCS API based on shipment parameters.
     *
     * Sends a GET request with weight, cities, and COD amount to determine shipping cost.
     *
     * Example Input:
     * {
     *   "packet_weight": 1200,       // Weight in grams
     *   "shipment_type": 1,          // e.g., Overnight = 1
     *   "origin_city": 101,          // Origin city ID
     *   "destination_city": 202,     // Destination city ID
     *   "cod_amount": 1500.00        // Cash on Delivery amount
     * }
     *
     * @param array $filters An associative array containing tariff parameters.
     *
     * @return JsonResponse A JSON response containing tariff details or an error message.
     */
    public function getTariffDetails(array $filters): JsonResponse
    {
        // ✅ Validate input parameters
        $validator = Validator::make($filters, [
            'packet_weight' => 'required|numeric|min:1',
            'shipment_type' => 'required|integer',
            'origin_city' => 'required|integer',
            'destination_city' => 'required|integer',
            'cod_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'getTariffDetails' . $this->format;

            $params = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'packet_weight' => $filters['packet_weight'],
                'shipment_type' => $filters['shipment_type'],
                'origin_city' => $filters['origin_city'],
                'destination_city' => $filters['destination_city'],
                'cod_amount' => $filters['cod_amount'],
            ];

            $query = http_build_query($params);
            $fullUrl = $url . '?' . $query;
            $response = Http::get($fullUrl);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch tariff details', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Tariff Details Retrieved', $data ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Retrieves shipping charges from the LCS API for a list of consignment numbers.
     *
     * Sends a GET request with the provided CN numbers and returns the calculated charges.
     *
     * Example Input:
     * {
     *   "cn_numbers": [
     *     "LE123456789",
     *     "LE234523456"
     *   ]
     * }
     *
     * @param array $filters Array with a key `cn_numbers` containing up to 50 unique consignment numbers.
     *
     * @return JsonResponse A JSON response with shipping charge data or an error message.
     */
    public function getShippingCharges(array $filters): JsonResponse
    {
        // ✅ Validate input
        $validator = Validator::make($filters, [
            'cn_numbers' => 'required|array|min:1|max:50',
            'cn_numbers.*' => 'string|distinct'
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'getShippingCharges' . $this->format;

            $queryParams = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'cn_numbers' => implode(',', $filters['cn_numbers']),
            ];

            $response = Http::get($url, $queryParams);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch shipping charges', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Shipping Charges Retrieved', $data['data'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Retrieves detailed information about a shipper from the LCS API using a specified lookup parameter.
     *
     * Sends a GET request with the provided filter to fetch shipper details. Handles validation,
     * API failure, and exceptions.
     *
     * Example Input:
     * {
     *   "request_param": "cnic",           // Field to search by (e.g., "cnic", "shipment_email", etc.)
     *   "request_value": "35201-1234567-8" // Value of the field to look up
     * }
     *
     * @param array $filters An associative array containing 'request_param' and 'request_value'.
     *
     * @return JsonResponse A JSON response containing shipper details or error information.
     */
    public function getShipperDetails(array $filters): JsonResponse
    {
        $validator = Validator::make($filters, [
            'request_param' => 'required|string',
            'request_value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'getShipperDetails' . $this->format;

            $queryParams = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'request_param' => $filters['request_param'],
                'request_value' => $filters['request_value'],
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->get($url, $queryParams);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch shipper details', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Shipper Details Retrieved', $data['shipper_detail'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Fetches Electronic Proof of Delivery (E-POD) details for the provided consignment numbers.
     *
     * Validates the input list, sends a GET request to the LCS API, and returns the response or handles errors accordingly.
     *
     * Example Input:
     * {
     *   "cn_numbers": [
     *     "LE123456789",
     *     "LE987654321"
     *   ]
     * }
     *
     * @param array $cn_numbers Array with a key `cn_numbers` containing up to 50 unique consignment numbers.
     *
     * @return JsonResponse A JSON response containing E-POD details or error information.
     */
    public function getElectronicPOD(array $cn_numbers): JsonResponse
    {
        $validator = Validator::make($cn_numbers, [
            'cn_numbers' => 'required|array|min:1|max:50',
            'cn_numbers.*' => 'string|distinct'
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'getElectronicProofOfDelivery' . $this->format;

            $queryParams = [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'cn_number' => implode(',', $cn_numbers['cn_numbers']),
            ];

            $response = Http::get($url, $queryParams);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch E-POD data', $response->json());
            }

            $data = $response->json();
            ;
            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Electronic Proof of Delivery Retrieved', $data['epod_list'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Retrieves a paginated list of shipper advice entries from the LCS API based on filters.
     *
     * Validates the input and posts the request to the remote API. Handles validation errors, timeouts, and response failures.
     *
     * Example Input:
     * {
     *   "start": 0,                    // Required. Pagination offset.
     *   "length": 20,                  // Required. Number of records per page (1-100).
     *   "dateFrom": "2025-04-01",      // Optional. Starting date (Y-m-d).
     *   "toDate": "2025-04-14",        // Optional. Ending date (Y-m-d, >= dateFrom).
     *   "product": "Overnight",        // Optional. Filter by product type.
     *   "status": "RT",                // Optional. Filter by shipper advice status.
     *   "origionID": 101,              // Optional. Origin city ID.
     *   "destinationID": 202,          // Optional. Destination city ID.
     *   "Cn_number": "LE123456789"     // Optional. Filter by consignment number.
     * }
     *
     * @param array $filters Filters for pagination and shipper advice filtering.
     *
     * @return JsonResponse A JSON response containing paginated advice list or error details.
     */
    public function getShipperAdviceListPaginated(array $filters): JsonResponse
    {
        // ✅ Validate input (only enforcing required ones)
        $validator = Validator::make($filters, [
            'start' => 'required|integer|min:0',
            'length' => 'required|integer|min:1|max:100',
            'dateFrom' => 'nullable|date_format:Y-m-d',
            'toDate' => 'nullable|date_format:Y-m-d|after_or_equal:dateFrom',
            'product' => 'nullable|string',
            'status' => 'nullable|string',
            'origionID' => 'nullable|integer',
            'destinationID' => 'nullable|integer',
            'Cn_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'shipperAdviceList' . $this->format;

            $payload = array_merge([
                'api_key' => $this->api,
                'api_password' => $this->password,
            ], $filters);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($url, $payload);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch shipper advice list (paginated)', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Paginated Shipper Advice List', $data['packet_list'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Retrieves activity logs from the LCS API based on provided filters.
     *
     * Validates the input filters, constructs the API payload, and handles response validation and exceptions.
     *
     * Expected Input:
     * [
     *   'start' => 0,             // Required. Offset for pagination.
     *   'length' => 20,           // Required. Limit per page (1-100).
     *   'product' => 'Overnight', // Optional. Filter by product type.
     *   'status' => 'Delivered',  // Optional. Filter by status.
     *   'Cn_number' => 'LE123',   // Optional. Filter by consignment number.
     * ]
     *
     * @param array $filters Filters to be applied on activity logs (start, length, product, status, Cn_number).
     *
     * @return JsonResponse A JSON response containing the logs or an error message.
     */
    public function getActivityLog(array $filters): JsonResponse
    {
        // ✅ Validate input
        $validator = Validator::make($filters, [
            'start' => 'required|integer|min:0',
            'length' => 'required|integer|min:1|max:100',
            'product' => 'nullable|string',
            'status' => 'nullable|string',
            'Cn_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'activityLog' . $this->format;

            $payload = array_merge([
                'api_key' => $this->api,
                'api_password' => $this->password,
            ], $filters);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to fetch activity log', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Activity Log Retrieved', $data ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
    }

    /**
     * Sends a request to the LCS API to update shipper advice statuses for multiple consignments.
     *
     * Validates the provided payload and posts the data to the remote API.
     * Handles validation errors, API failures, gateway timeouts, and exceptions gracefully.
     *
     * Example Payload:
     * [
     *   'data' => [
     *     [
     *       'id' => 101,
     *       'cn_number' => 'LE123456789',
     *       'shipper_advice_status' => 'RA', // RA = Return Advised, RT = Return To
     *       'shipper_remarks' => 'Customer not available'
     *     ],
     *     [
     *       'id' => 102,
     *       'cn_number' => 'LE987654321',
     *       'shipper_advice_status' => 'RT',
     *       'shipper_remarks' => 'Refused delivery'
     *     ]
     *   ]
     * ]
     *
     * @param array $payload The payload containing consignment updates, expected to have a 'data' key with up to 50 entries.
     *
     * @return JsonResponse A JSON response indicating success or failure, with API response data or error message.
     */
    public function updateShipperAdvice(array $payload): JsonResponse
    {
        $validator = Validator::make($payload, [
            'data' => 'required|array|min:1|max:50',
            'data.*.id' => 'required|integer',
            'data.*.cn_number' => 'required|string',
            'data.*.shipper_advice_status' => 'required|in:RA,RT',
            'data.*.shipper_remarks' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return Helper::failure('Validation Error', $validator->errors()->toArray());
        }

        try {
            $url = $this->url . 'updateShipperAdvice' . $this->format;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, [
                'api_key' => $this->api,
                'api_password' => $this->password,
                'data' => $payload['data'],
            ]);

            if ($response->status() === 504) {
                throw new \Exception('LCS API Gateway Timeout (504)');
            }

            if ($response->failed()) {
                return Helper::failure('Failed to update shipper advice', $response->json());
            }

            $data = $response->json();

            if (!isset($data['status']) || $data['status'] != 1) {
                return Helper::failure('API returned unsuccessful status', $data);
            }

            return Helper::success('Shipper Advice Updated Successfully', $data['updated_list'] ?? []);

        } catch (\Throwable $e) {
            return Helper::failure('API Exception', $e->getMessage());
        }
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

    protected function validatePacket(array $order, int $index): array
    {
        $rules = [
            'weight' => 'required|numeric|min:1',
            'collect_amount' => 'required|numeric|min:0',
            'order_id' => 'required|string',
            'booked_packet_cn' => 'required|string',
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
            'booked_packet_cn' => $order['booked_packet_cn'] ?? '',
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

    public function getSelectedCourierClient(): JsonResponse
    {
        return Helper::failure('Not Supported for this Client...', '');
    }

    public function ReverseLogistics($request): JsonResponse
    {
        return Helper::failure('Not Supported for this Client...', '');

    }

    public function OriginsList(): JsonResponse
    {
        return Helper::failure('Not Supported for this Client...', '');
    }


}


