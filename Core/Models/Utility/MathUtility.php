<?php
namespace Core\Models\Utility;
use \InvalidArgumentException;
class MathUtility{
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
    
}
