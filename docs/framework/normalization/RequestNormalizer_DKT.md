# RequestNormalizer — Design Knowledge Transfer

**Subsystem:** `RequestNormalizer` / `RequestSchema`  
**Framework:** PBT Framework  
**Ngôn ngữ tài liệu:** Tiếng Việt  
**Mục đích:** Chuyển giao tri thức thiết kế sang một phiên làm việc mới trong Codex có quyền truy cập source code hiện tại.

---

## Quy ước mức độ xác nhận

Tài liệu dùng bốn nhãn:

- **CONFIRMED** — Đã được thống nhất rõ trong cuộc trao đổi hiện tại.
- **PROPOSED** — Đã được đề xuất và có lý do thiết kế, nhưng chưa có quyết định cuối cùng.
- **OPEN** — Chưa giải quyết, còn thiếu contract, hoặc cần kiểm chứng bằng source hiện tại.
- **SUPERSEDED** — Phương án cũ đã bị thay thế hoặc không còn được coi là thiết kế hiện hành.

### Giới hạn bằng chứng

Tài liệu này tổng hợp từ cuộc trao đổi thiết kế hiện tại và một số artifact/source lịch sử đã được đọc trong ChatGPT.

Đã đọc được artifact của:

- `AtomicNormalizer`
- `JsonNormalizer`
- tài liệu DKT của `TableDAUNormalizer`

Tuy nhiên, **không có quyền truy cập trực tiếp repository PBT-DOCUMENT hiện tại trong cuộc trao đổi này**. Vì vậy:

- không được coi artifact đã đọc là source-of-truth của repository hiện tại;
- không được khẳng định một quyết định đã được triển khai chỉ vì từng có code trong chat;
- Codex phải đọc source hiện tại trước khi sửa hoặc triển khai.

Một điểm đặc biệt cần kiểm tra: artifact `AtomicNormalizer` đã đọc dùng namespace `Core\Normalization`, trong khi artifact lịch sử của `JsonNormalizer` đã đọc dùng `Core\Data\Normalization`. Người dùng hiện mô tả subsystem đang nằm trong `Core\Normalization`. Vì vậy namespace thực tế của `JsonNormalizer` trong repository hiện tại phải được xác minh.

---

# A. Overview

## A.1. Mục đích subsystem

**CONFIRMED** — PBT Framework đang hình thành một tầng normalization hoàn chỉnh cho dữ liệu request.

Các thành phần nền đã được hình thành theo mô tả của người dùng:

```text
AtomicSchema
AtomicNormalizer
JsonNormalizer
TableDAUNormalizer
```

Tầng tiếp theo cần thiết kế là:

```text
Core\Normalization\RequestSchema
Core\Normalization\RequestNormalizer
```

Mục tiêu của `RequestNormalizer` là nhận:

```text
Request
+
RequestSchema
```

sau đó:

1. lấy dữ liệu từ đúng source trong request;
2. dispatch sang normalizer phù hợp;
3. thu normalized data;
4. thu lỗi normalization;
5. trả một aggregate result thống nhất cho tầng phía trên.

`RequestNormalizer` là **orchestration layer**, không phải nơi triển khai lại logic atomic/json/table.

---

## A.2. Vị trí trong kiến trúc PBT

**CONFIRMED về hướng kiến trúc**:

```text
HTTP Request
    │
    ▼
Request abstraction
    +
RequestSchema
    │
    ▼
RequestNormalizer
    │
    ├── AtomicNormalizer
    │
    ├── JsonNormalizer
    │      └── AtomicNormalizer
    │      └── composite UDA processing
    │      └── TableDAUNormalizer khi phù hợp
    │
    └── future normalizers (ví dụ file/upload)
    │
    ▼
normalized request data
+
normalization errors
    │
    ▼
Controller / argument binding / business layer
```

`RequestNormalizer` không nên phụ thuộc trực tiếp vào HTTP response generation.

Normalization error và HTTP response là hai tầng trách nhiệm khác nhau.

---

## A.3. Phạm vi hiện tại

**CONFIRMED** — Các request source đã được bàn trực tiếp:

```text
segments
query
post
json
```

**OPEN / PROPOSED** — `files` được nhận diện là một source cần hỗ trợ trong kiến trúc tổng quát, nhưng `FileSchema` / `FileNormalizer` chưa được thiết kế trong cuộc trao đổi này.

---

# B. Architecture

## B.1. Các thành phần chính

### `RequestSchema`

**CONFIRMED về vai trò, OPEN về API class cụ thể**.

`RequestSchema` là contract do Application/UDA cung cấp để mô tả:

- dữ liệu cần lấy từ source nào;
- node/field nào cần normalize;
- loại schema/normalizer áp dụng cho dữ liệu đó.

Cấu trúc khái niệm là một nested array theo source:

```php
[
    'segments' => [...],
    'query'    => [...],
    'post'     => [...],
    'json'     => [...],
]
```

Không phải request nào cũng có đủ tất cả root.

Người dùng hiện muốn hình thành `Core\Normalization\RequestSchema`, nhưng public API cụ thể của class này (`isValid()`, constants, constructor, factory, v.v.) **chưa được chốt trong cuộc trao đổi hiện tại**.

---

### `RequestNormalizer`

