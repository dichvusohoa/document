# TableDAUNormalizer — Chuyển giao tri thức thiết kế

Ngày tổng hợp: 2026-10-20. Ngôn ngữ giải thích: tiếng Việt. Framework: PBT.

## Cách đọc và giới hạn bằng chứng

Tài liệu tổng hợp thiết kế từ cuộc hội thoại, không phải đặc tả đã hoàn tất hay báo cáo kiểm định source hiện tại. Không có thao tác sửa code triển khai subsystem trong nhiệm vụ này.

Mỗi nội dung quan trọng dùng một trong bốn nhãn:

- **CONFIRMED**: người dùng đã chỉ định hoặc thống nhất rõ trong cuộc trao đổi. Không đồng nghĩa đã triển khai.
- **PROPOSED**: phương án AI đã đề xuất, chưa có xác nhận cuối cùng của người dùng.
- **OPEN**: chưa giải quyết, thiếu contract hoặc có các quy tắc đang mâu thuẫn.
- **SUPERSEDED**: phương án cũ đã bị thay thế; không dùng làm thiết kế hiện hành.

Bằng chứng gồm các phát biểu và đoạn code có trong ngữ cảnh hội thoại, cùng kết quả review các bản đính kèm trước đây. Source trong repository của người dùng không được kiểm tra lại trong lần tổng hợp này. Các chi tiết chỉ biết qua review lịch sử được đánh dấu tương ứng. Không coi code đã sinh trong chat hoặc file đính kèm cũ là source hiện hành.

Các trao đổi mới hơn về ExtArray/JsonNormalizer ở cuộc làm việc khác chỉ là cảnh báo về khả năng thay đổi dependency; phải đọc source và tài liệu của các subsystem đó trong Codex. Không tự áp các API mới chưa được kiểm tra vào TableDAUNormalizer.

## A. Overview

### A.1. Mục đích

**CONFIRMED** — `TableDAUNormalizer` chuẩn hóa và kiểm tra một khối yêu cầu thay đổi dữ liệu của một bảng, gồm Delete, Add và Update. Đây là dữ liệu mô tả các thay đổi, không phải một bảng dữ liệu thuần túy gồm các dòng cùng hình dạng.

Subsystem nhận:

1. Khối dữ liệu DAU.
2. Schema các field và quy tắc key của bảng.

Kết quả mong muốn là Response gồm trạng thái, dữ liệu sau chuẩn hóa và thông tin lỗi. Subsystem giúp kiểm soát dữ liệu trước khi tầng ghi DB thực hiện thao tác.

### A.2. Phạm vi

**CONFIRMED**:

- Phạm vi chính là một bảng; không thiết kế bộ lập lịch thao tác nhiều bảng.
- Dùng `AtomicSchema` và `AtomicNormalizer` cho giá trị của từng field.
- Tận dụng `JsonNormalizer` để duyệt/normalize cấu trúc DAU.
- Thiết kế có tính đến bộ normalize/validate phía client, dự kiến dùng trong `jAutoTable.js`.
- Client và server đều kiểm tra; server không tin rằng client đã xử lý đúng.
- Không thực thi SQL trong TableDAUNormalizer theo phân công trách nhiệm đã trao đổi.

**OPEN** — Chưa có contract hoàn chỉnh về transaction, ghi một phần batch, lấy giá trị DB vừa sinh, chống xung đột hoặc rollback. Đây không phải các tính năng đã được xác nhận của normalizer.

### A.3. Bối cảnh PBT

PHP mục tiêu của dự án là 8.0.13. DB đang dùng trong bối cảnh thiết kế là MariaDB 10.4.30. Schema hướng tới khả năng diễn đạt tổng quát hơn một DB cụ thể nhưng không muốn tăng độ phức tạp quá mức.

**CONFIRMED** — Quy tắc `nullable`, `default_value`, `auto_value` và `on_add` phải được hiểu thống nhất giữa client/server. Khi server giữ field khuyết để DB xử lý, ứng dụng có trách nhiệm giữ schema PHP và schema DB đồng bộ.

## B. Architecture

| Thành phần | Trách nhiệm | Mức xác nhận |
|---|---|---|
| `TableFieldSchema` | Kiểm tra cấu trúc schema field/key bằng `isValid()` | CONFIRMED |
| `TableDAUNormalizer` | Áp dụng contract DAU, chọn schema field, xử lý chính sách Add/key và tạo Response | CONFIRMED về mục đích; chi tiết thuật toán còn OPEN |
| `JsonNormalizer` | Cơ chế normalize theo step, kết nối callback với duyệt cây và lưu lỗi | CONFIRMED về tái sử dụng |
| `AtomicSchema` | Kiểm tra contract của schema một giá trị | CONFIRMED |
| `AtomicNormalizer` | Normalize/validate giá trị atomic theo schema | CONFIRMED |
| `StringFormatValidator` | Hỗ trợ format `email`, `http_url`, `uuid` cho các lớp atomic | Đã được trao đổi/review; kiểm tra source hiện tại |
| `ExtArray` | Cơ chế duyệt và phân loại node phía dưới JsonNormalizer | CONFIRMED về dependency; API hiện tại cần xác minh |
| Bộ xử lý client | Chuẩn hóa ô nhập, tạo payload, phản ánh lỗi field/row | CONFIRMED về hướng; chưa có implementation trong cuộc trao đổi này |
| Tầng ghi DB | Tạo/thực hiện SQL, để DB cấp giá trị cho field khuyết phù hợp | Phân công trách nhiệm đã trao đổi; API cụ thể OPEN |

Quan hệ dự kiến:

