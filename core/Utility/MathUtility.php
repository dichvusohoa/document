<?php
namespace Core\Utility;
use InvalidArgumentException;
class MathUtility{
    public static function toNonNegativeInt(mixed $value): int{
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException(
                    'Giá trị phải là số nguyên không âm.'
                );
            }

            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Giá trị phải là số nguyên không âm.'
            );
        }

        $value = trim($value);

        if ($value === '' || !ctype_digit($value)) {
            throw new InvalidArgumentException(
                'Giá trị phải là số nguyên không âm.'
            );
        }

        $result = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0
                ]
            ]
        );

        if ($result === false) {
            throw new InvalidArgumentException(
                'Giá trị phải là số nguyên không âm và không vượt quá giới hạn kiểu int.'
            );
        }

        return $result;
    }
    /**
    * Tính tích Đề các (Cartesian Product) của một danh sách các mảng đầu vào.
    *
    * Mỗi phần tử trong mảng đầu vào phải là một mảng một chiều không rỗng.
    * Hàm trả về một mảng các tổ hợp, trong đó mỗi tổ hợp là một mảng chứa
    * đúng một phần tử từ mỗi mảng thành phần theo tất cả các kết hợp có thể.
    *
    * Ví dụ:
    *   Input:  [['a', 'b'], ['c', 'd']]
    *   Output: [['a', 'c'], ['a', 'd'], ['b', 'c'], ['b', 'd']]
    *
    * @param array $arrays Danh sách các mảng đầu vào (mỗi phần tử phải là mảng)
    * @return array Mảng chứa tất cả các tổ hợp Đề các giữa các mảng thành phần
    *
    * @throws InvalidArgumentException Nếu bất kỳ phần tử nào không phải là mảng
    */
    static public function cartesianProduct(array $arrays): array {
        $result = [[]];//khởi tạo mảng kết quả ban đầu
        foreach ($arrays as $i => $subArray) {//lấy ra $subArray là từng phần tử trong $arrays
            if (!is_array($subArray)) {
                throw new InvalidArgumentException("Thành phần thứ $i không phải là mảng.");
            }
            /*if (count($subArray) === 0) {
                throw new InvalidArgumentException("Mảng con tại vị trí $i bị rỗng.");
            }*/
            $append = [];
            foreach ($result as $product) { // tổ hợp $result với $subArray
                foreach ($subArray as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $result = $append;//$result = $result  X  $subArray
        }
        return $result;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * So sánh hai mảng số theo thứ tự từ trái sang phải.
     *
     * Ví dụ:
     * [2, 5] < [3, 1]
     * [2, 5] < [2, 7]
     * [2, 5] = [2, 5]
     *
     * @return int -1 nếu $arr1 nhỏ hơn $arr2,
     *              0 nếu hai mảng bằng nhau,
     *              1 nếu $arr1 lớn hơn $arr2.
     */
    public static function compareNumberArray(array $arr1, array $arr2): int{
        if (count($arr1) !== count($arr2)) {
            throw new InvalidArgumentException(
                'Hai mảng số dùng để so sánh phải có cùng số phần tử.'
            );
        }

        foreach ($arr1 as $iIndex => $number1) {
            $number2 = $arr2[$iIndex];

            if (
                (!is_int($number1) && !is_float($number1))
                || (!is_int($number2) && !is_float($number2))
            ) {
                throw new InvalidArgumentException(
                    "Phần tử tại vị trí {$iIndex} của hai mảng phải là số."
                );
            }

            if ($number1 < $number2) {
                return -1;
            }

            if ($number1 > $number2) {
                return 1;
            }
        }

        return 0;
    }
}
