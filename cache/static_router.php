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
      'category' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\CategoryController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
      'document' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\DocumentController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
    ),
    'it-documents' => 
    array (
      'category' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\CategoryController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
      'document' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\DocumentController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
    ),
    'pbt-framework' => 
    array (
      'category' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\CategoryController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
      'document' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\DocumentController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
    ),
    'bud-project' => 
    array (
      'category' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\CategoryController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
      'document' => 
      array (
        'api_class' => 'App\\Controllers\\_Shared\\DocumentController',
        'html_class' => NULL,
        'html_schema' => NULL,
      ),
    ),
    'login' => 
    array (
      'api_class' => 'Core\\Controllers\\LoginController',
      'html_class' => 'Core\\Controllers\\HtmlPageControllers\\LoginPageController',
      'html_schema' => 'Core\\Models\\HtmlPageSchemas\\LoginPageSchema',
    ),
    'admin-login' => 
    array (
      'api_class' => 'Core\\Controllers\\AdminLoginController',
      'html_class' => 'Core\\Controllers\\HtmlPageControllers\\AdminLoginPageController',
      'html_schema' => 'Core\\Models\\HtmlPageSchemas\\AdminLoginPageSchema',
    ),
    'client-info' => 
    array (
      'api_class' => 'Core\\Controllers\\ClientInfoController',
      'html_class' => NULL,
      'html_schema' => NULL,
    ),
  ),
  'arrFCQNA2F' => 
  array (
    'App\\Controllers\\_Shared\\CategoryController' => 
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
    'App\\Controllers\\_Shared\\DocumentController' => 
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
    'Core\\Controllers\\LoginController' => 
    array (
      'login' => 
      array (
        'function' => 'login',
        'method' => 'post',
      ),
      'logout' => 
      array (
        'function' => 'logout',
        'method' => 'post',
      ),
    ),
    'Core\\Controllers\\HtmlPageControllers\\LoginPageController' => 
    array (
      'renderPage' => 
      array (
        'function' => 'renderPage',
        'method' => 'get',
      ),
    ),
    'Core\\Controllers\\AdminLoginController' => 
    array (
      'index' => 
      array (
        'function' => 'index',
        'method' => 'get',
      ),
      'login' => 
      array (
        'function' => 'login',
        'method' => 'post',
      ),
      'logout' => 
      array (
        'function' => 'logout',
        'method' => 'post',
      ),
    ),
    'Core\\Controllers\\ClientInfoController' => 
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
        'fctype' => NULL,
        'module' => '[module:*]',
        'controller' => NULL,
        'action' => NULL,
        'method' => NULL,
        'role' => NULL,
      ),
      'fqcn' => 'Core\\Middlewares\\ClientInfoMiddleware',
    ),
    1 => 
    array (
      'expr' => 
      array (
        'fctype' => NULL,
        'module' => '[module:pbt-framework|bud-project]',
        'controller' => NULL,
        'action' => NULL,
        'method' => NULL,
        'role' => NULL,
      ),
      'fqcn' => 'Core\\Middlewares\\AuthMiddleware',
    ),
  ),
  'arrTMCAR' => 
  array (
    'api_class' => 
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\CategoryController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
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
            'fqcn' => 'App\\Controllers\\_Shared\\DocumentController',
            'html_schema' => NULL,
            'function' => 'update',
            'method' => 'POST',
          ),
        ),
      ),
      'login' => 
      array (
        'login' => 
        array (
          'roles' => 
          array (
            0 => 'guest',
          ),
          'fqcn' => 'Core\\Controllers\\LoginController',
          'html_schema' => NULL,
          'function' => 'login',
          'method' => 'POST',
        ),
      ),
      'admin-login' => 
      array (
        'login' => 
        array (
          'roles' => 
          array (
            0 => 'guest',
          ),
          'fqcn' => 'Core\\Controllers\\AdminLoginController',
          'html_schema' => NULL,
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
          'fqcn' => 'Core\\Controllers\\ClientInfoController',
          'html_schema' => NULL,
          'function' => 'index',
          'method' => 'JSON',
        ),
      ),
    ),
    'html_class' => 
    array (
      'login' => 
      array (
        'renderPage' => 
        array (
          'roles' => 
          array (
            0 => 'guest',
          ),
          'fqcn' => 'Core\\Controllers\\HtmlPageControllers\\LoginPageController',
          'html_schema' => 'Core\\Models\\HtmlPageSchemas\\LoginPageSchema',
          'function' => 'renderPage',
          'method' => 'GET',
        ),
      ),
    ),
  ),
);