```text
TableDAUNormalizer sử dụng TableFieldSchema và JsonNormalizer.
JsonNormalizer sử dụng ExtArray và AtomicNormalizer.
AtomicNormalizer kiểm tra schema qua AtomicSchema.
Các lớp atomic sử dụng StringFormatValidator khi cần.
Client tạo DAU payload theo contract tương đương.
Tầng ghi DB nhận dữ liệu sau normalization.
```

**OPEN — Namespace:** các bản đã review không hoàn toàn đồng nhất. Bản AtomicSchema/AtomicNormalizer đính kèm dùng `Core\Normalization`; bản JsonNormalizer từng đọc dùng `Core\Data\Normalization`; ExtArray dùng `Core\Foundation`. File TableFieldSchema từng sinh dùng `Core\Normalization`. Không coi các namespace này đã được thống nhất cho source hiện hành. Codex phải đối chiếu autoload và các file trong repository trước khi triển khai.

## C. Current Design Contracts

### C.1. Schema field của bảng

**CONFIRMED** — Tên `TableFieldSchema` được chọn vì cấu trúc chỉ mô tả field và key, không phải toàn bộ schema của một DB table.

```php
$arrTableFieldSchema = [
    'fields' => [
        'field_a' => $arrFieldASchema,
        'field_b' => $arrFieldBSchema,
        'field_c' => $arrFieldCSchema,
        'key_1'   => $arrKey1Schema,
        'key_2'   => $arrKey2Schema,
        'key_3'   => $arrKey3Schema,
    ],
    'keys' => [
        'key_1' => ['on_add' => 'required'],
        'key_2' => ['on_add' => 'optional'],
        'key_3' => ['on_add' => 'forbidden'],
    ],
];
```

Các schema con là array theo AtomicSchema; không phải instance AtomicSchema. Key field cũng nằm trong `fields`. `keys` bổ sung vai trò định danh và chính sách Add.

**CONFIRMED** — Chỉ thêm `on_add` cho key. Không bổ sung `on_update`/`on_delete` cho key hoặc field thường trong thiết kế hiện tại. Điều này không tự giải quyết mọi chính sách định danh; xem phần H.

Public method đã yêu cầu:

```php
public static function isValid(mixed $schema): bool;
```

**Hiện trạng lịch sử, không phải xác nhận repository:** file TableFieldSchema từng sinh có các constant sau:

```php
FIELD_FIELDS = 'fields';
FIELD_KEYS = 'keys';
FIELD_ON_ADD = 'on_add';
ON_ADD_REQUIRED = 'required';
ON_ADD_OPTIONAL = 'optional';
ON_ADD_FORBIDDEN = 'forbidden';
```

Bản từng sinh dùng class `final`, constructor private, yêu cầu root đúng hai mục `fields`/`keys`, cả hai là array không rỗng; tên field/key là string không rỗng; schema field hợp lệ qua `AtomicSchema::isValid()`; key phải thuộc fields; mỗi option key chỉ có `on_add` hợp lệ. Các giới hạn nghiêm ngặt này cần đối chiếu code và xác nhận lại nếu trở thành public contract; không suy diễn rằng mọi chi tiết trong code AI từng sinh đã được người dùng chốt.

### C.2. Chính sách key khi Add

**CONFIRMED** — Nghĩa ban đầu và vẫn dùng để phân biệt sự hiện diện:

| `on_add` | Ý nghĩa |
|---|---|
| `required` | Key phải xuất hiện trong payload Add |
| `optional` | Có thể xuất hiện hoặc bị khuyết |
| `forbidden` | Bắt buộc không xuất hiện |

`required` không đồng nghĩa với không-null. Một field hiện diện với giá trị `null` vẫn là hiện diện; tính hợp lệ của null do schema giá trị và chính sách thao tác quyết định.

**CONFIRMED mới nhất** — Kịch bản điển hình của `forbidden`: cột `id` AUTO_INCREMENT và ứng dụng không cho user tự chỉ định ID. Client phải bỏ `id` khỏi payload Add. Nếu server vẫn nhận `id`, kể cả `id => null`, thì vi phạm contract.

```php
'fields' => [
    'id' => [
        'type'       => 'int',
        'nullable'   => false,
        'auto_value' => true,
    ],
],
'keys' => [
    'id' => ['on_add' => 'forbidden'],
],
```

Tự sinh không đương nhiên đồng nghĩa forbidden: nếu ứng dụng cho phép nhập ID thủ công thì `optional` diễn đạt khác biệt đó.

**CONFIRMED** — Không ép mọi key trong bộ tổ hợp phải cùng chính sách. Có thể có key DB cấp và key phải nhập; không loại bỏ các tổ hợp chỉ vì chúng trộn required/optional/forbidden. Tính khả thi của một bảng cụ thể vẫn phụ thuộc DB và schema.

**OPEN quan trọng** — Bản mô tả mới nhất của người dùng gộp `required | optional` khi xử lý blank/null và cho phép bỏ key nhờ auto_value. Điều này xung đột với quy tắc required bị khuyết phải lỗi. AI đã đề xuất required không được bị loại bỏ; người dùng chưa chốt sửa đổi này. Không tự chọn một bên khi triển khai.

### C.3. Các thuộc tính atomic liên quan

**CONFIRMED** — Tên hiện hành là `default_value`. Tên cũ `defaultValue` đã bị thay thế. Một số mô tả văn xuôi cũ còn dùng tên cũ; không coi đó là tên field khác.

**Theo source đã review trước đây**:

- `nullable` có thể bị khuyết, được hiểu là false. Nếu khai báo phải là bool.
- `default_value` tùy chọn, phải đúng kiểu canonical và thỏa các constraint.
- `default_value => null` chỉ hợp lệ khi nullable true.
- Schema vẫn kiểm tra default đã khai báo dù nullable true khiến input null không sử dụng default.
- Default không được dùng để cứu một giá trị khác null nhưng không hợp lệ.
- Chuỗi rỗng không tự động trở thành null trong AtomicNormalizer đã review.

Các type đã xuất hiện trong bản review: `int`, `float`, `decimal`, `string`, `bool`, `date`, `time`, `datetime`. Constraint gồm `gt`, `gte`, `lt`, `lte`, `scale`, `trim`, `min_length`, `max_length`, `format`. Không mở rộng các constraint trong nhiệm vụ TableDAU nếu chưa có yêu cầu riêng.

AtomicNormalizer đã review có API:

```php
public static function normalize(mixed $value, array $arrSchema): array;
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

Kết quả atomic trong bản đã review:

```php
['err_number' => $iError, 'value' => $value]
```

`ERR_NONE = 0`; `ERR_NULL_NOT_ALLOWED = 1` trong bản đó. Chữ `NO_EMPTY` trong mô tả table/client là tên lỗi theo ý nghĩa; chưa có quyết định ánh xạ nó thành constant/code cụ thể của TableDAU.

### C.4. `auto_value`

**CONFIRMED về hướng bổ sung** — Người dùng đã đưa `auto_value` vào các bản mô tả client/server mới và xác nhận ví dụ ID forbidden với auto_value true.

Ý nghĩa đang được dùng: khi Add không cung cấp field, DB có cơ chế cấp giá trị. Cờ áp dụng được cho key và field thường. Ví dụ field thường `created_at` được DB cấp thời điểm tạo.

Phân biệt:

- `nullable`: cho phép giá trị null.
- `default_value`: giá trị cụ thể mà normalizer có thể sử dụng.
- `auto_value`: thông tin về khả năng DB cấp giá trị khi bỏ field.
- `on_add`: chính sách sự hiện diện của key trong Add.

**PROPOSED** — `auto_value` là boolean, khuyết tương đương false; constant `AtomicSchema::FIELD_AUTO_VALUE = 'auto_value'`; chấp nhận thuộc tính ở lớp AtomicSchema dùng chung, còn tầng table thực hiện chính sách bỏ field. AI đã đưa ra code sửa `isValidBase()`; chưa có bằng chứng code đó đã vào repository.

**PROPOSED, chưa xác nhận cuối cùng** — Cấm `auto_value === true` cùng tồn tại với bất kỳ `default_value` nào, kể cả null. Người dùng nêu nghi vấn rằng hai thuộc tính không thể cùng tồn tại; AI đồng ý đề xuất cấm để contract đơn giản. Đây là lựa chọn framework, không phải chứng minh hai cơ chế luôn mâu thuẫn: omission có thể dùng DB generation còn explicit null dùng PHP default. Không biến đề xuất cấm thành quyết định đã chốt.

**PROPOSED** — Cho phép `auto_value === true` kết hợp `nullable === true`; nullable được ưu tiên khi xử lý blank/null. Không có quyết định cấm tổ hợp này.

**OPEN** — AtomicNormalizer có nên chỉ chấp nhận metadata auto_value và giữ nguyên behavior null, hay phải tham gia cơ chế bỏ leaf mới? AI đề xuất giữ việc bỏ field ở tầng table. Cần kiểm tra source mới nhất và cơ chế DISCARDED_LEAF nếu có.

### C.5. DAU input

**CONFIRMED**:

```php
$arrData = [
    'delete' => [
        ['key_1' => 1, 'key_2' => 'A', 'key_3' => 7],
    ],
    'add' => [
        [
            'field_a' => 'abc',
            'field_b' => null,
            'key_1'   => 2,
            'key_2'   => 'B',
            'key_3'   => 8,
        ],
    ],
    'update' => [
        [
            'keys' => [
                'key_1' => 1,
                'key_2' => 'A',
                'key_3' => 7,
            ],
            'values' => [
                'field_a' => 'changed',
            ],
        ],
    ],
];
```

Ví dụ chỉ minh họa hình dạng, không ngụ ý mọi giá trị/key đều phù hợp với schema ví dụ ở C.1.

**CONFIRMED** — Các danh sách action dùng chỉ số nguyên liên tiếp từ 0; không chấp nhận index có khoảng trống. Key field thuộc danh sách keys; field thuộc danh sách fields.

Đường dẫn field hợp lệ:

```text
delete -> integer index -> key field
add    -> integer index -> field
update -> integer index -> keys | values -> field
```

**OPEN/PROPOSED chưa chốt** — Cho phép khuyết action, empty batch, empty update values; bắt buộc đúng hai mục keys/values của mỗi update; cho phép sửa key trong update values; cách xử lý action/field thừa cụ thể. Hình dạng cơ bản đã rõ nhưng không nên tự suy ra mọi edge case đã được chốt.

### C.6. API TableDAU và Response

**CONFIRMED** — Trung tâm của lớp:

```php
public function normalize(): array;
```

Input gồm `$arrData` và schema field của bảng. **OPEN** — Prototype constructor, tên/thứ tự tham số, visibility property, composition hay inheritance với JsonNormalizer chưa được quyết định đầy đủ.

Response mong muốn:

```php
[
    'status' => /* trạng thái */,
    'data'   => /* dữ liệu sau chuẩn hóa */,
    'extra'  => /* dữ liệu lỗi nếu có */,
]
```

Trong JsonNormalizer từng review, status là `'ok'` hoặc `'normalization_error'`; extra là cây thưa theo path, leaf lỗi là `[$err_number, $originalValue]`. Đây là contract dependency đã thấy ở bản cũ, **cần đối chiếu source hiện tại** trước khi ấn định mã/trạng thái TableDAU.

### C.7. Callback và số step

**CONFIRMED** — Người dùng chủ động chọn tái sử dụng JsonNormalizer với **một step**. Delete/Add/Update là ba nhánh trong một step, không phải ba step. Chưa có quyết định tăng số step sau khi xuất hiện auto_value.

Trong bản JsonNormalizer đã review trước đây, defineLeafFn nhận một hoặc hai tham số:

```php
function (mixed $value): int|string|array;
function (array $arrChain, mixed $value): int|string|array;
```

TableDAU cần biến thể có path. `$arrChain = []` có thể xảy ra: root được phân loại trước khi duyệt xuống, kể cả input rỗng. Callback nhận value bằng giá trị trong bản đó; không được giả định sửa `$obj` sẽ sửa dữ liệu gốc.

Các định nghĩa leaf trong bản lịch sử:

- `ExtArray::EXCLUDED`: bỏ qua node/subtree khi duyệt; không xóa node.
- `ExtArray::BRANCH_OR_PETIOLE`: duyệt xuống node dạng array.
- `ExtArray::COMPOSITE_LEAF`: xử lý toàn node bằng callback; không tự duyệt tiếp con trong cùng step.
- Array AtomicSchema: leaf atomic, JsonNormalizer tự gọi AtomicNormalizer.

Không phải mọi int/string đều hợp lệ.

Prototype processLeafFn đã thấy ở bản review:

```php
function (array $arrChain, mixed $value): array;
// ['err_number' => int, 'value' => mixed]
```

Bản đó yêu cầu đúng cấu trúc kết quả, success 0; lỗi UDA từ 1000 trở lên, vùng 1..999 dành framework. Lỗi composite lưu tại node composite, lỗi atomic tại field. Exception từ callback được truyền ra ngoài.

**OPEN tương thích** — Các marker đã được thảo luận đổi sang integer và bổ sung `DISCARDED_LEAF` trong subsystem ExtArray ở giai đoạn sau. Không hard-code giá trị marker hoặc giữ return type `int|string|array` chỉ vì bản cũ dùng string. Phải xác minh cả ExtArray lẫn JsonNormalizer hiện tại trước khi viết callback TableDAU. Không khẳng định DISCARDED_LEAF đã xóa leaf đúng ý nghĩa chỉ vì constant tồn tại.

## D. Algorithms

### D.1. Ý tưởng một step

**CONFIRMED về định hướng, chưa hoàn tất cơ chế**:

1. Kiểm tra schema trước khi dùng để normalize.
2. Duyệt DAU bằng JsonNormalizer.
3. defineLeafFn dựa vào path và node hiện tại để kiểm tra hình dạng, nhận diện action/row/nhóm field và chọn schema leaf.
4. Các giá trị atomic dùng AtomicNormalizer.
5. Logic riêng của table xử lý thiếu field, key policy, loại bỏ field và mức lỗi row.
6. Trả Response theo kết quả.

Không coi bước 5 đã có implementation tương thích một step. Composite row chặn duyệt xuống con nên phải quyết định chính xác chỗ nào dùng composite và chỗ nào còn duyệt atomic.

### D.2. Thứ tự xử lý null atomic đã review

```text
input === null:
    nullable === true -> success với null
    còn lại, có default_value -> success với default_value
    còn lại -> ERR_NULL_NOT_ALLOWED
