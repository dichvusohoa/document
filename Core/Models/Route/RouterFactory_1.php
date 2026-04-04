<?php
namespace Core\Models\Route;
use Core\Models\Utility\ValidUtility;
use Core\Models\Cache\RouterCache;
use Core\Models\Response;
class RouterFactory{ 
    public static function create(string $strRouterClassFCQN, array $arrAuthInfo, RouterCache $cache): Router{
        if($arrAuthInfo['status'] === Response::SERVER_DB_ERR_STATUS){
            throw new RuntimeException('Lỗi cơ sở dữ liệu khi xác thực người dùng');
        }
        
        $arrUserRole = array_keys($arrAuthInfo['data']['roles']);
        $arrEnableModule = $arrAuthInfo['data']['registered_modules'];
        $routerFC = $cache->loadCache();//$routerFC mean router from cache
        //nếu chưa có cache router, dựng router từ đầu dựa theo đầu vào $arrEnableModule, $arrUserRole)
        if($routerFC === null){
            error_log('[RouterFactory] Router cache MISS - build new router');
            $router = new ($strRouterClassFCQN)($arrEnableModule, $arrUserRole);
            return $router;
        }
        //nếu đã có cache thì kiểm tra xem user role và enable module có bị thay đổi không
  
        if(
            !ValidUtility::equalIntStringMapAsSet($arrUserRole,$routerFC->getUserRoles()) ||//so khớp user role trong cache router và authInfo    
            !ValidUtility::equalIntStringMapAsSet($arrEnableModule,$routerFC->getEnableModules())//so khớp enable module trong cache router và authInfo    
        ){ 
            // lý do xảy ra: logout và chuyển sang user khác, setup lại dữ liệu user hiện thời ví dụ thay đổi enable module của user đó
            $routerFC->setUserRoles($arrUserRole);
            $routerFC->setEnableModules($arrEnableModule);
            error_log('[RouterFactory] Router cache INVALIDATED - roles/modules changed, rebuilding');
            $routerFC->buildMainData(); // build lại thành phần arrData trong router
        }
        error_log('[RouterFactory] Router cache HIT - reuse cached router');
        return $routerFC;
    }
    
}