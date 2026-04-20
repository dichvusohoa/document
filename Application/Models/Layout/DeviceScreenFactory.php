<?php
namespace App\Models\Layout;
use Core\Models\Layout\BaseDeviceScreenFactory;
class DeviceScreenFactory extends BaseDeviceScreenFactory {
    protected function requiresScreenDetection(): bool{
        return false;
    }
  
}
