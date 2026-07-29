<?php
namespace Core\View\HtmlSchema;
use Core\View\Layout\BaseLayout;
use Core\Http\RequestAuthContext;
use UnexpectedValueException;
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
 $requestAuthContext, $arrRouteMCA, $strLayoutFilePath, $arrUiContext có ảnh hưởng gì tới defineSchema.
 - $arrRouteMCA: Bình thường với loại HtmlPageSchema chỉ ứng với 1 request uri thì $arrRouteMCA là không cần.
 Nhưng cũng có tình huống HtmlPageSchema ứng với nhiều loại request uri thì có thể cần $arrRouteMCA để phân biệt các request
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
 dùng $requestAuthContext->request để lấy value của param area, page. Cũng có thể dùng trong defineSchema trong tình huống
 * đặc thù nào đó nhưng có lẽ là ít.
   
  
 
  
 * 
*/


abstract class BaseHtmlPageSchema {
    protected RequestAuthContext $requestAuthContext;
    protected string    $strLayoutFilePath;
    protected array     $arrSchema; 
    protected array     $arrPositionToFragmentMap;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(BaseLayout $layout){
        $this->requestAuthContext   = $layout->getRequestAuthContext();
        $this->strLayoutFilePath    = $layout->mapToLayoutFile();
        $arrSchema            =  $this->defineSchema();
        $this->validateSchema($arrSchema);
        $arrPositionToFragmentMap = $this->definePositionToFragmentMap();
        $this->validatePositionToFragmentMap($arrPositionToFragmentMap, $arrSchema);
        $this->arrSchema = $arrSchema; 
        $this->arrPositionToFragmentMap = $arrPositionToFragmentMap;  
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    protected function validateSchema(array $arrSchema): void{
        if ($arrSchema === []) {
            throw new UnexpectedValueException(
                'Schema của trang không được rỗng.'
            );
        }
        foreach ($arrSchema as $strFragmentName => $arrDesc) {
            if (!is_string($strFragmentName)
                || trim($strFragmentName) === ''
            ) {
                throw new UnexpectedValueException(
                    'Tên fragment trong schema phải là chuỗi không rỗng.'
                );
            }
            HtmlFragmentSchemaData::validate($arrDesc, $strFragmentName); 
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    protected function validatePositionToFragmentMap(
        array $arrPositionToFragmentMap,
        array $arrSchema
    ): void {
        if ($arrPositionToFragmentMap === []) {
            throw new UnexpectedValueException(
                'Map position tới fragment không được rỗng.'
            );
        }

        foreach (
            $arrPositionToFragmentMap
            as $strPositionId => $strFragmentName
        ) {
            if (
                !is_string($strPositionId)
                || trim($strPositionId) === ''
            ) {
                throw new UnexpectedValueException(
                    'Position ID trong map position-fragment '
                    . 'phải là chuỗi không rỗng.'
                );
            }

            if (
                !is_string($strFragmentName)
                || trim($strFragmentName) === ''
            ) {
                throw new UnexpectedValueException(
                    "Fragment name được map tại position "
                    . "'{$strPositionId}' phải là chuỗi không rỗng."
                );
            }

            if (!array_key_exists($strFragmentName, $arrSchema)) {
                throw new UnexpectedValueException(
                    "Position '{$strPositionId}' trỏ tới fragment "
                    . "'{$strFragmentName}', nhưng fragment này "
                    . 'không tồn tại trong schema.'
                );
            }
        }
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
    public function getSchema(){
        return $this->arrSchema;
    }
    /*---------------------------------------------------------------------------------------------------------------*/   
    public function getPositionToFragmentMap(){
        return $this->arrPositionToFragmentMap;
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    abstract protected function defineSchema(): array;
    /*---------------------------------------------------------------------------------------------------------------*/        
    abstract protected function definePositionToFragmentMap(): array;
    /*---------------------------------------------------------------------------------------------------------------*/        
 
}