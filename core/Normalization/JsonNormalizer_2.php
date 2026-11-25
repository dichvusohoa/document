<?php
declare(strict_types=1);

namespace Core\Normalization;

use Closure;
use Core\Foundation\ExtArray;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionReference;
use Throwable;
use UnexpectedValueException;

/**
 * PHP >= 8.0. Dữ liệu đã decode; root phải là array.
 *
 * API:
 *   (new JsonNormalizer($data))->addStep($defineFn, $processFn)->normalize();
 *   new JsonNormalizer($data, [[$defineFn, $processFn], [$atomicFn, null]]);
 *
 * defineFn: (mixed $value) hoặc (array $path, mixed $value).
 *   Trả EXCLUDED | BRANCH_OR_PETIOLE | COMPOSITE_LEAF | DISCARDED_LEAF
 *   hoặc atomic schema array (return type array|int, không dùng string marker).
 *   DISCARDED_LEAF xóa node/subtree không có lỗi; root trở thành [].
 *   Giữ branch rỗng và numeric keys, không tự reindex sau khi xóa.
 * processFn: (array $path, mixed $value): array
 *   Trả list record ['path' => array, 'err_number' => int, 'value' => mixed,
 *   'error_value' => mixed]. Cả bốn key đều bắt buộc.
 *   Path tương đối; đúng một path=[] chứa kết quả toàn lá, vị trí tùy ý.
 *   Luôn commit value của path=[], kể cả lỗi; record con chỉ báo lỗi.
 *   error_value dùng ghi lỗi; mã 0 bỏ qua error_value (nên dùng null).
 *   Mỗi path duy nhất. Mã 0 không ghi lỗi; không suy diễn lỗi cha từ lỗi con.
 *   Hai lỗi không được trỏ cùng slot petiole dành riêng (p và p/__petiole__).
 *   Cho phép mã AtomicNormalizer; mã riêng UDA >= UDA_ERR_MIN (1000).
 *   Contract composite cũ thiếu error_value không còn được hỗ trợ.
 *
 * Callback là code tin cậy: defineFn chỉ đọc; processFn chỉ sửa candidate của
 * nó, không sửa input/normalizer qua biến capture, global hoặc side effect.
 * Closure UDA giữ nguyên binding (kể cả $this của Controller và static closure).
 *
 * Response: ['status' => STATUS_OK|STATUS_ERROR, 'data' => array, 'extra' => array].
 * Status dưới đây là status của subsystem, KHÔNG giả định mã Core\Http\Response.
 * extra là cây ExtArray: lá lỗi ['err_number' => int, 'value' => mixed]; petiole giữ lỗi cha
 * khi có lỗi con. UDA error_value được ghi nguyên như callback trả; atomic giữ input.
 * Không lỗi: extra = []. Path dữ liệu tuân thủ tên petiole dành riêng của ExtArray.
 *
 * Atomic chỉ commit khi thành công; composite commit cả kết quả một phần.
 * Lỗi dữ liệu không rollback các unit khác.
 * Lỗi tại/tổ tiên/hậu duệ của unit ngăn xử lý unit đó trong các step sau.
 * EXCLUDED không có nghĩa dữ liệu đã được validate.
 *
 * Không parse JSON, không tự phát hiện field bị thiếu: kiểm tra field bắt buộc
 * tại composite cha. Không sửa ExtArray::$strPetiole; tôn trọng topology ExtArray.
 * Khi engine thu gọn petiole đơn độc, error path được chuyển theo data path.
 *
 * An toàn dữ liệu: từ chối object/resource/reference trong input và kết quả UDA,
 * giới hạn độ sâu 128. Không deep-copy từng step. Quét input một lần; trước
 * callback composite chỉ tách container có reference do engine tạo; kiểm tra
 * kết quả trước commit. Khi xuất kết quả tách reference còn lại một lần.
 *
 * Sau khi normalize hoàn tất, lần gọi tiếp trả kết quả đã lưu. Sau exception,
 * instance bị khóa (không chạy lại trên dữ liệu đã commit dở). Không thêm step
 * sau khi bắt đầu. Instance không hỗ trợ clone.
 */
