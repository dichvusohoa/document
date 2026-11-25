# JsonNormalizer — Chuyển giao tri thức thiết kế PBT Framework

Ngày tổng hợp: 2026-10-18. Ngôn ngữ giải thích: tiếng Việt. Tài liệu chuyển giao thiết kế, không phải yêu cầu viết lại subsystem.

## Cách đọc và giới hạn bằng chứng

Các nhãn dùng xuyên suốt:

- **CONFIRMED**: người dùng đã thống nhất hoặc yêu cầu rõ trong cuộc trao đổi.
- **PROPOSED**: phương án do AI đề xuất; chưa có xác nhận riêng đủ rõ để coi là quyết định thiết kế cuối cùng.
- **OPEN**: chưa giải quyết, chưa đủ bằng chứng kết luận hoặc cần đối chiếu source hiện tại.
- **SUPERSEDED**: phương án cũ đã được thay thế.

Phân biệt hai trục: mức xác nhận thiết kế và bằng chứng triển khai. Một cơ chế có trong bản code sinh từ chat vẫn có thể là **PROPOSED** về mặt thiết kế.

Nguồn tổng hợp chính là cuộc hội thoại JsonNormalizer đang hiển thị, gồm bản tóm lược yêu cầu đầu cuộc trao đổi và các lần review sau đó. Có đối chiếu toàn bộ bản `JsonNormalizer.php` đã tạo trong cuộc hội thoại, phiên bản 2, cập nhật 2026-10-04, 372 dòng, 16.734 byte. Đây là **bản artifact sinh từ chat**, không phải source hiện tại trong repository Windows của người dùng. Khi dùng tài liệu, Codex phải đọc source thực tế và xác định khác biệt; không chép đè source dựa vào tài liệu này.

Chưa kiểm chứng source mới nhất của `ExtArray`, `AtomicNormalizer`, `AtomicSchema`, `Response` trong repository ở thời điểm bàn giao. Những thay đổi ExtArray sau baseline có thể làm các prototype và marker trong bản JsonNormalizer cũ không còn tương thích. Các cuộc chat riêng về ExtArray/AtomicNormalizer không được xem là đã đọc đầy đủ ở lần tổng hợp này. Không suy đoán các quyết định chưa xuất hiện trong nội dung khả dụng.

## A. Overview

### A.1. Mục đích và phạm vi

**CONFIRMED** — `JsonNormalizer` thuộc namespace `Core\Data\Normalization`, xử lý JSON đã biểu diễn thành PHP array phức tạp thông qua nhiều normalization step. Nó dùng `Core\Foundation\ExtArray` làm engine traversal.

Các step chạy tuần tự trên cùng cây làm việc. Step sau nhìn thấy các normalized value được commit thành công ở step trước. Khi thích hợp, xây pipeline từ fine-grained đến coarse-grained: atomic field → composite object/row → collection.

Chức năng chính:

- Chọn leaf theo từng step bằng `defineLeafFn`.
- Framework tự normalize atomic leaf qua `AtomicNormalizer` và `AtomicSchema`.
- Gọi callback UDA để normalize/validate composite leaf.
- Commit từng unit thành công; giữ input của unit nếu lỗi dữ liệu.
- Tích lũy lỗi theo cây nested và tránh xử lý dây chuyền tại các unit phụ thuộc dữ liệu đã lỗi.
- Tiếp tục xử lý các subtree độc lập chưa lỗi.

UDA trong tài liệu là code của Application/Controller cung cấp callback.

### A.2. Vị trí trong Framework

**CONFIRMED** — Đích tích hợp về sau là nhánh `['type' => 'json', ...]` của `RequestSchema`. `JsonSchema` dự kiến là configuration mỏng mô tả các step; không phải JSON Schema truyền thống mô tả lại toàn bộ topology JSON.

Việc thiết kế `RequestSchema` được hoãn đến khi JsonNormalizer đủ rõ. Không đưa thiết kế RequestSchema chưa thống nhất vào contract hiện tại. TableNormalizer/TableDAUNormalizer là bên có thể tái sử dụng sau này, không phải nghiệp vụ nằm trong JsonNormalizer.

Bản artifact nhận PHP array đã decode, không parse chuỗi JSON và không tự phát hiện field không tồn tại. Những giới hạn này có trong code/comment nhưng chưa có thiết kế đầy đủ cho JSON scalar root hoặc missing-field synthesis.

## B. Architecture

### B.1. Thành phần và trách nhiệm

| Thành phần | Trách nhiệm | Mức xác nhận |
|---|---|---|
| `JsonNormalizer` | Điều phối steps, adapter, commit, error tree, skip, response | CONFIRMED |
| `ExtArray` | Traversal, phân loại topology BRANCH/PETIOLE/LEAF/EXCLUDED, đưa leaf theo reference cho adapter | CONFIRMED ở baseline |
| `AtomicSchema` | Mô tả và kiểm tra configuration của atomic type | CONFIRMED; chi tiết xem source riêng |
| `AtomicNormalizer` | Normalize/validate atomic value và trả result/error | CONFIRMED; không viết lại logic này trong JsonNormalizer |
| UDA `defineLeafFn` | Chọn topology/leaf cho từng step | CONFIRMED |
| UDA `processLeafFn` | Xử lý composite theo giá trị, trả normalized candidate hoặc mã lỗi | CONFIRMED |
| Adapter chọn node `$select` | Chặn node đã lỗi, gọi define của UDA, kiểm tra kết quả định nghĩa | Đã có trong artifact |
| Adapter xử lý `$process` / `processUnit()` | Atomic/composite dispatch, guard lỗi, candidate isolation, commit/error | Đã có trong artifact |
| `$arrError` | Cây lỗi thưa theo path | CONFIRMED |

