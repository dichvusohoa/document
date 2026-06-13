<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Database\DbService;
/*prefix authSrvc*/
class AuthTokenService{
    protected DbService $dbService;
    function __construct(DbService $dbService){
        $this->dbService    = $dbService;
        
    }
    public function tokenToDB(AuthToken $authToken, $strUserId){
        $sExpDateTime = date('Y-m-d H:i:s', strtotime(COOKIE_EXP_BY_DAYS . ' day', time()));

        $jsonFields = json_encode([
            ['field_name' => 'leftToken',        'field_type' => 'VARCHAR(255)'],
            ['field_name' => 'hashedRightToken', 'field_type' => 'VARCHAR(255)'],
            ['field_name' => 'userId',           'field_type' => 'INT'],
            ['field_name' => 'expDate',          'field_type' => 'DATETIME'],
        ], JSON_UNESCAPED_UNICODE);

        $jsonRecords = json_encode([
            'leftToken'        => $authToken->leftToken(),
            'hashedRightToken' => $authToken->hashedRightToken(),
            'userId'           => $strUserId,
            'expDate'          => $sExpDateTime,
        ], JSON_UNESCAPED_UNICODE);

        return $this->dbService->execActionSP('lib_spAdd', [
            'dbName'      => '',
            'tableName'   => 'auth_token',
            'jsonFields'  => $jsonFields,
            'jsonRecords' => $jsonRecords,
        ]);
    }
    
}