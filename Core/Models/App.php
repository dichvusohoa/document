<?php
namespace Core\Models;
use \RuntimeException;
use \ReflectionClass;

/*chú ý nên thiết kế $services của App chứa closure và instance của các class thôi
không nên chứa array hay các loại dữ liệu đơn giản vào đây, thứ đó chứa vào session */
class App{
 
    /** @var array<string, string|callable|object> */
    protected static array $services = [];

    /** @var array<string, string> */
    protected static array $classMap = [];
    //keyMap chính là revert ngược của $classMap. nó map class name => key
    protected static array $keyMap = []; 
    /**
     * Khởi tạo App với class map ban đầu. Hàm init này khởi tạo các instance mà không
     * thể nào khởi tạo bằng hàm newInstance được
     * @param array<string, string> $classMap
     */
    public static function init(array $classMap = []): void{
        self::$classMap = $classMap;
        self::$keyMap   = array_flip($classMap);
        // Đăng ký các service phức tạp bằng closure
        self::set('connection', function () {
            $cfg = loadCfgConnection();
            return new (self::$classMap['connection'])($cfg);
        });
        
      
        self::set('request_auth_context', function () {
            return new (self::$classMap['request_auth_context'])(
                self::get('request'),
                self::get('auth_context')->getAuthInfo()
            );
        });
        /*self::set('router_factory', function(){
            if(self::hasInstance('context_router')){
                return self::get('context_router');
            }
            //chưa có instance
            $router = self::$classMap['router_factory']::create(
                self::$classMap['static_router'],     
                self::$classMap['context_router'],    
                //hoặc là gọi self::get('auth_context')->getAuthInfo() cũng được
                self::get('request_auth_context')->authInfo(),  
                self::get('static_router_cache')
            );
            self::set('context_router', $router);
            return $router;
        });*/
        self::set('context_router', function(){
            $router = self::$classMap['router_factory']::create(
                self::$classMap['static_router'],
                self::$classMap['context_router'],
                self::get('request_auth_context')->authInfo(),
                self::get('static_router_cache')
            );
            return $router;
        });
        /*self::set('layout', function(){
            return self::$classMap['layout_factory']::create(
                self::$classMap['layout'],    
                self::get('request'),
                self::get('auth_context'),
                Session::get('device_screen'),
                self::get('mobile_detect'),    
                Session::get('route_tmca')
            );
        });*/
        self::set('layout', function(){
            return new (self::$classMap['layout'])(
                self::get('request_auth_context'),
                Session::get('route_tmca'),
                function(){ //dùng callable
                    return self::get('mobile_detect');
                }, 
                function(){  //dùng callable
                    return Session::get('device_screen');
                }
            );
        });
        
        // Các service đơn giản (request, session, ...) sẽ tự tạo khi gọi lần đầu
    }
    /**
     * Đăng ký service (instance hoặc closure).
     */
    public static function set(string $key, object|callable $service): void{
        self::$services[$key] = $service;
    }
    /*Các class có contructor chứa tham số không phải là ReflectionNamedType hoặc
    là các dạng đơn giản như int, array,.. thì không thể tạo instance bằng newInstance
    phải khởi tạo bằng tay trong init */
    public static function newInstance(string $class): object{
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("$class không thể khởi tạo");
        }

        $ctor = $ref->getConstructor();
        if (!$ctor) {
            return new $class;
        }

        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            //nếu có default → bỏ qua
            if ($param->isDefaultValueAvailable()) { 
                continue;
            }
            $type = $param->getType();
            if(!($type instanceof \ReflectionNamedType) || $type->isBuiltin()){
                throw new RuntimeException(
                    "Không resolve được param \${$param->getName()} của $class"
                );
            }
            $strClassName = $type->getName();
            $strKey =  self::$keyMap[$strClassName] ?? null;
            if ($strKey) {
                $deps[] = self::get($strKey); // ✅ resolve đầy đủ
            } else {
                $deps[] = self::newInstance($strClassName);//đệ quy sâu hơn
            }
        }
        return $ref->newInstanceArgs($deps);
    }
    //$isObjReturnType = false thì có thể trả về object hoặc null, có thể callable hoặc null
    public static function getInstance(string $key): ?object{
        if (!isset(self::$services[$key])) {
            return null;
        }
        $service = self::$services[$key];

        if (is_object($service) && !is_callable($service)) {
            return $service; // ✅ chỉ trả instance
        }

        return null; // ❌ KHÔNG execute callable
        
    }
    /**
     * Lấy service (lazy load).
     */
    public static function get(string $key): object{
        //thử lấy tại lưu trữ $services xem đã có chưa
        $instance = self::getInstance($key);
        if($instance){
            return $instance;
        }
        // nếu là closure → resolve
        if (isset(self::$services[$key]) && is_callable(self::$services[$key])) {
            $instance = (self::$services[$key])();
            self::$services[$key] = $instance;
            return $instance;
        }

        if (!isset(self::$classMap[$key])) { //chưa định nghĩa trong bảng class_map
            throw new RuntimeException("Service '$key' chưa được đăng ký hoặc định nghĩa trong class map.");
        }
        // Nếu service chưa được lưu trữ thì cần tạo mới instance
        $instance = self::newInstance(self::$classMap[$key]);
        self::$services[$key] = $instance;
        return $instance;
    }
    
    public static function getCallable(string $key): callable{
        //return self::getInstance($key,false);
        if (!isset(self::$services[$key])) {
            throw new RuntimeException("Factory '$key' chưa được đăng ký");
        }
        $service = self::$services[$key];
        if (!is_callable($service)) {
            throw new RuntimeException("Service '$key' không phải là factory");
        }
        return $service;
    }
    /**
     * Override class map trong runtime. Cho phép thay đổi bảng classMap kkhi runtime
     */
    public static function setClass(string $key, string $className): void {
        self::$classMap[$key] = $className;
        unset(self::$services[$key]); // xoá instance cũ nếu có
    }
    public static function getClass(string $key): ?string {
        if (isset(self::$classMap[$key])) {
            return self::$classMap[$key];
        }
        return null;
    }
    public static function getKey(string $className): ?string {
        if (isset(self::$keyMap[$className])) {
            return self::$keyMap[$className];
        }
        return null;
    }
    
    public static function hasElement(string $key): bool{
        return isset(self::$services[$key]); 
    }
    public static function hasInstance(string $key): bool{
        return isset(self::$services[$key]) 
        && is_object(self::$services[$key])
        && !is_callable(self::$services[$key]);
    }
    public static function hasCallable(string $key): bool{
        return isset(self::$services[$key]) 
            && is_callable(self::$services[$key]);
    }
    /**
     * Reset toàn bộ service (hữu ích cho test).
     */
    public static function reset(): void{
        self::$services = [];
    }
    
}
