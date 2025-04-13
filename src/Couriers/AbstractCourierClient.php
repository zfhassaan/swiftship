<?php

namespace Zfhassaan\SwiftShip\Couriers;

use Zfhassaan\SwiftShip\Interface\CourierClientInterface;

abstract class AbstractCourierClient implements CourierClientInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    protected function getConfigValue(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    // Optionally override in child class
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