**CONFIRMED về trách nhiệm, OPEN về prototype cụ thể**.

Vai trò:

```text
RequestSchema + Request
        ↓
source extraction
        ↓
normalizer dispatch
        ↓
aggregate normalized data + errors
```

Không nên:

- tự triển khai atomic conversion;
- tự duyệt chi tiết JSON thay `JsonNormalizer`;
- tự triển khai DAU table algorithm thay `TableDAUNormalizer`;
- gộp luôn HTTP response logic;
- gộp luôn controller argument binding nếu chưa có quyết định riêng.

---

### `AtomicSchema`

Dependency của `AtomicNormalizer`.

Các atomic type đã xuất hiện trong artifact được review gồm:

```text
int
float
decimal
string
bool
date
time
datetime
```

Cần đọc source hiện tại để xác minh danh sách cuối cùng.

---

### `AtomicNormalizer`

Xử lý một atomic value.

Đây là **unit normalizer**, khác cấp abstraction với `JsonNormalizer` và `RequestNormalizer`.

---

### `JsonNormalizer`

Xử lý một PHP array/JSON structure phức tạp qua một hoặc nhiều normalization step, dùng `ExtArray` làm traversal engine.

Đây là **aggregate normalizer**.

---

### `TableDAUNormalizer`

Xử lý một khối DAU của một table:

```text
delete
add
update
```

Theo thiết kế lịch sử, nó tận dụng:

```text
JsonNormalizer
AtomicNormalizer
AtomicSchema
```

Exact implementation hiện tại phải được kiểm tra trong repository.

---

### `ExtArray`

Dependency traversal của `JsonNormalizer`.

Các trao đổi trước đã refactor `ExtArray` theo hướng:

- Application/UDA định nghĩa leaf;
- ExtArray chịu trách nhiệm topology/traversal;
- processing callback có thể xử lý leaf;
- path/chain được bảo toàn để tạo lỗi đúng vị trí.

RequestNormalizer không nên phụ thuộc trực tiếp vào topology của `ExtArray`; dependency này nên được encapsulate trong `JsonNormalizer`.

---

## B.2. Phân tầng normalizer

Một distinction quan trọng đã hình thành:

### Unit normalizer

```text
AtomicNormalizer
```

Input: một value.  
Output: một unit result.

### Aggregate normalizer

```text
JsonNormalizer
TableDAUNormalizer
RequestNormalizer
```

Input: một cấu trúc dữ liệu.  
Output: trạng thái tổng thể + data + error structure.

Đây là cơ sở quan trọng để thiết kế output của `RequestNormalizer`.

---

# C. Current Design Contracts

## C.1. RequestSchema root structure

### `segments`

**CONFIRMED**.

Nguồn từng được mô tả là:

```php
$requestAuthContext->arrMCAO
```

Ví dụ:

```php
[
    'segments' => [
        5 => [
            'type' => 'int',
            // AtomicSchema...
        ],

        7 => [
            'type' => 'string',
            // AtomicSchema...
        ],
    ],
]
```

Key có thể là integer vì biểu diễn vị trí segment.

Mỗi declared segment hiện được coi là atomic data:

```text
segments[index]
    ↓
AtomicSchema
    ↓
AtomicNormalizer
```

**OPEN** — Phải xác minh source hiện tại để biết dữ liệu segment hiện nằm trực tiếp trong `Request`, trong `RequestAuthContext`, hay trong một abstraction route context khác.

---

### `query`

**CONFIRMED**.

Nguồn logic:

```php
$_GET
```

hoặc API tương ứng của `Request`.

Ví dụ:

```php
[
    'query' => [
        'id' => [
            'type' => 'int',
        ],

        'page' => [
            'type' => 'int',
            'gte'  => 1,
        ],

        'pageSize' => [
            'type' => 'int',
        ],
    ],
]
```

Thiết kế hiện tại coi các field `query` là atomic.

---

### `post`

**CONFIRMED**.

Nguồn logic:

```php
$_POST
```

hoặc API tương ứng của `Request`.

Ví dụ:

```php
[
    'post' => [
        'username' => [
            'type' => 'string',
        ],

        'password' => [
            'type' => 'string',
        ],

        'remember' => [
            'type' => 'bool',
        ],
    ],
]
```

Thiết kế hiện tại:

> `post` chủ yếu dùng cho simple/atomic form field.

Không dùng form POST làm transport chính cho structured table DAU.

---

### `json`

**CONFIRMED về nguyên tắc, OPEN về exact schema syntax**.

JSON là source cho structured/complex data.

Payload có thể chứa:

- atomic data;
- nested object/array;
- table DAU;
- composite structures.

**Không dùng traditional recursive JSON Schema để mô tả lại toàn bộ topology.**

JSON được xử lý bằng `JsonNormalizer`.

Exact format của phần `'json'` trong RequestSchema chưa được chốt; nó phải phản ánh configuration thực tế mà `JsonNormalizer` cần, không được quay lại thiết kế verbose kiểu mô tả mọi field/branch.

---

### `files`

**OPEN / PROPOSED**.

Đã nhận diện nhu cầu tương lai:

```php
[
    'files' => [
        'avatar' => [
            'type' => 'file',
            // ...
        ],
    ],
]
```

