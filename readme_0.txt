Cấu trúc dữ liệu trả về

status (cả cho server và client)
    - server_ok, client_ok,.... các loại lỗi kiểu dữ liệu sai, constrains, format,....
 vì sao không dùng sucsess: true/false vì như thế không gọn trường. Nếu có lỗi thì lại
mất thêm 1 field nữa để giải thích nó là lỗi gì. Hệ thống này bắt lỗi tương đối phức tạp
và nhiều loại nên trường status phải giải thích ngay lỗi gì để tiết kiệm

data
    - Khi không lỗi thì nó là dữ liệu trả về. Dạng đơn row hoặc dạng bảng dữ liệu

đổi từ info = > data cho đỡ chung chung
    - khi có lỗi thì nó có thể là blank  hoặc đôi khi phức tạp như khi bắt lỗi ở table,
    là một cấu trúc định vị các cell bị lỗi. Hoặc lỗi 1 phần  + thành công một phần => như
khi chạy lệnh update 1 bảng. Lúc đó data cũng rất phức tạp

extra: vẫn giữ tên này chứ không phải message vì nhiều trường hợp nó phức tạp hơn là 1 message thông thường

Ví dụ
    - thành công khi trả về dữ liệu bảng: còn có pagination, schema....
    - thất bại nó cũng có thể phải bổ trợ cho data
    - còn cần dự trữ cho tương lai nên giữ nguyên tên extra
    