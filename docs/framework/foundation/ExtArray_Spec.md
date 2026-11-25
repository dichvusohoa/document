# ExtArray — Đặc tả kỹ thuật tổng thể

Ngày đối chiếu: **2026-10-23**. Class chuẩn: `Core\Foundation\ExtArray`.

## 1. Phạm vi, trạng thái và nguồn

Tài liệu này mô tả toàn subsystem để tiếp tục bảo trì mà không cần lịch sử chat: kiến trúc, API, callbacks, thuật toán, giới hạn và consumers. Nó mở rộng Spec cắt lá ngày 2026-10-18; [ExtArray_DKT.md](ExtArray_DKT.md) giữ lịch sử và lý do thiết kế.

Quy ước:

- **CONFIRMED**: quyết định đã chốt trong DKT/thảo luận tiếp nối; không mặc nhiên đã triển khai.
- **IMPLEMENTED**: behavior từ source hiện tại; không tự động là quyết định được phê chuẩn lâu dài.
- **VERIFIED**: kiểm chứng cụ thể tại mục 15, không suy rộng coverage.
- **OPEN**: cần quyết định hoặc kiểm chứng thêm; không tự sửa behavior.
- **SUPERSEDED**: thiết kế cũ đã thay thế.

Các mục API/thuật toán hiện hành mặc định IMPLEMENTED. Đợt này chỉ sửa Spec, không sửa source, DKT hoặc tests.

| Nguồn | Vai trò |
|---|---|
| [ExtArray.php](../../../core/Foundation/ExtArray.php) | Implementation chuẩn, đọc toàn bộ |
| [ExtArray_DKT.md](ExtArray_DKT.md) | Ý định và lịch sử |
| [JsonNormalizer.php](../../../core/Normalization/JsonNormalizer.php) | Consumer traversal và setValue |
| [TableDAUNormalizer.php](../../../core/Normalization/TableDAUNormalizer.php) | Consumer gián tiếp |
| [JsonNormalizer_DKT.md](../normalization/JsonNormalizer_DKT.md) | Lịch sử consumer, chưa phản ánh hết code mới |
| [JsonNormalizerTest.php](../../../tests/JsonNormalizerTest.php) | Regression tích hợp |
| [TableDAUNormalizerTest.php](../../../tests/TableDAUNormalizerTest.php) | Regression bảng |

HEAD: `9ea1ee6ac93369a7bd5e75ad9ff979f00128b6d5`. Working tree có thay đổi chưa commit; **HEAD không định danh đầy đủ snapshot đã đọc**. SHA-256:

```text
core/Foundation/ExtArray.php
C8CF687EF9A9E5D00F50E01DBF48221A9AD7955B97C7ED0175101ACAD6EA2F38
core/Normalization/JsonNormalizer.php
217C6B34F6D835FC5343635B98AC2D0A1F70330EC6EF430ACED2E1012FA656B5
core/Normalization/TableDAUNormalizer.php
DADD6FFEBDE37F67E7717D57466223940CD7D0C318ACB8253432DB65164BF2D7
docs/framework/foundation/ExtArray_DKT.md
60F797F5731A1157D04A552367AA73EE0D63DB8F6535F8157AFABEE651CBA64F
```

