<?php
namespace Core\Middleware;
use \Closure;
use Core\Http\RequestAuthContext;
use Core\Http\HttpException;
/**
 * Description of MaintenanceMiddleware
 *
 * @author admin
 */
class MaintenanceMiddleware {
    public function handle(RequestAuthContext $requestAuthContext,  Closure $next){
        if (!defined('SYSTEM_MAINTENANCE') || !SYSTEM_MAINTENANCE ) {
            return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
        }
        throw new HttpException(503, 'Service Unavailable');
    }
}
