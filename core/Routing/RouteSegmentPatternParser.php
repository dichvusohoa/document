<?php
namespace Core\Routing;
use InvalidArgumentException;
class RouteSegmentPatternParser {
    protected array $arrM;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(array $arrM) {
        $this->arrM   = $arrM;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function parse(string $strRoutePath): array {
        //chuẩn hóa lại $strRoutePath
        $strRoutePath = $this->normalizeRoutePath($strRoutePath);
        return $this->buildFromRoutePath($strRoutePath);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //chuẩn hóa lại $strRoutePath, các segment khuyết type (plain segment) thì cần viết lại
    protected function normalizeRoutePath(string $strRoutePath): string {
        $strRoutePath = trim($strRoutePath);
        if ($strRoutePath === '') {
            throw new InvalidArgumentException('Route path không được rỗng');
        }
        $segments = array_map('trim', explode('/', $strRoutePath));
        if (in_array('', $segments, true)) {
            throw new InvalidArgumentException("Route path '{$strRoutePath}' có segment rỗng");
        }
        $plainSegments = [];
        $typedSegments = [];
        $hasTyped = false;
        foreach ($segments as $segment) {
            if ($this->isTypedSegment($segment)) {
                $hasTyped = true;
                $typedSegments[] = $segment;
                continue;
            }
           
            if ($hasTyped){
                throw new InvalidArgumentException(
                    "Route path '{$strRoutePath}': các plain segment phải nằm liên tục ở các vị trí đầu tiên"
                );
            }
            $plainSegments[] = $segment;
            if (count($plainSegments) >= 4) {
                $iNum = count($plainSegments);
                throw new InvalidArgumentException(
                    "Route path '{$strRoutePath}' đang có {$iNum} plain segment, quá mức tối đa là 3 plain segment nằm liên tục ở các vị trí đầu tiên"
                );
            }
        }
        if (count($plainSegments) === 0) {
            return implode('/', $segments);
        }
        return implode('/', array_merge(
            $this->normalizePlainSegments($plainSegments),
            $typedSegments
        ));
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizePlainSegments(array $plainSegments): array {
        $plainCount = count($plainSegments);
        if ($plainCount === 3) {
            return [
                "[module:{$plainSegments[0]}]",
                "[controller:{$plainSegments[1]}]",
                "[action:{$plainSegments[2]}]",
            ];
        }
        if ($plainCount === 2) {
            if (in_array($plainSegments[0], $this->arrM, true)) {
                return [
                    "[module:{$plainSegments[0]}]",
                    "[controller:{$plainSegments[1]}]",
                ];
            }

            return [
                "[controller:{$plainSegments[0]}]",
                "[action:{$plainSegments[1]}]",
            ];
        }
        if ($plainCount === 1) {
            if (in_array($plainSegments[0], $this->arrM, true)) {
                return ["[module:{$plainSegments[0]}]"];
            }

            return ["[controller:{$plainSegments[0]}]"];
        }

        throw new InvalidArgumentException('Route path không hợp lệ');
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //Chú ý $strRoutePath giờ đã là 1 đầu vào được chuẩn hóa
    protected function buildFromRoutePath(string $strRoutePath): array {
        $result = MCARMeInfo::createEmpty();
        foreach (explode('/', $strRoutePath) as $segment) {
            if (!preg_match('/^\[(\w+):(.+)\]$/', $segment, $matches)) {
                throw new InvalidArgumentException(
                    "Segment '{$segment}' không đúng format [type:value]"
                );
            }

            $type = $matches[1];
            $arrType = array_keys($result);
            if (!in_array($type, $arrType, true)) {
                throw new InvalidArgumentException(
                    "Segment type '{$type}' không hợp lệ"
                );
            }
            //đây là lỗi có nhiều segment trùng type
            if ($result[$type] !== null) {
                throw new InvalidArgumentException(
                    "Route path '{$strRoutePath}' khai báo trùng segment '{$type}'"
                );
            }

            $result[$type] = $segment;
        }

        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Hàm này rất quan trọng. Nó sử dụng trong hàm matchUri của ContextRouter để quyết định xem có gắn middleware vào 1 leaf
    của StaticRouter không 
    $arrSegmentExp: 1 route ( lấy từ 1 line trong config.middleware.php
    được biến đổi ra format 
     [
        'module' => strModuleExpr, 
        'controller' => strControllerExpr,
        'action' => strActionExpr,
        'method' => strMethodExpr,
        'role'=> strRoleExpr
    ]
    $arrSegment có format 
     [
        'module' => strModuleValue,
        'controller' => strControllerValue,
        'action' => strActionValue,
        'method' => strMethodValue,
        'role'=> roles . Chú ý là roles có thể là single value hoặc là 1 array
    ]
    Logic kiểm tra của hàm này là AND. tức là mọi điều kiện (trong $arrSegmentExp) phải được $arrSegment
    đáp ứng thỏa mãn
     */
    public static function match(array $arrSegmentExp, array $arrSegment): bool {
        foreach ($arrSegmentExp as $strType => $strExpr) {
            if ($strExpr === null) { //không có điều kiện yêu cấu tại $strType, bỏ qua
                continue;
            }
            //có điều kiện yêu cầu tại $strType ($strExpr!==null), 
            //nhưng $arrSegment không có giá trị xác định tại $strType
            if (!array_key_exists($strType, $arrSegment) || $arrSegment[$strType] === null) {
                return false;
            }
            //chỉ cần 1 điều kiện trong $arrSegmentExp không thỏa mãn là trả về false
            if(!self::matchSegmentExpr($strExpr,$arrSegment[$strType])){
                return false;
            }
        }
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function matchSegmentExpr(string $strExpr, string|array $values): bool {
        $arrFilter = RoutePattern::filter($strExpr, $values);

        $arrMatchedValues = $arrFilter['values'];
        $exprType         = $arrFilter['type'];
        $arrValues        = is_array($values) ? $values : [$values];

        /*
            Ý nghĩa chung:

            RoutePattern::filter($strExpr, $values) trả về các value của $values
            còn sống sót sau khi áp dụng expression.

            Ví dụ:
            - [role:admin|editor], values = ['admin', 'user']
              => filter còn ['admin']

            - [role:!guest], values = ['admin', 'user']
              => filter còn ['admin', 'user']

            - [role:!guest], values = ['admin', 'guest']
              => filter còn ['admin']

            Luật match:

            1. Với RoutePattern::EXPR_ALL_VALUES, RoutePattern::EXPR_INCLUDE_VALUES, RoutePattern::EXPR_SINGLE_VALUE:
               Chỉ cần filter còn ít nhất 1 value là match.

            2. Với RoutePattern::EXPR_EXCLUDE_VALUES:
               Tất cả value đầu vào đều phải sống sót.
               Nếu có value bị loại, tức là resource/user có dính vào nhóm bị cấm,
               thì không match.

            Nói ngắn gọn:
            - include: match nếu giao nhau khác rỗng.
            - exclude: match nếu không có phần tử nào bị loại.
        */

        if ($exprType === RoutePattern::EXPR_EXCLUDE_VALUES) {
            return count($arrMatchedValues) === count($arrValues);
        }

        return count($arrMatchedValues) >= 1;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function isTypedSegment(string $segment): bool {
        return preg_match('/^\[\w+:.+\]$/', $segment) === 1;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function createEmpty(): array {
        return [
            'module'     => null,
            'controller' => null,
            'action'     => null,
            'method'     => null,
            'role'       => null,
        ];
    }
}