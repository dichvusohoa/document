<?php
namespace App\View\Layout;
use Core\View\Layout\BaseDeviceScreenFactory;
class DeviceScreenFactory extends BaseDeviceScreenFactory {
    public function requiresScreenDetection(): bool{
        return true;
    }
  
}
