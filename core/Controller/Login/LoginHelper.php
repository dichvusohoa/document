<?php

namespace Core\Controller\Login;
use Core\Http\RequestAuthContext;
/**
 * Description of LoginHelper
 *
 * @author admin
 */
class LoginHelper {
    protected RequestAuthContext $requestAuthContext;
        
    public static  function isAdminLoginRequest(RequestAuthContext $requestAuthContext): bool{
        $arrMCA = $requestAuthContext->routePath();
        $strController = $arrMCA[0] ?? '';
        return array_key_exists($strController, ADMIN_CONTROLLER_RENAME);
    }
    public static  function loginRequiredRole(RequestAuthContext $requestAuthContext): ?string{
        return $self::isAdminLoginRequest($requestAuthContext)
        ? ADMIN_ROLE_NAME
        : null;
    }
}
