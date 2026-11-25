<?php
declare(strict_types=1);

namespace Core\Normalization;

use Core\Foundation\ExtArray;
use Core\Validator\ArrayShape;
use Closure;
use LogicException;

/**
 * Tree of upload policies. Missing fields stay missing (inheritance is resolved
 * by the upload normalizer). Rule names are reserved as path segments.
 * setValue() normalizes incoming policies; normalize() normalizes existing leaves.
 * Traversal is in-place: earlier leaves may already be normalized on failure.
 * Empty containers are allowed for incremental construction; empty policies are not.
 */
final class UploadFilesSchema extends ExtArray
{
    public const FIELD_MIN_COUNT = 'min_count';
    public const FIELD_MAX_COUNT = 'max_count';
    public const FIELD_MAX_SIZE = 'max_size';
    public const FIELD_MAX_TOTAL_SIZE = 'max_total_size';
    public const FIELD_ALLOWED_TYPES = 'allowed_types';

    // Reserved errors: 1400–1499; reserved for custom errors: 1500–1599.
    // AtomicNormalizer errors retain their original codes in the 1–999 range.
    public const ERR_STRUCTURE = 1400;
    public const ERR_RESERVED_PATH = 1401;
    public const ERR_COUNT_RELATION = 1402;
    public const ERR_ALLOWED_TYPES = 1403;

    private const FIELDS = [self::FIELD_MIN_COUNT, self::FIELD_MAX_COUNT,
        self::FIELD_MAX_SIZE, self::FIELD_MAX_TOTAL_SIZE, self::FIELD_ALLOWED_TYPES];

    public function __construct(&$arrData = null)
    {
        parent::__construct($arrData);
        parent::setDefineLeafFn(function (array $arrPath, mixed $mixValue): int {
            self::checkPath($arrPath);
            if (!is_array($mixValue)) {
                self::fail(self::ERR_STRUCTURE, $arrPath, 'Expected a policy or branch array.');
            }
            $arrKeys = array_keys($mixValue);
            //leaf là array có ít nhất 1 field thuộc self::FIELDS và không chứa các key lạ
            if (array_intersect($arrKeys, self::FIELDS) !== []) {
                $arrUnknownKeys = array_diff($arrKeys, self::FIELDS);
                if ($arrUnknownKeys !== []) {
                    $mixUnknownKey = reset($arrUnknownKeys);
                    self::fail(
                        self::ERR_STRUCTURE,
                        [...$arrPath, $mixUnknownKey],
                        'Unknown policy field.'
                    );
                }
                return self::COMPOSITE_LEAF;
            }
            return self::BRANCH_OR_PETIOLE;
        });
        parent::setNormalizeLeafFn(function (array $arrPath, mixed $mixValue): array {
            return self::normalizePolicy($arrPath, $mixValue);
        });
        parent::setProcessLeafFn(function (array $arrPath, mixed &$mixValue, array|int $mixDefinition): void {
            $arrResult = $this->callNormalizeLeafFn($arrPath, $mixValue);
            $mixValue = $arrResult['value'];
        });
    }

    public function setDefineLeafFn(Closure $fnCallback): void
    {
        throw new LogicException('UploadFilesSchema::setDefineLeafFn() is locked.');
    }

    public function setNormalizeLeafFn(Closure $fnCallback): void
    {
        throw new LogicException('UploadFilesSchema::setNormalizeLeafFn() is locked.');
    }

    public function setProcessLeafFn(Closure $fnCallback): void
    {
        throw new LogicException('UploadFilesSchema::setProcessLeafFn() is locked.');
    }

    public function normalize(): void
    {
        $this->traverseTree();
    }
    /*Thuật toán ở đây là chỉ cần check phần từ cuối cùng của $arrPath nếu nằm trong danh sách self::FIELDS là báo lỗi luôn*/
    private static function checkPath(array $arrPath): void
    {
        // Traversal checks each ancestor when visiting that node; root has no name.
        if ($arrPath !== [] && in_array($arrPath[count($arrPath) - 1], self::FIELDS, true)) {
            self::fail(self::ERR_RESERVED_PATH, $arrPath, 'Policy names cannot be field/group names.');
        }
    }