Bản `_1`, `_2` không dùng làm implementation chuẩn. Composer ánh xạ `Core\` tới `core/`; không require đồng thời các bản khai báo cùng class.

## 2. Mục đích và trách nhiệm

**CONFIRMED; IMPLEMENTED:** mỗi path có thể vừa mang logical value riêng vừa có child paths.

- UDA quyết định semantic leaf/container/exclude/discard.
- ExtArray quản lý representation, path, petiole, promotion, traversal, collapse.
- Array definition được chuyển nguyên tới processor; engine không hiểu AtomicSchema/business errors/row bảng.
- validLeafFn chỉ kiểm tra value đầu vào setValue; không validate toàn cây hoặc processor output.
- Các traversal độc lập; engine không biết normalization steps hay nhớ unit đã xử lý.

Không buộc UDA phân biệt BRANCH/PETIOLE; không khôi phục classifyNode để đóng gói lại semantic definition.

## 3. Mô hình dữ liệu và invariant

### 3.1. Nodes

| Loại | Ý nghĩa tại path | Ví dụ |
|---|---|---|
| BRANCH | Children, không own value | `'a'=>['b'=>20]` |
| LEAF | Một logical value, không descend | `'a'=>10` |
| PETIOLE | Own value dưới slot và children | `'a'=>['__petiole__'=>10,'b'=>20]` |

Một path có tối đa một own value. Cách nói “branch b mang leaf 20” không tạo object branch riêng trong source: callback nhận path `['a','b']`, value `20`.

Leaf có thể scalar hoặc array/object nếu caller policy cho phép. Physical array không tự động là BRANCH. `[]` có thể là branch rỗng hoặc logical leaf tùy definition.

### 3.2. Storage/root/reference

`protected array $arrData` là storage. Bên ngoài đọc toàn bộ cây vật lý qua `getData(): array`, trả theo value và giữ nguyên các slot PETIOLE. Đây là phép trả array thông thường của PHP, không phải deep copy để tách reference/object bên trong. Constructor vẫn có thể bind storage với biến array bên ngoài bằng reference. Root luôn array, nhưng có thể được define là logical leaf; không nhận scalar root trực tiếp. Root path `[]`, không có trạng thái root không tồn tại.

Constructor có thể bind array ngoài. `$isExternalRef` ghi nhận cách khởi tạo nhưng không điều phối method khác. Public property và các references cho phép bypass validation; engine không encapsulate tuyệt đối mutation.

### 3.3. Paths

Public path API nhận string/int/array. Scalar thành một segment: `'a/b'` không tự split. Array path kỳ vọng là list zero-based string/int; `[]` là root.

Source đọc `$path[$i]`, chưa validate đầy đủ list/segments. Associative/sparse path không được bảo đảm. Keys theo PHP array, numeric string có thể thành int.

Petiole segment phải terminal; segment sau slot bị guard nếu code đi tới đó. Missing-path early return có thể bỏ qua kiểm tra suffix; getter không phải validator cho toàn path chưa tồn tại.

### 3.4. Petiole name và validation

`PETIOLE_DEFAULT_NAME='__petiole__'`. `setPetioleName` thay protected static `self::$strPetiole` dùng chung instances, không per-instance. Không migrate data hoặc validate empty/collision/numeric-string. Khuyến nghị cấu hình tên ổn định trước sử dụng; đây không phải guard đã code.

- Container definition yêu cầu array.
- Node tại petiole slot không được define BRANCH_OR_PETIOLE.
- Presence dùng array_key_exists; null vẫn tồn tại.
- Traversal cho solitary petiole rồi collapse; setValue/lookup vẫn strict tại chỗ kiểm tra.
- Constructor không quét invariant toàn tree; EXCLUDED không chứng nhận data hợp lệ.

## 4. Constants

| Constant | Giá trị | Miền |
|---|---:|---|
| EXCLUDED | 0 | Policy skip/collapse tại node |
| BRANCH | 1 | Structural type |
| LEAF | 2 | Structural type |
| PETIOLE | 3 | Structural type |
| BRANCH_OR_PETIOLE | 13 | Semantic container |
| COMPOSITE_LEAF | 20 | Process nguyên node |
| DISCARDED_LEAF | 21 | Traversal xóa, setValue reject |
| BRANCH_PROCESSING | 1 | Previous action |
| LEAF_PROCESSING | 2 | Previous action |

Definition không trả BRANCH/LEAF/PETIOLE. Action và structural type khác miền dù trùng số.

Modes: `MODE_REPLACE='replace'`, `MODE_KEEP_OLD_VALUE='keep_old_value'`, `MODE_MERGE_VALUE='merge_value'`.

## 5. Toàn bộ public API và lifecycle

```php
__construct(&$arrData = null)
setDefineLeafFn(Closure $fn): void
setValidLeafFn(Closure $fn): void
setProcessLeafFn(Closure $fn): void
setStartBranchFn(Closure $fn): void
setEndBranchFn(Closure $fn): void
static setPetioleName(string $strPetioleName): void
setValue(string|int|array $arrPath, mixed $value,
         string $sOption = self::MODE_REPLACE): void
getData(): array
getValue(string|int|array $arrElement): mixed
getExistInfoAndValue(string|int|array $arrElement,
                    bool $isGetByReference = false): array
