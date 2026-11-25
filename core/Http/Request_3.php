<?php
namespace Core\Http;

use Core\Foundation\ExtArray;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use UnexpectedValueException;

class Request {
    protected array $getData;
    protected array $postData;
    // null: nguồn chưa được chuẩn bị; []: nguồn đã được chuẩn bị nhưng rỗng.
    protected ?array $jsonData = null;
    protected array $serverData;
    protected array $filesData;
    protected ?array $arrMCAO = null; //bổ sung 2026-11-05
    public function __construct() {
        $this->getData    = $_GET;
        $this->postData   = $_POST;
        $this->serverData = $_SERVER;
        $this->filesData  = $_FILES;
        $this->initJson();
        $this->prepareFileData();
    }
    // ----------------------------------------------------------------
    public function setMCAO(array $arrMCAO):void{
        $this->arrMCAO = $arrMCAO;
    }
    // ----------------------------------------------------------------
    public function mcao(): ?array{
        return $this->arrMCAO;
    }
    // ----------------- Getter theo nguồn -----------------
    // Key null: lấy toàn nguồn, không áp dụng filter.
    // Key có mặt với giá trị null vẫn giữ null; default chỉ dùng khi thiếu key.
    public function query(string|int|null $key = null, $default = null, $filter = null) {
        return $this->readSource($this->getData, $key, $default, $filter);
    }

    /** Alias tương thích của query(). */
    public function get(string|int|null $key = null, $default = null, $filter = null) {
        return $this->query($key, $default, $filter);
    }

    public function post(string|int|null $key = null, $default = null, $filter = null) {
        return $this->readSource($this->postData, $key, $default, $filter);
    }

    /** Lấy toàn nguồn có thể trả null; đọc một key yêu cầu nguồn đã sẵn sàng. */
    public function json(string|int|null $key = null, $default = null, $filter = null) {
        if ($key === null) {
            return $this->jsonData;
        }
        if ($this->jsonData === null) {
            throw new LogicException('Cannot access a JSON field before JSON data is prepared.');
        }
        return $this->readSource($this->jsonData, $key, $default, $filter);
    }

    /** Chỉ lấy other_params; giữ index gốc trong danh sách URI segments. */
    public function pathParam(?int $key = null, $default = null) {
        if ($this->arrMCAO === null) {
            throw new LogicException('Cannot access path_params before MCAO is set.');
        }
        return $this->readSource($this->arrMCAO['other_params'], $key, $default);
    }
    /**
     * Thay thế toàn bộ một nguồn, không merge hoặc đánh lại key.
     * query/get là hai tên của cùng nguồn. JSON có thể được khởi tạo từ null
     * hoặc thay thế khi đã sẵn sàng; [] là nguồn đã chuẩn bị nhưng rỗng.
     * path_params chỉ thay other_params, yêu cầu MCAO đã được gán.
     * Không normalize dữ liệu, không cập nhật các biến superglobal.
     */
    public function set(string $source, array $data): void {
        switch ($source) {
            case 'query':
            case 'get':
                $this->getData = $data;
                break;
            case 'post':
                $this->postData = $data;
                break;
            case 'json':
                $this->jsonData = $data;
                break;
            case 'files':
                // Caller phải cung cấp cấu trúc files đã chuẩn bị, không phải cấu trúc thô $_FILES.
                $this->filesData = $data;
                break;
            case 'path_params':
                if ($this->arrMCAO === null) {
                    throw new LogicException('Cannot update path_params before MCAO is set.');
                }
                $this->arrMCAO['other_params'] = $data;
                break;
            default:
                throw new InvalidArgumentException('Unknown request source: ' . $source);
        }
    }

    // -----------------  File -----------------
    

