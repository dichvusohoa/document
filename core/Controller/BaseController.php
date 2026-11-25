<?php
namespace Core\Controller;
use BadMethodCallException;
use Core\Http\RequestAuthContext;
use Core\Http\Response;
use Core\Normalization\BaseRequestSchema;
use Core\Normalization\RequestNormalizer;
use InvalidArgumentException;
abstract class BaseController{
    protected RequestAuthContext  $requestAuthContext;
    protected ?RequestNormalizer $requestNormalizer;
    protected ?BaseRequestSchema $requestSchema;
    /*---------------------------------------------------------------------------------------------------------------*/
    /*có thể không cần truyền $arrRouteMCA vì khi tới các action thì các yếu tố đó đã rõ rồi*/
    public function __construct(
        RequestAuthContext $requestAuthContext,
        ?RequestNormalizer $requestNormalizer = null,
        ?BaseRequestSchema $requestSchema = null
    ) {
        if (
            ($requestNormalizer === null && $requestSchema !== null)
            || ($requestNormalizer !== null && $requestSchema === null)
        ) {
            throw new InvalidArgumentException(
                'RequestNormalizer và BaseRequestSchema phải cùng có hoặc cùng null.'
            );
        }
        $this->requestAuthContext = $requestAuthContext;
        $this->requestNormalizer = $requestNormalizer;
        $this->requestSchema = $requestSchema;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRequestAuthContext(): RequestAuthContext {
        return $this->requestAuthContext;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Mỗi controller con bắt buộc phải định nghĩa
     * cách xác định parameters cho các action của nó.
     * argumentsForFunction căn cứ vảo $this->requestAuthContext, $strFunctName mà tính ra giá trị các parameter
     */
    abstract protected function argumentsForFunction(string $strFunctName): array;
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Hàm tiện ích chung: 
     * Tự động gọi action tương ứng với tham số được resolve.
     */
    public function doFunction(string $strFunctionName, bool $normalizeInput = true){
        if (!method_exists($this, $strFunctionName)) {
            throw new BadMethodCallException("Function '$strFunctionName' không tồn tại trong " . static::class);
        }
        if ($normalizeInput && $this->requestNormalizer !== null && $this->requestSchema !== null) {
            $arrError = $this->requestNormalizer->normalize($strFunctionName, $this->requestSchema);
            if ($arrError !== null) {
                return [
                    'status' => Response::SERVER_LOGIC_ERR_STATUS,
                    'data' => null,
                    'extra' => $arrError,
                ];
            }
        }
        $params = $this->argumentsForFunction($strFunctionName);
        return call_user_func_array([$this, $strFunctionName], $params);
    }
}
