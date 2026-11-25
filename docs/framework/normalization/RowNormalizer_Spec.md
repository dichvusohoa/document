# RowFieldSchema và RowNormalizer

Ngày: 2026-11-01. Namespace: `Core\Normalization`. PHP >= 8.0.

## 1. Mục đích và API

Normalize một row tổng quát, không gắn với DB hoặc action add/update/delete.
Không dùng JsonNormalizer hay ExtArray. Không thay đổi TableDAUNormalizer.

```php
RowFieldSchema::isValid(mixed $schema): bool;
new RowNormalizer(array $arrRow, array $arrRowFieldSchema);
$normalizer->normalize(): array;
```

Schema là array; RowFieldSchema là class kiểm tra contract, không phải DTO.

## 2. Schema

```php
[
    'fields' => [
        'start_date' => ['type' => 'date'],
        'end_date' => ['type' => 'date'],
    ],
    'row_normalizer' => static function (array $row): array {
        $missing = [];
        foreach (['start_date', 'end_date'] as $name) {
            if (!array_key_exists($name, $row)) {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            return ['err_number' => 1000, 'value' => $missing];
        }
        if ($row['start_date'] > $row['end_date']) {
            return ['err_number' => 1001, 'value' => ['start_date', 'end_date']];
        }
        return ['err_number' => 0, 'value' => $row];
    },
]
```

- Root chỉ nhận `fields` và tùy chọn `row_normalizer`.
- `fields` là map không rỗng; tên field là string nguyên thủy không rỗng
  hoặc int không âm. Không trim: `' '` hợp lệ, `''` không hợp lệ.
- Key số không cần liên tiếp; cho phép trộn string/int, ví dụ
  `['name' => ['type' => 'string'], 0 => ['type' => 'int'], 5 => ['type' => 'int']]`.
  Giữ nguyên key và thứ tự input trong data, map lỗi field và dòng đưa vào callback.
- Xét key thực tế sau khi PHP tạo array, không tự chuyển đổi hoặc đánh lại chỉ số.
  UDA chịu trách nhiệm về key dễ bị PHP chuyển đổi hoặc gộp: `'0'` thành `0`,
  `'-1'` thành `-1` (bị từ chối), còn `'01'` vẫn là string không rỗng.
- Mọi AtomicSchema đều phải hợp lệ, kể cả field không xuất hiện trong input.
- Không dành riêng tên `__petiole__`, `err_number` hoặc `value` cho field.
- `row_normalizer` là optional: có thể bỏ key hoặc đặt null, đều bỏ qua bước
  xử lý toàn dòng; các field có mặt vẫn được AtomicNormalizer xử lý bình thường.
- Callback phải là Closure, đúng một tham số bắt buộc kiểu array, trả array.
  Không nullable, reference, variadic hoặc tham số bổ sung. Static Closure được phép.
- Schema sai khiến constructor throw InvalidArgumentException.

## 3. Presence và field ngoài schema

- Field thiếu: bỏ qua, không thêm null/default, không báo lỗi framework.
- Field có null: gọi AtomicNormalizer; nullable/default_value áp dụng nguyên trạng.
- Field có giá trị khác null: gọi AtomicNormalizer bình thường.
- `auto_value` không có hành vi riêng tại tầng row; không giả định DB sinh giá trị.
- Field ngoài schema: constructor throw
  InvalidArgumentException trước khi normalize bất kỳ field nào.
  Schema đã từ chối key âm và string rỗng, nên các key đó cũng không thể là
  field hợp lệ trong input.
- Input `[]` hợp lệ. Không có callback thì thành công; callback có thể từ chối.
- UDA kiểm tra field bắt buộc bằng array_key_exists trong callback, rồi mới dùng
  field để kiểm tra quan hệ. Null hợp lệ theo schema vẫn có thể cần xử lý riêng
  trong logic UDA; isset không phân biệt null với thiếu field.

## 4. Thuật toán và dữ liệu từng phần

1. Constructor kiểm tra toàn bộ schema và tên field input.
2. normalize duyệt các field có mặt theo thứ tự input, gọi AtomicNormalizer.
3. Ghi value kết quả từng field: thành công là canonical, lỗi giữ input gốc.
4. Thu thập mọi lỗi field, không dừng ở lỗi đầu tiên.
5. Có lỗi field: trả Response lỗi, không gọi row_normalizer.
6. Không có lỗi field và có callback: gọi với dòng đã normalize (có thể thiếu field).
7. Kiểm tra contract kết quả; mã khác 0 tạo lỗi toàn dòng.
8. Callback thành công: nhận value làm dòng kết quả. Không có callback thì giữ
   dòng đã normalize. Trả Response thành công.

