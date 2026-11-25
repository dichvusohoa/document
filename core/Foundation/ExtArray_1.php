<?php    
/*ExtArray là một cấu trúc dữ liệu keys/value, trong đó key là một dãy. Kiểu như  obj[key1][key2]....[keyn] = value
key1,key2... thì  dạng dữ liệu dạng đơn giản như string. Như vậy có thể hình dung ExtArray như một dạng hierarchy tree với các
branch node là dạng đơn giản chỉ chứa key, còn leaf node chứa data. Nó khác với các dạng hierarchy tree đầy đủ thì các
branch node cũng chứa các thông tin tổ hợp. Chú ý rằng mục tiêu của ExtArray không phải là tạo dựng một cấu trúc tree data phức tạp và 
đầy đủ, đó là loại tree có node cấu trúc phức tạp, có schema đầy đủ. Về schema thì ExtArray hoặc là không cần
hoặc chỉ cần schema tại data thôi (suy nghĩ thêm sau)

Về định dạng dữ liệu, 
- các dữ liệu tại nút BRANCH chỉ có dạng string hoặc int
- các dữ liệu tại nút LEAF có thể có bất kỳ dạng nào

Chính vì thế nên ExtArray cho phép bổ sung $sFuncFilter($arrChain,$value) để định nghĩa thế nào là 
EXCLUDED (cấm không truy cập), BRANCH, LEAF

 ExtArray cho phép xử lý linh hoạt bằng cách bổ sung thêm 5 call_user_func
    defineLeafFn    → EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF hoặc array definition
    validLeafFn     → kiểm tra VALUE của LEAF có hợp lệ
    processLeafFn   → xử lý/thay đổi LEAF khi traverse; hỗ trợ 1, 2 hoặc 3 tham số theo mức context cần dùng
    $startBranchFn($arrChain): tùy chọn xử lý đầu nhánh
    $endBranchFn($arrChain):   tùy chọn xử lý cuối nhánh
 * Update 2025-07-25
    ExtArray bổ sung thêm phần tử thứ ba ngoài branch, leaf, là petiole (cuống lá). đây
    là phần tử trung gian nhằm giải quyết tình huống tại 1 branch thì vừa có sub-branch vừa có leaf
 *  lúc đó buộc phải dùng petiole để đưa leaf vào branch đó:   
 *  branch - petiole - leaf
 *         - subbranch
 *         - subbranch
 *  Về mặt quan hệ
 * branch -  branch ( 1- nhiều)
 * branch -  leaf   ( 1- 1)
 * petiole - leaf ( 1- 1)
 * 
 * Update 2026-09-18 cho phép các node của branch là string | int
*/
namespace Core\Foundation;
use Closure;
use Throwable;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
/* defineLeafFn xác định topology; validLeafFn kiểm tra value khi setValue. */
class ExtArray { 
    public const EXCLUDED = 0; // Bỏ qua node và toàn bộ subtree.
    const BRANCH = 1; /*Dùng cho hàm filter value 1 mean nhánh*/
    public const COMPOSITE_LEAF = 'composite_leaf';
    public const BRANCH_OR_PETIOLE = 'branch_or_petiole';
    const LEAF = 2; /*Dùng cho hàm filter value 2 mean lá*/
    const PETIOLE = 3; /*Dùng cho hàm filter value 2 mean cuống*/
    const BRANCH_PROCESSING  = 1; /*Hành động xử lý nhánh*/
    const LEAF_PROCESSING  = 2; /*Hành động xử lý lá*/
    /*đặt tên dặt biệt cho cuống lá để khỏi lẫn với tên các branch khác */
    const  PETIOLE_DEFAULT_NAME = '__petiole__';

    // Các mode cho setValue
    const MODE_REPLACE          = 'replace';
    const MODE_KEEP_OLD_VALUE   = 'keep_old_value';
    const MODE_MERGE_VALUE      = 'merge_value';

