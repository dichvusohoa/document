<?php

namespace Core\View\HtmlSchema;
use UnexpectedValueException;
use Core\Utility\ValidUtility;
class HtmlFragmentSchemaData{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*các loại fragment type. Sau này có thể bổ sung sau*/
    public const FRAGMENT_TYPE_CSS_LINK  = 'css_link';
    public const FRAGMENT_TYPE_SCRIPT    = 'script';
    public const FRAGMENT_TYPE_VIEW      = 'view';
    public const FRAGMENT_TYPE_ELEMENT   = 'element';
    public const FRAGMENT_TYPE_TEXT      = 'text';
    /*---------------------------------------------------------------------------------------------------------------*/
    public const DATA_SOURCE_CONTROLLER = 'controller';
    public const DATA_SOURCE_OTHER      = 'other';
    /*---------------------------------------------------------------------------------------------------------------*/
    public const RENDER_MODE_SERVER = 'server';
    public const RENDER_MODE_CLIENT = 'client';

    /*---------------------------------------------------------------------------------------------------------------*/
    public const FAILURE_FAIL_PAGE = 'fail_page';
    public const FAILURE_FALLBACK  = 'fallback';
    public const FAILURE_OMIT      = 'omit';
    /*---------------------------------------------------------------------------------------------------------------*/
    public const UI_CONTEXT_NONE    = 'none';
    public const UI_CONTEXT_DEFAULT = 'default';
    public const UI_CONTEXT_CUSTOM  = 'custom';
     /*---------------------------------------------------------------------------------------------------------------*/
    protected const FRAGMENT_TYPES = [
        self::FRAGMENT_TYPE_CSS_LINK,
        self::FRAGMENT_TYPE_SCRIPT,
        self::FRAGMENT_TYPE_VIEW,
        self::FRAGMENT_TYPE_ELEMENT,
        self::FRAGMENT_TYPE_TEXT
    ];
    //các dạng fragment mà phần khung vỏ luôn được render tại server
    //kể cả khi render_mode = client
    protected const SERVER_RENDERED_SHELL = [
        self::FRAGMENT_TYPE_VIEW,
        self::FRAGMENT_TYPE_ELEMENT
    ];
    protected const DATA_SOURCES = [
        self::DATA_SOURCE_CONTROLLER,
        self::DATA_SOURCE_OTHER
    ];

    protected const RENDER_MODES = [
        self::RENDER_MODE_SERVER,
        self::RENDER_MODE_CLIENT
    ];

    protected const FAILURE_MODES = [
        self::FAILURE_FAIL_PAGE,
        self::FAILURE_FALLBACK,
        self::FAILURE_OMIT
    ];

    protected const ALLOWED_FIELDS = [
        'fragment_type',
        'data_source',
        'render_mode',
        'render_detail',
        'failure'
    ];
    protected const VIEW_DETAIL_FIELDS = [
        'file',
        'ui_context'
    ];