&getReferenceAt(string|int|array $arrElement)
existElement(string|int|array $arrElement): bool
isEmpty(): bool
traverseTree(): void
```

Constructor không truyền/null tạo internal []; biến null ngoài không được bind. Biến array được bind by-reference; input khác ném InvalidArgumentException. Caller truyền biến cho tham số reference. Typed storage cũng ràng buộc reference ngoài, không tùy ý gán scalar vào biến đang bind.

Default define throw LogicException khi gọi, default validLeaf throw InvalidArgumentException. Processor/start/end null. Getter/isEmpty không cần callback.

Không có state machine khóa sau lỗi, cache hoặc custom clone guard. Traversal tiếp theo chạy trên data hiện tại.

Từ 2026-11-20, năm callback setter giữ nguyên Closure, object binding và class scope của caller; validate khi có validator. Static Closure được chấp nhận nếu đúng signature. Caller cần context ExtArray phải tự `bindTo($extArray, ExtArray::class)` (hoặc scope class con phù hợp), kiểm tra kết quả rồi đăng ký. Không có public unset callback bằng null.

## 6. Callback contracts

### 6.1. Definition và value validation

```php
function (mixed $value) { /* ... */ }
function (array $path, mixed $value) { /* ... */ }
```

Value untyped/mixed; path non-nullable array. Đúng 1/2 parameters, không by-reference/variadic. Optional parameters được chấp nhận, engine vẫn truyền đủ theo mode.

| Callback | Return declaration | Runtime |
|---|---|---|
| fnDefineLeaf | Omitted/int/array/array\|int, không nullable/by-reference | Array bất kỳ hoặc token 0/13/20/21 |
| validLeafFn | Omitted/bool non-nullable, không by-reference | Bool; false reject setValue |

Return declaration mixed không hợp lệ. Array rỗng return vẫn là leaf definition. Value là input, definition là output.

validLeafFn gọi một lần trước khi đi path setValue, kể cả KEEP hoặc sau đó gặp EXCLUDED. Không gọi lại cho promoted old leaf, merged output, traversal hoặc processor output.

### 6.2. Processor

```php
function (array $path, mixed &$value, array|int $leafDefinition) { /* ... */ }
```

Chỉ parameter 2 by-reference, untyped/mixed. Parameter 1 nhận array (untyped/mixed/iterable/union phù hợp được chấp nhận); parameter 3 nhận cả array và int. Ba parameter đầu không variadic. Có thể thêm optional parameters; tổng required không quá 3. Return không được kiểm tra hoặc dùng làm signal.

Chỉ gọi cho array definition/COMPOSITE_LEAF, forward nguyên definition không gọi define lại. Processor null vẫn hoàn tất leaf visit và cập nhật state.

### 6.3. Branch callbacks

Start/end nhận một path array, return bỏ qua. Setter lưu nguyên Closure, không validate signature; sai chữ ký lỗi lúc gọi. Root/empty container cũng có callbacks; leaf/excluded/discarded không có. End sau children, trước collapse.

Callbacks là code tin cậy. Quyền truy cập protected state phụ thuộc scope có sẵn hoặc binding tường minh của caller; setter không cấp thêm quyền truy cập. Không sandbox captures/global/arrData. Reentry hoặc tự sửa sibling/key giữa foreach chưa có contract; không suy ra “mỗi node một lần” với mutation ngoài luồng đã thiết kế.

## 7. Lookup và reference

### 7.1. getExistInfoAndValue

Đi physical path bằng array_key_exists, **không gọi fnDefineLeaf**. Tại đích:

| Trạng thái | Result |
|---|---|
| Thiếu segment/không descend được | `['exists'=>false,'value'=>null]` |
| Petiole key + child khác | exists true, petiole value |
| Chỉ petiole key | LogicException; không tự collapse |
| Không petiole key | exists true, raw node |

Không trả node type. Đích vừa là petiole slot vừa có petiole key bên trong cũng bị guard. `$isGetByReference=true` trả element value là reference khi tồn tại; result array không return-by-reference.

Hệ quả: BRANCH trả array children; lookup đi sâu được vào composite array; leaf array có petiole-like key có thể được đọc khác traversal. Lookup root [] cũng áp dụng petiole extraction, không luôn trả toàn physical root. Xem O2.

### 7.2. Wrapper APIs

- getValue lấy value; missing và null cùng trả null, cần exists để phân biệt.
- existElement lấy exists; empty branch tồn tại; vẫn có thể throw vì representation invalid.
- getReferenceAt return reference tới logical value; missing ném InvalidArgumentException. Caller dùng `$ref =& $tree->getReferenceAt($path)`.
- isEmpty là strict `arrData === []`; `['a'=>[]]` và `[null]` không rỗng.

Reference giữ trước discard/collapse không được bảo đảm tiếp tục định danh cùng path. Lấy lại theo path mới khi cần; chưa có API relocation.

## 8. setValue và ma trận mode

### 8.1. Luồng hiện hành

1. Chuẩn hóa path; validLeafFn cho value mới và path đích.
2. Đi segments bằng reference, kiểm tra petiole terminal.
3. Define current node: discard throw, excluded return không ghi.
4. Leaf giữa path promote thành `[petioleKey=>oldValue]`, ghi undo.
5. Container validate strict, không cho solitary petiole.
6. Child thiếu tạo placeholder [], ghi undo, đánh dấu path mới.
7. Target define lần nữa; reject discard/skip excluded/validate container.
8. Áp dụng mode, đánh dấu committed.
9. Nếu chưa committed, finally đảo ngược inserts/promotions đã ghi.

Definition cần xử lý placeholder [] và representation sau promotion. Không skip definition target mới.

### 8.2. Target × mode

Giả định value pass validation và không gặp skip/reject. LEAF gồm array definition và COMPOSITE_LEAF.

| Target | REPLACE | KEEP_OLD_VALUE | MERGE_VALUE |
|---|---|---|---|
| Path mới tạo | Gán trực tiếp | Gán trực tiếp | Gán trực tiếp |
| LEAF | Thay value | Giữ value | Cả hai array: array_merge; còn lại thay |
| BRANCH hiện có | Thay toàn node, bỏ subtree | Giữ branch, không thêm own value | Thêm petiole value, giữ children |
| PETIOLE hiện có | Thay toàn node, bỏ children | Giữ nguyên | Merge/thay own value, giữ children |

REPLACE không chỉ thay logical value. KEEP không tự thêm own value còn thiếu. MERGE là array_merge không đệ quy: string key mới ghi đè, numeric keys trong merged value bị đánh lại theo PHP. Không reindex của discard không áp dụng cho merge values.

### 8.3. Promotion và root

**CONFIRMED; IMPLEMENTED:** `['a'=>10]` sau setValue(['a','b'],20) thành `['a'=>['__petiole__'=>10,'b'=>20]]`. Không validLeafFn lại cho old value. Mode target không ngăn promotion giữa path.

setValue([],value) dùng mode tại root. Typed root vẫn array; không bọc scalar như collapse. Processor gán scalar root cũng gặp TypeError.

Undo chỉ bao phủ inserts/promotions đã ghi, không transaction toàn cây hoặc rollback side effects callback.

Các góc cạnh chưa chốt sửa:

- MERGE existing empty BRANCH tạo solitary petiole, getter reject cho tới khi traversal sửa.
- Mode lạ chỉ reject ở switch target cũ; new path short-circuit assignment nên vẫn được ghi.
- Không postvalidate final assignment/merge; value mới hợp lệ không bảo đảm merged output/topology cuối hợp lệ.

## 9. Traversal, discard và collapse

Contract mục này **CONFIRMED; IMPLEMENTED**.

### 9.1. Dispatch

| Definition | Hành động |
|---|---|
| Array / COMPOSITE_LEAF | Process nếu có, không descend |
| BRANCH_OR_PETIOLE | Validate, children, end, collapse |
| DISCARDED_LEAF | Không process/descend, parent xóa entry |
| EXCLUDED | Không process/descend, collapse một lớp tại node nếu đơn độc |

Depth-first theo insertion order PHP array. Petiole slot được thăm như entry thường, path chứa key petiole; không tự ưu tiên trước children. Nội dung leaf/composite không được tự suy diễn topology từ key.

### 9.2. Đơn vị cắt

```php
['nodeX'=>['nodeY'=>$leaf]]
// define tại ['nodeX','nodeY'] = DISCARDED_LEAF
['nodeX'=>[]]
```

Xóa cả entry nodeY, không để nodeY=>[]; giữ parent rỗng, không recursive prune. Root discard thành []. Define array unit là discarded leaf thì xóa nguyên unit. Muốn cắt từng child, parent phải là container được duyệt.

Numeric keys giữ nguyên: `[0=>'x',1=>'y',2=>'z']` cắt key 1 thành `[0=>'x',2=>'z']`.

### 9.3. Collapse container

| Sau children và end callback | Kết quả |
|---|---|
| Petiole + child khác | Giữ PETIOLE |
| Chỉ petiole slot | Value slot thay container |
| Children không slot | Giữ BRANCH |
| Không entry | Giữ [] |

Áp dụng cả solitary petiole có từ đầu. Không cần traversal thứ hai/repair riêng. Cắt slot chỉ bỏ own value; empty child vẫn ngăn collapse parent.

```php
['a'=>['__petiole__'=>30,'b'=>10,'c'=>20]]
// a container; b/c discarded; own value được process thành 31
['a'=>31]
```

Processor chạy tại slot, không chạy lại tại a sau collapse. Slot đứng cuối vẫn được thăm trước kết thúc container. Không collapse parent ngay sau từng unset vì còn slot có thể chưa xét.

### 9.4. Root collapse

Value slot là array thì dùng trực tiếp, không array thì bọc [value]:

```text
['__petiole__'=>10]       → [10]
['__petiole__'=>null]     → [null]
['__petiole__'=>['x'=>1]] → ['x'=>1]
```

Không callback cho key 0 mới trong cùng lượt. Quy tắc không mở rộng sang setValue/processor root.

### 9.5. EXCLUDED

Không lưu policy vào data. Không callback hậu duệ hoặc branch callbacks của node excluded; discard rules bên trong không được xét. Không quét subtree để repair.

Nếu **chính value** chỉ chứa một petiole key, bỏ đúng một lớp wrapper, giữ nguyên payload. Không tiếp tục descend/collapse payload. Nếu còn entry khác, giữ nguyên toàn node. Root theo mục 9.4.

```php
['a'=>['__petiole__'=>['__petiole__'=>10]]]
// a EXCLUDED →
['a'=>['__petiole__'=>10]]
```

Đây là ngoại lệ có chủ ý dù EXCLUDED không phân biệt leaf/container. Áp dụng cả tại một petiole slot bị exclude. Mỗi visit bỏ tối đa một lớp; ancestor container được duyệt có thể collapse riêng khi unwinding.

Nếu parent container được duyệt, own value excluded và sibling discarded, parent vẫn được collapse mà không process own value lần nữa. Nếu parent excluded thì children hoàn toàn không được thăm. Chi phí skip không theo số descendants.

## 10. Thuật toán nội bộ và state

### 10.1. Luồng khớp source

```text
traverseTree:
    reset previous state về null; define root []
    discard → arrData=[]; return
    excluded → collapse(root,true); return
    leaf → process nếu có; state=LEAF_PROCESSING, depth=0; return
    container → validate(allowSolitary=true)
                traverseBranch(root,[]); collapse(root,true)

