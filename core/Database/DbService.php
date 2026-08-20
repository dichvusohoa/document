<?php
namespace Core\Database;
use PDO;
use PDOStatement;
use PDOException;
use InvalidArgumentException;
use Core\Database\Connection\Connection;
use Core\Http\Response;
use Core\Foundation\ErrorHandler;
use Core\Utility\MathUtility;
/*Kết quả trả về [status =>, data => extra]
 * status có 2 loại SERVER_DB_ERR_STATUS và Response::SERVER_OK_STATUS
 */
class DbService{
    protected Connection $connection;
 /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(Connection $connection){
        $this->connection  = $connection;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setConnection(Connection $connection): void{
        $this->connection  = $connection;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Xử lý tập trung PDOException.
     *
     * $throwDbOnError = true:
     *     chuyển PDOException thành DataAccessException và ném cho tầng gọi.
     *
     * $throwDbOnError = false:
     *     Chuyển PDOException => DataAccessException => response lỗi database.
     *
     * Hàm này không xử lý các loại exception khác.
     */
    protected function handleDbException( PDOException $e, bool $throwDbOnError): array {
        $exception = new DataAccessException(
            'Không thể thực hiện thao tác database.',
            0,
            $e
        );

        if ($throwDbOnError) {
            throw $exception;
        }

        return ErrorHandler::toResponseFormat(
            $exception,
            Response::SERVER_DB_ERR_STATUS
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Đóng PDOStatement khi xử lý lỗi.
     *
     * Không để exception phát sinh trong quá trình cleanup
     * che mất PDOException gốc.
     */
    protected function cleanupStatement(?PDOStatement $pdoStatement): void {
        if ($pdoStatement === null) {
            return;
        }
        try {
            $pdoStatement->closeCursor();
        } catch (PDOException $e) {
            // Không ném lỗi nếu $pdoStatement->closeCursor() để tránh che mất lỗi database gốc.
            // Sau này có thể ghi log tại đây.
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*@description: Chạy các store procedure dạng tổng quát E= Exists,C= Count,I=Insert,U=Update D = Delete
     * @param
     *      $strSPName: Tên của store procedure 
     *      $arrParam: Mảng các tham số 
     * @return: int số phần tử bị ảnh hưởng
     *                    Exists SP:  return= 0 hoặc 1. 0 = không tồn tại 1= có tồn tại
     *                    Count SP:   số phần từ đếm được
     *                    Insert SP
     *                    Update SP  
     *                    DeleteSP:   return số phần tử bị xóa  
     * Hàm tự quản lý và đóng PDOStatement
     * Hàm này luôn ném PDOException khi database lỗi.
     * Hàm này luôn ném InvalidArgumentException khi $pdo->query('SELECT @total AS num;')->fetchColumn(0)
     * không cast được về số nguyên > 0
     */
    protected function innerExecActionSP(string $strSPName, array $arrParam): int{
        $pdo = $this->connection->get();
        //Begin tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....@total);"
        $arrArgument = array_map(
            static fn(string $strName): string => ":{$strName}",
            array_keys($arrParam)
        );
        $arrArgument[] = '@total';
        $strSQL = sprintf(
            'CALL %s(%s);',
            $strSPName,
            implode(', ', $arrArgument)
        );
        //End tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....@total);"
        $pdoStatement = $pdo->prepare($strSQL);
        foreach($arrParam as $strName=>$value){
            $pdoStatement->bindValue(":".$strName,$value);
        }
        $pdoStatement->execute();
        /*
         * Phải đóng result set của CALL trước khi chạy
         * SELECT @total trên cùng connection.
         */
        $pdoStatement->closeCursor();
        //return (int)$pdo->query('SELECT @total AS num;')->fetchColumn(0);
        $value = $pdo->query('SELECT @total AS num;')->fetchColumn(0);
        return MathUtility::toNonNegativeInt($value);
    }     
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Public operation thực thi stored procedure dạng action.
     */
    public function execActionSP(
        string $strSPName,
        array $arrParam,
        bool $throwDbOnError = true
    ): array {
        try {
            $num = $this->innerExecActionSP(
                $strSPName,
                $arrParam
            );

            return [
                'status' => Response::SERVER_OK_STATUS,
                'data'   => $num,
                'extra'  => null
            ];
        } catch (PDOException $e) {
            return $this->handleDbException(
                $e,
                $throwDbOnError
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*@description: Chạy các store procedure tổng quát dạng SELECT
     * @param
     *      pdoCont: connection
     *      $strSPName: Tên của store procedure 
     *      $arrParam: Mảng các tham số 
     * @return: dạng PDOStatement. Hàm này cung cấp PDOStatement để cho các hàm như fetchPageResult,... khai thác và format lại
     * Hàm này luôn ném PDOException khi database lỗi.
     * Caller (như fetchPageResult) chịu trách nhiệm fetch dữ liệu và đóng cursor.
    */
    protected function innerExecSelectSP(string $strSPName, array $arrParam): PDOStatement{
        //Begin tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....);"
        $strPlaceholders = implode(",", array_map(fn($k) => ":$k", array_keys($arrParam)));
        $strSQL = "CALL $strSPName($strPlaceholders);";
        //End tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....);"
        $pdoStatement = $this->connection->get()->prepare($strSQL);
        foreach($arrParam as $strName=>$value){
            $pdoStatement->bindValue(":".$strName,$value);
        }
        $pdoStatement->execute();
        return $pdoStatement;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildPagination(int $totalRows, int $pageIndex, int $pageSize): array {
        $minPageSize = min(ARR_PAGE_SIZE);
        $maxPageSize = max(ARR_PAGE_SIZE);
        if ($totalRows === 0) {
            return [
                'pageSize'        => $pageSize,
                'minPageSize'     => $minPageSize,
                'maxPageSize'     => $maxPageSize,
                'pageIndex'       => 0,
                'totalPages'      => 0,
                'startRow'        => 0,
                'endRow'          => 0,
                'totalRows'       => 0,
                'pageSizeOptions' => ARR_PAGE_SIZE
            ];
        }
        $totalPages = $pageSize === 0 ? 1 : (int)ceil($totalRows / $pageSize);
        if ($pageIndex > $totalPages - 1) {
            $pageIndex = $totalPages - 1;
        }
        $startRow = $pageSize === 0 ? 1 : $pageIndex * $pageSize + 1;
        $endRow = $pageSize === 0 ? $totalRows : min(($pageIndex + 1) * $pageSize, $totalRows);

        return [
            "pageSize"        => $pageSize,
            "minPageSize"     => $minPageSize,
            "maxPageSize"     => $maxPageSize,
            "pageIndex"       => $pageIndex,
            "totalPages"      => $totalPages,
            "startRow"        => $startRow,
            "endRow"          => $endRow,
            "totalRows"       => $totalRows,
            "pageSizeOptions" => ARR_PAGE_SIZE
        ];
    }
/*---------------------------------------------------------------------------------------------------------------*/
    /*
     * *Description: Trả về dữ liệu của database căn cứ theo các dữ liệu query đầu vào
    Params
     * $strCountSPName    : store procedure count số phần tử
     * $arrCountSPParam : các tham số cho count store procedure
     * $strSelectSPName     : store procedure liệt kê số phần tử
     * $arrSelectSPParam  : các tham số cho select store procedure
     * $arrSelectSPParam là nguồn duy nhất chứa giá trị pageIndex và pageSize.
     * $arrPaginationParamMap: format bắt buộc 
     * ['pageIndexParam' => tên param pageIndex trong $arrSelectSPParam dạng string, 
     *  'pageSizeParam' => tên param pageSize trong $arrSelectSPParam dạng string]
     * $throwDbOnError
    
    Return: Cấu trúc dạng
    ["status"=>Response::SERVER_OK_STATUS,"data"=>$arrData,"extra"=>$pagination];
     */
    public function fetchPageResult(
            string $strSelectSPName, 
            array $arrSelectSPParam, 
            string $strCountSPName, 
            array $arrCountSPParam, 
            array $arrPaginationParamMap, 
            bool $throwDbOnError = true): array{
        /*
         * Các lỗi validation không phải lỗi database,
         * vì vậy được đặt ngoài phạm vi catch PDOException.
         */
        $this->validatePaginationParamMap(
            $arrPaginationParamMap,
            $arrSelectSPParam
        );

        $strPageIndexParam =
            $arrPaginationParamMap['pageIndexParam'];

        $strPageSizeParam =
            $arrPaginationParamMap['pageSizeParam'];

        $iPageIndex = MathUtility::toNonNegativeInt(
            $arrSelectSPParam[$strPageIndexParam]
        );

        $iPageSize = MathUtility::toNonNegativeInt(
            $arrSelectSPParam[$strPageSizeParam]
        );

        $pdoStatement = null;

        try {
            $totalRows = $this->innerExecActionSP(
                $strCountSPName,
                $arrCountSPParam
            );
            $pagination = $this->buildPagination(
                $totalRows,
                $iPageIndex,
                $iPageSize
            );

            if ($totalRows === 0) {
                return [
                    'status' => Response::SERVER_OK_STATUS,
                    'data'   => [],
                    'extra'  => $pagination
                ];
            }

            /*
             * Đồng bộ pagination đã được chuẩn hóa vào
             * parameter thực tế của SELECT SP.
             */
            $arrSelectSPParam[$strPageIndexParam] =
                $pagination['pageIndex'];

            $arrSelectSPParam[$strPageSizeParam] =
                $pagination['pageSize'];

            $pdoStatement = $this->innerExecSelectSP(
                $strSelectSPName,
                $arrSelectSPParam
            );

            $arrData = $pdoStatement->fetchAll(
                PDO::FETCH_ASSOC
            );
            $pdoStatement->closeCursor();
            $pdoStatement = null;
            return [
                'status' => Response::SERVER_OK_STATUS,
                'data'   => $arrData,
                'extra'  => $pagination
            ];
        } catch (PDOException $e) {
            $this->cleanupStatement($pdoStatement);
            return $this->handleDbException(
                $e,
                $throwDbOnError
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Trường hợp sử dụng riêng fetchPageResult based trên các nền tảng của PBT là 2 stored procedure
    lib_spSelect và lib_spCount */
    public function fetchLibPageResult(array $arrSelectSPParam, bool $throwDbOnError = true):array{
        $arrCountSPParam = [
            'selectClause'  => $arrSelectSPParam['selectClause'],
            'jsonWhere'     => $arrSelectSPParam['jsonWhere'],
            'jsonHaving'    => $arrSelectSPParam['jsonHaving'],
            'groupByClause' => $arrSelectSPParam['groupByClause']
        ];

    
        return $this->fetchPageResult(
            'lib_spSelect',
            $arrSelectSPParam,
            'lib_spCount',
            $arrCountSPParam,
            ['pageIndexParam' => 'pageIndex', 'pageSizeParam'  => 'pageSize'],
            $throwDbOnError
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
     /**
     * @description: Chạy stored procedure SELECT và trả về toàn bộ hoặc một dòng.
     * @param string $strSPName: Tên stored procedure
     * @param array $arrParam: Tham số đầu vào
     * @param bool $onlyOne: Nếu true thì chỉ trả về 1 dòng
     * @return array: Kết quả có dạng ['status'=>..., 'data'=>..., 'extra'=>...]
     */
    public function fetchResult(
        string $strSPName,
        array $arrParam,
        bool $onlyOne = false,
        bool $throwDbOnError = true
    ): array {
        $pdoStatement = null;
        try {
            $pdoStatement = $this->innerExecSelectSP(
                $strSPName,
                $arrParam
            );

            if ($onlyOne) {
                $row = $pdoStatement->fetch(PDO::FETCH_ASSOC);
                $data = $row === false ? null : $row;
            } else {
                $data = $pdoStatement->fetchAll(
                    PDO::FETCH_ASSOC
                );
            }
            $pdoStatement->closeCursor();
            $pdoStatement = null;
            return [
                'status' => Response::SERVER_OK_STATUS,
                'data'   => $data,
                'extra'  => null
            ];
        } catch (PDOException $e) {
            /*
            * Chỉ cleanup dự phòng sau khi đã có lỗi gốc.
            * Không để lỗi cleanup che lỗi $e.
            */
            $this->cleanupStatement($pdoStatement);
            return $this->handleDbException(
                $e,
                $throwDbOnError
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function fetchAll(string $strSPName, array $arrParam, bool $throwDbOnError = true): array {
        return $this->fetchResult($strSPName, $arrParam, false, $throwDbOnError);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function fetchOne(string $strSPName, array $arrParam, bool $throwDbOnError = true): array {
        return $this->fetchResult($strSPName, $arrParam, true, $throwDbOnError);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validatePaginationParamMap(array $arrPaginationParamMap, array $arrSelectSPParam ): void {
        $arrRequiredKey = ['pageIndexParam', 'pageSizeParam'];
        foreach ($arrRequiredKey as $strKey) {
            if (!array_key_exists($strKey, $arrPaginationParamMap)) {
                throw new InvalidArgumentException(
                    "Pagination parameter map thiếu key '{$strKey}'."
                );
            }
            $strParamName = $arrPaginationParamMap[$strKey];
            if (
                !is_string($strParamName) || trim($strParamName) === ''
            ) {
                throw new InvalidArgumentException(
                    "Pagination parameter map tại '{$strKey}' phải là tên parameter không rỗng."
                );
            }
            if ($strParamName !== trim($strParamName)) {
                throw new InvalidArgumentException(
                    "Tên parameter tại '{$strKey}' là '{$strParamName}' hiện đang chứa khoảng trắng không được phép."
                );
            }
            
            if (!array_key_exists($strParamName, $arrSelectSPParam)) {
                throw new InvalidArgumentException(
                    "Select SP parameter '{$strParamName}' được map từ '{$strKey}' nhưng không tồn tại."
                );
            }
        }
        $arrExtraKey = array_diff(
            array_keys($arrPaginationParamMap),
            $arrRequiredKey
        );

        if ($arrExtraKey !== []) {
            throw new InvalidArgumentException(
                'Pagination parameter map có key không hợp lệ: '
                . implode(', ', $arrExtraKey)
            );
        }
        if ($arrPaginationParamMap['pageIndexParam'] === $arrPaginationParamMap['pageSizeParam']){
            throw new InvalidArgumentException(
                'pageIndexParam và pageSizeParam không được map vào cùng một parameter.'
            );
        }
    }
}