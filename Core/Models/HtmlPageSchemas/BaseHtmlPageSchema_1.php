<?php
namespace Core\Models\HtmlPageSchemas;
use Core\Models\RequestAuthContext;
abstract class BaseHtmlPageSchema {
    protected RequestAuthContext $requestAuthContext;
    protected string $strLayoutFilePath;
   
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(RequestAuthContext $requestAuthContext,string $strLayoutFilePath){
        $this->requestAuthContext          = $requestAuthContext;
        $this->strLayoutFilePath  = $strLayoutFilePath;
       
    }
    /*---------------------------------------------------------------------------------------------------------------*/        
    abstract public function buildSchema(): array;
    /*trả về array của các element có cấu trúc như sau
    type: css, script,embed_fragment_layout, link_fragment_layout
    path_fragment( chỉ có giá trị khi type = link_fragment_layout)
    fqcn:function => controller + function phụ trách render dữ liệu cho fragment đó
    */
  //  abstract public function dependencyFragments(string $masterFragment, string $strAction):array;
    //trả về mảng các dependency fragments. Chưa có cách nào mô tả tham sổ
}