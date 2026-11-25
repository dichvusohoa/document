# TableDAUNormalizer — Đặc tả kỹ thuật

Ngày cập nhật: 2026-11-02. Namespace: `Core\Normalization`. PHP mục tiêu: 8.0.13.

Đặc tả này mô tả implementation hiện hành và contract row_normalizer đã chốt.
`TableDAUNormalizer_DKT.md` được giữ làm tài liệu chuyển giao/lịch sử; không phải
mọi phương án OPEN/PROPOSED trong DKT đều được triển khai. Các chính sách DAU cũ
được giữ nguyên; callback nay được phép hiệu chỉnh dữ liệu nghiệp vụ.

## 1. Phạm vi và API

```php
$normalizer = new TableDAUNormalizer($data, $schema);
$result = $normalizer->normalize();
```

Chuẩn hóa payload thay đổi dữ liệu của một bảng. Không thực thi SQL, không tự
lookup DB, không kiểm tra transaction, uniqueness, duplicate operations hay
quyền truy cập. Controller/tầng nghiệp vụ chịu trách nhiệm cho các phần đó.

Một step JsonNormalizer; mỗi row là COMPOSITE_LEAF. TableDAUNormalizer gọi
AtomicNormalizer để xử lý từng giá trị. Không thay đổi input của caller.

## 2. TableFieldSchema

```php
$schema = [
    'fields' => [
        'id' => ['type' => 'int', 'auto_value' => true],
        'start_date' => ['type' => 'date'],
        'end_date' => ['type' => 'date'],
    ],
    'keys' => ['id' => ['on_add' => 'forbidden']],
    'row_normalizer' => $validator, // Tùy chọn; null cũng hợp lệ.
];
```

- Root bắt buộc có `fields`, `keys`; chỉ thêm được `row_normalizer`.
- `fields` và `keys` là array không rỗng. Tên là string không rỗng.
- Mỗi field có AtomicSchema hợp lệ. Mỗi key phải được khai báo trong fields.
- Cấu hình mỗi key có đúng `on_add`: `required`, `optional`, `forbidden`.
- `TableFieldSchema::isValid(mixed $schema): bool` kiểm tra schema; constructor
  TableDAUNormalizer ném InvalidArgumentException nếu schema không hợp lệ.
- `row_normalizer` khác null phải là Closure với đúng prototype ở mục 5.
- Không tự trim tên field; không cho thuộc tính schema lạ.

## 3. Payload DAU

```php
$data = [
    'delete' => [['id' => '10']],
    'add' => [['start_date' => '2026-10-30', 'end_date' => '2026-11-01']],
    'update' => [[
        'keys' => ['id' => '11'],
        'values' => ['start_date' => '2026-10-30', 'end_date' => '2026-11-01'],
    ]],
];
```

Root phải có ít nhất một action. Chỉ chấp nhận delete/add/update; action có thể
khuyết, nhưng nếu có thì phải là list row không rỗng, chỉ số liên tiếp từ 0.
Mỗi row phải là array. Sai cấu trúc/tên field gây InvalidArgumentException,
không chuyển thành lỗi dữ liệu trong extra.

### Delete

Delete phục vụ xóa các dòng được chọn trên giao diện bảng. Mỗi phần tử là bộ
key đầy đủ định danh một record; nhiều phần tử định danh nhiều record.
Schema/DB do UDA thiết kế phải bảo đảm bộ key có khả năng định danh đó.

Chỉ nhận field thuộc keys, không nhận field thường. Không hỗ trợ xóa hàng loạt
theo điều kiện tùy ý như `WHERE field_a = 'xxx'`. Field thường xuất hiện là lỗi
cấu trúc. Thiếu key là lỗi dữ liệu toàn row. Giá trị key sai dừng xử lý row.

### Add

- required: phải hiện diện, kể cả khi field có auto_value.
- forbidden: không được hiện diện, kể cả giá trị null.
- optional: hiện tại chỉ được khuyết khi auto_value=true.
- Field thường bị khuyết: hiện tại chỉ được chấp nhận khi auto_value=true;
  nullable/default_value riêng lẻ không cho phép omission trong implementation này.
- Key hiện diện được AtomicNormalizer xử lý; không bị cắt nhờ auto_value.
- Field thường hiện diện bằng null: nullable=true giữ null; nếu không nullable
  thì dùng default_value nếu có; nếu không có default và auto_value=true thì
  cắt field; còn lại atomic báo lỗi field.
