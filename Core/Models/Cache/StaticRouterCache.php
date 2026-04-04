<?php
namespace Core\Models\Cache;
use Core\Models\Route\StaticRouter;

class StaticRouterCache extends Cache
{
    public function __construct(){
        parent::__construct(
            false, // ❗ disk cache
            null,
            CACHE_PATH . '/static_router.php',
            3600,
            [
                CONFIG_PATH.'/list.module.php',
                CONFIG_PATH.'/list.role.php',
                CONFIG_PATH.'/config.fca2f.php',
                CONFIG_PATH.'/config.mc2fc.php',
                CONFIG_PATH.'/config.api.mcr2a.php',
                CONFIG_PATH.'/config.html.mcr2a.php',
                CONFIG_PATH.'/middleware.route.php',
            ],
            StaticRouter::class
        );
    }

    /*--------------------------------------------------*/
    public function saveCache(object $obj): bool
    {
        if (!$obj instanceof StaticRouter) {
            return false;
        }

        $array = $obj->toArray();

        $content = "<?php\nreturn " . var_export($array, true) . ";\n";

        return (bool)file_put_contents(
            $this->strCacheFile,
            $content,
            LOCK_EX
        );
    }

    /*--------------------------------------------------*/
    public function loadCache(): ?StaticRouter{
        
        if (!$this->isCacheValid() || !is_file($this->strCacheFile)) {
            return null;
        }
        // đọc dữ liệu từ file, vì li do trong file đã có ghi sẵn code PHP dạng
        // return ...
        $data = require $this->strCacheFile; 

        if (!is_array($data)) {
            return null;
        }
        return StaticRouter::fromArray($data);
    }
}
