<?php    
/*ExtArray là một cấu trúc dữ liệu keys/value, trong đó key là một dãy. Kiểu như  obj[key1][key2]....[keyn] = value
key1,key2... thì  dạng dữ liệu dạng đơn giản như string. Như vậy có thể hình dung ExtArray như một dạng hierarchy tree với các
branch node là dạng đơn giản chỉ chứa key, còn leaf node chứa data. Nó khác với các dạng hierarchy tree đầy đủ thì các
branch node cũng chứa các thông tin tổ hợp. Chú ý rằng mục tiêu của ExtArray không phải là tạo dựng một cấu trúc tree data phức tạp và 
đầy đủ, đó là loại tree có node cấu trúc phức tạp, có schema đầy đủ. Về schema thì ExtArray hoặc là không cần
hoặc chỉ cần schema tại data thôi (suy nghĩ thêm sau)

Về định dạng dữ liệu, 
- các dữ liệu tại nút BRANCH chỉ có dạng string
- các dữ liệu tại nút LEAF có thể có bất kỳ dạng nào

Chính vì thế nên ExtArray cho phép bổ sung $sFuncFilter($arrChain,$value) để định nghĩa thế nào là 
EXCLUDED (cấm không truy cập), BRANCH, LEAF

 ExtArray cho phép xử lý linh hoạt bằng cách bổ sung thêm 4 call_user_func
    $sFuncFilter($arrChain,$value): căn cứ vào vị trí đang duyệt định nghĩa thế nào là  EXCLUDED,BRANCH,LEAF       
    $sFuncProcessLeaf($arrChain,$value): nếu $sFuncFilter trả về giá trị LEAF thì gọi hàm này
    $sFuncStartBranch($arrChain): tùy chọn xử lý đầu nhánh
    $sFuncEndBranch($arrChain):   tùy chọn xử lý cuối nhánh
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
*/
namespace Core\Models;
use \Closure;
use \Throwable;
use \InvalidArgumentException;
use \LogicException;
/*Khi sử dụng ExtArray bắt buộc phải set closure validLeafFn. Có validLeafFn thì
 ExtArray mới hiểu đâu là nút lá, nó mới phân định ra được trong 1 dãy nút, thì tới 
đâu là LEAF */
class ExtArray { 
    const EXCLUDED = 0 ; /*Dùng cho hàm filter value 0 mean loại trừ không xử lý*/
    const BRANCH = 1; /*Dùng cho hàm filter value 1 mean nhánh*/
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
    
    //Begin các Closure dùng khi duyệt cây
    protected Closure $validLeafFn;
    protected Closure $filterFn;
    protected ?Closure $leafFn = null;
    protected ?Closure $startBranchFn = null;
    protected ?Closure $endBranchFn = null;
    //End các Closure dùng khi duyệt cây
    