### B.2. Hai tầng callback phải phân biệt

UDA callback không được đăng ký trực tiếp làm processing callback của ExtArray. JsonNormalizer tạo adapter đáp ứng prototype của engine. Nhờ đó UDA không cần tự gọi AtomicNormalizer hay tự commit bằng reference.

Bản artifact có hai lớp closure quanh mỗi adapter: closure bên trong giữ scope JsonNormalizer; closure mỏng bên ngoài được ExtArray bind lại. Không được rút gọn tùy tiện thành closure truy cập private member sau khi bị rebind.

`addStep()` dùng dispatch closure để hỗ trợ define một hoặc hai tham số mà vẫn giữ binding `$this` gốc của Controller, kể cả khi UDA cung cấp static closure. Việc bind closure của ExtArray không được làm đổi `$this` mà callback UDA dự kiến sử dụng.

## C. Current Design Contracts

### C.1. Public API

**CONFIRMED** — Cấu trúc đăng ký qua constructor và fluent `addStep()`:

```php
public function __construct(
    array $arrData,
    array $arrStepNormalization = []
);

public function addStep(
    Closure $defineLeafFn,
    ?Closure $processLeafFn = null
): self;

public function normalize(): array;
```

Tham số optional steps trong constructor là hình thức bản artifact đã triển khai. Mỗi phần tử step là đúng một cặp:

```php
[
    0 => $defineLeafFn,  // Closure
    1 => $processLeafFn, // Closure|null
]
```

Bản artifact yêu cầu array steps là list key liên tiếp `0..n-1`; cặp phải có đúng hai phần tử và key `0`, `1`. Constructor không chấp nhận cặp thiếu key `1`; nếu atomic-only thì truyền `null`. `addStep($defineLeafFn)` tự bổ sung null.

```php
$jsonNormalizer = (new JsonNormalizer($arrData))
    ->addStep($defineAtomicFn)
    ->addStep($defineRowFn, $processRowFn)
    ->addStep($defineCollectionFn, $processCollectionFn);

$arrResponse = $jsonNormalizer->normalize();
```

**CONFIRMED** — Thứ tự gọi `addStep()` chính là thứ tự chạy; hàm append step và trả `$this`. Không viết cơ chế đặc thù chỉ hỗ trợ đúng ba steps.

### C.2. Input, ownership và root

Bản artifact dùng `array $arrData` by-value; bên trong có `$this->arrData`, truyền reference của property ấy cho một ExtArray duy nhất. Không truyền input của Controller by-reference qua public API.

**Đã thể hiện trong artifact; chưa có xác nhận riêng cho mọi giới hạn**:

- Root phải là array; composite xử lý root thành công cũng phải trả array.
- Cho phép array, null, bool, int, string, finite float.
- Từ chối object, resource, NaN/infinity và PHP reference trong input/kết quả composite thành công.
- Giới hạn độ sâu `MAX_DEPTH = 128`.
- Dữ liệu ban đầu của Controller không bị mutate bởi engine trong contract callback tin cậy.

Không đồng nhất PHP string với string JSON UTF-8 hợp lệ: helper không kiểm tra UTF-8. Đây không phải bộ kiểm tra đầy đủ mọi điều kiện của `json_encode()`.

### C.3. Constants trong bản artifact

| Symbol | Giá trị | Phạm vi |
|---|---|---|
| `FIELD_ERR_NUMBER` | `'err_number'` | Key result UDA |
| `FIELD_VALUE` | `'value'` | Key result UDA |
| `STATUS_OK` | `'ok'` | Status nội bộ subsystem trong artifact |
| `STATUS_ERROR` | `'normalization_error'` | Status có lỗi dữ liệu |
| `MAX_DEPTH` | `128` | Guard độ sâu |
| `UDA_ERR_MIN` | `1000` | Ranh giới mã UDA trong artifact |
| `READY`, `RUNNING`, `DONE`, `FAILED` | `0`, `1`, `2`, `3` | State lifecycle private |
| `NO_ERROR`, `BLOCKED`, `DESCENDANT_ERROR` | `0`, `1`, `2` | State lỗi private |

Các tên/status trên kiểm chứng được trong artifact. Việc ánh xạ sang constants của `Core\Http\Response` là **OPEN**. Không khẳng định `'ok'` là status chuẩn của PBT Response.

### C.4. `defineLeafFn`

**CONFIRMED ở baseline của cuộc chat** — Hỗ trợ một hoặc hai tham số by-value:

```php
function (mixed $value): array|int|string;
function (array $arrChain, mixed $value): array|int|string;
```

`$value` có thể không khai báo kiểu; return type có thể bỏ hoặc thu hẹp vào các loại được validator ExtArray chấp nhận. Callback chỉ đọc dữ liệu, không sửa tree qua reference/capture/global.

