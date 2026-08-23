<?php
namespace Core\User;
use UnexpectedValueException;
use JsonException;
use InvalidArgumentException;
use LogicException;
use Core\Database\DbService;
class UserService
{
    protected DbService $dbService;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getUserByToken(
        string $strLeftToken,
        string $strRightToken
    ): array {
        /*
         * fetchOne() mặc định $throwDbOnError = true.
         * Lỗi DB sẽ throw DataAccessException.
         */
        $arrResp = $this->dbService->fetchOne(
            'lib_spGetUserByToken',
            [
                'leftToken' => $strLeftToken
            ]
        );

        /*
         * Không tìm thấy selector hoặc token đã hết hạn.
         *
         * Đây không phải lỗi database hay lỗi contract.
         */
        if ($arrResp['data'] === null) {
            return $arrResp;
        }

        /*
         * hashed_validator là field phụ trợ của SP,
         * không thuộc UserInfo.
         *
         * Nếu SP trả thiếu hoặc sai kiểu thì đây là lỗi contract.
         */
        if (
            !array_key_exists(
                'hashed_validator',
                $arrResp['data']
            )
            || !is_string(
                $arrResp['data']['hashed_validator']
            )
        ) {
            throw new UnexpectedValueException(
                'lib_spGetUserByToken trả về field '
                . 'hashed_validator không hợp lệ.'
            );
        }

        $strHashedValidator =
            $arrResp['data']['hashed_validator'];

        /*
         * Verify validator do client gửi lên.
         */
        $strHashedRightToken = hash(
            'sha256',
            $strRightToken
        );

        if (
            !hash_equals(
                $strHashedValidator,
                $strHashedRightToken
            )
        ) {
            /*
             * Token không hợp lệ.
             *
             * Không phải lỗi framework/DB nên không throw.
             * Caller chỉ nhận kết quả không có user.
             */
            $arrResp['data'] = null;

            return $arrResp;
        }

        /*
         * hashed_validator không phải thành phần UserInfo.
         */
        unset(
            $arrResp['data']['hashed_validator']
        );

        /*
         * Từ đây data phải đúng contract UserInfo.
         */
        $arrResp['data'] = UserInfo::normalizeDbData(
            'lib_spGetUserByToken',
            $arrResp['data']
        );

        return $arrResp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getUserByNameAndRoles(
        string $strUser,
        array $arrContextAcceptedRole
    ): array {
        try {
            $strContextAcceptedRolesJson = json_encode(
                $arrContextAcceptedRole,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                'Lỗi khi mã hóa arrContextAcceptedRole thành JSON.',
                0,
                $e
            );
        }

        /*
         * fetchOne với $throwDbOnError = true:
         * lỗi DB sẽ throw Exception.
         */
        $arrResp = $this->dbService->fetchOne(
            'lib_spGetUserByNameAndRoles',
            [
                'pName'  => $strUser,
                'pRoles' => $strContextAcceptedRolesJson
            ]
        );

        /*
         * Không tìm thấy user phù hợp.
         */
        if ($arrResp['data'] === null) {
            return $arrResp;
        }

        /*
         * password là field riêng của dữ liệu xác thực,
         * không thuộc UserInfo cơ bản.
         */
        if (!isset($arrResp['data']['password'])) {
            throw new LogicException(
                'lib_spGetUserByNameAndRoles trả về thiếu field password '
                . 'hoặc password bằng null.'
            );
        }

        $strPassword = $arrResp['data']['password'];

        if (!is_string($strPassword) || $strPassword === '') {
            throw new LogicException(
                'lib_spGetUserByNameAndRoles trả về password không đúng format.'
            );
        }

        unset($arrResp['data']['password']);

        $arrResp['data'] = UserInfo::normalizeDbData(
            'lib_spGetUserByNameAndRoles',
            $arrResp['data']
        );

        $arrResp['extra'] = $strPassword;

        return $arrResp;
    }
}