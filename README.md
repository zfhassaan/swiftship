<!--suppress ALL -->
<p align="center">
  <img src="./assets/images/swiftship.jpeg" alt="SwiftShip Laravel Package" width="150"/><br/>
</p>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zfhassaan/easypaisa.svg?style=flat-square)](https://packagist.org/packages/zfhassaan/easypaisa)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/zfhassaan/easypaisa.svg?style=flat-square)](https://packagist.org/packages/zfhassaan/easypaisa)

# SwiftShip
### Supported Companies

<p align="center">
  <img src="https://www.tcsexpress.com/TCS.svg" alt="Image 2" width="150"/>

[//]: # (  <img src="./assets/images/image3.jpeg" alt="Image 3" width="150"/>)

[//]: # (  <img src="./assets/images/image4.jpeg" alt="Image 4" width="150"/>)
</p>



### Disclaimer
* This software package is unofficial and is not endorsed or affiliated with any official shipping service.
* It is designed for single-service integration only; multiservice functionality is not currently enabled.
* Please be aware that this project is in an active development phase and may undergo changes and updates.
* We have plans to integrate multiple shipping services in future releases, allowing for parallel usage and expanded functionality.

### Planned Support For:
  <img style="margin-right: 30px;" src="./assets/images/trax-logo.svg" alt="Image 1" width="150"/>

### Introduction
Welcome to SwiftShip, your gateway to effortless courier company integration within the Laravel ecosystem.
Seamlessly connect with top-tier shipping services like TCS, DHL, LCS, Trax, and Leopard, all designed to supercharge your shipping operations. Experience real-time tracking, cost optimization, and an intuitive interface, all wrapped in a package built to elevate your eCommerce or logistics platform. Join us on the journey to a faster, more efficient shipping experience, with SwiftShipLaravel as your trusted partner.

### Intended Audience
SwiftShipLaravel is tailored for Laravel developers, eCommerce businesses, and logistics professionals seeking efficient courier company integration. Whether you're a seasoned developer looking to streamline shipping services or an organization aiming to enhance your logistics operations, our package is designed to meet your needs. Explore the capabilities of SwiftShipLaravel and elevate your shipping experience today.
The package only uses the COD apis for booking the courier and tracking.

### Requirements
The following are required fields from service which you need to collect the information: 

* Tcs: 
    * X-IBM-Client-Id
    * userName
    * Password

### Usage
All couriers will have this format ```ServiceNameClient``` such as ```LCSClient``` or ```TCSClient```.
```php

use zfhassaan\SwiftShip\SwiftShip;
use zfhassaan\SwiftShip\Couriers\LCS\LCSClient;

class YourController extends Controller
{
    public function TrackLCSShipment($trackingNumber)
    {
        // Create an instance of the Service You want to use. e.g. LCSClient / TCSClient or something-else.
        $lcsClient = new LCSClient();

        // Set the LCSClient as the selected courier client
        (new \zfhassaan\swiftship\SwiftShip())->setCourierClient($lcsClient);

        // Now you can track the LCS shipment
        $trackingInfo = (new \zfhassaan\swiftship\SwiftShip())->trackShipment(string $trackingNumber);

        // Process and return the tracking information
        return view('tracking', ['trackingInfo' => $trackingInfo]);
    }
}

```
#### Shipping Calculation

**Shipping Fee Formula:**

The shipping fee is calculated based on two factors: the initial weight and the additional/excess weight.

1. **Initial Weight Freight:**

    - The initial weight is a predefined weight limit that is used as a threshold for determining the shipping cost.
    - The "Initial Weight Freight" is the cost associated with this initial weight.
    - If the shipment's weight is equal to or less than the initial weight, the shipping cost is equal to the "Initial Weight Freight."

2. **Additional Weight Freight:**

    - The "Additional Weight Freight" is the cost associated with any weight beyond the initial weight.
    - If the shipment's weight exceeds the initial weight, the shipping cost for the excess weight is calculated as follows:

**Shipping Fee for Excess Weight Calculation:**

Shipping fee for excess weight = Freight fee for initial weight + (Excess weight * Unit price for excess weight)

- "Freight fee for initial weight" is the cost for the initial weight (as mentioned earlier).
- "Excess weight" is the weight that exceeds the predefined initial weight limit.
- "Unit price for excess weight" is the cost per unit of weight for the excess weight.

**Example:**

Let's consider a practical example to illustrate this formula:

- Initial Weight Limit: 5 kg
- Initial Weight Freight: Rs. 10
- Unit Price for Excess Weight: Rs. 2 per kg

Scenario 1: Shipment Weight is 4 kg
- Since the shipment weight (4 kg) is less than the initial weight limit (5 kg), the shipping cost is equal to the "Initial Weight Freight," which is Rs. 10.

Scenario 2: Shipment Weight is 7 kg
- The shipment weight (7 kg) exceeds the initial weight limit (5 kg).
- To calculate the shipping cost for the excess weight:
    - Initial Weight Freight = Rs. 10
    - Excess Weight = 7 kg - 5 kg = 2 kg
    - Unit Price for Excess Weight = Rs. 2 per kg
- Shipping fee for excess weight = Rs. 10 (Freight fee for initial weight) + (Rs. 2 per kg * 2 kg) = Rs. 14
- So, the total shipping cost for a 7 kg shipment would be Rs. 10 (initial weight) + Rs. 14 (excess weight) = Rs. 24.

In summary, this formula allows you to calculate the shipping cost based on the weight of the shipment. If the weight is within the initial weight limit, the cost is determined by the initial weight freight. If the weight exceeds the initial limit, the cost includes the initial weight freight plus an additional cost based on the excess weight and the unit price for excess weight.
