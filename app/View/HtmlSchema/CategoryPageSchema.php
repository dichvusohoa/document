<?php
namespace App\View\HtmlSchema;
use Core\View\HtmlSchema\BaseHtmlPageSchema;
class CategoryPageSchema extends BaseHtmlPageSchema{
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function defineSchema(): array{
        return [ 'title' => ['type' => 'title'],
            'css'   => ['type' => 'css'],
            'script'   => ['type' => 'script']
            ];
        
    }
    
    
    /*trả về array của các element có cấu trúc như sau
    type: css, script,embed_fragment_layout, link_fragment_layout
    path_fragment( chỉ có giá trị khi type = link_fragment_layout)
    fqcn:function => controller + function phụ trách render dữ liệu cho fragment đó
    */
    //abstract public function dependencyFragments(string $masterFragment, string $strAction):array;
    //trả về mảng các dependency fragments. Chưa có cách nào mô tả tham sổ
}