input !== null:
    normalize/coerce -> kiểm tra constraint -> success hoặc lỗi
```

Áp dụng cả strict true/false đối với null trong bản AtomicNormalizer đã review. Default đã được schema kiểm tra, không normalize lại default khi sử dụng. Không có nhánh auto_value trong bản atomic đó.

### D.3. Client: field thường

**CONFIRMED về quy tắc người dùng đưa ra cho Add**:

1. Giá trị cụ thể, không blank/null: normalize theo schema.
2. Blank/null và nullable true: gửi null.
3. Không nullable, có default_value: gửi default_value.
4. Không nullable, không default, auto_value true: bỏ field khỏi payload.
5. Còn lại: lỗi cục bộ `NO_EMPTY` ở field.

**PROPOSED** — Giới hạn nhánh bỏ field vì auto_value ở Add. Chưa có xác nhận cuối cùng cho cách áp dụng field thường ở Update trong bản mô tả gộp.

**OPEN** — Định nghĩa blank: `''`, chuỗi toàn khoảng trắng, trước/sau trim, khác biệt giữa các type. AI đã cảnh báo không dùng truthiness vì 0 và false là giá trị hợp lệ. Chưa có đặc tả client hoàn chỉnh.

### D.4. Client: key

**CONFIRMED theo mô tả người dùng**:

- Update/delete phải đủ bộ key.
- Key null phải được schema cho phép; không thỏa thì lỗi cả row.
- Add key forbidden xuất hiện thì lỗi cả row.
- Add key required/optional blank: xét nullable, rồi default_value, rồi auto_value, cuối cùng lỗi row.

**OPEN** — Nhánh auto_value bỏ key required xung đột required presence; xem H.1. Không chốt thuật toán cho nhánh này bằng suy luận của AI.

### D.5. Server: giá trị hiện diện trong Add

**CONFIRMED theo bản người dùng mới nhất**:

- Field thường khác null: normalize theo schema.
- Field thường null: nullable -> null; nếu không nullable thì default; nếu không default và auto_value true thì bỏ field; còn lại lỗi field.
- Key required/optional null: cùng thứ tự nullable -> default -> auto -> lỗi, nhưng lỗi key là mức row.
- Key forbidden hiện diện: lỗi row trước khi diễn giải giá trị.

**OPEN** — Sự khác biệt giữa quy tắc bỏ field này và AtomicNormalizer hiện tại; đặc biệt không thể chỉ giao mọi null cho atomic rồi mong field biến mất. Chỗ bỏ key required chưa giải quyết.

### D.6. Server: field khuyết ở Add

Tại path `['add', $iRow]`, node là toàn bộ row nên có thể so sánh với schema:

**CONFIRMED — Mô tả mới nhất của người dùng**:

| Field khuyết | Xử lý được yêu cầu |
|---|---|
| Field thường nullable true | Giữ khuyết, để DB xử lý |
| Field thường không nullable, có default_value hoặc auto_value true | Giữ khuyết, để DB xử lý |
| Field thường không nullable, không default, không auto | Lỗi cả row |
| Key required | Lỗi cả row |
| Key forbidden | Hợp lệ, giữ khuyết |
| Key optional, có default_value hoặc auto_value true | Hợp lệ, giữ khuyết |
| Key optional, không default, không auto | Theo mô tả người dùng: lỗi cả row |

**PROPOSED chưa chốt** — Cho optional key nullable được khuyết dù không default/auto, dùng chung điều kiện với field thường:

```php
$canOmit = ($schema['nullable'] ?? false)
    || array_key_exists('default_value', $schema)
    || ($schema['auto_value'] ?? false);