Nhưng chưa có contract hoàn chỉnh cho:

```text
FileSchema
FileNormalizer
```

Không tự ép file upload vào AtomicNormalizer nếu semantics không phù hợp.

---

## C.2. `type` làm schema discriminator

**CONFIRMED**.

Tất cả schema node được định hướng dùng chung field:

```php
'type'
```

để xác định loại schema.

Atomic:

```php
['type' => 'int']
['type' => 'string']
['type' => 'bool']
['type' => 'date']
['type' => 'datetime']
```

Structured:

```php
['type' => 'json']
['type' => 'table']
```

Dự kiến tương lai:

```php
['type' => 'file']
```

Conceptual dispatch:

```text
type ∈ AtomicSchema supported types
    → AtomicSchema / AtomicNormalizer

type = json
    → JsonNormalizer configuration

type = table
    → TableDAUNormalizer configuration

type = file
    → future file/upload normalizer
```

Không cần thêm discriminator dư thừa kiểu:

```php
'schema_type' => 'atomic'
```

khi `type` đã đủ phân biệt.

---

## C.3. AtomicNormalizer output contract

Artifact mới hơn đã đọc dùng:

```php
[
    'err_number' => int,
    'value'      => mixed,
]
```

Constants trong artifact:

```php
public const FIELD_ERR_NUMBER = 'err_number';
public const FIELD_VALUE = 'value';

public const ERR_NONE = 0;
```

Semantics:

### Success

```text
err_number == ERR_NONE
value = canonical normalized value
```

### Data error

```text
err_number != ERR_NONE
value = input nguyên gốc của lần gọi normalize hiện tại
```

### Schema/configuration error

```text
throw InvalidArgumentException
```

không biến thành normal data error.

Public API trong artifact:

```php
public static function normalize(
    mixed $value,
    array $arrSchema
): array;

public static function validate(
    mixed $value,
    array $arrSchema,
    bool $strict = true
): array;

public static function isValid(
    mixed $value,
    array $arrSchema,
    bool $strict = true
): bool;
```

**Implementation note:** artifact mới hơn đã đổi field từ lịch sử `'errNumber'` sang `'err_number'`. Codex phải kiểm tra source hiện tại và chỉ dùng public constant thay vì hard-code tên cũ.

---

## C.4. JsonNormalizer processing contract

Artifact lịch sử đã đọc mô tả API dạng:

```php
(new JsonNormalizer($data))
    ->addStep($defineFn, $processFn)
    ->normalize();
```

hoặc constructor nhận list step.

### UDA `defineLeafFn`

Artifact đã đọc cho phép:

```php
function (mixed $value)

function (
    array $path,
    mixed $value
)
```

Kết quả trong artifact lịch sử:

```text
ExtArray::EXCLUDED
ExtArray::BRANCH_OR_PETIOLE
ExtArray::COMPOSITE_LEAF
AtomicSchema array
```

**OPEN / compatibility warning** — ExtArray đã tiếp tục được thiết kế sau artifact này, bao gồm thay đổi marker và thảo luận `DISCARDED_LEAF`. Phải đọc source hiện tại trước khi giữ return type lịch sử `array|int|string`.

### UDA `processLeafFn`

Artifact lịch sử đã đọc:

```php
function (
    array $path,
    mixed $value
): array
```

Return:

```php
[
    'err_number' => int,
    'value'      => mixed,
]
```

Trong artifact đó, callback UDA nhận candidate **by value**; JsonNormalizer adapter nội bộ mới commit normalized value vào ExtArray by-reference khi success.

Không được vô tình biến callback UDA thành by-reference callback nếu source hiện tại vẫn giữ contract này.

### Internal ExtArray adapter

Artifact lịch sử dùng adapter conceptually:

```php
function (
    array $path,
    mixed &$value,
    $leafDefinition
): void
```

Mục đích:

- `leafDefinition` chuyển trực tiếp kết quả `defineLeafFn` sang processing;
- atomic schema → gọi `AtomicNormalizer`;
- composite marker → gọi UDA `processLeafFn`;
- commit chỉ khi success.

---

## C.5. JsonNormalizer output contract

**CONFIRMED trong artifact đã đọc**:

```php
[
    'status' => STATUS_OK | STATUS_ERROR,
    'data'   => array,
    'extra'  => array,
]
```

Constants artifact:

```php
public const STATUS_OK = 'ok';
public const STATUS_ERROR = 'normalization_error';
```

`data`:

```text
normalized hoặc partially normalized data
```

`extra`:

```text
sparse nested error tree
```

Ví dụ:

```php
[
    'table' => [
        'add' => [
            3 => [
                'age' => [
                    $errNumber,
                    $inputValue,
                ],
            ],
        ],
    ],
]
```

Error leaf dùng **numeric pair**:

```php
[$errNumber, $inputValue]
```

khác với associative pair của unit-result:

```php
[
    'err_number' => ...,
    'value'      => ...,
]
```

### Partial normalization semantics

Artifact đã đọc thể hiện:

- unit success được commit;
- unit error không commit candidate lỗi;
- sibling success không bị rollback;
- `extra === []` nghĩa là không ghi nhận normalization error;
- configuration/programming error có thể throw.

---

## C.6. TableDAUNormalizer contract

