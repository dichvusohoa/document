<?php
return array (
  'arrM' => 
  array (
  ),
  'arrMC' => 
  array (
  ),
  'arrStC' => 
  array (
    0 => 'category',
    1 => 'document',
    2 => 'client-info',
    3 => 'login',
    4 => 'cacquak',
  ),
  'arrR' => 
  array (
    'guest' => 
    array (
      'display_name' => 'khách',
      'weight' => 0,
    ),
    'cm_admin' => 
    array (
      'display_name' => 'admin tài liệu sưu tập',
      'weight' => 2,
    ),
    'it_admin' => 
    array (
      'display_name' => 'admin tài liệu tin học',
      'weight' => 2,
    ),
    'pbt_fwk_user' => 
    array (
      'display_name' => 'người dùng PBT framework',
      'weight' => 1,
    ),
    'pbt_fwk_admin' => 
    array (
      'display_name' => 'admin PBT framework',
      'weight' => 2,
    ),
    'bud_prj_user' => 
    array (
      'display_name' => 'người dùng BUD project',
      'weight' => 1,
    ),
    'bud_prj_admin' => 
    array (
      'display_name' => 'admin BUD project',
      'weight' => 2,
    ),
    'admin' => 
    array (
      'display_name' => 'quản trị hệ thống',
      'weight' => 3,
    ),
  ),
  'arrAuthRegistry' => 
  array (
    'login' => 
    array (
      'max_fail_count' => 5,
      'turnstile' => 3,
      'remember_cookie' => true,
      'remember_expire' => 604800,
      'default_business_path' => '/',
      'accepted_roles' => 
      array (
        0 => 'cm_admin',
        1 => 'it_admin',
        2 => 'pbt_fwk_user',
        3 => 'pbt_fwk_admin',
        4 => 'bud_prj_user',
        5 => 'bud_prj_admin',
      ),
      'weights' => 
      array (
        'max_role_weight' => 2,
        'accepted_role_count' => 6,
      ),
    ),
    'cacquak' => 
    array (
      'max_fail_count' => 3,
      'turnstile' => 'always',
      'remember_cookie' => false,
      'default_business_path' => '/',
      'remember_expire' => NULL,
      'accepted_roles' => 
      array (
        0 => 'admin',
      ),
      'weights' => 
      array (
        'max_role_weight' => 3,
        'accepted_role_count' => 1,
      ),
    ),
  ),
  'arrMC2FQCN' => 
  array (
    'category' => 'App\\Controller\\Category\\CategoryPageController',
    'document' => 'App\\Controller\\Document\\DocumentPageController',
    'login' => 'Core\\Controller\\Login\\LoginPageController',
    'client-info' => 'Core\\Controller\\ClientInfoController',
  ),
  'arrFCAction' => 
  array (
    'App\\Controller\\Category\\CategoryPageController' => 
    array (
      'index' => 
      array (
        'function' => 'renderPage',
        'method' => 'get',
      ),
      'update' => 
      array (
        'method' => 'post',
      ),
    ),
    'App\\Controller\\Document\\DocumentPageController' => 
    array (
      'index' => 
      array (
        'method' => 'get',
      ),
      'update' => 
      array (
        'method' => 'post',
      ),
    ),
    'Core\\Controller\\Login\\LoginPageController' => 
    array (
      'index' => 
      array (
        'function' => 'renderPage',
        'method' => 'get',
      ),
      'login' => 
      array (
        'method' => 'post',
      ),
    ),
    'Core\\Controller\\ClientInfoController' => 
    array (
      'index' => 
      array (
        'method' => 'json',
      ),
    ),
  ),
  'arrMiddlewareParsed' => 
  array (
    0 => 
    array (
      'expr' => 
      array (
        'module' => NULL,
        'controller' => '[controller:*]',
        'action' => NULL,
        'role' => NULL,
        'method' => NULL,
      ),
      'fqcn' => 
      array (
        0 => 'Core\\Middleware\\AuthMiddleware',
        1 => 'Core\\Middleware\\ClientInfoMiddleware',
      ),
    ),
  ),
  'arrMCAR' => 
  array (
    'category' => 
    array (
      'index' => 
      array (
        'roles' => 
        array (
          0 => 'cm_admin',
          1 => 'it_admin',
          2 => 'pbt_fwk_admin',
          3 => 'bud_prj_admin',
          4 => 'admin',
        ),
        'fqcn' => 'App\\Controller\\Category\\CategoryPageController',
        'function' => 'renderPage',
        'method' => 'GET',
        'route_type' => 'business',
        'authentication_path' => '/login',
        'default_business_path' => NULL,
      ),
      'update' => 
      array (
        'roles' => 
        array (
          0 => 'cm_admin',
          1 => 'it_admin',
          2 => 'pbt_fwk_admin',
          3 => 'bud_prj_admin',
          4 => 'admin',
        ),
        'fqcn' => 'App\\Controller\\Category\\CategoryPageController',
        'function' => 'update',
        'method' => 'POST',
        'route_type' => 'business',
        'authentication_path' => '/login',
        'default_business_path' => NULL,
      ),
    ),
    'document' => 
    array (
      'index' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
          1 => 'pbt_fwk_user',
          2 => 'bud_prj_user',
          3 => 'cm_admin',
          4 => 'it_admin',
          5 => 'pbt_fwk_admin',
          6 => 'bud_prj_admin',
          7 => 'admin',
        ),
        'fqcn' => 'App\\Controller\\Document\\DocumentPageController',
        'function' => 'index',
        'method' => 'GET',
        'route_type' => 'business',
        'authentication_path' => '/login',
        'default_business_path' => NULL,
      ),
      'update' => 
      array (
        'roles' => 
        array (
          0 => 'cm_admin',
          1 => 'it_admin',
          2 => 'pbt_fwk_admin',
          3 => 'bud_prj_admin',
          4 => 'admin',
        ),
        'fqcn' => 'App\\Controller\\Document\\DocumentPageController',
        'function' => 'update',
        'method' => 'POST',
        'route_type' => 'business',
        'authentication_path' => '/login',
        'default_business_path' => NULL,
      ),
    ),
    'login' => 
    array (
      'index' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
        ),
        'fqcn' => 'Core\\Controller\\Login\\LoginPageController',
        'function' => 'renderPage',
        'method' => 'GET',
        'route_type' => 'authentication',
        'authentication_path' => NULL,
        'default_business_path' => '/',
      ),
      'login' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
        ),
        'fqcn' => 'Core\\Controller\\Login\\LoginPageController',
        'function' => 'login',
        'method' => 'POST',
        'route_type' => 'authentication',
        'authentication_path' => NULL,
        'default_business_path' => '/',
      ),
    ),
    'client-info' => 
    array (
      'index' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
          1 => 'cm_admin',
          2 => 'it_admin',
          3 => 'pbt_fwk_user',
          4 => 'pbt_fwk_admin',
          5 => 'bud_prj_user',
          6 => 'bud_prj_admin',
          7 => 'admin',
        ),
        'fqcn' => 'Core\\Controller\\ClientInfoController',
        'function' => 'index',
        'method' => 'JSON',
        'route_type' => 'business',
        'authentication_path' => '/login',
        'default_business_path' => NULL,
      ),
    ),
  ),
  'arrDefaultRoute' => 
  array (
    'default_entry' => 
    array (
      'type' => 'controller',
      'value' => 'category',
    ),
    'routes' => 
    array (
      'category' => 'index',
      'login' => 'index',
      'client-info' => 'index',
    ),
  ),
);