class JsonNormalizer
{
    public const FIELD_ERR_NUMBER = 'err_number';
    public const FIELD_VALUE = 'value';
    public const FIELD_ERROR_VALUE = 'error_value';
    public const FIELD_PATH = 'path';
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'normalization_error';
    public const MAX_DEPTH = 128;
    public const UDA_ERR_MIN = 1000;

    private const READY = 0;
    private const RUNNING = 1;
    private const DONE = 2;
    private const FAILED = 3;
    private const NO_ERROR = 0;
    private const BLOCKED = 1;
    private const DESCENDANT_ERROR = 2;

    private array $arrData;
    /** @var array<int, array{0: Closure, 1: ?Closure}> */
    private array $arrStepNormalization = [];
    private array $arrError = [];
    private ?array $arrResponse = null;
    private int $iState = self::READY;
    private ExtArray $extData;
    private ExtArray $extError;
    /** Explicit error paths: distinguish a data petiole from a storage petiole. */
    private array $errorPaths = [];

    public function __construct(array $arrData, array $arrStepNormalization = [])
    {
        [$this->arrData] = self::validateAndDetachReferences($arrData, false, 0);
        $this->extData = new ExtArray($this->arrData);
        $this->extError = new ExtArray($this->arrError);
        $isError = static fn(mixed $v): bool => self::isErrorLeaf($v);
        $getPetiole = static fn(): string => self::petioleName();
        $this->extError->setDefineLeafFn(function (array $p, mixed $v) use ($isError, $getPetiole): int {
            return $isError($v) || ($p !== [] && end($p) === $getPetiole())
                ? ExtArray::COMPOSITE_LEAF : ExtArray::BRANCH_OR_PETIOLE;
        });
        $this->extError->setValidLeafFn(function (mixed $v) use ($isError): bool {
            return $isError($v);
        });
        $iExpectedKey = 0;
        foreach ($arrStepNormalization as $key => $arrStep) {
            if ($key !== $iExpectedKey++ || !is_array($arrStep)) {
                throw new InvalidArgumentException('Normalization steps must be a list of arrays.');
            }
            self::validateStepClosurePair($arrStep);
            $this->addStep($arrStep[0], $arrStep[1]);
        }
    }

    private function __clone() {}

    public function addStep(Closure $defineLeafFn, ?Closure $processLeafFn = null): self
    {
        if ($this->iState !== self::READY) {
            throw new LogicException('Cannot add steps after normalization has started.');
        }
        // Reuse ExtArray's protected validators WITHOUT rebinding the UDA closure.
        $iMode = self::definitionArgMode($defineLeafFn);
        if ($processLeafFn !== null) {
            self::validateUdaProcessLeafFn($processLeafFn);
        }
        // Store a uniform callable once, not Reflection work for each visited node.
        $dispatch = static function (array $path, mixed $value) use ($defineLeafFn, $iMode) {
            return $iMode === 1 ? $defineLeafFn($value) : $defineLeafFn($path, $value);
        };
        $this->arrStepNormalization[] = [$dispatch, $processLeafFn];
        return $this;
    }

    /** Validate pair shape only; addStep() validates callback prototypes. */
    protected static function validateStepClosurePair(array $arrStep): void
    {
        if (count($arrStep) !== 2 || !array_key_exists(0, $arrStep)
            || !array_key_exists(1, $arrStep)
            || !($arrStep[0] instanceof Closure)
            || ($arrStep[1] !== null && !($arrStep[1] instanceof Closure))) {
            throw new InvalidArgumentException('A step must be [Closure defineLeafFn, Closure|null processLeafFn].');
        }
    }

