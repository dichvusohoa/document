<?php
declare(strict_types=1);

namespace Core\Normalization;

use Core\Http\Request;
use JsonException;
use InvalidArgumentException;

/**
 * Điều phối normalization cho một function của controller. PHP >= 8.0.
 * Request phải là cùng instance mà controller dùng để lấy arguments.
 * Schema được truyền theo từng lần gọi, không lưu trong normalizer.
 * Không gọi action và không tạo response status/data/extra.
 *
 * Xử lý path_params/query/post, các bảng JSON DAU và files theo schema.
 */
class RequestNormalizer
{
    // Lỗi cứng: 11000–11099; dành 11100–11199 cho lỗi custom.
    public const ERR_POST_JSON_NOT_STRING = 11000;
    public const ERR_POST_JSON_INVALID = 11001;
    public const ERR_POST_JSON_NOT_ARRAY = 11002;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * - null: không cần normalization hoặc các nguồn có schema đều thành công.
     * - array không rỗng: lỗi dữ liệu, phân theo source; không trả [].
     * - Exception cấu hình/schema được truyền ra ngoài.
     * BaseController chỉ lấy arguments và gọi action khi kết quả là null.
     * Mỗi lần gọi xử lý dữ liệu hiện tại; không cache, không rollback nguồn đã ghi.
     */
    public function normalize(string $strFunctionName, BaseRequestSchema $requestSchema): ?array
    {
        $arrSchema = $requestSchema->createSchema($strFunctionName);
        if ($arrSchema === null) {
            return null;
        }

        // Chỉ đọc nguồn khi có schema; path_params chưa có định tuyến sẽ ném lỗi.
        foreach ([BaseRequestSchema::FIELD_PATH_PARAMS, BaseRequestSchema::FIELD_QUERY] as $source) {
            if (($arrSchema[$source] ?? null) === null) {
                continue;
            }
            $data = $source === BaseRequestSchema::FIELD_PATH_PARAMS
                ? $this->request->pathParam() : $this->request->query();
            $result = (new RowNormalizer($data, $arrSchema[$source]))->normalize();
            if ($result['extra'] !== null) {
                return [$source => $result['extra']];
            }
            $this->request->set($source, $result['data']);
        }

        // Tách field JSON trước khi RowNormalizer kiểm tra field ngoài schema POST.
        $prepareErrors = $this->prepareJsonData($arrSchema);
        if ($prepareErrors !== null) {
            return $prepareErrors;
        }

        $arrError = [];
        if (($arrSchema[BaseRequestSchema::FIELD_POST] ?? null) !== null) {
            $result = (new RowNormalizer(
                $this->request->post(), $arrSchema[BaseRequestSchema::FIELD_POST]
            ))->normalize();
            if ($result['extra'] !== null) {
                $arrError[BaseRequestSchema::FIELD_POST] = $result['extra'];
            } else {
                $this->request->set('post', $result['data']);
            }
        }

        $jsonSchema = $arrSchema[BaseRequestSchema::FIELD_JSON] ?? null;
        $jsonData = $this->request->json();
        if ($jsonSchema !== null && $jsonData !== null) {
            // Kiểm tra tên/kiểu trước khi chạy callback của bất kỳ bảng nào.
            foreach ($jsonData as $name => $tableData) {
                if (!array_key_exists($name, $jsonSchema)) {
                    throw new InvalidArgumentException('Unknown JSON table: ' . $name);
                }
                if (!is_array($tableData)) {
                    throw new InvalidArgumentException('JSON table must be an array: ' . $name);
                }
            }
            $jsonErrors = [];
            $normalizedJson = [];
            foreach ($jsonData as $name => $tableData) {
                $result = (new TableDAUNormalizer($tableData, $jsonSchema[$name]))->normalize();
                if ($result['extra'] !== []) {
                    $jsonErrors[$name] = $result['extra'];
                }
                $normalizedJson[$name] = $result['data'];
            }
            if ($jsonErrors !== []) {
                $arrError[BaseRequestSchema::FIELD_JSON] = $jsonErrors;
            } else {
                $this->request->set('json', $normalizedJson);
            }
        }

        if (($arrSchema[BaseRequestSchema::FIELD_FILES] ?? null) !== null) {
            // BaseRequestSchema đã normalize policy; Request đã chuẩn bị cấu trúc files.
            $arrFilesSchema = $arrSchema[BaseRequestSchema::FIELD_FILES];
            $uploadFilesSchema = new UploadFilesSchema($arrFilesSchema);
            $result = (new UploadFilesNormalizer($this->request->file(), $uploadFilesSchema))->normalize();
            if ($result['extra'] !== null) {
                $arrError[BaseRequestSchema::FIELD_FILES] = $result['extra'];
            } else {
                $this->request->set('files', $result['data']);
            }
        }
        return $arrError === [] ? null : $arrError;
    }

    /**
     * Nhận schema đã được BaseRequestSchema kiểm tra.
     * Chỉ chuẩn bị JSON chưa khởi tạo từ các field của form POST.
     * null: thành công/bỏ qua; lỗi: ['post' => [field => ['err_number', 'value']]].
     * Thu hết lỗi; chỉ cập nhật POST và JSON khi tất cả field có mặt đều hợp lệ.
     */
    protected function prepareJsonData(array $arrSchema): ?array
    {
        if ($this->request->json() !== null) {
            return null;
        }
        $fields = $arrSchema[BaseRequestSchema::FIELD_POST_JSON_FIELDS] ?? null;
        if ($fields === null) {
            return null;
        }
        $contentType = $this->request->header('Content-Type', '');
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if (!in_array($mediaType, ['multipart/form-data', 'application/x-www-form-urlencoded'], true)) {
            return null;
        }

        $postData = $this->request->post();
        $jsonData = [];
        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $postData)) {
                continue;
            }
            $value = $postData[$field];
            $code = AtomicNormalizer::ERR_NONE;
            if (!is_string($value)) {
                $code = self::ERR_POST_JSON_NOT_STRING;
            } else {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) {
                        $code = self::ERR_POST_JSON_NOT_ARRAY;
                    }
                } catch (JsonException $exception) {
                    $code = self::ERR_POST_JSON_INVALID;
                }
            }
            if ($code !== AtomicNormalizer::ERR_NONE) {
                $errors[$field] = [
                    AtomicNormalizer::FIELD_ERR_NUMBER => $code,
                    AtomicNormalizer::FIELD_VALUE => $value,
                ];
                continue;
            }
            $jsonData[$field] = $decoded;
            unset($postData[$field]);
        }
        if ($errors !== []) {
            return [BaseRequestSchema::FIELD_POST => $errors];
        }
        $this->request->set('post', $postData);
        $this->request->set('json', $jsonData);
        return null;
    }

}
