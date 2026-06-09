<?php
namespace Core\Controller;
use Core\View\HtmlSchema\BaseHtmlPageSchema;
use Core\Http\Response;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;

/*BaseHtmlPageController vẫn là abstract nên chưa cần implement resolveParam*/
abstract class BaseHtmlPageController extends BaseController{
    protected RequestAuthContext $requestAuthContext;
    protected BaseHtmlPageSchema $htmlSchema;
   
    protected ?array $arrDataFrag; // dữ liệu cụ thể tại các fragment
    function __construct(BaseHtmlPageSchema $htmlSchema){
        parent::__construct($htmlSchema->getRequestAuthContext());
        $this->htmlSchema = $htmlSchema;
        $this->arrDataFrag = null;
        
    }        
    protected  function buildDataFragments(): void{
        //foreach ($this->arrDescFrag as $strFragment => $value) {
        foreach ($this->htmlSchema->getSchema() as $strFragment => $value) {
            $this->arrDataFrag[$strFragment] = $this->dataAtFragment($strFragment);
        }
    }
    //ví dụ sau này hàm lấy dữ liệu có thể là index(), list() có thể call lại hàm này
    public function renderPage(?array $arrUiFactor = null){
        $this->htmlSchema->buildSchemaDetail($arrUiFactor);
        $this->buildDataFragments();
        //các variable cố định phải truyền cho việc render page
        $arrVar = ['desc_fragment'=>$this->htmlSchema->getSchema(), 
            'data_fragment' => $this->arrDataFrag, 
            'ui_context' => $this->htmlSchema->getUiContext()];
        
        Response::sendHtmlFile($this->htmlSchema->getLayoutFilePath(), false, $arrVar);
    }
    abstract protected function dataAtFragment(string $strFragment):array;
}

