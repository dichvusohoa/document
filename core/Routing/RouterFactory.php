<?php
namespace Core\Routing;
use RuntimeException;
use LogicException;
use Core\Cache\StaticRouterCache;
use Core\Routing\ContextRouter;
use Core\Http\Response;

class RouterFactory{ 
    protected string    $strStaticRouterFCQN;
    protected string    $strRouterFCQN;
    protected array     $arrAuthInfo;
    protected StaticRouterCache     $cache;
    protected ?StaticRouter $staticRouter = null;//giữ lại kết quả tính toán
    function __construct(
            string $strStaticRouterFCQN, 
            string $strRouterFCQN, 
            array $arrAuthInfo, 
            StaticRouterCache $cache){
        if($arrAuthInfo['status'] === Response::SERVER_DB_ERR_STATUS){
            throw new RuntimeException('Lỗi cơ sở dữ liệu khi xác thực người dùng');
        }
        $this->strStaticRouterFCQN = $strStaticRouterFCQN;
        $this->strRouterFCQN = $strRouterFCQN;
        $this->arrAuthInfo = $arrAuthInfo;
        $this->cache = $cache;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function create(): ContextRouter{
        //vì $this->arrAuthInfo['data']['roles'] có format dạng array [strRoleCode => strDisplayName...] nên phải đùng
        //array_keys để lọc các roleCode ra
        $arrUserRole = array_keys($this->arrAuthInfo['data']['roles']);
        $arrEnabledModule = $this->arrAuthInfo['data']['registered_modules'];
        $staticRouter = $this->cache->loadCache();//$staticRouterFC mean static router from cache
        //nếu chưa có cache router, dựng router từ đầu dựa theo đầu vào $arrEnableModule, $arrUserRole)
        if($staticRouter === null){
            //error_log('[RouterFactory] Static router cache MISS - build new static router');
            $staticRouter = new ($this->strStaticRouterFCQN)();
            $this->cache->saveCache($staticRouter);
        }
        $this->staticRouter = $staticRouter;
        //error_log('[RouterFactory] Static router cache HIT - reuse cached static router');
        return new ($this->strRouterFCQN)($arrEnabledModule, $arrUserRole, $staticRouter); 
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getStaticRouter(): StaticRouter
    {
        if ($this->staticRouter === null) {
            throw new LogicException(
                'StaticRouter has not been created.'
            );
        }
        return $this->staticRouter;
    }
}