<?php
namespace App\View\Layout;
use Core\View\Layout\BaseMobileDetectFactory;
class MobileDetectFactory extends BaseMobileDetectFactory{
    protected function requiresDeviceDetection(): bool{
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
}
