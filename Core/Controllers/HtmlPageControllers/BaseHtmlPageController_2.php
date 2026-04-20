<?php
namespace Core\Controllers\HtmlPageControllers;
use Core\Models\HtmlPageSchemas\BaseHtmlPageSchema;
use Core\Models\Response;
use Core\Models\RequestAuthContext;
use Core\Controllers\BaseController;

/*BaseHtmlPageController vẫn là abstract nên chưa cần implement resolveParam*/
abstract class BaseHtmlPageController extends BaseController{
    protected RequestAuthContext $requestAuthContext;
    protected array $arrRouteTMCA;
    protected string $strLayoutFilePath; //tên file layout
    protected array $arrUiContext; // thường thì là role

    protected array $arrDescFrag; // Desc = description, dữ liệu view cụ thể tại 1 fragment 
    protected array $arrDataFrag; // dữ liệu cụ thể tại các fragment
    function __construct(BaseHtmlPageSchema $htmlSchema){
        $this->requestAuthContext = $htmlSchema->getRequestAuthContext();
        $this->arrRouteTMCA = $htmlSchema->getRouteTMCA();
        $this->strLayoutFilePath = $htmlSchema->getLayoutFilePath();
        $this->arrUiContext = $htmlSchema->getUiContext();
        $this->arrDescFrag = $htmlSchema->getSchema();
        $this->buildDataFragments();
        
    }        
    protected  function buildDataFragments(): void{
        foreach ($this->arrDescFrag as $strFragment => $value) {
            $this->arrDataFrag[$strFragment] = $this->dataAtFragment($strFragment);
        }
    }
    //ví dụ sau này hàm lấy dữ liệu có thể là index(), list() có thể call lại hàm này
    public function renderPage(){
        Response::sendHtmlFile($this->strLayoutFilePath, false, ['desc_fragment'=>$this->arrDescFrag, 'data_fragment' => $this->arrDataFrag, 'ui_context' => $this->arrUiContext]);
    }
    abstract protected function dataAtFragment(string $strFragment):array;
}

