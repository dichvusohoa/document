# ExtArray_DKT.md

# ExtArray — Design Knowledge Transfer

> **Subsystem:** `ExtArray` — PBT Framework  
> **Mục đích tài liệu:** Chuyển giao tri thức thiết kế sang một phiên làm việc mới có quyền truy cập source code hiện tại.  
> **Nguyên tắc:** Tài liệu này mô tả **thiết kế đã hình thành trong trao đổi**, không được xem là bằng chứng rằng source code hiện tại đã triển khai đầy đủ các quyết định đó. Codex phải đối chiếu source trước khi sửa.

---

## Quy ước trạng thái

Các nội dung quan trọng trong tài liệu được gắn một trong bốn trạng thái:

- **CONFIRMED** — Đã được người dùng chốt rõ hoặc là contract hiện hành được xác nhận trong trao đổi gần nhất.
- **PROPOSED** — Đã được đề xuất và có cơ sở thiết kế, nhưng chưa có quyết định cuối cùng rõ ràng.
- **OPEN** — Vấn đề còn đang thảo luận hoặc cần source code hiện tại để quyết định.
- **SUPERSEDED** — Thiết kế cũ đã bị thay thế; chỉ giữ lại để tránh quay lại một hướng đã bỏ.

Trong trường hợp có xung đột giữa các mốc trao đổi, **quyết định mới hơn được ưu tiên**.

---

# A. Overview

## A.1. Mục đích subsystem

**CONFIRMED**

`ExtArray` là một abstraction/engine thao tác trên cây dữ liệu PHP array lồng nhau, trong đó dữ liệu được nhìn theo topology:

- `BRANCH`
- `LEAF`
- `PETIOLE`
- và một số trạng thái/policy bổ sung phục vụ traversal/normalization.

Mục tiêu ban đầu của `ExtArray` là làm việc với các cấu trúc key/value phân cấp mà:

1. Một node có thể chỉ chứa các nhánh con.
2. Một node có thể là leaf.
3. Một node có thể **vừa có giá trị logic tại chính node đó, vừa có các nhánh con**.

Trường hợp (3) được biểu diễn bằng khái niệm **PETIOLE**, với giá trị logic của node được lưu dưới một key đặc biệt, mặc định:

```php
'__petiole__'
```

`ExtArray` đồng thời được dùng như một **tree traversal engine** cho subsystem normalization, đặc biệt là `JsonNormalizer`.

---

## A.2. Chức năng và phạm vi

**CONFIRMED**

Các nhóm chức năng chính đã xuất hiện xuyên suốt thiết kế:

- đọc giá trị theo path;
- ghi giá trị theo path;
- xác định sự tồn tại và trạng thái của node;
- xử lý node `BRANCH / LEAF / PETIOLE`;
- traverse toàn bộ cây;
- gọi callback ở các thời điểm thích hợp trong quá trình traversal;
- cho phép UDA/application định nghĩa leaf semantic thông qua callback;
- hỗ trợ `JsonNormalizer` chạy một hoặc nhiều normalization pass trên cùng cây;
- hỗ trợ biến đổi topology khi cần lưu một leaf value tại node vốn đang có sub-branches.

`ExtArray` **không nên trở thành nơi chứa business validation của từng UDA**. Nó là engine topology/traversal; UDA hoặc subsystem phía trên quyết định semantic của leaf.

---

## A.3. Vị trí trong kiến trúc PBT Framework

**CONFIRMED về vai trò; OPEN về namespace/file hiện tại**

Trong các trao đổi cũ, `ExtArray` từng được nhắc là thuộc `Core\Models`.

Trong thiết kế normalization mới hơn, `ExtArray` được sử dụng như nền traversal cho:

```text
Core\Data\Normalization\JsonNormalizer
```

Do không có source code hiện tại trong phiên tổng hợp này, cần Codex xác minh:

- namespace hiện tại của `ExtArray`;
- đường dẫn file thực tế;
- mức độ phụ thuộc trực tiếp giữa `JsonNormalizer` và `ExtArray`;
- các public API nào hiện vẫn tồn tại.

Không được tự động đổi namespace chỉ dựa vào tài liệu này.

---

# B. Architecture

## B.1. Thành phần trung tâm: `ExtArray`

**CONFIRMED về trách nhiệm tổng quát**

`ExtArray` quản lý một PHP nested array như một cây.

Các khái niệm topology:

```text
BRANCH
    node chỉ có các child branch/node, không có logical value tại chính node.

LEAF
    node là logical value, không tiếp tục traverse xuống dưới.

PETIOLE
    node vừa có logical value của chính nó vừa có child nodes.
    logical value được giữ dưới key petiole, mặc định "__petiole__".

EXCLUDED
    traversal policy: node/subtree không được xử lý trong pass hiện tại.
```

Thiết kế mới nhất bổ sung các semantic result phục vụ `defineLeafFn`:

```text
COMPOSITE_LEAF
BRANCH_OR_PETIOLE
DISCARDED_LEAF
```

Chi tiết được trình bày ở phần C.

---

## B.2. `JsonNormalizer`

**CONFIRMED**

`JsonNormalizer` sử dụng `ExtArray` như traversal engine cho dữ liệu JSON/PHP array phức tạp.

Mô hình đã thống nhất ở mức kiến trúc:

```text
raw data
    ↓
step 1
    ↓
step 2
    ↓
...
    ↓
step N
```

Mỗi step gồm một cặp callback:

```text
defineLeafFn
processLeafFn
```

`defineLeafFn` quyết định semantic của node đối với step.

`processLeafFn` xử lý logical leaf/composite leaf được chọn.

`ExtArray` không nên phải hiểu business meaning của `AtomicSchema`, composite structure, table row, v.v. Ngoại trừ những token/definition đã được contract hóa để traversal hoạt động.

---

## B.3. UDA / layer phía trên

**CONFIRMED**

UDA cung cấp policy/definition cho traversal.

