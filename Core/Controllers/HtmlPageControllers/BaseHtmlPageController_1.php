<?php
namespace Core\Controllers\HtmlPageControllers;
use Core\Models\HtmlPageSchemas\BaseHtmlPageSchema;
use Core\Models\Response;
use Core\Models\RequestAuthContext;
use Core\Controllers\BaseController;
use RuntimeException;
/*BaseHtmlPageController vẫn là abstract nên chưa cần implement resolveParam*/
abstract class BaseHtmlPageController extends BaseController{
    protected string $strLayoutFilePath; //tên file layout
    protected array $arrDescFrag; // Desc = description, dữ liệu view cụ thể tại 1 fragment 
    protected array $arrDataFrag; // dữ liệu cụ thể tại các fragment
    protected ?array $arrUIContext; // thường thì là role
    function __construct(RequestAuthContext $requestAuthContext, string $strLayoutFilePath, array $arrUIContext, string $strHtmlPageSchemaFQCN){
        parent::__construct($requestAuthContext);
        $oPageSchema= new $strHtmlPageSchemaFQCN($requestAuthContext, $strLayoutFilePath);
        if (!($oPageSchema instanceof BaseHtmlPageSchema)) {
            throw new RuntimeException("....");
        }
        $this->strLayoutFilePath = $strLayoutFilePath;
        $this->arrUIContext = $arrUIContext;
        $this->arrDescFrag = $oPageSchema->buildSchema();
        $this->buildDataFragments();
        
    }        
    protected  function buildDataFragments(): void{
        foreach ($this->arrDescFrag as $strFragmentName => $value) {
            $this->arrDataFrag[$strFragmentName] = $this->dataAtFragment($strFragmentName);
            $this->buildViewInfoAtFragment($strFragmentName);
        }
    }
    protected function buildViewInfoAtFragment(string $strFragmentName): void{
        if($this->arrDescFrag[$strFragmentName]['type'] === 'link_view'){
            $this->arrDescFrag[$strFragmentName]['render_view'] = Response::sendHtmlFile($this->arrDescFrag[$strFragmentName]['path_view'],true,$this->arrUIContext);
        }
    }
    function renderPage(){
        Response::sendHtmlFile($this->strLayoutFilePath, false, ['desc_fragment'=>$this->arrDescFrag, 'data_fragment' => $this->arrDataFrag]);
    }
    abstract protected function dataAtFragment(string $strFragmentName):array;
}

