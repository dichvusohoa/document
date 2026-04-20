<?php
namespace App\Models\Layout;
use Core\Models\Layout\BaseMobileDetectFactory;
class MobileDetectFactory extends BaseMobileDetectFactory{
    protected function requiresDeviceDetection() {
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
}
