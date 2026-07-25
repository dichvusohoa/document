<?php

namespace Core\View;

use Core\Http\Response;
use Core\Utility\HtmlUtility;
use Core\View\HtmlSchema\HtmlFragmentSchemaData;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class FragmentRenderer
{
    protected const CLIENT_BOOTSTRAP_ELEMENT_ID = 'fragment-bootstrap-data';

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        protected  array $arrSchema,
        protected  array $arrFragmentData,
        protected  array $arrPositionToFragmentMap,
        protected  array $arrFragmentUiContext,
        protected  array $arrDefaultUiContext
    ) {
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Render fragment được ánh xạ với một position trên layout.
     */
    public function renderPosition(string $strPositionId): void
    {
        /*
         * Position ID do file layout truyền vào nên vẫn cần guard.
         * Đây không phải validate lại arrPositionToFragmentMap.
         */
        if (
            !array_key_exists(
                $strPositionId,
                $this->arrPositionToFragmentMap
            )
        ) {
            throw new RuntimeException(
                "Không tìm thấy fragment được ánh xạ với position "
                . "'{$strPositionId}'."
            );
        }

        $strFragmentName =
            $this->arrPositionToFragmentMap[$strPositionId];

        $this->renderFragment($strFragmentName);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Sinh vùng JSON bootstrap cho các fragment render ở client.
     *
     * Method này chỉ gọi một lần trên layout, thông thường ở cuối body
     * và trước script khởi tạo jFragmentRenderer.
     */
    public function renderClientBootstrap(): void
    {
        $arrBootstrap = $this->buildClientBootstrap();

        if ($arrBootstrap['schema'] === []) {
            return;
        }

        $strJson =
            HtmlUtility::toEmbeddedJson($arrBootstrap);

        echo '<script'
            . ' id="' . self::CLIENT_BOOTSTRAP_ELEMENT_ID . '"'
            . ' type="application/json"'
            . '>';

        echo $strJson;

        echo '</script>';
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Luồng render chính:
     *
     * - Response lỗi + failure=omit:
     *     Không render.
     *
     * - Response lỗi + failure=fallback:
     *     Render fallback theo fragment_type.
     *
     * - Response thành công:
     *     Render bình thường theo fragment_type.
     *
     * failure=fail_page đã được BaseHtmlPageController xử lý bằng
     * Exception trước khi FragmentRenderer được tạo.
     */
    protected function renderFragment(string $strFragmentName): void
    {
        $arrFragmentSchema =
            $this->arrSchema[$strFragmentName];

        $arrDataContext =
            $this->arrFragmentData[$strFragmentName];

        if (!Response::isResponseOK($arrDataContext)) {
            if (
                $arrFragmentSchema['failure']
                === HtmlFragmentSchemaData::FAILURE_OMIT
            ) {
                return;
            }

            /*
             * Tới đây về nguyên tắc chỉ còn failure=fallback.
             */
            $this->renderFallbackFragment(
                $strFragmentName,
                $arrFragmentSchema,
                $arrDataContext
            );

            return;
        }

        $this->renderSuccessfulFragment(
            $strFragmentName,
            $arrFragmentSchema,
            $arrDataContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderSuccessfulFragment(
        string $strFragmentName,
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $strFragmentType =
            $arrFragmentSchema['fragment_type'];

        switch ($strFragmentType) {
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_VIEW:
                $this->renderViewFragment(
                    $strFragmentName,
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_ELEMENT:
                $this->renderElementFragment(
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_TEXT:
                $this->renderTextFragment(
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_CSS_LINK:
                $this->renderCssLinkFragment(
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_SCRIPT:
                $this->renderScriptFragment(
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;
        }

        /*
         * Bình thường không tới đây vì schema đã được validate.
         */
        throw new UnexpectedValueException(
            "Không hỗ trợ fragment_type '{$strFragmentType}' "
            . "tại fragment '{$strFragmentName}'."
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderFallbackFragment(
        string $strFragmentName,
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $strFragmentType =
            $arrFragmentSchema['fragment_type'];

        switch ($strFragmentType) {
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_VIEW:
                /*
                 * Server:
                 * View nhận nguyên Response lỗi qua $dataContext
                 * và tự quyết định cách hiển thị.
                 *
                 * Client:
                 * View chỉ tạo shell; Response lỗi được đưa xuống
                 * client bootstrap.
                 */
                $this->renderViewFragment(
                    $strFragmentName,
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_ELEMENT:
                $this->renderFallbackElementFragment(
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_TEXT:
                $this->renderFallbackTextFragment(
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_CSS_LINK:
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_SCRIPT:
                $this->renderFallbackComment(
                    $strFragmentName,
                    $arrFragmentSchema,
                    $arrDataContext
                );
                return;
        }

        throw new UnexpectedValueException(
            "Không hỗ trợ fallback cho fragment_type "
            . "'{$strFragmentType}' tại fragment "
            . "'{$strFragmentName}'."
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderViewFragment(
        string $strFragmentName,
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $strFilePath =
            $arrFragmentSchema['render_detail']['file'];

        $arrVar = [];

        /*
         * View render server nhận nguyên Response để tự xử lý
         * cả trường hợp thành công và fallback.
         *
         * View render client chỉ tạo shell. Data context được đưa
         * xuống bằng renderClientBootstrap().
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_SERVER
        ) {
            $arrVar['dataContext'] = $arrDataContext;
        }

        $arrUiContext = $this->resolveFragmentUiContext(
            $strFragmentName,
            $arrFragmentSchema
        );

        if ($arrUiContext !== null) {
            $arrVar['uiContext'] = $arrUiContext;
        }

        $this->renderFile(
            $strFilePath,
            $arrVar
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderElementFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $arrRenderDetail =
            $arrFragmentSchema['render_detail'];

        $strTagName =
            $arrRenderDetail['tag'];

        $arrAttribute =
            $arrRenderDetail['attributes'] ?? [];

        $strHtmlAttribute =
            HtmlUtility::toHtmlAttributeString(
                $arrAttribute
            );

        echo "<{$strTagName}{$strHtmlAttribute}>";

        /*
         * Element render client chỉ cần shell.
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_SERVER
        ) {
            $this->renderElementContent(
                $arrFragmentSchema,
                $arrDataContext['data']
            );
        }

        echo "</{$strTagName}>";
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderElementContent(
        array $arrFragmentSchema,
        mixed $data
    ): void {
        $boolEscapeHtml =
            $arrFragmentSchema['render_detail']['escape_html']
            ?? true;

        $strContent =
            (string) $data;

        echo $boolEscapeHtml
            ? HtmlUtility::escape($strContent)
            : $strContent;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderTextFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        /*
         * Text render client được xử lý hoàn toàn bởi JavaScript.
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_CLIENT
        ) {
            return;
        }

        $boolEscapeHtml =
            $arrFragmentSchema['render_detail']['escape_html']
            ?? true;

        $strText =
            (string) $arrDataContext['data'];

        echo $boolEscapeHtml
            ? HtmlUtility::escape($strText)
            : $strText;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderCssLinkFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        /*
         * Nếu css_link render client thì phía server không xuất link.
         * Dữ liệu của fragment được đưa xuống bootstrap cho client.
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_CLIENT
        ) {
            return;
        }

        echo HtmlUtility::toCssLinks(
            PUBLIC_PATH,
            $arrDataContext['data']
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderScriptFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        /*
         * Nếu script render client thì phía server không trực tiếp
         * tạo script links từ data của fragment.
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_CLIENT
        ) {
            return;
        }

        echo HtmlUtility::toScriptLinks(
            PUBLIC_PATH,
            $arrDataContext['data']
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderFallbackElementFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $arrRenderDetail =
            $arrFragmentSchema['render_detail'];

        $strTagName =
            $arrRenderDetail['tag'];

        $arrAttribute =
            $arrRenderDetail['attributes'] ?? [];

        $strHtmlAttribute =
            HtmlUtility::toHtmlAttributeString(
                $arrAttribute
            );

        echo "<{$strTagName}{$strHtmlAttribute}>";

        /*
         * Element render client chỉ cần shell.
         * JavaScript đọc Response lỗi trong bootstrap và tự hiển thị.
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_SERVER
        ) {
            $strText = APP_DEBUG
                ? $this->fallbackDataToString(
                    $arrDataContext['data']
                )
                : 'error';

            /*
             * Nội dung lỗi luôn escape, không phụ thuộc escape_html
             * của nội dung thành công.
             */
            echo HtmlUtility::escape($strText);
        }

        echo "</{$strTagName}>";
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderFallbackTextFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        /*
         * Text render client do JavaScript quyết định cách hiển thị:
         *
         * APP_DEBUG=true:
         *     Hiển thị data lỗi.
         *
         * APP_DEBUG=false:
         *     Hiển thị "error".
         */
        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_CLIENT
        ) {
            return;
        }

        $strText = APP_DEBUG
            ? $this->fallbackDataToString(
                $arrDataContext['data']
            )
            : 'error';

        echo HtmlUtility::escape($strText);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderFallbackComment(
        string $strFragmentName,
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $strFragmentType =
            $arrFragmentSchema['fragment_type'];

        $strComment =
            "Fragment '{$strFragmentName}' "
            . "of type '{$strFragmentType}' failed";

        if (APP_DEBUG) {
            $strError =
                $this->fallbackDataToString(
                    $arrDataContext['data']
                );

            if ($strError !== '') {
                $strComment .= ': ' . $strError;
            }
        }

        /*
         * "--" không hợp lệ bên trong HTML comment.
         */
        $strComment =
            str_replace('--', '—', $strComment);

        echo '<!-- '
            . HtmlUtility::escape($strComment)
            . ' -->';
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Chuyển dữ liệu fallback đơn giản thành chuỗi.
     *
     * Theo hợp đồng hiện tại:
     * - data lỗi fallback thường là string;
     * - trường hợp dự phòng có thể là array of string;
     * - không xử lý cấu trúc ErrorInfo phức tạp vì các lỗi đó thuộc
     *   failure=fail_page và đã kết thúc trước khi tới renderer.
     */
    protected function fallbackDataToString(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            $arrText = [];

            foreach ($data as $value) {
                if (is_string($value)) {
                    $arrText[] = $value;
                }
            }

            return implode('; ', $arrText);
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        return '';
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveFragmentUiContext(
        string $strFragmentName,
        array $arrFragmentSchema
    ): ?array {
        $strUiContextMode =
            $arrFragmentSchema['render_detail']['ui_context']
            ?? HtmlFragmentSchemaData::UI_CONTEXT_NONE;

        return match ($strUiContextMode) {
            HtmlFragmentSchemaData::UI_CONTEXT_NONE =>
                null,

            HtmlFragmentSchemaData::UI_CONTEXT_DEFAULT =>
                $this->arrDefaultUiContext,

            HtmlFragmentSchemaData::UI_CONTEXT_CUSTOM =>
                $this->arrFragmentUiContext[$strFragmentName],

            /*
             * Bình thường không tới đây vì schema đã được validate.
             */
            default =>
                throw new UnexpectedValueException(
                    "UI context mode '{$strUiContextMode}' "
                    . "không hợp lệ tại fragment "
                    . "'{$strFragmentName}'."
                ),
        };
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Tạo bootstrap cho các fragment có render_mode=client.
     *
     * Cấu trúc gần tương ứng với dữ liệu phía server:
     *
     * schema
     * dataContext
     * uiContext
     * defaultUiContext
     * debug
     */
    protected function buildClientBootstrap(): array
    {
        $arrClientSchema = [];
        $arrClientDataContext = [];
        $arrClientUiContext = [];

        foreach (
            $this->arrSchema
            as $strFragmentName => $arrFragmentSchema
        ) {
            if (
                $arrFragmentSchema['render_mode']
                !== HtmlFragmentSchemaData::RENDER_MODE_CLIENT
            ) {
                continue;
            }

            $arrDataContext =
                $this->arrFragmentData[$strFragmentName];

            /*
             * Client cũng không được render fragment lỗi có
             * failure=omit.
             */
            if (
                !Response::isResponseOK($arrDataContext)
                && $arrFragmentSchema['failure']
                    === HtmlFragmentSchemaData::FAILURE_OMIT
            ) {
                continue;
            }

            $arrClientSchema[$strFragmentName] =
                $this->buildClientFragmentSchema(
                    $arrFragmentSchema
                );

            $arrClientDataContext[$strFragmentName] =
                $arrDataContext;

            /*
             * arrFragmentUiContext là sparse map:
             * chỉ lưu fragment dùng custom UI context.
             */
            if (
                array_key_exists(
                    $strFragmentName,
                    $this->arrFragmentUiContext
                )
            ) {
                $arrClientUiContext[$strFragmentName] =
                    $this->arrFragmentUiContext[$strFragmentName];
            }
        }

        return [
            'schema' =>
                $arrClientSchema,

            'dataContext' =>
                $arrClientDataContext,

            'uiContext' =>
                $arrClientUiContext,

            'defaultUiContext' =>
                $this->arrDefaultUiContext,

            'debug' =>
                APP_DEBUG,
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Giữ schema phía client gần schema phía server nhất có thể,
     * nhưng loại bỏ dữ liệu thuần server.
     */
    protected function buildClientFragmentSchema(
        array $arrFragmentSchema
    ): array {
        $arrClientFragmentSchema =
            $arrFragmentSchema;

        /*
         * Không đưa đường dẫn file view phía server xuống client.
         */
        unset(
            $arrClientFragmentSchema[
                'render_detail'
            ]['file']
        );

        return $arrClientFragmentSchema;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Include file view với các biến đã chuẩn bị.
     */
    protected function renderFile(
        string $strFilePath,
        array $arrVar = []
    ): void {
        if (!is_file($strFilePath)) {
            throw new RuntimeException(
                "View file not found: {$strFilePath}"
            );
        }

        extract($arrVar, EXTR_SKIP);

        /*
         * Không dùng output buffer vì method này render trực tiếp
         * ra output của trang.
         */
        try {
            include $strFilePath;
        } catch (Throwable $e) {
            throw $e;
        }
    }
}