traverseNode(&value,path) -> bool:
    define node
    discard → true
    excluded → collapse một lớp; false
    leaf → process nếu có; cập nhật leaf state; false
    container → validate(allowSolitary=true)
                traverseBranch(value,path); collapse một lớp
                cập nhật branch state; false

traverseBranch(array &container,path):
    start callback nếu có
    foreach entry by-reference:
        nếu traverseNode(child,childPath) trả true:
            unset entry tại parent
    unset reference biến lặp
    end callback nếu có
```

Bool là signal xóa nội bộ, không definition/action state. Parent có key nên phải unset entry; unset reference ở hàm con không đủ. Collapse ở caller sau traverseBranch, tránh đổi sang scalar khi còn trong tham số array.

### 10.2. Previous-action state

| Sự kiện | Cập nhật sau sự kiện |
|---|---|
| Bắt đầu traverseTree | iPrevAction/iLengthPrev null |
| Leaf visit, kể cả processor null | LEAF_PROCESSING, count(path) |
| Hoàn tất non-root container/collapse | BRANCH_PROCESSING, count(path) |
| Discard/EXCLUDED | Không cập nhật |
| Hoàn tất root container | Không gán branch state riêng cho root |

Processor thấy previous state vì state mới ghi sau callback. End thấy children state trước khi caller cập nhật branch state. Container collapse vẫn là branch visit, không giả lập leaf-processing event.

### 10.3. Helpers

- detectBranchOrPetiole: array + presence slot, không semantic classification.
- validateBranchOrPetioleNode(path,value,allowSolitaryPetiole=false): array, vị trí slot, solitary policy.
- hasSubBranches: có key khác petiole, không đi sâu/xét definition.
- isLeafDefinition: array hoặc COMPOSITE_LEAF, không discard.
- callDefineLeafFn/callValidLeafFn: dispatch theo mode Reflection nhận dạng ở setter.
- detectLeafCallbackArgMode và signature/return validators: protected nhưng consumer dùng subclass bridge.
- setValueAtEndPath/setValueMVModeAtEndPath: quyết định mode/representation đích.
- collapseSolitaryPetiole: một lớp, count/presence, root wrap; không callback.

## 11. Exceptions, hiệu năng và giới hạn

| Tình huống | Kết quả chính |
|---|---|
| Constructor sai | InvalidArgumentException |
| Chưa có definition khi cần gọi | LogicException |
| Chưa có validLeafFn khi setValue | InvalidArgumentException |
| Signature/return declaration sai | InvalidArgumentException; setter không thực hiện bind |
| Runtime definition sai | InvalidArgumentException hoặc lỗi kiểu PHP tại callback boundary |
| validLeafFn không bool / false | LogicException |
| Container non-array, slot là branch, path sau slot, solitary strict | LogicException |
| setValue gặp discard | LogicException |
| getReferenceAt missing | InvalidArgumentException |
| Mode lạ tại target cũ | InvalidArgumentException; ngoại lệ new path tại mục 8 |
| Gán scalar root | TypeError |
| Callback throw | Truyền ra, không chuyển thành data error |

**CONFIRMED:** callback thuộc trách nhiệm UDA. Traversal không rollback hoặc cố hoàn tất end/collapse sau exception. End throw có thể để lại solitary petiole. Failed traversal không phải trạng thái thành công hoàn chỉnh. Quyết định này không bỏ undo setValue.

Engine không depth limit/cycle detection/reference detachment/reentrancy lock; các bảo vệ JsonNormalizer không thuộc ExtArray. Không postvalidate sau callback.

Collapse check O(1), không thêm quét subtree. Chi phí tạo path bằng array_merge theo độ sâu; không tuyên bố toàn bộ runtime O(N) độc lập depth. Stack theo chiều cao; callback, copy-on-write, references, merge có chi phí riêng. Chưa benchmark cây ba triệu node.

## 12. Ví dụ sử dụng

```php
use Core\Foundation\ExtArray;

