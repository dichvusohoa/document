<?php
namespace Core\Routing;
use RuntimeException;
use Core\Cache\StaticRouterCache;
use Core\Routing\ContextRouter;
use Core\Http\Response;

class RouterFactory{ 
    protected string    $strStaticRouterFCQN;
    protected string    $strRouterFCQN;
    protected array     $arrAuthInfo;
    protected StaticRouterCache     $cache;
    function __construct(string $strStaticRouterFCQN, string $strRouterFCQN, array $arrAuthInfo, StaticRouterCache $cache){
        if($arrAuthInfo['status'] === Response::SERVER_DB_ERR_STATUS){
            throw new RuntimeException('Lỗi cơ sở dữ liệu khi xác thực người dùng');
        }
        $this->strStaticRouterFCQN = $strStaticRouterFCQN;
        $this->strRouterFCQN = $strRouterFCQN;
        $this->arrAuthInfo = $arrAuthInfo;
        $this->cache = $cache;
    }
    public function create(): ContextRouter{
        
        
        $arrUserRole = array_keys($this->arrAuthInfo['data']['roles']);
        $arrEnableModule = $this->arrAuthInfo['data']['registered_modules'];
        $staticRouterFC = $this->cache->loadCache();//$staticRouterFC mean static router from cache
        //nếu chưa có cache router, dựng router từ đầu dựa theo đầu vào $arrEnableModule, $arrUserRole)
        if($staticRouterFC === null){
            error_log('[RouterFactory] Static router cache MISS - build new static router');
            $staticRouter = new ($this->strStaticRouterFCQN)();
            $this->cache->saveCache($staticRouter);
            return new ($this->strRouterFCQN)($arrEnableModule, $arrUserRole, $staticRouter); 
        }
        
        error_log('[RouterFactory] Static router cache HIT - reuse cached static router');
        return new ($this->strRouterFCQN)($arrEnableModule, $arrUserRole, $staticRouterFC); 
    }
    
}