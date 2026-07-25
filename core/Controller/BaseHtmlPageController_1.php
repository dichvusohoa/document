<?php
namespace Core\Controller;
use Core\View\HtmlSchema\BaseHtmlPageSchema;
use PDOException;
use Core\Http\Response;
use Core\View\FragmentRenderer;
use Core\Controller\BaseController;
use Core\Database\DataAccessException;

/*BaseHtmlPageController vẫn là abstract nên chưa cần implement resolveParam*/
abstract class BaseHtmlPageController extends BaseController{
    protected BaseHtmlPageSchema $htmlSchema;
    protected array  $arrUiContextDefault;
    
    function __construct(BaseHtmlPageSchema $htmlSchema){
        parent::__construct($htmlSchema->getRequestAuthContext());
        $this->htmlSchema = $htmlSchema;
    }        
    protected  function buildDataFragments(): array{
        $arrDataFrag = [];
        foreach ( $this->htmlSchema->getSchema() as $strFragment => $arrDesc ) {
            if ($arrDesc['data_source'] !== 'controller') {
                continue;
            }
            $arrDataFrag[$strFragment]
                = $this->enforceDataAtFragmentContract($strFragment, $arrDesc);
        }
        return $arrDataFrag;
    }
    protected  function buildUiContextFragments(): array{
        $arr = [];
        foreach ( $this->htmlSchema->getSchema() as $strFragment => $arrDesc ) {
            if ($arrDesc['render_detail']['ui_context'] === 'custom') {
                $arr[$strFragment] = $this->uiContextAtFragment($strFragment);
            }
            
        }
        return $arr;
    }
    public function buildUiContextDefault(?array $arrUiFactor =null){
        $arrUiContext = $this->getRequestAuthContext();
        //cần bổ sung một hàm lọc bỏ các field như password trong $arrUiContext đi
        if($arrUiFactor !== null || empty($arrUiContext)){
            $this->arrUiContextDefault = $arrUiContext;
        }
        else{
            $this->arrUiContextDefault = [
                'status'=>$arrUiContext['status'], 
                'data' => array_merge($arrUiContext['data'], $arrUiFactor),
                'extra'=>$arrUiContext['extra']];
        }
    }
    //ví dụ sau này hàm lấy dữ liệu có thể là index(), list() có thể call lại hàm này
    public function renderPage(?array $arrUiFactor = null){
        $this->buildUiContextDefault($arrUiFactor);
        $arrDataFragment =
        $this->buildDataFragments();
        $arrUi = $this->buildUiContextFragments();
        $fragmentRenderer = new FragmentRenderer(
            $this->htmlSchema->getSchema(),
            $arrDataFragment,
            $this->htmlSchema->getPositionToFragmentMap(),
            $arrUi,
            $this->arrUiContextDefault    
        );
        
        Response::sendHtmlFile($this->htmlSchema->getLayoutFilePath(), false, $fragmentRenderer);
    }
    final protected function enforceDataAtFragmentContract(string $strFragment,  array $arrDesc ){
        try {
           $arrResult = $this->dataAtFragment($strFragment);
        } catch (PDOException $e) {
            throw new \LogicException(
                "dataAtFragment('{$strFragment}') không được phép "
                . 'để PDOException thoát ra ngoài.',
                0,
                $e
            );
        } catch (DataAccessException $e) {
            if ($arrDesc['failure'] !== 'fail_page') {
                throw new \LogicException(
                    "dataAtFragment('{$strFragment}') không được phép "
                    . "ném DataAccessException khi failure != 'fail_page'.",
                    0,
                    $e
                );
            }

            throw $e;
        }

        if (!Response::isValid($arrResult)) {
            throw new \UnexpectedValueException(
                "dataAtFragment('{$strFragment}') "
                . 'phải trả về Response format hợp lệ.'
            );
        }

        if (
            $arrDesc['failure'] === 'fail_page'
            && Response::isResponseError($arrResult)
        ) {
            throw new \LogicException(
                "dataAtFragment('{$strFragment}') phải trả Response thành công "
                . "hoặc ném DataAccessException khi failure = 'fail_page'."
            );
        }
        return $arrResult;
    }
    protected function uiContextAtFragment(string $strFragment){
        return $this->arrUiContextDefault;
    }        
    abstract protected function dataAtFragment(string $strFragment):array;
}