$data = [];
$tree = new ExtArray($data);
$tree->setDefineLeafFn(function (mixed $value): array|int {
    return is_array($value) ? ExtArray::BRANCH_OR_PETIOLE : [];
});
$tree->setValidLeafFn(function (mixed $value): bool {
    return is_int($value) || $value === null;
});
$tree->setValue('a', 10);
$tree->setValue(['a', 'b'], 20);
// $data = ['a'=>['__petiole__'=>10, 'b'=>20]]
// getValue('a') === 10

$tree->setDefineLeafFn(function (array $path, mixed $value): array|int {
    if ($path === ['a', 'b']) {
        return ExtArray::DISCARDED_LEAF;
    }
    return is_array($value) ? ExtArray::BRANCH_OR_PETIOLE : [];
});
$tree->setProcessLeafFn(function (array $path, mixed &$value, array|int $definition): void {
    if (is_int($value)) {
        ++$value;
    }
});
$tree->traverseTree();
// $data = ['a'=>11]; b không process; own value xử lý một lần.
```

Closure trực tiếp có thể static hoặc giữ binding của caller. Array leaf cần definition riêng, không dùng policy mọi array là container như ví dụ đơn giản này.

## 13. Consumer map và compatibility

### 13.1. JsonNormalizer hiện tại

Namespace `Core\Normalization`, khác `Core\Data\Normalization` trong DKT cũ.

```text
UDA → JsonNormalizer → extData: ExtArray (traversal)
                     → extError: ExtArray (setValue)
