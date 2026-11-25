<?php

/*
 * ExtArray là một cấu trúc dữ liệu key/value, trong đó key là một dãy:
 *
 *     obj[key1][key2]....[keyn] = value
 *
 * Có thể hình dung ExtArray như một hierarchy tree đơn giản:
 * - BRANCH node chỉ tổ chức đường dẫn.
 * - LEAF node chứa data.
 * - PETIOLE giữ leaf riêng của một branch; hợp lệ cả khi không còn sub-branch.
 * - Segment petiole chỉ được xuất hiện một lần và phải ở cuối path.
 *
 * ExtArray cho phép bổ sung các callback:
 *
 *     fnDefineLeaf
 *         -> xác định semantic definition của node hiện tại.
 *         -> có thể trả EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF,
 *            DISCARDED_LEAF hoặc một array atomic definition.
 *
 *     fnNormalizeLeaf
 *         -> normalize VALUE trước khi ghi; trả ['err_number' => int >= 0, 'value' => mixed].
 *
 *     fnProcessLeaf
 *         -> xử lý/thay đổi LEAF khi traverse.
 *
 *     fnStartBranch / fnEndBranch
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
 * - fnDefineLeaf và fnNormalizeLeaf (tên tại thời điểm này) hỗ trợ hai prototype:
 *
 *       fn(mixed $obj)
 *
 *   hoặc
 *
 *       fn(array $arrChain, mixed $obj)
 *
 * Update 2026-10-13:
 * - Chuẩn hóa các definition/type constant về int.
 * - fnDefineLeaf chỉ trả int|array.
 * - Bỏ classifyNode().
 * - UDA không còn phải phân biệt BRANCH và PETIOLE.
 * - ExtArray chỉ tự phân biệt BRANCH/PETIOLE tại nơi thực sự cần.
 * - Bổ sung DISCARDED_LEAF để dành cho cơ chế "cắt lá"; chưa triển khai ở phiên bản này.
 *
 * Update 2026-10-18:
 * - Traversal hỗ trợ DISCARDED_LEAF; giữ empty branch và numeric keys.
 * - Collapse petiole đơn độc sau fnEndBranch, không process leaf lần nữa.
 * - EXCLUDED bỏ qua subtree nhưng cho phép bỏ một lớp petiole đơn độc tại node.
 * - Root collapse giữ kiểu array; setValue() từ chối DISCARDED_LEAF.
 *
 * Update 2026-11-15:
 * - Thay validLeafFn bằng fnNormalizeLeaf; err_number = 0 là thành công.
 * - MERGE_VALUE normalize candidate sau merge đúng một lần, trước khi ghi.
 * - Result sai contract ném UnexpectedValueException; mã lỗi khác 0 ném
 *   LogicException kèm path và exception code. Exception từ callback truyền nguyên ra.
 *
 * Update 2026-11-21:
 * - Getter/setter chấp nhận petiole đơn độc, vẫn giữ quy tắc petiole kết thúc path.
 * - Traversal giữ nguyên petiole đơn độc; không tự collapse, kể cả EXCLUDED.
 */

namespace Core\Foundation;

use Closure;
use Core\Utility\ArrayUtility;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use UnexpectedValueException;

class ExtArray
{
    /*---------------------------------------------------------------------------------------------------------------
     * Semantic definition do fnDefineLeaf trả về.
     *--------------------------------------------------------------------------------------------------------------*/
    public const EXCLUDED = 0;
    public const BRANCH_OR_PETIOLE = 13;
    public const COMPOSITE_LEAF = 20;

    /*
     * Traversal xóa entry mang leaf này; setValue() không chấp nhận definition này.
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
     * Prototype mode của fnDefineLeaf / fnNormalizeLeaf.
     *--------------------------------------------------------------------------------------------------------------*/
    protected const CALLBACK_VALUE_ONLY = 1;
    protected const CALLBACK_PATH_VALUE = 2;

    /*---------------------------------------------------------------------------------------------------------------
     * Closure dùng khi duyệt cây.
     *--------------------------------------------------------------------------------------------------------------*/
    protected Closure $fnDefineLeaf;
    protected Closure $fnNormalizeLeaf;
    protected ?Closure $fnProcessLeaf = null;
    protected ?Closure $fnStartBranch = null;
    protected ?Closure $fnEndBranch = null;
    /*---------------------------------------------------------------------------------------------------------------
     * Prototype mode đã được nhận dạng ngay khi set callback.
     *--------------------------------------------------------------------------------------------------------------*/
    protected int $iDefineLeafFnArgMode;
    protected int $iNormalizeLeafFnArgMode;

