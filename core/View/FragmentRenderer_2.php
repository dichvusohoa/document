<?php

namespace Core\View;

use Core\Utility\HtmlUtility;
use Core\View\HtmlSchema\HtmlFragmentSchemaData;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class FragmentRenderer{
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        protected readonly array $arrSchema,
        protected readonly array $arrFragmentData,
        protected readonly array $arrPositionToFragmentMap,
        protected readonly array $arrFragmentUiContext,
        protected readonly array $arrDefaultUiContext
    ) {
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function renderPosition(string $strPositionId): void
    {
        if (!array_key_exists($strPositionId, $this->arrPositionToFragmentMap)) {
            throw new RuntimeException(
                "Không tìm thấy fragment tại position '{$strPositionId}'."
            );
        }

        $strFragmentName =
            $this->arrPositionToFragmentMap[$strPositionId];

        $this->renderFragment($strFragmentName);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderFragment(string $strFragmentName): void{
        $arrFragmentSchema =
            $this->arrSchema[$strFragmentName];

        $arrDataContext =
            $this->arrFragmentData[$strFragmentName];

        $strFragmentType =
            $arrFragmentSchema['fragment_type'];

        $strRenderMode =
            $arrFragmentSchema['render_mode'];

        /*
         * Fragment render hoàn toàn ở client và không có
         * server-rendered shell thì server không xuất gì.
         */
        if (
            $strRenderMode === HtmlFragmentSchemaData::RENDER_MODE_CLIENT
            && !HtmlFragmentSchemaData::hasServerRenderedShell(
                $strFragmentType
            )
        ) {
            return;
        }

        switch ($strFragmentType) {
            case HtmlFragmentSchemaData::FRAGMENT_TYPE_SCRIPT:
                $this->renderScriptFragment($arrDataContext);
                return;

            case HtmlFragmentSchemaData::FRAGMENT_TYPE_CSS_LINK:
                $this->renderCssLinkFragment($arrDataContext);
                return;

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
        }

        /*
         * Bình thường không tới đây vì schema đã được validate.
         * Giữ exception để phòng trường hợp invariant bị phá vỡ.
         */
        throw new UnexpectedValueException(
            "Fragment type không được hỗ trợ: '{$strFragmentType}'."
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderScriptFragment(array $arrDataContext): void
    {
        echo HtmlUtility::toScriptLinks(
            PUBLIC_PATH,
            $arrDataContext['data']
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderCssLinkFragment(array $arrDataContext): void
    {
        echo HtmlUtility::toCssLinks(
            PUBLIC_PATH,
            $arrDataContext['data']
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

        echo $this->renderFileToString(
            $strFilePath,
            $arrVar
        );
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

            default =>
                throw new UnexpectedValueException(
                    "UI context mode không hợp lệ: "
                    . "'{$strUiContextMode}'."
                ),
        };
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

        $strAttributeHtml =
            $this->buildAttributeHtml($arrAttribute);

        echo "<{$strTagName}{$strAttributeHtml}>";

        if (
            $arrFragmentSchema['render_mode']
            === HtmlFragmentSchemaData::RENDER_MODE_SERVER
        ) {
            echo (string) $arrDataContext['data'];
        }

        echo "</{$strTagName}>";
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderTextFragment(
        array $arrFragmentSchema,
        array $arrDataContext
    ): void {
        $strText =
            (string) $arrDataContext['data'];

        $boolEscapeHtml =
            $arrFragmentSchema['render_detail']['escape_html']
            ?? true;

        echo $boolEscapeHtml
            ? htmlspecialchars(
                $strText,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
            : $strText;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildAttributeHtml(array $arrAttribute): string
    {
        $arrAttributeHtml = [];

        foreach ($arrAttribute as $strName => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $strEscapedName = htmlspecialchars(
                $strName,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            if ($value === true) {
                $arrAttributeHtml[] = $strEscapedName;
                continue;
            }

            $strEscapedValue = htmlspecialchars(
                (string) $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $arrAttributeHtml[] =
                "{$strEscapedName}=\"{$strEscapedValue}\"";
        }

        return $arrAttributeHtml === []
            ? ''
            : ' ' . implode(' ', $arrAttributeHtml);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function renderFileToString(
        string $strFilePath,
        array $arrVar = []
    ): string {
        if (!is_file($strFilePath)) {
            throw new RuntimeException(
                "View file not found: {$strFilePath}"
            );
        }

        extract($arrVar, EXTR_SKIP);

        ob_start();

        try {
            include $strFilePath;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}