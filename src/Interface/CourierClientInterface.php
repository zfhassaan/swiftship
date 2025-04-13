<?php

namespace Zfhassaan\SwiftShip\Interface;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface CourierClientInterface
{
    public function trackShipment(string $trackingNumber);
    public function createBooking(array $data);
    public function cancelBooking(string $consignmentNumber);
    public function CountriesList();
    public function GetCities();
    public function BatchBookPackets(array $orders);
    public function GenerateLoadSheet(array $cn_numbers);
    public function createShipper(array $data);
    public function downloadLoadSheet(array $data);
    public function getBookedPacketLastStatuses(string $fromDate, string $toDate);
    public function getShipperAdviceList(array $filters);
    public function getShipmentDetailsByOrderID(array $filters);
    public function getAllBanks();
    public function getPaymentDetails(array $cn_numbers);
    public function getTariffDetails(array $filters);
    public function getShippingCharges(array $filters);
    public function getShipperDetails(array $filters);
    public function getElectronicPOD(array $cn_numbers);
    public function getShipperAdviceListPaginated(array $filters);
    public function getActivityLog(array $filters);
    public function updateShipperAdvice(array $payload);
    public function ReverseLogistics($request);
    public function OriginsList();
}
