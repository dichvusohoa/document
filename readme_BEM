Trường hợp user truy cập hệ thống và không đủ quyền thực hiện. Hai tình huống

Trường hợp 1: Người dùng là guest (chưa đăng nhập)
👉 Hành vi hệ thống nên hướng đến:
➤ login – tức là yêu cầu người dùng xác thực danh tính trước.

📌 Lý do: Vì hệ thống chưa biết bạn là ai, nên chưa thể xác định quyền.


🟢 Trường hợp 2: Người dùng đã đăng nhập nhưng không có quyền
👉 Hành vi hệ thống nên hướng đến:
➤ Thông báo “Không đủ quyền” (403 Forbidden)

📌 Lý do: Danh tính đã xác thực rồi, nhưng vai trò (role) hoặc quyền (right) không cho phép thực hiện hành vi này.

✍️ Tổng kết lại bằng một câu:
👉 Nếu chưa biết bạn là ai, thì mời bạn login;
👉 Nếu đã biết bạn là ai nhưng bạn không đủ quyền, thì từ chối truy cập.
======================

Route
- Vì login rất phức tạp nên phải có route riêng cho nó
- 403, 404 thì tùy. Sau này sẽ xem xét sau. Tạm thời hiện tại tính sau
- logout thì cũng phức tạp cần route riêng . Khuyến cáo Tốt nhất là dùng POST /logout với token CSRF hợp lệ.

