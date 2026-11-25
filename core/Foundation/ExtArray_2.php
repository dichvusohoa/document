<?php

/*
 * ExtArray là một cấu trúc dữ liệu key/value, trong đó key là một dãy:
 *
 *     obj[key1][key2]....[keyn] = value
 *
 * Có thể hình dung ExtArray như một hierarchy tree đơn giản:
 * - BRANCH node chỉ tổ chức đường dẫn.
 * - LEAF node chứa data.
 * - PETIOLE là node trung gian để một branch vừa có leaf riêng, vừa có sub-branch.
 *
 * ExtArray cho phép bổ sung các callback:
 *
 *     defineLeafFn
 *         -> xác định semantic definition của node hiện tại.
 *         -> có thể trả EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF
 *            hoặc một array atomic definition.
 *
 *     validLeafFn
 *         -> kiểm tra VALUE có hợp lệ để được set làm LEAF hay không.
 *
 *     processLeafFn
 *         -> xử lý/thay đổi LEAF khi traverse.
 *
 *     startBranchFn / endBranchFn
 *         -> xử lý tùy chọn tại đầu/cuối branch.
 *
 * Update 2025-07-25:
 * - Bổ sung PETIOLE để biểu diễn trường hợp một branch vừa có leaf, vừa có sub-branch.
 *
 * Update 2026-09-18:
 * - Cho phép key của branch là string | int.
 *
 * Update 2026-09-29:
 * - Khôi phục EXCLUDED.
 * - Bổ sung COMPOSITE_LEAF, BRANCH_OR_PETIOLE.
 * - defineLeafFn và validLeafFn hỗ trợ hai prototype:
 *
 *       fn(mixed $obj)
 *
 *   hoặc
 *
 *       fn(array $arrChain, mixed $obj)
 *
 * Update 2026-10-13:
 * - Chuẩn hóa các definition/type constant về int.
 * - defineLeafFn chỉ trả int|array.
 * - Bỏ classifyNode().
 * - UDA không còn phải phân biệt BRANCH và PETIOLE.
 * - ExtArray chỉ tự phân biệt BRANCH/PETIOLE tại nơi thực sự cần.
 * - Bổ sung DISCARDED_LEAF để dành cho cơ chế "cắt lá"; chưa triển khai ở phiên bản này.
 */

namespace Core\Foundation;

use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;

class ExtArray
{
    /*---------------------------------------------------------------------------------------------------------------
     * Semantic definition do defineLeafFn trả về.
     *--------------------------------------------------------------------------------------------------------------*/
    public const EXCLUDED = 0;
    public const BRANCH_OR_PETIOLE = 13;
    public const COMPOSITE_LEAF = 20;

    /*
     * Reserved cho cơ chế "cắt lá" sau này.
     * Phiên bản hiện tại chưa cho phép defineLeafFn sử dụng definition này.
     */
    public const DISCARDED_LEAF = 21;

    /*---------------------------------------------------------------------------------------------------------------
     * Structural node type nội bộ của ExtArray.
     *--------------------------------------------------------------------------------------------------------------*/
    public const BRANCH = 1;
    public const LEAF = 2;
    public const PETIOLE = 3;

    /*---------------------------------------------------------------------------------------------------------------
     * Loại hành động traverse trước đó.
     *--------------------------------------------------------------------------------------------------------------*/
    public const BRANCH_PROCESSING = 1;
    public const LEAF_PROCESSING = 2;

    /*---------------------------------------------------------------------------------------------------------------
     * Tên đặc biệt cho petiole để không lẫn với tên các branch khác.
     *--------------------------------------------------------------------------------------------------------------*/
    public const PETIOLE_DEFAULT_NAME = '__petiole__';

    /*---------------------------------------------------------------------------------------------------------------
     * Mode cho setValue().
     *--------------------------------------------------------------------------------------------------------------*/
    public const MODE_REPLACE = 'replace';
    public const MODE_KEEP_OLD_VALUE = 'keep_old_value';
    public const MODE_MERGE_VALUE = 'merge_value';