    private static function normalizePolicy(array $arrPath, mixed $mixValue): array
    {
        foreach ($mixValue as $mixKey => $mixItem) {
            if ($mixKey === self::FIELD_ALLOWED_TYPES) {
                if (!ArrayShape::isStringListMap($mixItem, [
                    'allow_string_value' => false,
                    // Nonempty-key validation also requires trimmed keys;
                    // normalizeTypes() handles trimming and empty extensions.
                    'non_empty_key' => false,
                ])) {
                    self::fail(self::ERR_STRUCTURE, [...$arrPath, $mixKey], 'Expected a map of string keys to string lists.');
                }
                $mixValue[$mixKey] = self::normalizeTypes([...$arrPath, $mixKey], $mixItem);
                continue;
            }
            // Preserve the existing positive max-count/size policy.
            $arrResult = AtomicNormalizer::normalize($mixItem, [
                'type' => 'int', 'gte' => $mixKey === self::FIELD_MIN_COUNT ? 0 : 1,
            ]);
            if ($arrResult['err_number'] !== AtomicNormalizer::ERR_NONE) {
                self::fail($arrResult['err_number'], [...$arrPath, $mixKey], 'Invalid numeric policy value.');
            }
            $mixValue[$mixKey] = $arrResult['value'];
        }
        if (isset($mixValue[self::FIELD_MIN_COUNT], $mixValue[self::FIELD_MAX_COUNT])
            && $mixValue[self::FIELD_MAX_COUNT] < $mixValue[self::FIELD_MIN_COUNT]) {
            self::fail(self::ERR_COUNT_RELATION, $arrPath, 'max_count must be >= min_count.');
        }
        return ['err_number' => 0, 'value' => $mixValue];
    }

    private static function normalizeTypes(array $arrPath, array $arrTypes): array
    {
        if ($arrTypes === []) {
            self::fail(self::ERR_ALLOWED_TYPES, $arrPath, 'Expected a nonempty extension-to-MIME map.');
        }
        $arrOut = [];
        foreach ($arrTypes as $strExtension => $arrMimes) {
            $arrEntryPath = [...$arrPath, $strExtension];
            $strExtension = strtolower(trim($strExtension));
            if (preg_match('/\A[a-z0-9]+\z/', $strExtension) !== 1 || array_key_exists($strExtension, $arrOut)) {
                self::fail(self::ERR_ALLOWED_TYPES, $arrEntryPath, 'Invalid or duplicate normalized extension.');
            }
            if ($arrMimes === []) {
                self::fail(self::ERR_ALLOWED_TYPES, $arrEntryPath, 'Expected a nonempty MIME list.');
            }
            $arrList = [];
            foreach ($arrMimes as $iKey => $strMime) {
                $strMime = strtolower(trim($strMime));
                if (preg_match('~\A[a-z0-9][a-z0-9!#$&^_.+\x2d]*/[a-z0-9][a-z0-9!#$&^_.+\x2d]*\z~', $strMime) !== 1) {
                    self::fail(self::ERR_ALLOWED_TYPES, [...$arrEntryPath, $iKey], 'Invalid MIME type; wildcards are not supported.');
                }
                if (!in_array($strMime, $arrList, true)) {
                    $arrList[] = $strMime;
                }
            }
            $arrOut[$strExtension] = $arrList;
        }
        return $arrOut;
    }

    private static function fail(int $iCode, array $arrPath, string $strReason): void
    {
        throw new LogicException('UploadFilesSchema err_number=' . $iCode . ' path='
            . json_encode($arrPath, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
            . ': ' . $strReason, $iCode);
    }
}