- Field khác null được chuẩn hóa bình thường, không cắt chỉ vì có auto_value.
- Row Add rỗng có thể hợp lệ nếu các chính sách key/field đều cho phép khuyết.

Đây là hành vi source hiện tại; các khác biệt với bảng omission của DKT cũ
không được tự sửa trong đợt triển khai row_normalizer.

### Update

Row có đúng `keys` và `values`, cả hai là array; values không rỗng. keys phải
đủ bộ định danh cũ. values chỉ chứa field thuộc fields, có thể bao gồm key mới.
Field khuyết trong values không được tự bổ sung. Không cắt field Update nhờ
auto_value. Key cũ sai là lỗi row; key mới trong values sai là lỗi field.

Key null ở Delete/Update vẫn theo behavior atomic: nullable/default được áp
dụng như source hiện tại. Không thêm chính sách bypass default trong đợt này.

## 4. Thứ tự xử lý mỗi row

1. defineLeaf kiểm tra hình dạng và tên field của row.
2. processRow kiểm tra presence của key và field. Có lỗi thì trả ngay lỗi row.
3. Chuẩn hóa key định danh theo thứ tự keys trong schema. Key sai trả ngay
   ERR_INVALID_KEY; không xử lý key tiếp theo hoặc values.
4. Chuẩn hóa field thường/new values theo thứ tự payload, thu các lỗi field;
   vẫn xử lý các field còn lại khi một field sai.
5. Chỉ khi các bước trên không có lỗi, gọi row_normalizer nếu có.
6. Callback thành công: kiểm tra contract DAU của row đầu ra qua
   validateNormalizedRow(), rồi nhận row mới. Không chạy lại AtomicNormalizer.
7. Trả kết quả composite cho JsonNormalizer; tiếp tục kiểm tra an toàn dữ liệu
   (reference, object/resource, số hữu hạn, độ sâu) trước khi commit.

Các biến đổi atomic đã hoàn tất được giữ khi có lỗi; row lỗi không bị xóa khỏi data.
Nếu callback báo lỗi, bỏ các hiệu chỉnh cục bộ trong callback và giữ row sau
atomic nhưng trước callback. value của kết quả lỗi chỉ mô tả lỗi.
Lỗi dữ liệu của một row không dừng các row khác. Exception dừng normalize,
instance bị khóa và không trả một response thành công một phần.

## 5. row_normalizer

```php
function (string $action, array $row): array
```

Hai tham số bắt buộc, đúng type string/array, return type array; không nullable,
optional, variadic hoặc truyền/trả tham chiếu. Closure giữ binding gốc để UDA
có thể dùng `$this` hoặc capture ngữ cảnh. Đây là code tin cậy của UDA, không
được sửa dữ liệu bên ngoài thông qua side effect.

- Áp dụng cho Add/Update/Delete. Callback tự chọn logic theo action.
- Add: row là map field; Update: row gồm keys và values; Delete: row là bộ key.
- Input là row sau chuẩn hóa, sau áp dụng default và omission phù hợp.
- UDA quyết định logic nào cần kiểm tra và thứ tự; gặp lỗi đầu tiên trả ngay.
- Logic cần field nào thì callback phải kiểm tra field đó hiện diện; thiếu
  thì trả lỗi toàn row. Dùng array_key_exists để phân biệt khuyết với null.
- TableDAUNormalizer không suy ra nhóm phụ thuộc, không tự lookup DB, không
  tự bỏ qua logic vì thiếu dữ liệu. Ý nghĩa null theo nghiệp vụ do UDA xác định.
- Callback vừa kiểm tra vừa hiệu chỉnh nghiệp vụ. Khi thành công, value là
  toàn row đầu ra; khi báo lỗi, value chỉ là thông tin lỗi.

Kết quả có đúng hai key (không phụ thuộc thứ tự):

```php
// Thành công: trả toàn row sau hiệu chỉnh.
return ['err_number' => 0, 'value' => $row];

// Lỗi toàn row, thông tin lỗi là toàn row (quy ước loại 1).
return ['err_number' => 2001, 'value' => $row];

// Lỗi toàn row, thông tin lỗi là nhóm field (quy ước loại 2).
return ['err_number' => 2000, 'value' => ['start_date', 'end_date']];
```

