Vấn đề Model (nghiệp vụ) , DB , Entity (thực thể)

Entity hình dung như một record. Ví dụ UserEntity như là một Row
UserDb hay UserRepository là để thao tác với DB. Nghiệp vụ xuất nhập

class UserRepository {
    protected DbService $db;

    public function __construct(DbService $db) {
        $this->db = $db;
    }

    public function findByUsername(string $username): ?UserEntity {
        $row = $this->db->selectOne("SELECT * FROM user WHERE username = ?", [$username]);
        return $row ? new UserEntity($row) : null;
    }
}

UserService hay User không sẽ là User Model chứa các nghiệp vụ: login, register

class UserService {
    protected UserRepository $userRepo;

    public function __construct(UserRepository $userRepo) {
        $this->userRepo = $userRepo;
    }

    public function login(string $username, string $password): ?UserEntity {
        $user = $this->userRepo->findByUsername($username);
        if (!$user || !password_verify($password, $user->password)) {
            return null;
        }
        return $user;
    }

    public function register(string $username, string $email, string $password): UserEntity {
        // kiểm tra logic → gọi repo để lưu → trả entity
    }
}



========
Tùy điều kiện mà xử lý cho linh hoạt
=========
dependency injection.

Thường nhất là dùng dependency injection
class User {
    protected DbService $db;

    function __construct(DbService $db) {
        $this->db = $db;
    }
}

đó là một kiểu dependency injection qua constructor


Qua thực tế thấy rằng rất ít khi tạo class Entity khi cấu trúc đó rất ít trường và rất ổn định.
Ít phụ thuộc vào DB. Ví dụ TokenEntity chẳng hạn, AuthEntity
chẳng hạn. Còn các entity khác quá nhiều field hoặc sau này do vấn đề thiết kế lạ DB mà thay
 đổi số field thì không nên. Như  FoodNutritionEntity thì không nên vì nó quá nhiều field


Service và Reposity thì có thể cân nhắc để sáp nhập thành một cho đỡ phức tạp. Cần linh hoạt