| Kết quả | Ý nghĩa |
|---|---|
| `ExtArray::EXCLUDED` | Bỏ node và toàn bộ subtree ở step hiện tại; giữ dữ liệu |
| Array atomic schema | Atomic leaf; Framework dùng AtomicNormalizer |
| `ExtArray::COMPOSITE_LEAF` | Composite leaf; dùng process callback của UDA |
| `ExtArray::BRANCH_OR_PETIOLE` | ExtArray tự phân biệt branch/petiole và tiếp tục traversal |

Không trả `true/false` như phương án cũ. Không bắt UDA tự phân biệt BRANCH với PETIOLE.

**OPEN — đồng bộ dependency:** baseline dùng marker composite/branch-or-petiole dạng string. Có thông tin bàn giao từ subsystem ExtArray rằng marker về sau đổi sang integer và có `DISCARDED_LEAF`; phải đọc ExtArray/source tài liệu của nó trước khi chốt prototype mới. Không coi `array|int|string` là API vĩnh viễn khi dependency đã thay đổi.

### C.5. UDA `processLeafFn`

**CONFIRMED về vai trò và prototype đang được review**:

```php
$udaProcessLeafFn = function (
    array $arrChain,
    mixed $value
): array {
    return [
        'err_number' => 0,
        'value' => $normalizedValue,
    ];
};
```

`validateUdaProcessLeafFn()` trong artifact yêu cầu:

- Chính xác hai tham số, cả hai bắt buộc.
- Tham số thứ nhất typed `array`, không nullable.
- Tham số thứ hai untyped hoặc `mixed`.
- Không by-reference, không variadic, không return-by-reference.
- Return type omitted hoặc non-nullable `array`.
- Tên biến tham số không có ý nghĩa hợp đồng; `$path` trong code tương đương `$arrChain`.

Return structure được kiểm tra ở runtime riêng, không thể kiểm tra bằng Reflection prototype. Bản artifact yêu cầu đúng hai key `err_number`, `value`; `err_number` là int không âm; success là `AtomicNormalizer::ERR_NONE` (baseline 0). `value` khi lỗi không được commit; luôn ghi input thực tế của processing unit vào error tree.

**PROPOSED, đã được đưa vào artifact theo lần yêu cầu sinh code sau thảo luận:** `1..999` dành cho Framework, UDA chỉ dùng `0` hoặc `>= UDA_ERR_MIN = 1000`. Adapter throw nếu UDA dùng mã dành riêng. Người dùng nêu vấn đề collision; phân vùng là giải pháp AI đề xuất và triển khai, chưa có xác nhận riêng rằng toàn Framework đã đăng ký/chuẩn hóa khoảng mã đó. Cần bảo đảm mã AtomicNormalizer hiện tại thực sự nằm trong vùng Framework. Phân vùng không ngăn hai callback UDA tự trùng mã; Application cần quản lý mã chung.

Không có tham số `$leafDefinition` thứ ba trong callback UDA của artifact hiện tại.

### C.6. Adapter đăng ký vào ExtArray

**CONFIRMED ở baseline** — Prototype processing adapter:

```php
function (
    array $arrChain,
    mixed &$value,
    array|string $leafDefinition
): void;
```

Reference dùng để commit normalized result vào cây. Tham số thứ ba đưa kết quả define sang processing mà không gọi define lần hai. Định nghĩa array → atomic; composite marker → UDA.

Việc ExtArray hỗ trợ callback processing một/hai/ba tham số giúp giữ tính tổng quát; không ép mọi consumer ExtArray phải nhận ba tham số. JsonNormalizer chọn mode ba tham số. Nếu marker ExtArray chuyển sang int, kiểu `array|string` này phải được đối chiếu và cập nhật tương ứng.

### C.7. Response và trạng thái lỗi một phần

**CONFIRMED về cấu trúc**:

```php
[
    'status' => /* success hoặc có lỗi dữ liệu */,
    'data'   => $normalizedOrPartiallyNormalizedData,
    'extra'  => $nestedErrors,
]
```

- Unit thành công được commit.
- Unit lỗi giữ input tại thời điểm lần xử lý đó; không phải bắt buộc raw input của toàn pipeline.
- Không rollback các step/unit đã thành công.
- `extra === []` biểu thị không có lỗi được ghi; không khẳng định mọi field đã được validate, vì có EXCLUDED hoặc không có step.
- Configuration/prototype sai có thể throw; không bị biến thành lỗi dữ liệu thông thường.

### C.8. Error tree

**CONFIRMED** — Cây lỗi nested, thưa, phản ánh path trong data. Không dùng danh sách phẳng chứa path/step/source.

```php
[
    'table' => [
        'add' => [
            3 => [
                'age' => [$err_number, $inputValue],
            ],
        ],
    ],
]
```

Error leaf là cặp numeric `[$err_number, $inputValue]`, khác associative result UDA. Root error có `extra = [$err_number, $rootInput]`. Mỗi path chỉ giữ lỗi đầu tiên theo guard hiện tại; không append nhiều lỗi cho cùng node qua nhiều step.

**Invariant thuật toán artifact:** node trong error tree hoặc là error leaf, hoặc là branch chứa các array con; không có nhánh rỗng được giữ lại và không đồng thời chứa lỗi tại chính node lẫn hậu duệ. Nhờ đó nhận diện error leaf bằng `isset($node[0]) && is_int($node[0])` không nhầm với nhánh numeric key 0 (giá trị của key 0 trong branch luôn là array).

