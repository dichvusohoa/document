<?php
namespace Core\Models\User;
use Core\Models\Utility\StringUtility;

class UserInfo {
    public static function buildGuest(){ 
        return[
            'id'       => null,
            'name'     => null,
            'password' => null,
            'subscriber_id' => null,
            'roles'          => ['guest' => 'khách'],
            'registered_modules' => array_map([StringUtility::class, 'spacesToDash'], GUEST_ACCESSIBLE_MODULES)
        ];
    }
    public static function isValid(mixed $arrData): bool {
        return  is_array($arrData)
        && array_key_exists('id', $arrData)
        && array_key_exists('name', $arrData)
        && array_key_exists('password', $arrData)        
        && array_key_exists('subscriber_id', $arrData)
        && isset($arrData['roles']) && is_array($arrData['roles'])     
        && isset($arrData['registered_modules']) && is_array($arrData['registered_modules']);
             
                
    }
}