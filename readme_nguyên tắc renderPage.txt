Về renderPage
1) Nguyên tắc thiết kế nhiều tầng
-layout: chia nhỏ làm các view
-schema
-controller
- model classs


Lợi và hại gì
Cái hại
- Thiết kế thông thường thì có 1 layout cụ thể riêng cho 1 controller. Nó đơn giản hơn khi áp dụng
Điểm lợi
- Giảm số layout: layout sẽ chỉ là khung phác thảo Html chung nhất, nhiều controller có thể dùng lại
- Cân nhắc lợi ích của nó trong xu hướng layout phức tạp ngày nay ( nhiều layout cho loại thiết bị). Vậy thiết kế này có thể giúp cho sự mềm dẻo. Vì có schema làm cầu nối giữa controller và layout. Thí dụ với các device khác nhau 1 schema đứng giữa sẽ định nghĩa ra nhiều layout theo tình huống. Đồng thời schema ấy cũng có thể điều chỉnh controller xuất data theo các tình huống layout khác nhau

NGuyên lý chung của layout và schema
a) Các phần tử HTML (meta, tag, attribute, ...) được phân bố ở cả layout và schema. 
b) Về layout: 
+ giữ các phần tử HTML cơ bản nhất như head, body, main,... Hạn chế tạo các phần tử HTML  cụ thể quá mức (như <div id = pupil> )=>làm giảm độ tổng quát của layout; hạn chế các nhóm phần tử có cấp nested quá sâu (như là tạo <form><input >...<input > </form>>) vì mức chi tiết đó thì nên chuyển sang fragment schema dạng view; hạn chế các phần tử có value đơn lẻ như <input value = ...> vì nên cố gắng nhóm chúng vào một fragment dạng view.  Tuy nhiên c 
+ Các phần tử Html nếu như có nội dung tĩnh không biến đổi thì có thể nhúng trực tiếp vào layout

c) Về fragment schema sẽ ưu tiên những fragment type script, css_link, meta ,view. Hạn chế ở mức thấp có thể các loại như text, elemement ( tương ứng với các phần tử đơn lẻ vì nên xử lý nhóm chúng vào một view thay vì tạo fragment riêng rẽ)
d) Về các fragment dạng phần tử riêng lẻ. Được hiểu là các phần tử html:
	+ không có cấu trúc html lồng phức tạp thường là 1 thẻ đơn
	+ có value hoặc attribute nào đó là dynamic ( đương nhiên các phần tử hoàn toàn là static đã được nhúng trực tiếp vào layout rồi)
	+ value hay attribute của phần tử đó chỉ là dạng string hay number đơn giản.
Các phần tử riêng lẻ này tuy nên hạn chế gom vào fragment view nhưng cũng không thể tránh được vì lý do
	+ có những tag đặc thù như <title>	
	+ kỹ thuật lập trình yêu cầu các <input type = hidden>
	+ có tình huống 1 nhóm vài phần tử, nhưng nhóm này rất ít phần tử nhông nhiều như 1 form, tính re-use lại không cao tức là chỉ dùng cho 1 layout nào đó nên tách ra thành view cũng không cần thiết. Ví dụ 1 thanh status bar 
	<div>
		<span> Thông tin dynamic 1</span><span> Thông tin dynamic 2</span><span> Thông tin dynamic 3</span>
	</div>
	
	Tình huống này đôi khi có thể xem như 1 embed view được viết nhúng mã HTML trực tiếp trong layout, gồm 3 phần tử riêng lẻ
	+ các phần tử riêng lẻ trong schema định nghĩa như là các fragment có fragment_type  = text hay elemement
	


 
- Vê

