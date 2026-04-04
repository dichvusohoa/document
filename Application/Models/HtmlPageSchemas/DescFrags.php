<?php
namespace App\Models\HtmlPageSchema;
use Core\Models\BaseHtmlPageSchema;
class DescFrags extends BaseHtmlPageSchema{
   
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function buildSchema(): array{
        return [ 'title' => ['type' => 'title'],
            'css'   => ['type' => 'css'],
            'script'   => ['type' => 'script'],
            'header' => ['type' => 'embed_view', 'url' => '/shared-fragments/header', 'required'=> false],
            'hmenu' => ['type' => 'embed_sub_layout', 'required'=> true],
            $x1 => ['type' => 'embed_sub_layout', 'required'=> true],
            $x2 => ['type' => 'embed_sub_layout','url' => UriUtility::appendQueryParams(['response_type' => 'json']) , 'required'=> true],
            'footer' => ['type' => 'embed_sub_layout', 'required'=> true]
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