Hướng mới nhất cố ý giảm gánh nặng topology cho UDA:

- UDA **không phải tự phân biệt `BRANCH` và `PETIOLE`**.
- UDA có thể trả `BRANCH_OR_PETIOLE`.
- ExtArray tự resolve topology thực tế bằng logic nội bộ.

Đây là một trong các lý do chính dẫn tới quyết định bỏ `classifyNode` khỏi API thiết kế mới.

---

## B.4. Quan hệ thành phần

Thiết kế mong muốn:

```text
UDA / Normalizer step
        │
        │ defineLeafFn
        │ processLeafFn
        ▼
     ExtArray
        │
        │ resolve topology
        │ traverse
        ▼
 underlying PHP nested array
```

Đối với normalization:

```text
JsonNormalizer
   │
   ├── defineLeafFn supplied by step/UDA
   ├── processLeafFn supplied/adapted by step/UDA
   │
   └── ExtArray traversal engine
```

---

# C. Current Design Contracts

# C.1. Constants

## C.1.1. Bộ constant mới nhất

**CONFIRMED**

Thiết kế mới nhất chốt các giá trị:

```php
EXCLUDED          = 0;
BRANCH            = 1;
LEAF              = 2;
PETIOLE           = 3;

BRANCH_OR_PETIOLE = 13;

COMPOSITE_LEAF    = 20;
DISCARDED_LEAF    = 21;
```

Ý nghĩa phân nhóm:

### Structural/topology types

```php
BRANCH  = 1
LEAF    = 2
PETIOLE = 3
```

Đây là topology nội bộ của tree.

### Traversal / leaf-definition semantics

```php
EXCLUDED          = 0
BRANCH_OR_PETIOLE = 13
COMPOSITE_LEAF    = 20
DISCARDED_LEAF    = 21
```

`BRANCH_OR_PETIOLE` đặc biệt quan trọng trong thiết kế mới: UDA nói rằng node cần tiếp tục được xem như cấu trúc chứa child; **ExtArray tự quyết định node thực tế là `BRANCH` hay `PETIOLE`**.

---

## C.1.2. `DISCARDED_LEAF`

**CONFIRMED về constant; OPEN về full semantics**

Người dùng đã chốt bổ sung:

```php
DISCARDED_LEAF = 21;
```

nhưng **chưa triển khai cơ chế “cắt/bứt lá”**.

Do đó:

- constant thuộc design contract;
- chưa được coi là bằng chứng rằng ExtArray hiện có khả năng remove leaf trong traversal;
- hành vi runtime khi `defineLeafFn` trả `DISCARDED_LEAF` cần kiểm tra source hiện tại.

**PROPOSED trong trao đổi trước:** nếu chưa hỗ trợ “cắt lá”, trả `DISCARDED_LEAF` có thể bị reject/throw thay vì âm thầm xử lý. Điều này chưa được xác nhận cuối cùng.

---

# C.2. Petiole

## C.2.1. Petiole key

**CONFIRMED**

Default petiole key:

```php
'__petiole__'
```

Có API lịch sử:

```php
setPetioleName(...)
```

cho phép thay tên key đặc biệt này.

Cần kiểm tra source hiện tại để xác nhận exact prototype.

---

## C.2.2. Petiole semantics

**CONFIRMED**

Ví dụ logical tree:

```text
a = 10
a/b = 20
```

không thể biểu diễn trực tiếp bằng PHP array nếu `a` đồng thời vừa là scalar vừa là array.

ExtArray biểu diễn tương đương theo dạng:

```php
[
    'a' => [
        '__petiole__' => 10,
        'b' => 20,
    ],
]
```

Như vậy node `a` là `PETIOLE`.

---

# C.3. Public API lịch sử cần bảo toàn/kiểm tra

Các API sau đã tồn tại trong thiết kế trước đây và được sử dụng như contract của ExtArray:

```php
setValue(...)
getValue(...)
getExistInfoAndValue(...)
traverseTree(...)
```

**CONFIRMED về sự tồn tại lịch sử và vai trò.**

Thiết kế mới gần nhất có chủ trương **giữ API ngoài như `setValue`, `getValue`, `traverseTree`**, trong khi refactor logic classification bên trong.

Cần Codex kiểm tra exact prototypes trong source.

---

## C.3.1. `setValue`

**CONFIRMED về semantics tổng quát**

`setValue` hỗ trợ các mode:

```text
replace
keep_old_value
merge_value
```

Tên constant/string exact trong source phải được kiểm tra.

### Các trường hợp topology cần xử lý

`setValue` phải xử lý ít nhất ma trận:

```text
target không tồn tại
target là LEAF
target là BRANCH
target là PETIOLE
```

kết hợp với:

```text
REPLACE
KEEP_OLD_VALUE
MERGE_VALUE
```

Điểm quan trọng:

- logical leaf value khác với physical storage;
- nếu target hiện là `BRANCH` nhưng cần gán logical value tại chính node đó, node phải chuyển thành `PETIOLE` và logical value được lưu dưới petiole key;
- nếu trên đường đi có một leaf nhưng cần đi sâu hơn, leaf có thể phải **auto-promote thành PETIOLE** để giữ giá trị cũ đồng thời tạo sub-branch.

### Mid-path leaf promotion

**CONFIRMED**

Nếu một leaf xuất hiện ở giữa path mới, ví dụ:

```text
đang có:
a = 10

sau đó set:
a/b = 20
```

ExtArray phải bảo toàn `a = 10` bằng cách chuyển:

```php
'a' => 10
```

thành:

```php
'a' => [
    '__petiole__' => 10,
    'b' => 20,
]
```

Đây là invariant quan trọng.

---

## C.3.2. `getValue`

**CONFIRMED về vai trò**

Phải trả logical value của path, không bắt caller biết logical value đang nằm trực tiếp ở node hay dưới petiole key.

Ví dụ:

```php
getValue(['a'])
```

