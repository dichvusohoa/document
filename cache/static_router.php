<?php
return array (
  'arrM' => 
  array (
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
  'arrFCQNA2F' => 
  array (
    'App\\Controller\\Category\\CategoryPageController' => 
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
        'module' => '[module:*]',
        'controller' => NULL,
        'action' => NULL,
        'method' => NULL,
        'role' => NULL,
      ),
      'fqcn' => 'Core\\Middleware\\ClientInfoMiddleware',
    ),
    1 => 
    array (
      'expr' => 
      array (
        'module' => NULL,
        'controller' => '[controller:category|document]',
        'action' => NULL,
        'method' => NULL,
        'role' => NULL,
      ),
      'fqcn' => 'Core\\Middleware\\AuthMiddleware',
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
);