    // Các field của kết quả classifyNode().
    protected const FIELD_NODE_TYPE       = 'node_type';
    protected const FIELD_LEAF_DEFINITION = 'leaf_definition';

    // Prototype mode của defineLeafFn / validLeafFn / processLeafFn.
    protected const CALLBACK_VALUE_ONLY            = 1;
    protected const CALLBACK_PATH_VALUE            = 2;
    protected const CALLBACK_PATH_VALUE_DEFINITION = 3;
    
    //Begin các Closure dùng khi duyệt cây
    protected Closure  $defineLeafFn;// Xác định node hiện tại có phải LEAF hay không.
    protected Closure  $validLeafFn;
    protected ?Closure $processLeafFn = null;
    protected ?Closure $startBranchFn = null;
    protected ?Closure $endBranchFn = null;

    protected int $iDefineLeafFnArgMode;
    protected int $iValidLeafFnArgMode;
    protected ?int $iProcessLeafFnArgMode = null;
    //End các Closure dùng khi duyệt cây
    
    static protected string $strPetiole = self::PETIOLE_DEFAULT_NAME;
    public array $arrData;
    protected bool $isExternalRef;
 
    protected ?int $iPrevAction; /*Bổ sung 2024-03-12 Lưu giữ loại xử lý trước đó*/
    protected ?int $iLengthPrev; /*Bổ sung 2024-03-12. Lưu giữ độ sâu xử lý trước đó. Sử dụng $iLengthPrev để dùng
    cho một số trường  hợp xử lý nút leaf mà cấn biết trạng thái của previous leaf ví dụ như trường hợp dùng ExtArray để
    print các cấu trúc như <UL><LI>*/
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Truyền vào $arrData dạng reference để không tạo bản sao dữ liệu và các hàm trong ExtData sẽ làm thay đổi
    khối dữ liệu bên ngoài */
    function __construct(&$arrData = null){
        if ($arrData === null) {
            $this->arrData = []; // Chế độ "bảo vệ nội bộ"
            $this->isExternalRef = false;
        } else if(is_array($arrData)){
            $this->arrData = &$arrData; // Chế độ "tham chiếu bên ngoài"
            $this->isExternalRef = true;
        }
        else{
            throw new InvalidArgumentException("Constructor expects null or array reference");
        }
        //làm thế này để khi sử dụng ExtArray buộc phải đặt giá trị cho defineLeafFn
        $this->defineLeafFn = function(
            array $arrChain,
            mixed $obj
        ): array|int|string {
            throw new LogicException(
                'Leaf definition function must be set before use.'
            );
        };
        $this->iDefineLeafFnArgMode = self::CALLBACK_PATH_VALUE;

        //làm thế này để khi sử dụng ExtArray buộc phải đặt giá trị cho validLeafFn
        $this->validLeafFn = function(
            mixed $obj
        ): bool {
            throw new InvalidArgumentException(
                'Validation function for leaf nodes must be set before use.'
            );
        };
        $this->iValidLeafFnArgMode = self::CALLBACK_VALUE_ONLY;
       // $this->filterFn = Closure::fromCallable([$this, 'defaultFilterFn']);
        $this->iPrevAction = null;
        $this->iLengthPrev = null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setDefineLeafFn(Closure $fn): void{
        $fn = $fn->bindTo($this, static::class);
        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind defineLeafFn.'
            );
        }

        $this->iDefineLeafFnArgMode =
            self::detectLeafCallbackArgMode(
                $fn,
                'defineLeafFn'
            );

        self::validateDefineLeafFnReturnType($fn);