đối với PETIOLE phải trả logical value của `a`, không phải toàn bộ physical array của node `a`.

Exact error behavior/source prototype cần kiểm tra.

---

## C.3.3. `getExistInfoAndValue`

**CONFIRMED về vai trò; OPEN về exact legacy semantics**

API này trả thông tin tồn tại + value/type của path.

Trong trao đổi cũ có lưu ý rằng hàm có **legacy leaf-as-final-path semantics**: leaf value là kết quả cuối của path, không phải một path component để đi tiếp.

Cần kiểm tra source hiện tại trước khi thay đổi vì có khả năng các subsystem khác phụ thuộc vào behavior cũ.

---

## C.3.4. `traverseTree`

**CONFIRMED**

`traverseTree()` là engine duyệt tree và là điểm tích hợp quan trọng với normalization.

Thiết kế đã được mở rộng để **root cũng có thể là LEAF**, không bắt buộc root luôn là branch.

Đây là contract cần bảo toàn khi refactor classification.

---

# C.4. Callback APIs

## C.4.1. API callback lịch sử

Các callback setter từng tồn tại:

```php
setValidLeafFn(...)
setFilterFn(...)
setStartBranchFn(...)
setEndBranchFn(...)
```

Trong implementation cũ, closures được bind về object bằng hướng:

```php
$closure->bindTo($this, static::class)
```

**CONFIRMED là implementation/design lịch sử.**

Tuy nhiên, `setFilterFn` và cách phân vai giữa `validLeafFn`, `filterFn`, `defineLeafFn`, `classifyNode` đã thay đổi nhiều lần.

Do đó:

- không được coi toàn bộ callback API lịch sử là thiết kế mới nhất;
- phải kiểm tra source hiện tại;
- xem mục F về các thiết kế đã bị thay thế.

---

# C.5. `defineLeafFn`

## C.5.1. Prototype

**CONFIRMED**

Thiết kế mới nhất chốt:

`defineLeafFn` nhận **1 hoặc 2 tham số**.

Hai dạng được chấp nhận về mặt contract:

```php
function (mixed $value)
```

hoặc:

```php
function (array $arrChain, mixed $value)
```

`$arrChain` là path/context chain tới node đang xét.

Root được gọi với:

```php
$arrChain = []
```

nếu callback dùng dạng có path.

---

## C.5.2. Return type

**CONFIRMED**

`defineLeafFn` trả:

```php
int|array
```

Không còn contract kiểu `true/false`.

Các giá trị `int` semantic:

```php
EXCLUDED
BRANCH_OR_PETIOLE
COMPOSITE_LEAF
DISCARDED_LEAF
```

`array` được dùng để biểu diễn leaf definition cụ thể, điển hình là một `AtomicSchema`/schema definition mà layer normalization phía trên có thể dùng.

Thiết kế mới nhất cố tình **không yêu cầu UDA trả trực tiếp `BRANCH` hoặc `PETIOLE`** để tránh đẩy topology knowledge sang UDA.

---

## C.5.3. Ý nghĩa return

### `EXCLUDED`

**CONFIRMED**

Node/subtree bị loại khỏi processing của pass hiện tại.

Cần giữ khác biệt giữa:

- node không thuộc pass hiện tại;
- node là leaf nhưng invalid;
- node bị “discard” khỏi dữ liệu.

`EXCLUDED` không đồng nghĩa với `DISCARDED_LEAF`.

---

### `BRANCH_OR_PETIOLE`

**CONFIRMED**

UDA xác nhận node cần tiếp tục được duyệt như một container.

ExtArray sẽ dùng logic nội bộ:

```text
detectBranchOrPetiole
```

để phân giải node thành:

```text
BRANCH
hoặc
PETIOLE
```

UDA không phải làm bước này.

---

### `COMPOSITE_LEAF`

**CONFIRMED**

Node được coi là một logical leaf mặc dù physical value có thể là array/object phức tạp.

`processLeafFn` xử lý **nguyên node**.

Mục đích là hỗ trợ các unit dữ liệu mà không thể/không nên traverse xuống từng field như branch thông thường.

---

### `array`

**CONFIRMED ở mức normalization design**

Một array return được dùng làm leaf definition, điển hình:

```text
AtomicSchema
```

`JsonNormalizer`/adapter phía trên dùng definition này để normalize/validate atomic leaf.

ExtArray chỉ cần bảo toàn và chuyển leaf definition tới processing callback theo contract, không nên tự hiểu toàn bộ schema business.

---

### `DISCARDED_LEAF`

**CONFIRMED là token, OPEN về execution**

Dự kiến dành cho cơ chế “bứt/cắt leaf” khỏi tree.

Chưa được triển khai/chốt đầy đủ trong giai đoạn hiện tại.

---

# C.6. `processLeafFn`

Thiết kế của callback này đã trải qua nhiều phiên bản.

## C.6.1. UDA-level processing

**CONFIRMED ở mức JsonNormalizer design**

UDA processing function được định hướng theo dạng:

```php
function (array $arrChain, mixed $value): array
```

hoặc một biến thể có thêm leaf definition nếu adapter cần chuyển definition:

```php
function (
    array $arrChain,
    mixed $value,
    mixed $leafDefinition
): array
```

Kết quả là một array mô tả:

```text
success/status
normalized value
hoặc
error information
```

Exact tuple/field names cần đọc source `JsonNormalizer` hiện tại.

---

## C.6.2. ExtArray adapter

**CONFIRMED về pattern, OPEN về exact current prototype**

Trong thiết kế `JsonNormalizer`, adapter vào ExtArray từng dùng pattern:

```php
function (
    array $arrChain,
    &$value,
    $leafDefinition = null
) use ($uda) {
    ...
}
```

Nguyên tắc:

- UDA nhận value theo logical input;
- nếu xử lý thành công thì adapter mới commit normalized value vào `$value`;
- nếu lỗi thì giữ giá trị cũ và ghi error tree ở `JsonNormalizer`.

