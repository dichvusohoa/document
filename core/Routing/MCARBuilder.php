<?php
namespace Core\Routing;
use UnexpectedValueException;
use Core\Utility\MathUtility;

class MCARBuilder {
    protected MCRoutePathParser $parser;
    protected array $arrMC2FQCN;
    protected array $arrFCAction;
    protected array $arrR;
    protected AuthRegistry $authRegistry;
    
    static protected string $strFileName    = 'config.mc2ra.php';
    static protected string $strFileName2   = 'config.mc.php';
    static protected string $strFileName3   = 'config.fc.action.php';
    static protected string $strFileName4   = 'config.role.php';
    static protected string $strFileName5   = 'config.mc2fc.php';
    public function __construct(
        MCRoutePathParser $parser,
        array $arrMC2FQCN,
        array $arrFCAction,
        array $arrR,
        AuthRegistry $authRegistry    
    ) {
        $this->parser      = $parser;
        $this->arrMC2FQCN  = $arrMC2FQCN;
        $this->arrFCAction = $arrFCAction;
        $this->arrR        = $arrR;
        $this->authRegistry = $authRegistry;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function build(): array {
        $strFileName = self::$strFileName;
        //$strFileName2 = self::$strFileName2;
        $arrTree = [];
        $arrData = require CONFIG_PATH . '/' . $strFileName;

        if (!is_array($arrData)) {
            throw new UnexpectedValueException("File {$strFileName} phải return một array");
        }

        foreach ($arrData as $strExprMC => $arrExprRA) {
            if (!is_string($strExprMC)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: thành phần module/controller phải là string"
                );
            }

            if (!is_array($arrExprRA)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: value tại route '{$strExprMC}' phải là array role/action"
                );
            }
            $arrMC = $this->parser->parse($strExprMC);
            $arrTree = $this->buildForOneRule($arrTree, $arrMC, $arrExprRA);
        }
        $this->validateAuthRouteRoleRules($arrTree);
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //ứng với 1 dòng config
    protected function buildForOneRule(array $arrTree, array $arrMC, array $arrExprRA): array {
        foreach ($arrMC as $pairMC) {
            $arrTree = $this->buildForOnePairMC($arrTree, $pairMC, $arrExprRA);
        }

        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //$pairMC: 1 cặp [module, controller] (module có thể khuyết  của 1 dòng config
    protected function buildForOnePairMC(array $arrTree, array $pairMC, array $arrExprRA): array {
        $strFileName = self::$strFileName;
        $strFileName5 = self::$strFileName5;

        $str = $pairMC[0];// module hoặc controller (nếu khuyết module)

        if (!isset($this->arrMC2FQCN[$str])) {
            throw new UnexpectedValueException(
                "Hai file config không tương thích. File {$strFileName} có đường dẫn {$str} nhưng không có đường dẫn {$str} trong file {$strFileName5}"
            );
        }

        $arrTree[$str] ??= [];//đi sâu vào một mức
        $refTree = $arrTree[$str];

        if (count($pairMC) === 2) {//$pairMC đủ cả module và controller
            $strController = $pairMC[1];
            $refTree[$strController] ??= [];

            if (!isset($this->arrMC2FQCN[$str][$strController])) {
                throw new UnexpectedValueException(
                    "Hai file config không tương thích. File {$strFileName} có đường dẫn {$str}/{$strController} nhưng không có đường dẫn {$str}/{$strController} trong file {$strFileName5}"
                );
            }

            $strFQCN = $this->arrMC2FQCN[$str][$strController];

            $refTree[$strController] = $this->buildAtRALevel(
                $strController,    
                $strFQCN,
                $arrExprRA,
                false,      
                $refTree[$strController]
            );
        } else {
            $strFQCN = $this->arrMC2FQCN[$str];

            $refTree = $this->buildAtRALevel(
                $str,    
                $strFQCN,
                $arrExprRA,
                $this->authRegistry->hasAuthController($str),    
                $refTree
            );
        }

        $arrTree[$str] = $refTree;
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildAtRALevel(string $strController ,
            string $strFQCN, 
            array $arrExprRA, 
            bool $isAuthenticationRoute,
            array $arrNode): array {
        $strFileName3 = self::$strFileName3;
        $arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA);
        $strRouteType = $isAuthenticationRoute ? 
                RouteInfo::ROUTE_TYPE_AUTHENTICATION : 
                RouteInfo::ROUTE_TYPE_BUSINESS;
        foreach ($arrPairRA as [$strRole, $strAction]) {
            //không cần kiểm tra !isset($this->arrFCAction[$strFQCN]) vì đã kiểm tra trong
            //$arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA) rồi
            if (!isset($this->arrFCAction[$strFQCN][$strAction])) {
                throw new UnexpectedValueException(
                    "File {$strFileName3}: class {$strFQCN}, action '{$strAction}' không tồn tại"
                );
            }

            $arrActionDetail = $this->arrFCAction[$strFQCN][$strAction];
             // Khởi tạo leaf nếu chưa tồn tại
            if (!isset($arrNode[$strAction])) {
                if (empty($arrActionDetail['method'])) {
                    throw new UnexpectedValueException(
                        "File {$strFileName3}: class {$strFQCN}, action '{$strAction}' thiếu khai báo method"
                    );
                }

                $arrNode[$strAction] = [
                    RouteInfo::FIELD_ROLES    => [],
                    RouteInfo::FIELD_FQCN     => $strFQCN,
                    RouteInfo::FIELD_FUNCTION => $arrActionDetail['function'] ?? $strAction,
                    RouteInfo::FIELD_METHOD   => strtoupper($arrActionDetail['method']),
                    RouteInfo::FIELD_ROUTE_TYPE => $strRouteType
                ];
            }
            // Bổ sung role
            if (!in_array($strRole, $arrNode[$strAction][RouteInfo::FIELD_ROLES], true)) {
                $arrNode[$strAction][RouteInfo::FIELD_ROLES][] = $strRole;
            }
        }
        if($strRouteType === RouteInfo::ROUTE_TYPE_AUTHENTICATION){
            foreach ($arrNode as $strAction => $value) {
                $arrNode[$strAction][RouteInfo::FIELD_AUTHENTICATION_PATH] = null;
            }
            
        }
        else{
            foreach ($arrNode as $strAction => $value) {
                $arrNode[$strAction][RouteInfo::FIELD_AUTHENTICATION_PATH] = 
                        $this->authRegistry->findAuthPathByRoles($arrNode[$strAction][RouteInfo::FIELD_ROLES]);
            }
        }
        return $arrNode;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
     //$strFQCN: fully qualified class name.
    //array $arrRAExpr: array các phần tử dạng [$strRExpr => $strAExpr]
    //mục tiêu là tạo ra array các phần tử dạng đơn giản [$strRole => $strAction]
    //$strFQCN có ý nghĩa là class name chứa các action trong $arrExpRA
    protected function parseExprRAList(string $strFQCN, array $arrExprRA): array {
       
        $strFileName = self::$strFileName;
        $strFileName3 = self::$strFileName3;
        $strFileName4 = self::$strFileName4;

        if (!isset($this->arrFCAction[$strFQCN])) {
            throw new UnexpectedValueException("File {$strFileName3}: không tìm thấy class {$strFQCN}");
        }

        $allAction = array_keys($this->arrFCAction[$strFQCN]);
        $arrTmp = [];

        foreach ($arrExprRA as $strExprR => $strExprA) {
            $arrRoleParse = RoutePattern::parse(
                $strExprR,
                $this->arrR
            );
            if ($arrRoleParse['type'] !== '' && $arrRoleParse['type'] !== 'role') {
                throw new UnexpectedValueException(
                    "File {$strFileName}: biểu thức {$strExprR} phải có type là role, chứ không được là {$arrRoleParse['type']}"
                );
            }
            $arrActionParse = RoutePattern::parse(
                $strExprA,
                $allAction
            );
            if ($arrActionParse['type'] !== '' && $arrActionParse['type'] !== 'action') {
                throw new UnexpectedValueException(
                    "File {$strFileName}: biểu thức {$strExprA} phải có type là action, chứ không được là {$arrActionParse['type']}"
                );
            }
            $arrTmp = array_merge(
                $arrTmp,
                MathUtility::cartesianProduct([
                    $arrRoleParse['values'],
                    $arrActionParse['values'],
                ])
            );
        }
        //loại bỏ các phần tử trùng lặp trong $arrTmp để tính ra $arrPairRA, $arrPairRA
        //là cấu trúc chứa các cặp [strRole, strAction] không trùng lặp
        $arrPairRA = [];
        $seen = [];
        foreach ($arrTmp as $item) {
            $key = implode('|', $item);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $arrPairRA[] = $item;
            }
        }

        return $arrPairRA;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //kiểm tra các nhánh đặc biệt authentication route để đảm bảo rằng
    // A = tập hợp các roles đầu vào của route
    // B = tập hợp các roles cho phép đầu ra sau khi xác thực của route
    // phải đảm bảo A giao B = empty
    protected function validateAuthRouteRoleRules(array $arrMCAR): void
    {
        foreach (
            $this->authRegistry->getAuthControllers()
            as $strController
        ) {
            $arrAuthPolicy =  $this->authRegistry->getAuthPolicy($strController);
            $arrAcceptedRoles = $arrAuthPolicy[AuthRegistry::FIELD_ACCEPTED_ROLES];
            foreach (
                $arrMCAR[$strController]
                as $strAction => $arrRouteInfo
            ) {
                $arrEntryRoles =
                    $arrRouteInfo[RouteInfo::FIELD_ROLES];
                $arrConflictedRoles = array_intersect(
                    $arrEntryRoles,
                    $arrAcceptedRoles
                );
                if (!empty($arrConflictedRoles)) {
                    throw new UnexpectedValueException(
                        "Authentication route '{$strController}/{$strAction}': "
                        . "entry roles và accepted roles không được giao nhau. "
                        . "Role bị trùng: "
                        . implode(', ', $arrConflictedRoles)
                    );
                }
            }
        }
    }
}