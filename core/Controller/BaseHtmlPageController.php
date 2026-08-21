<?php
namespace Core\Controller;
use Core\Auth\AuthResponse;
use Core\Database\DataAccessException;
use Core\Http\Response;
use Core\View\FragmentRenderer;
use Core\View\HtmlSchema\BaseHtmlPageSchema;
use Core\View\HtmlSchema\HtmlFragmentSchemaData;
use LogicException;
use PDOException;
use UnexpectedValueException;

/*BaseHtmlPageController vẫn là abstract nên chưa cần implement resolveParam*/
abstract class BaseHtmlPageController extends BaseController{
    protected BaseHtmlPageSchema $htmlSchema;
    protected array  $arrDefaultUiContext;
    
    public function __construct(BaseHtmlPageSchema $htmlSchema){
        parent::__construct($htmlSchema->getRequestAuthContext());
        $this->htmlSchema = $htmlSchema;
    }        
    protected  function buildFragmentData(): array{
        $arrFragmentData = [];
        foreach ( $this->htmlSchema->getSchema() as $strFragmentName => $arrFragmentSchema ) {
            if ($arrFragmentSchema['data_source'] !== HtmlFragmentSchemaData::DATA_SOURCE_CONTROLLER) {
                continue;
            }
            //HtmlFragmentSchemaData đảm bảo có $arrFragmentSchema['failure'] khi $arrFragmentSchema['data_source']
            //là controller
            $arrFragmentData[$strFragmentName]
                = $this->enforceDataAtFragmentContract($strFragmentName, $arrFragmentSchema['failure']);
        }
        return $arrFragmentData;
    }
    protected function buildFragmentUiContext(): array{
        $arrFragmentUiContext = [];

        foreach (
            $this->htmlSchema->getSchema()
            as $strFragmentName => $arrFragmentSchema
        ) {
            /*
             * Không khai báo ui_context hoặc ui_context = null
             * tương đương UI_CONTEXT_NONE.
             */
            $strUiContext =
                $arrFragmentSchema['render_detail']['ui_context']
                ?? HtmlFragmentSchemaData::UI_CONTEXT_NONE;

            if (
                $strUiContext
                === HtmlFragmentSchemaData::UI_CONTEXT_CUSTOM
            ) {
                $arrFragmentUiContext[$strFragmentName]
                = $this->uiContextAtFragment($strFragmentName);
            }

            
        }
        return $arrFragmentUiContext;
    }
    protected function buildDefaultUiContext(): array{
        $arrAuthResponse = $this->getRequestAuthContext()->auth();
        $arrSessionInfo = $arrAuthResponse['data'];
        //lọc bỏ chỉ lấy các field cần thiết cho uicontext
        return [
            'id' => $arrSessionInfo['id'],
            'name' => $arrSessionInfo['name'],
            'subscriber_id' => $arrSessionInfo['subscriber_id'],
            'roles' => $arrSessionInfo['roles'],
            'registered_modules' => $arrSessionInfo['registered_modules'],
            'is_authenticated' => AuthResponse::isAuthenticated($arrAuthResponse)
        ];
    }
    //ví dụ sau này hàm lấy dữ liệu có thể là index(), list() có thể call lại hàm này
    public function renderPage(): void{
        $this->arrDefaultUiContext = $this->buildDefaultUiContext();
        $arrFragmentData = $this->buildFragmentData();

        $arrFragmentUiContext = $this->buildFragmentUiContext();
        $fragmentRenderer = new FragmentRenderer(
            $this->htmlSchema->getSchema(),
            $arrFragmentData,
            $this->htmlSchema->getPositionToFragmentMap(),
            $arrFragmentUiContext,
            $this->arrDefaultUiContext    
        );
        Response::sendHtmlFile($this->htmlSchema->getLayoutFilePath(), false, ['fragmentRenderer' => $fragmentRenderer]);
    }
    final protected function enforceDataAtFragmentContract(
        string $strFragmentName,
        string $strFailure
    ): array {
        try {
            $arrResult =
                $this->dataAtFragment($strFragmentName);
        } catch (PDOException $e) {
            throw new LogicException(
                "dataAtFragment('{$strFragmentName}') "
                . 'không được phép để PDOException thoát ra ngoài.',
                0,
                $e
            );
        } catch (DataAccessException $e) {
            if (
                $strFailure
                !== HtmlFragmentSchemaData::FAILURE_FAIL_PAGE
            ) {
                throw new LogicException(
                    "dataAtFragment('{$strFragmentName}') "
                    . 'không được phép ném DataAccessException '
                    . "khi failure = '{$strFailure}'.",
                    0,
                    $e
                );
            }

            throw $e;
        }

        if (!Response::isValid($arrResult)) {
            throw new UnexpectedValueException(
                "dataAtFragment('{$strFragmentName}') "
                . 'phải trả về Response format hợp lệ.'
            );
        }

        if (
            $strFailure
                === HtmlFragmentSchemaData::FAILURE_FAIL_PAGE
            && Response::isResponseError($arrResult)
        ) {
            throw new LogicException(
                "dataAtFragment('{$strFragmentName}') "
                . 'phải trả Response thành công hoặc ném '
                . 'DataAccessException khi '
                . "failure = '"
                . HtmlFragmentSchemaData::FAILURE_FAIL_PAGE
                . "'."
            );
        }

        return $arrResult;
    }
    protected function uiContextAtFragment(string $strFragmentName): array {
        return $this->arrDefaultUiContext;
    }        
    abstract protected function dataAtFragment(string $strFragmentName):array;
}