    /*---------------------------------------------------------------------------------------------------------------
     * Prototype mode của defineLeafFn / validLeafFn.
     *--------------------------------------------------------------------------------------------------------------*/
    protected const CALLBACK_VALUE_ONLY = 1;
    protected const CALLBACK_PATH_VALUE = 2;

    /*---------------------------------------------------------------------------------------------------------------
     * Closure dùng khi duyệt cây.
     *--------------------------------------------------------------------------------------------------------------*/
    protected Closure $defineLeafFn;
    protected Closure $validLeafFn;
    protected ?Closure $processLeafFn = null;
    protected ?Closure $startBranchFn = null;
    protected ?Closure $endBranchFn = null;

    /*---------------------------------------------------------------------------------------------------------------
     * Prototype mode đã được nhận dạng ngay khi set callback.
     *--------------------------------------------------------------------------------------------------------------*/
    protected int $iDefineLeafFnArgMode;
    protected int $iValidLeafFnArgMode;

    static protected string $strPetiole = self::PETIOLE_DEFAULT_NAME;

    public array $arrData;

    protected bool $isExternalRef;

    /*
     * Lưu giữ loại xử lý và độ sâu xử lý trước đó.
     * Dùng cho các trường hợp process leaf cần biết trạng thái của previous leaf,
     * ví dụ khi ExtArray được dùng để sinh cấu trúc như <UL><LI>.
     */
    protected ?int $iPrevAction;
    protected ?int $iLengthPrev;

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Truyền $arrData dạng reference để không tạo bản sao dữ liệu và để ExtArray có thể thay đổi
     * trực tiếp khối dữ liệu bên ngoài.
     */
    public function __construct(&$arrData = null)
    {
        if ($arrData === null) {
            $this->arrData = [];
            $this->isExternalRef = false;
        } elseif (is_array($arrData)) {
            $this->arrData = &$arrData;
            $this->isExternalRef = true;
        } else {
            throw new InvalidArgumentException(
                'Constructor expects null or array reference.'
            );
        }

        /*
         * Buộc caller phải set defineLeafFn trước khi sử dụng các API cần xác định semantic của node.
         * Default callback dùng prototype PATH_VALUE.
         */
        $this->defineLeafFn = function (
            array $arrChain,
            mixed $obj
        ): array|int {
            throw new LogicException(
                'Leaf definition function must be set before use.'
            );
        };
        $this->iDefineLeafFnArgMode = self::CALLBACK_PATH_VALUE;

        /*
         * Buộc caller phải set validLeafFn trước khi sử dụng setValue().
         * Default callback dùng prototype VALUE_ONLY.
         */
        $this->validLeafFn = function (mixed $obj): bool {
            throw new InvalidArgumentException(
                'Validation function for leaf nodes must be set before use.'
            );
        };
        $this->iValidLeafFnArgMode = self::CALLBACK_VALUE_ONLY;

        $this->iPrevAction = null;
        $this->iLengthPrev = null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setDefineLeafFn(Closure $fn): void
    {
        $fn = $fn->bindTo($this, static::class);

        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind defineLeafFn.'
            );
        }

        $iArgMode = self::detectLeafCallbackArgMode(
            $fn,
            'defineLeafFn'
        );

        self::validateDefineLeafFnReturnType($fn);