Mã custom là integer >=2000; mã 0 là thành công. Callback trả tối đa một lỗi,
không trả list lỗi. Sai format, sai type mã, mã ngoài phạm vi hoặc kết quả
thành công có value không phải array gây UnexpectedValueException. Trả sai return type
PHP có thể gây TypeError. Exception của callback truyền ra ngoài.

Dữ liệu đầu ra và thông tin lỗi phải tuân thủ giới hạn dữ liệu của JsonNormalizer: không object,
resource, số không hữu hạn hoặc reference, và phải nằm trong giới hạn độ sâu.
Kiểm tra dữ liệu này có thể gây InvalidArgumentException/UnexpectedValueException.

### Phạm vi hiệu chỉnh và kiểm tra tập trung sau callback

validateNormalizedRow(action, before, after) chỉ chạy khi callback trả mã 0.
Vi phạm contract đầu ra là lỗi lập trình callback, gây UnexpectedValueException.

| Action | Quyền hiệu chỉnh và điều kiện đầu ra |
| --- | --- |
| Add | Sửa/thêm/bỏ field trong schema; giữ presence của required/optional và cấm forbidden. Field thường chỉ được bỏ nếu auto_value=true theo chính sách hiện tại. |
| Update | Giữ đúng keys/values; values không rỗng, chỉ chứa field thuộc schema. Được sửa/thêm/bỏ field trong values, kể cả giá trị key mới. Bộ keys định danh cũ không được đổi. |
| Delete | Giữ nguyên bộ key định danh; callback có thể kiểm tra và báo lỗi. |

Identity được so sánh theo tập tên key và giá trị strict (bao gồm kiểu); thứ tự
key trong array có thể thay đổi. Update keys khác với key mới trong values.
Hàm kiểm tra tập trung không sửa dữ liệu, không chạy atomic, không kiểm tra lại
constraint giá trị. UDA chịu trách nhiệm đầu ra sau hiệu chỉnh đúng nghiệp vụ
và canonical nếu tầng tiếp theo yêu cầu. Add có thể trả row rỗng nếu tất cả
chính sách presence cho phép; Update values rỗng luôn bị từ chối.

Constraint muốn UDA hiệu chỉnh không nên chặn trước ở AtomicSchema: muốn clamp
pageSize >100 về 100 thì khai báo type=int nhưng không đặt lte=100 tại atomic.
Nếu atomic đã báo lỗi thì callback sẽ không chạy để cứu giá trị đó.

```php
// Ví dụ schema có pageSize:int, start_date:date, end_date:date.
'row_normalizer' => static function (string $action, array $row): array {
    if ($action === 'delete') {
        return ['err_number' => 0, 'value' => $row];
    }
    $values = $action === 'update' ? $row['values'] : $row;
    foreach (['pageSize', 'start_date', 'end_date'] as $name) {
        if (!array_key_exists($name, $values)) {
            return ['err_number' => 2001, 'value' => $row];
        }
    }
    $values['pageSize'] = min(100, $values['pageSize']);
    if ($values['end_date'] < $values['start_date']) {
        $values['end_date'] = $values['start_date'];
    }
    if ($action === 'update') {
        $row['values'] = $values;
    } else {
        $row = $values;
    }
    return ['err_number' => 0, 'value' => $row];
},
```

Ví dụ giả định các giá trị không nullable và UDA yêu cầu đủ ba field cho logic.
Không sao chép lifecycle của RowNormalizer: TableDAU vẫn cache và khóa instance
sau exception qua JsonNormalizer. Không thay đổi format status/data/extra.

### Quy ước thông tin lỗi nhóm field

Dùng tên field, không dùng đường dẫn. Với Update, mọi tên trong danh sách chỉ
field ở nhánh values, không chỉ keys. Lỗi liên quan keys hoặc quan hệ giữa keys
và values dùng toàn row. Đây là quy ước UDA phải tuân thủ; hiện server không
phân loại nội dung value theo mã và không xác minh danh sách field qua resolver.

### Ví dụ validator

```php
$validator = static function (string $action, array $row): array {
    if ($action === 'delete') {
        return ['err_number' => 0, 'value' => $row];
    }
    $values = $action === 'update' ? $row['values'] : $row;
    // Ví dụ: UDA yêu cầu kiểm tra khoảng ngày cho mọi Add/Update này.
    if (!array_key_exists('start_date', $values)
        || !array_key_exists('end_date', $values)) {
        return ['err_number' => 2001, 'value' => $row];
    }
    // Hai ngày trong schema ví dụ không nullable, đã chuẩn hóa YYYY-MM-DD.
    if ($values['start_date'] > $values['end_date']) {
        return ['err_number' => 2000, 'value' => ['start_date', 'end_date']];
    }
    return ['err_number' => 0, 'value' => $row];
};
```