```

Không tự thay hàng cuối bảng bằng đề xuất này nếu người dùng chưa quyết định.

**CONFIRMED** — Không còn quy tắc mặc định bổ sung null cho mọi field thường bị thiếu. Giữ khuyết có ý nghĩa chuyển việc cấp giá trị cho DB. Explicit null và omission có thể dẫn tới giá trị DB khác nhau.

### D.7. Update/delete và định danh

**CONFIRMED** — Cần đủ key định danh; null key xét nullable; key sai làm lỗi row theo bản người dùng mới nhất.

**PROPOSED** — Không dùng default_value hoặc auto_value để cứu key định danh null. Mục đích: tránh biến một key sai thành key khác rồi thao tác nhầm dòng. Mô tả người dùng nhấn mạnh xét nullable nhưng chưa chốt cách tái sử dụng atomic khi schema key có default; cần giải quyết rõ.

**PROPOSED** — Field khuyết trong update values nghĩa là giữ dữ liệu cũ; field có mặt phải được validate, không âm thầm loại bỏ nhờ auto_value. Đây là phản biện AI về nguy cơ user xóa ô nhưng dữ liệu cũ vẫn giữ nguyên.

### D.8. Hai mức lỗi

**CONFIRMED** — defineLeafFn không chỉ trả marker/schema: callback có thể throw Exception khi dữ liệu sai cấu trúc quá mức, thuộc lỗi hệ thống/contract mà user không thể sửa bằng nhập lại ô.

Ví dụ đã thống nhất ở mức cấu trúc: action không thuộc DAU, index không đúng dạng, nesting sai. Không cố ép mọi lỗi thành extra.

**CONFIRMED theo bản người dùng mới nhất** — Các lỗi sau được mô tả ở mức row: key bắt buộc hiện diện nhưng giá trị không hợp lệ, forbidden key xuất hiện, thiếu required key trong Add, thiếu thành phần định danh update/delete, thiếu field thường không thể có giá trị. Điều này thay thế một số quyết định Exception cũ cho các trường hợp thiếu key/forbidden.

**OPEN** — Chưa có mã lỗi row, cấu trúc chi tiết để vừa ghi lỗi row vừa lỗi field, ưu tiên khi cùng row có nhiều lỗi, hay chính sách dữ liệu output khi row lỗi. Không giả định row lỗi đã bị loại khỏi data.

## E. Confirmed Design Decisions

| Quyết định | Lý do hình thành trong trao đổi | Ảnh hưởng |
|---|---|---|
| Tên `TableFieldSchema` | Chỉ mô tả field/key, không phải toàn bộ table schema | Naming và trách nhiệm lớp |
| Tên `TableDAUNormalizer` | Tên gắn trực tiếp Delete/Add/Update, dễ hiểu hơn ChangeSet | Không tự suy ra chức năng thực thi DB |
| Tận dụng JsonNormalizer, một step | Cấu trúc DAU là cây có path/schema field xác định | Phải giải quyết row error/discard trong khả năng callback |
| `on_add` chỉ ở keys | Tránh thêm thuộc tính action không cần thiết cho mọi field | Field thường dùng schema atomic và quy tắc của action |
| Required/optional/forbidden mô tả presence | Phân biệt input key bắt buộc, được bỏ qua, bị cấm | Không đồng nhất required với nullable false |
| Forbidden áp dụng cả giá trị null | Điều cấm là sự hiện diện | Phải kiểm tra bằng tồn tại key |
| Tên `default_value` | Người dùng đã đổi source từ defaultValue | Client/server/schema thống nhất vocabulary |
| Bổ sung auto_value trong mô hình table | Diễn đạt DB tự cấp khi thiếu field, không cần default PHP giả | Có thể áp dụng field thường lẫn key |
| Client chủ động normalize/default/omit | Tạo payload hợp lệ, phản hồi sớm cho user | Server vẫn kiểm tra độc lập |
| Server giữ field khuyết khi cho phép | Chuyển việc cấp giá trị cho DB theo schema đồng bộ | Không hoàn thiện mọi row bằng null |
| Phân biệt lỗi dữ liệu và Exception cấu trúc | Lỗi ô/row còn sửa được khác với lỗi giao tiếp hệ thống | defineLeafFn được throw |
| Index action liên tiếp | Contract list rõ ràng | Phải validate trước khi discard có thể làm thủng index |
| Delete trước là ưu tiên thực tiễn | Thường giải phóng giá trị đang chiếm, nhất là uniqueness trong một bảng | Không phải bảo đảm thành công mọi batch |

Về thứ tự DAU: người dùng nghiêng về Delete -> Add -> Update và tên DAU được giữ. Cuộc trao đổi không có chứng cứ thống kê xác suất thành công. Không tuyên bố Delete luôn tối ưu hay DAU luôn giải quyết mọi xung đột. Normalizer không có trách nhiệm chứng minh thứ tự SQL đúng cho mọi batch.

## F. Superseded / Rejected Designs

1. **SUPERSEDED — Tên `TableSchema` cho cấu trúc fields/keys.** Thay bằng TableFieldSchema vì phạm vi hẹp hơn.
2. **SUPERSEDED — Tên `TableChangeSetNormalizer`.** Người dùng chọn TableDAUNormalizer vì dễ hiểu và gắn với DAU.
3. **SUPERSEDED — Tên thuộc tính `defaultValue`.** Dùng default_value.
4. **SUPERSEDED — Mọi field thường Add phải có trong payload.** Người dùng yêu cầu cho phép thiếu field; bản mới cho DB xử lý khi schema cho phép.
5. **SUPERSEDED — Add thiếu field thường luôn bổ sung null để atomic xử lý.** Bản mới giữ khuyết khi có nullable/default/auto phù hợp.
6. **SUPERSEDED — Optional key thiếu cần literal default PHP để diễn đạt mọi trường hợp DB tự sinh.** Có auto_value để mô tả khả năng này. Vẫn còn tranh luận optional nullable không default/auto.
7. **SUPERSEDED — Thiếu required key Add, thiếu key delete/update hoặc forbidden key xuất hiện đều phải Exception.** Người dùng ban đầu chấp thuận, sau đó bản mô tả mới yêu cầu lỗi row. Không giữ hai chính sách đồng thời.
8. **Không được coi là quyết định hiện hành — Dùng nullable false thay cho required.** Hai khái niệm khác nhau; on_add vẫn tồn tại. Tranh luận bỏ required chưa dẫn đến việc bỏ thuộc tính đó.
9. **Không được coi là giải pháp — EXCLUDED đồng nghĩa xóa field.** Bản đã review chỉ bỏ qua duyệt. Việc xóa cần cơ chế thực sự được kiểm chứng.
10. **Không được coi là kết luận — Delete luôn tốt nhất hoặc DAU thành công mọi trường hợp.** Cuộc trao đổi đã thu hẹp về lựa chọn mặc định thực tiễn một bảng.

## G. Implementation Status

### G.1. Những gì biết từ cuộc trao đổi

- AtomicSchema, AtomicNormalizer, JsonNormalizer và ExtArray đã có các bản code được trao đổi/review.
- TableFieldSchema đã được sinh thành file trong cuộc hội thoại, nhưng không có xác minh nó đã được tích hợp vào repository hiện tại.
- AtomicSchema/AtomicNormalizer đính kèm đã được review sau khi đổi defaultValue thành default_value.
- Có đoạn đề xuất sửa AtomicSchema::isValidBase để chấp nhận auto_value, kiểm tra bool và cấm auto_value true cùng default_value. Không có bằng chứng đã áp dụng sửa đổi này vào repo.
- Chưa có bản triển khai TableDAUNormalizer hoàn chỉnh được xác nhận trong cuộc hội thoại này.
- Chưa có bộ normalize client được xác nhận đã triển khai.
- Không có kết quả test runtime TableDAU được xác nhận. Những review source trước có phần là static review; không coi là kiểm thử thực thi.

### G.2. Điểm phải kiểm tra bằng source hiện tại

1. Namespace/autoload thực tế của mọi lớp liên quan.
2. AtomicSchema đã chấp nhận auto_value chưa; có áp dụng quy tắc loại trừ default chưa.
3. Behavior null/default thực tế của normalize/validate strict.
4. Prototype callback JsonNormalizer hiện tại, kiểu trả về marker và quy tắc lỗi UDA.
5. DISCARDED_LEAF đã có semantics và implementation xóa chưa; có truyền qua JsonNormalizer không.
6. Nhánh composite có thể thu lỗi chi tiết field/row như mong muốn không.
7. Shape Response/status/error code có dùng chung Response framework hay literal string.
8. TableFieldSchema tồn tại ở đâu, mức strictness có đúng yêu cầu ứng dụng không.
9. Cách nhận JSON và giữ phân biệt field khuyết với field null.

## H. Open Issues

### H.1. Required và bỏ key bởi auto_value

**OPEN — Ưu tiên giải quyết trước triển khai.**

Người dùng: Add key required/optional blank/null, nếu không nullable/default và auto true thì bỏ key. Đồng thời: required key bị khuyết phải lỗi row.

**PROPOSED của AI:** required không được bị bỏ; muốn DB tự cấp thì optional hoặc forbidden. Chưa có xác nhận cuối cùng. Cũng chưa quyết định required được hiểu theo input ban đầu hay theo dữ liệu sau normalize nếu định thay đổi nghĩa.

### H.2. Optional key nullable bị khuyết

Người dùng chỉ cho thiếu khi có default/auto. AI đề xuất thêm nullable vào điều kiện, đồng nhất field thường. Chưa thống nhất.

### H.3. Auto và default có loại trừ nhau không

Đã có nghi vấn của người dùng và đề xuất cấm từ AI. Chưa có phê chuẩn cuối cùng. Chưa chốt constant/public metadata placement trong source.

### H.4. Quy tắc field thường ở Update

Mô tả gộp có thể dẫn đến bỏ field update vì auto. AI đề xuất chỉ dùng omission theo auto ở Add. Cần chốt và tách thuật toán action rõ ràng.

### H.5. Key định danh và default

Nếu AtomicNormalizer có default, null có thể trở thành một key khác. Cần chốt bypass default cho update/delete, yêu cầu schema không có default cho identity context, hoặc một giải pháp khác. Chưa chọn giải pháp.

### H.6. Một step, row error và xóa field

Cần thiết kế cụ thể mà không vô tình thay đổi tính tổng quát của ExtArray/JsonNormalizer:

- defineLeafFn nhận value by-value thì không trực tiếp sửa row gốc.
- COMPOSITE_LEAF dừng duyệt con, nên dùng row composite phải quyết định ai normalize từng field.
- EXCLUDED không xóa field.
- Field/row cần xóa theo cơ chế discard phải kiểm tra dependency hiện hành.
- Nếu ghi row lỗi, có còn duyệt/normalize các field khác không?
- Giữ hay bỏ row lỗi khỏi data? Không được tự chọn vì ảnh hưởng an toàn tầng ghi DB.

Mục tiêu một step vẫn là quyết định hiện hành; chưa có giải pháp implementation cuối cùng cho toàn bộ các điểm này.

### H.7. Các edge case cấu trúc

Chưa thống nhất đầy đủ: action thiếu/thừa, root rỗng, danh sách action rỗng, row Add rỗng khi DB cấp mọi field, update values rỗng, key xuất hiện trong update values, field lạ là Exception hay lỗi row ở mọi vị trí, list index sau discard row, duplicate operations trên cùng identity.

### H.8. Client và SQL downstream

- Định nghĩa blank theo type/trim.
- Field forbidden có cột hiển thị/read-only trong GUI không; hiện diện trong GUI không nhất thiết là hiện diện trong payload.
- Nullable key có đủ tính duy nhất để định danh dòng không; SQL phải xử lý null đúng cách. Không coi mọi UNIQUE nullable là identity an toàn.
- Auto_value chỉ là khai báo khả năng, normalizer có xác minh DB metadata hay không chưa được quyết định.
- Đồng bộ default PHP/DB là yêu cầu vận hành; chưa có generator/checker tự động.
- Chính sách full batch vs partial success, giới hạn transaction và lấy generated keys chưa được thiết kế ở đây.

## I. Compatibility and Dependencies

### I.1. Các invariant cần bảo toàn

**CONFIRMED hoặc có bằng chứng source lịch sử:**

- Sự hiện diện phải được kiểm tra độc lập giá trị. Trong PHP, `array_key_exists()` phân biệt đúng field hiện diện bằng null; không dùng `isset()` để thực thi required/forbidden.
- Default 0, false và chuỗi rỗng vẫn là default tồn tại; không kiểm tra bằng truthiness.
- auto_value không đồng nghĩa nullable, không đồng nghĩa forbidden.
- Không mặc định auto_value chỉ dành cho key hoặc chỉ type int.
- Không biến literal `'CURRENT_TIMESTAMP'` thành datetime default PHP canonical. Biểu thức DB khác với giá trị default cụ thể.
- Field omission có thể giữ nguyên tới tầng SQL; không vô tình chuyển omission thành null khi serialize hoặc xây INSERT.
- Strict mode atomic không tự trở thành chính sách presence của table.

### I.2. Tương thích nền tảng

Giữ PHP 8.0.13: không dùng API/cú pháp chỉ có từ các bản mới hơn nếu chưa nâng phiên bản mục tiêu. Chuỗi associative spread trong array cần đối chiếu tương thích nếu được dùng khi triển khai; các ví dụ contract không yêu cầu kỹ thuật đó.

Bảo toàn API atomic và JsonNormalizer đang thực tế được các subsystem khác sử dụng. Không đổi behavior null của AtomicNormalizer toàn cục chỉ để xử lý omission của table nếu chưa đánh giá tác động.

### I.3. DB

Kết quả khảo sát trước được dùng làm bối cảnh, không phải test DB hiện tại:

- MariaDB primary key không lưu null; nói về nullable key là mở rộng mô hình hoặc identifier khác, không khẳng định MariaDB PK nullable.
- Gửi null vào AUTO_INCREMENT có thể có semantics DB riêng; thiết kế mới chủ động mô tả omission, không dựa mọi DB hiểu null là tự sinh.
- DB generation có thể áp dụng field thường như created_at.
- Cần phân biệt default DB và default_value PHP.
- Thứ tự DAU có thể tránh một số xung đột uniqueness nhưng không thay thế kiểm tra DB/transaction.

Tài liệu không tái khảo sát DB trong nhiệm vụ chuyển giao này. Nếu implementation dựa vào behavior/version cụ thể, phải xác minh tài liệu DB và test môi trường thực tế.

## J. Test Scenarios

Không có tuyên bố các test dưới đây đã chạy. Nhóm J.1 là tình huống/behavior đã được người dùng xác nhận hoặc mô tả rõ; J.2 là test làm rõ những điểm còn mở; J.3 là đề xuất bổ sung của tài liệu để kiểm tra tích hợp, không phải thiết kế mới đã chốt.

### J.1. Tình huống có căn cứ xác nhận trong trao đổi

| Trường hợp | Kỳ vọng theo quy tắc đã nêu |
|---|---|
| Add id auto, on_add forbidden, payload không id | Chấp nhận omission |
| Add id forbidden nhưng id là số | Lỗi row |
| Add id forbidden nhưng id null | Lỗi row |
| Field thường Add null, nullable true | Giữ null |
| Field thường Add null, không nullable, có default hợp lệ | Dùng default |
| Field thường Add null, không nullable/default, auto true | Loại field theo mô tả table mới; cần cơ chế xóa |
| Field thường Add blank/null không nullable/default/auto | Lỗi cục bộ field theo mô tả mới |
| Field thường Add bị khuyết, nullable true | Giữ khuyết |
| Field thường Add bị khuyết, có default hoặc auto | Giữ khuyết để DB cấp |
| Field thường Add bị khuyết, không thể cấp giá trị | Lỗi row |
| Required key Add thiếu | Lỗi row theo mô tả mới nhất |
| Update/delete thiếu thành phần key | Lỗi row theo mô tả mới nhất |
| Key hiện diện null, schema không nullable và không default | Lỗi row |
| Action/index/nesting sai hệ thống | Exception theo contract cấu trúc |
| Index action 0, 2 | Không hợp lệ vì không liên tiếp |
| Giá trị atomic khác null nhưng sai constraint | Không dùng default để cứu |
| Default bằng 0 hoặc false | Nhận diện đúng sự tồn tại của default |

### J.2. Test chỉ có thể ấn định expected sau khi chốt OPEN

- Required key null + không nullable/default + auto true: lỗi row hay bỏ key?
- Optional key thiếu + nullable true + không default/auto: chấp nhận hay lỗi?
- auto_value true + default_value bất kỳ: schema invalid hay hợp lệ?
- Update field null không nullable/default nhưng auto true: lỗi hay bỏ?
- Update/delete key null không nullable nhưng có default: lỗi hay thay default?
- Unknown field ở Add/delete/update: Exception hay lỗi row?
- Root/action/row rỗng và update values rỗng.
- Có key trong update values, thay đổi key định danh.
- Một row có đồng thời lỗi field và lỗi key: hình dạng extra và ưu tiên lỗi.
- Partial normalize của row lỗi: data chứa giá trị nào và có bị loại row không?

### J.3. PROPOSED — Test tích hợp cần bổ sung

1. Client normalize rồi server normalize lại: không làm thay đổi ý nghĩa omission/null/default.
2. Giá trị 0, false, `''`, chuỗi toàn khoảng trắng: kiểm tra blank policy sau khi chốt.
3. defineLeafFn root path rỗng không truy cập arrChain[0] trước khi kiểm tra độ sâu.
4. Sai node ở từng độ sâu bị phát hiện, kể cả branch rỗng không có leaf để duyệt.
5. Discard thật sự xóa field khỏi data; EXCLUDED chỉ skip.
6. Schema invalid bị từ chối trước khi tạo payload ghi DB.
7. Bỏ cột trong INSERT kích hoạt đúng DB default/generation; explicit null có kết quả đúng như contract.
8. Field default PHP và default DB lệch nhau: test phát hiện rủi ro đồng bộ, chưa giả định normalizer tự phát hiện.
9. Nullable identity dùng câu lệnh SQL đúng và không tác động nhiều dòng ngoài ý muốn.
10. Tương thích prototype callback và mã lỗi với bản JsonNormalizer/ExtArray hiện tại.
11. Trường hợp một field value là array sai type: phân loại được là lỗi atomic hay lỗi cấu trúc theo quyết định cuối cùng, không đi sâu sai path.
12. Delete giải phóng unique value rồi Add/Update sử dụng: test tầng ghi DB riêng; không dùng làm bằng chứng mọi DAU đều thành công.

## K. Next Steps

### K.1. Đọc và đối chiếu trước

1. Đọc source hiện hành của TableFieldSchema (nếu có), AtomicSchema, AtomicNormalizer, JsonNormalizer, ExtArray và StringFormatValidator trong repository.
2. Đọc tài liệu chuyển giao hiện hành của JsonNormalizer/ExtArray để xác định API discard, callback và trạng thái lỗi đã được thay đổi gì.
3. Ghi rõ điểm lệch giữa source và tài liệu này; không tự sửa một subsystem nền chỉ vì bản lịch sử khác.

### K.2. Chốt các vấn đề ảnh hưởng tính đúng đắn

Ưu tiên: required + auto, optional nullable omission, loại trừ auto/default, auto trong Update, default trên key định danh, semantics row error/discard và shape Response. Sau đó chốt các edge case cấu trúc và blank client.

### K.3. Những phần đã đủ rõ để triển khai sau đối chiếu

- Schema fields/keys và các giá trị on_add.
- Kiểm tra field/key có thuộc danh sách cho phép.
- Hình dạng DAU và index liên tiếp.
- Forbidden key Add bị cấm hiện diện kể cả null.
- Chọn AtomicSchema theo path của field.
- Tái sử dụng pipeline atomic hiện có.
- Giữ omission khi quy tắc đã cho phép DB cấp giá trị.

Tuy nhiên không coi các phần đủ rõ này đã tạo thành một TableDAUNormalizer hoàn chỉnh: vẫn phải giải quyết các OPEN có liên quan trước khi khóa API và test expected.

### K.4. Điểm tiếp tục của cuộc làm việc mới

Người dùng đã quay lại xác nhận mục tiêu **JsonNormalizer với một step**, rồi làm rõ lại `on_add = forbidden` cho ID AUTO_INCREMENT không cho user nhập. Bước tiếp theo là đối chiếu khả năng JsonNormalizer/ExtArray hiện tại với nhu cầu bỏ field và lỗi row, đồng thời giải quyết các mâu thuẫn còn mở. Chưa có yêu cầu ở tài liệu này để viết lại hoặc triển khai code.
