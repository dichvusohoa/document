<?php
namespace App\Controller\Category;
use Core\Controller\BaseHtmlPageController;
use App\View\HtmlSchema\CategoryPageSchema;
class CategoryPageController extends BaseHtmlPageController{
    protected CategoryController $apiController;
    public function __construct(CategoryPageSchema $schema, 
            CategoryController $apiController){
        parent::__construct($schema);
        $this->apiController = $apiController;
    }
    protected function argumentsForFunction(string $strFunctName):array{
        if($strFunctName === 'renderPage'){
            return [];
        }
        
    }
    protected function dataAtFragment(string $strFragmentName):array{
        switch ($strFragmentName){
            case 'title':
                return ['status'=> Response::SERVER_OK_STATUS, 'data'=>'Category', 'extra'=>null];
            case 'css':
                return [
                    'status'=> Response::SERVER_OK_STATUS, 
                    'data'=>['/lib_assets/css/style.css', 
                        '/lib_assets/css/err.css', 
                        '/lib_assets/css/button.css', 
                        '/lib_assets/css/loading.css'], 
                    'extra'=>null];
            case 'script':
                return ['status'=> Response::SERVER_OK_STATUS,
                    'data'=> [ 
                        '/lib_assets/js/jcommon.js',
                        ['importmap' => true, 'namespace' => 'url', 'src' => '/lib_assets/js/jurl.js'],
                        ['importmap' => true, 'namespace' => 'loading', 'src' => '/lib_assets/js/control/jloading.js'],
                        ['importmap' => true, 'namespace' => 'autoForm', 'src' => '/lib_assets/js/control/jauto_form.js'],
                        ], 
                    'extra'=>null];
            case 'category_tree':    
                return ['status'=> Response::SERVER_OK_STATUS,'data'=> null, 'extra'=>null];
            case 'category_detail':    
                return ['status'=> Response::SERVER_OK_STATUS,'data'=> null, 'extra'=>null];    
        }
    }
    
}