Không được khẳng định exact prototype trên là source hiện tại nếu chưa kiểm tra.

---

# C.7. `detectBranchOrPetiole`

**CONFIRMED về trách nhiệm thiết kế**

Sau khi bỏ `classifyNode`, ExtArray vẫn cần một internal helper để phân biệt:

```text
BRANCH
vs
PETIOLE
```

đối với node đã được `defineLeafFn` phân loại semantic là:

```text
BRANCH_OR_PETIOLE
```

Tên được thảo luận/chốt:

```php
detectBranchOrPetiole(...)
```

Ý tưởng chính:

1. node phải có cấu trúc phù hợp để traverse;
2. nếu tồn tại petiole key thì node là `PETIOLE`;
3. nếu không thì là `BRANCH`;
4. petiole key phải được kiểm tra hợp lệ như một leaf.

Exact prototype cần kiểm tra code được sinh gần nhất/source hiện tại.

---

# C.8. Petiole validation

**CONFIRMED về invariant**

Nếu node chứa:

```php
'__petiole__'
```

thì giá trị ở petiole key phải là một valid logical leaf theo contract đang dùng.

Trong `classifyNode` cũ từng có guard đại ý:

```php
if (end($arrChain) === self::$strPetiole) {
    throw new LogicException(
        "Invalid '__petiole__' node: expected a valid leaf but found an invalid structure."
    );
}
```

Thiết kế mới không nhất thiết giữ đúng đoạn code này, nhưng **invariant phải còn**:

> Không được chấp nhận một `__petiole__` node có cấu trúc làm nó lại trở thành branch/petiole không hợp lệ.

Việc check có thể được chuyển sang validation riêng thay vì nằm trong `classifyNode`.

---

# C.9. Path/key constraints

## C.9.1. Branch key

**OPEN / cần source verification**

Thiết kế rất cũ giới hạn branch key là string.

Sau đó có thảo luận mở rộng key sang:

```php
string|int
```

để phù hợp JSON/PHP arrays có numeric indices.

Không có bằng chứng đủ rõ trong ngữ cảnh tổng hợp rằng việc mở rộng này đã được chốt cho public path API.

Codex phải kiểm tra source hiện tại trước khi thay đổi.

---

## C.9.2. Null và key existence

**CONFIRMED về nguyên tắc kỹ thuật**

Khi phát hiện petiole hoặc kiểm tra key tồn tại, không được dùng logic khiến:

```php
null
```

bị nhầm thành “không tồn tại”.

Khi cần phân biệt tồn tại với null, phải dùng semantics tương đương:

```php
array_key_exists(...)
```

thay vì chỉ dựa vào `isset(...)`.

---

# D. Algorithms

# D.1. Traversal algorithm — trạng thái thiết kế mới nhất

**CONFIRMED ở mức architecture**

Pseudocode khái niệm:

```text
visit(node, path):

    leafDefinition = defineLeafFn(path, node)
        hoặc defineLeafFn(node)

    switch leafDefinition:

        EXCLUDED:
            stop processing this node/subtree

        COMPOSITE_LEAF:
            process whole node as one logical leaf
            stop descending

        array schema/leaf definition:
            process node as logical leaf using the supplied definition
            stop descending

        BRANCH_OR_PETIOLE:
            structuralType = detectBranchOrPetiole(node, path)

            if structuralType == PETIOLE:
                process/handle logical petiole value as required by traversal contract
                traverse child nodes excluding the petiole storage key

            if structuralType == BRANCH:
                traverse children

        DISCARDED_LEAF:
            semantics not yet implemented/chốt
```

Điểm mấu chốt:

- `defineLeafFn` quyết định **semantic category**.
- ExtArray quyết định **physical topology** khi semantic category là `BRANCH_OR_PETIOLE`.
- Không cần một public/separate `classifyNode` làm tầng trung gian nữa.

---

# D.2. `detectBranchOrPetiole`

Pseudocode conceptual:

```text
detectBranchOrPetiole(node):

    node must be structurally traversable

    if petiole key exists:
        validate the petiole entry is a legitimate leaf
        return PETIOLE

    return BRANCH
```

**CONFIRMED về hướng.**

Không được suy diễn rằng chỉ cần `array_key_exists('__petiole__')` là đủ trong mọi trường hợp; invalid petiole structure vẫn phải bị phát hiện.

---

# D.3. `setValue` — topology conversion

## D.3.1. Gặp mid-path LEAF

**CONFIRMED**

Ví dụ:

```text
existing:
A = x

set:
A/B = y
```

Thuật toán logic:

```text
A đang là LEAF
→ tạo container tại A
→ chuyển x vào petiole slot
→ tạo child B
→ set B = y
```

Kết quả:

```php
[
    'A' => [
        '__petiole__' => $x,
        'B' => $y,
    ],
]
```

---

## D.3.2. Set logical value tại BRANCH

**CONFIRMED**

Ví dụ:

```text
existing:
A/B = y

set:
A = x
```

Không được phá subtree `A/B` chỉ để lưu `A=x`, trừ khi mode `replace` được định nghĩa rõ là thay toàn physical node trong source hiện tại.

Hướng thiết kế đã thảo luận cho logical-value semantics:

```text
BRANCH A
→ PETIOLE A
→ store x under __petiole__
→ preserve children
```

Tuy nhiên exact behavior theo từng `replace / keep_old_value / merge_value` phải đối chiếu implementation hiện tại.

---

# D.4. JsonNormalizer multi-step

**CONFIRMED**

Các step chạy tuần tự trên cùng tree.

Một node đã được xử lý ở step trước có thể phải trả `EXCLUDED` ở step sau để tránh xử lý lại.

Root có thể là LEAF.

Một step có thể xác định:

```text
EXCLUDED
atomic leaf
COMPOSITE_LEAF
BRANCH_OR_PETIOLE
```