TableDAUNormalizer → JsonNormalizer
```

- Hai instances bind internal arrays data/error.
- Adapters đã dùng array|int; lỗi string declaration của baseline trước không còn tại file chuẩn.
- JsonNormalizer đăng ký trực tiếp các callback define/process/end, giữ scope JsonNormalizer; đã bỏ các lớp bọc chỉ dùng để tránh rebind. Callback cây lỗi gọi trực tiếp helper static trong scope JsonNormalizer. Dispatcher đổi prototype 1/2 tham số của UDA vẫn được giữ; static/controller UDA closures tiếp tục giữ binding gốc.
- End callback align error paths trước collapse. EXCLUDED không có end callback nên select adapter align lỗi trước trả token.
- Error tree dùng replace/merge/promotion để giữ lỗi cha cùng lỗi con; là consumer thực sự của ma trận setValue.
- Anonymous subclass bridges dùng protected strPetiole, callback mode constants, detectLeafCallbackArgMode, validateDefineLeafFnReturnType. Đổi protected API có thể phá consumer.

UDA processor hiện trả list records `{path: relative array, err_number: int, value: mixed}`, đúng một record path=[] cho toàn unit; records con là lỗi. Không còn tuple hai field cũ. Composite commit value của root record kể cả có lỗi; atomic chỉ commit thành công. Error leaf `[code,reportedValue]`; petiole lưu own error cạnh child errors.

JsonNormalizer chỉ cho array/JSON scalar, reject object/resource/input-output reference, giới hạn depth 128, có lifecycle/cache/failed-instance lock. Không gán các bảo vệ consumer này cho ExtArray.

### 13.2. Discard và errors

**IMPLEMENTED; VERIFIED:** lỗi tại node/ancestor làm select trả EXCLUDED trước UDA definition. Descendant error làm DISCARDED_LEAF đổi thành EXCLUDED. Chỉ unit không lỗi liên quan được discard; siblings độc lập vẫn được xử lý. Error paths align khi collapse.

**CONFIRMED trong thảo luận ExtArray trước đây:** nếu step sau discard composite chứa lỗi thì đồng thời bỏ error subtree tương ứng. Source/test consumer hiện bảo vệ lỗi thay vì xóa unit chứa lỗi. Chưa có bằng chứng trong các nguồn đối chiếu rằng yêu cầu cũ đã bị hủy chính thức. Ghi OPEN; không tự sửa consumer hoặc coi implementation là quyết định thay thế.

### 13.3. TableDAUNormalizer

Đã có `Core\Normalization\TableDAUNormalizer`; nhận định lịch sử chưa có implementation không còn đúng.

- Một step; root/action là container, row depth 2 là COMPOSITE_LEAF.
- Action delete/add/update; fields xử lý trong processor row, không traversal từng field.
- Input row indices phải liên tiếp từ 0; đây là validator consumer, không thay đổi numeric holes của ExtArray.
- Unset auto-value field trong candidate là mutation composite, không phải engine discard.
- Phụ thuộc path, binding, partial-result/error contract của JsonNormalizer và giữ indices.

Đã tìm tham chiếu PHP trong repository. JsonNormalizer là consumer chuẩn trực tiếp; TableDAUNormalizer dùng constants và engine gián tiếp. Không khẳng định tìm kiếm tĩnh bao phủ dynamic calls/code ngoài repo. Bản `_1`/`_2` không dùng để chốt contract hiện tại.

## 14. Khoảng cách và vấn đề mở

| ID | Hiện trạng/lịch sử | Trạng thái bảo trì |
|---|---|---|
| O1 | DKT có scalar-root example; storage/constructor array, quyết định mới wrap root collapse | Không hỗ trợ scalar root trực tiếp; không tự đổi storage thành mixed |
| O2 | Lookup physical không definition; composite/petiole-like leaf có thể đọc khác traversal | OPEN: thống nhất semantic lookup hay giữ? Chưa đổi behavior |
| O3 | Merge existing empty branch tạo solitary petiole, getter reject | VERIFIED; OPEN cách xử lý lâu dài |
| O4 | Mode lạ được chấp nhận tại new path | VERIFIED; OPEN validation mode nhất quán |
| O5 | Path list/segments chưa validate đầy đủ; petiole name global không migrate | IMPLEMENTED limitation, chưa chốt hardening |
| O6 | Callback/reference/merge có thể phá invariant sau validation | Chưa có postvalidation blanket guarantee/mutation restrictions |
| O7 | Yêu cầu cũ discard chứa lỗi + xóa errors khác source/test hiện tại | OPEN ở JsonNormalizer; chốt policy trước sửa tests/code |
| O8 | Protected helpers có external subclass consumers | Review compatibility khi đổi signature/visibility/name |
| O9 | Chưa thấy persistent ExtArray-only suite | OPEN coverage bền vững; probe chat không phải test đã lưu |

SUPERSEDED: boolean/string markers; UDA trả BRANCH/PETIOLE; classifyNode; discard chỉ reserve; collapse chỉ sau discard; repair/traversal thứ hai bắt buộc; EXCLUDED cấm mọi representation change; processor phát tín hiệu discard; process lại leaf sau collapse; cố rollback/collapse khi callback throw.

Namespace/prototype/commit policy cũ của JsonNormalizer trong DKT không mô tả source hiện tại. Không coi mọi item OPEN cũ vẫn mở nếu source/decisions mới đã giải quyết.

## 15. Kiểm chứng và nghiệm thu

### 15.1. Kết quả ngày 2026-10-23

PHP CLI 8.0.13 trên Windows. Source dùng mixed/union types PHP 8; chưa kiểm chứng mọi phiên bản khác.

```text
php tests/JsonNormalizerTest.php       → 20 scenarios passed
php tests/TableDAUNormalizerTest.php   → 10 scenarios passed
```

Chạy từ repo root, cần vendor/autoload.php. Integration tests không chứng nhận toàn bộ API ExtArray.

Probe PHP stdin, không lưu test file, xác minh: merge empty branch tạo solitary/getter reject; mode lạ new path vẫn ghi; lookup sâu composite; replace PETIOLE bỏ children; constructor null giữ biến ngoài null/internal [].

Lần triển khai trước báo 30 kiểm tra cắt lá và probes hồi quy nhưng scripts không phải persistent suite hiện có. Không dùng số này tuyên bố coverage hiện tại đầy đủ.

### 15.2. Ma trận cần duy trì

| Nhóm | Tình huống |
|---|---|
| Storage | null/internal, external array reference, invalid constructor, root array |
| Lookup | missing/null/empty/root, own value, explicit slot, invalid path, reference |
| Set | missing/leaf/branch/petiole × replace/keep/merge; scalar/array/null; shallow numeric merge |
| Promotion/undo | mid-path leaf, placeholder, excluded/exception sau insertion/promotion |
| Signature | definition/valid 1–2 args; return types; binding; processor reference/type |
| Traversal | order, root/empty callbacks, processor null, state timing |
| Discard | ordinary/composite/root/petiole, numeric holes, empty parent, no processing |
| Collapse | initial solitary, last child, slot trước/sau child, null/custom key, root array/scalar |
| Excluded | no descendant callbacks, one-layer only, multi-entry unchanged, parent collapse |
| Failure | callback throw sau mutation, end throw trước collapse, no rollback |
| Consumer | error alignment/protection, error-tree set modes, DAU indices/composite row |

Tests cần assert data, callback count/order/path và errors liên quan. Chốt O7 trước đổi expectation bảo vệ lỗi. Không thêm test/source trong đợt tài liệu này.

## 16. Quy trình tiếp tục bảo trì

1. So hash/mốc source; source đổi thì đối chiếu trước khi dựa vào Spec.
2. Xác định API/contract/consumer ảnh hưởng; protected helper không luôn chỉ là nội bộ.
3. Đọc OPEN liên quan, phân biệt đề xuất với quyết định, không tự chốt thay người dùng.
4. Khi triển khai thay đổi contract, cập nhật Spec và regression cùng đợt; DKT giữ lịch sử lý do.
5. Chạy consumer tests khi đổi definition/references/petiole/set modes/callback timing/root.
6. Cập nhật ngày/hash và mức kiểm chứng thực tế; code từng sinh trong chat không chứng minh đã merge/triển khai.

## 17. Xuất array phẳng: toFlatArray (bổ sung 2026-11-17)

```php
public function toFlatArray(?Closure $fnMap = null): array;
// Callback tùy chọn:
// fn(array $arrPath, mixed $mixValue, array|int $mixDefinition): mixed
```

- Dùng fnDefineLeaf hiện hành để phân loại từng node; chỉ xuất atomic/composite leaf.
- Key của kết quả là `serialize($arrPath)`. Path truyền vào fnMap là đường dẫn logic,
  bỏ segment petiole cuối; root leaf có path `[]`. fnDefineLeaf vẫn nhận path vật lý.
- Value mặc định là leaf nguyên khối; có fnMap thì dùng giá trị callback trả về.
  Callback giữ binding của caller, được gọi với ba đối số trên; exception truyền ra.
- Giữ thứ tự duyệt depth-first theo thứ tự key nguồn. Empty branch không tạo entry.
  EXCLUDED và DISCARDED_LEAF đều bỏ qua toàn bộ subtree trong kết quả.
- Không chạy fnNormalizeLeaf, fnProcessLeaf, fnStartBranch/fnEndBranch; không thay
  callback, trạng thái traversal hoặc collapse/xóa node nguồn. Kiểm tra invariant
  branch/petiole như traversal hiện hành, cho phép solitary petiole khi đọc.
- Tách reference array một lần bằng ArrayUtility::detachReferences trước khi đọc;
  callback/output không ghi qua reference array vào nguồn. Object không được clone;
  cây array phải hữu hạn, không có reference vòng. Callback tự gây side effect qua
  binding/capture bên ngoài vẫn thuộc trách nhiệm caller.

Kiểm chứng: `php tests/ExtArrayFlatArrayTest.php`,
`php tests/ExtArrayNormalizationTest.php`, `php tests/UploadFilesNormalizerTest.php`.
