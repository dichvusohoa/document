<?php
namespace Core\User;
use Core\Http\Response;
use Core\Database\DbService;
use RuntimeException;
use LogicException;
use JsonException;

class UserService
{
    protected DbService $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }
    public function getUserByToken(string $strLeftToken): array
    {
        $arrResp = $this->dbService->fetchOne(
            'lib_spGetUserByToken',
            ['leftToken' => $strLeftToken]
        );
        return UserInfo::normalizeDbData('lib_spGetUserByToken', UserInfo::DB_DATA_BASIC, $arrResp);
    }
}