Errors được lưu ở một tree phản chiếu path dữ liệu.

Processing thành công mới commit value mới.

Processing lỗi:

- giữ original/current value;
- ghi error state;
- không biến lỗi normalization thành structural topology corruption.

---

# D.5. “Bứt/cắt lá”

**OPEN**

Mục tiêu tương lai:

- trong traversal/normalization, một logical leaf có thể được đánh dấu để remove khỏi tree;
- token dự kiến là:

```php
DISCARDED_LEAF
```

Các vấn đề đã nhận diện nhưng chưa chốt đầy đủ:

1. Remove leaf ở root thì kết quả tree là gì?
2. Remove petiole value nhưng node vẫn có children thì:
   - PETIOLE → BRANCH?
3. Remove child cuối cùng của branch có ảnh hưởng parent không?
4. Numeric keys:
   - giữ nguyên key/hole,
   - hay reindex?
5. Nếu node đang có lỗi normalization thì discard có được phép không?
6. Multi-step:
   - leaf bị discard ở step N có còn xuất hiện ở step N+1 không? về logic là không, nhưng behavior cần implement rõ.
7. Cần signal/return contract nào giữa `processLeafFn`, ExtArray và `JsonNormalizer`.

Trong trao đổi trước có khuynh hướng **giữ nguyên numeric keys** khi remove, nhưng chưa đủ căn cứ để ghi thành CONFIRMED.

---

# E. Confirmed Design Decisions

# E.1. Bỏ `classifyNode` khỏi thiết kế mới

**CONFIRMED**

### Quyết định

Không tiếp tục duy trì `classifyNode` như một tầng cần thiết giữa UDA và traversal.

Thay vào đó:

```text
defineLeafFn
    ↓
semantic result
    ↓
ExtArray internal topology resolver
```

Đặc biệt:

```text
defineLeafFn returns BRANCH_OR_PETIOLE
→ ExtArray detectBranchOrPetiole()
→ BRANCH or PETIOLE
```

### Lý do

`classifyNode` tạo thêm một tầng classification có phần trùng vai trò với `defineLeafFn`.

Nếu buộc UDA trả trực tiếp `BRANCH/PETIOLE`, UDA phải biết chi tiết storage topology của ExtArray, làm tăng coupling.

`BRANCH_OR_PETIOLE` cho phép UDA chỉ nói:

> node này là container cần descend

còn ExtArray tự hiểu physical representation.

### Ảnh hưởng

Cần rà tất cả call-site đang gọi:

```php
classifyNode(...)
```

và chuyển trách nhiệm phù hợp sang:

- `defineLeafFn`;
- `detectBranchOrPetiole`;
- validation helper nội bộ.

---

# E.2. Không làm UDA chịu thêm trách nhiệm phân biệt BRANCH/PETIOLE

**CONFIRMED**

Đây là tiêu chí thiết kế mấu chốt.

Việc bỏ `classifyNode` chỉ được chấp nhận nếu không đẩy gánh nặng topology sang UDA.

UDA trả:

```php
BRANCH_OR_PETIOLE
```

ExtArray resolve topology.

---

# E.3. Bộ constant numeric mới

**CONFIRMED**

```php
EXCLUDED          = 0;
BRANCH            = 1;
LEAF              = 2;
PETIOLE           = 3;
BRANCH_OR_PETIOLE = 13;
COMPOSITE_LEAF    = 20;
DISCARDED_LEAF    = 21;
```

Việc dùng numeric constants thay cho mixed string constants giúp contract nhất quán hơn và dễ kiểm tra bằng type `int|array`.

---

# E.4. `defineLeafFn` không còn trả boolean

**CONFIRMED**

Return contract:

```php
int|array
```

thay cho các phiên bản cũ kiểu:

```text
true/false
false/'composite'/array
```

Lý do:

- boolean không đủ biểu đạt semantic;
- normalization cần phân biệt excluded, composite, atomic definition, container;
- numeric semantic tokens làm contract rõ hơn.

---

# E.5. Root có thể là LEAF

**CONFIRMED**

Traversal không được giả định root luôn là array branch.

Điều này quan trọng đối với `JsonNormalizer` vì JSON input hợp lệ về mặt logical normalization có thể là một atomic value hoặc composite leaf ở root.

---

# E.6. Leaf giữa path được auto-promote thành PETIOLE

**CONFIRMED**

Đây là behavior cốt lõi để ExtArray có thể hỗ trợ đồng thời:

```text
A = x
A/B = y
```

mà không làm mất dữ liệu.

---

# E.7. Tách semantic leaf definition khỏi physical topology

**CONFIRMED**

Các khái niệm:

```text
COMPOSITE_LEAF
AtomicSchema array
EXCLUDED
DISCARDED_LEAF
```

không phải topology giống `BRANCH/PETIOLE/LEAF`.

Thiết kế mới phân biệt rõ:

```text
semantic definition
vs
physical tree topology
```

---

# F. Superseded / Rejected Designs

# F.1. `defaultFilterFn`/custom `filterFn` làm cả topology lẫn validation

**SUPERSEDED về vai trò trung tâm**

Thiết kế cũ có `defaultFilterFn()` theo hướng:

```text
if validLeafFn(obj)
    → LEAF
else if not array
    → error
else if has __petiole__ and sub-branches
    → PETIOLE
else
    → BRANCH
```

Vấn đề:

- trộn leaf validation với topology;
- normalization cần có khả năng coi một value chưa hợp lệ là leaf để processor trả lỗi chi tiết;
- khó biểu diễn composite leaf;
- khó chạy nhiều pass với semantic khác nhau.

Hướng thay thế:

```text
defineLeafFn → semantic definition
ExtArray → internal topology detection
```

`defaultFilterFn` có thể vẫn tồn tại trong source vì compatibility; không được xóa trước khi kiểm tra call-sites.

---

# F.2. `defineLeafFn(...): bool`

**SUPERSEDED**

Từng có đề xuất:

