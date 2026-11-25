# JsonNormalizer — Đặc tả kỹ thuật

Ngày: 2026-10-30. Namespace: `Core\Normalization`. PHP mục tiêu: 8.0.13.
Đặc tả source hiện hành; DKT là tài liệu lịch sử. Đợt này thay contract composite
ba key bằng bốn key, tách dữ liệu chuẩn hóa và thông tin lỗi.

## 1. API và phạm vi

```php
$n = (new JsonNormalizer($data))->addStep($defineFn, $processFn);
$response = $n->normalize();
// Hoặc new JsonNormalizer($data, [[$defineFn, $processFn], ...]);
```

Input đã decode, root là array. Không parse JSON, không thực thi nghiệp vụ DB.
Tái sử dụng ExtArray để duyệt. Atomic leaf dùng AtomicNormalizer; composite leaf
do UDA xử lý. Callback giữ binding gốc. Input caller không bị sửa.

## 2. Định nghĩa leaf và step

defineFn nhận value hoặc path/value; trả array schema atomic hoặc marker integer
EXCLUDED, BRANCH_OR_PETIOLE, COMPOSITE_LEAF, DISCARDED_LEAF của ExtArray.
Không dùng marker string cũ. Root path là [].

- EXCLUDED bỏ qua xử lý, không xác nhận dữ liệu hợp lệ và không xóa.
- BRANCH_OR_PETIOLE duyệt xuống theo topology ExtArray.
- COMPOSITE_LEAF xử lý toàn node bằng processFn; không tự duyệt con cùng step.
- DISCARDED_LEAF xóa node/subtree không có lỗi; root thành []; không reindex
  numeric keys và giữ nhánh rỗng. Node có lỗi tại/tổ tiên/hậu duệ được bảo vệ.

## 3. Contract processFn

```php
function (array $path, mixed $value): array
```

Trả list record chỉ số liên tiếp từ 0. Mỗi record có đúng bốn key bắt buộc,
không phụ thuộc thứ tự key:

```php
[
    'path' => [],
    'err_number' => 2000,
    'value' => $normalizedRow,
    'error_value' => ['start_date', 'end_date'],
]
```

- path là list thành phần string/int tương đối với leaf hiện tại.
- Đúng một record path=[]; vị trí record trong list tùy ý.
- Mã 0 là thành công. Mã lỗi cho phép là các mã AtomicNormalizer hiện hành
  (1–14) hoặc UDA >=1000. Quy định >=2000 chỉ thuộc row_validator tầng TableDAU.
- Record con chỉ báo lỗi, không được có mã 0.
- Path không trùng, kể cả sau chuẩn hóa numeric string thành PHP array key.
- Hai lỗi không được trỏ vào cùng slot petiole dành riêng.

### Dữ liệu cập nhật và thông tin lỗi

Chỉ value của path=[] dùng cập nhật toàn leaf, kể cả khi mã lỗi khác 0.
value của record con không cập nhật dữ liệu; vẫn tuân thủ kiểm tra an toàn
dữ liệu kết quả như contract cũ.

Mỗi record có mã khác 0 ghi error_value vào lỗi tại path đầy đủ:

```php
$arrError[...] = [
    'err_number' => $record['err_number'],
    'value' => $record['error_value'],
];
```

Mã 0 bỏ qua error_value; nên trả null. Null khi có lỗi là giá trị lỗi hợp lệ,
không có fallback sang value. JsonNormalizer không diễn giải tên field hoặc
phân loại lỗi row/field của ứng dụng.

Ví dụ thành công toàn leaf, lỗi riêng một field:

```php
return [
    ['path' => [], 'err_number' => 0,
     'value' => ['a' => 12, 'b' => 'bad'], 'error_value' => null],
    ['path' => ['b'], 'err_number' => 3,
     'value' => 'bad', 'error_value' => 'bad'],
];
```

Không suy diễn lỗi cha từ lỗi con; status chung vẫn là normalization_error
nếu có bất kỳ lỗi nào. Toàn bộ danh sách kết quả được kiểm tra trước khi commit.

## 4. Response và cây lỗi

```php
['status' => 'ok' /* hoặc normalization_error */, 'data' => [...], 'extra' => [...]]
```

extra=[] khi không lỗi. Leaf lỗi có đúng err_number/value, không còn tuple số.
Lỗi cha và con có thể cùng tồn tại; lỗi cha lưu tại petiole của node khi cần:

```php
[
    '__petiole__' => ['err_number' => 2000, 'value' => ['a', 'b']],
    'b' => ['err_number' => 3, 'value' => 'bad'],
]
```

Tên petiole theo cấu hình ExtArray, không hard-code tên mặc định trong thuật toán.
Khi engine thu gọn petiole, đường dẫn lỗi được điều chỉnh theo dữ liệu; snapshot
thông tin lỗi không bị thay đổi.

Atomic chỉ cập nhật dữ liệu khi thành công; lỗi atomic giữ input và ghi input
vào lỗi. Composite commit value của path=[] kể cả lỗi. Không rollback các unit
khác khi có lỗi dữ liệu. Lỗi chặn xử lý unit tại/tổ tiên/hậu duệ trong step sau;
các sibling hợp lệ vẫn được xử lý khi tiếp tục duyệt xuống.

## 5. Kiểm tra an toàn và lifecycle

- Input, value kết quả và error_value thực sự dùng ghi lỗi phải đáp ứng giới
  hạn dữ liệu của JsonNormalizer: không object/resource/reference, không số
  không hữu hạn; MAX_DEPTH=128. Wrapper lưu lỗi được tính riêng trong cây lỗi.
- Không cho reference trực tiếp ở các thành phần record, kể cả error_value
  khi mã 0; nội dung error_value không dùng của mã 0 không được ghi vào response.
- Sai format composite gây UnexpectedValueException; dữ liệu không an toàn
  có thể gây InvalidArgumentException. Exception callback truyền ra ngoài.
- Kết quả normalize được cache; lần gọi sau không chạy lại callback.
- Sau exception, instance bị khóa. Không addStep sau khi bắt đầu; không clone.

## 6. Migration từ contract trước

Mọi callback composite phải bổ sung error_value. Record ba key bị từ chối.

- Thành công: giữ value, thêm error_value=null.
- Lỗi cũ cần giữ cùng dữ liệu và lỗi: sao chép value sang error_value.
- Lỗi có thông tin khác dữ liệu: value chứa dữ liệu; error_value chứa thông tin lỗi.
- Không đổi prototype processFn; vẫn trả list record.
- Không đổi AtomicNormalizer, ExtArray hoặc format Response.
- Nơi đọc extra tiếp tục đọc err_number/value, không đọc error_value.

Kiểm thử: `php tests/JsonNormalizerTest.php`; kiểm thử tích hợp TableDAU:
`php tests/TableDAUNormalizerTest.php`.
