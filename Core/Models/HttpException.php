<?php
namespace Core\Models;
use \Exception;
/*Cách dùng
throw new HttpException(404, 'Not Found');
throw new HttpException(503, 'Service Unavailable', ['Retry-After' => 120]);
*/
class HttpException extends Exception {
    public function __construct(
        int $httpStatusCode, //đây thường là các HTTP Status Code 200, 404, 503, 500
        string $message = '',
        array $headers = [], 
        int $code = 0, // mã này để giữ tương thích với Exception trong PHP, thường giữ = 0
        ?Exception $previous = null
    ) {
        $this->httpStatusCode = $httpStatusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getHttpStatusCode(): int{
        return $this->httpStatusCode;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getHeaders(): array{
        return $this->headers;
    }
}