```php
defineLeafFn($obj): bool
```

hoặc:

```php
defineLeafFn(array $arrChain, mixed $obj): bool
```

với `true = leaf`, `false = branch`.

Đã bị thay thế vì không đủ biểu đạt các loại:

```text
EXCLUDED
COMPOSITE_LEAF
BRANCH_OR_PETIOLE
AtomicSchema
DISCARDED_LEAF
```

---

# F.3. `classifyNode` trả `(node_type, leaf_definition)`

**SUPERSEDED**

Một phiên bản trung gian dùng:

```text
defineLeafFn
    ↓
classifyNode
    ↓
[node_type, leaf_definition]
```

Đã bị thay thế bởi hướng bỏ `classifyNode`.

Giá trị lịch sử của thiết kế này: cho thấy cần giữ cả topology và leaf definition, nhưng hai phần đó không nhất thiết phải được đóng gói trong một public/intermediate method.

---

# F.4. UDA trực tiếp trả `BRANCH` hoặc `PETIOLE`

**REJECTED / SUPERSEDED**

Không chọn hướng này vì:

- UDA phải biết chi tiết petiole storage;
- làm tăng coupling;
- mỗi UDA phải lặp lại logic topology;
- đi ngược mục tiêu để ExtArray chịu trách nhiệm cấu trúc tree.

Thay bằng:

```php
BRANCH_OR_PETIOLE
```

---

# F.5. String constants cho semantic classification

**SUPERSEDED**

Từng có dạng:

```php
COMPOSITE_LEAF = 'composite_leaf';
BRANCH_OR_PETIOLE = 'branch_or_petiole';
DISCARDED_LEAF = '...';
```

Thiết kế mới chuyển sang numeric constants:

```php
13, 20, 21
```

---

# F.6. Coi `EXCLUDED` là structural node type ngang hàng BRANCH/LEAF/PETIOLE

**SUPERSEDED về cách hiểu**

`EXCLUDED` nên được hiểu chủ yếu là **processing/traversal policy** cho pass hiện tại, không phải một physical topology của dữ liệu.

Giá trị constant vẫn giữ là:

```php
EXCLUDED = 0;
```

---

# G. Implementation Status

> Phần này cố ý thận trọng. Không có source code hiện tại được đọc trong phiên tổng hợp này.

## G.1. Những gì biết là đã tồn tại trong các phiên bản trước

**KNOWN FROM PRIOR DISCUSSION, NOT CURRENT-SOURCE VERIFIED**

Các thành phần sau đã từng tồn tại hoặc từng được cung cấp dưới dạng code trong chat:

- class `ExtArray`;
- constants `EXCLUDED / BRANCH / LEAF / PETIOLE`;
- petiole key mặc định `__petiole__`;
- `setPetioleName`;
- `setValue`;
- `getValue`;
- `getExistInfoAndValue`;
- `traverseTree`;
- callback plumbing;
- `defaultFilterFn`;
- logic mid-path promotion;
- các mode `replace / keep_old_value / merge_value`;
- một hoặc nhiều version của `classifyNode`;
- một version mới được yêu cầu sinh lại với `DISCARDED_LEAF` nhưng **chưa code “cắt lá”**.

Không được coi bản code từng sinh trong chat là source hiện tại.

---

## G.2. Những thay đổi design mới nhất có khả năng cần áp dụng

Codex cần kiểm tra source để xác định đã có hay chưa:

1. constants mới:

```php
BRANCH_OR_PETIOLE = 13;
COMPOSITE_LEAF    = 20;
DISCARDED_LEAF    = 21;
```

2. `defineLeafFn`:

```php
1 hoặc 2 input parameters
return int|array
```

3. loại bỏ call-path phụ thuộc vào `classifyNode`;

4. internal helper:

```php
detectBranchOrPetiole(...)
```

5. validation chặt cho invalid `__petiole__`;

6. đảm bảo UDA không phải tự resolve BRANCH/PETIOLE;

7. giữ root-leaf traversal;

8. compatibility với `JsonNormalizer`.

---

## G.3. Điểm phải kiểm tra trực tiếp bằng source

Codex nên ưu tiên grep/search các symbol:

```text
class ExtArray
classifyNode
defineLeafFn
defaultFilterFn
setFilterFn
setValidLeafFn
setProcessLeafFn
processLeafFn
detectBranchOrPetiole
traverseTree
setValue
setValueMVModeAtEndPath
getValue
getExistInfoAndValue
setPetioleName
__petiole__
COMPOSITE_LEAF
BRANCH_OR_PETIOLE
DISCARDED_LEAF
```

và các consumer:

```text
JsonNormalizer
TableDAUNormalizer
```

Không nên sửa ExtArray trước khi lập call-site map.

---

# H. Open Issues

# H.1. Full semantics của `DISCARDED_LEAF`

**OPEN**

Đã có constant, chưa có contract triển khai hoàn chỉnh.

Cần quyết định:

- `defineLeafFn` trả `DISCARDED_LEAF` trực tiếp hay `processLeafFn` mới phát signal discard?
- discard xảy ra trước hay sau processing?
- discard root?
- discard petiole value?
- discard last child?
- preserve/reindex numeric keys?
- error interaction?

---

# H.2. Exact `processLeafFn` prototype sau refactor

**OPEN**

Có nhiều version trong lịch sử.

Cần source review để chốt một contract duy nhất giữa:

```text
ExtArray
JsonNormalizer
UDA
```

Đặc biệt cần xác định leaf definition được truyền:

- như tham số thứ ba;
- hay giữ trong state/context khác.

Không được tự chốt chỉ từ các draft cũ.

---

# H.3. Callback validation APIs

**OPEN**

Trước đây có:

```text
validLeafFn
validateLeafFn
validateUdaProcessLeafFn
setFilterFn
```

Một phần đã thay đổi ý nghĩa qua refactor.

Cần xác định:

