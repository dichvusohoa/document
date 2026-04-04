<?php 
    require_once "deploy.php";
//    define("BASE_URL","/");
    $languages_id =1;
    require_once APPLICATION_PATH."views/languages/lang.vi.php";
    require_once APPLICATION_PATH."controllers/sysenv.php"; 
    require_once APPLICATION_PATH."controllers/sysenvdb.php";
    session_start();
    $pdoCont            = Connection::getConnectToCommonDB();
    $errCode=filter_input(INPUT_GET,"err",FILTER_SANITIZE_STRING,array("options" => array("default" => 404)));
    if(isset($_SESSION["error_message"]) && $_SESSION["error_message"] !==""){
        $errDescription = $_SESSION["error_message"];
    }
    else{
        switch ($errCode) {
            case 404:
            $errDescription = "Không tìm thấy đường dẫn trên";
            break;
            case "dberror":
            $errDescription = "Truy cập cơ sở dữ liệu bị lỗi";
            break;
            case "access_denied":
            $errDescription = "Không có đủ quyền truy cập dữ liệu";
            break;
            default:
            $errDescription = "Page error";
        }
    }
    $sFormatErrCode = Utility::showErrorCode($errCode);
    $sPathHomePage=filter_input(INPUT_GET,"pathHP",FILTER_SANITIZE_URL,array("options" => array("default" =>"/")));
    $extAHead = new Head();
    $execSoftware = Utility::getSoftwareInfo($pdoCont);
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="vi" lang="vi">
        <head>
        <meta  http-equiv="content-type" content="text/html; charset=utf-8">';
    echo    $extAHead->metaTag();
    echo    '<title>'.$sFormatErrCode." | ".$execSoftware["info"].'</title>';
    echo    $extAHead->headLinks();
   // echo    $extAHead->headScripts();
    echo'</head>
        <body>
            <div id="outer">
                <nav pos="upper"></nav>
                <header></header>
                <main data-code="err">
                    <p class=err--code>'.$sFormatErrCode.'</p>
                    <p>'.$errDescription.'</p>
                    <p>Quay lại <a href="'.$sPathHomePage.'">TRANG CHỦ</a></p>
                </main>';
    echo        '<div id="status"></div>
                <footer>';
                    $dbTblSupportOnline = new DbTable($pdoCont);   
                    $execSupportOnline = $dbTblSupportOnline->getRowData("spListSupportOnline",["languageId"=>1]);
                    $exec = require_once APPLICATION_PATH."views/index/footer.phtml";	
                    echo Utility::showData($exec);
    echo        '</footer>
            </div>    
        </body>
    </html>';