## D. Algorithms

### D.1. Đăng ký step

1. Constructor kiểm tra input và khởi tạo ExtArray từ working data.
2. Với từng step trong constructor: kiểm tra list/pair bằng `validateStepClosurePair()`, rồi gọi `addStep()`.
3. `addStep()` chỉ cho phép state READY; validate define prototype qua `definitionArgMode()` và UDA process prototype nếu có.
4. Tạo dispatch thống nhất define `(path, value)` một lần, append pair, trả `$this`.

`validateStepClosurePair()` chỉ kiểm tra shape/type Closure, không kiểm tra prototype. Tách trách nhiệm này là có chủ ý để không validate prototype hai lần.

### D.2. `normalize()`

1. DONE → trả cached response. RUNNING/FAILED → throw LogicException. READY → RUNNING.
2. Lặp steps theo thứ tự đăng ký, trên cùng ExtArray.
3. Tạo select adapter: nếu error state BLOCKED thì EXCLUDED; nếu không thì gọi define và kiểm tra kết quả hợp lệ.
4. Tạo process adapter: forward `(path, &value, definition)` tới `processUnit()`.
5. Set hai adapter vào ExtArray, gọi `traverseTree()`.
6. Sau tất cả steps: detach internal references còn lại trong data và error; tạo response, chuyển DONE và trả kết quả.
7. Bắt Throwable: chuyển FAILED, rethrow. `finally` release ExtArray và clear step closures.

State machine/caching/khóa clone là biện pháp của artifact do AI thêm; chưa có xác nhận riêng yêu cầu public API phải luôn giữ toàn bộ lifecycle này. Không chạy lại failed instance trên cây đã commit dở.

### D.3. `processUnit()`

1. `errorStateAt(path) !== NO_ERROR` → return, không chạy atomic/UDA.
2. Definition là array → gọi `AtomicNormalizer::normalize(value, schema)`.
3. Definition là COMPOSITE_LEAF → yêu cầu process callback khác null, detach reference từ subtree thành candidate, gọi UDA by-value.
4. Kiểm tra result UDA shape, kiểu/range mã lỗi.
5. Nếu lỗi dữ liệu: bỏ candidate, snapshot input thực tế, `recordError()`, không commit.
6. Nếu UDA success: từ chối reference ở slot result `value`, validate subtree result trước commit; root vẫn phải array.
7. Gán trực tiếp `$value = $normalized`; không gọi lại `setValue()` hoặc tra path để commit.

Atomic logic không được sao chép vào adapter. Schema atomic/configuration sai được phép throw `InvalidArgumentException` theo dependency contract.

### D.4. `errorStateAt()` và skip

| Kết quả | Ý nghĩa |
|---|---|
| `NO_ERROR` | Không lỗi ở chính node, tổ tiên hoặc hậu duệ |
| `BLOCKED` | Chính node hoặc tổ tiên là error leaf |
| `DESCENDANT_ERROR` | Đích là nhánh trong error tree, bên dưới chắc chắn có lỗi |

Hàm duyệt error tree theo path. Cây lỗi rỗng → NO_ERROR. Đang đi mà gặp error leaf → BLOCKED. Thiếu key trên đường đi → NO_ERROR. Đến đích: error leaf → BLOCKED, branch → DESCENDANT_ERROR.

Hai guard khác nhau:

- Select chỉ prune BLOCKED. Khi có descendant error, vẫn cho define quyết định tiếp tục traversal xuống các nhánh độc lập.
- Process chỉ xử lý NO_ERROR. Composite chứa descendant error bị skip cả processing unit.

Nếu define chọn một ancestor có lỗi hậu duệ thành composite leaf, step đó dừng traversal ở leaf ấy rồi skip processing; không tự đi sâu trở lại để xử lý sibling. Muốn xử lý sibling trong step đó thì define phải tiếp tục coi ancestor là branch. Đây là hệ quả cần giữ rõ khi viết UDA.

Ví dụ lỗi `rows[1].age`: `rows[0]` và `rows[1].name` có thể tiếp tục; `rows[1].age` bị BLOCKED; composite `rows[1]`, `rows` hoặc root bị DESCENDANT_ERROR và không được process.

Tra cứu state là O(độ sâu path), không quét toàn bộ error descendants. `recordError()` tạo các branch theo path và đặt error leaf; chỉ gọi khi unit chưa bị guard chặn.

### D.5. `validateAndDetachReferences()`

**CONFIRMED — prototype mới nhất được người dùng yêu cầu sinh lại code**:

```php
private static function validateAndDetachReferences(
    mixed $value,
    bool $allowReferences,
    int $depth
): array;

// Result:
[$cleanValue, $changed]
```

Không có tham số `bool &$changed`, không có phần tử error thứ ba. Invalid data/reference không được phép → throw.

**CONFIRMED — giữ tên `$allowReferences`:** false gặp reference thì throw; true cho phép input có reference và tách chúng trong output. True không có nghĩa giữ reference trong output.

Quy trình:

1. Kiểm tra depth và kiểu scalar/array.
2. Scalar hợp lệ trả `[value, false]`.
3. Với array, duyệt từng child, phát hiện reference bằng `ReflectionReference::fromArrayElement()`.
4. Reference khi allow=false → throw.
5. Đệ quy lấy `[cleanChild, childChanged]`.
6. Chỉ khi child là reference hoặc đã thay đổi mới dựng array thay thế cho container hiện tại; gán từng phần tử bằng giá trị để loại direct reference, không dùng `$copy = $value`.
7. Nếu dựng copy, cập nhật child đã làm sạch và trả `[copy, true]`; ngược lại `[value, false]`.

Các vị trí gọi trong artifact:

| Vị trí | allowReferences | depth |
|---|---|---|
| Constructor input | false | 0 |
| Candidate composite | true | count(path) |
| Composite success result | false | count(path) |
| Snapshot ghi lỗi | true | count(path) |
| Export data | true | 0 |
| Export error | true | -2 |

Depth -2 của error export bù các lớp wrapper error payload trong cách cài đặt hiện tại; đây là chi tiết cần review cùng test độ sâu tối đa, không phải quy tắc topology dữ liệu mới.

### D.6. Reference safety và hiệu suất

**CONFIRMED qua phần review:** PHP array by-value không tự cách ly embedded references. Candidate chia sẻ reference với input có thể sửa cây gốc trước khi báo lỗi, phá invariant không commit khi lỗi. Phải detach trước khi giao composite candidate.

Tách bộ nhớ một phần, cách ly hành vi sửa dữ liệu trong contract array/scalar:

- Cây có B chứa C, C1, C2; chỉ C có ba lá reference → dựng lại C, B và các ancestor tới root.
- C1/C2 được duyệt kiểm tra nhưng không deep-copy nội dung; các entry trỏ tới chúng trong B mới được chép, còn subtree được chia sẻ qua copy-on-write.
- Sửa output về sau khiến PHP copy-on-write ở container cần thiết; input giữ nguyên.
- Dựng container rộng vẫn phải chép mọi phần tử trực tiếp của nó. Không suy ra chi phí chỉ bằng ba lá.

Helper vẫn quét toàn bộ subtree đầu vào: một triệu node vẫn phải kiểm tra một triệu node. Không deep-copy toàn cây mỗi step không đồng nghĩa zero-copy hoặc O(1). Bản trả tuple mới cấp phát result array hai phần tử mỗi lần đệ quy; chi phí tăng so với output parameter cũ đã được giải thích và người dùng vẫn yêu cầu cách mới.

Chi phí tổng còn tùy số step, số node được traversal, độ sâu path và tổng kích thước các composite subtree phải kiểm tra. Composite lồng nhau ở nhiều step có thể bị quét lặp lại. Không có benchmark production hoặc bảo đảm bộ nhớ tuyệt đối trong cuộc trao đổi.

### D.7. Trường hợp biên và exceptions

- Atomic-only step có callback process null. Nếu runtime lại chọn composite và unit chưa bị lỗi chặn → throw.
- Scalar không được chọn làm leaf/EXCLUDED có thể làm ExtArray báo topology invalid; define cần trả đúng theo từng step.
- Đã normalize node ở step trước không khiến Framework tự EXCLUDE node ở step sau. UDA chủ động loại nếu step sau không muốn xử lý nó; vẫn có thể chọn lại có chủ ý.
- Lỗi dữ liệu trả response lỗi một phần; callback throw/configuration sai thì propagate exception, không bọc thành error leaf mặc định.
- Callback là code tin cậy, không sandbox. Không bảo vệ khỏi UDA cố ý sửa captured tree/normalizer/global hoặc gây side effect bên ngoài.
- Input cycle dựa trên reference bị từ chối khi allow=false; chế độ allow=true giả định internal tree không tự sinh cycle từ callback tin cậy, có depth guard.

## E. Confirmed Design Decisions

| Quyết định | Lý do | Ảnh hưởng |
|---|---|---|
| Dùng ExtArray traversal | Tận dụng traversal và leaf by-reference sẵn có | JsonNormalizer không tự viết engine cây thứ hai |
| N steps tuần tự | Cho atomic trước composite khi cần | Step sau dùng kết quả commit trước |
| Framework xử lý atomic | Tránh UDA gọi AtomicNormalizer lặp lại | Define trả schema, adapter dispatch |
| UDA xử lý composite by-value | Có thể validate candidate trước commit | Adapter chịu trách nhiệm mutation cây |
| Commit theo processing unit | Giữ kết quả hợp lệ và tiếp tục nhánh độc lập | Không transaction rollback toàn JSON |
| Nested error tree | Truy cập lỗi theo path và kiểm tra ancestor/descendant thuận tiện | Bỏ danh sách phẳng path/step/source |
| EXCLUDED khác branch | Node không tham gia step không bị traversal tới scalar sai topology | EXCLUDED giữ node và prune subtree |
| Fluent addStep tổng quát | Controller đọc dễ hơn sáu closure dồn vào constructor | Append, return self, bảo toàn thứ tự |
| `err_number` | Thống nhất phong cách key config của Framework | UDA result dùng snake_case |
| `validateStepClosurePair()` | Mô tả đúng cặp closure, tránh hiểu là prototype validation | Shape validation tách khỏi addStep prototype validation |
| `validateAndDetachReferences()` | Làm rõ validation và detach | Thay tên prepareValue |
| Return `[value, changed]` | Tránh vừa return value vừa output-by-reference | Sửa toàn bộ callers/đệ quy; thêm tuple allocation |
| Giữ `$allowReferences` | false đúng nghĩa không chấp nhận reference và throw | Không đổi thành detachReferences |

