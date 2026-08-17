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

    public function __getUserByToken(string $strLeftToken): array
    {
        $arrResp = $this->dbService->fetchOne(
            'lib_spGetUserByToken',
            ['leftToken' => $strLeftToken]
        );

        // 1. Kiểm tra ngay lỗi DB
        if (Response::isResponseError($arrResp)) {
            throw new RuntimeException(
                'Lỗi database trong khi lấy user by token'
            );
        }
        if (Response::isResponseEmpty($arrResp)) {
            return $arrResp;
        }
        /*
         * roles bắt buộc phải tồn tại và khác null.
         */
        $strFieldRoles = UserInfo::FIELD_ROLES;

        if (!isset($arrResp['data'][$strFieldRoles])) {
            throw new LogicException(
                "lib_spGetUserByToken trả về thiếu field {$strFieldRoles} "
                . 'của UserInfo hoặc giá trị bằng null'
            );
        }

        /*
         * registered_modules bắt buộc phải tồn tại,
         * nhưng giá trị null là hợp lệ trong bài toán no-module.
         */
        $strFieldRegisteredModules = UserInfo::FIELD_REGISTERED_MODULES;

        if (!array_key_exists(
            $strFieldRegisteredModules,
            $arrResp['data']
        )) {
            throw new LogicException(
                "lib_spGetUserByToken trả về thiếu field "
                . "{$strFieldRegisteredModules} của UserInfo"
            );
        }

        try {
            $arrResp['data'][$strFieldRoles] = json_decode(
                $arrResp['data'][$strFieldRoles],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if ($arrResp['data'][$strFieldRegisteredModules] !== null) {
                $arrResp['data'][$strFieldRegisteredModules] = json_decode(
                    $arrResp['data'][$strFieldRegisteredModules],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        } catch (JsonException $e) {
            throw new LogicException(
                'lib_spGetUserByToken trả về JSON không hợp lệ',
                0,
                $e
            );
        }

        if (!UserInfo::isValid($arrResp['data'])) {
            throw new LogicException(
                'lib_spGetUserByToken trả về dữ liệu không chuẩn format UserInfo'
            );
        }
        return $arrResp;
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