**CONFIRMED về thiết kế lịch sử, IMPLEMENTATION UNVERIFIED**.

Tài liệu TableDAUNormalizer gần nhất mô tả trung tâm API:

```php
public function normalize(): array;
```

và aggregate response mong muốn:

```php
[
    'status' => ...,
    'data'   => ...,
    'extra'  => ...,
]
```

Table DAU structure đã được thiết kế theo dạng:

```php
[
    'delete' => [
        [
            'key_1' => ...,
            'key_2' => ...,
        ],
    ],

    'add' => [
        [
            'field_a' => ...,
            'field_b' => ...,
        ],
    ],

    'update' => [
        [
            'keys' => [
                'key_1' => ...,
            ],

            'values' => [
                'field_a' => ...,
            ],
        ],
    ],
]
```

**OPEN** — Trong cuộc trao đổi hiện tại chưa đọc source `TableDAUNormalizer` đang nằm trong repository. Exact constructor, status constants, schema class và edge cases phải được xác minh.

---

## C.7. RequestNormalizer input contract

**CONFIRMED về conceptual input**:

```text
Request
+
RequestSchema
```

**OPEN về prototype**.

Chưa chốt:

```php
new RequestNormalizer(
    Request $request,
    array $arrRequestSchema
)
```

hay:

```php
new RequestNormalizer(
    Request $request,
    RequestSchema $requestSchema
)
```

hay một static API.

Không tự quyết định trong DKT này.

---

## C.8. RequestNormalizer output contract

**PROPOSED, chưa có quyết định cuối cùng**.

Dựa trên contract hiện tại của aggregate normalizer, ứng viên mạnh nhất:

```php
[
    'status' => ...,

    'data' => [
        'segments' => ...,
        'query'    => ...,
        'post'     => ...,
        'json'     => ...,
    ],

    'extra' => [
        // sparse error tree
    ],
]
```

Lý do:

- `RequestNormalizer` xử lý một cấu trúc aggregate;
- `JsonNormalizer` và thiết kế `TableDAUNormalizer` đã dùng family `status/data/extra`;
- không cần tạo một error representation hoàn toàn mới.

Tuy nhiên output này **chưa được người dùng chốt cuối cùng**. Câu hỏi hiện tại trước khi chuyển giao chính là lựa chọn output phù hợp cho `RequestNormalizer`.

---

## C.9. Preserve source trong normalized data

**PROPOSED, có lý do mạnh nhưng chưa chốt cuối cùng**.

Không merge sớm:

```php
[
    'id' => ...,
    'page' => ...,
]
```

Thay vào đó giữ:

```php
[
    'segments' => [...],
    'query'    => [...],
    'post'     => [...],
    'json'     => [...],
]
```

Lý do:

```text
query[id]
post[id]
json[id]
```

có thể cùng tồn tại và không phải cùng một datum.

Argument binding nên là concern riêng ở tầng sau nếu chưa có quyết định khác.

---

## C.10. Request error tree

**PROPOSED**.

Có thể nâng error tree hiện có thêm root source:

```php
[
    'segments' => [
        5 => [$errNumber, $inputValue],
    ],

    'query' => [
        'page' => [$errNumber, $inputValue],
    ],

    'post' => [
        'email' => [$errNumber, $inputValue],
    ],

    'json' => [
        'table1' => [
            'add' => [
                3 => [
                    'age' => [$errNumber, $inputValue],
                ],
            ],
        ],
    ],
]
```

Hệ tọa độ lỗi:

```text
source + path
```

Ưu điểm:

- giữ nguyên nested-path semantics của `JsonNormalizer`;
- không cần list phẳng chứa `source/path/code/value`;
- dễ gắn atomic error vào đúng source;
- dễ chuyển JSON/Table error tree vào request-level error tree.

Chưa chốt cuối cùng.

---

# D. Algorithms

## D.1. RequestNormalizer — flow tổng quát

**PROPOSED**.

```text
1. Validate RequestSchema/configuration
2. Với từng root source được khai báo:
   2.1. lấy dữ liệu từ Request/context
   2.2. xác định schema type
   2.3. dispatch tới normalizer phù hợp
   2.4. lưu normalized/partially normalized data
   2.5. merge lỗi vào request-level error tree dưới source tương ứng
3. Tạo aggregate status
4. Trả status/data/extra
```

RequestNormalizer không nên duyệt JSON leaf trực tiếp.

---

## D.2. Atomic source (`segments`, `query`, `post`)

**PROPOSED dựa trên các quyết định hiện có**.

Với mỗi declared field:

```text
read raw value
    ↓
AtomicNormalizer::normalize(rawValue, atomicSchema)
    ↓
ERR_NONE?
    ├── yes → commit normalized value
    └── no  → giữ error tại source/path
```

Exact semantics khi field bị thiếu **OPEN**.

Không được giả định:

```text
missing == null
```

nếu RequestSchema chưa chốt quy tắc đó.

---

## D.3. JSON source

**CONFIRMED về phân tầng, OPEN về exact RequestSchema syntax**.

```text
Request json data
    ↓
JsonNormalizer
    ↓
N steps
    ↓
AtomicNormalizer/composite processing
    ↓
aggregate status/data/extra
```