Bảng không nâng các biện pháp do AI thêm (state machine, depth=128, error namespace 1000, root-only array) thành quyết định độc lập của toàn Framework chỉ vì chúng có trong artifact.

## F. Superseded / Rejected Designs

| Phương án cũ | Trạng thái và thay thế | Lý do |
|---|---|---|
| Define trả bool | SUPERSEDED bởi schema/marker/EXCLUDED/BRANCH_OR_PETIOLE | Bool không đủ biểu diễn atomic/composite và bỏ traversal |
| EXCLUDED từng bị bỏ | SUPERSEDED; khôi phục EXCLUDED | Step sau cần bỏ atomic đã xử lý, không để đi tới scalar |
| UDA tự gọi AtomicNormalizer trong composite callback | SUPERSEDED cho atomic leaf | Framework dispatch tự động |
| Public normalize nhận data/steps mỗi lần, từng phác `normalize(?array &$value, array $arrPairOfFunct)` | SUPERSEDED bởi object giữ data/steps và normalize() không tham số trong artifact | Đăng ký pipeline rõ hơn |
| Chỉ xây thủ công ví dụ ba step | Không được chấp nhận như cơ chế Framework; thay bằng addStep | Cần số step bất kỳ và fluent API |
| Flat errors `{path, step, source, error}` | SUPERSEDED bởi nested tree | Người dùng yêu cầu tra lỗi theo đường dẫn |
| `errNumber` cho key UDA | SUPERSEDED bởi `err_number` | Phong cách config nhất quán; không tự đổi dependency cũ |
| `prepareValue()` | SUPERSEDED bởi `validateAndDetachReferences()` | Tên cũ quá chung |
| `validateNormalizationStep()` / `validateStepStructure()` | SUPERSEDED trong artifact bởi `validateStepClosurePair()` | Tên cụ thể theo cặp callback |
| `checkFunctIsClosure()` / `validateClosurePairStep()` | Từng cân nhắc; không phải tên đã dùng cuối cùng | Thiếu ý nghĩa pair hoặc trật tự từ kém rõ |
| `validateAndDetachReferences(..., bool &$changed): mixed` | SUPERSEDED bởi ba tham số, return array tuple | Người dùng không muốn output parameter pha với return value |
| Đổi allowReferences thành detachReferences | Bị người dùng bác bỏ | Giữ ý nghĩa false → throw |
| Chỉ khuyến nghị UDA dùng >=1000 | SUPERSEDED trong artifact bằng runtime guard; chuẩn Framework vẫn cần xác nhận | Khuyến nghị không ngăn collision |
| UDA nhận leafDefinition tham số thứ ba | Đã cân nhắc, không có trong artifact hiện tại | Adapter cần metadata atomic/composite; UDA hiện chỉ xử lý composite |

Không coi compact numeric atomic result ban đầu là đã bị loại ở mọi dependency; việc đổi contract AtomicNormalizer phải theo source của subsystem ấy.

## G. Implementation Status

### G.1. Đã kiểm chứng ở bản artifact, không phải repository hiện hành

Bản JsonNormalizer.php phiên bản 2 đọc khi lập tài liệu có:

- Constructor by-value array, steps list/pair, fluent addStep.
- Hai kiểu define callback, UDA process hai tham số, ExtArray adapter ba tham số.
- Một ExtArray xuyên suốt steps; closure bridge bảo toàn UDA binding.
- AtomicNormalizer dispatch qua public constants của dependency.
- Nested errors, O(depth) errorStateAt, skip unit có lỗi, commit qua reference.
- Helper tên mới, return `[value, changed]`, không `&$changed`.
- UDA_ERR_MIN=1000 và validation mã; root array/depth/type/reference guards.
- Cached response, FAILED lock, clear closures và unset engine sau normalize.

### G.2. Chưa triển khai trong artifact đã đọc

- Bứt lá: không có REMOVE_LEAF/DISCARDED_LEAF trong whitelist `$select`; traversal deletion chưa tích hợp.
- Đồng bộ marker integer/prototype mới của ExtArray nếu subsystem đó đã thay đổi sau baseline.
- Ánh xạ status với Core\Http\Response.
- RequestSchema/JsonSchema integration.
- Tự phát hiện field vắng mặt hoặc tự thêm missing nodes.

### G.3. Phải đối chiếu repository

Không khẳng định artifact đã được chép vào `core/Data/Normalization/JsonNormalizer.php` hoặc branch/commit nào. Namespace suy ra vị trí PSR-4 là gợi ý tìm source, không chứng cứ file đang tồn tại.

`definitionArgMode()` đang dùng anonymous subclass ExtArray để expose protected validators `detectLeafCallbackArgMode()`, `validateDefineLeafFnReturnType()` và constant `CALLBACK_VALUE_ONLY`. Đây là coupling với API protected của baseline; nếu ExtArray refactor phải review, không chỉ đổi marker.

Atomic adapter đọc `AtomicNormalizer::FIELD_ERR_NUMBER`, `FIELD_VALUE`, `ERR_NONE`. Baseline dependency từng dùng literal key `errNumber`, nên adapter đọc constant để không hardcode sai. Nếu source hiện tại dùng compact `[0,1]` hoặc constant/API khác, phải cập nhật bridge từ contract thật; không đoán key dựa vào lời phác thảo cũ.