## 6. Response và mã lỗi

```php
['status' => 'ok' /* hoặc normalization_error */, 'data' => $data, 'extra' => $errors]
```

Không lỗi: extra là array rỗng. Lỗi field nằm tại path field; lỗi row nằm tại
path action/index. Leaf lỗi luôn có dạng `['err_number' => int, 'value' => mixed]`.

| Mã | Tên | Phạm vi |
| --- | --- | --- |
| 1–14 hiện hành | Các mã AtomicNormalizer | Field; key sai được đổi thành 1003 |
| 1000 | ERR_MISSING_KEY | Row |
| 1001 | ERR_FORBIDDEN_KEY | Row |
| 1002 | ERR_MISSING_FIELD | Row |
| 1003 | ERR_INVALID_KEY | Row |
| >=2000 | Mã row_normalizer do UDA định nghĩa | Row |

Ví dụ lỗi nhóm field không thay thế dữ liệu:

```php
$result['data']['update'][0] = [
    'keys' => ['id' => 11],
    'values' => ['start_date' => '2026-10-30', 'end_date' => '2026-10-01'],
];
$result['extra']['update'][0] = [
    'err_number' => 2000,
    'value' => ['start_date', 'end_date'],
];
```

Sau normalize thành công (kể cả response có lỗi dữ liệu), gọi lại trả cache,
không chạy validator lại. Sau exception, gọi lại gây LogicException.

## 7. Tích hợp JsonNormalizer

Theo [JsonNormalizer_Spec.md](JsonNormalizer_Spec.md), record composite có đủ
path/err_number/value/error_value. TableDAU giữ row ở value của path=[] và
chuyển thông tin callback báo lỗi sang error_value; khi thành công nhận row đã
hiệu chỉnh vào value. Chỉ value tại path=[] cập
nhật dữ liệu; record con chỉ báo lỗi. Không thêm step normalization.

## 8. Các cơ chế tạm hoãn

- `error_kind_resolver`: chưa có trong schema, chưa kiểm tra mã custom theo
  danh mục, chưa phân loại value theo mã. Quy ước loại 1/2 ở trên vẫn dành cho UDA.
- `field_groups` và `field_dependencies`: chưa có trong schema và thuật toán
  server. Chưa tự mở rộng hoặc kiểm tra nhóm field theo metadata.
- Kết nối JavaScript: mục tiêu tương lai là đồng bộ cao schema, normalization,
  validator, mã lỗi và test giữa PHP/JS. Cơ chế cụ thể chưa chốt. Khi triển khai
  client sẽ xem xét lại cả metadata phụ thuộc để gửi đủ field liên quan và cách
  phân loại lỗi để hiển thị.

Các cơ chế trên tạm hoãn, không bị loại bỏ khỏi định hướng. Hiện thêm các key
đó vào schema sẽ bị từ chối như thuộc tính lạ. Trách nhiệm kiểm tra field cần
cho logic hiện thuộc row_normalizer do UDA viết.

## 9. Kiểm thử

`php tests/TableDAUNormalizerTest.php` kiểm tra DAU hiện hành, schema callback,
thứ tự chạy, binding, cache, lỗi toàn row/nhóm field, tiếp tục batch và exception.
`php tests/JsonNormalizerTest.php` kiểm tra contract nền và tách data/error.

## 10. Migration từ row_validator

Đổi key schema thành row_normalizer, constant thành FIELD_ROW_NORMALIZER;
ROW_VALIDATOR_ERR_MIN đổi thành ROW_NORMALIZER_ERR_MIN (vẫn 2000).
Không có alias tên cũ: schema chứa row_validator bị từ chối, kể cả bằng null.
Giữ callback hai tham số action/row và kết quả err_number/value. Callback
thành công nay được trả row khác input; callback lỗi vẫn không cập nhật data.
Khuyết callback hoặc row_normalizer=null tiếp tục hợp lệ.
error_kind_resolver, field_groups và field_dependencies tiếp tục tạm hoãn.