RequestNormalizer chỉ orchestration.

Không được hiểu nội dung `defineLeafFn`, `processLeafFn`, ExtArray topology ở RequestNormalizer.

---

## D.4. Table data

**CONFIRMED về phân công trách nhiệm**.

Structured table DAU được xử lý bởi `TableDAUNormalizer`, không phải bằng logic table viết trực tiếp trong `RequestNormalizer`.

Exact dispatch path giữa:

```text
RequestNormalizer
JsonNormalizer
TableDAUNormalizer
```

cần đọc source hiện tại trước khi chốt.

Có thể TableDAUNormalizer được gọi từ JSON configuration hoặc từ một schema node `type='table'`; exact mechanism hiện **OPEN**.

---

## D.5. Error handling

### Data error

**PROPOSED nhất quán với normalizer hiện có**:

```text
invalid user input
    → aggregate normalization error
    → đưa vào extra
```

### Programming/configuration error

**CONFIRMED về nguyên tắc từ AtomicNormalizer/JsonNormalizer**:

```text
invalid schema
invalid callback contract
unknown configuration
internal impossible state
    → exception
```

Không biến programming/configuration error thành user-input error.

---

## D.6. Partial success

**PROPOSED cho RequestNormalizer, đã có tiền lệ ở JsonNormalizer**.

Một source/field normalize thành công không nhất thiết rollback vì một sibling source/field lỗi.

Ví dụ:

```text
query.page  → success
query.limit → error
json.x      → success
```

có thể trả:

```text
data = partially normalized request data
extra = errors
status = error
```

Exact transactional semantics ở request level chưa được chốt, nhưng phù hợp với semantics JsonNormalizer hiện có.

---

## D.7. Missing source / missing field

**OPEN**.

Chưa chốt các câu hỏi:

- RequestSchema khai báo `query.page` nhưng request không có `page` thì sao?
- Required/optional nằm trong AtomicSchema hay RequestSchema wrapper?
- Có truyền `null` vào AtomicNormalizer không?
- Có phân biệt key absent với explicit `null` không?
- Nếu toàn root source vắng mặt thì lỗi root hay field-level?
- JSON body không có / parse failure nằm ở Request hay RequestNormalizer?

Codex không được tự quyết định trước khi review source và thiết kế tiếp.

---

# E. Confirmed Design Decisions

## E.1. RequestSchema tổ chức theo source

**CONFIRMED**.

Root chính đã bàn:

```text
segments
query
post
json
```

### Lý do

Các source có semantics khác nhau và có thể có key trùng nhau.

### Ảnh hưởng

RequestNormalizer phải biết source extraction nhưng không được merge mất source identity quá sớm.

---

## E.2. Query/Post/Segments chủ yếu là atomic

**CONFIRMED**.

### Lý do

Đây là các trường đơn giản phù hợp với `AtomicSchema` / `AtomicNormalizer`.

Structured data phức tạp được ưu tiên đưa vào JSON.

---

## E.3. Table DAU ưu tiên JSON thay vì form POST

**CONFIRMED**.

### Lý do

Table DAU có cấu trúc tự nhiên:

```text
add
update
delete
```

JSON biểu diễn rõ hơn `$_POST` array conventions.

### Ảnh hưởng

`TableDAUNormalizer` được đặt trong luồng structured JSON, không phải form-field normalization.

---

## E.4. Không dùng traditional recursive JSON Schema

**CONFIRMED**.

### Lý do loại bỏ

UDA sẽ phải mô tả lại gần như toàn bộ topology JSON:

- verbose;
- khó bảo trì;
- khó dynamic key;
- khó table/list;
- duplicate knowledge giữa business structure và schema.

### Thiết kế hiện hành

JSON được normalize bằng `JsonNormalizer` qua một hoặc nhiều step dựa trên `ExtArray`.

---

## E.5. `type` là discriminator chung

**CONFIRMED**.

Không thêm discriminator dư thừa `schema_type`.

### Lý do

```php
'type' => 'int'
```

đã đủ nói đây là atomic schema.

```php
'type' => 'json'
```

đã đủ nói đây là JSON processing configuration.

---

## E.6. RequestNormalizer là orchestration layer

**CONFIRMED về trách nhiệm**.

### Không làm

- atomic conversion;
- ExtArray traversal;
- table DAU validation details;
- HTTP response rendering.

### Lý do

Giữ single responsibility và tái sử dụng normalizer chuyên biệt.

---

## E.7. `isValid(): bool` không đủ làm primary normalization contract

**CONFIRMED trong quá trình thiết kế normalization trước đó**.

Primary flow cần:

```text
normalize
    → normalized value/data
    + detailed errors
```

Boolean-only validation không đủ cho:

- coercion;
- canonicalization;
- path-aware error;
- partial normalization;
- complex JSON.

`isValid()` nếu tồn tại chỉ nên là convenience API ở nơi phù hợp.

---

## E.8. Configuration error khác data error

**CONFIRMED**.

Schema/callback/programming error phải được báo bằng exception, không bị nuốt thành user-input error.

Đây là pattern đã thấy rõ ở AtomicNormalizer/JsonNormalizer.

---

# F. Superseded / Rejected Designs

## F.1. Traditional recursive JsonSchema

**SUPERSEDED / REJECTED**.

