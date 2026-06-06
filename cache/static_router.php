<?php
return array (
  'arrM' => 
  array (
    0 => 'compiled-materials',
    1 => 'it-documents',
    2 => 'pbt-framework',
    3 => 'bud-project',
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
    'compiled-materials' => 
    array (
      'category' => 'App\\Controller\\CategoryController',
      'document' => 'App\\Controller\\DocumentController',
    ),
    'it-documents' => 
    array (
      'category' => 'App\\Controller\\CategoryController',
      'document' => 'App\\Controller\\DocumentController',
    ),
    'pbt-framework' => 
    array (
      'category' => 'App\\Controller\\CategoryController',
      'document' => 'App\\Controller\\DocumentController',
    ),
    'bud-project' => 
    array (
      'category' => 'App\\Controller\\CategoryController',
      'document' => 'App\\Controller\\DocumentController',
    ),
    'login' => 'Core\\Controller\\Login\\LoginController',
    'admin-login' => 'Core\\Controller\\Login\\LoginController',
    'client-info' => 'Core\\Controller\\ClientInfoController',
  ),
  'arrFCQNA2F' => 
  array (
    'App\\Controller\\CategoryController' => 
    array (
      'index' => 
      array (
        'function' => 'index',
        'method' => 'get',
      ),
      'update' => 
      array (
        'function' => 'update',
        'method' => 'post',
      ),
    ),
    'App\\Controller\\DocumentController' => 
    array (
      'index' => 
      array (
        'function' => 'index',
        'method' => 'get',
      ),
      'update' => 
      array (
        'function' => 'update',
        'method' => 'post',
      ),
    ),
    'Core\\Controller\\Login\\LoginController' => 
    array (
      'index' => 
      array (
        'function' => 'renderPage',
        'method' => 'get',
      ),
      'login' => 
      array (
        'function' => 'login',
        'method' => 'post',
      ),
    ),
    'Core\\Controller\\ClientInfoController' => 
    array (
      'index' => 
      array (
        'function' => 'index',
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
        'module' => '[module:pbt-framework|bud-project]',
        'controller' => NULL,
        'action' => NULL,
        'method' => NULL,
        'role' => NULL,
      ),
      'fqcn' => 'Core\\Middleware\\AuthMiddleware',
    ),
  ),
  'arrMCAR' => 
  array (
    'compiled-materials' => 
    array (
      'category' => 
      array (
        'index' => 
        array (
          'roles' => 
          array (
            0 => 'guest',
            1 => 'cm_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'cm_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
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
            1 => 'cm_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'cm_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'update',
          'method' => 'POST',
        ),
      ),
    ),
    'it-documents' => 
    array (
      'category' => 
      array (
        'index' => 
        array (
          'roles' => 
          array (
            0 => 'guest',
            1 => 'it_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'it_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
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
            1 => 'it_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'it_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'update',
          'method' => 'POST',
        ),
      ),
    ),
    'pbt-framework' => 
    array (
      'category' => 
      array (
        'index' => 
        array (
          'roles' => 
          array (
            0 => 'pbt_fwk_user',
            1 => 'pbt_fwk_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'pbt_fwk_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
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
            0 => 'pbt_fwk_user',
            1 => 'pbt_fwk_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'pbt_fwk_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'update',
          'method' => 'POST',
        ),
      ),
    ),
    'bud-project' => 
    array (
      'category' => 
      array (
        'index' => 
        array (
          'roles' => 
          array (
            0 => 'bud_prj_user',
            1 => 'bud_prj_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'bud_prj_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\CategoryController',
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
            0 => 'bud_prj_user',
            1 => 'bud_prj_admin',
            2 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'index',
          'method' => 'GET',
        ),
        'update' => 
        array (
          'roles' => 
          array (
            0 => 'bud_prj_admin',
            1 => 'admin',
          ),
          'fqcn' => 'App\\Controller\\DocumentController',
          'function' => 'update',
          'method' => 'POST',
        ),
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
        'fqcn' => 'Core\\Controller\\Login\\LoginController',
        'function' => 'renderPage',
        'method' => 'GET',
      ),
      'login' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
        ),
        'fqcn' => 'Core\\Controller\\Login\\LoginController',
        'function' => 'login',
        'method' => 'POST',
      ),
    ),
    'admin-login' => 
    array (
      'index' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
        ),
        'fqcn' => 'Core\\Controller\\Login\\LoginController',
        'function' => 'renderPage',
        'method' => 'GET',
      ),
      'login' => 
      array (
        'roles' => 
        array (
          0 => 'guest',
        ),
        'fqcn' => 'Core\\Controller\\Login\\LoginController',
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