    protected const ELEMENT_DETAIL_FIELDS = [
        'tag',
        'attributes',
        'escape_html'
    ];
    protected const TEXT_DETAIL_FIELDS = [
        'escape_html'
    ];
    protected const UI_CONTEXTS = [
        self::UI_CONTEXT_NONE,
        self::UI_CONTEXT_DEFAULT,
        self::UI_CONTEXT_CUSTOM
    ];
    protected const VOID_HTML_ELEMENTS = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'source',
        'track',
        'wbr'
    ];

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function validate(
        mixed $arrData,
        string $strFragmentName 
    ): void {
        $strContext = "HTML fragment schema '{$strFragmentName}'";

        if (!is_array($arrData)) {
            throw new UnexpectedValueException(
                "{$strContext} phải là một array."
            );
        }

        ValidUtility::validateNoUnexpectedFields($arrData, self::ALLOWED_FIELDS, $strContext);
        self::validateFragmentType($arrData, $strContext);
        self::validateDataSource($arrData, $strContext);
        self::validateRenderMode($arrData, $strContext);
        self::validateRenderDetail($arrData, $strContext);
        self::validateFailure($arrData, $strContext);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function hasServerRenderedShell(
        string $strFragmentType
    ): bool {
        return in_array(
            $strFragmentType,
            self::SERVER_RENDERED_SHELL,
            true
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateFragmentType(
        array $arrData,
        string $strContext
    ): void {
        ValidUtility::validateRequiredEnumField(
            $arrData,
            'fragment_type',
            self::FRAGMENT_TYPES,
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateDataSource(
        array $arrData,
        string $strContext
    ): void {
        ValidUtility::validateRequiredEnumField(
            $arrData,
            'data_source',
            self::DATA_SOURCES,
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateRenderMode(
        array $arrData,
        string $strContext
    ): void {
        ValidUtility::validateRequiredEnumField(
            $arrData,
            'render_mode',
            self::RENDER_MODES,
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateRenderDetail(
        array $arrData,
        string $strContext
    ): void {
        /*
         * fragment_type đã được validate trước nên có thể truy cập trực tiếp.
         */
        $strFragmentType = $arrData['fragment_type'];

        if ($strFragmentType === self::FRAGMENT_TYPE_VIEW) {
            if (!array_key_exists('render_detail', $arrData)) {
                throw new UnexpectedValueException(
                    "{$strContext} thiếu field 'render_detail' "
                    . "khi 'fragment_type' là 'view'."
                );
            }

            self::validateViewRenderDetail(
                $arrData['render_detail'],
                $strContext
            );

            return;
        }

        if ($strFragmentType === self::FRAGMENT_TYPE_ELEMENT) {
            if (!array_key_exists('render_detail', $arrData)) {
                throw new UnexpectedValueException(
                    "{$strContext} thiếu field 'render_detail' "
                    . "khi 'fragment_type' là 'element'."
                );
            }

            self::validateElementRenderDetail(
                $arrData['render_detail'],
                $strContext
            );

            return;
        }
        if ($strFragmentType === self::FRAGMENT_TYPE_TEXT) {
            /*
             * Với text:
             *
             * - Không khai báo render_detail      → hợp lệ, escape_html mặc định true.
             * - render_detail = null              → hợp lệ, escape_html mặc định true.
             * - render_detail = []                → hợp lệ, escape_html mặc định true.
             * - escape_html = true|false          → hợp lệ.
             */
            if (
                !array_key_exists('render_detail', $arrData)
                || $arrData['render_detail'] === null
            ) {
                return;
            }

            self::validateTextRenderDetail(
                $arrData['render_detail'],
                $strContext
            );

            return;
        }

        /*
         * css_link, script không có render metadata riêng.
         * Nội dung của chúng không được đặt trong schema.
         */
        if (array_key_exists('render_detail', $arrData) && 
                $arrData['render_detail'] !== null) {
            throw new UnexpectedValueException(
                "{$strContext} khi 'fragment_type' là '{$strFragmentType}' thì field 'render_detail' hoặc là không khai báo, hoặc khai báo giá trị null"
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    //kiểm tra field 'render_detail' đối ứng với 'fragment_type' = view
    protected static function validateViewRenderDetail(
        mixed $arrRenderDetail,
        string $strContext
    ): void {
        if (!is_array($arrRenderDetail)) {
            throw new UnexpectedValueException(
                "{$strContext} field 'render_detail' phải là array "
                . "khi 'fragment_type' là 'view'."
            );
        }

        ValidUtility::validateNoUnexpectedFields(
            $arrRenderDetail,
            self::VIEW_DETAIL_FIELDS,
            $strContext,
            'render_detail'    
        );

        ValidUtility::validateRequiredNonEmptyStringField(
            $arrRenderDetail,
            'file',
            $strContext,
            'render_detail'
        );
        self::validateUiContext(
            $arrRenderDetail,
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateElementRenderDetail(
        mixed $arrRenderDetail,
        string $strContext
    ): void {
        if (!is_array($arrRenderDetail)) {
            throw new UnexpectedValueException(
                "{$strContext} field 'render_detail' phải là array "
                . "khi 'fragment_type' là 'element'."
            );
        }

        ValidUtility::validateNoUnexpectedFields(
            $arrRenderDetail,
            self::ELEMENT_DETAIL_FIELDS,
            $strContext,
            'render_detail'
        );
        //tag là field phải có trong render_detail
        if (!array_key_exists('tag', $arrRenderDetail)) {
            throw new UnexpectedValueException(
                "{$strContext} thiếu field 'render_detail.tag'."
            );
        }
        self::validateHtmlElementName(
            $arrRenderDetail['tag'],
            $strContext
        );
        //attributes là tùy chọn, chấp nhận không có 'attributes' hoặc 'attributes'=>null
        if (array_key_exists('attributes', $arrRenderDetail) && $arrRenderDetail['attributes']!==null) {
            self::validateAttributes(
                $arrRenderDetail['attributes'],
                $strContext
            );
        }
        self::validateEscapeHtml($arrRenderDetail,$strContext);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateTextRenderDetail(
        mixed $arrRenderDetail,
        string $strContext
    ): void {
        if (!is_array($arrRenderDetail)) {
            throw new UnexpectedValueException(
                "{$strContext} field 'render_detail' phải là array "
                . "khi 'fragment_type' là 'text'."
            );
        }

        ValidUtility::validateNoUnexpectedFields(
            $arrRenderDetail,
            self::TEXT_DETAIL_FIELDS,
            $strContext,
            'render_detail'
        );
        self::validateEscapeHtml($arrRenderDetail,$strContext);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateEscapeHtml(
        array $arrRenderDetail,
        string $strContext
    ): void {
        /*
         * Không khai báo escape_html thì dùng mặc định true.
         */
        if (!array_key_exists('escape_html', $arrRenderDetail)) {
            return;
        }

        if (!is_bool($arrRenderDetail['escape_html'])) {
            throw new UnexpectedValueException(
                "{$strContext} field 'render_detail.escape_html' "
                . "phải là bool; nhận được "
                . get_debug_type($arrRenderDetail['escape_html'])
                . "."
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateUiContext(
        array $arrRenderDetail,
        string $strContext
    ): void {
        /*
         * Không khai báo ui_context hoặc khai báo null
         * đều tương đương ui_context = none.
         */
        if (
            !array_key_exists('ui_context', $arrRenderDetail)
            || $arrRenderDetail['ui_context'] === null
        ) {
            return;
        }

        $mixUiContext = $arrRenderDetail['ui_context'];
        $strPath = 'render_detail.ui_context';

        if (!is_string($mixUiContext)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' phải là string hoặc null; "
                . 'nhận được '
                . get_debug_type($mixUiContext)
                . "."
            );
        }

        if (!in_array($mixUiContext, self::UI_CONTEXTS, true)) {
            $strAllowedValues = implode(
                "', '",
                self::UI_CONTEXTS
            );

            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' có giá trị không hợp lệ "
                . "'{$mixUiContext}'; các giá trị cho phép là "
                . "'{$strAllowedValues}'."
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateFailure(
        array $arrData,
        string $strContext
    ): void {
        /*
         * data_source đã được validate trước.
         */
        $strDataSource = $arrData['data_source'];

        if ($strDataSource === self::DATA_SOURCE_OTHER) {
            if (array_key_exists('failure', $arrData)
                && $arrData['failure'] !== null) {
                throw new UnexpectedValueException(
                    "{$strContext} khi 'data_source' là 'other' thì field 'failure' hoặc là không khai báo, hoặc khai báo giá trị null "
                );
            }

            return;
        }

        ValidUtility::validateRequiredEnumField(
            $arrData,
            'failure',
            self::FAILURE_MODES,
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateHtmlElementName(
        mixed $strTagName,
        string $strContext
    ): void {
        $strPath = 'render_detail.tag';

        if (!is_string($strTagName)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' phải là string."
            );
        }

        if ($strTagName === '') {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' không được rỗng."
            );
        }

        if ($strTagName !== trim($strTagName)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' không được có "
                . "khoảng trắng ở đầu hoặc cuối."
            );
        }

        if ($strTagName !== strtolower($strTagName)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' phải được viết thường."
            );
        }

        /*
         * Chấp nhận HTML element name hoặc custom element name đơn giản.
         *
         * Ví dụ hợp lệ:
         * div
         * section
         * my-component
         *
         * Ví dụ không hợp lệ:
         * <div>
         * div class="main"
         * div/span
         */
        if (
            preg_match('/^[a-z][a-z0-9-]*$/', $strTagName) !== 1
        ) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' chứa tên HTML element "
                . "không hợp lệ '{$strTagName}'."
            );
        }

        if (in_array($strTagName, self::VOID_HTML_ELEMENTS, true)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strPath}' không được dùng void element "
                . "'{$strTagName}', vì fragment_type='element' được render theo "
                . "dạng '<tag>content</tag>'."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateAttributes(
        mixed $arrAttributes,
        string $strContext
    ): void {
        $strBasePath = 'render_detail.attributes';

        if (!is_array($arrAttributes)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strBasePath}' phải là array."
            );
        }

        foreach ($arrAttributes as $strAttributeName => $mixAttributeValue) {
            if (!is_string($strAttributeName)) {
                throw new UnexpectedValueException(
                    "{$strContext} có tên attribute không phải string "
                    . "tại field '{$strBasePath}'."
                );
            }

            $strPath = "{$strBasePath}.{$strAttributeName}";

            if (!self::isValidHtmlAttributeName($strAttributeName)) {
                throw new UnexpectedValueException(
                    "{$strContext} field '{$strPath}' có tên HTML attribute "
                    . "không hợp lệ."
                );
            }

            /*
             * Quy ước:
             *
             * string|int|float:
             *     attribute có value.
             *
             * true:
             *     boolean attribute được render.
             *
             * false|null:
             *     attribute không được render.
             */
            if (
                !is_string($mixAttributeValue)
                && !is_int($mixAttributeValue)
                && !is_float($mixAttributeValue)
                && !is_bool($mixAttributeValue)
                && $mixAttributeValue !== null
            ) {
                throw new UnexpectedValueException(
                    "{$strContext} field '{$strPath}' chỉ chấp nhận "
                    . 'string, int, float, bool hoặc null; nhận được '
                    . get_debug_type($mixAttributeValue)
                    . '.'
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isValidHtmlAttributeName(
        string $strAttributeName
    ): bool {
        /*
         * Hỗ trợ các tên thông thường:
         *
         * class
         * data-fragment
         * aria-live
         * xml:lang
         * x-data
         */
         if ( 
            $strAttributeName === '' //không cho phép $strAttributeName blank
            || $strAttributeName !== trim($strAttributeName)//khong cho phép chứa khoảng trắng ở đầu và cuối
            || ($strAttributeName !== strtolower($strAttributeName)) //không cho phép dung chữ hoa     
        ) {
            return false;
        }

        return preg_match(
            '/^[a-z][a-z0-9_:.-]*$/',
            $strAttributeName
        ) === 1;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
  
}