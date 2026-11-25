<?php
declare(strict_types=1);

namespace Core\Controller\Login;

use Core\Normalization\BaseRequestSchema;
use LogicException;

/** Schema của LoginPageController; nội dung theo function sẽ hoàn thiện sau. */
class LoginRequestSchema extends BaseRequestSchema
{
    protected function defineSchema(string $strFunctionName): ?array
    {
        /*
         * TODO: xác định schema cho từng function của LoginPageController.
         * Payload login hiện có POST user[login], user[password] và
         * cf-turnstile-response; cần chốt cách biểu diễn dữ liệu lồng này.
         * Chỉ trả null cho function đã xác định không cần normalization.
         * Chưa trả null mặc định để tránh âm thầm bỏ qua kiểm tra dữ liệu.
         */
        throw new LogicException('LoginRequestSchema::defineSchema() chưa được triển khai.');
    }
}
