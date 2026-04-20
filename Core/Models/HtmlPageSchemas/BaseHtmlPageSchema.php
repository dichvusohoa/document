<?php
namespace Core\Models\HtmlPageSchemas;
use Core\Models\Response;
use Core\Models\Layout\BaseLayout;
/*1. BaseHtmlPageSchema có nhiệm vụ chính là tính ra một cấu trúc mô tả đặc tính các thành phần 
 (như header, footer, menu, main ...)  của 1 trang web hoàn chỉnh. Trung tâm của class này là
 function schema 
 
Khác với BaseLayout, vì số lượng file layout trong một app có thể không nhiều nhưng số lượng
schema có thể nhiều. Nếu chỉ thiết kế một class HtmlPageSchema giống như một class Layout thì
hàm schema sẽ bị dồn vào đảm nhiệm rất nhiều schema. Kiểu như:
if(...){
    return schema1;
}
else if(...){
    return schema2;
}  
Để làm giảm khối lượng trong schema thì chương trình dùng Router để phân nhánh ra nhiều class HtmlPageSchema
theo request đầu vào. Cách này có ưu điểm là có thể làm cho hàm schema trong một class HtmlPageSchema nào
đó có thể không cần nhiều rẽ nhánh chỉ đảm nhiệm 1 schema duy nhất.
 
Tuy nhiên cần thiết kế linh hoạt, có khi lại thiết kế 01 class HtmlPageSchema cho nhiều url request. Trường hợp điển 
hình là khi acc admin quản trị nhiều danh mục nhỏ dữ liệu kiểu như /unit, /area, /food_type, ...
thì hoàn toàn có thể để 1 class kiểu như CommonPageSchema.php phục vụ cho nhiều loại schema   

 2. Phân tích các loại thành phần dữ liệu BaseHtmlPageSchema chứa
 $requestAuthContext, $arrRouteTMCA, $strLayoutFilePath, $arrUiContext có ảnh hưởng gì tới buildSchema.
 - $arrRouteTMCA: Bình thường với loại HtmlPageSchema chỉ ứng với 1 request uri thì $arrRouteTMCA là không cần.
 Nhưng cũng có tình huống HtmlPageSchema ứng với nhiều loại request uri thì có thể cần $arrRouteTMCA để phân biệt các request
 - $strLayoutFilePath (đại diện cho nhân tố thiết bị và màn hình): vì 01 HtmlPageSchema ứng với 1 uri request, nhưng uri request này có thể xuất phát từ nhiều device type 
 khác nhau có thể cần các layout khác nhau => cần các schema khác nhau. Thí dụ uri school/list từ mobile và desktop có thể cần các
 layout => schema khác nhau. Vậy trong hàm schema có thể phân loại kiểu như
  
  if($strLayoutFilePath === ...){
    return schema1; 
  }
  else if($strLayoutFilePath === ...){
    return schema2;
  }
  - $arrUiContext thường chứa user info bao gồm cả role. Thường ít ảnh hưởng đến schema. Ảnh hưởng đến các thiết kế chi tiết
  giao diện trong layout hơn.
  -  $requestAuthContext. Thường để khai thác các param phụ của url request. Thí dụ /school/list?area=hanoi&page=1. Có thể
 dùng $requestAuthContext->request để lấy value của param area, page. Cũng có thể dùng trong buildSchema trong tình huống
 * đặc thù nào đó nhưng có lẽ là ít.
   
  
 
  
 * 
*/


abstract class BaseHtmlPageSchema {
    protected RequestAuthContext $requestAuthContext;
    protected string    $strLayoutFilePath;
    protected array     $arrUiContext;
    protected array     $arrSchema;  
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(BaseLayout $layout){
        $this->requestAuthContext   = $layout->getRequestAuthContext();
        $this->strLayoutFilePath    = $layout->mapToLayoutFile();
        $this->arrUiContext         = $layout->mapToUiContext();
        $this->arrSchema  =  $this->schema();
        $this->processLinkViewFragment();
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function getRequestAuthContext() {
        return $this->requestAuthContext;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getLayoutFilePath() {
        return $this->strLayoutFilePath;
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function getUiContext() {
        return $this->arrUiContext;
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function getSchema(){
        return $this->arrSchema;
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    abstract public function schema(): array;
    /*trả về array của các element có cấu trúc như sau
    type: css, script,embed_fragment_layout, link_fragment_layout
    path_fragment( chỉ có giá trị khi type = link_fragment_layout)
    fqcn:function => controller + function phụ trách render dữ liệu cho fragment đó
    */
    /*---------------------------------------------------------------------------------------------------------------*/        
    protected function processLinkViewFragment() {
        //biến đổi một chút arrSchema tại cac fragment loại link view. Kết nối các link view phụ này vào và tạo thông tin
        //$this->arrSchema[$strFragment]['render_view']
        foreach ($this->arrSchema as $strFragment => $value) {
            if($this->arrSchema[$strFragment]['type'] === 'link_view'){
                $this->arrSchema[$strFragment]['render_view'] = Response::sendHtmlFile($this->arrSchema[$strFragment]['path_view'],true,$this->arrUiContext);
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
 
}