Dạng kiểu:

```php
[
    'type' => 'json',
    'fields' => [
        'user' => [
            'type' => 'json',
            'fields' => [
                // ...
            ],
        ],
    ],
]
```

không còn là hướng mặc định.

### Thay thế

`JsonNormalizer` + `ExtArray` + N processing steps.

---

## F.2. Dùng `post` để truyền table DAU

**REJECTED cho hướng mặc định**.

PHP có thể biểu diễn array qua form naming, nhưng không phải transport ưu tiên cho cấu trúc DAU phức tạp.

### Thay thế

JSON body.

---

## F.3. Thêm `schema_type = atomic`

**REJECTED / không cần thiết**.

### Thay thế

Dùng trực tiếp field:

```php
'type'
```

làm discriminator.

---

## F.4. Merge mọi request source thành một flat array ngay trong normalization

**Không được chọn làm hướng hiện tại**.

Nguy cơ collision:

```text
query[id]
post[id]
json[id]
```

### Hướng hiện tại được đề xuất

Giữ source boundary ít nhất tới sau RequestNormalizer.

---

## F.5. RequestNormalizer trả chỉ `true/false`

**REJECTED về mặt kiến trúc**.

Không đủ cho normalized data và detailed error.

---

## F.6. RequestNormalizer trả một `RequestNormalizeResult` object riêng

**SUPERSEDED AS CURRENT PREFERENCE / chưa được chốt thành contract**.

Trong một giai đoạn trước đã đề xuất object:

```text
RequestNormalizeResult
    isOk()
    getData()
    getErrors()
```

Sau khi đối chiếu output hiện có của `JsonNormalizer` và thiết kế `TableDAUNormalizer`, hướng gần nhất chuyển sang cân nhắc reuse family:

```php
[
    'status' => ...,
    'data'   => ...,
    'extra'  => ...,
]
```

Không coi result-object là thiết kế hiện hành trừ khi Codex/thảo luận mới quyết định quay lại.

---

## F.7. Gắn normalization result trực tiếp với HTTP `Response`

**REJECTED về phân tầng**.

Normalization subsystem không nên biết HTTP status, redirect, output rendering.

`status` của normalizer nếu có là subsystem status, không phải `Core\Http\Response` status.

---

# G. Implementation Status

## G.1. Những gì người dùng cho biết đã hình thành

**USER-REPORTED, chưa kiểm tra trực tiếp repo trong phiên này**:

Trong `Core\Normalization` hiện đã có:

```text
AtomicNormalizer
JsonNormalizer
TableDAUNormalizer
```

Ngoài ra các subsystem hỗ trợ đã được phát triển trong các cuộc làm việc trước:

```text
AtomicSchema
ExtArray
```

---

## G.2. Những gì đã đọc được trong artifact

### AtomicNormalizer

Đã đọc artifact có namespace:

```php
namespace Core\Normalization;
```

và output mới hơn:

```php
[
    'err_number' => int,
    'value'      => mixed,
]
```

### JsonNormalizer

Đã đọc artifact lịch sử có namespace:

```php
namespace Core\Data\Normalization;
```

và output:

```php
[
    'status' => 'ok' | 'normalization_error',
    'data'   => array,
    'extra'  => array,
]
```

Namespace này có thể đã cũ.

### TableDAUNormalizer

Trong phiên này chỉ đọc được tài liệu DKT, không đọc implementation source hiện tại.

---

## G.3. Chưa hình thành trong cuộc trao đổi hiện tại

Theo chính mục tiêu hiện tại, còn cần hình thành:

```text
RequestSchema
RequestNormalizer
```

Không có bằng chứng trong cuộc trao đổi này rằng hai class trên đã được triển khai.

---

## G.4. Những điểm Codex phải kiểm tra lại bằng source hiện tại

1. Namespace thực tế:
   ```text
   Core\Normalization
   vs
   Core\Data\Normalization
   ```

2. Source hiện tại của:
   ```text
   AtomicSchema
   AtomicNormalizer
   JsonNormalizer
   TableDAUNormalizer
   ExtArray
   Request
   RequestAuthContext
   ```

3. `JsonNormalizer` marker types sau các thay đổi ExtArray.

4. Có `DISCARDED_LEAF` hay không và semantics thực tế.

5. `TableDAUNormalizer::normalize()` output/status chính xác.

6. Request API để lấy:
   ```text
   query
   post
   json
   files
   route/segments
   ```

7. `arrMCAO` hiện thuộc object nào.

8. AtomicSchema hiện có field required/optional hay không.

9. Có class result chung hoặc Response helper nào trong `Core\Normalization` chưa.

10. Quy ước constant/status/error code toàn subsystem.

---

# H. Open Issues

## H.1. Exact public API của `RequestSchema`

**OPEN**.

Chưa chốt:

- chỉ là array contract;
- hay class có constants + validation;
- constructor;
- `isValid()`;
- factory;
- schema normalization.

---

## H.2. Exact public API của `RequestNormalizer`

**OPEN**.

Concept đã chốt:

```text
Request + RequestSchema → normalize
```

Nhưng chưa chốt prototype.

---

## H.3. Exact output của `RequestNormalizer`

**OPEN — vấn đề đang được xem xét ngay trước khi chuyển giao**.

