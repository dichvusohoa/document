<?php

namespace Core\Utility;

use InvalidArgumentException;
use ReflectionReference;
use Core\Validator\ArrayShape;
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

        $arrPath = self::normalizeStringList(
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

        $arrPath = self::normalizeStringList(
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

        $arrPath = self::normalizeStringList(
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
    /**
     * Chuyển string thành list một phần tử hoặc kiểm tra list string có sẵn.
     * Các phần tử phải là string không rỗng; không ép kiểu, trim hay tách chuỗi.
     * $isAllowEmpty chỉ cho phép list rỗng [], không cho phép phần tử rỗng.
     */
    public static function normalizeStringList(
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

        if (!ArrayShape::isStringList($arrPath, $arrOption)) {
            throw new InvalidArgumentException(
                'String list không đúng format.'
            );
        }

        return $arrPath;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Chuẩn hóa từng segment của path theo quy tắc key của PHP array.
     * Ví dụ: ['users', '1', '01', '+1'] thành ['users', 1, '01', '+1'].
     * Không ép mọi chuỗi số sang int: '01' và số vượt miền int vẫn là string.
     * Giữ nguyên index, thứ tự và số phần tử của path; không sửa array đầu vào.
     *
     * @param array<int|string, int|string> $arrPath
     * @return array<int|string, int|string>
     */
    public static function normalizePathKeys(array $arrPath): array
    {
        $arrNormalizedPath = [];

        foreach ($arrPath as $index => $segment) {
            // Dùng segment làm key để PHP áp dụng đúng quy tắc chuyển kiểu key.
            $arrSingleKey = [$segment => true];
            $arrNormalizedPath[$index] = array_key_first($arrSingleKey);
        }

        return $arrNormalizedPath;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra dữ liệu lồng nhau theo các kiểu và điều kiện được phép.
     *
     * Options:
     * - allowed_types: list tên kiểu được phép; mặc định
     *   ['array', 'int', 'float', 'string', 'bool', 'null'].
     *   Có thể thêm 'object', 'resource'; áp dụng cho value, không kiểm tra key.
     * - allow_references: cho phép phần tử array chứa reference; mặc định false.
     * - allow_non_finite_floats: cho phép NaN/INF/-INF; mặc định false.
     *
     * Object/resource được coi là leaf, không duyệt property object.
     * Array có vòng tham chiếu luôn bị từ chối, kể cả khi cho phép reference.
     * Không sửa dữ liệu đầu vào, không kiểm tra nội dung/encoding của string.
     *
     * $iMaxDepth:
     * - null: không đặt giới hạn.
     * - 0: chỉ cho phép giá trị đầu vào, không có phần tử con.
     * - Mỗi lần xuống một phần tử, kể cả scalar/null, tăng độ sâu thêm 1.
     *
     * @throws InvalidArgumentException Khi option hoặc dữ liệu không hợp lệ.
     */
    public static function validateNestedArray(
        mixed $value,
        array $arrOption = [],
        ?int $iMaxDepth = null
    ): void {
        $arrDefault = [
            'allowed_types' => ['array', 'int', 'float', 'string', 'bool', 'null'],
            'allow_references' => false,
            'allow_non_finite_floats' => false,
        ];

        // Không bỏ qua option viết sai tên.
        foreach ($arrOption as $name => $_) {
            if (!array_key_exists($name, $arrDefault)) {
                throw new InvalidArgumentException('Unknown option: ' . $name);
            }
        }
        //chuyển các phần tử default trong $arrDefault sang $arrOption nếu như $arrOption không khai báo không
        $arrOption = array_replace($arrDefault, $arrOption);

        if (!ArrayShape::isStringList($arrOption['allowed_types'])) {
            throw new InvalidArgumentException('allowed_types must be a list of strings.');
        }
        //begin: validate các type trong $arrOption['allowed_types'] phải nằm trong danh sách cho phép
        $arrSupportedTypes = ['array', 'int', 'float', 'string', 'bool', 'null', 'object', 'resource'];
        foreach ($arrOption['allowed_types'] as $type) {
            if (!in_array($type, $arrSupportedTypes, true)) {
                throw new InvalidArgumentException('Unsupported type: ' . $type);
            }
        }
        //end: validate các type trong $arrOption['allowed_types'] phải nằm trong danh sách cho phép
        foreach (['allow_references', 'allow_non_finite_floats'] as $name) {
            if (!is_bool($arrOption[$name])) {
                throw new InvalidArgumentException($name . ' must be bool.');
            }
        }

        if ($iMaxDepth !== null && $iMaxDepth < 0) {
            throw new InvalidArgumentException('iMaxDepth must not be negative.');
        }

        // Chỉ theo dõi reference trên nhánh đệ quy đang chạy.
        $arrActiveReferences = [];
        self::innerValidateNestedArray($value, $arrOption, $iMaxDepth, $arrActiveReferences);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    private static function innerValidateNestedArray(
        mixed $value,
        array $arrOption,
        ?int $iRemainingDepth,
        array &$arrActiveReferences
    ): void {
        if ($iRemainingDepth !== null && $iRemainingDepth < 0) {
            throw new InvalidArgumentException('Data exceeds maximum depth.');
        }

        // Đổi tên kiểu của gettype() sang tên dùng trong allowed_types.
        $type = match (gettype($value)) {
            'integer' => 'int',
            'double' => 'float',
            'boolean' => 'bool',
            'NULL' => 'null',
            default => gettype($value),
        };

        if (!in_array($type, $arrOption['allowed_types'], true)) {
            throw new InvalidArgumentException('Disallowed type: ' . $type);
        }

        if ($type === 'float' && !is_finite($value) && !$arrOption['allow_non_finite_floats']) {
            throw new InvalidArgumentException('Non-finite floats are not allowed.');
        }

        if ($type !== 'array') {
            return;
        }

        foreach ($value as $key => $child) {
            $reference = ReflectionReference::fromArrayElement($value, $key);
            $referenceId = null;

            if ($reference !== null) {//$child là 1 tham chiếu
                if (!$arrOption['allow_references']) {
                    throw new InvalidArgumentException('References are not allowed.');
                }
                //chống tham chiếu vòng "circular reference", ở đây điều kiện is_array($child)
                //là chỉ chống cho phần tử dạng array, nếu muốn chống toàn bộ kiểu khác có thể bỏ if (is_array($child)) đi
                if (is_array($child)) { 
                    $referenceId = $reference->getId();
                    if (isset($arrActiveReferences[$referenceId])) {
                        throw new InvalidArgumentException('Cyclic arrays are not allowed.');
                    }
                    $arrActiveReferences[$referenceId] = true;
                }
            }

            try {
                self::innerValidateNestedArray(
                    $child,
                    $arrOption,
                    $iRemainingDepth === null ? null : $iRemainingDepth - 1,
                    $arrActiveReferences
                );
            } finally {
                if ($referenceId !== null) {
                    /*Minh họa Đang đi sâu: A → B → C
                    Map đang giữ: A, B, C

                    Xử lý xong C, quay về B:
                    Xóa dấu C, vẫn giữ A và B.

                    Chuyển sang nhánh D của B:
                    Map đang giữ A, B, D.*/
                    unset($arrActiveReferences[$referenceId]);
                }
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /** Tác dụng: Trả về 1 array đã được ngắt các tham chiếu lồng nhau*/
    public static function detachReferences(array $arrInput): array
    {
        $arrOutput = [];
        foreach ($arrInput as $mixKey => $mixValue) {
            $arrOutput[$mixKey] = is_array($mixValue) ? self::detachReferences($mixValue) : $mixValue;
        }
        return $arrOutput;
    }
}
