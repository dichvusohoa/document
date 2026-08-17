<?php
namespace Core\Routing;
use Core\Utility\ValidUtility;
class MiddlewareRegistry {
    protected array $arrMiddleware;
    public function __construct(RouteSegmentPatternParser $parser)
    {        
        $this->loadConfig($parser);
        //dự kiến có các hàm sau này, hiện nay chỉ có 1 dòng loadConfig
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadConfig(RouteSegmentPatternParser $parser){
        $this->arrMiddleware = [];
        $strFileName = 'config.middleware.php';
        $arrTmp = require CONFIG_PATH.'/'.$strFileName;
        if(!ValidUtility::isStringListMap($arrTmp, ['allow_string_value' => true])){
            throw new UnexpectedValueException("File {$strFileName} có format không phù hợp"); 
        }
        foreach ($arrTmp as $routePath => $fqcn) {
            if(is_string($fqcn)){
                $fqcn = [$fqcn];
            }
            foreach ($fqcn as $strFQCN){
                if (!class_exists($strFQCN)) {
                    throw new UnexpectedValueException("File {$strFileName}: class middleware '{$strFQCN}' không tồn tại");
                }
            }
            $expr = $parser->parse($routePath);
            $this->arrMiddleware[] = [
                //'expr' chuyển định dạng biểu thức của $strRoutePath ra dạng 
                //array['module'=> strExprModule, 'controller' => strExprController, 'action' => strExprAction 'method' => strExprMethod ,'role' => strExprRole
                'expr' => $expr,
                'fqcn' => $fqcn// mode aray
            ];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*$arrSegment có format 
     [
        'module' => strModuleValue,
        'controller' => strControllerValue,
        'action' => strActionValue,
        'method' => strMethodValue,
        'role'=> roles . Chú ý là roles có thể là single value hoặc là 1 array
    ]*/
    public function matchMiddlewares(array $arrSegment): array {
        $result = [];
        foreach ($this->arrMiddleware as $element) {
            if (!RouteSegmentPatternParser::match($element['expr'], $arrSegment)) {
                //$result[] = $element['fqcn'];
                continue;
            }
            foreach ($element['fqcn'] as $strFQCN) {
                // dùng $strFQCN làm key để lọc các $strFQCN trùng, tránh chạy một middleware 2 lần
                $result[$strFQCN] = true; 
            }
        }
        return array_keys($result);
        //return $result;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMiddlewareRegistry(): array{
        return $this->arrMiddleware;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function fromArray(array $arrMiddleware): self{
        $ref = new \ReflectionClass(self::class);
        /** @var self $obj */
        $obj = $ref->newInstanceWithoutConstructor();
        $obj->arrMiddleware = $arrMiddleware;
        return $obj;
    }
}