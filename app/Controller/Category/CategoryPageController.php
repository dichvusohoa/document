<?php
namespace App\Controller\Category;
use Core\Controller\BaseHtmlPageController;
class CategoryPageController extends BaseHtmlPageController{
    protected function resolveParams(string $strFunctionName):array{
        if($strFunctName === 'renderPage'){
            return [];
        }
        
    }
    protected function dataAtFragment(string $strFragmentName):array{
        return [];
    }
    
}