Ứng viên hiện mạnh nhất:

```php
[
    'status' => ...,
    'data'   => ...,
    'extra'  => ...,
]
```

với `data` giữ source boundary và `extra` là source-rooted sparse error tree.

Cần quyết định sau khi đọc source hiện tại của các aggregate normalizer.

---

## H.4. Status constants dùng chung hay riêng từng normalizer

**OPEN**.

Có thể:

```text
JsonNormalizer::STATUS_OK
JsonNormalizer::STATUS_ERROR
```

được tái dùng conceptually, hoặc cần constants chung.

Không tự tạo base class/trait/constants mới nếu chưa cần.

---

## H.5. Missing field semantics

**OPEN**.

Đặc biệt:

- absent vs null;
- required;
- optional;
- default;
- source không tồn tại;
- empty JSON.

---

## H.6. `segments` lấy từ đâu

**OPEN**.

Lịch sử nói:

```php
$requestAuthContext->arrMCAO
```

Nhưng thiết kế mới muốn `RequestNormalizer` nhận `Request`.

Phải xác định có cần:

- mở rộng Request;
- truyền RequestAuthContext;
- hay dùng một input-context abstraction khác.

---

## H.7. JsonSchema configuration trong RequestSchema

**OPEN**.

Đã chốt **không** mô tả topology truyền thống.

Chưa chốt exact representation của:

```text
N JsonNormalizer steps
defineLeafFn
processLeafFn
TableDAUNormalizer integration
```

---

## H.8. Table schema integration

**OPEN**.

Dù `'type' => 'table'` đã được dùng như discriminator conceptually, cần xác định:

- schema node table nằm ở đâu;
- RequestNormalizer hay JsonNormalizer dispatch;
- TableDAUNormalizer nhận schema gì;
- lỗi TableDAU được merge ra sao.

---

## H.9. Files/upload

**OPEN**.

Chưa thiết kế.

---

## H.10. Argument binding

**OPEN nhưng nên tách khỏi RequestNormalizer cho tới khi có quyết định khác**.

Trước đây từng có ý tưởng:

```php
argumentSchemaForFunction(...)
```

với mapping source như:

```php
'source' => ['segments', 0]
```

Nhưng Request normalization nên được chốt trước.

---

# I. Compatibility and Dependencies

## I.1. PHP/runtime

PBT trước đây được thiết kế cho PHP 8.0.x.

Codex phải kiểm tra target runtime hiện tại trước khi dùng API PHP mới hơn.

Không dùng API PHP 8.1+ nếu project vẫn target PHP 8.0.13.

---

## I.2. AtomicNormalizer contract cần bảo toàn

RequestNormalizer không nên hard-code field lịch sử `'errNumber'`.

Ưu tiên dùng:

```php
AtomicNormalizer::FIELD_ERR_NUMBER
AtomicNormalizer::FIELD_VALUE
AtomicNormalizer::ERR_NONE
```

nếu source hiện tại vẫn cung cấp.

---

## I.3. JsonNormalizer error tree

Nếu source hiện tại vẫn giữ sparse nested tree:

```php
[$errNumber, $inputValue]
```

RequestNormalizer nên tránh flatten nếu không có lý do mạnh.

Flatten sẽ làm mất tính đồng nhất với JsonNormalizer.

---

## I.4. Partial commit semantics

Nếu JsonNormalizer/TableDAUNormalizer đang trả partially normalized data khi có lỗi, RequestNormalizer phải quyết định nhất quán:

- preserve partial data;
- hay rollback tại request level.

Hướng được đề xuất là preserve partial data, nhưng chưa chốt.

---

## I.5. ExtArray

RequestNormalizer không nên trực tiếp phụ thuộc marker/topology ExtArray.

Nếu RequestNormalizer cần biết `EXCLUDED`, `COMPOSITE_LEAF`, `BRANCH_OR_PETIOLE`, đó là dấu hiệu abstraction boundary đang bị rò.

---

## I.6. Namespace migration

Cần đặc biệt kiểm tra:

```text
Core\Normalization\JsonNormalizer
```

so với artifact lịch sử:

```text
Core\Data\Normalization\JsonNormalizer
```

Không copy namespace cũ sang code mới nếu repo đã chuyển.

---

# J. Test Scenarios

## J.1. Test đã được xác nhận trong cuộc trao đổi

**Không có runtime test nào của RequestNormalizer được xác nhận**, vì subsystem này chưa được triển khai trong cuộc trao đổi hiện tại.

Các test JsonNormalizer/AtomicNormalizer trong tài liệu cũ không được coi là test RequestNormalizer.

---

## J.2. Test nên bổ sung khi triển khai — PROPOSED

### Atomic query success

Input:

```text
?page=2
```

Schema:

```php
'query' => [
    'page' => ['type' => 'int']
]
```

Expected:

- `page` canonical int;
- không error.

---

### Atomic query conversion error

Input:

```text
?page=abc
```

Expected:

- aggregate status error;
- error dưới:
  ```text
  extra.query.page
  ```
- input error được bảo toàn theo AtomicNormalizer contract.

---

### Integer segment key

Schema:

```php
'segments' => [
    5 => ['type' => 'int']
]
```

Expected:

