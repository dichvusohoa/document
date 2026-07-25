<?php
namespace Core\View;
use Core\Utility\HtmlUtility;
use RuntimeException;
/**
 * Description of HtmlFragmentRenderer
 *
 * @author admin
 */
class HtmlFragment {
    public function __construct(
        protected array $arrSchema,
        protected array $arrDataFragment,
        protected array $arrUiContext    
    ) {
    }

    public function renderPosition(string $strPositionId): void{
        foreach ($this->arrSchema as $strFragmentName => $arrDesc) {
            if (($arrDesc['position_id'] ?? null) !== $strPositionId) {
                continue;
            }

            $this->renderFragment(
                $strFragmentName,
                $arrDesc,
                $this->arrDataFragment[$strFragmentName] ?? null
            );

            return;
        }
    }

    protected function renderFragment(
        string $strFragmentName,
        array $arrDesc,
        mixed $dataFragment
    ): void {
        $strRenderMode = $arrDesc['render_mode'];
        $strFragmentType = arrDesc['fragment_type'] ?? null;
        $isX = $strRenderMode === 'server' ;
        switch ($strFragmentType){
            case 'title':
                if($isX && isset($dataFragment['data'])){
                    echo '<title>'.htmlspecialchars($dataFragment['data'], ENT_QUOTES, 'UTF-8').'</title>';
                }
                return;
            case 'script':
                if($isX && isset($dataFragment['data'])){
                    echo HtmlUtility::toScriptLinks(PUBLIC_PATH, $dataFragment['data']);
                }
                return;
            case 'css':
                if($isX && isset($dataFragment['data'])){
                    echo HtmlUtility::toCssLinks(PUBLIC_PATH, $dataFragment['data']);
                }
                return;
            case 'text':
                $data = ($isX && isset($dataFragment['data'])) ? htmlspecialchars($dataFragment['data']) : '';
                break;
            case 'raw':        
                $data = ($isX && isset($dataFragment['data'])) ? $dataFragment['data'] : '';
                break;
        }
        //from here thì còn các trường hợp $strRenderMethod = raw, text
        $strShellType = $arrDesc['shell']['type'] ?? 'none';
        switch($strShellType){
            case 'view':
                $strFilePath = $arrDesc['shell']['file'];
                if($isX && isset($dataFragment['data'])){
                    //nếu render trên server thì đưa cả dữ liệu data vào. Trong file.phtml sẽ dùng biến 
                    //'data_context', biến ui_context để sinh dữ liệu trên server
                    $arrVar = ['data_context' => $dataFragment, 'ui_context' => $this->arrUiContext];
                }
                else{
                    $arrVar = ['ui_context' => $this->arrUiContext];
                }
                echo self::renderFileToString($strFilePath,$arrVar);
                return;
            case 'container':
                $arrTagContainer = [$openTag, $closeTag];// hàm gì đó tính toán ra được, viết thêm vào    
            break;
            case 'none':
                $arrTagContainer  = [];
            break;    
            default :
                $arrTagContainer  = [];
        }
        $str = empty($arrTagContainer)? $data : $openTag.$data.$closeTag;
        echo $str;
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
