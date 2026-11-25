<?php

namespace Core\Validator;

use InvalidArgumentException;
use UnexpectedValueException;
use Core\Normalization\AtomicSchema;
use Core\Normalization\AtomicNormalizer;
use Core\Utility\StringUtility;

class DataFields extends BaseValidator
{
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function validateNoUnexpectedFields(
        array $arrData,
        array $arrAllowedFields,
        string $strContext,
        ?string $strParentPath = null
    ): void {
        foreach (array_keys($arrData) as $mixFieldName) {
            /*
             * Primitive check đơn giản:
             * không cần AtomicNormalizer.
             */
            if (!is_string($mixFieldName)) {
                $strParentInfo = $strParentPath === null
                    ? ''
                    : " tại '{$strParentPath}'";

                throw new UnexpectedValueException(
                    "{$strContext}{$strParentInfo} có field name "
                    . 'không phải string; nhận được '
                    . get_debug_type($mixFieldName)
                    . '.'
                );
            }

            $strFieldPath = self::buildFieldPath(
                $mixFieldName,
                $strParentPath
            );

            if (
                !in_array(
                    $mixFieldName,
                    $arrAllowedFields,
                    true
                )
            ) {
                throw new UnexpectedValueException(
                    "{$strContext} chứa field không hợp lệ "
                    . "'{$strFieldPath}'."
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra field bắt buộc.
     *
     * Field luôn phải tồn tại.
     *
     * Nếu không truyền option thì chỉ kiểm tra field tồn tại.
     *
     * Nullable được biểu diễn bằng dấu "?" ở đầu type.
     * Dấu "?" chỉ cho phép value = null,
     * không làm field trở thành optional.
     *
     * Các type hỗ trợ:
     *
     * string | ?string
     *     non_empty => bool
     *     trimmed   => bool
     *     values    => array
     *
     * int | ?int
     *     min       => int
     *
     * bool | ?bool
     *
     * array | ?array
     *     non_empty => bool
     *     unique    => bool
     *
     * string_list | ?string_list
     *     non_empty      => bool
     *     item_non_empty => bool
     *     unique         => bool
     */
    public static function validateRequiredField(
        array $arrData,
        string $strFieldName,
        string $strContext,
        array $arrOption = [],
        ?string $strParentPath = null
    ): void {
        $strFieldPath = self::buildFieldPath(
            $strFieldName,
            $strParentPath
        );

        /*
         * required thuộc tầng DataFields,
         * không thuộc AtomicSchema.
         */
        if (!array_key_exists($strFieldName, $arrData)) {
            throw new UnexpectedValueException(
                "{$strContext} thiếu field '{$strFieldPath}'."
            );
        }

        /*
         * Không có option:
         * chỉ yêu cầu field tồn tại.
         */
        if ($arrOption === []) {
            return;
        }

        /*
         * Primitive rule check.
         */
        if (
            !array_key_exists('type', $arrOption)
            || !is_string($arrOption['type'])
            || $arrOption['type'] === ''
        ) {
            throw new InvalidArgumentException(
                __METHOD__
                . ": option 'type' là bắt buộc "
                . 'và phải là string không rỗng.'
            );
        }

        /*
         * Parse nullable type.
         *
         * string  -> nullable=false, type=string
         * ?string -> nullable=true,  type=string
         */
        $strType = $arrOption['type'];

        $boolNullable = str_starts_with(
            $strType,
            '?'
        );

        if ($boolNullable) {
            $strType = substr(
                $strType,
                1
            );

            if ($strType === '') {
                throw new InvalidArgumentException(
                    __METHOD__
                    . ": option 'type' không hợp lệ."
                );
            }
        }

        /*
         * Kiểm tra cấu trúc option theo type trước khi nullable return.
         *
         * Ví dụ:
         *
         * ?bool + min
         *
         * vẫn là rule sai.
         */
        $arrAllowedOption = match ($strType) {
            AtomicSchema::TYPE_STRING => [
                'type',
                'non_empty',
                'trimmed',
                'values'
            ],

            AtomicSchema::TYPE_INT => [
                'type',
                'min'
            ],

            AtomicSchema::TYPE_BOOL => [
                'type'
            ],

            'array' => [
                'type',
                'non_empty',
                'unique'
            ],

            'string_list' => [
                'type',
                'non_empty',
                'item_non_empty',
                'unique'
            ],

            default => throw new InvalidArgumentException(
                __METHOD__
                . ": type '{$arrOption['type']}' "
                . 'không được hỗ trợ.'
            )
        };

        self::validateOptions(
            $arrOption,
            $arrAllowedOption,
            __METHOD__
        );

        $mixFieldValue = $arrData[$strFieldName];

        /*
         * Semantics nullable đã chốt:
         *
         * ?type + null
         *     -> hợp lệ ngay.
         *
         * Các constraint của nhánh non-null
         * không cần được kiểm tra.
         */
        if ($mixFieldValue === null) {
            if ($boolNullable) {
                return;
            }

            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' "
                . 'không được là null.'
            );
        }

        switch ($strType) {
            /*-------------------------------------------------------------------------------------------------------*/
            case AtomicSchema::TYPE_STRING:
                $arrAtomicSchema = [
                    AtomicSchema::FIELD_TYPE
                        => AtomicSchema::TYPE_STRING
                ];

                /*
                 * DataFields:
                 *
                 * non_empty=true
                 *
                 * tương ứng AtomicSchema:
                 *
                 * min_length=1
                 */
                if (
                    self::boolOption(
                        $arrOption,
                        'non_empty'
                    )
                ) {
                    $arrAtomicSchema[
                        AtomicSchema::FIELD_MIN_LENGTH
                    ] = 1;
                }

                /*
                 * DataFields:
                 *
                 * trimmed=true
                 *
                 * tương ứng AtomicSchema:
                 *
                 * trim=true
                 *
                 * validate() chạy strict=true mặc định,
                 * nên chuỗi chưa trim bị reject,
                 * không bị sửa.
                 */
                if (
                    self::boolOption(
                        $arrOption,
                        'trimmed'
                    )
                ) {
                    $arrAtomicSchema[
                        AtomicSchema::FIELD_TRIM
                    ] = true;
                }

                self::validateAtomicFieldValue(
                    $mixFieldValue,
                    $arrAtomicSchema,
                    $strContext,
                    $strFieldPath
                );

                /*
                 * AtomicSchema hiện chưa có enum,
                 * nên values vẫn do DataFields xử lý.
                 */
                if (array_key_exists('values', $arrOption)) {
                    if (!is_array($arrOption['values'])) {
                        throw new InvalidArgumentException(
                            __METHOD__
                            . ": option 'values' phải là array."
                        );
                    }

                    if (
                        !in_array(
                            $mixFieldValue,
                            $arrOption['values'],
                            true
                        )
                    ) {
                        $strAllowedValues = implode(
                            "', '",
                            $arrOption['values']
                        );

                        throw new UnexpectedValueException(
                            "{$strContext} field '{$strFieldPath}' "
                            . "có giá trị không hợp lệ '{$mixFieldValue}'; "
                            . "các giá trị cho phép là "
                            . "'{$strAllowedValues}'."
                        );
                    }
                }

                break;

            /*-------------------------------------------------------------------------------------------------------*/
            case AtomicSchema::TYPE_INT:
                /*
                 * Primitive option validation:
                 * không cần AtomicNormalizer.
                 *
                 * Việc kiểm tra field value + gte phía dưới
                 * mới giao cho AtomicNormalizer.
                 */
                if (
                    array_key_exists('min', $arrOption)
                    && !is_int($arrOption['min'])
                ) {
                    throw new InvalidArgumentException(
                        __METHOD__
                        . ": option 'min' phải là int."
                    );
                }

                $arrAtomicSchema = [
                    AtomicSchema::FIELD_TYPE
                        => AtomicSchema::TYPE_INT
                ];

                if (array_key_exists('min', $arrOption)) {
                    $arrAtomicSchema[
                        AtomicSchema::FIELD_GTE
                    ] = $arrOption['min'];
                }

                self::validateAtomicFieldValue(
                    $mixFieldValue,
                    $arrAtomicSchema,
                    $strContext,
                    $strFieldPath
                );

                break;

            /*-------------------------------------------------------------------------------------------------------*/
            case AtomicSchema::TYPE_BOOL:
                /*
                 * Chỉ cần kiểm tra primitive bool.
                 * Không cần dựng AtomicSchema chỉ để thay is_bool().
                 */
                if (!is_bool($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'phải là boolean; nhận được '
                        . get_debug_type($mixFieldValue)
                        . '.'
                    );
                }

                break;

            /*-------------------------------------------------------------------------------------------------------*/
            case 'array':
                if (!is_array($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'phải là array.'
                    );
                }

                if (
                    self::boolOption(
                        $arrOption,
                        'non_empty'
                    )
                    && $mixFieldValue === []
                ) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'không được là array rỗng.'
                    );
                }

                if (
                    self::boolOption(
                        $arrOption,
                        'unique'
                    )
                    && count($mixFieldValue)
                        !== count(array_unique($mixFieldValue))
                ) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'không được chứa giá trị trùng nhau.'
                    );
                }

                break;

            /*-------------------------------------------------------------------------------------------------------*/
            case 'string_list':
                $arrListOption = [];

                foreach (
                    [
                        'non_empty',
                        'item_non_empty',
                        'unique'
                    ] as $strOptionName
                ) {
                    if (
                        array_key_exists(
                            $strOptionName,
                            $arrOption
                        )
                    ) {
                        $arrListOption[$strOptionName]
                            = $arrOption[$strOptionName];
                    }
                }

                if (
                    !ArrayShape::isStringList(
                        $mixFieldValue,
                        $arrListOption
                    )
                ) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'không đúng format string list yêu cầu.'
                    );
                }

                break;
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra array có đúng tập field yêu cầu:
     *
     * - không thiếu field;
     * - không có field ngoài dự kiến;
     * - expected field phải là string không rỗng và đã trim;
     * - thứ tự field không quan trọng.
     */
    public static function hasExactFields(
        mixed $arrData,
        array $arrExpectedFields
    ): bool {
        if (!is_array($arrData)) {
            return false;
        }

        if (
            count($arrData)
            !== count($arrExpectedFields)
        ) {
            return false;
        }

        $arrExpected = [];

        foreach ($arrExpectedFields as $strField) {
            /*
             * Đây là convenience predicate dùng nhiều,
             * nên gọi StringUtility thay vì dựng AtomicSchema
             * ngay tại call-site.
             */
            if (!StringUtility::isNonEmptyString($strField)) {
                return false;
            }

            $arrExpected[$strField] = true;
        }

        foreach ($arrData as $strField => $_) {
            if (
                !is_string($strField)
                || !isset($arrExpected[$strField])
            ) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    private static function validateAtomicFieldValue(
        mixed $mixValue,
        array $arrAtomicSchema,
        string $strContext,
        string $strFieldPath
    ): void {
        /*
         * Đây là nơi AtomicNormalizer thực sự có giá trị:
         *
         * - type validation;
         * - canonical validation;
         * - trim;
         * - length;
         * - range;
         * - các atomic constraint khác.
         */
        $arrResult = AtomicNormalizer::validate(
            $mixValue,
            $arrAtomicSchema
        );

        if (
            $arrResult[
                AtomicNormalizer::FIELD_ERR_NUMBER
            ] === AtomicNormalizer::ERR_NONE
        ) {
            return;
        }

        throw new UnexpectedValueException(
            "{$strContext} field '{$strFieldPath}': "
            . AtomicNormalizer::showMessage(
                $arrResult[
                    AtomicNormalizer::FIELD_ERR_NUMBER
                ],
                $arrResult[
                    AtomicNormalizer::FIELD_VALUE
                ],
                $arrAtomicSchema
            )
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    private static function buildFieldPath(
        string $strFieldName,
        ?string $strParentPath = null
    ): string {
        return $strParentPath === null
            ? $strFieldName
            : "{$strParentPath}.{$strFieldName}";
    }
}