- callback nào còn public;
- callback nào chỉ là internal adapter;
- validation nào kiểm tra signature;
- validation nào kiểm tra data/schema.

---

# H.4. Key type: `string` hay `string|int`

**OPEN**

Numeric array index là nhu cầu thực tế của JSON/table data.

Nhưng public path compatibility phải được kiểm tra trước khi mở rộng.

---

# H.5. `setValueMVModeAtEndPath`

**OPEN về implementation, nhưng có vai trò thiết kế rõ**

Trong thảo luận gần nhất, đây được nhận diện là một trong các nơi trực tiếp cần biết target cuối là:

```text
BRANCH
hay
PETIOLE
```

nên khi bỏ `classifyNode`, helper này hoặc caller của nó cần dùng internal topology resolver thay thế.

Codex phải inspect exact implementation và tất cả branch conditions trước khi sửa.

---

# H.6. Invalid petiole detection location

**OPEN**

Invariant đã rõ:

> `__petiole__` phải chứa một valid logical leaf.

Nhưng chưa cần giữ check ở đúng vị trí cũ.

Các lựa chọn:

- trong `detectBranchOrPetiole`;
- helper validation riêng;
- trong traversal trước khi processing petiole.

Cần chọn dựa trên current code structure.

---

# I. Compatibility and Dependencies

# I.1. `JsonNormalizer`

**CONFIRMED — dependency chính**

Mọi refactor ExtArray phải kiểm tra ít nhất:

- `defineLeafFn` signature;
- leaf-definition passing;
- root leaf;
- `EXCLUDED`;
- `COMPOSITE_LEAF`;
- `BRANCH_OR_PETIOLE`;
- behavior khi processing callback mutate by reference;
- error-tree path consistency;
- multi-step traversal.

---

# I.2. `TableDAUNormalizer`

**CONFIRMED là consumer gián tiếp/tiềm năng qua JsonNormalizer**

`TableDAUNormalizer` được thiết kế dựa trên `JsonNormalizer`, thường chỉ cần một normalization step.

Do đó thay đổi ExtArray có thể ảnh hưởng:

```text
TableDAUNormalizer
    → JsonNormalizer
        → ExtArray
```

Đặc biệt structural path dạng:

```text
delete → int → key_field
add    → int → field_name
update → int → (keys|values) → field_name
```

phụ thuộc traversal với numeric indices.

Đây là thêm một lý do phải kiểm tra vấn đề `string|int` path key.

---

# I.3. Public ExtArray APIs

**CONFIRMED cần thận trọng compatibility**

Không nên thay đổi tùy tiện:

```text
setValue
getValue
getExistInfoAndValue
traverseTree
```

Nếu exact signature phải đổi, cần search toàn PBT Framework trước.

---

# I.4. Petiole storage

**CONFIRMED cần bảo toàn**

Bất kỳ code nào truy cập physical tree có thể phụ thuộc:

```php
'__petiole__'
```

hoặc configured petiole name.

Do đó thay storage representation là một breaking change lớn và không nằm trong thiết kế hiện tại.

---

# J. Test Scenarios

Phần này chia thành:

- **Confirmed scenarios** — trực tiếp kiểm chứng contract đã thống nhất.
- **Recommended additional tests** — test nên bổ sung khi triển khai, nhưng không đồng nghĩa từng test đã được thảo luận/chốt trước đây.

---

## J.1. Confirmed scenarios

### J.1.1. Plain leaf

Input:

```php
['a' => 10]
```

Expect:

```text
a = LEAF
getValue(a) = 10
```

---

### J.1.2. Plain branch

Input:

```php
[
    'a' => [
        'b' => 20,
    ],
]
```

Expect:

```text
a = BRANCH
a/b = LEAF
```

---

### J.1.3. Petiole

Input:

```php
[
    'a' => [
        '__petiole__' => 10,
        'b' => 20,
    ],
]
```

Expect:

```text
a = PETIOLE
logical value(a) = 10
a/b = 20
```

---

### J.1.4. Mid-path LEAF → PETIOLE

Start:

```php
[
    'a' => 10,
]
```

Operation conceptually:

```text
setValue(a/b, 20)
```

Expect:

```php
[
    'a' => [
        '__petiole__' => 10,
        'b' => 20,
    ],
]
```

---

### J.1.5. Root leaf traversal

Input:

```php
10
```

Expect:

- traversal accepts root as logical leaf;
- `defineLeafFn` is called for root;
- with 2-arg callback:

```php
$arrChain === []
```

---

### J.1.6. `BRANCH_OR_PETIOLE` resolves to BRANCH

Input node:

```php
[
    'x' => 1,
    'y' => 2,
]
```

`defineLeafFn` returns:

```php
BRANCH_OR_PETIOLE
```

Expect internal topology:

```text
BRANCH
```

---

### J.1.7. `BRANCH_OR_PETIOLE` resolves to PETIOLE

Input node:

```php
[
    '__petiole__' => 5,
    'x' => 1,
]
```

`defineLeafFn` returns:

```php
BRANCH_OR_PETIOLE
```

Expect:

```text
PETIOLE
```

---

### J.1.8. `COMPOSITE_LEAF`

Input:

```php
[
    'x' => 1,
    'y' => 2,
]
```

Nếu `defineLeafFn` trả:

```php
COMPOSITE_LEAF
```

Expect:

- node được process nguyên khối;
- không traverse `x`, `y` như children của branch.

---

### J.1.9. `EXCLUDED`

Nếu `defineLeafFn` trả:

```php
EXCLUDED
```

Expect:

- node/subtree không được processing trong pass đó;
- không bị hiểu là data error;
- không bị remove khỏi source tree.

---

### J.1.10. Null petiole value

Input:

```php
[
    'a' => [
        '__petiole__' => null,
        'b' => 1,
    ],
]
```

Nếu logical leaf policy cho phép null, presence của petiole key vẫn phải được nhận biết.

Test này kiểm chứng không dùng `isset()` sai chỗ.

