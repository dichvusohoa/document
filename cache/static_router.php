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
  ),
  'arrR' => 
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
          0 => 'guest',
          1 => 'pbt_fwk_user',
          2 => 'bud_prj_user',
          3 => 'cm_admin',
          4 => 'it_admin',
          5 => 'pbt_fwk_admin',
          6 => 'bud_prj_admin',
          7 => 'admin',
        ),
        'fqcn' => 'App\\Controller\\Category\\CategoryPageController',
        'function' => 'renderPage',
        'method' => 'GET',
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