    public function normalize(): array
    {
        if ($this->iState === self::DONE) {
            return $this->arrResponse;
        }
        if ($this->iState !== self::READY) {
            throw new LogicException('Normalization is already running or previously failed with an exception.');
        }
        $this->iState = self::RUNNING;
        try {
            // endBranchFn runs after children, immediately before ExtArray collapses
            // a solitary petiole. Keep errors aligned before the next step runs.
            $endBranch = function (array $path): void {
                if ($this->arrError === []) {
                    return;
                }
                $value = $this->arrData;
                foreach ($path as $key) {
                    $value = $value[$key];
                }
                $this->alignErrorsBeforePetioleCollapse($path, $value);
            };
            $this->extData->setEndBranchFn(
                function (array $path) use ($endBranch): void {
                    $endBranch($path);
                }
            );
            foreach ($this->arrStepNormalization as [$defineFn, $processFn]) {
                // Inner closures retain JsonNormalizer scope. ExtArray only rebinds
                // the thin outer adapters, which never access $this/private methods.
                $select = function (array $path, mixed $value) use ($defineFn): array|int {
                    $errorState = $this->errorStateAt($path);
                    if ($errorState === self::BLOCKED) {
                        $this->alignErrorsBeforePetioleCollapse($path, $value);
                        return ExtArray::EXCLUDED;
                    }
                    $definition = $defineFn($path, $value);
                    if (!is_array($definition)
                        && $definition !== ExtArray::EXCLUDED
                        && $definition !== ExtArray::COMPOSITE_LEAF
                        && $definition !== ExtArray::BRANCH_OR_PETIOLE
                        && $definition !== ExtArray::DISCARDED_LEAF) {
                        throw new UnexpectedValueException('Invalid defineLeafFn result.');
                    }
                    // Discard bypasses processUnit(), so protect errored descendants
                    // here as well. EXCLUDED skips the whole selected unit this step.
                    if ($definition === ExtArray::DISCARDED_LEAF && $errorState !== self::NO_ERROR) {
                        $definition = ExtArray::EXCLUDED;
                    }
                    if ($definition === ExtArray::EXCLUDED) {
                        $this->alignErrorsBeforePetioleCollapse($path, $value);
                    }
                    return $definition;
                };
                $process = function (array $path, mixed &$value, array|int $definition) use ($processFn): void {
                    $this->processUnit($path, $value, $definition, $processFn);
                };
                $this->extData->setDefineLeafFn(
                    function (array $path, mixed $value) use ($select): array|int {
                        return $select($path, $value);
                    }
                );
                $this->extData->setProcessLeafFn(
                    function (array $path, mixed &$value, array|int $definition) use ($process): void {
                        $process($path, $value, $definition);
                    }
                );
                $this->extData->traverseTree();
            }
            // Strip engine-created references before exposing/caching a PHP array.
            [$this->arrData] = self::validateAndDetachReferences($this->arrData, true, 0);
            [$this->arrError] = self::validateAndDetachReferences($this->arrError, true, -3);
            $this->arrResponse = $this->createResponse();
            $this->iState = self::DONE;
            return $this->arrResponse;
        } catch (Throwable $exception) {
            $this->iState = self::FAILED;
            throw $exception;
        } finally {
            // Release bound adapters and captured Controller closures promptly.
            unset($this->extData, $this->extError);
            $this->errorPaths = [];
            $this->arrStepNormalization = [];
        }
    }

    /** Override here if Core\Http\Response requires different status constants. */
    protected function createResponse(): array
    {
        return [
            'status' => $this->arrError === [] ? self::STATUS_OK : self::STATUS_ERROR,
            'data' => $this->arrData,
            'extra' => $this->arrError,
        ];
    }

