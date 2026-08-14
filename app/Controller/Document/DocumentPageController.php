<?php
namespace App\Controller\Document;
use Core\Controller\BaseHtmlPageController;
class DocumentPageController extends BaseHtmlPageController{
    protected function argumentsForFunction(string $strFunctionName):array{
    }
    protected function dataAtFragment(string $strFragmentName):array{
        return [];
    }
}
