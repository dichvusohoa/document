<?php
namespace Core\Models\Route;
use RuntimeException;
use Core\Models\Cache\StaticRouterCache;
use Core\Models\Route\ContextRouter;
use Core\Models\Response;

class RouterFactory{ 
    public static function create(string $strStaticRouterFCQN, string $strRouterFCQN, array $arrAuthInfo, StaticRouterCache $cache): ContextRouter{
        if($arrAuthInfo['status'] === Response::SERVER_DB_ERR_STATUS){
            throw new RuntimeException('Lỗi cơ sở dữ liệu khi xác thực người dùng');
        }
        
        $arrUserRole = array_keys($arrAuthInfo['data']['roles']);
        $arrEnableModule = $arrAuthInfo['data']['registered_modules'];
        $staticRouterFC = $cache->loadCache();//$staticRouterFC mean static router from cache
        //nếu chưa có cache router, dựng router từ đầu dựa theo đầu vào $arrEnableModule, $arrUserRole)
        if($staticRouterFC === null){
            error_log('[RouterFactory] Static router cache MISS - build new static router');
            $staticRouter = new ($strStaticRouterFCQN)();
            $cache->saveCache($staticRouter);
            return new ($strRouterFCQN)($arrEnableModule, $arrUserRole, $staticRouter); 
        }
        
        error_log('[RouterFactory] Static router cache HIT - reuse cached static router');
        return new ($strRouterFCQN)($arrEnableModule, $arrUserRole, $staticRouterFC); 
    }
    
}