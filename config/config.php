<?php
/*Hệ thống sử dụng 2 file config.php và deploy.php để cấu hình tùy biến. config.php chứa các
tham số chung nhất còn deploy.php chứa các tham số có thể thay đổi theo môi trường triển khai
 khi sử dụng thì luôn include hoặc require.php config trước deploy.php */
define('SYSTEM_MAINTENANCE', false);
define('SYSTEM_MAINTENANCE_MESSAGE','Hệ thống đang bảo trì từ 0h 20/07/2025');
/*danh sách các modules mà guest user có thể truy cập*/
const GUEST_ACCESSIBLE_MODULES = ['compiled-materials','it-documents'];
define('SESSION_TIMEOUT', 1800); // thời gian giữ session là 30 phút
define('MIN_CACHE_TTL', 3600); // thời gian time to live của cache tối thiểu là 60 phút
define("ARR_PAGE_SIZE", array(25,50,75,100));

/*Tên các class, nếu config lại các tên này thì sẽ cho phép sử dụng các extends class
do người dùng sau này phát triển 
dùng cú pháp kiểu \Core\Models\Connection::class thay vì dùng hằng số nhằm để kiểm soát 
 lỗi sớm ngay từ khi phân tích file chứ không chờ đến lúc runtime
Ví dụ viết 'connection' => \Core\Models\Connection::class chứ không viết
 'connection' => 'Core\Models\Connection'
*/

$CLASS_NAME_MAP = ['connection' => \Core\Models\Connection\Connection::class, 
    'db_service' => \Core\Models\DbService::class,
    'request' => \Core\Models\Request::class,
    'user_service' => \Core\Models\User\UserService::class,
    'auth_context' => \Core\Models\Auth\AuthContext::class,
    'request_auth_context' => \Core\Models\RequestAuthContext::class,
    'static_router' => \Core\Models\Route\StaticRouter::class,
    'context_router' => \Core\Models\Route\ContextRouter::class,
    'static_router_cache' => \Core\Models\Cache\StaticRouterCache::class,
    'router_factory' => \Core\Models\Route\RouterFactory::class,
    'mobile_detect' => \Detection\MobileDetect::class,
    'layout' => \App\Models\Layout::class,
    'layout_factory' => \Core\Models\Layout\LayoutFactory::class,
    'controller_factory' => \Core\Models\ControllerFactory::class,
    'app' => \Core\Models\App::class
    ];
/*--------------------------------------------------------------------*/
$lifetime = 2 * SESSION_TIMEOUT;
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params($lifetime);
/*--------------------------------------------------------------------*/
function loadCfgConnection() {
    $envFile = dirname(__FILE__) . '/.env.local.php';
    if (file_exists($envFile)) {
        return include $envFile;
    }

    // Trường hợp production: lấy từ biến môi trường
    return [
        /*các tham số của kết nối default*/    
        'DEFAULT_CONNECT' => [
            'DB_SERVER'     => getenv('DB_SERVER'),
            'DB_NAME'       => getenv('DB_NAME'),
            'DB_USERNAME'   => getenv('DB_USERNAME'),
            'DB_PASSWORD'   => getenv('DB_PASSWORD'),
            'DB_CHARSET'    => getenv('DB_CHARSET'),
            ]
            /*Begin: các tham số của kết nối khác. Ví dụ kết nối other có thể có tên là 
            OTHER_DB_SERVER, OTHER_DB_NAME, OTHER_DB_USERNAME, OTHER_DB_PASSWORD*/
            /*End: các tham số của kết nối khác*/    
    ];

}
/*--------------------------------------------------------------------*/
/*kỹ thuật security bằng cách thay đường dẫn của tài khoản admin.
 Tài khoản admin thường có dạng: https://domain/admin-controller-name
 * nó thường được thay bằng https://domain/something-word
 * Trong đó something-word thường là 1 từ hơi khó đoán. Trong hàm matchUri khi phát hiện
 *chuỗi  something-word sẽ tự thay bằng admin-controller thật 
 */
//const URI_PREFIX_MAP = ['cacquak' => ['login','index']];
const ADMIN_CONTROLLER_RENAME = ['admin-login' => 'cacquak'];
const ADMIN_ROLE_NAME = 'admin';
//DEFAULT_ENTRY dùng trong tình huống khi url dạng khuyết cả module, controller, action
define('DEFAULT_ENTRY',  'compiled-materials'); //có thể là module hoặc controller
const DEFAULT_API_ROUTE = [// module => comtroller => action hoặc comtroller => action
    'compiled-materials' => ['category' => 'index'],    
    'it-documents' => ['category' => 'index'], 
    'pbt-framework' => ['category' => 'index'], 
    'bud-project' => ['category' => 'index'], 
    'login' => 'login',
    'admin-login' => 'login',
    'client-info' => 'index'
];
const DEFAULT_HTML_ROUTE = [// module => comtroller => action hoặc comtroller => action
    'compiled-materials' => ['category' => 'renderPage'],    
    'it-documents' => ['category' => 'renderPage'], 
    'pbt-framework' => ['category' => 'renderPage'], 
    'bud-project' => ['category' => 'renderPage'], 
    'login' => 'renderPage',
    'admin-login' => 'renderPage'
];
define ('URI_CLIENT_INFO', '/client-info?response_type=json'); 
//define ('URI_DATA_LAYOUT', '/data-layout?response_type=json'); 
//define('CSS_SCRIPT_VERSION','2024.12.21.00.20'); //viết theo đúng format yyyy.MM.dd.hh.mm  (năm tháng ngày giờ phút) hoặc yyyyMMddhhmmss năm tháng ngày giờ phút giây)



    
    
    
    

