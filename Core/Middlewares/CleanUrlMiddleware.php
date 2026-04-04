<?php
namespace Core\Middlewares;
use \Closure;
use Core\Models\RequestAuthContext;
/**
 * Description of MaintenanceMiddleware
 *
 * @author admin
 */
class CleanUrlMiddleware {
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, 'index.php') === false) {
            return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
        }
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // Nếu URL chứa index.php thì redirect
        // loại bỏ index.php
        $cleanUri = str_replace('index.php', '', $requestUri);
        if(APP_DEBUG){
            header("Location: {$cleanUri}", true, 307);
        }
        else{
            header("Location: {$cleanUri}", true, 301);
        }
        exit;
    }
}
