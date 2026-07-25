<?php

namespace Core\Database;

use RuntimeException;
use Throwable;

/**
 * Đại diện cho lỗi xảy ra trong quá trình truy cập hoặc xây dựng dữ liệu.
 *
 * Exception này có thể:
 *
 * - Bọc một exception tầng thấp như PDOException.
 * - Được ném trực tiếp khi quá trình tạo dữ liệu bằng logic ứng dụng thất bại.
 *
 * Không dùng DataAccessException cho lỗi lập trình, lỗi truyền sai tham số
 * hoặc lỗi vi phạm contract.
 */
class DataAccessException extends RuntimeException
{
    public function __construct(
        string $message = 'Không thể truy cập hoặc xây dựng dữ liệu.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }
}

