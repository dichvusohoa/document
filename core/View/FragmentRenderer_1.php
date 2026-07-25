<?php
namespace Core\View;
use Core\Utility\HtmlUtility;
use RuntimeException;
use Core\View\HtmlSchema\HtmlFragmentSchemaData;
/**
 * Description of HtmlFragmentRenderer
 *
 * @author admin
 */
class FragmentRenderer {
    public function __construct(
        protected readonly array $arrSchema,
        protected readonly array $arrFragmentData,
        protected readonly array $arrPositionToFragmentMap,    
        protected readonly array $arrFragmentUiContext, 
        protected readonly array $arrUiContextDefault      
    ) {
    }

    public function renderPosition(string $strPositionId): void{
        $strFragmentName = $this->arrPositionToFragmentMap[$strPositionId];
        $this->renderFragment($strFragmentName, 
                $this->arrSchema[$strFragmentName],
                $this->arrFragmentData[$strFragmentName]
                );
    }

    protected function renderFragment(
        string $strFragmentName,
        array $arrDesc,
        mixed $data
    ): void {
        $strFragmentType  = $arrDesc['fragment_type'];
        $strRenderMode = $arrDesc['render_mode'];
        
        if ($strRenderMode === HtmlFragmentSchemaData::RENDER_MODE_CLIENT  &&  
            !HtmlFragmentSchemaData::hasServerRenderedShell($strFragmentType)){
            return;
        }
        switch ($strFragmentType){
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_SCRIPT:
                echo HtmlUtility::toScriptLinks(PUBLIC_PATH, $data['data']);
            break;
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_CSS_LINK:
                echo HtmlUtility::toCssLinks(PUBLIC_PATH, $data['data']);
            break;    
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_VIEW:
                self::renderFragment_loại_view($strFragmentName, $arrDesc, $data);
            break;   
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_ELEMENT:
                self::renderFragment_loại_element($strFragmentName, $arrDesc, $data);
            break;
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_TEXT:
                self::renderFragment_loại_text($strFragmentName, $arrDesc, $data);
            break;
        }
        
    }
    protected function renderFragment_loại_view(
        string $strFragmentName,
        array $arrDesc,
        mixed $data
    ){
        $strRenderMode = $arrDesc['render_mode'];
        $strFilePath = $arrDesc['render_detail'];
        if(isset($this->arrFragmentUiContext[$strFragmentName])){
            $arrUiContext = $this->arrFragmentUiContext[$strFragmentName]; 
        } 
        else if($arrDesc['render_detail']['ui_context'] ==='default'){
            $arrUiContext = $this->arrUiContextDefault;
        }
        else{
            $arrUiContext = null;
        }
        if($arrUiContext){
            if(isset($arrUiContext['extra'])){
                $strUiVarName = $arrUiContext['extra'];
            }
            else{
                $strUiVarName = 'ui_context'; //tên biến mặc định
            }
            
        }
        
        if($strRenderMode === HtmlFragmentSchemaData::RENDER_MODE_SERVER){
            if(isset($data['extra'])){
                $strDataVarName = $data['extra'];
            }
            else{
                $strDataVarName = ['data_context'];//tên mặc định
            }
            
        }
        elseif($strRenderMode === HtmlFragmentSchemaData::RENDER_MODE_CLIENT){
            $strDataVarName = null;
        } 
        if(isset($arrUiContext) && isset($strDataVarName)){
            $arrVar = [$strUiVarName => $arrUiContext, $strDataVarName =>$data];
        }
        else if(isset($arrUiContext)){
            $arrVar = [$strUiVarName => $arrUiContext];
        }
        else if(isset($strDataVarName)){
            $arrVar = [$strDataVarName => $data];
        }
        else{
            $arrVar = [];
        }
        self::renderFileToString($strFilePath,$arrVar);
    }
    protected function renderFragment_loại_element(
        string $strFragmentName,
        array $arrDesc,
        mixed $data
    ){
        $strRenderMode = $arrDesc['render_mode'];
        $strOpenTag = "....";//thiếu code tạo openTag
        $strCloseTag = "....";//thiếu code tạo closeTag
        if($strRenderMode === HtmlFragmentSchemaData::RENDER_MODE_SERVER){
            echo $strOpenTag.$data.$strCloseTag;
        }
        elseif($strRenderMode === HtmlFragmentSchemaData::RENDER_MODE_CLIENT){
            echo $strOpenTag.$strCloseTag;
        }
    }
    protected function renderFragment_loại_text(
        string $strFragmentName,
        array $arrDesc,
        mixed $data
    ){
        //cần căn cứ theo  render_detail['escape_html'] để render ra $data cho phù hợp
    }
    public static function renderFileToString(
        string $strFilePath,
        array $arrVars = []
    ): string {
        if (!is_file($strFilePath)) {
            throw new RuntimeException(
                "View file not found: {$strFilePath}"
            );
        }
        extract($arrVars, EXTR_SKIP);
        ob_start();
        try {
            include $strFilePath;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

}
