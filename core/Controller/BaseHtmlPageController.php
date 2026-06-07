<?php
namespace Core\Controller;
use Core\View\HtmlSchema\BaseHtmlPageSchema;
use Core\Http\Response;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;

/*BaseHtmlPageController vẫn là abstract nên chưa cần implement resolveParam*/
abstract class BaseHtmlPageController extends BaseController{
    protected RequestAuthContext $requestAuthContext;
    protected string $strLayoutFilePath; //tên file layout
    protected array $arrUiContext; // thường thì là role

    protected array $arrDescFrag; // Desc = description, dữ liệu view cụ thể tại 1 fragment 
    protected array $arrDataFrag; // dữ liệu cụ thể tại các fragment
    function __construct(BaseHtmlPageSchema $htmlSchema){
        $this->requestAuthContext = $htmlSchema->getRequestAuthContext();
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
    public function renderPage(?array $arrOptionVar = null){
        //các variable cố định phải truyền cho việc render page
        $arrVar = ['desc_fragment'=>$this->arrDescFrag, 'data_fragment' => $this->arrDataFrag, 'ui_context' => $this->arrUiContext];
        if(is_array($arrOptionVar)){
            $arrVar = array_merge($arrVar, $arrOptionVar);
        }
        Response::sendHtmlFile($this->strLayoutFilePath, false, $arrVar);
    }
    abstract protected function dataAtFragment(string $strFragment):array;
}

