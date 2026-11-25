<?php
declare(strict_types=1);

namespace Core\Normalization;

use Core\Foundation\ExtArray;
use Core\Http\Response;
use finfo;

/**
 * Input: Request::file() after prepareFileData(), and an already normalized schema.
 * No schema normalization, file moves or writes. Missing policies impose no limits.
 * NO_FILE leaves remain in output but do not participate in validation/statistics.
 * Other upload errors count as attempts, then stop individual validation of that file.
 * Each normalize() starts fresh; errors return data=null and a flat list in extra.
 */
final class UploadFilesNormalizer
{
    // Reserved upload data errors: 1600–1699.
    public const ERR_STRUCTURE = 1600;
    public const ERR_UPLOAD = 1601;
    public const ERR_MAX_SIZE = 1602;
    public const ERR_ALLOWED_TYPES = 1603;
    public const ERR_MIN_COUNT = 1604;
    public const ERR_MAX_COUNT = 1605;
    public const ERR_MAX_TOTAL_SIZE = 1606;
    public const ERR_READ_FILE = 1607;

    private const ATTRIBUTES = ['name', 'full_path', 'type', 'tmp_name', 'error', 'size'];

    private array $arrUploadFile;
    private array $arrSchema;
    //chú ý phải đảm bảo $uploadFilesSchema đã chạy normalize trước để đảm bảo chuẩn dữ liệu
    public function __construct(array $arrUploadFile, UploadFilesSchema $uploadFilesSchema)
    {
        // Input là dữ liệu upload do PHP tạo, đã qua Request::prepareFileData().
        $this->arrUploadFile = $arrUploadFile;
        $this->arrSchema = $uploadFilesSchema->getData();
    }
    /* Trả về giá trị kiểu Response
    - Thành công ['status' => Response::SERVER_OK_STATUS, 'data' => $value, 'extra' => null] 
    $value có format dạng Request::filesData sau khi đã được chạy prepareFileData
    - Thất bại ['status' => Response::SERVER_LOGIC_ERR_STATUS, , 'data' => null, 'extra' => $error] 
    $error có dạng array [ $rowErr1, rowErr2, ..., rowErrn] , trong đó rowErrx có dạng 
    ['path' => $arrPath, 'err_number' => $iCode, 'value' => $mixValue, 'rule_path' => $arrRulePath]
    path là đường dẫn tới lỗi sai tại data 
    rule_path là đường dẫn tới lỗi sai tại schema
    err_number là mã lỗi sai
    value là giá trị sai tại data
     */
    public function normalize(): array
    {
        $uploadFilesSchema = new UploadFilesSchema($this->arrSchema);
        $arrFlatSchema = $uploadFilesSchema->toFlatArray(
            function (array $arrPath, mixed $mixValue, array|int $mixDefinition): array {
                //policy chưa 1 hoặc nhiều trong các phần tử: min_count, max_count, max_size, max_total_size, allowed_types
                return ['path' => $arrPath, 'policy' => $mixValue, 'count' => 0, 'size' => 0];
            }
        );
        $arrError = [];
        // Copy-on-write isolates traversal of prepared upload data.
        $arrData = $this->arrUploadFile;
        $extArrData = new ExtArray($arrData);
        $arrAttribute = self::ATTRIBUTES;
        $fnProcess = function (array $arrPath, mixed $mixFile) use (&$arrFlatSchema, &$arrError): void {
            $this->processFile($arrPath, $mixFile, $arrFlatSchema, $arrError);
        };
        /*Định nghĩa COMPOSITE_LEAF là array có đủ 6 key 'name', 'full_path', 'type', 'tmp_name', 'error', 'size'
         * EXCLUDED trước hết phải là LEAF và $mixValue['error'] === UPLOAD_ERR_NO_FILE
         */
        $extArrData->setDefineLeafFn(function (array $arrPath, mixed $mixValue) use ($arrAttribute): int {
            if (is_array($mixValue)
                && count($mixValue) === count($arrAttribute)
                && array_diff($arrAttribute, array_keys($mixValue)) === []) {
                return $mixValue['error'] === UPLOAD_ERR_NO_FILE
                    ? ExtArray::EXCLUDED : ExtArray::COMPOSITE_LEAF;
            }
            return ExtArray::BRANCH_OR_PETIOLE;
        });
        /*Khi sử dụng normalizeLeafFn thì $mixValue phải là 1 leaf rồi*/
        $iStructureError = self::ERR_STRUCTURE;
        $extArrData->setNormalizeLeafFn(function (mixed $mixValue) use ($iStructureError): array {
            foreach (['name', 'full_path', 'type', 'tmp_name'] as $strKey) {
                if (!is_string($mixValue[$strKey])) {
                    return ['err_number' => $iStructureError, 'value' => $mixValue];
                }
            }
            $isValid = is_int($mixValue['error']) && is_int($mixValue['size']) && $mixValue['size'] >= 0;
            return ['err_number' => $isValid ? 0 : $iStructureError, 'value' => $mixValue];
        });
        $fnRecordError = function (array $arrPath, array $arrResult) use (&$arrError): void {
            $arrError[] = self::error($arrPath, $arrResult['err_number'], $arrResult['value']);
        };
        $fnProcessLeaf = function (array $arrPath, mixed &$mixValue, array|int $mixDefinition) use ($fnProcess, $fnRecordError): void {
            $arrResult = $this->callNormalizeLeafFn($arrPath, $mixValue);//cần bind đối tượng vào $extArrData để gọi được callNormalizeLeafFn
            if ($arrResult['err_number'] !== 0) {
                $fnRecordError($arrPath, $arrResult);
                return;
            }
            $mixValue = $arrResult['value'];
            $fnProcess($arrPath, $mixValue);
        };
        // Chỉ processor này cần scope ExtArray để gọi protected callNormalizeLeafFn().
        $fnProcessLeaf = $fnProcessLeaf->bindTo($extArrData, ExtArray::class);
        if ($fnProcessLeaf === null) {
            throw new \LogicException('Cannot bind upload leaf processor.');
        }
        $extArrData->setProcessLeafFn($fnProcessLeaf);
        //Begin: duyệt data, kiểm tra dữ liệu file có vi phạm không + đồng thời update $arrFlatSchema
        $extArrData->traverseTree();
        //End: duyệt data, kiểm tra dữ liệu file có vi phạm không + đồng thời update $arrFlatSchema
        //Begin. Kiểm tra các điều kiện min_count, max_count, max_total_size tại từng nhánh của $arrFlatSchema
        foreach ($arrFlatSchema as $arrGroupInfo) {
            $arrPolicy = $arrGroupInfo['policy'];
            foreach ([UploadFilesSchema::FIELD_MIN_COUNT => self::ERR_MIN_COUNT,
                UploadFilesSchema::FIELD_MAX_COUNT => self::ERR_MAX_COUNT,
                UploadFilesSchema::FIELD_MAX_TOTAL_SIZE => self::ERR_MAX_TOTAL_SIZE] as $strRule => $iCode) {
                if (!isset($arrPolicy[$strRule])) {
                    continue;
                }
                $iActual = $strRule === UploadFilesSchema::FIELD_MAX_TOTAL_SIZE
                    ? $arrGroupInfo['size'] : $arrGroupInfo['count'];

                if ($strRule === UploadFilesSchema::FIELD_MIN_COUNT) {
                    $isInvalid = $iActual < $arrPolicy[$strRule];
                } else {//max_count, max_total_size 
                    $isInvalid = $iActual > $arrPolicy[$strRule];
                }
                if ($isInvalid) {
                    $arrError[] = self::error($arrGroupInfo['path'], $iCode, $iActual, $arrGroupInfo['path']);
                }
            }
        }
        //End. Kiểm tra các điều kiện min_count, max_count, max_total_size tại từng nhánh của $arrFlatSchema
        return ['status' => $arrError === [] ? Response::SERVER_OK_STATUS : Response::SERVER_LOGIC_ERR_STATUS,
            'data' => $arrError === [] ? $arrData : null,
            'extra' => $arrError === [] ? null : $arrError];
    }
    /* $mixFile có cấu trúc [
        'name'     => ...,
        'type'     => ...,
        'tmp_name' => ...,
        'error'    => ...,
        'size'     => ...,
        'full_path'=> ...,
    ]
     */
    private function processFile(array $arrPath, mixed $mixFile, array &$arrFlatSchema, array &$arrError): void
    {
        /*$arrEffectiveFilePolicy có dạng
            [
                'max_size' => ['value' => ..., 'path'=>...]
                'allowed_types' => ['value' => ..., 'path'=>...]
            ]
         $arrEffectiveFilePolicy chính là điều kiện áp đặt lên mixFile
         Do tác dụng của vòng for từ ngắn nhất => dài nhất nên $arrEffectiveFilePolicy chính là điều kiện áp đặt lên
         $mixFile ở mức sâu nhất có thể. Điều này thể hiện thuật toán là qui định ở mức sâu hơn sẽ phủ định
         qui định của các tổ tiên ở ngoài. Bởi vì vòng for vào sâu dần nên value tại 2 key max_size và allowed_types
         không ngừng bị phủ định  
         */
        $arrEffectiveFilePolicy = [];
        //for: lần lượt từ $arrPath của file tạo ra các đường dẫn từ ngắn nhất đến dài nhất 
        for ($iDepth = 0; $iDepth <= count($arrPath); ++$iDepth) {
            //$strKey là bản mã hóa các đường dẫn đó
            $strKey = serialize(array_slice($arrPath, 0, $iDepth));
            if (!isset($arrFlatSchema[$strKey])) {
                continue;
            }
            //Update $arrFlatSchema: so khớp đường dẫn $strKey với dữ liệu trong $arrFlatSchema thì đếm tăng 'count' và 'size'
            ++$arrFlatSchema[$strKey]['count']; //đếm tăng số file lên 1
            $arrFlatSchema[$strKey]['size'] += $mixFile['size']; //đếm tăng dung lượng các file do $arrFlatSchema quản lý
            //Update $arrEffectiveFilePolicy
            foreach ([UploadFilesSchema::FIELD_MAX_SIZE, UploadFilesSchema::FIELD_ALLOWED_TYPES] as $strRule) {
                if (array_key_exists($strRule, $arrFlatSchema[$strKey]['policy'])) {
                    $arrEffectiveFilePolicy[$strRule] = ['value' => $arrFlatSchema[$strKey]['policy'][$strRule],
                        'path' => $arrFlatSchema[$strKey]['path']];
                }
            }
        }
        if ($mixFile['error'] !== UPLOAD_ERR_OK) {
            $arrError[] = self::error($arrPath, self::ERR_UPLOAD, $mixFile['error']);
            return;
        }
        //tới đây thì dùng $arrEffectiveFilePolicy tìm được để kiểm tra file upload
        //Begin: kiểm tra điều kiện max_size
        $arrSizeRule = $arrEffectiveFilePolicy[UploadFilesSchema::FIELD_MAX_SIZE] ?? null;
        if ($arrSizeRule !== null && $mixFile['size'] > $arrSizeRule['value']) {
            $arrError[] = self::error($arrPath, self::ERR_MAX_SIZE, $mixFile['size'], $arrSizeRule['path']);
        }
        //End: kiểm tra điều kiện max_size
        $arrTypeRule = $arrEffectiveFilePolicy[UploadFilesSchema::FIELD_ALLOWED_TYPES] ?? null;
        if ($arrTypeRule === null) {
            return;
        }
        //Begin: kiểm tra điều kiện file type
        $strExtension = strtolower(pathinfo($mixFile['name'], PATHINFO_EXTENSION));
        if (!isset($arrTypeRule['value'][$strExtension])) {
            $arrError[] = self::error($arrPath, self::ERR_ALLOWED_TYPES, $strExtension, $arrTypeRule['path']);
            return;
        }
        //End: kiểm tra điều kiện file type
        //Begin: Kiểm tra lỗi file đang ghi tạm trên server
        $strTmpName = $mixFile['tmp_name'];
        // Never resolve a stream wrapper supplied as a temporary filename.
        if ($strTmpName === '' || str_contains($strTmpName, "\0") || str_contains($strTmpName, '://')
            || !is_file($strTmpName) || !is_readable($strTmpName)) {
            $arrError[] = self::error($arrPath, self::ERR_READ_FILE, null, $arrTypeRule['path']);
            return;
        }
        //End: Kiểm tra lỗi file đang ghi tạm trên server
        //Begin: kiểm tra xem type/subtype của file tmp trên server có phù hợp với thông tin trong $arrEffectiveFilePolicy không
        $mixMime = @(new finfo(FILEINFO_MIME_TYPE))->file($strTmpName);
        if ($mixMime === false) {
            $arrError[] = self::error($arrPath, self::ERR_READ_FILE, null, $arrTypeRule['path']);
        } elseif (!in_array($mixMime, $arrTypeRule['value'][$strExtension], true)) {
            $arrError[] = self::error($arrPath, self::ERR_ALLOWED_TYPES, $mixMime, $arrTypeRule['path']);
        }
        //End: kiểm tra xem type/subtype của file tmp trên server có phù hợp với thông tin trong $arrEffectiveFilePolicy không
    }

    private static function error(array $arrPath, int $iCode, mixed $mixValue, ?array $arrRulePath = null): array
    {
        return ['path' => $arrPath, 'err_number' => $iCode, 'value' => $mixValue, 'rule_path' => $arrRulePath];
    }

}
