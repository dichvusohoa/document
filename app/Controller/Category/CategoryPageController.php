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
    protected function resolveParams(string $strFunctName):array{
        if($strFunctName === 'renderPage'){
            return [];
        }
        
    }
    protected function dataAtFragment(string $strFragmentName):array{
        return [];
    }
    
}

