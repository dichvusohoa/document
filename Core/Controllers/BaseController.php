<?php
namespace Core\Controllers;
use Core\Models\RequestAuthContext;
abstract class BaseController{
    protected RequestAuthContext  $requestAuthContext;
    /*---------------------------------------------------------------------------------------------------------------*/
    /*có thể không cần truyền $arrRouteTMCA vì khi tới các action thì các yếu tố đó đã rõ rồi*/
    function __construct(RequestAuthContext $requestAuthContext){
        $this->requestAuthContext = $requestAuthContext;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Mỗi controller con bắt buộc phải định nghĩa
     * cách xác định parameters cho các action của nó.
     * resolveParam căn cứ vảo $this->request, $strFunctionName mà tính ra giá trị các parameter
     */
    abstract protected function resolveParams(string $strFunctionName): array;
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Hàm tiện ích chung: 
     * Tự động gọi action tương ứng với tham số được resolve.
     */
    public function doAction(string $strFunctionName){
        if (!method_exists($this, $strFunctionName)) {
            throw new \BadMethodCallException("Action '$strFunctionName' không tồn tại trong " . static::class);
        }
        $params = $this->resolveParams($strFunctionName);
        return call_user_func_array([$this, $strFunctionName], $params);
    }
}
