<?php
namespace App\Models\Layout;
use Core\Models\Layout\BaseDeviceScreenFactory;
class DeviceScreenFactory extends BaseDeviceScreenFactory {
    public function requiresScreenDetection(): bool{
        return true;
    }
  
}
