<?php
namespace Core\View\HtmlSchema;
class LoginPageSchema extends BaseHtmlPageSchema{
    /*public function __construct(LoginPageSchema $schema){
        parent::__construct($schema);
    }*/
    /*---------------------------------------------------------------------------------------------------------------*/        
    protected function defineSchema(): array{
        return [ 
            'title' => 
                [   'fragment_type' => 'element', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => ['tag' => 'title'],
                    'failure' => 'fail_page'
                ],
            'css'   => 
                [   'fragment_type' => 'css_link', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => null,
                    'failure' => 'fail_page'
                ],
            'script'   => 
                [   'fragment_type' => 'script', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => null,
                    'failure' => 'fail_page'
                ],    
            'login' => 
                [   'fragment_type' => 'view', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => 
                        [   
                            'file' => CORE_PATH.'/resources/views/login/login.phtml',
                            'ui_context' => 'custom'
                        ],
                    'failure' => 'fail_page'
                ]
            ];
        
    }
    protected function definePositionToFragmentMap(): array{
        return['title' => 'title', 'css' => 'css', 'script' => 'script', 'main'=>'login'];
    }
    
    
}