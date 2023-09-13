<?php

namespace zfhassaan\swiftship\Couriers\TCS;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use zfhassaan\swiftship\Interface\CourierClientInterface;
use Illuminate\Support\Facades\Validator;
use zfhassaan\swiftship\Utility\Helper;

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

    public function trackShipment($trackingNumber): PromiseInterface|JsonResponse|\Illuminate\Http\Client\Response
    {
        try {
            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->get($this->trackingUrl.'?'.http_build_query([
                    'ConsignmentNo' => $trackingNumber
                ]));
            (new Helper())->success($result);
            return $result;
        } catch(\Exception $e) {
            return (new Helper())->failure($e->getMessage());
        }
    }

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
                    'consigneeAddress.required' => 'Consiagnee Address is required',
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
                return (new Helper())->failure($validator->errors()->toArray());
            }

            $result = Http::withHeaders([
                'X-IBM-Client-Id' => $this->client_id,
                'Content-Type' => 'application/json'
            ])->post($this->url.'create-order',$data);

            (new Helper())->LogData('swiftship',' Create Booking API Response ', json_decode($result->body()));
            return $result->body();
        } catch(\Exception $e)
        {
            return (new Helper())->failure($e->getMessage());
        }
    }
}