## H. Open Issues

### H.1. Bứt lá — phần thiết kế đang dở

**CONFIRMED về nhu cầu:** có leaf không cần gửi vì giá trị được cơ chế khác (ví dụ DB) tạo ra; EXCLUDED chỉ giữ và bỏ xử lý, không đáp ứng xóa field.

**PROPOSED trong cuộc chat JsonNormalizer:** define phát tín hiệu; ExtArray nhận diện và traversal xóa key khỏi parent, không gọi processLeafFn. Tên `REMOVE_LEAF` trong các snippet chỉ là tên tạm của AI. Có context từ trao đổi ExtArray dùng `DISCARDED_LEAF`, nhưng mapping/chức năng thật phải đọc source mới và tài liệu ExtArray riêng. Không đưa constant REMOVE_LEAF vào API như tên đã chốt.

Những chỗ dự kiến sửa trong JsonNormalizer:

1. Whitelist `$select` chấp nhận marker xóa mới.
2. Lấy error state một lần để dùng cho cả guard và quyết định xóa.
3. Đề xuất chặn xóa nếu BLOCKED hoặc DESCENDANT_ERROR, để không mất data nhưng còn lỗi dưới path đó.
4. Đề xuất không cho bứt root, vì artifact yêu cầu root array tồn tại.
5. Cập nhật doc/prototype adapter theo loại marker thật.

Các quy tắc chặn xóa/root là **PROPOSED**, chưa phải quyết định người dùng chốt riêng. Không có bản code JsonNormalizer tích hợp bứt lá được xác minh trong chat này.

**OPEN:** xóa chỉ atomic leaf hay được phép composite subtree; xóa có cần validate điều kiện trước; root policy; parent rỗng/petiole sau xóa; giữ numeric indices; xóa gây sparse list và tác động JSON array/object. Đề xuất không reindex trong pipeline để giữ path error, nhưng chưa có chính sách serialization cuối cùng.

### H.2. Chuyển đổi ExtArray

**OPEN:** version marker/type mới, có còn classifyNode hay không, callback parameter 3 còn gọi leafDefinition hay không, mode validator protected có đổi không, root/petiole traversal sau bứt lá. Không tự sửa ExtArray trong nhiệm vụ bàn giao này.

### H.3. Những điểm cần xác nhận ở cấp Framework

- Status constants tích hợp Response.
- Khoảng mã lỗi Framework/UDA có được chuẩn hóa toàn cục hay chỉ JsonNormalizer.
- Có chấp nhận input PHP array với reference bằng cách detach thay vì reject; hiện artifact reject.
- Root nullable/scalar có thuộc yêu cầu tương lai; hiện artifact chỉ array.
- Độ sâu 128, caching, lock sau exception, không clone có phải contract public cần giữ hay chỉ implementation choice.
- Chỉ validate field tồn tại có đủ cho JsonSchema; missing-field checks đặt ở composite parent hiện là hướng comment, chưa có schema API hoàn chỉnh.
- Hiệu suất với composite lớn và nhiều step: chưa benchmark và chưa có tối ưu quét lặp được thống nhất.

## I. Compatibility and Dependencies

### I.1. Runtime

