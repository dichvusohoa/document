Về cấu trúc 
["status" =>..., "data" =>... "extra"=>...]

- Trong một số trường hợp dữ liệu phức tạp thì dùng trường "extra" để bổ sung
 ví dụ trong cấu trúc dữ liệu phân trang thì "extra" sẽ chứa dữ liệu mô tả phân trang
trong trường hợp ngoài dữ liệu trả về còn có cả schema thì "extra" sẽ chứa schema
- Trong trường hợp lỗi
 
dữ liệu vẫn lưu trữ trong "data".  "data" là dữ liệu nó không có ý phân biệt là dữ liệu
có lỗi hay là không lỗi