        $this->defineLeafFn = $fn;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setValidLeafFn(Closure $fn): void {
        $fn = $fn->bindTo($this, static::class);
        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind validLeafFn.'
            );
        }

        $this->iValidLeafFnArgMode =
            self::detectLeafCallbackArgMode(
                $fn,
                'validLeafFn'
            );

        self::validateValidLeafFnReturnType($fn);

        $this->validLeafFn = $fn;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setProcessLeafFn(Closure $fn): void{
        $fn = $fn->bindTo($this, static::class);
        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind processLeafFn.'
            );
        }

        $this->iProcessLeafFnArgMode =
            self::detectProcessLeafFnArgMode($fn);

        self::validateProcessLeafFnReturnType($fn);

        $this->processLeafFn = $fn;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setStartBranchFn(Closure $fn): void {
        $fn = $fn->bindTo($this, static::class);
        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind setStartBranchFn.'
            );
        }
        $this->startBranchFn = $fn;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEndBranchFn(Closure $fn): void {
        $fn = $fn->bindTo($this, static::class);
        if ($fn === null) {
            throw new InvalidArgumentException(
                'Cannot bind setEndBranchFn.'
            );
        }
        $this->endBranchFn = $fn;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function setPetioleName(string $strPetioleName): void{
        self::$strPetiole = $strPetioleName;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Return true nếu $arrElement là một nhánh hoặc một phần nhánh của array và false nếu ngược lại
     * Update 2025-05-24
     */
    public function existElement(string|int|array $arrElement): bool{
        $result = $this->getExistInfoAndValue($arrElement);
        return $result["exists"]; 
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setValue(string|int|array $arrPath,$value,$sOption = self::MODE_REPLACE):void{
        if(!is_array($arrPath)){
            $arrPath = [$arrPath];
        }

        if(!$this->callValidLeafFn($arrPath,$value)){
            throw new LogicException('Invalid value: this leaf value does not pass the defined validation rule.');
        }

        $iLength = count($arrPath);
        //__construct check là type array trước nên tới đây chắc chắn $ref là array
        $ref = &$this->arrData;
        $isNewBranch = false;
        $petioleExists  =  false;
        $arrTmp = [];
        $arrUndo = [];
        $isCommitted = false;
        try {
            for($i=0; $i < $iLength; $i++){
                $node = $arrPath[$i];
                self::validateBranchNode($node, $petioleExists);
                if($node === self::$strPetiole){
                    $petioleExists = true;
                }

                $arrNodeInfo = $this->classifyNode($arrTmp,$ref);
                $iPosType = $arrNodeInfo[self::FIELD_NODE_TYPE];

                if ($iPosType === self::EXCLUDED) {
                    return;
                }
                if($iPosType === self::LEAF){
                    $arrUndo[] = ['kind' => 'replace', 'ref' => &$ref, 'old' => $ref];
                    $ref = [self::$strPetiole => $ref];//cho mọc thêm một cuống lá
                }
                //if(!isset($ref[$node])){
                if(!array_key_exists($node, $ref)){
                    $arrUndo[] = ['kind' => 'insert', 'ref' => &$ref, 'key' => $node];
                    $ref[$node] = [];
                    $isNewBranch = true;
                }
                //tiến sâu thêm một mức, chú ý phải dùng reference.
                $ref = &$ref[$node];
                array_push($arrTmp,$node);
            }

            $arrNodeInfo = $this->classifyNode($arrPath,$ref);
            $iPosType = $arrNodeInfo[self::FIELD_NODE_TYPE];

            if ($iPosType === self::EXCLUDED) {
                return;
            }

            self::setValueAtEndPath($iPosType,$ref,$value,$sOption,$isNewBranch);
            $isCommitted = true;
        } finally {
            // Không để lại nhánh rỗng/cuống tạm khi gặp EXCLUDED hoặc lỗi.
            if (!$isCommitted) {
                for ($iUndo = count($arrUndo) - 1; $iUndo >= 0; $iUndo--) {
                    if ($arrUndo[$iUndo]['kind'] === 'insert') {
                        unset($arrUndo[$iUndo]['ref'][$arrUndo[$iUndo]['key']]);
                    } else {
                        $arrUndo[$iUndo]['ref'] = $arrUndo[$iUndo]['old'];
                    }
                }
            }
            unset($ref, $arrUndo);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/   
    public function getValue(string|int|array $arrElement):mixed{
        $result = $this->getExistInfoAndValue($arrElement);
        return $result['value']; 
    }
    /*---------------------------------------------------------------------------------------------------------------*/   
    public function &getReferenceAt(string|int|array $arrElement){
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
    /*Return value nếu $arrElement là một nhánh hoặc một phần nhánh của array và NULL nếu không phải
     * Update 2024-03-24
     * Update 2025-05-24
     * Update 2025-07-25
     * Update 2025-09-07, bổ sung $isGetByReference
     */
    public function getExistInfoAndValue(string|int|array $arrElement, 
            bool $isGetByReference = false): array {
        $arrChunk = &$this->arrData; //dùng reference để tránh copy array. tăng hiệu suất
         // Nếu là một giá trị đơn (string), chuyển thành mảng
        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }
        $iLength = count($arrElement);
        $element = '';
        $petioleExists  =  false;
        for($i=0; $i < $iLength; $i++){
            $element = $arrElement[$i];
            /*Bỏ đi vì self::validateBranchNode($element,$petioleExists); sẽ phát hiện lỗi ở bước tiếp theo
            if($element === self::$strPetiole && $i < $iLength-1){
                throw new LogicException('"__petiole__" must be the last element in the path');
            }*/
            self::validateBranchNode($element,$petioleExists);
            if($element === self::$strPetiole){
                $petioleExists =  true;
            }
            if(is_array($arrChunk)&& array_key_exists($element, $arrChunk)){
                //dùng reference để tránh copy array. tăng hiệu suất. Tiến sâu vào trong
                $arrChunk = &$arrChunk[$element];
            }
            else{
                return ['exists' => false, 'value' => null];
            }
        }
        //nếu $arrElement ngắn hơn độ sâu của $this->arrData thì chạy đến đây
        //$hasPetiole = isset($arrChunk[self::$strPetiole]);
        $hasPetiole = is_array($arrChunk)
        && array_key_exists(
            self::$strPetiole,
            $arrChunk
        );
        if($hasPetiole){
            $hasSubBranches = self::hasSubBranches($arrChunk);
            if(!$hasSubBranches){
                //cuống lá chỉ là giải pháp khi có các nhánh phụ mọc cùng. không có nhánh phụ thì không cần cuống lá
                //throw new LogicException('Khi không có nhánh phụ thì không cần mọc cuống lá');
                throw new LogicException("A petiole should not be created when there are no sub-branches.");
            }
            if($element === self::$strPetiole){
                //tình huống vô lý vì có 2 cái cuống lá nối tiếp
                throw new LogicException('A "__petiole__" node cannot be followed by another key — it must be the leaf holder');
            }
            //tới vị trí cuống lá, lấy dữ liệu sâu hơn một mức 
            
            if($isGetByReference){
                return ['exists' => true, 'value' => &$arrChunk[self::$strPetiole]];
            }
            else{
                return ['exists' => true, 'value' => $arrChunk[self::$strPetiole]];
            }
            
        }
        if($isGetByReference){
            return ['exists' => true, 'value' => &$arrChunk];
        }
        else{
            return ['exists' => true, 'value' => $arrChunk];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function isEmpty(): bool{
        return $this->arrData === [];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function classifyNode(
        array $arrChain,
        mixed $obj
    ): array {

        //1. Xác định definition do tầng sử dụng cung cấp.
        $mixLeafDefinition = $this->callDefineLeafFn(
            $arrChain,
            $obj
        );

        if ($mixLeafDefinition === self::EXCLUDED) {
            return [
                self::FIELD_NODE_TYPE       => self::EXCLUDED,
                self::FIELD_LEAF_DEFINITION => null,
            ];
        }

        if (
            $mixLeafDefinition === self::COMPOSITE_LEAF
            || is_array($mixLeafDefinition)
        ) {
            return [
                self::FIELD_NODE_TYPE       => self::LEAF,
                self::FIELD_LEAF_DEFINITION => $mixLeafDefinition,
            ];
        }

        if ($mixLeafDefinition !== self::BRANCH_OR_PETIOLE) {
            throw new InvalidArgumentException(
                'defineLeafFn must return EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF, or an array definition.'
            );
        }

        //2. Tính toán tiếp xem là nhánh hay cuống lá.
        if(!is_array($obj)){
            throw new LogicException(
                "To create branches or a petiole at this node, the data must be an array."
            );
        }

        if(end($arrChain) === self::$strPetiole){
            throw new LogicException(
                "Invalid '__petiole__' node: expected a valid leaf but found an invalid structure."
            );
        }

        $hasPetiole = array_key_exists(
            self::$strPetiole,
            $obj
        );

        if($hasPetiole){
            $hasSubBranches = self::hasSubBranches($obj);

            if($hasSubBranches){
                return [
                    self::FIELD_NODE_TYPE       => self::PETIOLE,
                    self::FIELD_LEAF_DEFINITION => null,
                ];
            }

            throw new LogicException(
                "Petiole creation is invalid when no child branches are present."
            );
        }

        return [
            self::FIELD_NODE_TYPE       => self::BRANCH,
            self::FIELD_LEAF_DEFINITION => null,
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function traverseTree(): void
    {
        /*
         * Mỗi lần traverse là một traversal độc lập.
         */
        $this->iPrevAction = null;
        $this->iLengthPrev = null;

        /*
         * Root cũng phải được classify.
         */
        $arrNodeInfo = $this->classifyNode(
            [],
            $this->arrData
        );

        $iNodeType =
            $arrNodeInfo[self::FIELD_NODE_TYPE];

        if ($iNodeType === self::EXCLUDED) {
            return;
        }

        /*
         * Toàn bộ ExtArray có thể được định nghĩa là một LEAF.
         */
        if ($iNodeType === self::LEAF) {
            if ($this->processLeafFn) {
                $this->callProcessLeafFn(
                    [],
                    $this->arrData,
                    $arrNodeInfo[self::FIELD_LEAF_DEFINITION]
                );
            }

            $this->iPrevAction = self::LEAF_PROCESSING;
            $this->iLengthPrev = 0;

            return;
        }

        /*
         * Root là BRANCH hoặc PETIOLE.
         */
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
        $arrNodeInfo = $this->classifyNode(
            $arrChain,
            $value
        );

        $iNodeType =
            $arrNodeInfo[self::FIELD_NODE_TYPE];

        if ($iNodeType === self::EXCLUDED) {
            return;
        }

        if ($iNodeType === self::LEAF) {
            if ($this->processLeafFn) {
                $this->callProcessLeafFn(
                    $arrChain,
                    $value,
                    $arrNodeInfo[self::FIELD_LEAF_DEFINITION]
                );
            }

            $this->iPrevAction = self::LEAF_PROCESSING;
            $this->iLengthPrev = count($arrChain);

            return;
        }

        /*
         * BRANCH và PETIOLE đều tiếp tục traverse xuống dưới.
         *
         * classifyNode() đã bảo đảm rằng nếu node không phải LEAF
         * thì cấu trúc ở đây phải phù hợp để phân nhánh.
         */
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
         * foreach by-reference:
         * bỏ reference tới phần tử cuối.
         */
        unset($value);

        if ($this->endBranchFn) {
            ($this->endBranchFn)(
                $arrChain
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function callDefineLeafFn(
        array $arrChain,
        mixed $obj
    ): array|int|string {
        if (
            $this->iDefineLeafFnArgMode
            === self::CALLBACK_VALUE_ONLY
        ) {
            $mixResult = ($this->defineLeafFn)($obj);
        }
        else {
            $mixResult = ($this->defineLeafFn)(
                $arrChain,
                $obj
            );
        }

        if (
            !is_array($mixResult)
            && !is_int($mixResult)
            && !is_string($mixResult)
        ) {
            throw new LogicException(
                'defineLeafFn must return array, int, or string.'
            );
        }

        return $mixResult;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function callValidLeafFn(
        array $arrChain,
        mixed $obj
    ): bool {
        if (
            $this->iValidLeafFnArgMode
            === self::CALLBACK_VALUE_ONLY
        ) {
            $mixResult = ($this->validLeafFn)($obj);
        }
        else {
            $mixResult = ($this->validLeafFn)(
                $arrChain,
                $obj
            );
        }

        if (!is_bool($mixResult)) {
            throw new LogicException(
                'validLeafFn must return bool.'
            );
        }

        return $mixResult;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function callProcessLeafFn(
        array $arrChain,
        mixed &$value,
        array|string $leafDefinition
    ): void {
        switch ($this->iProcessLeafFnArgMode) {
            case self::CALLBACK_VALUE_ONLY:
                ($this->processLeafFn)(
                    $value
                );
                break;

            case self::CALLBACK_PATH_VALUE:
                ($this->processLeafFn)(
                    $arrChain,
                    $value
                );
                break;

            case self::CALLBACK_PATH_VALUE_DEFINITION:
                ($this->processLeafFn)(
                    $arrChain,
                    $value,
                    $leafDefinition
                );
                break;

            default:
                throw new LogicException(
                    'Invalid processLeafFn argument mode.'
                );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Hai prototype hợp lệ:
     *
     *     function(mixed $obj)
     *
     * hoặc
     *
     *     function(array $arrChain, mixed $obj)
     *
     * $obj có thể không khai báo type hoặc khai báo mixed.
     * Nếu có $arrChain thì parameter này phải khai báo type array.
     */
    protected static function detectLeafCallbackArgMode(
        Closure $fn,
        string $strCallbackName
    ): int {
        $ref = new ReflectionFunction($fn);
        $arrParam = $ref->getParameters();
        $iParamCount = count($arrParam);

        if (
            $iParamCount !== 1
            && $iParamCount !== 2
        ) {
            throw new InvalidArgumentException(
                $strCallbackName
                . ' must declare either one parameter '
                . '(mixed $obj) or two parameters '
                . '(array $arrChain, mixed $obj).'
            );
        }

        if (
            $ref->getNumberOfRequiredParameters()
            !== $iParamCount
        ) {
            throw new InvalidArgumentException(
                $strCallbackName
                . ' parameters must not be optional.'
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
            self::validateLeafValueParameter(
                $arrParam[0],
                $strCallbackName
            );

            return self::CALLBACK_VALUE_ONLY;
        }

        $pathType = $arrParam[0]->getType();

        if (
            !($pathType instanceof \ReflectionNamedType)
            || $pathType->getName() !== 'array'
            || $pathType->allowsNull()
        ) {
            throw new InvalidArgumentException(
                'The first parameter of '
                . $strCallbackName
                . ' must be array $arrChain.'
            );
        }

        self::validateLeafValueParameter(
            $arrParam[1],
            $strCallbackName
        );

        return self::CALLBACK_PATH_VALUE;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateLeafValueParameter(
        \ReflectionParameter $param,
        string $strCallbackName
    ): void {
        $type = $param->getType();

        if ($type === null) {
            return;
        }

        if (
            $type instanceof \ReflectionNamedType
            && $type->getName() === 'mixed'
        ) {
            return;
        }

        throw new InvalidArgumentException(
            'The value parameter of '
            . $strCallbackName
            . ' must be untyped or mixed.'
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateDefineLeafFnReturnType(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);
        $type = $ref->getReturnType();

        if ($type === null) {
            return;
        }

        $arrAllowedType = [
            'array',
            'int',
            'string',
        ];

        if ($type instanceof \ReflectionNamedType) {
            if (
                in_array(
                    $type->getName(),
                    $arrAllowedType,
                    true
                )
                && !$type->allowsNull()
            ) {
                return;
            }

            throw new InvalidArgumentException(
                'defineLeafFn return type must be '
                . 'array, int, string, or a union of these types.'
            );
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $part) {
                if (
                    !($part instanceof \ReflectionNamedType)
                    || !in_array(
                        $part->getName(),
                        $arrAllowedType,
                        true
                    )
                    || $part->allowsNull()
                ) {
                    throw new InvalidArgumentException(
                        'defineLeafFn return type must be '
                        . 'array, int, string, or a union of these types.'
                    );
                }
            }

            return;
        }

        throw new InvalidArgumentException(
            'defineLeafFn return type must be '
            . 'array, int, string, or a union of these types.'
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateValidLeafFnReturnType(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);
        $type = $ref->getReturnType();

        if ($type === null) {
            return;
        }

        if (
            $type instanceof \ReflectionNamedType
            && $type->getName() === 'bool'
            && !$type->allowsNull()
        ) {
            return;
        }

        throw new InvalidArgumentException(
            'validLeafFn return type must be bool.'
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Các prototype hợp lệ:
     *
     *     function(mixed &$value): void
     *
     * hoặc
     *
     *     function(array $arrChain, mixed &$value): void
     *
     * hoặc
     *
     *     function(
     *         array $arrChain,
     *         mixed &$value,
     *         array|string $leafDefinition
     *     ): void
     *
     * $value có thể không khai báo type hoặc khai báo mixed.
     * Nếu có $arrChain thì parameter này phải khai báo type array.
     * Nếu có $leafDefinition thì parameter này phải chấp nhận cả array và string.
     */
    protected static function detectProcessLeafFnArgMode(
        Closure $fn
    ): int {
        $ref = new ReflectionFunction($fn);
        $arrParam = $ref->getParameters();
        $iParamCount = count($arrParam);

        if (
            $iParamCount < 1
            || $iParamCount > 3
        ) {
            throw new InvalidArgumentException(
                'processLeafFn must declare one of these prototypes: '
                . '(mixed &$value), '
                . '(array $arrChain, mixed &$value), or '
                . '(array $arrChain, mixed &$value, array|string $leafDefinition).'
            );
        }

        if (
            $ref->getNumberOfRequiredParameters()
            !== $iParamCount
        ) {
            throw new InvalidArgumentException(
                'processLeafFn parameters must not be optional.'
            );
        }

        foreach ($arrParam as $param) {
            if ($param->isVariadic()) {
                throw new InvalidArgumentException(
                    'processLeafFn parameters must not be variadic.'
                );
            }
        }

        if ($iParamCount === 1) {
            if (!$arrParam[0]->isPassedByReference()) {
                throw new InvalidArgumentException(
                    'The value parameter of processLeafFn must be passed by reference.'
                );
            }

            self::validateLeafValueParameter(
                $arrParam[0],
                'processLeafFn'
            );

            return self::CALLBACK_VALUE_ONLY;
        }

        // Với prototype có path, parameter đầu tiên luôn là array $arrChain.
        if ($arrParam[0]->isPassedByReference()) {
            throw new InvalidArgumentException(
                'The path parameter of processLeafFn must not be passed by reference.'
            );
        }

        $pathType = $arrParam[0]->getType();

        if (
            !($pathType instanceof \ReflectionNamedType)
            || $pathType->getName() !== 'array'
            || $pathType->allowsNull()
        ) {
            throw new InvalidArgumentException(
                'The first parameter of processLeafFn must be array $arrChain.'
            );
        }

        if (!$arrParam[1]->isPassedByReference()) {
            throw new InvalidArgumentException(
                'The value parameter of processLeafFn must be passed by reference.'
            );
        }

        self::validateLeafValueParameter(
            $arrParam[1],
            'processLeafFn'
        );

        if ($iParamCount === 2) {
            return self::CALLBACK_PATH_VALUE;
        }

        // Prototype ba tham số: parameter thứ ba là leafDefinition.
        if ($arrParam[2]->isPassedByReference()) {
            throw new InvalidArgumentException(
                'The leafDefinition parameter of processLeafFn must not be passed by reference.'
            );
        }

        if (!self::acceptsArrayAndString($arrParam[2]->getType())) {
            throw new InvalidArgumentException(
                'The third parameter of processLeafFn must accept both array and string.'
            );
        }

        return self::CALLBACK_PATH_VALUE_DEFINITION;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function acceptsArrayAndString(
        ?\ReflectionType $type
    ): bool {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName() === 'mixed';
        }

        if ($type instanceof \ReflectionUnionType) {
            $hasArray = false;
            $hasString = false;

            foreach ($type->getTypes() as $part) {
                if (!($part instanceof \ReflectionNamedType)) {
                    return false;
                }

                $strName = $part->getName();

                if ($strName === 'array') {
                    $hasArray = true;
                }
                else if ($strName === 'string') {
                    $hasString = true;
                }
                else {
                    return false;
                }
            }

            return $hasArray && $hasString;
        }

        return false;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateProcessLeafFnReturnType(
        Closure $fn
    ): void {
        $ref = new ReflectionFunction($fn);
        $type = $ref->getReturnType();

        if ($type === null) {
            return;
        }

        if (
            $type instanceof \ReflectionNamedType
            && $type->getName() === 'void'
            && !$type->allowsNull()
        ) {
            return;
        }

        throw new InvalidArgumentException(
            'processLeafFn return type must be void.'
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateBranchNode(string|int $key, ?bool $petioleExists  = null): void {
        if($petioleExists  === null){
            return;
        }
        if($petioleExists  === true){
            if($key === self::$strPetiole){
                // không thể có nhiều hơn 1 cuống trên 1 nhánh
                throw new LogicException("Invalid path: multiple '__petiole__' keys in the same branch.");
            }
            else{
                //vô lý vì đã có cuống lá rồi mà nhánh lại nối dài ra chưa kết thúc
                throw new LogicException("Invalid path: '__petiole__' must be the terminal key in a branch.");
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/  
    protected static function hasSubBranches(array $ref): bool {
        foreach ($ref as $key => $_) {
            if ($key !== self::$strPetiole) return true;
        }
        return false;
    }
    /*---------------------------------------------------------------------------------------------------------------*/  
    protected static function setValueMVModeAtEndPath($iPosType, &$ref, $value): void {
        if($iPosType === self::LEAF){
            if(is_array($ref) && is_array($value)){
                $ref =  array_merge($ref, $value);
            }
            else{
                $ref = $value;
            }
        }
        else if($iPosType === self::PETIOLE){
            if(is_array($ref[self::$strPetiole]) && is_array($value)){
                $ref[self::$strPetiole] =  array_merge($ref[self::$strPetiole], $value);
            }
            else{
                $ref[self::$strPetiole] = $value;
            }
        }
        else if ($iPosType === self::BRANCH){
            $ref[self::$strPetiole] = $value;
        }
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function setValueAtEndPath($iPosType, &$ref, $value, $mode, $isNewBranch): void {
        if ($mode === self::MODE_REPLACE || $isNewBranch) {
            $ref = $value;
            return;
        }
        switch ($mode) {
            case self::MODE_MERGE_VALUE:
                self::setValueMVModeAtEndPath($iPosType,$ref, $value);
                break;
            case self::MODE_KEEP_OLD_VALUE:
                // do nothing
                break;
            default:
                throw new InvalidArgumentException("Unknown mode: $mode");
        }
    }
}        