    private function processUnit(array $path, mixed &$value, array|int $definition, ?Closure $processFn): void
    {
        if ($this->errorStateAt($path) !== self::NO_ERROR) {
            return;
        }
        if (is_array($definition)) {
            $result = AtomicNormalizer::normalize($value, $definition);
            // Works with both the older 'errNumber' and renamed 'err_number'
            // version through the existing public constant, without guessing keys.
            $errNumber = $result[AtomicNormalizer::FIELD_ERR_NUMBER];
            $normalized = $result[AtomicNormalizer::FIELD_VALUE];
        } else {
            if ($definition !== ExtArray::COMPOSITE_LEAF || $processFn === null) {
                throw new InvalidArgumentException('Composite leaf requires a UDA processing callback.');
            }
            [$candidate] = self::validateAndDetachReferences($value, true, count($path));
            $result = $processFn($path, $candidate);
            unset($candidate);
            [$normalized, $records] = self::validateCompositeResult($result, $path);
            if ($path === [] && !is_array($normalized)) {
                throw new UnexpectedValueException('The normalized root must remain an array.');
            }
            foreach ($records as $record) {
                if ($record[self::FIELD_ERR_NUMBER] !== 0) {
                    $this->recordError(array_merge($path, $record[self::FIELD_PATH]),
                        $record[self::FIELD_ERR_NUMBER], $record[self::FIELD_ERROR_VALUE]);
                }
            }
            $value = $normalized;
            return;
        }
        if ($errNumber !== AtomicNormalizer::ERR_NONE) {
            $this->recordError($path, $errNumber, $value);
            return;
        }
        if ($path === [] && !is_array($normalized)) {
            throw new UnexpectedValueException('The normalized root must remain an array.');
        }
        // No setValue(), no path lookup, no full-tree copy for commit.
        $value = $normalized;
    }

    /** Validate every record before committing any result. */
    private static function validateCompositeResult(array $results, array $base): array
    {
        $rootFound = false;
        $normalized = null;
        $seen = [];
        $errorLocations = [];
        $expected = 0;
        foreach ($results as $index => $record) {
            if ($index !== $expected++ || ReflectionReference::fromArrayElement($results, $index) !== null
                || !is_array($record) || count($record) !== 4
                || !array_key_exists(self::FIELD_PATH, $record)
                || !array_key_exists(self::FIELD_ERR_NUMBER, $record)
                || !array_key_exists(self::FIELD_VALUE, $record)
                || !array_key_exists(self::FIELD_ERROR_VALUE, $record)) {
                throw new UnexpectedValueException('UDA result must be a list of path/err_number/value/error_value records.');
            }
            foreach ($record as $key => $_) {
                if (ReflectionReference::fromArrayElement($record, $key) !== null) {
                    throw new UnexpectedValueException('UDA records must not contain references.');
                }
            }
            $relative = $record[self::FIELD_PATH];
            if (!is_array($relative)) {
                throw new UnexpectedValueException('UDA path must be a list.');
            }
            $position = 0;
            $full = $base;
            foreach ($relative as $key => $segment) {
                if ($key !== $position++ || (!is_int($segment) && !is_string($segment))
                    || ReflectionReference::fromArrayElement($relative, $key) !== null) {
                    throw new UnexpectedValueException('UDA path must contain string/int keys without references.');
                }
                $full[] = array_key_first([$segment => true]);
            }
            foreach ($full as $key => $segment) {
                if ($segment === self::petioleName() && $key !== count($full) - 1) {
                    throw new UnexpectedValueException('Petiole must be terminal in a path.');
                }
            }
            $id = serialize($full);
            if (isset($seen[$id])) {
                throw new UnexpectedValueException('Duplicate UDA result path.');
            }
            $seen[$id] = true;
            $code = $record[self::FIELD_ERR_NUMBER];
            if (!is_int($code) || $code < 0
                || ($code > AtomicNormalizer::ERR_LTE && $code < self::UDA_ERR_MIN)
                || ($relative !== [] && $code === 0)) {
                throw new UnexpectedValueException('Invalid UDA error code.');
            }
            if ($code !== 0) {
                // ExtArray reserves the terminal petiole for its parent's own leaf.
                // Reject two logical records targeting that same physical slot.
                $location = $full;
                if ($location !== [] && end($location) === self::petioleName()) {
                    array_pop($location);
                }
                $locationId = serialize($location);
                if (isset($errorLocations[$locationId])) {
                    throw new UnexpectedValueException('Error paths alias the same reserved petiole slot.');
                }
                $errorLocations[$locationId] = true;
            }
            self::validateAndDetachReferences($record[self::FIELD_VALUE], false, count($full));//đề phòng hàm processFn của UDA đưa vào tạo dữ liệu không hợp lệ
            if ($code !== 0) {
                self::validateAndDetachReferences($record[self::FIELD_ERROR_VALUE], false, count($full));
            }
            if ($relative === []) {
                $rootFound = true;
                $normalized = $record[self::FIELD_VALUE];
            }
        }
        if (!$rootFound) {
            throw new UnexpectedValueException('UDA result requires exactly one empty path.');
        }
        return [$normalized, $results];
    }