- integer key không bị reindex/sai path.

---

### Trùng field name giữa sources

Input:

```text
query.id
post.id
json.id
```

Expected:

- ba value không ghi đè nhau nếu output preserve source boundary.

---

### JSON nested error

JsonNormalizer trả error path:

```text
table.add.3.age
```

Expected request-level path:

```text
json.table.add.3.age
```

nếu source-rooted error tree được chốt.

---

### Partial success giữa sources

Ví dụ:

```text
query.page  success
post.email  error
json.data   success
```

Kiểm tra semantics `data` khi aggregate status error.

---

### Invalid RequestSchema

Ví dụ:

```php
'type' => 'unknown'
```

Expected:

- exception configuration;
- không biến thành normal input error.

---

### Empty RequestSchema

Cần quyết định:

- success với empty data;
- hay invalid configuration.

Đây là test để buộc contract được chốt.

---

### Missing field

Schema có field nhưng request không có.

Cần test sau khi chốt required/optional semantics.

---

### Explicit null vs absent

Nếu source hỗ trợ null:

- key absent;
- key present với null.

Phải phân biệt nếu contract yêu cầu.

---

### JsonNormalizer exception propagation

Nếu callback/configuration JsonNormalizer throw:

- RequestNormalizer không được nuốt thành user-data error trừ khi có contract rõ.

---

### TableDAUNormalizer integration

Test:

```text
json
└── table node
    ├── add
    ├── update
    └── delete
```

với cả success và field error.

Exact setup phụ thuộc source TableDAUNormalizer hiện tại.

---

### Files

Chỉ bổ sung khi `FileSchema/FileNormalizer` được thiết kế.

---

# K. Next Steps

## K.1. Việc đầu tiên trong Codex

Đọc source hiện tại của:

```text
Core\Normalization\AtomicSchema
Core\Normalization\AtomicNormalizer
Core\Normalization\JsonNormalizer
Core\Normalization\TableDAUNormalizer
Core\Foundation\ExtArray
Request
RequestAuthContext
```

và các DKT liên quan.

Không code RequestNormalizer trước khi hoàn thành bước đối chiếu.

---

## K.2. Chốt output family

Câu hỏi đầu tiên cần giải quyết:

> `RequestNormalizer` có chính thức dùng aggregate result `status/data/extra` giống `JsonNormalizer` / `TableDAUNormalizer` hay không?

Nếu có, cần chốt:

- constants;
- status semantics;
- partial data semantics;
- error tree semantics.

---

## K.3. Chốt RequestSchema validation

Sau khi biết API các normalizer hiện tại:

1. định nghĩa constants của root source;
2. định nghĩa discriminator `type`;
3. xác định source nào chỉ atomic;
4. xác định JSON configuration representation;
5. xác định table integration;
6. quyết định missing/required semantics.

---

## K.4. Chốt RequestNormalizer extraction contract

Đặc biệt:

```text
segments / arrMCAO
```

Phải xác định source abstraction trước khi viết constructor.

---

## K.5. Chốt error aggregation

Ứng viên hiện tại:

```text
source + nested path
```

Ví dụ:

```php
[
    'query' => [
        'page' => [$errNumber, $inputValue],
    ],

    'json' => [
        'table' => [
            'add' => [
                3 => [
                    'age' => [$errNumber, $inputValue],
                ],
            ],
        ],
    ],
]
```

Cần quyết định chính thức.

---

## K.6. Sau khi contract ổn định mới triển khai

Thứ tự đề nghị cho phiên Codex:

```text
1. Source review
2. RequestSchema contract
3. RequestNormalizer input/output contract
4. Missing-data semantics
5. Dispatch algorithm
6. Error aggregation
7. Tests
8. Implementation
9. Integration review với Controller/argument binding
```

---

# Tóm tắt baseline để bắt đầu phiên Codex

Thiết kế hiện tại có thể được nhớ bằng sơ đồ:

```text
Request
  +
RequestSchema
  │
  ▼
RequestNormalizer
  │
  ├── segments ──→ AtomicNormalizer
  ├── query    ──→ AtomicNormalizer
  ├── post     ──→ AtomicNormalizer
  └── json     ──→ JsonNormalizer
                      │
                      ├── AtomicNormalizer
                      ├── composite UDA processing
                      └── TableDAUNormalizer khi phù hợp
  │
  ▼
aggregate normalization result
```

Quyết định nền:

```text
- RequestSchema phân theo source.
- Atomic dùng AtomicSchema/AtomicNormalizer.
- Complex structured input ưu tiên JSON.
- Không dùng traditional recursive JSON Schema.
- JsonNormalizer/ExtArray xử lý JSON qua processing steps.
- type là discriminator chung.
- RequestNormalizer là orchestration layer.
- Programming/configuration error khác user-data error.
```

Điểm chưa chốt quan trọng nhất:

```text
- exact RequestSchema API
- exact RequestNormalizer API
- exact RequestNormalizer output
- missing/required semantics
- segments source abstraction
- exact Json/Table integration
- files/upload
- argument binding layer
```

**Không được coi các mục PROPOSED/OPEN ở trên là quyết định đã triển khai. Codex phải đối chiếu source hiện tại trước khi tiếp tục thiết kế hoặc code.**