Callback kiểm tra logic và có thể hiệu chỉnh dữ liệu nghiệp vụ. Khi thành công,
value phải là array và thay thế toàn bộ Response.data. Khi callback báo lỗi,
data giữ dòng đã normalize trước callback; value chỉ mô tả lỗi.
Không chạy lại AtomicNormalizer hoặc kiểm tra lại tên field trên array callback
trả về. UDA chịu trách nhiệm về dữ liệu sau hiệu chỉnh, kể cả thêm/bỏ field.
UDA phân chia ràng buộc giữa AtomicSchema và callback: muốn tự sửa pageSize
ngoài khoảng 1..100 thì không đặt gte/lte tương ứng ở AtomicSchema để tránh
chặn dữ liệu trước callback. Ví dụ:

```php
$schema = [
    'fields' => ['pageSize' => ['type' => 'int']],
    'row_normalizer' => static function (array $row): array {
        if (array_key_exists('pageSize', $row)) {
            $row['pageSize'] = max(1, min(100, $row['pageSize']));
        }
        return ['err_number' => 0, 'value' => $row];
    },
];
// Input ['pageSize' => '200'] -> Response.data ['pageSize' => 100].
```

Lỗi field và lỗi callback không thể xuất hiện cùng lúc trong một kết quả.
Không sửa input caller bằng phép gán giá trị canonical; kết quả row được tạo mới.
Không deep-copy object/reference lồng nhau trong dữ liệu lỗi hoặc payload UDA;
không cam kết snapshot bất biến với các giá trị đó. Callback chịu trách nhiệm
về side effect. Mỗi lần normalize xử lý lại input đã lưu và gọi lại callback
nếu đủ điều kiện; không cache hay khóa instance sau exception.

## 5. Contract kết quả callback

Đúng hai key `err_number` và `value`, không phụ thuộc thứ tự key.

| Điều kiện | Quy định |
|---|---|
| err_number | int, bằng 0 hoặc >= 1000 |
| Hai phần tử trực tiếp | Không được là reference |
| Mã 0 | value phải là array; được phép khác dòng đã truyền vào, kể cả array rỗng |
| Mã >= 1000 | value là mixed do UDA quy định; không bắt buộc danh sách tên field |

Vi phạm gây UnexpectedValueException. Kiểm tra reference chỉ tại hai key trực
tiếp bằng ReflectionReference, không đệ quy; chi phí không tăng theo kích thước
value. Không còn so sánh value với dòng trước callback.
Callback khai báo trả array nhưng trả kiểu khác gây TypeError của PHP.
Exception của UDA truyền ra, không chuyển thành lỗi dữ liệu.

## 6. Response

```php
// Thành công
['status' => Response::SERVER_OK_STATUS, 'data' => $row, 'extra' => null]

// Lỗi field
[
    'status' => Response::SERVER_LOGIC_ERR_STATUS,
    'data' => ['quantity' => 'abc', 'price' => 12],
    'extra' => [
        'quantity' => ['err_number' => 3, 'value' => 'abc'],
    ],
]

// Lỗi toàn dòng
[
    'status' => Response::SERVER_LOGIC_ERR_STATUS,
    'data' => $row,
    'extra' => ['err_number' => 1000, 'value' => ['start_date']],
]
```

Không dùng wrapper __petiole__. Mã lỗi field do AtomicNormalizer cung cấp.
Consumer có thể nhận diện lỗi toàn dòng bằng err_number kiểu int tại root extra;
field có tên err_number thì giá trị tương ứng là một record array, không phải int.
Không dùng chỉ array_key_exists('err_number', extra) để phân biệt hai dạng.

## 7. Kiểm thử

Chạy từ repo root: `php tests/RowNormalizerTest.php`.
Suite bao phủ missing/null/default, auto_value không có hành vi DB, lỗi field
tiếp tục và chặn callback, presence do UDA, field lạ, tên petiole thông thường,
schema/prototype sai, miền mã lỗi, hiệu chỉnh pageSize/ngày, nhận array mới mà
không normalize lại, giữ data khi callback báo lỗi, hai reference trực tiếp,
TypeError, exception UDA, input isolation khi gán canonical và gọi lặp lại.

Kết quả kiểm chứng ngày 2026-11-02: RowNormalizerTest đạt 15 nhóm tình huống,
bao gồm callback khuyết/null, từ chối giá trị khác Closure/null, key số thưa/trộn,
giữ key lỗi số, string khoảng trắng và từ chối key âm/rỗng;
TableDAUNormalizerTest đạt 16 nhóm tình huống hồi quy. Hai class mới qua php -l.
