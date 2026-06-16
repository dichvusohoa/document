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
            ['field_name' => 'selector',         'field_type' => 'VARCHAR(30)'],
            ['field_name' => 'hashed_validator', 'field_type' => 'VARCHAR(255)'],
            ['field_name' => 'user_id',          'field_type' => 'INT'],
            ['field_name' => 'exp_date',         'field_type' => 'DATETIME'],
        ], JSON_UNESCAPED_UNICODE);

        $jsonRecords = json_encode([
            'selector'         => $authToken->leftToken(),
            'hashed_validator' => $authToken->hashedRightToken(),
            'user_id'          => $strUserId,
            'exp_date'         => $sExpDateTime,
        ], JSON_UNESCAPED_UNICODE);

        return $this->dbService->execActionSP('lib_spAdd', [
            'dbName'      => '',
            'tableName'   => 'auth_token',
            'jsonFields'  => $jsonFields,
            'jsonRecords' => $jsonRecords,
        ]);
    }
    
}