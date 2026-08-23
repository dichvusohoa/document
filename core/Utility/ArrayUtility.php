<?php

namespace Core\Utility;

use InvalidArgumentException;

final class ArrayUtility
{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Lấy giá trị theo path.
     *
     * Ví dụ:
     *
     * $arrData = [
     *     'a' => [
     *         'b' => [
     *             'c' => 10
     *         ]
     *     ]
     * ];
     *
     * ArrayUtility::getByPath(
     *     $arrData,
     *     ['a', 'b', 'c']
     * );
     *
     * => 10
     *
     * Path rỗng [] đại diện cho root.
     */
    public static function getByPath(
        array $arrData,
        array|string $mixPath,
        mixed $default = null
    ): mixed {

        $arrPath = self::normalizePath(
            $mixPath,
            true
        );

        if ($arrPath === []) {
            return $arrData;
        }

        $mixValue = $arrData;

        foreach ($arrPath as $strElement) {

            if (
                !is_array($mixValue)
                || !array_key_exists(
                    $strElement,
                    $mixValue
                )
            ) {
                return $default;
            }

            $mixValue = $mixValue[$strElement];
        }

        return $mixValue;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Ghi giá trị theo path.
     *
     * Các intermediate element chưa tồn tại sẽ được tạo thành array.
     *
     * Nếu intermediate element đã tồn tại nhưng không phải array
     * thì sẽ được thay bằng array để có thể tiếp tục đi xuống.
     *
     * Ví dụ:
     *
     * ArrayUtility::setByPath(
     *     $arrData,
     *     ['a', 'b', 'c'],
     *     10
     * );
     */
    public static function setByPath(
        array &$arrData,
        array|string $mixPath,
        mixed $mixValue
    ): void {

        $arrPath = self::normalizePath(
            $mixPath,
            false
        );

        $ref = &$arrData;

        $iLastIndex = count($arrPath) - 1;

        foreach ($arrPath as $iIndex => $strElement) {

            /*
             * Element cuối cùng là nơi ghi value.
             */
            if ($iIndex === $iLastIndex) {
                $ref[$strElement] = $mixValue;
                break;
            }

            /*
             * Nếu intermediate element chưa tồn tại
             * hoặc không phải array thì tạo lại thành array.
             */
            if (
                !array_key_exists(
                    $strElement,
                    $ref
                )
                || !is_array($ref[$strElement])
            ) {
                $ref[$strElement] = [];
            }

            $ref = &$ref[$strElement];
        }

        unset($ref);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Xóa element theo path.
     *
     * Option:
     *
     *      prune_empty
     *
     *          false:
     *              chỉ xóa element cuối.
     *
     *          true:
     *              xóa tiếp các parent array bị rỗng.
     *
     * Return:
     *
     *      true
     *          element tồn tại và đã được xóa.
     *
     *      false
     *          path không tồn tại.
     */
    public static function removeByPath(
        array &$arrData,
        array|string $mixPath,
        array $arrOption = []
    ): bool {

        $arrPath = self::normalizePath(
            $mixPath,
            false
        );

        self::validateRemoveOption(
            $arrOption
        );

        $isPruneEmpty =
            $arrOption['prune_empty'] ?? false;

        return self::innerRemoveByPath(
            $arrData,
            $arrPath,
            0,
            $isPruneEmpty
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function innerRemoveByPath(
        array &$arrData,
        array $arrPath,
        int $iIndex,
        bool $isPruneEmpty
    ): bool {

        $strElement = $arrPath[$iIndex];

        if (
            !array_key_exists(
                $strElement,
                $arrData
            )
        ) {
            return false;
        }

        /*
         * Đã tới element cuối cùng.
         */
        if ($iIndex === count($arrPath) - 1) {

            unset($arrData[$strElement]);

            return true;
        }

        /*
         * Chưa tới cuối nhưng element hiện tại không phải array
         * => path không tồn tại.
         */
        if (!is_array($arrData[$strElement])) {
            return false;
        }

        $isRemoved = self::innerRemoveByPath(
            $arrData[$strElement],
            $arrPath,
            $iIndex + 1,
            $isPruneEmpty
        );

        /*
         * Nếu yêu cầu prune và child array đã rỗng,
         * xóa luôn child khỏi parent.
         */
        if (
            $isRemoved
            && $isPruneEmpty
            && $arrData[$strElement] === []
        ) {
            unset($arrData[$strElement]);
        }

        return $isRemoved;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateRemoveOption(
        array $arrOption
    ): void {

        foreach ($arrOption as $strOption => $mixValue) {

            if ($strOption !== 'prune_empty') {
                throw new InvalidArgumentException(
                    "ArrayUtility::removeByPath không hỗ trợ option '{$strOption}'."
                );
            }
        }

        if (
            array_key_exists(
                'prune_empty',
                $arrOption
            )
            && !is_bool($arrOption['prune_empty'])
        ) {
            throw new InvalidArgumentException(
                "Option 'prune_empty' phải là boolean."
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function normalizePath(
        array|string $mixPath,
        bool $isAllowEmpty = false
    ): array {
        $arrPath = is_array($mixPath)
        ? $mixPath
        : [$mixPath];

        $arrOption = [
            'item_non_empty' => true
        ];

        if (!$isAllowEmpty) {
            $arrOption['non_empty'] = true;
        }

        if (!ValidUtility::isStringList($arrPath, $arrOption)) {
            throw new InvalidArgumentException(
                'Array path không đúng format.'
            );
        }

        return $arrPath;
    }
}