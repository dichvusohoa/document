Nháp

Các controller chỉ lo việc trả dữ liệu dạng json thôi
Nếu query dữ liệu dạng html thì qui trình như sau
- Gọi LayoutController
- Căn cứ vào 
Request ( vì request có đủ thông tin nhất về url), 
UserInfo (có thông tin về roles và enable module), 
screen
Device => chọn ra layout

đó là 4 yếu tố nguyên tố để chọn layout. Tuy nhiên nếu dùng 4 yếu tố nguyên tố đó thì hơi khó dùng. Nên tận dụng kết quả của $router->matchUri. Dùng 3 nhân tố sau

$mca   ($module, $controller, $action)
$query_params 
$routeInfo  
screen
$device

hai tham số $mca và $routeInfo  chính là từ  Request và UserInfo mà ra.Ta dùng để tận dụng việc parse của Router. Nhưng còn thiếu những tham số trong query string. Nên dùng $query_params ( thực chất là $_GET lấy tử request). Không cần truyền nguyên $request vì thực chất layout trả về gần như chắc chắn phụ thuộc vào $_GET, không phụ thuộc vào $_POST

===========================
Cấu trúc của các view. Ví dụ view header.phtm

return ['view' => '<div>...</div>', 'url' =>'example.com/header' ,'required' => false]
 


=======================
Để tránh tạo ra các function tập trung quá lớn, cần chia nhỏ từng bước ( giống Router khi xưa)
LayoutController::renderLayout($mca, $query_params,$routeInfo,$device)

1. LayoutController::mapToLayoutPath($mca, $query_params,$routeInfo,$device){
	//hàm này không phức tạp vì  số lượng layout khung bao ngoài ( to nhất) không nhiều nên
	// thân hàm sẽ không nhiều code
	return path_to_layout; 
}
2. LayoutController::mapToUriView(path_to_layout, path_to_view,$mca, $query_params,$routeInfo){
	return url;
}
Xong bước này sẽ gọi bước 2

3. LayoutController::mapToViews(path_to_layout, $mca, $query_params,$routeInfo,$device) {
	['key1'=>['html' =>...,'url'=>... , 'required' => ...],'key2'=>  ]  
	
	trong hàm này sẽ gọi mapToUriView để tính ra các url cho các view mà có url động
}


========================
Cách dàn trang tổng quát trong layout.phtml

$fragment_names = array_keys[$layout] ;//dãy key1, key2


trong trang layout sẽ là các lệnh lần lượt  

echo $layout[$fragment_names[0]]['html'];
echo $layout[$fragment_names[1]]['html'];


Cách này sẽ làm linh hoạt layout , với 1 file  layout.phtml nó có thể tổ hợp với nhiều biến $layout khác nhau tạo ra nhiều giao diện. Như vậy 1 panel bên trái có thể lúc thì là leftmenu,
lúc lại là treeview, ..... Nó "động" hơn cách đưa vào các biến kiểu  echo 'header.phtml' truyền thống


Trong layout.phtm sẽ phân tích lấy hết các url ở trong biến $layout và tổng  hợp lại và gửi dạng post cho một controller là CollectionController ( chưa nghĩ ra url gì cho phù hợp , có thể đặt
vào trong config/config.php) 

CollectionController sẽ hoạt động giống MiddlewareChain
==============================

Trong thiết kế có hai trường phái cần dung hòa là tập trung và phân tán. Tập trung quá mức thì
cũng không được, ví dụ như trước khi thiết kế PermissionTree. Phân tán nhiều gây trùng lắp thì không được.

Cân nhắc về class Layout. Là một thiết kế tập trung quản lý layout và views thay vì phân tán về các Controller vì lý do sau:
- hàm mapToLayout thì do số lượng layout không lớn. Nếu tính cả yếu tố layout cho mobile và pc,
normal user và admin user thì thường số lượng layout sẽ chỉ khoảng 2->6 layout trên server thôi.
Ngoài ra còn việc client sẽ dùng CSS để custom layout nên số lượng layout trên server sẽ không có số lượng thật là lớn. 
- hàm mapToFragments thì sẽ dài hơn (chọn tên Fragments chứ không phải Views vì thực tế có nhiều vùng là css, script,... ). Dự kiến là sẽ chia case theo layout

function mapToFragments(){
	if(layout = x){
		mapToFragmentsXLayout();
	}
	else if(layout = y){
		mapToFragmentsLayout();
	} 
	.... // ước lượng từ 2->6 hàm
}

đối với hàm  mapToFragmentsXLayout(){

}

thì trong một 1 layout có độ phức tạp trung bình gồm các fragment
- title
- css
- script
- header
- menu
- command function ( ví dụ thanh search bar)
- status bar
- left panel
- right panel
- footer

Khoảng 10 fragments = > lượng code cũng không đến mức lớn
 