    static protected string $strPetiole = self::PETIOLE_DEFAULT_NAME;

    protected array $arrData;

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
         * Buộc caller phải set fnDefineLeaf trước khi sử dụng các API cần xác định semantic của node.
         * Default callback dùng prototype PATH_VALUE.
         */
        $this->fnDefineLeaf = function (
            array $arrChain,
            mixed $obj
        ): array|int {
            throw new LogicException(
                'Leaf definition function must be set before use.'
            );
        };
        $this->iDefineLeafFnArgMode = self::CALLBACK_PATH_VALUE;

        /*
         * Buộc caller phải set fnNormalizeLeaf trước khi sử dụng setValue().
         * Default callback dùng prototype VALUE_ONLY.
         */
        $this->fnNormalizeLeaf = function (mixed $obj): array {
            throw new InvalidArgumentException(
                'Normalization function for leaf nodes must be set before use.'
            );
        };
        $this->iNormalizeLeafFnArgMode = self::CALLBACK_VALUE_ONLY;

        $this->iPrevAction = null;
        $this->iLengthPrev = null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    // Callback setters preserve the caller's object binding and class scope.
    // Callers that need ExtArray scope must bind their Closure explicitly.
    public function setDefineLeafFn(Closure $fn): void
    {
        $iArgMode = self::detectLeafCallbackArgMode(
            $fn,
            'fnDefineLeaf'
        );

        self::validateDefineLeafFnReturnType($fn);

        $this->fnDefineLeaf = $fn;
        $this->iDefineLeafFnArgMode = $iArgMode;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setNormalizeLeafFn(Closure $fn): void
    {
        $iArgMode = self::detectLeafCallbackArgMode(
            $fn,
            'fnNormalizeLeaf'
        );

        self::validateNormalizeLeafFnReturnType($fn);

        $this->fnNormalizeLeaf = $fn;
        $this->iNormalizeLeafFnArgMode = $iArgMode;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setProcessLeafFn(Closure $fn): void
    {
        self::validateProcessLeafFnSignature($fn);

        $this->fnProcessLeaf = $fn;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setStartBranchFn(Closure $fn): void
    {
        $this->fnStartBranch = $fn;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEndBranchFn(Closure $fn): void
    {
        $this->fnEndBranch = $fn;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function setPetioleName(string $strPetioleName): void
    {
        self::$strPetiole = $strPetioleName;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function getPetioleName(): string
    {
        return self::$strPetiole;
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
         * Chuẩn hóa path trước khi gọi fnNormalizeLeaf vì fnNormalizeLeaf có thể dùng prototype PATH_VALUE.
         * [] có nghĩa là root.
         */
        if (!is_array($arrPath)) {
            $arrPath = [$arrPath];
        }

        if ($sOption !== self::MODE_MERGE_VALUE) {
            $value = $this->normalizeValueForSet($arrPath, $value);
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

                if ($mixLeafDefinition === self::DISCARDED_LEAF) {
                    throw new LogicException('setValue() does not accept DISCARDED_LEAF.');
                }

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

            if ($mixLeafDefinition === self::DISCARDED_LEAF) {
                throw new LogicException('setValue() does not accept DISCARDED_LEAF.');
            }

            if ($mixLeafDefinition === self::EXCLUDED) {
                return;
            }

            if ($mixLeafDefinition === self::BRANCH_OR_PETIOLE) {
                self::validateBranchOrPetioleNode(
                    $arrPath,
                    $ref
                );
            }

            $this->setValueAtEndPath(
                $mixLeafDefinition,
                $ref,
                $value,
                $sOption,
                $isNewBranch,
                $arrPath
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
    /** Return the complete physical tree by value, including petiole slots. */
    public function getData(): array
    {
        return $this->arrData;
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
    /**
     * Xuất leaf theo thứ tự duyệt, key = serialize(logical path), bỏ segment petiole cuối.
     * fnMap(array $arrPath, mixed $mixValue, array|int $mixDefinition): mixed.
     * Chỉ dùng fnDefineLeaf; không normalize, process, gọi branch callback hoặc sửa cây nguồn.
     * EXCLUDED/DISCARDED_LEAF bỏ qua subtree; empty branch không tạo entry.
     * Tách array reference để output/callback không ghi qua reference vào nguồn;
     * object trong leaf vẫn được giữ nguyên, không clone.
     */
    public function toFlatArray(?Closure $fnMap = null): array
    {
        $arrFlat = [];
        $arrData = ArrayUtility::detachReferences($this->arrData);
        //toFlatArray dùng collectFlatLeaf để duyệt đệ qui $arrData chứ không dùng traverseTree() 
        //có tác dụng phụ: gọi các callback xử lý, xóa DISCARDED_LEAF và cập nhật trạng thái traversal. Trong khi toFlatArray() chỉ cần đọc và xuất leaf
        $this->collectFlatLeaf($arrData, [], $fnMap, $arrFlat);
        return $arrFlat;
    }

    protected function collectFlatLeaf(
        mixed $mixValue,
        array $arrPath,
        ?Closure $fnMap,
        array &$arrFlat
    ): void {
        $mixDefinition = $this->callDefineLeafFn($arrPath, $mixValue);
        if ($mixDefinition === self::EXCLUDED || $mixDefinition === self::DISCARDED_LEAF) {
            return;
        }
        if (self::isLeafDefinition($mixDefinition)) {
            if ($arrPath !== [] && end($arrPath) === self::$strPetiole) {
                array_pop($arrPath);
            }
            $arrFlat[serialize($arrPath)] = $fnMap === null
                ? $mixValue : $fnMap($arrPath, $mixValue, $mixDefinition);
            return;
        }
        self::validateBranchOrPetioleNode($arrPath, $mixValue);
        foreach ($mixValue as $mixKey => $mixChild) {
            $this->collectFlatLeaf($mixChild, [...$arrPath, $mixKey], $fnMap, $arrFlat);
        }
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

        if ($mixLeafDefinition === self::DISCARDED_LEAF) {
            $this->arrData = [];
            return;
        }

        if ($mixLeafDefinition === self::EXCLUDED) {
            return;
        }

        /*
         * Toàn bộ ExtArray có thể được định nghĩa là một LEAF.
         */
        if (self::isLeafDefinition($mixLeafDefinition)) {
            if ($this->fnProcessLeaf) {
                ($this->fnProcessLeaf)(
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
    // true yêu cầu parent xóa entry; false giữ node. Không phải processing state.
    protected function traverseNode(
        mixed &$value,
        array $arrChain
    ): bool {
        $mixLeafDefinition = $this->callDefineLeafFn(
            $arrChain,
            $value
        );

        if ($mixLeafDefinition === self::DISCARDED_LEAF) {
            return true;
        }

        if ($mixLeafDefinition === self::EXCLUDED) {
            return false;
        }

        if (self::isLeafDefinition($mixLeafDefinition)) {
            if ($this->fnProcessLeaf) {
                ($this->fnProcessLeaf)(
                    $arrChain,
                    $value,
                    $mixLeafDefinition
                );
            }

            $this->iPrevAction = self::LEAF_PROCESSING;
            $this->iLengthPrev = count($arrChain);

            return false;
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

        return false;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function traverseBranch(
        array &$arrData,
        array $arrChain
    ): void {
        if ($this->fnStartBranch) {
            ($this->fnStartBranch)(
                $arrChain
            );
        }

        foreach ($arrData as $key => &$value) {
            $arrNewChain = array_merge(
                $arrChain,
                [$key]
            );

            if ($this->traverseNode( //tình huống cắt lá DISCARDED_LEAF
                $value,
                $arrNewChain
            )) {
                // Xóa entry tại parent, giữ nguyên numeric keys.
                unset($arrData[$key]);
            }
        }

        /*
         * foreach by-reference: bỏ reference tới phần tử cuối.
         */
        unset($value);

        if ($this->fnEndBranch) {
            ($this->fnEndBranch)(
                $arrChain
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Gọi fnDefineLeaf đúng theo prototype mode đã nhận dạng tại setDefineLeafFn().
     *
     * Runtime contract hiện tại:
     *
     *     EXCLUDED
     *     BRANCH_OR_PETIOLE
     *     COMPOSITE_LEAF
     *     array atomic definition
     *
     *     DISCARDED_LEAF (chỉ traversal thực hiện xóa; setValue reject)
     */
    protected function callDefineLeafFn(
        array $arrChain,
        mixed $obj
    ): array|int {
        if (
            $this->iDefineLeafFnArgMode
            === self::CALLBACK_VALUE_ONLY
        ) {
            $mixLeafDefinition = ($this->fnDefineLeaf)(
                $obj
            );
        } else {
            $mixLeafDefinition = ($this->fnDefineLeaf)(
                $arrChain,
                $obj
            );
        }

        if (is_array($mixLeafDefinition)) {
            return $mixLeafDefinition;
        }

        if (!is_int($mixLeafDefinition)) {
            throw new InvalidArgumentException(
                'fnDefineLeaf must return int|array.'
            );
        }

        if (
            $mixLeafDefinition !== self::EXCLUDED
            && $mixLeafDefinition !== self::BRANCH_OR_PETIOLE
            && $mixLeafDefinition !== self::COMPOSITE_LEAF
            && $mixLeafDefinition !== self::DISCARDED_LEAF
        ) {
            throw new InvalidArgumentException(
                'fnDefineLeaf must return EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF, DISCARDED_LEAF, or an array definition.'
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
     * Petiole đơn độc hợp lệ; node tại segment petiole vẫn phải là leaf.
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
                "Invalid '__petiole__' node: '__petiole__' must be a leaf, but fnDefineLeaf returned BRANCH_OR_PETIOLE."
            );
        }

    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Helper cũ, ngừng sử dụng: traversal giữ nguyên petiole đơn độc.
     * Bỏ đúng một lớp petiole đơn độc, không descend vào value lấy ra.
     * Root phải luôn là array, kể cả khi petiole value là scalar/null.
     * Ví dụ ['__petiole__' => ['a', 'b']] thành ['a', 'b'].
    protected static function collapseSolitaryPetiole(
        mixed &$value,
        bool $isRoot = false
    ): void {
        if (
            !is_array($value)
            || count($value) !== 1
            || !array_key_exists(self::$strPetiole, $value)
        ) {
            return;
        }

        $leaf = $value[self::$strPetiole];
        $value = $isRoot && !is_array($leaf) ? [$leaf] : $leaf;
    }
    */

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
     * Gọi fnNormalizeLeaf đúng theo prototype mode đã nhận dạng tại setNormalizeLeafFn().
     */
    protected function callNormalizeLeafFn(
        array $arrChain,
        mixed $obj
    ): array {
        if (
            $this->iNormalizeLeafFnArgMode
            === self::CALLBACK_VALUE_ONLY
        ) {
            $result = ($this->fnNormalizeLeaf)(
                $obj
            );
        } else {
            $result = ($this->fnNormalizeLeaf)(
                $arrChain,
                $obj
            );
        }

        /*
         * Nếu UDA không khai báo return type thì vẫn bắt buộc runtime result phải có err_number và value.
         */
        if (
            !is_array($result)
            || !array_key_exists('err_number', $result)
            || !is_int($result['err_number'])
            || $result['err_number'] < 0
            || !array_key_exists('value', $result)
        ) {
            throw new UnexpectedValueException(
                'fnNormalizeLeaf must return an array containing err_number (non-negative int) and value.'
            );
        }

        return $result;
    }

    /* Normalize candidate before commit; no reference to the target is passed. */
    protected function normalizeValueForSet(array $arrPath, mixed $value): mixed
    {
        $definition = $this->callDefineLeafFn($arrPath, $value);
        if (!self::isLeafDefinition($definition)) {
            throw new LogicException(
                'Cannot set value at path '
                . json_encode($arrPath, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                . ': the candidate must be a leaf.'
            );
        }

        $result = $this->callNormalizeLeafFn($arrPath, $value);
        if ($result['err_number'] !== 0) {
            throw new LogicException(
                'Cannot normalize leaf at path '
                . json_encode($arrPath, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                . '; error code: ' . $result['err_number'] . '.',
                $result['err_number']
            );
        }
        return $result['value'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Hai prototype hợp lệ cho fnDefineLeaf / fnNormalizeLeaf:
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
                'fnDefineLeaf must not return by reference.'
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
                    'fnDefineLeaf return type must be array|int or a subset of these types.'
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
                        'fnDefineLeaf return type must be array|int or a subset of these types.'
                    );
                }
            }

            return;
        }

        throw new InvalidArgumentException(
            'fnDefineLeaf return type must be array|int or a subset of these types.'
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateNormalizeLeafFnReturnType(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);

        if ($ref->returnsReference()) {
            throw new InvalidArgumentException(
                'fnNormalizeLeaf must not return by reference.'
            );
        }

        $returnType = $ref->getReturnType();

        /*
         * Cho phép không khai báo return type; callNormalizeLeafFn() sẽ kiểm tra runtime result phải có err_number và value.
         */
        if ($returnType === null) {
            return;
        }

        if (
            !($returnType instanceof \ReflectionNamedType)
            || $returnType->allowsNull()
            || $returnType->getName() !== 'array'
        ) {
            throw new InvalidArgumentException(
                'fnNormalizeLeaf return type must be array.'
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
     * Trong đó int hiện tại chỉ có COMPOSITE_LEAF khi fnProcessLeaf được gọi.
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
                'fnProcessLeaf must declare three parameters and require no additional arguments.'
            );
        }

        for ($i = 0; $i < 3; $i++) {
            if ($arrParam[$i]->isVariadic()) {
                throw new InvalidArgumentException(
                    'The first three parameters of fnProcessLeaf must not be variadic.'
                );
            }
        }

        if (
            $arrParam[0]->isPassedByReference()
            || !$arrParam[1]->isPassedByReference()
            || $arrParam[2]->isPassedByReference()
        ) {
            throw new InvalidArgumentException(
                'Only the second parameter of fnProcessLeaf must be passed by reference.'
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
                'The first parameter of fnProcessLeaf must accept array paths.'
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
                'The second parameter of fnProcessLeaf must be untyped or mixed.'
            );
        }

        $definitionType = $arrParam[2]->getType();

        if (
            !$acceptsType($definitionType, 'array')
            || !$acceptsType($definitionType, 'int')
        ) {
            throw new InvalidArgumentException(
                'The third parameter of fnProcessLeaf must accept both array and int.'
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
    /* Hiện chưa sử dụng: petiole đơn độc đã là cấu trúc hợp lệ.
    protected static function hasSubBranches(array $ref): bool
    {
        foreach ($ref as $key => $_) {
            if ($key !== self::$strPetiole) {
                return true;
            }
        }

        return false;
    }
    */

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * MODE_MERGE_VALUE là nơi thực sự cần phân biệt representation BRANCH và PETIOLE.
     * fnDefineLeaf chỉ cung cấp BRANCH_OR_PETIOLE; ExtArray tự nhận diện khi cần.
     */
    protected function setValueMVModeAtEndPath(
        array|int $mixLeafDefinition,
        mixed &$ref,
        mixed $value,
        array $arrPath
    ): void {
        if (self::isLeafDefinition($mixLeafDefinition)) {
            $candidate = is_array($ref) && is_array($value)
                ? array_merge($ref, $value) : $value;
            $ref = $this->normalizeValueForSet($arrPath, $candidate);
            return;
        }

        if ($mixLeafDefinition !== self::BRANCH_OR_PETIOLE) {
            throw new LogicException('Invalid leaf definition at setValueMVModeAtEndPath().');
        }

        $candidate = $value;
        if (self::detectBranchOrPetiole($ref) === self::PETIOLE) {
            $old = $ref[self::$strPetiole];
            if (is_array($old) && is_array($value)) {
                $candidate = array_merge($old, $value);
            }
        }

        // Normalize the logical value, never the container's children.
        $normalized = $this->normalizeValueForSet($arrPath, $candidate);
        $ref[self::$strPetiole] = $normalized;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function setValueAtEndPath(
        array|int $mixLeafDefinition,
        mixed &$ref,
        mixed $value,
        string $mode,
        bool $isNewBranch,
        array $arrPath
    ): void {
        if ($mode === self::MODE_REPLACE || $isNewBranch) {
            // A new target has no old value to merge, but still normalizes once.
            $ref = $mode === self::MODE_MERGE_VALUE
                ? $this->normalizeValueForSet($arrPath, $value) : $value;
            return;
        }

        switch ($mode) {
            case self::MODE_MERGE_VALUE:
                $this->setValueMVModeAtEndPath($mixLeafDefinition, $ref, $value, $arrPath);
                break;
            case self::MODE_KEEP_OLD_VALUE:
                break;
            default:
                throw new InvalidArgumentException('Unknown mode: ' . $mode);
        }
    }
}
