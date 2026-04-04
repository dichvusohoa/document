<?php
namespace Core\Models;
use Core\Models\Utility\HtmlUtility;
class HtmlFragment {
    public static  function render(string $strName, array $arrDescFrag, array $arrDataFrag) {
        $type = $arrDescFrag['type'];
        switch ($type){
            case 'title':
            if(isset($arrDataFrag['data'])){
                echo '<title>'.htmlspecialchars($arrDataFrag['data'], ENT_QUOTES, 'UTF-8').'</title>';
            }
            break;
            case 'css':
            if(isset($arrDataFrag['data'])){
                echo HtmlUtility::toCssLinks(PUBLIC_PATH, $arrDataFrag['data']);
            }
            break;
            case 'script':
            if(isset($arrDataFrag['data'])){
                echo HtmlUtility::toScriptLinks(PUBLIC_PATH, $arrDataFrag['data']);
            }
            break;
            case 'link_view':
            if(isset($arrDescFrag['render_view'])){
                echo $arrDescFrag['render_view'];
            }
            break; 
            case 'embed_view':
            $strTagName = $arrDescFrag['tag_name'] ?? 'div';
            echo "<$strTagName id=\"" . htmlspecialchars($strName) . "\"></$strTagName>";
            break;
        }
        
    }
}