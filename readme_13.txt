Phương pháp kiểm soát lỗi trả về 
Nếu là API/AJAX (response_type=json) → JS xử lý lỗi.
Nếu là browser full page (response_type=html) → PHP include view lỗi.
========================================================
✔ Ưu điểm khi bạn không tạo route phụ, chỉ dựa vào response_type

Đơn giản hóa backend:
Không cần phải tạo thêm route /error/500, /error/503 → tránh ảnh hưởng đến global middleware, router middleware.
Mọi thứ vẫn qua single entry point (index.php), chỉ khác ở response format (JSON vs HTML).

Nhất quán middleware logic:
Middleware global, auth, permission… đều chạy như bình thường. Lỗi chỉ được quyết định ở layer response (html/json), không cần bypass middleware.

Tách biệt rõ:

Nếu là API/AJAX (response_type=json) → JS xử lý lỗi.

Nếu là browser full page (response_type=html) → PHP include view lỗi.

✖ Nhược điểm

Client phải viết JS nhiều hơn:
Bạn sẽ cần custom fetch wrapper + render error UI.
Ví dụ lỗi 503 (Service Unavailable), server trả về JSON, thì JS phải biết “render overlay báo lỗi” thay vì để PHP xuất HTML.

Độ phức tạp tăng ở frontend:
Bạn gần như phải viết một lớp "Error Renderer" JS (giống như mini framework) để hiển thị error page hoặc popup, thay vì chỉ rely on server-rendered error view.

SEO & Crawler không thấy lỗi JSON:
Nếu lỗi xảy ra ở API thì ổn, nhưng nếu user request trực tiếp URL mà response_type=json → SEO bot chỉ thấy JSON (có thể không mong muốn).

💡 Đề xuất cân bằng

Với API / AJAX (response_type=json): dùng giải pháp bạn nói (throw Exception ở JS, render UI).

Với full page (response_type=html): vẫn để PHP include view lỗi chuẩn → nhẹ nhàng, ít code JS.

👉 Nghĩa là bạn không cần route phụ, chỉ cần phân biệt response_type.
Với frontend bạn chỉ cần viết thêm 1 hàm JS render lỗi cho các API call thôi, không nhất thiết phải “render toàn trang lỗi” như PHP.