---

### J.1.11. Invalid petiole structure

Một cấu trúc làm cho `__petiole__` không còn là valid leaf phải bị reject bằng exception/logic error phù hợp, không được silently coi là nested branch hợp lệ.

Exact exception class/message cần lấy từ source contract sau khi review.

---

## J.2. Recommended additional tests

**PROPOSED TESTS**

### J.2.1. Full setValue mode matrix

Test:

```text
target state:
missing / LEAF / BRANCH / PETIOLE

× mode:
replace / keep_old_value / merge_value
```

Mục tiêu: tránh regression khi bỏ `classifyNode`.

---

### J.2.2. Custom petiole name

Sau:

```text
setPetioleName(customName)
```

mọi:

- detection;
- getValue;
- setValue;
- traversal;

phải dùng custom name nhất quán.

---

### J.2.3. Numeric indices

Nếu source hiện hỗ trợ hoặc quyết định hỗ trợ:

```php
[
    'rows' => [
        0 => [...],
        1 => [...],
    ],
]
```

test path có int segment.

---

### J.2.4. Multi-step JsonNormalizer

Step 1 process một leaf.

Step 2 phải exclude hoặc xử lý đúng theo contract đã định, không process lại ngoài ý muốn.

---

### J.2.5. Exception safety

Nếu callback throw giữa traversal:

- tree không bị half-converted một cách không kiểm soát;
- reference mutation phải được hiểu rõ.

Exact transactionality chưa được chốt, nhưng cần test current behavior.

---

### J.2.6. Future discard tests

Chỉ viết sau khi contract `DISCARDED_LEAF` được chốt:

- discard ordinary leaf;
- discard petiole value;
- discard child;
- discard root;
- numeric key behavior.

---

# K. Next Steps

## K.1. Việc đầu tiên trong Codex

**CONFIRMED**

Không code ngay.

Trước hết cần audit source hiện tại:

1. locate class `ExtArray`;
2. đọc toàn bộ class;
3. search toàn repo các call-site:
   - `classifyNode`
   - `defineLeafFn`
   - `traverseTree`
   - `setValue`
   - callback setters;
4. đọc `JsonNormalizer`;
5. nếu có, đọc `TableDAUNormalizer`;
6. lập dependency/call-site map.

---

## K.2. Rà soát mục tiêu bỏ `classifyNode`

**CONFIRMED**

Với từng call-site `classifyNode`, phân loại:

```text
A. chỉ cần semantic result từ defineLeafFn
B. thực sự cần BRANCH/PETIOLE physical topology
C. cần both leaf definition + topology
```

Sau đó:

- A → dùng `defineLeafFn`;
- B → dùng `detectBranchOrPetiole` hoặc internal equivalent;
- C → tách hai trách nhiệm, không tái tạo một `classifyNode` trá hình nếu không cần.

---

## K.3. Kiểm tra UDA burden

**CONFIRMED**

Sau refactor phải kiểm chứng:

> UDA không phải tự viết logic phát hiện `__petiole__`, BRANCH hay PETIOLE.

Nếu refactor khiến UDA phải làm việc đó, thiết kế đã đi sai hướng.

---

## K.4. Chốt callback contracts từ source thực tế

**OPEN**

Cần xác định exact prototypes hiện tại của:

```text
defineLeafFn
processLeafFn
setValidLeafFn
setFilterFn
setStartBranchFn
setEndBranchFn
```

Sau đó mới quyết định compatibility layer hoặc refactor.

---

## K.5. Chưa triển khai “cắt lá”

**CONFIRMED**

Ở giai đoạn ngay sau handoff:

- giữ `DISCARDED_LEAF` trong design;
- không tự động triển khai discard;
- trước hết hoàn tất chuẩn hóa ExtArray theo hướng bỏ `classifyNode`.

Sau khi baseline ổn định mới quay lại thiết kế “bứt/cắt lá”.

---

# Appendix A — Design snapshot ngắn

Thiết kế mới nhất có thể tóm gọn:

```php
EXCLUDED          = 0;
BRANCH            = 1;
LEAF              = 2;
PETIOLE           = 3;
BRANCH_OR_PETIOLE = 13;
COMPOSITE_LEAF    = 20;
DISCARDED_LEAF    = 21;
```

```text
defineLeafFn:
    input  = (value)
          | (path, value)

    output = int | array
```

Semantic:

```text
EXCLUDED
    → skip node/subtree in this pass

BRANCH_OR_PETIOLE
    → ExtArray internally resolves BRANCH/PETIOLE

COMPOSITE_LEAF
    → process whole node as one leaf

array
    → leaf definition, e.g. AtomicSchema

DISCARDED_LEAF
    → reserved for future leaf removal; not yet implemented
```

Core architectural rule:

```text
UDA decides semantic meaning.
ExtArray owns physical tree topology.
```

Vì vậy:

```text
classifyNode
```

không còn là tầng cần thiết trong thiết kế mới.

---

# Appendix B — Cảnh báo cho phiên Codex mới

1. Tài liệu này **không phải source-of-truth cho implementation hiện tại**.
2. Code từng được sinh trong chat không đồng nghĩa đã được merge vào repository.
3. Không xóa API cũ trước khi search call-sites.
4. Không triển khai `DISCARDED_LEAF` trước khi contract discard được chốt.
5. Không chuyển trách nhiệm BRANCH/PETIOLE sang UDA.
6. Phải bảo toàn semantics của PETIOLE và mid-path promotion.
7. Mọi thay đổi ExtArray phải review tác động lên `JsonNormalizer`, và qua đó có thể tác động `TableDAUNormalizer`.
8. Nếu source hiện tại mâu thuẫn với tài liệu:
   - ghi nhận mâu thuẫn;
   - xác định source đang ở design generation nào;
   - không tự ý coi source hay tài liệu là “đúng” nếu chưa đối chiếu mục tiêu thiết kế mới nhất.