        $this->defineLeafFn = $fn;
        $this->iDefineLeafFnArgMode = $iArgMode;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setValidLeafFn(Closure $fn): void
    {
        $fn = $fn->bindTo($this, static::class);

        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind validLeafFn.'
            );
        }

        $iArgMode = self::detectLeafCallbackArgMode(
            $fn,
            'validLeafFn'
        );

        self::validateValidLeafFnReturnType($fn);

        $this->validLeafFn = $fn;
        $this->iValidLeafFnArgMode = $iArgMode;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setProcessLeafFn(Closure $fn): void
    {
        $fn = $fn->bindTo($this, static::class);

        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind processLeafFn.'
            );
        }

        self::validateProcessLeafFnSignature($fn);

        $this->processLeafFn = $fn;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setStartBranchFn(Closure $fn): void
    {
        $fn = $fn->bindTo($this, static::class);

        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind startBranchFn.'
            );
        }

        $this->startBranchFn = $fn;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEndBranchFn(Closure $fn): void
    {
        $fn = $fn->bindTo($this, static::class);

        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind endBranchFn.'
            );
        }

        $this->endBranchFn = $fn;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function setPetioleName(string $strPetioleName): void
    {
        self::$strPetiole = $strPetioleName;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function existElement(string|int|array $arrElement): bool
    {
        $arrResult = $this->getExistInfoAndValue($arrElement);

        return $arrResult['exists'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setValue(
        string|int|array $arrPath,
        mixed $value,
        string $sOption = self::MODE_REPLACE
    ): void {
        /*
         * Chuẩn hóa path trước khi gọi validLeafFn vì validLeafFn có thể dùng prototype PATH_VALUE.
         * [] có nghĩa là root.
         */
        if (!is_array($arrPath)) {
            $arrPath = [$arrPath];
        }

        if (!$this->callValidLeafFn($arrPath, $value)) {
            throw new LogicException(
                'Invalid value: this leaf value does not pass the defined validation rule.'
            );
        }

        $iLength = count($arrPath);

        // __construct() đã bảo đảm arrData là array.
        $ref = &$this->arrData;

        $isNewBranch = false;
        $petioleExists = false;
        $arrTmp = [];
        $arrUndo = [];
        $isCommitted = false;

        try {
            for ($i = 0; $i < $iLength; $i++) {
                $node = $arrPath[$i];

                self::validateBranchNode(
                    $node,
                    $petioleExists
                );

                if ($node === self::$strPetiole) {
                    $petioleExists = true;
                }

                $mixLeafDefinition = $this->callDefineLeafFn(
                    $arrTmp,
                    $ref
                );

                if ($mixLeafDefinition === self::EXCLUDED) {
                    return;
                }

                if (self::isLeafDefinition($mixLeafDefinition)) {
                    $arrUndo[] = [
                        'kind' => 'replace',
                        'ref' => &$ref,
                        'old' => $ref,
                    ];

                    /*
                     * Node hiện tại đang là LEAF nhưng path còn đi tiếp.
                     * Cho mọc thêm một PETIOLE để giữ leaf hiện tại bên cạnh sub-branch mới.
                     */
                    $ref = [
                        self::$strPetiole => $ref,
                    ];
                } else {
                    /*
                     * callDefineLeafFn() đã loại các definition không hợp lệ,
                     * nên trường hợp còn lại chỉ có thể là BRANCH_OR_PETIOLE.
                     */
                    self::validateBranchOrPetioleNode(
                        $arrTmp,
                        $ref
                    );
                }

                if (!array_key_exists($node, $ref)) {
                    $arrUndo[] = [
                        'kind' => 'insert',
                        'ref' => &$ref,
                        'key' => $node,
                    ];

                    $ref[$node] = [];
                    $isNewBranch = true;
                }

                // Tiến sâu thêm một mức, phải dùng reference.
                $ref = &$ref[$node];
                $arrTmp[] = $node;
            }

            $mixLeafDefinition = $this->callDefineLeafFn(
                $arrPath,
                $ref
            );

            if ($mixLeafDefinition === self::EXCLUDED) {
                return;
            }

            if ($mixLeafDefinition === self::BRANCH_OR_PETIOLE) {
                self::validateBranchOrPetioleNode(
                    $arrPath,
                    $ref
                );
            }

            self::setValueAtEndPath(
                $mixLeafDefinition,
                $ref,
                $value,
                $sOption,
                $isNewBranch
            );

            $isCommitted = true;
        } finally {
            /*
             * Không để lại branch rỗng / petiole tạm khi gặp EXCLUDED hoặc lỗi.
             */
            if (!$isCommitted) {
                for (
                    $iUndo = count($arrUndo) - 1;
                    $iUndo >= 0;
                    $iUndo--
                ) {
                    if ($arrUndo[$iUndo]['kind'] === 'insert') {
                        unset(
                            $arrUndo[$iUndo]['ref'][
                                $arrUndo[$iUndo]['key']
                            ]
                        );
                    } else {
                        $arrUndo[$iUndo]['ref'] =
                            $arrUndo[$iUndo]['old'];
                    }
                }
            }

            unset($ref, $arrUndo);
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getValue(string|int|array $arrElement): mixed
    {
        $arrResult = $this->getExistInfoAndValue($arrElement);

        return $arrResult['value'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function &getReferenceAt(string|int|array $arrElement)
    {
        $arrResult = $this->getExistInfoAndValue(
            $arrElement,
            true
        );

        if (!$arrResult['exists']) {
            throw new InvalidArgumentException(
                'Cannot get reference: the specified path does not exist.'
            );
        }

        $ref = &$arrResult['value'];

        return $ref;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getExistInfoAndValue(
        string|int|array $arrElement,
        bool $isGetByReference = false
    ): array {
        // Dùng reference để tránh copy array và để hỗ trợ getReferenceAt().
        $arrChunk = &$this->arrData;

        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }

        $iLength = count($arrElement);
        $element = '';
        $petioleExists = false;

        for ($i = 0; $i < $iLength; $i++) {
            $element = $arrElement[$i];

            self::validateBranchNode(
                $element,
                $petioleExists
            );

            if ($element === self::$strPetiole) {
                $petioleExists = true;
            }

            if (
                is_array($arrChunk)
                && array_key_exists($element, $arrChunk)
            ) {
                $arrChunk = &$arrChunk[$element];
            } else {
                return [
                    'exists' => false,
                    'value' => null,
                ];
            }
        }

        $hasPetiole = is_array($arrChunk)
            && array_key_exists(
                self::$strPetiole,
                $arrChunk
            );

        if ($hasPetiole) {
            $hasSubBranches = self::hasSubBranches(
                $arrChunk
            );

            if (!$hasSubBranches) {
                throw new LogicException(
                    'A petiole should not be created when there are no sub-branches.'
                );
            }

            if ($element === self::$strPetiole) {
                throw new LogicException(
                    'A "__petiole__" node cannot be followed by another key — it must be the leaf holder.'
                );
            }

            if ($isGetByReference) {
                return [
                    'exists' => true,
                    'value' => &$arrChunk[self::$strPetiole],
                ];
            }

            return [
                'exists' => true,
                'value' => $arrChunk[self::$strPetiole],
            ];
        }

        if ($isGetByReference) {
            return [
                'exists' => true,
                'value' => &$arrChunk,
            ];
        }

        return [
            'exists' => true,
            'value' => $arrChunk,
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function isEmpty(): bool
    {
        return $this->arrData === [];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function traverseTree(): void
    {
        /*
         * Mỗi lần traverse là một traversal độc lập.
         */
        $this->iPrevAction = null;
        $this->iLengthPrev = null;

        $mixLeafDefinition = $this->callDefineLeafFn(
            [],
            $this->arrData
        );

        if ($mixLeafDefinition === self::EXCLUDED) {
            return;
        }

        /*
         * Toàn bộ ExtArray có thể được định nghĩa là một LEAF.
         */
        if (self::isLeafDefinition($mixLeafDefinition)) {
            if ($this->processLeafFn) {
                ($this->processLeafFn)(
                    [],
                    $this->arrData,
                    $mixLeafDefinition
                );
            }

            $this->iPrevAction = self::LEAF_PROCESSING;
            $this->iLengthPrev = 0;

            return;
        }

        /*
         * Trường hợp còn lại là BRANCH_OR_PETIOLE.
         */
        self::validateBranchOrPetioleNode(
            [],
            $this->arrData
        );

        $this->traverseBranch(
            $this->arrData,
            []
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function traverseNode(
        mixed &$value,
        array $arrChain
    ): void {
        $mixLeafDefinition = $this->callDefineLeafFn(
            $arrChain,
            $value
        );

        if ($mixLeafDefinition === self::EXCLUDED) {
            return;
        }

        if (self::isLeafDefinition($mixLeafDefinition)) {
            if ($this->processLeafFn) {
                ($this->processLeafFn)(
                    $arrChain,
                    $value,
                    $mixLeafDefinition
                );
            }

            $this->iPrevAction = self::LEAF_PROCESSING;
            $this->iLengthPrev = count($arrChain);

            return;
        }

        /*
         * Trường hợp còn lại là BRANCH_OR_PETIOLE.
         * BRANCH và PETIOLE đều tiếp tục traverse xuống dưới theo cùng một cách.
         */
        self::validateBranchOrPetioleNode(
            $arrChain,
            $value
        );

        $this->traverseBranch(
            $value,
            $arrChain
        );

        $this->iPrevAction = self::BRANCH_PROCESSING;
        $this->iLengthPrev = count($arrChain);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function traverseBranch(
        array &$arrData,
        array $arrChain
    ): void {
        if ($this->startBranchFn) {
            ($this->startBranchFn)(
                $arrChain
            );
        }

        foreach ($arrData as $key => &$value) {
            $arrNewChain = array_merge(
                $arrChain,
                [$key]
            );

            $this->traverseNode(
                $value,
                $arrNewChain
            );
        }

        /*
         * foreach by-reference: bỏ reference tới phần tử cuối.
         */
        unset($value);

        if ($this->endBranchFn) {
            ($this->endBranchFn)(
                $arrChain
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Gọi defineLeafFn đúng theo prototype mode đã nhận dạng tại setDefineLeafFn().
     *
     * Runtime contract hiện tại:
     *
     *     EXCLUDED
     *     BRANCH_OR_PETIOLE
     *     COMPOSITE_LEAF
     *     array atomic definition
     *
     * DISCARDED_LEAF đã được reserve nhưng chưa được hỗ trợ ở phiên bản này.
     */
    protected function callDefineLeafFn(
        array $arrChain,
        mixed $obj
    ): array|int {
        if (
            $this->iDefineLeafFnArgMode
            === self::CALLBACK_VALUE_ONLY
        ) {
            $mixLeafDefinition = ($this->defineLeafFn)(
                $obj
            );
        } else {
            $mixLeafDefinition = ($this->defineLeafFn)(
                $arrChain,
                $obj
            );
        }

        if (is_array($mixLeafDefinition)) {
            return $mixLeafDefinition;
        }

        if (!is_int($mixLeafDefinition)) {
            throw new InvalidArgumentException(
                'defineLeafFn must return int|array.'
            );
        }

        if ($mixLeafDefinition === self::DISCARDED_LEAF) {
            throw new LogicException(
                'DISCARDED_LEAF is reserved but leaf-discarding is not implemented yet.'
            );
        }

        if (
            $mixLeafDefinition !== self::EXCLUDED
            && $mixLeafDefinition !== self::BRANCH_OR_PETIOLE
            && $mixLeafDefinition !== self::COMPOSITE_LEAF
        ) {
            throw new InvalidArgumentException(
                'defineLeafFn must return EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF, or an array definition.'
            );
        }

        return $mixLeafDefinition;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isLeafDefinition(
        array|int $mixLeafDefinition
    ): bool {
        return is_array($mixLeafDefinition)
            || $mixLeafDefinition === self::COMPOSITE_LEAF;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * BRANCH_OR_PETIOLE là semantic definition do UDA cung cấp.
     * Hàm này chỉ kiểm tra invariant cấu trúc bắt buộc của ExtArray.
     */
    protected static function validateBranchOrPetioleNode(
        array $arrChain,
        mixed $obj
    ): void {
        if (!is_array($obj)) {
            throw new LogicException(
                'BRANCH_OR_PETIOLE node must contain array data.'
            );
        }

        /*
         * __petiole__ là vị trí chứa leaf của một PETIOLE,
         * vì vậy chính node __petiole__ không được tiếp tục là BRANCH_OR_PETIOLE.
         */
        if (
            $arrChain !== []
            && end($arrChain) === self::$strPetiole
        ) {
            throw new LogicException(
                "Invalid '__petiole__' node: '__petiole__' must be a leaf, but defineLeafFn returned BRANCH_OR_PETIOLE."
            );
        }

        $iStructureType = self::detectBranchOrPetiole(
            $obj
        );

        if (
            $iStructureType === self::PETIOLE
            && !self::hasSubBranches($obj)
        ) {
            throw new LogicException(
                'Petiole creation is invalid when no child branches are present.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Chỉ dùng khi node đã được biết là BRANCH_OR_PETIOLE.
     * Hàm này không classify semantic của node; nó chỉ nhận diện representation vật lý.
     */
    protected static function detectBranchOrPetiole(
        mixed $value
    ): int {
        if (!is_array($value)) {
            throw new LogicException(
                'BRANCH_OR_PETIOLE node must contain array data.'
            );
        }

        return array_key_exists(
            self::$strPetiole,
            $value
        )
            ? self::PETIOLE
            : self::BRANCH;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Gọi validLeafFn đúng theo prototype mode đã nhận dạng tại setValidLeafFn().
     */
    protected function callValidLeafFn(
        array $arrChain,
        mixed $obj
    ): bool {
        if (
            $this->iValidLeafFnArgMode
            === self::CALLBACK_VALUE_ONLY
        ) {
            $result = ($this->validLeafFn)(
                $obj
            );
        } else {
            $result = ($this->validLeafFn)(
                $arrChain,
                $obj
            );
        }

        /*
         * Nếu UDA không khai báo return type thì vẫn bắt buộc runtime result phải là bool.
         */
        if (!is_bool($result)) {
            throw new LogicException(
                'validLeafFn must return bool.'
            );
        }

        return $result;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Hai prototype hợp lệ cho defineLeafFn / validLeafFn:
     *
     *     fn(mixed $obj)
     *
     * hoặc
     *
     *     fn(array $arrChain, mixed $obj)
     *
     * $obj có thể untyped hoặc mixed.
     * Nếu có $arrChain thì parameter này phải được khai báo type array.
     * Không parameter nào được truyền by-reference hoặc variadic.
     */
    protected static function detectLeafCallbackArgMode(
        Closure $fn,
        string $strCallbackName
    ): int {
        $ref = new ReflectionFunction($fn);
        $arrParam = $ref->getParameters();
        $iParamCount = count($arrParam);

        if ($iParamCount !== 1 && $iParamCount !== 2) {
            throw new InvalidArgumentException(
                $strCallbackName
                . ' must use one of these prototypes: '
                . 'fn(mixed $obj) or '
                . 'fn(array $arrChain, mixed $obj).'
            );
        }

        foreach ($arrParam as $param) {
            if ($param->isVariadic()) {
                throw new InvalidArgumentException(
                    $strCallbackName
                    . ' parameters must not be variadic.'
                );
            }

            if ($param->isPassedByReference()) {
                throw new InvalidArgumentException(
                    $strCallbackName
                    . ' parameters must not be passed by reference.'
                );
            }
        }

        if ($iParamCount === 1) {
            if (!self::isUntypedOrMixedParameter($arrParam[0])) {
                throw new InvalidArgumentException(
                    $strCallbackName
                    . ' value parameter must be untyped or mixed.'
                );
            }

            return self::CALLBACK_VALUE_ONLY;
        }

        if (!self::isArrayParameter($arrParam[0])) {
            throw new InvalidArgumentException(
                $strCallbackName
                . ' first parameter must be declared as array $arrChain.'
            );
        }

        if (!self::isUntypedOrMixedParameter($arrParam[1])) {
            throw new InvalidArgumentException(
                $strCallbackName
                . ' value parameter must be untyped or mixed.'
            );
        }

        return self::CALLBACK_PATH_VALUE;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateDefineLeafFnReturnType(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);

        if ($ref->returnsReference()) {
            throw new InvalidArgumentException(
                'defineLeafFn must not return by reference.'
            );
        }

        $returnType = $ref->getReturnType();

        /*
         * Cho phép callback không khai báo return type để tương thích và thuận tiện cho UDA.
         * Runtime result vẫn được callDefineLeafFn() kiểm tra.
         */
        if ($returnType === null) {
            return;
        }

        if ($returnType instanceof \ReflectionNamedType) {
            if (
                $returnType->allowsNull()
                || !in_array(
                    $returnType->getName(),
                    ['array', 'int'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'defineLeafFn return type must be array|int or a subset of these types.'
                );
            }

            return;
        }

        if ($returnType instanceof \ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if (
                    !($type instanceof \ReflectionNamedType)
                    || $type->allowsNull()
                    || !in_array(
                        $type->getName(),
                        ['array', 'int'],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'defineLeafFn return type must be array|int or a subset of these types.'
                    );
                }
            }

            return;
        }

        throw new InvalidArgumentException(
            'defineLeafFn return type must be array|int or a subset of these types.'
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateValidLeafFnReturnType(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);

        if ($ref->returnsReference()) {
            throw new InvalidArgumentException(
                'validLeafFn must not return by reference.'
            );
        }

        $returnType = $ref->getReturnType();

        /*
         * Cho phép không khai báo return type; callValidLeafFn() sẽ kiểm tra runtime result phải là bool.
         */
        if ($returnType === null) {
            return;
        }

        if (
            !($returnType instanceof \ReflectionNamedType)
            || $returnType->allowsNull()
            || $returnType->getName() !== 'bool'
        ) {
            throw new InvalidArgumentException(
                'validLeafFn return type must be bool.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isArrayParameter(
        \ReflectionParameter $param
    ): bool {
        $type = $param->getType();

        return $type instanceof \ReflectionNamedType
            && !$type->allowsNull()
            && $type->getName() === 'array';
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isUntypedOrMixedParameter(
        \ReflectionParameter $param
    ): bool {
        $type = $param->getType();

        if ($type === null) {
            return true;
        }

        return $type instanceof \ReflectionNamedType
            && $type->getName() === 'mixed';
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Contract:
     *
     *     fn(
     *         array $arrChain,
     *         mixed &$value,
     *         array|int $leafDefinition
     *     )
     *
     * Trong đó int hiện tại chỉ có COMPOSITE_LEAF khi processLeafFn được gọi.
     * Untyped/mixed parameters được chấp nhận theo logic cũ.
     * Validation diễn ra tại setProcessLeafFn(), không phải lúc traverse.
     */
    protected static function validateProcessLeafFnSignature(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);
        $arrParam = $ref->getParameters();

        if (
            count($arrParam) < 3
            || $ref->getNumberOfRequiredParameters() > 3
        ) {
            throw new InvalidArgumentException(
                'processLeafFn must declare three parameters and require no additional arguments.'
            );
        }

        for ($i = 0; $i < 3; $i++) {
            if ($arrParam[$i]->isVariadic()) {
                throw new InvalidArgumentException(
                    'The first three parameters of processLeafFn must not be variadic.'
                );
            }
        }

        if (
            $arrParam[0]->isPassedByReference()
            || !$arrParam[1]->isPassedByReference()
            || $arrParam[2]->isPassedByReference()
        ) {
            throw new InvalidArgumentException(
                'Only the second parameter of processLeafFn must be passed by reference.'
            );
        }

        $acceptsType = function (
            ?\ReflectionType $type,
            string $strType
        ) use (&$acceptsType): bool {
            if ($type === null) {
                return true;
            }

            if ($type instanceof \ReflectionNamedType) {
                $strName = $type->getName();

                return $strName === 'mixed'
                    || $strName === $strType
                    || (
                        $strType === 'array'
                        && $strName === 'iterable'
                    );
            }

            if ($type instanceof \ReflectionUnionType) {
                foreach ($type->getTypes() as $part) {
                    if ($acceptsType($part, $strType)) {
                        return true;
                    }
                }
            }

            return false;
        };

        if (!$acceptsType($arrParam[0]->getType(), 'array')) {
            throw new InvalidArgumentException(
                'The first parameter of processLeafFn must accept array paths.'
            );
        }

        $valueType = $arrParam[1]->getType();

        if (
            $valueType !== null
            && !(
                $valueType instanceof \ReflectionNamedType
                && $valueType->getName() === 'mixed'
            )
        ) {
            throw new InvalidArgumentException(
                'The second parameter of processLeafFn must be untyped or mixed.'
            );
        }

        $definitionType = $arrParam[2]->getType();

        if (
            !$acceptsType($definitionType, 'array')
            || !$acceptsType($definitionType, 'int')
        ) {
            throw new InvalidArgumentException(
                'The third parameter of processLeafFn must accept both array and int.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateBranchNode(
        string|int $key,
        ?bool $petioleExists = null
    ): void {
        if ($petioleExists === null) {
            return;
        }

        if ($petioleExists === true) {
            if ($key === self::$strPetiole) {
                throw new LogicException(
                    'Invalid path: multiple "__petiole__" keys in the same branch.'
                );
            }

            throw new LogicException(
                'Invalid path: "__petiole__" must be the terminal key in a branch.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function hasSubBranches(array $ref): bool
    {
        foreach ($ref as $key => $_) {
            if ($key !== self::$strPetiole) {
                return true;
            }
        }

        return false;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * MODE_MERGE_VALUE là nơi thực sự cần phân biệt representation BRANCH và PETIOLE.
     * defineLeafFn chỉ cung cấp BRANCH_OR_PETIOLE; ExtArray tự nhận diện khi cần.
     */
    protected static function setValueMVModeAtEndPath(
        array|int $mixLeafDefinition,
        mixed &$ref,
        mixed $value
    ): void {
        /*
         * Atomic leaf hoặc composite leaf.
         */
        if (self::isLeafDefinition($mixLeafDefinition)) {
            if (is_array($ref) && is_array($value)) {
                $ref = array_merge(
                    $ref,
                    $value
                );
            } else {
                $ref = $value;
            }

            return;
        }

        if ($mixLeafDefinition !== self::BRANCH_OR_PETIOLE) {
            throw new LogicException(
                'Invalid leaf definition at setValueMVModeAtEndPath().'
            );
        }

        $iPosType = self::detectBranchOrPetiole(
            $ref
        );

        if ($iPosType === self::PETIOLE) {
            if (
                is_array($ref[self::$strPetiole])
                && is_array($value)
            ) {
                $ref[self::$strPetiole] = array_merge(
                    $ref[self::$strPetiole],
                    $value
                );
            } else {
                $ref[self::$strPetiole] = $value;
            }

            return;
        }

        /*
         * BRANCH: thêm leaf tại chính branch bằng petiole.
         */
        $ref[self::$strPetiole] = $value;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function setValueAtEndPath(
        array|int $mixLeafDefinition,
        mixed &$ref,
        mixed $value,
        string $mode,
        bool $isNewBranch
    ): void {
        if (
            $mode === self::MODE_REPLACE
            || $isNewBranch
        ) {
            $ref = $value;
            return;
        }

        switch ($mode) {
            case self::MODE_MERGE_VALUE:
                self::setValueMVModeAtEndPath(
                    $mixLeafDefinition,
                    $ref,
                    $value
                );
                break;

            case self::MODE_KEEP_OLD_VALUE:
                // Do nothing.
                break;

            default:
                throw new InvalidArgumentException(
                    'Unknown mode: ' . $mode
                );
        }
    }
}
