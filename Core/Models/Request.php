<?php
namespace Core\Models;
/**
 * Description of Request
 *
 * @author admin
 */
namespace Core\Models;

class Request {
    protected array $getData;
    protected array $postData;
    protected array $jsonData; // ví dụ do hàm fetch tạo ra 
    protected array $serverData;
    protected array $filesData;
    // dự kiến để tự bổ sung parameter bằng code , kiểu như 'prefix_url'
    protected array $customData = []; 
    protected ?string $rawInput = null;

    public function __construct() {
        $this->getData    = $_GET;
        $this->postData   = $_POST;
        $this->serverData = $_SERVER;
        $this->filesData  = $_FILES;
        $this->initJson();
    }
    // ----------------------------------------------------------------
    /* hiện nay initJson được gọi và tạo ra dữ liệu nội bộ $this->json phổ biến 
    thông qua hàm fetch
    fetch('somethingURL', {
        method: 'POST', //hoặc PUT hoặc PATCH, hoặc DELETE
        headers: { 'Content-Type': 'application/json' //dòng này quan trọng nhất
            'Accept': 'application/json'    //viết thêm cho cẩn thận
        }, 
        body: JSON.stringify({ name: 'Tuấn' })
    });
    */
    protected function initJson(): void {
        $contentType = $this->serverData['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $this->rawInput = file_get_contents('php://input');
            $decoded = json_decode($this->rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->jsonData = $decoded;
            } else {
                $this->jsonData = [];
            }
        } else {
            $this->jsonData = [];
        }
    }

    // ----------------- Getter với filter -----------------
    public function get(string $key, $default = null, $filter = null) {
        return $this->filterValue($this->getData[$key] ?? $default, $filter);
    }

    public function post(string $key, $default = null, $filter = null) {
        return $this->filterValue($this->postData[$key] ?? $default, $filter);
    }

    public function json(string $key, $default = null, $filter = null) {
        return $this->filterValue($this->jsonData[$key] ?? $default, $filter);
    }

    public function input(string $key, $default = null, $filter = null) {
        $value = $this->customData[$key]
            ?? $this->jsonData[$key]
            ?? $this->postData[$key]
            ?? $this->getData[$key]
            ?? $default;
        return $this->filterValue($value, $filter);
    }
    /**
     * Thêm hoặc ghi đè parameter
     */
    public function set(string $key, $value, string $source = 'custom'): void {
        switch ($source) {
            case 'get':  $this->getData[$key]  = $value; break;
            case 'post': $this->postData[$key] = $value; break;
            case 'json': $this->jsonData[$key] = $value; break;
            case 'custom':
            default:     $this->customData[$key] = $value; break;
        }
    }

    public function all(): array {
        // Ưu tiên: JSON → POST → GET
        return array_merge($this->getData, 
                $this->postData, 
                $this->jsonData, 
                $this->customData // customData ưu tiên cao hơn
                );
    }
    // -----------------  File -----------------
    

    public function file(string $key) {
        return $this->filesData[$key] ?? null;
    }

    // ----------------- Header & Method -----------------
    public function header(string $name, $default = null) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->serverData[$key])) {
            return $this->serverData[$key];
        }
        $altKey = strtoupper(str_replace('-', '_', $name));
        return $this->serverData[$altKey] ?? $default;
    }
    /*GET, POST, PUT, DELETE
     */
    public function method(): string {
        return strtoupper($this->serverData['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string {
        return parse_url($this->serverData['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }
    public function fullUrl(): string {
        $uri = $this->uri();
        // chỉ lấy tham số GET gốc, không trộn với POST/JSON
        $queryString = http_build_query($this->getData);
        return $queryString ? $uri . '?' . $queryString : $uri;
    }
    // ----------------- Check Method -----------------
    public function isGet(): bool { return $this->method() === 'GET'; }
    public function isPost(): bool { return $this->method() === 'POST'; }
    public function isAjax(): bool {
        return ($this->serverData['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
    public function isJsonRequest(): bool {
        $accept = $this->serverData['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json') 
            || stripos($this->serverData['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }
    public function isSecure(): bool {
        return (!empty($this->serverData['HTTPS']) && $this->serverData['HTTPS'] !== 'off')
            || ($this->serverData['SERVER_PORT'] ?? 80) == 443;
    }
    
    // ----------------- Tiện ích -----------------
    public function rawInput(): ?string {
        return $this->rawInput;
    }

    public function segmentUri(): ?array {
        //dùng array_filter với điều kiện lọc strlen để lọc bỏ các chuỗi rỗng ''
        //sau đó dùng array_values để đánh lại chỉ số mảng liên tiếp từ 0
        $segments = array_values(array_filter(explode('/', trim($this->uri(), '/')), 'strlen'));
        return $segments ?? null;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->jsonData)
            || array_key_exists($key, $this->postData)
            || array_key_exists($key, $this->getData);
    }

    public function only(array $keys): array {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    public function except(array $keys): array {
        $data = $this->all();
        return array_diff_key($data, array_flip($keys));
    }
    public static function getResponseType() {
        return strtolower(trim($_GET['response_type'] ?? Response::RESPONSE_HTML_TYPE));
    }
    public static function isHtmlResponse(){
        return self::getResponseType() === Response::RESPONSE_HTML_TYPE;
    }
    public function queryParams(){
        return $this->getData;
    }
    
    // ----------------- Helper nội bộ -----------------
    protected function filterValue($value, $filter) {
        if ($filter === null) {
            return $value;
        }
        if (is_callable($filter)) {
            return $filter($value);
        }
        if (is_string($filter) && function_exists($filter)) {
            return $filter($value);
        }
        return $value;
    }
    
    
}