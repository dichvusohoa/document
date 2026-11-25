<?php
namespace App\View\HtmlSchema;
use Core\View\HtmlSchema\BaseHtmlPageSchema;
class CategoryPageSchema extends BaseHtmlPageSchema{
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function defineSchema(): array{
        return [ 
            'title' => 
                [   'fragment_type' => 'element', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => ['tag' => 'title'],
                    'failure' => 'fallback'
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
            'category_tree' => 
                [   'fragment_type' => 'element', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => 
                        [   
                            'tag' => 'div'
                        ],
                    'failure' => 'fail_page'
                ],
            'category_detail' => 
                [   'fragment_type' => 'view', 
                    'data_source' => 'controller',
                    'render_mode' =>  'server',
                    'render_detail' => 
                        [   
                            'file' => APP_PATH.'/resources/views/category/category_detail.phtml',
                            'ui_context' => 'none'
                        ],
                    'failure' => 'fail_page'
                ]
            ];
        
    }

    protected function definePositionToFragmentMap(): array {
        return['title' => 'title', 'css' => 'css', 'script' => 'script', 
            'left_main'=>'category_tree', 
            'right_main'=>'category_detail'];
    }

   
}