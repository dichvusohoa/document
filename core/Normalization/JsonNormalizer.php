<?php
declare(strict_types=1);

namespace Core\Normalization;

use Closure;
use Core\Foundation\ExtArray;
use Core\Utility\ArrayUtility;
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
 *   (new JsonNormalizer($data))->addStep($fnDefineLeaf, $fnProcessLeaf)->normalize();
 *   new JsonNormalizer($data, [[$fnDefineLeaf, $fnProcessLeaf], [$fnDefineAtomicLeaf, null]]);
 *
 * fnDefineLeaf: (mixed $value) hoặc (array $arrPath, mixed $value).
 *   Trả EXCLUDED | BRANCH_OR_PETIOLE | COMPOSITE_LEAF | DISCARDED_LEAF
 *   hoặc atomic schema array (return type array|int, không dùng string marker).
 *   DISCARDED_LEAF xóa node/subtree trực tiếp; root trở thành [].
 *   UDA chọn subtree cần cắt trước khi normalize/ghi lỗi trong subtree đó.
 *   Giữ branch rỗng và numeric keys, không tự reindex sau khi xóa.
 * fnProcessLeaf: (array $arrPath, mixed $value): array
 *   Trả list record ['path' => array, 'err_number' => int, 'value' => mixed,
 *   'error_value' => mixed]. Cả bốn key đều bắt buộc.
 *   Path tương đối; đúng một path=[] chứa kết quả toàn lá, vị trí tùy ý.
 *   Luôn commit value của path=[], kể cả lỗi; record con chỉ báo lỗi.
 *   error_value dùng ghi lỗi; mã 0 bỏ qua error_value (nên dùng null).
 *   Mỗi path duy nhất. Mã 0 không ghi lỗi; không suy diễn lỗi cha từ lỗi con.
 *   Không ghi hai lỗi cùng path logic (ví dụ p và p/__petiole__).
 *   Cho phép mã AtomicNormalizer; mã riêng UDA >= UDA_ERR_MIN (1000).
 *   Contract composite cũ thiếu error_value không còn được hỗ trợ.
 *
 * Callback là code tin cậy: fnDefineLeaf chỉ đọc; fnProcessLeaf chỉ sửa candidate của
 * nó, không sửa input/normalizer qua biến capture, global hoặc side effect.
 * Closure UDA giữ nguyên binding (kể cả $this của Controller và static closure).
 *
 * Response: ['status' => STATUS_OK|STATUS_ERROR, 'data' => array, 'extra' => array].
 * Status dưới đây là status của subsystem, KHÔNG giả định mã Core\Http\Response.
 * extra là flat map: serialize(path) => ['path' => array, 'err_number' => int,
 * 'value' => mixed]. Path logic bỏ segment petiole cuối như ExtArray::toFlatArray().
 * UDA error_value được ghi nguyên như callback trả; atomic giữ input.
 * Không lỗi: extra = []. Root error có key serialize([]), path => [].
 *
 * Atomic chỉ commit khi thành công; composite commit cả kết quả một phần.
 * Lỗi dữ liệu không rollback các unit khác.
 * Pipeline xử lý từ leaf sâu lên root. Lỗi tại chính unit hoặc hậu duệ ngăn
 * processing unit đó ở step sau; các branch vẫn được duyệt và gọi fnDefineLeaf.
 * Không quay xuống xử lý hậu duệ của một unit đã được xử lý và báo lỗi.
 * EXCLUDED không có nghĩa dữ liệu đã được validate.
 *
 * Không parse JSON, không tự phát hiện field bị thiếu: kiểm tra field bắt buộc
 * tại composite cha. Không sửa ExtArray::$strPetiole; tôn trọng topology ExtArray.
 * Petiole đơn độc được giữ nguyên trong dữ liệu; error path dùng dạng logic.
 *
 * An toàn dữ liệu: từ chối object/resource/reference trong input và kết quả UDA,
 * giới hạn độ sâu 128. Quét input một lần và kiểm tra kết quả UDA trước commit.
 * Candidate và snapshot nhận by-value; dùng copy-on-write của PHP,
 * không quét lại cây để tách reference nội bộ.
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
    private array $arrData;
    /** @var array<int, array{0: Closure, 1: ?Closure}> */
    private array $arrStepNormalization = [];
    /** @var array<string, array{path: array, err_number: int, value: mixed}> */
    private array $arrError = [];
    /** @var array<string, true> Strict prefixes of logical error paths. */
    private array $arrHasDescendantError = [];
    private ?array $arrResponse = null;
    private int $iState = self::READY;
    private ExtArray $extData;

    public function __construct(array $arrData, array $arrStepNormalization = [])
    {
        [$this->arrData] = self::validateAndDetachReferences($arrData, false, 0);
        $this->extData = new ExtArray($this->arrData);
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
    /*$fnProcessLeaf = null được thiết kế để hỗ trợ step chỉ chuẩn hóa atomic*/
    public function addStep(Closure $fnDefineLeaf, ?Closure $fnProcessLeaf = null): self
    {
        if ($this->iState !== self::READY) {
            throw new LogicException('Cannot add steps after normalization has started.');
        }
        // Reuse ExtArray's protected validators WITHOUT rebinding the UDA closure.
        $iMode = self::definitionArgMode($fnDefineLeaf);
        if ($fnProcessLeaf !== null) {
            self::validateUdaProcessLeafFn($fnProcessLeaf);
        }
        // Store a uniform callable once, not Reflection work for each visited node.
        $fnDispatch = static function (array $arrPath, mixed $value) use ($fnDefineLeaf, $iMode) {
            return $iMode === 1 ? $fnDefineLeaf($value) : $fnDefineLeaf($arrPath, $value);
        };
        $this->arrStepNormalization[] = [$fnDispatch, $fnProcessLeaf];
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
    
    /**
     * Chạy các step theo thứ tự đăng ký và trả response gồm ba key:
     * - status: self::STATUS_OK ('ok') nếu không ghi nhận lỗi;
     *   self::STATUS_ERROR ('normalization_error') nếu có lỗi trong extra.
     * - data: dữ liệu sau xử lý, có thể đã được chuẩn hóa một phần khi có lỗi.
     *   Atomic lỗi giữ input; composite vẫn commit value do UDA trả về.
     * - extra: flat map với key serialize(path logic); [] nếu không có lỗi.
     *   Mỗi record là ['path' => array, 'err_number' => int, 'value' => mixed].
     *   value là input khi lỗi atomic hoặc error_value do UDA cung cấp khi lỗi composite.
     *
     * Ví dụ thành công:
     * [
     *     'status' => self::STATUS_OK,
     *     'data' => ['user' => ['name' => 'An', 'age' => 25]],
     *     'extra' => [],
     * ]
     *
     * Ví dụ lỗi tại user.age, các field khác vẫn có thể được chuẩn hóa:
     * [
     *     'status' => self::STATUS_ERROR,
     *     'data' => ['user' => ['name' => 'An', 'age' => 'abc']],
     *     'extra' => [
     *         serialize(['user', 'age']) => [
     *             'path' => ['user', 'age'],
     *             'err_number' => 1000,
     *             'value' => 'abc',
     *         ],
     *     ],
     * ]
     *
     * Lỗi cha và lỗi con là các record độc lập. Lỗi tại root dùng path => [].
     * Path logic bỏ segment petiole cuối; dữ liệu và path callback UDA giữ nguyên.
     * Đây là response cuối, khác list record path/err_number/value/error_value
     * mà callback UDA trả về. Lỗi cấu hình hoặc contract có thể throw exception.
     *
     * @return array{status: string, data: array, extra: array}
     */
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
            foreach ($this->arrStepNormalization as [$fnDefine, $fnProcessLeaf]) {
                // ExtArray validates definition results; errors only guard processing.
                $this->extData->setDefineLeafFn($fnDefine);
                $this->extData->setProcessLeafFn(function (array $arrPath, mixed &$value, array|int $definition) use ($fnProcessLeaf): void {
                    $this->processUnit($arrPath, $value, $definition, $fnProcessLeaf);
                });
                $this->extData->traverseTree();
            }
            $this->arrResponse = $this->createResponse();
            $this->iState = self::DONE;
            return $this->arrResponse;
        } catch (Throwable $exception) {
            $this->iState = self::FAILED;
            throw $exception;
        } finally {
            // Release traversal callbacks and captured Controller closures promptly.
            unset($this->extData);
            $this->arrHasDescendantError = [];
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

    private function processUnit(array $arrPath, mixed &$value, array|int $definition, ?Closure $fnProcessLeaf): void
    {
        $id = serialize(self::errorPath($arrPath));
        if (isset($this->arrError[$id]) || isset($this->arrHasDescendantError[$id])) {
            return;
        }
        if (is_array($definition)) {
            $arrResult = AtomicNormalizer::normalize($value, $definition);
            // Works with both the older 'errNumber' and renamed 'err_number'
            // version through the existing public constant, without guessing keys.
            $iErrNumber = $arrResult[AtomicNormalizer::FIELD_ERR_NUMBER];
            $normalized = $arrResult[AtomicNormalizer::FIELD_VALUE];
        } else {
            if ($fnProcessLeaf === null) {
                throw new InvalidArgumentException('Composite leaf requires a UDA processing callback.');
            }
            $arrResult = $fnProcessLeaf($arrPath, $value);
            [$normalized, $arrRecord] = self::validateCompositeResult($arrResult, $arrPath);
            if ($arrPath === [] && !is_array($normalized)) {
                throw new UnexpectedValueException('The normalized root must remain an array.');
            }
            foreach ($arrRecord as $arrRecordItem) {
                if ($arrRecordItem[self::FIELD_ERR_NUMBER] !== 0) {
                    $this->recordError(array_merge($arrPath, $arrRecordItem[self::FIELD_PATH]),
                        $arrRecordItem[self::FIELD_ERR_NUMBER], $arrRecordItem[self::FIELD_ERROR_VALUE]);
                }
            }
            $value = $normalized;
            return;
        }
        if ($iErrNumber !== AtomicNormalizer::ERR_NONE) {
            $this->recordError($arrPath, $iErrNumber, $value);
            return;
        }
        if ($arrPath === [] && !is_array($normalized)) {
            throw new UnexpectedValueException('The normalized root must remain an array.');
        }
        // No setValue(), no path lookup, no full-tree copy for commit.
        $value = $normalized;
    }

    /** Validate every record before committing any result. */
    private static function validateCompositeResult(array $arrResult, array $arrBase): array
    {
        $rootFound = false;
        $normalized = null;
        $arrSeen = [];
        $arrErrorLocation = [];
        $iExpected = 0;
        foreach ($arrResult as $index => $arrRecordItem) {
            /*begin kiểm tra đảm bảo $index = 0,1,2,3.. liên tiếp
            $arrRecordItem không được là dạng tham chiếu có format dạng 
            ['path'=>...,'err_number' => ..., 'value'=>..., 'error_value'=>...]*/
            if ($index !== $iExpected++ || ReflectionReference::fromArrayElement($arrResult, $index) !== null
                || !is_array($arrRecordItem) || count($arrRecordItem) !== 4
                || !array_key_exists(self::FIELD_PATH, $arrRecordItem)
                || !array_key_exists(self::FIELD_ERR_NUMBER, $arrRecordItem)
                || !array_key_exists(self::FIELD_VALUE, $arrRecordItem)
                || !array_key_exists(self::FIELD_ERROR_VALUE, $arrRecordItem)) {
                throw new UnexpectedValueException('UDA result must be a list of path/err_number/value/error_value records.');
            }
            /*end kiểm tra đảm bảo $index = 0,1,2,3.. liên tiếp
            $arrRecordItem không được là dạng tham chiếu có format dạng 
            ['path'=>...,'err_number' => ..., 'value'=>..., 'error_value'=>...]*/
            
            //đảm bảo $arrRecordItem không chứa các item dữ liệu dạng reference
            foreach ($arrRecordItem as $key => $_) {
                if (ReflectionReference::fromArrayElement($arrRecordItem, $key) !== null) {
                    throw new UnexpectedValueException('UDA records must not contain references.');
                }
            }
            $arrRelative = $arrRecordItem[self::FIELD_PATH];
            if (!is_array($arrRelative)) {//đảm bảo $arrRecordItem['path'] là array
                throw new UnexpectedValueException('UDA path must be a list.');
            }
            $iPosition = 0;
            $arrFull = $arrBase;// tạo ra đường dẫn đầy đủ từ $arrFull  = arrBase + $arrRelative
            foreach ($arrRelative as $key => $segment) {
                if ($key !== $iPosition++ || (!is_int($segment) && !is_string($segment))
                    || ReflectionReference::fromArrayElement($arrRelative, $key) !== null) {
                    throw new UnexpectedValueException('UDA path must contain string/int keys without references.');
                }
                $arrFull[] = $segment;
            }
            $arrFull = ArrayUtility::normalizePathKeys($arrFull);
            foreach ($arrFull as $key => $segment) {
                if ($segment === ExtArray::getPetioleName() && $key !== count($arrFull) - 1) {
                    throw new UnexpectedValueException('Petiole must be terminal in a path.');
                }
            }
            $id = serialize($arrFull);
            if (isset($arrSeen[$id])) {
                throw new UnexpectedValueException('Duplicate UDA result path.');
            }
            $arrSeen[$id] = true;
            $iCode = $arrRecordItem[self::FIELD_ERR_NUMBER];
            if (!is_int($iCode) || $iCode < 0
                || ($iCode > AtomicNormalizer::ERR_LTE && $iCode < self::UDA_ERR_MIN)
                || ($arrRelative !== [] && $iCode === 0)) {
                throw new UnexpectedValueException('Invalid UDA error code.');
            }
            if ($iCode !== 0) {
                // A path and its terminal petiole denote the same logical leaf.
                $locationId = serialize(self::errorPath($arrFull));
                if (isset($arrErrorLocation[$locationId])) {
                    throw new UnexpectedValueException('Duplicate logical error path.');
                }
                $arrErrorLocation[$locationId] = true;
            }
            self::validateAndDetachReferences($arrRecordItem[self::FIELD_VALUE], false, count($arrFull));//đề phòng hàm fnProcessLeaf của UDA đưa vào tạo dữ liệu không hợp lệ
            if ($iCode !== 0) {
                self::validateAndDetachReferences($arrRecordItem[self::FIELD_ERROR_VALUE], false, count($arrFull));
            }
            if ($arrRelative === []) {
                $rootFound = true;
                $normalized = $arrRecordItem[self::FIELD_VALUE];
            }
        }
        if (!$rootFound) {
            throw new UnexpectedValueException('UDA result requires exactly one empty path.');
        }
        return [$normalized, $arrResult];
    }

    /** Canonical PHP keys and the logical path used by ExtArray::toFlatArray(). */
    private static function errorPath(array $arrPath): array
    {
        $arrPath = ArrayUtility::normalizePathKeys($arrPath);
        if ($arrPath !== [] && end($arrPath) === ExtArray::getPetioleName()) {
            array_pop($arrPath);
        }
        return $arrPath;
    }

    private function recordError(array $arrPath, int $iErrNumber, mixed $value): void
    {
        $arrPath = self::errorPath($arrPath);
        $id = serialize($arrPath);
        if (isset($this->arrError[$id])) {
            throw new UnexpectedValueException('Duplicate error path.');
        }
        $this->arrError[$id] = [
            self::FIELD_PATH => $arrPath,
            self::FIELD_ERR_NUMBER => $iErrNumber,
            self::FIELD_VALUE => $value,
        ];
        $arrPrefix = [];
        foreach ($arrPath as $segment) {
            $this->arrHasDescendantError[serialize($arrPrefix)] = true;
            $arrPrefix[] = $segment;
        }
    }

    /**
     * Read-only scan; allocate a replacement container only if it or a child
     * contains references. No serialization or unconditional deep-copy.
     * Current callers use $allowReferences=false to reject incoming references.
     * Incoming/callback data cannot introduce cycles: references are rejected.
     * @return array{0: mixed, 1: bool} [value, changed]
     */
    private static function validateAndDetachReferences(
        mixed $value,
        bool $allowReferences,
        int $iDepth
    ): array
    {
        if ($iDepth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Data exceeds JsonNormalizer::MAX_DEPTH.');
        }
        if (!is_array($value)) {
            if ($value === null || is_string($value) || is_int($value) || is_bool($value)
                || (is_float($value) && is_finite($value))) {
                return [$value, false];
            }
            throw new InvalidArgumentException('Data must contain only arrays and JSON scalar values.');
        }
        $arrCopy = null;
        foreach ($value as $key => $child) {
            $hasReference = ReflectionReference::fromArrayElement($value, $key) !== null;
            if ($hasReference && !$allowReferences) {
                throw new InvalidArgumentException('Input and UDA output must not contain PHP references.');
            }
            [$cleanChild, $childChanged] =
                self::validateAndDetachReferences($child, $allowReferences, $iDepth + 1);
            if ($hasReference || $childChanged) {
                if ($arrCopy === null) {
                    $arrCopy = [];
                    // Explicit assignment drops direct references; $arrCopy=$value would retain them.
                    foreach ($value as $copyKey => $copyValue) {
                        $arrCopy[$copyKey] = $copyValue;
                    }
                }
                $arrCopy[$key] = $cleanChild;
            }
        }
        return $arrCopy === null ? [$value, false] : [$arrCopy, true];
    }

    private static function definitionArgMode(Closure $fn): int
    {
        // Anonymous bridge exposes validation only; it never traverses data.
        static $validator = null;
        if ($validator === null) {
            $validator = new class extends ExtArray {
                public function inspect(Closure $fn): int
                {
                    $iMode = parent::detectLeafCallbackArgMode($fn, 'defineLeafFn');
                    parent::validateDefineLeafFnReturnType($fn);
                    return $iMode === self::CALLBACK_VALUE_ONLY ? 1 : 2;
                }
            };
        }
        return $validator->inspect($fn);
    }

    private static function validateUdaProcessLeafFn(Closure $fn): void
    {
        $ref = new ReflectionFunction($fn);
        $arrParameter = $ref->getParameters();
        if (count($arrParameter) !== 2 || $ref->getNumberOfRequiredParameters() !== 2
            || $ref->returnsReference()) {
            throw new InvalidArgumentException('UDA callback must declare (array $arrPath, mixed $value).');
        }
        foreach ($arrParameter as $param) {
            if ($param->isPassedByReference() || $param->isVariadic()) {
                throw new InvalidArgumentException('UDA parameters must be by-value and not variadic.');
            }
        }
        $type = $arrParameter[0]->getType();
        if (!($type instanceof ReflectionNamedType) || $type->getName() !== 'array' || $type->allowsNull()) {
            throw new InvalidArgumentException('UDA path parameter must be array.');
        }
        $type = $arrParameter[1]->getType();
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
