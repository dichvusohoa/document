<?php
/*Mục đích: để có thể triển khai trên nhiều hệ thống khác nhau, ví dụ
DEPLOY =0: DEVELOPEMENT
DEPLOY =1: PRODUCTION  
DEPLOY =2: OTHER PRODUCTION  

Chú ý là các tham số như user, password, db name thì trong môi trường triển khai không nên lưu trực tiếp vào đây  vì lý do bảo mật
vì lý do bảo mật mà lưu trong enviroment variables  

Trong môi trường developement thì lưu vào .env.local.php 
*/
define('DEPLOY',0);
define('ROOT_PATH', dirname(__DIR__).'/');
switch(DEPLOY){
    case 0:
    error_reporting(E_ALL);//báo cáo tất cả lỗi
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);             // ✅ Ghi lỗi vào file log
    ini_set('error_log', ROOT_PATH.'logs/dev_errors.log');

    date_default_timezone_set('Asia/Saigon');
    define('CONFIG_PATH',ROOT_PATH.'config/');
    define('CORE_PATH',ROOT_PATH.'core/');
    define('APP_PATH',ROOT_PATH.'application/');
    define('PUBLIC_PATH',ROOT_PATH.'public/');
    define('CACHE_PATH',ROOT_PATH.'cache/');
    define('TURNSTILE_SITE_KEY', '1x00000000000000000000AA');
    define('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');
    define('APP_DEBUG', true);
    break;

    case 1:
    error_reporting(E_ALL);// đảm bảo log ghi lỗi vẫn ghi đầy đủ
    ini_set('display_errors', 0);//tắt không  hiển thị ra màn hình
    ini_set('display_startup_errors', 0);//tắt không  hiển thị ra màn hình những lỗi trước khi chạy PHP

    date_default_timezone_set('Asia/Saigon');
    define('CONFIG_PATH',ROOT_PATH.'config/');
    define('CORE_PATH',ROOT_PATH.'core/');
    define('APP_PATH',ROOT_PATH.'application/');
    define('PUBLIC_PATH',ROOT_PATH.'public/');
    define('CACHE_PATH',ROOT_PATH.'cache/');
    define('APP_DEBUG', false);
    /*hiện chưa có*/
    define('TURNSTILE_SITE_KEY', '1x00000000000000000000AA');
    define('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');
    break;
}
/*--------------------------------------------------------------------*/


