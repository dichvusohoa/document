<?php
namespace Core\Models;
use \RuntimeException;
use \ReflectionClass;
use Core\Models\Utility\ValidUtility;
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

      
        self::set('request_auth_info', function () {
            return new (self::$classMap['request_auth_info'])(
                self::get('request'),
                self::get('auth_context')->getAuthInfo()
            );
        });
        self::set('router', function(){
            return self::$classMap['router_factory']::create(
                self::$classMap['router'],    
                self::get('auth_context'),
                self::get('router_cache')
            );
        });
        self::set('layout', function(){
            return self::$classMap['layout_factory']::create(
                self::$classMap['layout'],    
                self::get('request'),
                self::get('auth_context'),
                Session::get('device_screen'),
                self::get('mobile_detect'),    
                Session::get('route_mca')
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
            $instance = null;
            if($strKey){
                $instance = self::getStoredService($strKey);
            }
            if ($instance) {
                $deps[] = $instance;
            } else {
                $deps[] = self::newInstance($strClassName);
            }
        }
        return $ref->newInstanceArgs($deps);
    }
    public static function getStoredService(string $key): ?object{
        if (isset(self::$services[$key])) { //đã có closure hoặc instance gắn vào
            $service = self::$services[$key];
            if (is_callable($service)) {//mới có closure chưa có instance
                $instance = $service();//create object
                self::$services[$key] = $instance;
                return $instance;
            }
            return $service; //trả về instance
        }
        return null;
        
    }
    /**
     * Lấy service (lazy load).
     */
    public static function get(string $key): object{
        //thử lấy tại lưu trữ $services xem đã có chưa
        $instance = self::getStoredService($key);
        if($instance){
            return $instance;
        }
        if (!isset(self::$classMap[$key])) { //chưa định nghĩa trong bảng class_map
            throw new RuntimeException("Service '$key' chưa được đăng ký hoặc định nghĩa trong class map.");
        }
        // Nếu service chưa được lưu trữ thì cần tạo mới instance
        $class = self::$classMap[$key];
        //$instance = new $class();
        $instance = self::newInstance($class);
        self::$services[$key] = $instance;
        return $instance;
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

    /**
     * Reset toàn bộ service (hữu ích cho test).
     */
    public static function reset(): void{
        self::$services = [];
    }
    
}
