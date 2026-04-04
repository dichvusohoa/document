Draft
1. Nói về qui định đặt tên biến. Với các biến kiểu đơn giản int, string, float, array, bool
vẫn duy trì qui tắc đặt tên biến Hungarian notation: i,s,n,arr,is. Vì xét ra các ký tự này cũng 
không làm tên biến dài lắm. Cũng có ý nghĩa khi đọc code dài cũng có thể nhanh đoán được loại biến
là gì. string thì có thể dùng str(khuyến nghị) hơn là s(dễ lẫn). float thì có thể là n hoặc f(khuyến cáo)

Còn đối với các class nhất là các class tự phát triển (ví dụ ExtArray) thì có thể bỏ qui tắc này vì đôi khi
nó làm tên biến quá dài, các class đó cũng không quá phổ biến

2. Nói về ExtArray 
ExtArray  là một cấu trúc phân chia nhánh thông tin theo các node dạng string hoặc int (không khuyến cáo)

Nó không được thiết kế với mục đích như là một tree hoàn chỉnh. Mỗi node đều có cấu trúc đầy đủ phức tạp, ví dụ
school => class = > pupil, school, class, pupil đều có thể là một cấu trúc có nhiều field kiểu như id,name,phone number, email,...
Các branch node của nó chỉ là các string hoặc int(không khuyến khích), trong khi leaf node có thể là một object bất kỳ. 
Tuy nhiên leaf node cũng nên tương thích chuẩn json object. Nhằm để cả object ExtArray có thể chuyển đổi
ra dạng json. HÌnh dung obj ExtArray như một khu vui chơi giải trí rộng, branch node là các biển phân luồng chỉ là 
các biển báo kiểu string.
Kiểu như obj['động vật']['thú dữ] => chuồng sư tử, chuồng hổ.

ExtArray không implement AccessArray vì hiệu năng truy suất obj[strKey1]...[strKeyn] thực hiện bằng cách định nghĩa lại
offsetGet tính ra không cao, không an toàn.

Khuyến cáo sử dụng ExtArray theo cách sau

$arrData = [];
$extAData = new ExtArray($arrData);

//dùng các hàm set
$extAData->setValue(["sKey1","sKey2"....],jsonObjValue);//value nên là kiểu đơn giản hoặc json

//khi get value thì dùng trực tiếp $arrData nếu có thể
$x = $arrData[sKey1][sKey2];

Một cách sử dụng khác là 
$extAData = new ExtArray();
lúc đó dữ liệu trong ExtArray là dữ liệu nội tại đóng kín, chỉ truy xuất được thông qua các hàm public 


Nếu có thể thì khi get dùng trực tiếp $arrData vì : trực quan, tốc độ hơn method getValue.
Tất nhiên là ta phải chắc chắn là đường dẫn sKey1,sKey2... đã được thiết lập đúng đắn trong phần setValue rồi

Ưu điểm của ExtArray so với Tree có thông tin ở node hoàn chỉnh là nó định tuyến tới thông tin nhanh vì thông
tin định tuyến chỉ là các nút string(int) liên tiếp
Ưu điểm thứ 2 là dễ dàng chuyển sang jsonData

Nhược điểm không có thông tin đầy đủ tại branch node có thể khắc phục bằng cách dùng nhiều ExtArray
Ví dụ ExtArray về Pupil :  objPupil[school_id][class_id] = ["pupil_id","pupil_name","pupil_address"...]
Cần bổ sung thêm 2 array khác: objSchool[school_id] = [ thông tin về school ];
objClass[class_id] = [ thông tin về class ];


3.Nói về permission tree
Nó thiết lập quan hệ giữa  M (modules not model) - C (controller) - R (role ) - P (permission)
Module là một khái niệm mang tính thương mại để chia dịch vụ hay phần mềm ra các gói khác nhau bán cho người dùng
Trong khi đó model, controller, view, role, permission mang tính kỹ thuật hơn

Yêu cầu đầu ra: Khi người dùng sử dụng chương trình / dịch vụ thì cần xác định họ sẽ được dùng các controller nào và các permission ra sao

Đầu vào:  
- Khi người dùng sử dụng chương trình / dịch vụ thì đã biết được một tập hợp các role của họ; kể cả khi họ không xác thực
thì họ cũng có anonymous role. 
- Do đăng ký mua gói dịch vụ => có được một array gọi là $arrPurchasedModules

hệ thống có một file /config/permission.json lưu một cấu trúc json 4 cấp: module=>controller=>role=>[permission]. 
permission là leaf nó có thể là một string hoặc array of string (nhiều permission)
mô tả đầy đủ tất cả các tình huống

Thực hiện. permission tree object sẽ load file /config/permission.json, nhận input parameter là $arrPurchasedModules, $arrUserRoles 
Sau đó permission tree object sẽ tính ra $arrEnableCP 









3. Nói về permission tree.
Khái niệm modules là khái niệm sinh ra từ nhu cầu thương mại, bán cho người dùng ít hay nhiều chức năng.
Trong khi controller, view, role, permission là khái niệm mang tính kỹ thuật hơn.

Bây giờ vấn đề đặt