    public function file(string|int|null $key = null, $default = null) {
        return $this->readSource($this->filesData, $key, $default);
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
    //loại bỏ hoàn toàn các tham số truy vấn (query string) đi kèm.    
    public function uri(): string {
        return parse_url($this->serverData['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }
    public function fullUrl(): string {
        $uri = $this->uri();
        // chỉ lấy tham số GET gốc, không trộn với POST/JSON
        $queryString = http_build_query($this->getData);
        return $queryString ? $uri . '?' . $queryString : $uri;
    }
    
    
    
    // ----------------- Tiện ích -----------------
    /**
     * Kiểm tra key trực tiếp trong một nguồn, kể cả khi value=null.
     * Array keys: tất cả phải tồn tại; array rỗng trả true.
     * Không diễn giải key thành nested path.
     * Nguồn JSON/path_params chưa sẵn sàng gây LogicException, kể cả keys rỗng.
     */
    public function has(string $source, string|int|array $keys): bool {
        $data = match ($source) {
            'query' => $this->query(),
            'post' => $this->post(),
            'json' => $this->json(),
            'files' => $this->file(),
            'path_params' => $this->pathParam(),
            default => throw new InvalidArgumentException('Unknown request source: ' . $source),
        };
        if ($data === null) {
            throw new LogicException('Cannot check JSON fields before JSON data is prepared.');
        }
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            if (!is_string($key) && !is_int($key)) {
                throw new InvalidArgumentException('Request keys must be strings or integers.');
            }
        }
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        return true;
    }
    public static function getResponseType() {
        return strtolower(trim($_GET['response_type'] ?? Response::RESPONSE_HTML_TYPE));
    }
    public static function isHtmlResponse(){
        return self::getResponseType() === Response::RESPONSE_HTML_TYPE;
    }
    
    
    /**
     * Chuyển [field, attribute, ...indices] thành [field, ...indices, attribute].
     * Giữ nguyên giá trị/index; chưa validate upload và không di chuyển file.
     * Chạy một lần trong constructor; chỉ ghi nguồn sau khi thành công.
     */
    protected function prepareFileData(): void
    {
        $arrFiles = $this->filesData;
        $arrOut = [];
        if ($arrFiles !== []) {
            $extArrFiles = new ExtArray($arrFiles);
            $extArrOut = new ExtArray($arrOut);
            // ExtArray bind callback vào scope của nó để đọc tên petiole hiện hành.
            $defineLeaf = function (mixed $value): array|int {
                if (is_array($value)) {
                    if (array_key_exists(self::$strPetiole, $value)) {
                        throw new UnexpectedValueException('Upload field/group conflicts with the ExtArray petiole name.');
                    }
                    return ExtArray::BRANCH_OR_PETIOLE;
                }
                if (!is_string($value) && !is_int($value)) {
                    throw new UnexpectedValueException('Upload leaves must be strings or integers.');
                }
                return []; // Atomic leaf; không cần metadata cho bước chuyển cấu trúc.
            };
            $extArrFiles->setDefineLeafFn($defineLeaf);
            $extArrOut->setDefineLeafFn($defineLeaf);
            $extArrOut->setValidLeafFn(function (mixed $value): bool {
                return is_string($value) || is_int($value);
            });
            $extArrFiles->setProcessLeafFn(function (array $path, mixed &$value, array|int $definition) use ($extArrOut): void {
                if (count($path) < 2 || !in_array($path[1], ['name', 'full_path', 'type', 'tmp_name', 'error', 'size'], true)) {
                    throw new UnexpectedValueException('Invalid upload attribute path.');
                }
                $attribute = $path[1];
                $target = [$path[0]];
                foreach (array_slice($path, 2) as $key) {
                    $target[] = $key;
                }
                $target[] = $attribute;
                $extArrOut->setValue($target, $value);
            });
            $extArrFiles->traverseTree();
        }
        $this->filesData = $arrOut;
    }

    // ----------------- Helper nội bộ -----------------
    protected function readSource(array $data, string|int|null $key, $default = null, $filter = null) {
        if ($key === null) {
            return $data;
        }
        $value = array_key_exists($key, $data) ? $data[$key] : $default;
        return $this->filterValue($value, $filter);
    }

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
    /**
     * Chỉ decode body application/json; Accept không quyết định định dạng body.
     * JSON sai cú pháp gây JsonException; root scalar/null gây UnexpectedValueException.
     * Không dùng null để biểu diễn decode thất bại, không lưu raw body vào property.
     */
    protected function initJson(): void {
        $contentType = $this->serverData['CONTENT_TYPE'] ?? '';
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($mediaType !== 'application/json') {
            return;
        }
        $rawInput = file_get_contents('php://input');
        if ($rawInput === false) {
            throw new RuntimeException('Cannot read JSON request body.');
        }
        $decoded = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('JSON request body must have an object or array root.');
        }
        $this->jsonData = $decoded;
    }
    
}
