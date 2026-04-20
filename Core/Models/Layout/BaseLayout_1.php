<?php
namespace Core\Models\Layout;
use Detection\MobileDetect;
use Core\Models\RequestAuthContext;

/*1. Layout có các yếu tố đầu vào là
- $requestAuthContext = $request + authInfo
- $arrRouteTMCA. $arrRouteTMCA thì không phải là nhân tố độc lập, nó được tính toán từ $request của 
contextRouter->matchUri. Do việc tính toán đó có "phí" lớn, $arrRouteTMCA lại chứa thông tin giá trị
nên tuy $arrRouteTMCA có depend on $request (chứa trong $requestAuthContext) nhưng ta vẫn dùng. Nguyên
 tắc dư thừa thông tin để nâng hiệu suất.
- Nhân tố màn hình và thiết bị truy cập. Các nhân tố này phải qua 2 hàm lọc xét xem có cần dùng
đến nhân tố không là requiresDeviceDetection và requiresScreenDetection
Ví dụ chương trình đại đa số trên các url request là chỉ dùng 1 layout A cho cả mobile và desktop
Tuy nhiên tại riêng một vài uri request nếu là desktop thì sẽ dùng 1 layout B có nhiều column hơn.
Như vậy khi requiresDeviceDetection bật = true thì mới cần xét đến nhân tố loại thiết bị.

Tình huống phụ thuộc screenInfo ( chủ yếu là screen resolution) thì hiếm hơn. Kịch bản gần nhất và khả dĩ
là trong một ứng dụng web đồ họa rất chuyên nghiệp nào đó, tại 1 nhánh uri request rất nào đó, cần thông tin
screen resolution để phân nhánh tiếp ra nhiều layout: dưới 1368px là layout A, từ 1368px đến 1920 px là layout B,...

Khi cần lấy thông tin về thiết bị hoặc screen info thì sẽ gọi callable $mobileDetectProvider và 
callable $deviceScreenProvider để đảm bảo tính tổng quát
  
 2. Layout có 2 nhiệm vụ (hàm đầu ra) đó là
- Nhiệm vụ quan trọng nhất của Layout là tính ra fullname layout file. Thực hiện bằng hàm mapToLayoutFile
- Nhiệm vụ tiếp theo của Layout là khi layout file đã xác định rồi thì cần tìm ra các nhân tố có thể gây ra 
 tùy biến giao diện. Ví dụ với url request /school?area=xxx  thì đã xác định được layout là một
 table có 2 cột, cột trái là area, cột phải là list school theo area. Tuy nhiên tùy theo user là nomal và admin
 mà có thể xuất hiện các button như Add, Delete hay không. Thực hiện bằng mapToUiContext, thường là thường là userInfo nó chứa trong self::requestAuthContext->authInfo()['data']
  
3.Các điểm cần chú ý về thiết kế file layout
File layout thể hiện ra được cấu trúc về hình học của các phân vùng, số lượng khối, vị trí tương quan của các phân vùng.

Trừ các phân vùng mà vai trò rất cố định như script, css, menu, header,.. thì không nên gán các ý nghĩa quá cụ thể vào 
các phân vùng đó kiểu như <div id = 'divSchook'>, sẽ làm mất tính tổng quát của layout đó
Ví dụ một layout chia làm 2 cột, cột trái là tham chiếu dữ liệu, cột phải là dữ liệu chính.
thì có thể dùng cho các uri kiểu như /school/list?area=xxx hoặc /pupil/list?class=xxx   

Cũng nên hạn chế gán các thẻ bao ngoài phân vùng một cách cụ thể kiểu như <DIV>,.. vì nó làm cứng hóa loại thẻ bao phân vùng,
và cứng hóa các atrribute có thể gắn thêm vào phân vùng đó.

Nếu như một phân vùng lại có dạng hình học phức tạp, thí dụ 1 layout lại có một phân vùng là một form có dạng hình học gồm vài
field và button. Vậy thì không nên nhúng toàn bộ form data đó vào layout làm mất tính tổng quát mà lên dùng loại link_view để
link vào form_layout này
 
Ngoài ra nên tận dụng dữ liệu uiContext do mapToUiContext tính ra để làm động tùy biến thêm layout

Nếu tuân theo các nguyên tắc này thì số liệu layout file sẽ không cần nhiều
*/
abstract class BaseLayout {
    //các properties được set độc lập
    protected  RequestAuthContext $requestAuthContext;
    protected  array $arrRouteTMCA;
    //các properties được tính toán phụ thuộc
    protected ?array $arrDeviceScreen = null;
    protected ?MobileDetect $mobileDetect = null;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(RequestAuthContext $requestAuthContext, array $arrRouteTMCA, 
            callable $mobileDetectProvider, callable $deviceScreenProvider ) {
        $this->requestAuthContext   = $requestAuthContext;
        $this->arrRouteTMCA          = $arrRouteTMCA;
        //tính toán các properties phụ thuộc
        if(static::requiresDeviceDetection($requestAuthContext, $arrRouteTMCA)){
            $this->mobileDetect = $mobileDetectProvider();
        }
        if(static::requiresScreenDetection($requestAuthContext, $arrRouteTMCA)){
            $this->arrDeviceScreen = $deviceScreenProvider();
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*requiresDeviceDetection quyết định trong ngữ cảnh nào (theo $requestAuthContext và $arrRouteTMCA) thì phải tính ra loại thiết bị là gì*/
    abstract protected static function requiresDeviceDetection(RequestAuthContext $requestAuthContext, array $arrRouteTMCA): bool;
    /*requiresScreenDetection quyết định trong ngữ cảnh nào (theo $requestAuthContext và $arrRouteTMCA) thì phải tính ra screen info là gì*/
    abstract protected static function requiresScreenDetection(RequestAuthContext $requestAuthContext, array $arrRouteTMCA): bool;
    /*mapToLayoutFile đã có đầy đủ các yếu tố để tính ra layout file name */
    abstract public function mapToLayoutFile():string;  
    //xác định các nhân tố gây tùy biến giao diện, thường là userInfo nó chứa trong self::requestAuthContext->authInfo()['data']
    abstract public function mapToUiContext(): array;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRequestAuthContext() {
        return $this->requestAuthContext;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRouteTMCA() {
        return $this->arrRouteTMCA;
    }
}
