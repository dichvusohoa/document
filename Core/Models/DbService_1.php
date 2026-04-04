<?php
namespace Core\Models;
use \PDO;
use \PDOException;
class DbService{
    //begin mã lỗi trả chạy sau thực hiện các lệnh sql
    //Begin: client sẽ post lên máy chủ dữ liệu dạng các row gồm nhiều field data. Khi có lỗi server sẽ trả về mô tả lỗi dạng
    //array có định vị đến từng row và từng field. Dưỡi đây là cac mã lỗi

    const PDO_NO_ERR = "00000"; // mã lỗi OK của MSQL
    /*---------------------------------------------------------------------------------------------------------------*/
    protected $pdoCont;

    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(PDO $pdoCont){
        $this->pdoCont  = $pdoCont;
        //$this->strDbName  = $strDbName;//strDbName = "" tức là Db hiện thời, không cần truyền tham số tên DB nữa
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setPDOConnect(PDO $pdoCont): void{
        $this->pdoCont  = $pdoCont;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*@description: Chạy các store procedure dạng tổng quát E= Exists,C= Count,I=Insert,U=Update D = Delete
     * @param
     *      pdoCont: connection
     *      $strSPName: Tên của store procedure 
     *      $arrParam: Mảng các tham số 
     * @return: dạng object
     *      return.status: Tình trạng lỗi: Có 02 mã ERR_STATUS, OK_STATUS. Lưu ý là sau này các hàm và class nâng cao hơn mỡi bổ sung thêm các mã lỗi như là LOGIC_ERR_STATUS. 
     *      return.data : Nếu return.status là OK thì return.data = số phần tử bị ảnh hưởng
     *                    Exists SP:  return.data= 0 hoặc 1. 0 = không tồn tại 1= có tồn tại
     *                    Count SP:   return.data= số phần từ đếm được
     *                    Insert SP
     *                    Update SP  
     *                    DeleteSP:   return.data= số phần tử bị xóa  
     *                    Nếu return.status là ERR thì return.data= Mã lỗi
     *      return.extra: Nếu return.status là ERR thì return.extra = Mô tả lỗi chi tiết
    */
    public function execActionSP(string $strSPName, array $arrParam): array{
        //Begin tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....@total);"
        try{
            $strPlaceHolders = implode(",", array_map(fn($k) => ":$k", array_keys($arrParam)));
            //End tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....@total);"
            $strSQL = "CALL $strSPName($strPlaceHolders, @total);";
            $pdoStatement = $this->pdoCont->prepare($strSQL);
            foreach($arrParam as $strName=>$value){
                $pdoStatement->bindValue(":".$strName,$value);
            }
            $pdoStatement->execute();
            $pdoStatement->closeCursor();
            $num = (int)$this->pdoCont->query("SELECT @total AS num;")->fetchColumn(0);
            //$pdoStatement->closeCursor();
            return array("status"=> Response::SERVER_OK_STATUS, "data"=>$num, "extra"=>"");
        }
        catch(PDOException $e){
            return ErrorHandler::toResponseFormat($e, Response::SERVER_DB_ERR_STATUS);
        }
    }     
    /*---------------------------------------------------------------------------------------------------------------*/
    /*@description: Chạy các store procedure tổng quát dạng SELECT
     * @param
     *      pdoCont: connection
     *      $strSPName: Tên của store procedure 
     *      $arrParam: Mảng các tham số 
     * @return: dạng object
     *      return.status: Tình trạng lỗi
     *      return.data: Nếu return.status là OK thì return.data= pdoStatement data dữ liệu
     *                    Nếu return.status là ERR thì return.data= Mã lỗi
     *      return.extra: Nếu return.status là ERR thì return.extra = Mô tả lỗi chi tiết
    */
    public function execSelectSP(string $strSPName, array $arrParam): array{
        //Begin tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....);"
        try{
            $strPlaceholders = implode(",", array_map(fn($k) => ":$k", array_keys($arrParam)));
            $strSQL = "CALL $strSPName($strPlaceholders);";
            //End tạo chuỗi kiểu như: "CALL strSPName(:x,:y,....);"
            $pdoStatement = $this->pdoCont->prepare($strSQL);
            foreach($arrParam as $strName=>$value){
                $pdoStatement->bindValue(":".$strName,$value);
            }
            $pdoStatement->execute();
            return array("status"=>Response::SERVER_OK_STATUS,"data"=>$pdoStatement,"extra"=>"");
        }
        catch (PDOException $e){
            //sử dụng cơ chế này để khi ghép nhiều mảnh dữ liệu lại thì cho phép
            //một số mảnh không quan trọng bị lỗi
            return ErrorHandler::toResponseFormat($e, Response::SERVER_DB_ERR_STATUS);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildPagination(int $totalRows, int $pageIndex, int $pageSize): array {
        $minPageSize = min(ARR_PAGE_SIZE);
        $maxPageSize = max(ARR_PAGE_SIZE);
        if ($pageSize === 0) {
            $totalPages = 1;
        } else {
            $totalPages = (int)ceil($totalRows / $pageSize);
        }

        if ($pageIndex > $totalPages - 1) {
            $pageIndex = max(0, $totalPages - 1);
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
     * $iPageIndex    : trang hiện tại, zero index
     * $iPageSize : số phần tử trong một trang. $iPageSize = 0 => không phân trang

     * chú ý là chấp nhận dư thừa dữ liệu về pageIndex, pageSize, tức là trong 
     * $arrSelectSPParam cũng phải chứa thông tin pageindex, pageSize. Lý do là vì 
     * arrSelectSPParam phải chứa pageindex, pageSize thì execSelectSP mới chạy được
     * còn 2 biến kiểu INT $iPageIndex,$iPageSize cũng phải có thì 
     * fetchPageResultInternal mới hoạt động được
    Return: Cấu trúc dạng
    ["status"=>Response::SERVER_OK_STATUS,"data"=>$arrData,"extra"=>$pagination];
     */
    protected function fetchPageResultInternal(string $strSelectSPName, array $arrSelectSPParam, 
    string $strCountSPName, array $arrCountSPParam, int $iPageIndex, int $iPageSize): array{
        $exec     = $this->execActionSP($strCountSPName,$arrCountSPParam);
        if($exec["status"]!==Response::SERVER_OK_STATUS){
            return $exec;
        }
        $totalRows = $exec['data'];
        if($totalRows <= 0){//không có dữ liệu
            return array("status"=>Response::SERVER_OK_STATUS,"data"=>[],"extra"=>"Không có dữ liệu");
        }
        $exec = $this->execSelectSP($strSelectSPName,$arrSelectSPParam);
        if($exec["status"] !== Response::SERVER_OK_STATUS){
            return $exec;
        }    
        $pdoStatement  = $exec['data'];
        $pagination = $this->buildPagination($totalRows, $iPageIndex, $iPageSize);
        $arrData = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $result = ["status"=>Response::SERVER_OK_STATUS,"data"=>$arrData,"extra"=>$pagination];
        $pdoStatement->closeCursor();
        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function fetchPageResult(string $strSelectSPName, array $arrSelectSPParam, ?string $strCountSPName = null, ?array $arrCountSPParam = null, int $iPageIndex = 0, int $iPageSize = 0){
        if( $strSelectSPName === "lib_spSelect" && 
            $strCountSPName === null && $arrCountSPParam === null && 
            $iPageIndex === 0 && $iPageSize === 0){
            $strCountSPName     = "lib_spCount";
            $arrCountSPParam    = [];
            $arrCountSPParam["selectClause"]  = $arrSelectSPParam["selectClause"];
            $arrCountSPParam["jsonWhere"]     = $arrSelectSPParam["jsonWhere"];
            $arrCountSPParam["jsonHaving"]    = $arrSelectSPParam["jsonHaving"];
            $arrCountSPParam["groupByClause"] = $arrSelectSPParam["groupByClause"];
            $iPageIndex                       = $arrSelectSPParam["pageIndex"] ?? 0;
            $iPageSize                        = $arrSelectSPParam["pageSize"] ?? 0;
        }
        return $this->fetchPageResultInternal($strSelectSPName, $arrSelectSPParam, $strCountSPName, $arrCountSPParam, $iPageIndex, $iPageSize);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
     /**
     * @description: Chạy stored procedure SELECT và trả về toàn bộ hoặc một dòng.
     * @param string $strSPName: Tên stored procedure
     * @param array $arrParam: Tham số đầu vào
     * @param bool $onlyOne: Nếu true thì chỉ trả về 1 dòng
     * @return array: Kết quả có dạng ['status'=>..., 'data'=>..., 'extra'=>...]
     */
    function fetchResult(string $strSPName, array $arrParam, bool $onlyOne = false): array {
        $exec = $this->execSelectSP($strSPName, $arrParam);
        if ($exec["status"] !== Response::SERVER_OK_STATUS) {
            return $exec;
        }
        $pdoStatement = $exec['data'];
        $data = $onlyOne 
            ? $pdoStatement->fetch(PDO::FETCH_ASSOC) 
            : $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $pdoStatement->closeCursor();
        return ["status" => Response::SERVER_OK_STATUS, "data" => $data, "extra" => ""];
    }
    /*---------------------------------------------------------------------------------------------------------------*/

    function fetchAll(string $strSPName, array $arrParam) {
        return $this->fetchResult($strSPName, $arrParam, false);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    function fetchOne(string $strSPName, array $arrParam) {
        return $this->fetchResult($strSPName, $arrParam, true);
    }
}