    /** Walk ancestors; no recursive scan of error descendants. */
    private function errorStateAt(array $path): int
    {
        if ($this->arrError === []) {
            return self::NO_ERROR;
        }
        $node = $this->arrError;
        $prefix = [];
        foreach ($path as $key) {
            if (isset($this->errorPaths[serialize($prefix)])) {
                return self::BLOCKED;
            }
            if (!array_key_exists($key, $node)) {
                return self::NO_ERROR;
            }
            $node = $node[$key];
            $prefix[] = $key;
        }
        return isset($this->errorPaths[serialize($prefix)]) ? self::BLOCKED : self::DESCENDANT_ERROR;
    }

    private static function hasOwnError(array $node): bool
    {
        return self::isErrorLeaf($node)
            || (isset($node[self::petioleName()]) && self::isErrorLeaf($node[self::petioleName()]));
    }

    /** Branch children are arrays; an error leaf has a positive integer error code. */
    private static function isErrorLeaf(mixed $node): bool
    {
        return is_array($node) && count($node) === 2
            && isset($node[self::FIELD_ERR_NUMBER])
            && is_int($node[self::FIELD_ERR_NUMBER])
            && $node[self::FIELD_ERR_NUMBER] > 0
            && array_key_exists(self::FIELD_VALUE, $node);
    }

    private function recordError(array $path, int $errNumber, mixed $value): void
    {
        $path = array_map(static fn($key) => array_key_first([$key => true]), $path);
        $id = serialize($path);
        if (isset($this->errorPaths[$id])) {
            throw new UnexpectedValueException('Duplicate error path.');
        }
        [$snapshot] = self::validateAndDetachReferences($value, true, count($path));
        $node = $this->arrError;
        foreach ($path as $key) {
            if (self::isErrorLeaf($node) || !array_key_exists($key, $node)) {
                $node = [];
                break;
            }
            $node = $node[$key];
        }
        if (self::hasOwnError($node)) {
            throw new UnexpectedValueException('Duplicate error at the same node.');
        }
        $this->extError->setValue($path, [
            self::FIELD_ERR_NUMBER => $errNumber,
            self::FIELD_VALUE => $snapshot,
        ],
            $node === [] ? ExtArray::MODE_REPLACE : ExtArray::MODE_MERGE_VALUE);
        $this->errorPaths[$id] = $path;
    }

    /** Mirror exactly one engine collapse; error snapshots remain unchanged. 
     * Điều chỉnh lại $this->arrError chơ khớp với extArray trước khi nó cắt các
     * cuống lá thừa. Hàm này chính là để đối ứng với ExtArray::collapseSolitaryPetiole()
     */
    private function alignErrorsBeforePetioleCollapse(array $path, mixed $value): void
    {
        if ($this->arrError === [] || !is_array($value) || count($value) !== 1) {
            return;
        }
        $petiole = self::petioleName();
        if (!array_key_exists($petiole, $value)) {
            return;
        }
        // A petiole holding this node's own error is metadata, not a data path.
        if (isset($this->errorPaths[serialize($path)])) {
            return;
        }
        $node = &$this->arrError;
        foreach ($path as $key) {
            if (self::isErrorLeaf($node) || !array_key_exists($key, $node)) {
                unset($node);
                return;
            }
            $node = &$node[$key];
        }
        // An error at this unit keeps its path; only descendants move upward.
        if (!self::isErrorLeaf($node) && array_key_exists($petiole, $node)) {
            $errors = $node[$petiole];
            $node = $path === [] && !is_array($value[$petiole]) ? [$errors] : $errors;
            $updated = [];
            foreach ($this->errorPaths as $errorPath) {
                $depth = count($path);
                if (array_slice($errorPath, 0, $depth) === $path
                    && ($errorPath[$depth] ?? null) === $petiole) {
                    array_splice($errorPath, $depth, 1);
                    if ($path === [] && !is_array($value[$petiole])) {
                        array_unshift($errorPath, 0);
                    }
                }
                $updated[serialize($errorPath)] = $errorPath;
            }
            $this->errorPaths = $updated;
        }
        unset($node);
    }