PHP mục tiêu dev là 8.0.13 (Windows/XAMPP); production Linux/OpenLiteSpeed. PSR-4 `Core\` → `core/`. Artifact dùng `mixed`, union types, ReflectionReference, destructuring và không dùng API chỉ có từ PHP 8.1 như `array_is_list()`.

Các lần test được báo cáo chạy PHP 8.0.30 qua WASM, không phải xác nhận trực tiếp trên PHP 8.0.13/XAMPP hoặc production.

### I.2. API cần bảo toàn khi tích hợp

- `addStep(Closure, ?Closure = null): self`, thứ tự append và atomic-only step.
- UDA process hai tham số by-value; không biến thành callback mutation ExtArray.
- Response ba key `status`, `data`, `extra` và nested error leaf cặp numeric.
- Commit theo unit, không sửa input của Controller trong contract callback tin cậy.
- Helper tuple `[value, changed]` và `$allowReferences` theo quyết định mới nhất trong chat này.
- Không bắt UDA phân biệt BRANCH với PETIOLE.

### I.3. Topology và binding

Baseline JsonNormalizer tôn trọng `__petiole__` của ExtArray, không đổi petiole name. Một JSON có key này cần kiểm chứng với topology engine; không coi mọi JSON object tùy ý đều tự tương thích.

Closure UDA có thể dùng `$this` của Controller; việc ExtArray bindTo() không được phá binding gốc. Wrapper lớp ngoài/lớp trong và dispatch là chi tiết an toàn đã có trong artifact, cần regression test nếu refactor.

TableDAUNormalizer có thể cần marker discard cho auto-generated fields; contract field/action schema riêng không thuộc tài liệu này. RequestSchema là consumer tương lai; không mở rộng nó trước khi chốt JsonNormalizer/ExtArray tương thích.

## J. Test Scenarios

### J.1. Các test đã được báo cáo trong cuộc thảo luận

Sau lần thay helper sang tuple, hội thoại báo 32 kiểm tra pass trên PHP 8.0.30 WASM. Lần tổng hợp tài liệu này không chạy lại suite và không xem đó là bằng chứng repository hiện tại pass.

Nhóm test được báo cáo gồm:

| Nhóm | Nội dung |
|---|---|
| Pipeline | Atomic → row composite; thứ tự commit; sibling độc lập tiếp tục |
| Ownership | Caller input giữ nguyên; sửa response không làm thay cached result |
| Error tree | Atomic nested path, numeric key không nhầm error leaf, root error |
| Skip | Failed descendant chặn composite row/root; failed unit không gọi define lần sau |
| Candidate | Callback sửa candidate rồi báo lỗi không làm đổi input; cả sau traversal reference từ step trước |
| Registration | Pair sai, define return declaration sai, UDA by-reference bị reject, add sau run bị reject |
| Runtime result | Result sai shape/type/mã âm; scalar root success bị reject |
| Data safety | Embedded input reference/cycle/object; external reference trong success output bị reject |
| Lifecycle | Reentry bị reject; exception rồi chạy lại bị reject |
| Binding | Callback Controller giữ `$this` gốc |
| Edge | Empty steps, EXCLUDED root, nullable atomic null |
| Error range | 1 và 999 bị reject; 1000 được ghi lỗi và giữ input; 0 dùng trong success cases |

Đếm 32 là số kiểm tra được báo cáo lúc đó, không phải bao phủ đầy đủ mọi combination hoặc benchmark.

### J.2. PROPOSED — test bổ sung cho source hiện tại

1. Tích hợp đúng ExtArray mới, cả define một/hai tham số và processing adapter ba tham số với marker integer nếu áp dụng.
2. Helper tuple: scalar/no reference → changed=false; direct reference hoặc descendant reference → changed=true; sibling sạch giữ hành vi COW và không bị mutation chéo.
3. Giới hạn độ sâu ngay biên và vượt biên, bao gồm error snapshot/export với depth offset -2.
4. Atomic schema sai throw đúng exception; schema mới/string format qua dependency mới vẫn được dispatch đúng.
5. Composite success thay topology để step sau nhìn đúng cây mới.
6. Descendant error nhưng define chọn ancestor composite: xác nhận skip và không tự traversal lại bên trong leaf ấy.
7. Key `__petiole__`, petiole hợp lệ/không hợp lệ và branch rỗng theo ExtArray thật.
8. Gọi normalize lần hai sau lỗi dữ liệu phải trả cache partial response; phân biệt với exception lock.
9. Prototype/return reference/optional/variadic combinations theo validator thật, thay vì chỉ dựa mô tả trong tài liệu.

### J.3. PROPOSED — test bứt lá sau khi chốt thiết kế

- Xóa field cần DB sinh, giữ sibling, không gọi AtomicNormalizer/UDA processor cho leaf bị xóa.
- Step kế tiếp không thấy field đã bị xóa.
- EXCLUDED giữ node còn discard xóa node.
- Xóa root theo policy được chốt.
- Node/ancestor/descendant error theo policy được chốt; không stale error paths.
- Xóa phần tử numeric, không làm lệch lỗi của phần tử sau; kiểm tra serialization sparse list.
- Xóa con cuối, petiole cuối hoặc branch composite theo phạm vi xóa đã thống nhất.
- Xóa trong foreach reference của ExtArray không bỏ sót sibling, không giữ reference dangling gây mutate nhầm.

Chỉ triển khai những test này sau khi có contract bứt lá chính thức; không dùng test như cách tự quyết định policy còn OPEN.

## K. Next Steps

1. Trong Codex đọc source hiện tại: JsonNormalizer, ExtArray, AtomicNormalizer, AtomicSchema và Response; đối chiếu tài liệu thiết kế ExtArray nếu được cung cấp. Lập diff contract trước khi sửa.
2. Xác nhận marker và prototypes ExtArray sau refactor. Xử lý dependency protected validators và callback parameter 3; không chỉ bổ sung một constant vào whitelist.
3. Giữ các quyết định đã đủ rõ: fluent steps, atomic dispatch tự động, UDA composite by-value, nested errors, commit theo unit, helper tuple.
4. Review khoảng mã UDA và status mapping với Framework; phân biệt proposal đã code với quy ước toàn cục đã được chấp nhận.
5. Tiếp tục chốt bứt lá: marker chính thức, ai xóa, root/error/subtree/reindex/petiole policy. Sau đó mới tích hợp `$select` và traversal tương ứng.
6. Chạy regression suite trên source thật và PHP mục tiêu; ghi commit/version cùng kết quả. Benchmark chỉ cần khi xử lý rủi ro cụ thể ở dữ liệu lớn.
7. Khi JsonNormalizer ổn định mới quay lại configuration mỏng JsonSchema/nhánh json của RequestSchema; TableDAUNormalizer giữ nghiệp vụ bảng ở layer riêng.

**Chỉ dẫn cho AI tiếp nhận:** source hiện hành quyết định tình trạng triển khai; tài liệu này bảo toàn ý định và lịch sử thiết kế. Nếu source khác thiết kế, nêu rõ khác biệt và trạng thái xác nhận, không tự coi source là lý do bác bỏ quyết định người dùng hoặc coi mọi đề xuất trong tài liệu là yêu cầu đã chốt. Nhiệm vụ hiện tại đã dừng ở bàn giao tri thức, chưa yêu cầu viết lại subsystem.
