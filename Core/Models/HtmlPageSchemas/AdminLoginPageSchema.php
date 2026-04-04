<?php
namespace Core\Models\HtmlPageSchemas;
class AdminLoginPageSchema extends BaseHtmlPageSchema{
   
    /*---------------------------------------------------------------------------------------------------------------*/        
    public function buildSchema(): array{
         // $arrCss = ['/lib_assets/css/style.css', '/lib_assets/css/err.css',
       //     '/lib_assets/css/button.css', '/lib_assets/css/login.css'];
        return [ 'title' => ['type' => 'title'],
            'css'   => ['type' => 'css'],
            'script'   => ['type' => 'script'],
            'login' => ['type' => 'link_view','path_view' => CORE_PATH.'/views/login/admin_login.phtml']
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