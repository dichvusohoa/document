<?php
namespace Core\User;
use Core\Database\DbService;
use JsonException;
use InvalidArgumentException;
class UserService
{
    protected DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }
    public function getUserByToken(string $strLeftToken): array
    {
        //hàm fetchOne gọi với tham số $throwDbOnError = true, throw ra Exception nếu lỗi DB
        $arrResp = $this->dbService->fetchOne(
            'lib_spGetUserByToken',
            ['leftToken' => $strLeftToken]
        );
        //tới đây $arrResp['data'] chắc chắn bằng Response::SERVER_OK_STATUS
        if($arrResp['data'] !== null){
            $arrResp['data'] = UserInfo::normalizeDbData2(
                    'lib_spGetUserByToken',
                    UserInfo::DB_DATA_BASIC,
                    $arrResp['data']
                    );
        }
        return $arrResp;
        
    }
    public function getUserByNameAndRoles(string $strUser, array $arrContextAcceptedRole): array{
        try{
            $strContextAcceptedRolesJson = json_encode(
                $arrContextAcceptedRole,
                JSON_THROW_ON_ERROR
            );
        }
        catch(JsonException $e){
            throw new InvalidArgumentException('Lỗi khi mã hóa tham số arrContextAcceptedRole thành chuỗi bằng hàm  json_encode');
        }
        //hàm fetchOne gọi với tham số $throwDbOnError = true, throw ra Exception nếu lỗi DB
        $arrResp = $this->dbService->fetchOne('lib_spGetUserByNameAndRoles',
            ['pName' => $strUser, 'pRoles' => $strContextAcceptedRolesJson]);
        //tới đây $arrResp['data'] chắc chắn bằng Response::SERVER_OK_STATUS
        if($arrResp['data'] !== null){
            $arrResp['data'] = UserInfo::normalizeDbData2(
                    'lib_spGetUserByNameAndRoles',
                    UserInfo::DB_DATA_WITH_PASSWORD,
                    $arrResp['data']
                    );
        }
        return $arrResp;
        
    }
}