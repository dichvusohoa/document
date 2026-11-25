# UploadFilesNormalizer

## Phạm vi và cách dùng

`UploadFilesSchema` chịu trách nhiệm chuẩn hóa policy. `UploadFilesNormalizer`
nhận `Request::file()` sau `prepareFileData()` và schema đã normalize; constructor
không tự normalize schema. Không dùng trực tiếp cấu trúc thô của `$_FILES`.

```php
$uploadFilesSchema = new UploadFilesSchema();
$uploadFilesSchema->setValue([], ['max_count' => 10, 'max_total_size' => 10485760]);
$uploadFilesSchema->setValue(['documents'], [
    'min_count' => 1,
    'max_size' => 2097152,
    'allowed_types' => ['pdf' => ['application/pdf']],
]);
$uploadFilesSchema->normalize();
$arrResult = (new UploadFilesNormalizer($request->file(), $uploadFilesSchema))->normalize();
```

Constructor lưu data và schema bằng phép gán array thông thường; input upload là
dữ liệu do PHP tạo và đã qua Request::prepareFileData(), không hỗ trợ bảo đảm cách ly
reference do UDA tự đưa vào. Schema được tách reference khi gọi toFlatArray(). Mỗi lần `normalize()`
tính lại thống kê từ đầu. Không sửa input/schema của caller, không di chuyển file.
Tên petiole cấu hình toàn cục của ExtArray phải giữ ổn định trong vòng đời schema
và normalizer, giống các thao tác ExtArray khác.

## Kết quả

- Thành công: `['status' => Response::SERVER_OK_STATUS, 'data' => $arrData, 'extra' => null]`.
- Lỗi dữ liệu: `['status' => Response::SERVER_LOGIC_ERR_STATUS, 'data' => null, 'extra' => $arrErrors]`.

Output thành công giữ nguyên cấu trúc file đã prepare, index thưa, metadata,
nhánh rỗng và leaf `UPLOAD_ERR_NO_FILE`. Không đổi tên hoặc thay `type` do client gửi.
Không có rule áp dụng thì không áp đặt giới hạn size/type/count.

`extra` là danh sách record `path`, `err_number`, `value`, `rule_path`.
`path` là mảng segment string/int, không có segment petiole. `rule_path` là đường dẫn
policy áp dụng; `[]` nghĩa là root, `null` nghĩa là lỗi không gắn với policy.
Lỗi file theo thứ tự duyệt data; lỗi nhóm theo thứ tự duyệt schema, sau lỗi file.
Một file/nhóm có thể có nhiều lỗi.

| Mã | Constant | `value` |
| --- | --- | --- |
| 1600 | ERR_STRUCTURE | Node dữ liệu sai cấu trúc |
| 1601 | ERR_UPLOAD | Mã upload gốc, kể cả mã chưa biết |
| 1602 | ERR_MAX_SIZE | Size file |
| 1603 | ERR_ALLOWED_TYPES | Extension hoặc MIME bị từ chối |
| 1604 | ERR_MIN_COUNT | Count thực tế |
| 1605 | ERR_MAX_COUNT | Count thực tế |
| 1606 | ERR_MAX_TOTAL_SIZE | Tổng size thực tế |
| 1607 | ERR_READ_FILE | `null` |

## Thuật toán

1. Duyệt bản sao schema bằng ExtArray, lập chỉ mục policy theo đường dẫn logic;
   khởi tạo count/size bằng 0, kể cả nhóm không xuất hiện trong data.
2. Duyệt data bằng ExtArray. File cần các thuộc tính `name`, `type`, `tmp_name`
   kiểu string, `error`, `size` kiểu int; `size >= 0`. `full_path` là string bắt buộc (contract upload từ PHP >= 8.3).
   Leaf phải có đúng 6 key. Sai kiểu thuộc tính trả ERR_STRUCTURE; node không khớp bộ key được coi là nhánh, cấu trúc cây sai có thể gây exception.
3. Leaf có đúng 6 key và `error === UPLOAD_ERR_NO_FILE` trả EXCLUDED ngay trong defineLeafFn: giữ nguyên output, bỏ qua normalizeLeafFn, thống kê và mọi kiểm tra cá nhân.
4. File khác có cấu trúc hợp lệ cộng count và size cho mọi policy trên đường dẫn,
   gồm root và node file. Upload lỗi vẫn tính là một lần upload; size dùng metadata
   đã nhận. Sau đó trả lỗi upload, không kiểm tra cá nhân tiếp cho leaf này.
5. Với upload thành công, chọn `max_size` gần nhất và `allowed_types` gần nhất
   riêng rẽ. Rule khuyết kế thừa; rule con thay thế toàn bộ giá trị của rule đó.
   Đường dẫn khớp chính xác, không hỗ trợ wildcard.
6. `max_size` dùng metadata `size` do PHP upload cung cấp. `allowed_types` kiểm tra
   extension từ `name` (không phân biệt hoa/thường) và MIME thực tế qua Fileinfo
   của `tmp_name`. Không tin `type` do client gửi. Cần extension Fileinfo khi có rule
   này. Không đọc file nếu extension đã bị từ chối; file không đọc được trả 1607.
7. Kiểm tra `min_count`, `max_count`, `max_total_size` của từng policy bằng thống kê
   riêng. Rule nhóm không bị override bởi nhóm con. Giá trị bằng giới hạn là hợp lệ.

Input được giả định là cây array hữu hạn từ Request và schema đã normalize.
Đây là bước kiểm tra metadata/policy, không kiểm chứng `is_uploaded_file()` hoặc
quản lý vòng đời file tạm; bước lưu upload cần xử lý việc đó. File tạm cần tồn tại
khi kiểm tra MIME. Lỗi cấu hình/runtime truyền ra ngoài; không đổi thành lỗi dữ liệu.

## Kiểm thử

`php tests/UploadFilesNormalizerTest.php` kiểm tra kế thừa độc lập, override,
giới hạn tổ tiên, nhóm vắng, boundary, index thưa, no-file/upload error, MIME giả,
file tạm không đọc được, shape lỗi, custom petiole, snapshot và Request integration.

## Phân công callback và quy ước đặt tên

`defineLeafFn` nhận diện COMPOSITE_LEAF bằng đúng bộ 6 key thuộc tính, không kiểm
tra tên segment trong path. Các node còn lại trả BRANCH_OR_PETIOLE.
`normalizeLeafFn` kiểm tra kiểu thuộc tính, trả err_number/value.
`processLeafFn` gọi normalizeLeafFn qua callNormalizeLeafFn; lỗi thì ghi nhận và dừng
leaf đó, thành công thì nhận value và xử lý policy/thống kê.

UDA nên tránh đặt tên field/nhóm trùng name, full_path, type, tmp_name, error, size.
Một node có đúng bộ 6 key này được nhận diện là leaf; nếu các giá trị là nhóm/array,
normalizeLeafFn trả lỗi dữ liệu. UDA chịu trách nhiệm tránh cách đặt tên nhập nhằng.