    private static function petioleName(): string
    {
        // Read the engine's active name, including names configured by the caller.
        static $reader = null;
        if ($reader === null) {
            $reader = new class extends ExtArray {
                public function name(): string
                {
                    return self::$strPetiole;
                }
            };
        }
        return $reader->name();
    }

    /**
     * Read-only scan; allocate a replacement container only if it or a child
     * contains references. No serialization or unconditional deep-copy.
     * $allowReferences=true is used ONLY on the trusted internal traversal tree.
     * Incoming/callback data cannot introduce cycles: references are rejected.
     * @return array{0: mixed, 1: bool} [value, changed]
     */
    private static function validateAndDetachReferences(
        mixed $value,
        bool $allowReferences,
        int $depth
    ): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Data exceeds JsonNormalizer::MAX_DEPTH.');
        }
        if (!is_array($value)) {
            if ($value === null || is_string($value) || is_int($value) || is_bool($value)
                || (is_float($value) && is_finite($value))) {
                return [$value, false];
            }
            throw new InvalidArgumentException('Data must contain only arrays and JSON scalar values.');
        }
        $copy = null;
        foreach ($value as $key => $child) {
            $hasReference = ReflectionReference::fromArrayElement($value, $key) !== null;
            if ($hasReference && !$allowReferences) {
                throw new InvalidArgumentException('Input and UDA output must not contain PHP references.');
            }
            [$cleanChild, $childChanged] =
                self::validateAndDetachReferences($child, $allowReferences, $depth + 1);
            if ($hasReference || $childChanged) {
                if ($copy === null) {
                    $copy = [];
                    // Explicit assignment drops direct references; $copy=$value would retain them.
                    foreach ($value as $copyKey => $copyValue) {
                        $copy[$copyKey] = $copyValue;
                    }
                }
                $copy[$key] = $cleanChild;
            }
        }
        return $copy === null ? [$value, false] : [$copy, true];
    }

    private static function definitionArgMode(Closure $fn): int
    {
        // Anonymous bridge exposes validation only; it never traverses data.
        static $validator = null;
        if ($validator === null) {
            $validator = new class extends ExtArray {
                public function inspect(Closure $fn): int
                {
                    $mode = parent::detectLeafCallbackArgMode($fn, 'defineLeafFn');
                    parent::validateDefineLeafFnReturnType($fn);
                    return $mode === self::CALLBACK_VALUE_ONLY ? 1 : 2;
                }
            };
        }
        return $validator->inspect($fn);
    }

    private static function validateUdaProcessLeafFn(Closure $fn): void
    {
        $ref = new ReflectionFunction($fn);
        $params = $ref->getParameters();
        if (count($params) !== 2 || $ref->getNumberOfRequiredParameters() !== 2
            || $ref->returnsReference()) {
            throw new InvalidArgumentException('UDA callback must declare (array $path, mixed $value).');
        }
        foreach ($params as $param) {
            if ($param->isPassedByReference() || $param->isVariadic()) {
                throw new InvalidArgumentException('UDA parameters must be by-value and not variadic.');
            }
        }
        $type = $params[0]->getType();
        if (!($type instanceof ReflectionNamedType) || $type->getName() !== 'array' || $type->allowsNull()) {
            throw new InvalidArgumentException('UDA path parameter must be array.');
        }
        $type = $params[1]->getType();
        if ($type !== null && !($type instanceof ReflectionNamedType && $type->getName() === 'mixed')) {
            throw new InvalidArgumentException('UDA value parameter must be untyped or mixed.');
        }
        $type = $ref->getReturnType();
        if ($type !== null && !($type instanceof ReflectionNamedType
            && $type->getName() === 'array' && !$type->allowsNull())) {
            throw new InvalidArgumentException('UDA return type must be array or omitted.');
        }
    }
}