    static protected string $strPetiole = self::PETIOLE_DEFAULT_NAME;
    public array $arrData;
    protected bool $isExternalRef;
    protected bool $isValidLeafFn; // đánh dấu xem đã chạy kiểm tra valid hàm leafFn chưa

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
        $this->validLeafFn = function($obj) {
            //làm thế này để khi sử dụng ExtArray buộc phải đặt giá trị cho validLeafFn
            throw new InvalidArgumentException("Validation function for leaf nodes must be set before use.");
        };
        $this->filterFn = Closure::fromCallable([$this, 'defaultFilterFn']);
        $this->isValidLeafFn = false;
        $this->iPrevAction = null;
        $this->iLengthPrev = null;
       
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setValidLeafFn(Closure $fn): void {
        $this->validLeafFn = $fn->bindTo($this, static::class);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setFilterFn(Closure $fn): void {
        $this->filterFn = $fn->bindTo($this, static::class);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setStartBranchFn(Closure $fn): void {
        $this->startBranchFn = $fn->bindTo($this, static::class);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEndBranchFn(Closure $fn): void {
        $this->endBranchFn = $fn->bindTo($this, static::class);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function setPetioleName(string $strPetioleName): void{
        self::$strPetiole = $strPetioleName;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Return true nếu $arrElement là một nhánh hoặc một phần nhánh của array và false nếu ngược lại
     * Update 2025-05-24
     */
    public function existElement(string|array $arrElement): bool{
        $result = $this->getExistInfoAndValue($arrElement);
        return $result["exists"]; 
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setValue(array|string $arrPath,$value,$sOption = self::MODE_REPLACE):void{
        if(!($this->validLeafFn)($value)){
            throw new LogicException('Invalid value: this leaf value does not pass the defined validation rule.');
        }
        if(!is_array($arrPath)){
            $arrPath = [$arrPath];
        }
        $iLength = count($arrPath);
        //__construct check là type array trước nên tới đây chắc chắn $ref là array
        $ref = &$this->arrData;
        $isNewBranch = false;
        $petioleExists  =  false;
        $arrTmp = [];
        for($i=0; $i < $iLength; $i++){
            $node = $arrPath[$i];
            self::validateBranchNode($node, $petioleExists);
            if($node === self::$strPetiole){
                $petioleExists = true;
            }
            $strPosType = ($this->filterFn)($arrTmp,$ref);
            if($strPosType === self::EXCLUDED){
                return;
            }
            if($strPosType === self::LEAF){
                $ref = [self::$strPetiole => $ref];//cho mọc thêm một cuống lá
            }
            if(!isset($ref[$node])){
                $ref[$node] = [];
                $isNewBranch = true;
            }
            //tiến sâu thêm một mức, chú ý phải dùng reference.
            $ref = &$ref[$node];
            array_push($arrTmp,$node);
        }
        $strPosType = ($this->filterFn)($arrPath,$ref);
        if($strPosType !== self::EXCLUDED){
            self::setValueAtEndPath($strPosType,$ref,$value,$sOption,$isNewBranch);
        }
        unset($ref);
    }
    /*---------------------------------------------------------------------------------------------------------------*/   
    public function getValue(string|array $arrElement):mixed{
        $result = $this->getExistInfoAndValue($arrElement);
        return $result['value']; 
    }
    /*---------------------------------------------------------------------------------------------------------------*/   
    public function &getReferenceAt(string|array $arrElement){
        $result = $this->getExistInfoAndValue($arrElement, true);
        return $result['value'] ;
    }
    /*---------------------------------------------------------------------------------------------------------------*/   
    /*Return value nếu $arrElement là một nhánh hoặc một phần nhánh của array và NULL nếu không phải
     * Update 2024-03-24
     * Update 2025-05-24
     * Update 2025-07-25
     * Update 2025-09-07, bổ sung $isGetByReference
     */
    public function getExistInfoAndValue(string|array $arrElement, $isGetByReference = false): array {
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
            else if($element === $arrChunk && $i === $iLength-1){
                //trường hợp này xảy ra khi $arrElement có độ sâu đúng bằng độ sâu một nhánh nào đó của  $this->arrData 
                //khi đi đến nhánh này thì $arrChunk chỉ còn là một value đơn giản string
                //ví dụ của nhánh này:
                // $arr = ["a"=>["b"=>["c"=>"d"]]]
                //$obj = new ExtArray($arr);
                //$res = $obj->getValue(["a","b","c","d"]);
                return ['exists' => true, 'value' => null]; 
            }
            else{
                return ['exists' => false, 'value' => null];
            }
        }
        //nếu $arrElement ngắn hơn độ sâu của $this->arrData thì chạy đến đây
        $hasPetiole = isset($arrChunk[self::$strPetiole]);
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
    /*Đây là một trường hợp cá biệt được thiết kế riêng của filterFn. Mục đích là để phục vụ
    cho hàm isValid sau này sẽ valid cả khối dữ liệu 
     Các hàm FilterFn khác có thể tham khảo hàm này để làm khuôn mẫu. 
     * defaultFilterFn này không có trường hợp return EXCLUDED. Cái đó do từng trường hợp sẽ custom lại
     */
    public function defaultFilterFn(array $chain,$obj) {
        /* bỏ đi vì count($chain) === 0 giờ cũng cho phép để mỏ rộng tổng quát
        if(count($chain) === 0){//tình huống phi lý, hàm Filter luôn hoạt động khi $chain có ít nhất 1 phần tử
            throw new LogicException("Invalid path: empty or non-array chain encountered during tree traversal.");
        }*/
        //1. Đạt tới vị trí LEAF
        if(($this->validLeafFn)($obj)){
            return self::LEAF;
        }
        //2. Tính toán tiếp xem là nhánh hay cuống lá
        if(!is_array($obj)){
            //throw new LogicException('muốn phân nhánh hoặc có cuống lá tại đây thì dữ liệu tại đây phải là array');
            throw new LogicException("To create branches or a petiole at this node, the data must be an array.");
        }
        if(end($chain) === self::$strPetiole){
            // vô lý vì nếu đã chạm tới cuống lá mà $obj lại không phải là lá
            throw new LogicException("Invalid '__petiole__' node: expected a valid leaf but found an invalid structure.");
        }
        $hasPetiole = isset($obj[self::$strPetiole]);
        if($hasPetiole){
            $hasSubBranches = self::hasSubBranches($obj);
            if($hasSubBranches){
                //3. Tồn tại cả cuống lá + nhánh phụ. Hợp lệ => là cuống lá
                return self::PETIOLE;
            }
            //cuống lá chỉ là giải pháp khi có các nhánh phụ mọc cùng. không có nhánh phụ thì không cần cuống lá
            //throw new LogicException('Khi không có nhánh phụ thì không cần mọc cuống lá');
            throw new LogicException("Petiole creation is invalid when no child branches are present.");
        }
        //4. không có cuống lá vì $obj là array nên chắc chắn có các nhánh phụ
        return self::BRANCH;
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*chỉ cần định nghĩa lại chính xác lại $this->validLeafFn cho nút lá*/
    public function isValid(): bool{
        try{
            $this->filterFn = Closure::fromCallable([$this, 'defaultFilterFn']);
            $this->startBranchFn = null;
            $this->endBranchFn = null;
            $type = ($this->filterFn)([], $this->arrData);
            if($type === self::LEAF){
                //tình huống này ít nhưng cũng cho phép để đảm bảo tính tổng quát.
                //đây là tình huống kiểu như 'no router', không có định tuyến.
                //chỉ có một 1 khối thông tin duy nhất ở ngoài cùng
                return ($this->validLeafFn)($this->arrData);
            }
            // From here là self::PETIOLE và self::BRANCH vì defaultFilterFn không
            //có trả về self:: EXCLUDED
            $this->traverseTree($this->arrData,[]); 
        }
        catch (Throwable $e){
            return false;
        }
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function traverseTree(array &$data, array $chain = [], bool $isFirstTime = true): void {
        if($isFirstTime){
            //reset lại $this->isValidLeafFn để bắt buộc chạy lại $this->validateLeafFn
            //khi tới nút lá đầu tiên
            $this->isValidLeafFn = false;
        }
        if($this->startBranchFn){
            ($this->startBranchFn)($chain);
        }
        $petioleExists  =  false;
        foreach ($data as $key => &$val) {
            self::validateBranchNode($key,$petioleExists);
            if($key === self::$strPetiole){
                $petioleExists =  true;
            }
            $newChain = array_merge($chain, [$key]);
            $type = ($this->filterFn)($newChain, $val);
            if ($type === self::BRANCH || $type === self::PETIOLE) {
                $this->traverseTree($val, $newChain, false);
                $this->iPrevAction = self::BRANCH_PROCESSING;
                $this->iLengthPrev  = count($newChain);
            } elseif ($type === self::LEAF) {
                if($this->leafFn){
                    if(!$this->isValidLeafFn){
                        //khi valid $this->leafFn thành công thì $this->validateLeafFn sẽ
                        //đặt lại $this->isValidLeafFn = true để sau này tắt không chạy lại
                        //hàm this->validateLeafFn lần thứ 2 nữa
                        $this->validateLeafFn();
                    }
                    // Bây giờ $val là tham chiếu -> leafFn có thể thay đổi trực tiếp
                    ($this->leafFn)($newChain,$val);
                }
                $this->iPrevAction = self::LEAF_PROCESSING;
                $this->iLengthPrev  = count($newChain);
            }
        }
        if($this->endBranchFn){
            ($this->endBranchFn)($chain);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __sleep(): array {
        $props = get_object_vars($this);
        $serializeKeys = [];
        foreach ($props as $name => $value) {
            // Bỏ qua Closure
            if ($value instanceof \Closure) {
                continue;
            }
            $serializeKeys[] = $name;
        }
        return $serializeKeys;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    // Gán lại closure mặc định sau khi unserialize. Các class thừa kế từ ExtArray phải viết lại
    //hàm này cho phù hợp
    public function __wakeup(): void {
        
        $this->validLeafFn = function($obj) {
            //throw new InvalidArgumentException('Hàm valid dữ liệu tại nút lá chưa được set');
            throw new InvalidArgumentException("Validation function for leaf nodes must be set before use.");
        };
        $this->filterFn    = Closure::fromCallable([static::class, 'defaultFilterFn']);
        $this->leafFn      = null;
        $this->startBranchFn = null;
        $this->endBranchFn   = null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateLeafFn(): void {
        if(!$this->leafFn){
            return;//không có $this->leafFn thì không valid
        }
        
        if (is_array($this->leafFn) && count($this->leafFn) === 2 && is_string($this->leafFn[1])) {
            $ref = new \ReflectionMethod($this->leafFn[0], $this->leafFn[1]);
        } elseif ($this->leafFn instanceof \Closure || is_string($this->leafFn)) {
            $ref = new \ReflectionFunction($this->leafFn);
        } else {
            throw new \InvalidArgumentException("Invalid callable provided");
        }    
        
        $params = $ref->getParameters();

        // phải có ít nhất 2 tham số
        if (count($params) < 2) {
            throw new \InvalidArgumentException(
                "LeafFn phải có ít nhất 2 tham số (array \$path, mixed &\$leafInfo)"
            );
        }
        // kiểm tra tham số 1: phải là array
        $type1 = $params[0]->getType();
        if ($type1 && $type1->getName() !== 'array') {
            throw new \InvalidArgumentException(
                "Tham số đầu tiên phải là array \$path."
            );
        }
        // kiểm tra tham số thứ 2 có phải passed by reference không
        if (!$params[1]->isPassedByReference()) {
            throw new \InvalidArgumentException(
                "Tham số thứ 2 (\$leafInfo) phải được truyền by-reference (&)."
            );
        }
        
        $this->isValidLeafFn = true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateBranchNode($key, ?bool $petioleExists  = null): void {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Path key must be of type string');
        }
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
    protected static function setValueMVModeAtEndPath($strPosType, &$ref, $value): void {
        if($strPosType === self::LEAF){
            if(is_array($ref) && is_array($value)){
                $ref =  array_merge($ref, $value);
            }
            else{
                $ref = $value;
            }
        }
        else if($strPosType === self::PETIOLE){
            if(is_array($ref[self::PETIOLE]) && is_array($value)){
                $ref[self::PETIOLE] =  array_merge($ref[self::PETIOLE], $value);
            }
            else{
                $ref[self::PETIOLE] = $value;
            }
        }
        else if($strPosType === self::PETIOLE){
            $ref[self::PETIOLE] = $value;
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function setValueAtEndPath($strPosType, &$ref, $value, $mode, $isNewBranch): void {
        if ($mode === self::MODE_REPLACE || $isNewBranch) {
            $ref = $value;
            return;
        }
        switch ($mode) {
            case self::MODE_MERGE_VALUE:
                self::setValueMVModeAtEndPath($strPosType,$ref, $value);
                break;
            case self::MODE_KEEP_OLD_VALUE:
                // do nothing
                break;
            default:
                throw new InvalidArgumentException("Unknown mode: $mode");
        }
    }
} 
       
