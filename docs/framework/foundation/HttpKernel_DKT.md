# HttpKernel — Tài liệu chuyển giao tri thức thiết kế

**Subsystem:** `Core\Foundation\HttpKernel` — PBT Framework  
**Tài liệu dự kiến:** `HttpKernel.md`  
**Ngày tổng hợp:** 2026-10-23  
**Nguồn:** Các đoạn code và quyết định được trao đổi trong cuộc hội thoại về `HttpKernel`, `ContextRouteInfo` và `ContextRouter::matchUri()`.  
**Cập nhật:** 2026-11-03 — bổ sung `function` vào projection RouteInfo lưu trong RequestAuthContext.  
**Giới hạn xác minh:** Bản tổng hợp ban đầu dựa trên trao đổi. Riêng cập nhật projection ngày 2026-11-03 đã đối chiếu trực tiếp `HttpKernel`, `RequestAuthContext`, `RouteInfo`, `MCARBuilder` và consumer `AuthMiddleware`; không có nghĩa toàn bộ các mục OPEN bên dưới đã được xác minh.

## Quy ước trạng thái

- **CONFIRMED:** Đã được thống nhất, hoặc đã được người dùng thể hiện rõ trong phương án mới nhất và không bị thay thế sau đó. Đây là xác nhận **thiết kế**, không phải xác nhận source đã triển khai.
- **PROPOSED:** Đề xuất xuất hiện trong trao đổi nhưng chưa được người dùng chốt.
- **OPEN:** Chưa đủ căn cứ để kết luận; cần đối chiếu source hoặc tiếp tục thảo luận.
- **SUPERSEDED:** Phương án trước đây đã được phương án mới hơn thay thế; không đưa vào thiết kế hiện hành.

> **Nguyên tắc cho Codex:** Xem các contract có trạng thái CONFIRMED là điểm xuất phát để review, nhưng phải đối chiếu source thực tế trước khi chỉnh sửa. Không tự nâng PROPOSED/OPEN thành quyết định chính thức.

---

## A. Overview

### A.1. Mục đích — CONFIRMED

`Core\Foundation\HttpKernel` tổ chức quá trình xử lý một HTTP request trong PBT Framework:

1. Nạp và tạo các **global middleware**.
2. Chạy `MiddlewareChain` toàn cục; terminal handler của chain này là `HttpKernel::handler()`.
3. Định tuyến request bằng `ContextRouter::matchUri()` (đối tượng router lấy từ `RouterFactory`).
4. Chuyển phần thông tin định tuyến cần cho xử lý request/authentication vào `RequestAuthContext`.
5. Resolve controller và xác định function/action.
6. Tạo và chạy **route-specific middleware**, với terminal handler gọi `controller->doFunction($strFunction)`.

`HttpKernel` điều phối các thành phần; logic nhận diện URI, tìm route và phát hiện route không tồn tại nằm ở `ContextRouter` (chi tiết trong D).

### A.2. Ranh giới trách nhiệm — CONFIRMED

- `ContextRouter` quyết định việc URI/route có hợp lệ và tồn tại hay không; ném `HttpException(404, ...)` khi không hợp lệ/không tìm được route.
- `HttpKernel` nhận kết quả `matchUri()` **đã hợp lệ nếu lời gọi return bình thường**, cập nhật context và điều phối dispatch.
- `RequestAuthContext` không mang nguyên `RouteInfo` của dispatcher; chỉ nhận phần dữ liệu đã được chọn lọc.
- Hai tầng middleware được tổ chức riêng: global middleware bên ngoài; route middleware sau routing.

### A.3. Ngoài phạm vi hoặc chưa mô tả — OPEN

Tài liệu không xác lập cơ chế trả HTTP response, cách render trang lỗi, nội dung đầy đủ của permission/authorization, vòng đời `RequestAuthContext`, hoặc API bên trong `MiddlewareChain`, vì chưa có đủ source/contract trong cuộc trao đổi.

---

## B. Architecture

### B.1. Các class và trách nhiệm

| Thành phần | Trách nhiệm có căn cứ từ trao đổi | Trạng thái |
|---|---|---|
| `Core\Foundation\HttpKernel` | Điều phối global middleware, routing, context, controller và route middleware | CONFIRMED |
| `Core\Routing\RouterFactory` | `create()` cung cấp đối tượng router dùng để gọi `matchUri()` | CONFIRMED về cách sử dụng; kiểu trả về cụ thể chưa kiểm chứng |
| `Core\Routing\ContextRouter` | Phân tích URI, tìm `RouteInfo`, tính thông tin route/context, trả `ContextRouteInfo` hoặc ném 404 | CONFIRMED theo method được người dùng cung cấp |
| `Core\Routing\ContextRouteInfo` | Định nghĩa các key của array kết quả `matchUri()` và `createEmpty()` | CONFIRMED theo bản class mới nhất |
| `Core\Routing\RouteInfo` | Định nghĩa constant/key của thông tin route đầy đủ | CONFIRMED về các key đã dùng; toàn bộ schema chưa được cung cấp |
| `Core\Http\RequestAuthContext` | Giữ `Request` và nhận một tập thông tin route/auth được lọc qua `setRouteMatchResult()` | CONFIRMED về cách gọi; implementation chưa được kiểm chứng |
| `Core\Middleware\MiddlewareFactory` | `createList($arrFQCN)` tạo danh sách middleware | CONFIRMED về cách gọi |
| `Core\Middleware\MiddlewareChain` | Nhận danh sách middleware và terminal handler; thực thi qua `handleChain($requestAuthContext)` | CONFIRMED về cách gọi; callback/return contract bên trong chưa được xác minh |
| `Core\Controller\ControllerResolver` | `create($strFQCN, $requestAuthContext)` tạo controller thích hợp | CONFIRMED về cách gọi |
| Controller được resolve | `doFunction($strFunction)` thực thi action/function đích | CONFIRMED về cách gọi |
| `Core\Http\HttpException` | Biểu diễn lỗi HTTP; `ContextRouter` dùng mã 404 cho lỗi routing | CONFIRMED |

Những class được sử dụng bên trong `ContextRouter::matchUri()` còn bao gồm `UrlToMCAOParser`, `MCAOInfo` và `staticRouter` (class thực tế của `staticRouter` chưa có trong trích đoạn).

### B.2. Luồng điều khiển — CONFIRMED

```text
HttpKernel::dispatch()
  |
  +-- require CONFIG_PATH . '/middleware.glb.php'
  +-- MiddlewareFactory::createList($arrFQCN)
  +-- new MiddlewareChain($arrGlobalMiddleware, [$this, 'handler'])
  +-- MiddlewareChain::handleChain($requestAuthContext)
             |
             +-- [các global middleware; khi tiếp tục đến terminal handler]
                        |
                        v
                 HttpKernel::handler()
                   |
                   +-- RouterFactory::create()
                   +-- ContextRouter::matchUri(Request)
                   |      +-- hợp lệ: trả ContextRouteInfo
                   |      +-- route không hợp lệ/không tồn tại: throw HttpException(404)
                   +-- updateRequestAuthContext(ContextRouteInfo)
                   +-- đọc FQCN và function từ full RouteInfo
                   +-- ControllerResolver::create(FQCN, RequestAuthContext)
                   +-- tạo closure: controller->doFunction(function)
                   +-- MiddlewareFactory::createList(route middlewares)
                   +-- new MiddlewareChain(route middlewares, closure)
                   +-- MiddlewareChain::handleChain($requestAuthContext)
                                |
                                +-- [route middleware; nếu đến terminal handler]
                                             |
                                             v
                                       controller->doFunction(...)
```

**Lưu ý:** Việc global middleware hoặc route middleware có thể ngắt chain, xử lý response hay exception như thế nào **chưa được xác minh**. Sơ đồ chỉ thể hiện thứ tự khi chain tiếp tục đến terminal handler.

---

## C. Current Design Contracts

### C.1. `Core\Routing\ContextRouteInfo` — CONFIRMED

Contract mới nhất được người dùng cung cấp:

```php
namespace Core\Routing;

class ContextRouteInfo
{
    public const FIELD_MCAO                   = 'mcao';
    public const FIELD_ROUTE_INFO             = 'route_info';
    public const FIELD_CONTEXT_ACCEPTED_ROLES = 'context_accepted_roles';
    public const FIELD_MIDDLEWARES            = 'middlewares';
    public const FIELD_PROHIBITED_MODULE      = 'prohibited_module';
    public const FIELD_PROHIBITED_ROLE        = 'prohibited_role';

    public static function createEmpty(): array
    {
        return [
            self::FIELD_MCAO                   => null,
            self::FIELD_ROUTE_INFO             => null,
            self::FIELD_CONTEXT_ACCEPTED_ROLES => null,
            self::FIELD_MIDDLEWARES            => null,
            self::FIELD_PROHIBITED_MODULE      => null,
            self::FIELD_PROHIBITED_ROLE        => null
        ];
    }
}
```

- `FIELD_CONTEXT_ACCEPTED_ROLES` là field mới được bổ sung, thay cho luồng trước đó dùng `FIELD_AUTH_POLICY` khi truyền thông tin tới `RequestAuthContext` — **CONFIRMED**.
- `createEmpty()` khởi tạo **cả sáu key với giá trị `null`** — **CONFIRMED**.
- Không có method `isValid()` trong đoạn class được cung cấp; **không suy đoán** method đó có hay không trong bản source đang lưu.
- `FIELD_MCAO` và `FIELD_ROUTE_INFO` không còn `null` khi `matchUri()` return bình thường, theo luồng được cung cấp — **CONFIRMED**.
- `FIELD_PROHIBITED_MODULE` có giá trị `true` hoặc `false` khi return bình thường. `FIELD_PROHIBITED_ROLE` có thể là `null` (module bị cấm) hoặc boolean (module không bị cấm) — **CONFIRMED**.
- `FIELD_CONTEXT_ACCEPTED_ROLES` là kết quả của `calculateContextAcceptedRoles(...)`. Kiểu dữ liệu chính xác, ý nghĩa của `null`/`[]` và invariant sâu hơn **chưa được xác định** — **OPEN**.
- `FIELD_MIDDLEWARES` được gán từ `buildMiddlewares(...)` trước khi return bình thường, nhưng hình dạng/khi rỗng là `[]` hay `null` chưa được xác minh — **OPEN**.

### C.2. Các symbol `RouteInfo` được dùng — CONFIRMED / OPEN

Trong các đoạn code mới nhất có tham chiếu:

```php
RouteInfo::FIELD_ROLES
RouteInfo::FIELD_ROUTE_TYPE
RouteInfo::FIELD_FQCN
RouteInfo::FIELD_FUNCTION
RouteInfo::FIELD_METHOD
```

Các key khác từng xuất hiện ở phiên bản cũ:

```php
RouteInfo::FIELD_AUTHENTICATION_PATH
RouteInfo::FIELD_DEFAULT_BUSINESS_PATH
```

Hai key cuối **không còn được chọn để đưa vào `RequestAuthContext` trong phương án mới nhất**, nhưng không có cơ sở để nói rằng chúng đã bị xóa khỏi schema `RouteInfo`. Giá trị chuỗi của các constant, tính bắt buộc/nullable, kiểm tra hợp lệ và toàn bộ shape của `RouteInfo` phải đọc source hiện hành — **OPEN**.

### C.3. `HttpKernel`: dependency và public/protected API — CONFIRMED

Constructor theo phiên bản mới nhất:

```php
public function __construct(
    RequestAuthContext $requestAuthContext,
    RouterFactory $routerFactory,
    MiddlewareFactory $middlewareFactory,
    ControllerResolver $controllerResolver
)
```

Các property tương ứng được khai báo `protected` và có đúng các kiểu class nêu trong constructor.

Method:

```php
public function dispatch(): void
public function handler(): void
protected function updateRequestAuthContext(array $arrContextRouteInfo): void
```

- `handler()` là **terminal handler của global middleware chain** trong thiết kế mới nhất.
- `updateRequestAuthContext()` là helper có side effect cập nhật object `RequestAuthContext`; không trả kết quả.
- Method cũ `route(): array` đã bị bỏ khỏi thiết kế và không còn được gọi — **SUPERSEDED**.
- Tên biến `$match` vẫn đang được người dùng sử dụng ở bản code mới nhất. Việc đổi thành `$arrContextRouteInfo` mới là đề xuất về convention, không phải yêu cầu chức năng — **PROPOSED**.

### C.4. `RequestAuthContext`: contract truyền dữ liệu — CONFIRMED; projection đã đối chiếu source 2026-11-03

Helper `updateRequestAuthContext()` chuyển dữ liệu sang:

```php
$this->requestAuthContext->setRouteMatchResult(
    $arrContextRouteInfo[ContextRouteInfo::FIELD_MCAO],
    $arrRouteInfo,
    $arrContextRouteInfo[ContextRouteInfo::FIELD_CONTEXT_ACCEPTED_ROLES],
    $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE],
    $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE]
);
```

`$arrRouteInfo` ở đây là **projection (allowlist)** từ full `RouteInfo`:

```php
$arrRouteInfo = [
    RouteInfo::FIELD_ROLES => $arrFullRouteInfo[RouteInfo::FIELD_ROLES],
    RouteInfo::FIELD_FUNCTION => $arrFullRouteInfo[RouteInfo::FIELD_FUNCTION],
    RouteInfo::FIELD_ROUTE_TYPE => $arrFullRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
];
```

**Invariant — CONFIRMED, đã đối chiếu source:** Chỉ `roles`, `function` và `route_type` được chuyển sang context từ `RouteInfo`; `fqcn`, `method` và các field bổ sung trong tương lai không tự động đi theo.

**Đường truyền đã kiểm chứng:** `setRouteMatchResult()` lưu nguyên `$arrRouteInfo`; `routeInfo(): ?array` trả lại mảng này. Setter không giới hạn projection ở hai key cũ. `AuthMiddleware` đọc riêng `roles` và `route_type`, nên không cần đổi logic khi bổ sung `function`.

**Ý nghĩa của `function`:** Đây là tên method controller đã được router phân giải, không nhất thiết trùng action trong MCAO. `MCARBuilder` lấy `$arrActionDetail['function'] ?? $strAction`. `HttpKernel` đưa giá trị này vào context trước khi tạo controller và dùng cùng giá trị để gọi `doFunction()`. Thiết kế `RequestNormalizer` sẽ lấy `routeInfo()[RouteInfo::FIELD_FUNCTION]` để gọi `BaseRequestSchema::createSchema($strFunctionName)`; trước route match, `routeInfo()` vẫn có thể là `null`. Không thay đổi cấu trúc MCAO.

### C.5. Middleware và callback — CONFIRMED về cách dùng, OPEN về interface chi tiết

Global chain:

```php
new MiddlewareChain($arrGlobalMiddleware, [$this, 'handler']);
$middlewareChain->handleChain($this->requestAuthContext);
```

Route chain có terminal handler theo mẫu:

```php
$handler = function() use ($controller, $strFunction) {
    $controller->doFunction($strFunction);
};

new MiddlewareChain($arrMiddleware, $handler);
$middlewareChain->handleChain($this->requestAuthContext);
```

- `HttpKernel::handler()` có **không tham số**, return `void` theo bản người dùng dán.
- Route terminal closure **không khai báo tham số** theo bản người dùng dán.
- Kiểu trả về bắt buộc của closure, cách `MiddlewareChain` gọi callable, API của từng middleware và semantics của `handleChain()` **chưa được cung cấp** — **OPEN**. Không được ngầm định một interface PSR-15 hay contract khác.

### C.6. `ContextRouter::matchUri()` — CONFIRMED theo đoạn code được cung cấp

```php
public function matchUri(Request $request): array
```

Phụ thuộc/truy cập được quan sát trong method:

```php
$this->staticRouter->createMCABasic();
new UrlToMCAOParser($mcaBasic);
$parser->parse($request->uri());
MCAOInfo::toMCAPath($arrMCAO);
$this->staticRouter->getRouteInfo($arrMCA);
$this->effectiveUserRolesAtRoute($arrRouteInfo);
$this->buildMiddlewares($arrMCA, $arrRouteInfo[RouteInfo::FIELD_METHOD], $arrEffectiveUserRolesAtRoute);
$this->calculateContextAcceptedRoles($arrMCAO, $arrRouteInfo);
$this->isModuleProhibited($strModule);
```

Tên và prototype đầy đủ của các helper nội bộ khác ngoài `matchUri()` **chưa được xác minh**. Không tự suy diễn thuật toán bên trong các helper.

---

## D. Algorithms

### D.1. `HttpKernel::dispatch()` — CONFIRMED

1. `require CONFIG_PATH . '/middleware.glb.php'` để nhận `$arrFQCN` (danh sách dùng để tạo global middleware).
2. `$arrGlobalMiddleware = $this->middlewareFactory->createList($arrFQCN)`.
3. Tạo `new MiddlewareChain($arrGlobalMiddleware, [$this, 'handler'])`.
4. Gọi `$middlewareChain->handleChain($this->requestAuthContext)`.

**Quan trọng:** Dùng `require`, **không** `require_once`. Với file cấu hình trả về array, `require_once` có thể không trả lại array trong lần include sau nếu file đã được nạp trước đó.

### D.2. `HttpKernel::handler()` — CONFIRMED

Nếu được global chain gọi đến:

1. `$contextRouter = $this->routerFactory->create()`.
2. `$match = $contextRouter->matchUri($this->requestAuthContext->request())`.
3. **Không kiểm tra lại** `FIELD_MCAO`/`FIELD_ROUTE_INFO` có `null` hay không; `matchUri()` có nghĩa vụ ném 404 khi không hợp lệ và chỉ return bình thường khi hai dữ liệu tồn tại.
4. `$this->updateRequestAuthContext($match)`.
5. Lấy full `RouteInfo = $match[ContextRouteInfo::FIELD_ROUTE_INFO]`.
6. Lấy `$strFQCN` bằng `RouteInfo::FIELD_FQCN` và `$strFunction` bằng `RouteInfo::FIELD_FUNCTION`.
7. `$controller = $this->controllerResolver->create($strFQCN, $this->requestAuthContext)`.
8. Tạo closure terminal handler gọi `$controller->doFunction($strFunction)`.
9. Tạo danh sách middleware từ `$match[ContextRouteInfo::FIELD_MIDDLEWARES]`.
10. Tạo route `MiddlewareChain($arrMiddleware, $handler)` và gọi `handleChain($this->requestAuthContext)`.

Thứ tự trên phản ánh phiên bản hiện tại được dán trong chat. Đặc biệt, **controller được resolve trước khi route middleware chạy**; đây là quan sát về trình tự, không tự khẳng định quyết định bảo mật/hiệu năng của thứ tự này đã được phân tích và chốt (xem H).

### D.3. `HttpKernel::updateRequestAuthContext()` — CONFIRMED

1. Lấy full route array qua `ContextRouteInfo::FIELD_ROUTE_INFO`.
2. Chỉ sao chép ba field `RouteInfo::FIELD_ROLES`, `RouteInfo::FIELD_FUNCTION` và `RouteInfo::FIELD_ROUTE_TYPE` sang `$arrRouteInfo` mới.
3. Gọi `RequestAuthContext::setRouteMatchResult()` theo đúng thứ tự **MCAO, route subset, context accepted roles, prohibited module, prohibited role**.

Helper không cần tự kiểm tra `FIELD_ROUTE_INFO === null` trong đường chạy chuẩn vì invariant được đảm bảo tại `ContextRouter::matchUri()`.

### D.4. `ContextRouter::matchUri(Request $request): array` — CONFIRMED

Trình tự trong đoạn code mới nhất:

1. Lấy `$mcaBasic = $this->staticRouter->createMCABasic()`.
2. Tạo `UrlToMCAOParser($mcaBasic)` và parse `$request->uri()` thành `$arrMCAO`.
3. Khởi tạo `$arrContextRouteInfo = ContextRouteInfo::createEmpty()`.
4. Nếu `$arrMCAO === null`, **throw `HttpException(404, ...)`**, không return array rỗng.
5. Tính `$arrMCA = MCAOInfo::toMCAPath($arrMCAO)`.
6. Lấy `$arrRouteInfo = $this->staticRouter->getRouteInfo($arrMCA)`.
7. Gán `FIELD_MCAO = $arrMCAO`; nếu `$arrRouteInfo === null`, **throw `HttpException(404, ...)`**.
8. Tính `$arrEffectiveUserRolesAtRoute = $this->effectiveUserRolesAtRoute($arrRouteInfo)`.
9. Tính `$arrMiddleware = $this->buildMiddlewares($arrMCA, $arrRouteInfo[RouteInfo::FIELD_METHOD], $arrEffectiveUserRolesAtRoute)`.
10. Lấy `$strModule = $arrMCAO[MCAOInfo::FIELD_MODULE]`.
11. Gán `FIELD_ROUTE_INFO = $arrRouteInfo`.
12. Gán `FIELD_CONTEXT_ACCEPTED_ROLES = $this->calculateContextAcceptedRoles($arrMCAO, $arrRouteInfo)`.
13. Gán `FIELD_MIDDLEWARES = $arrMiddleware`.
14. Nếu `$this->isModuleProhibited($strModule)` là `true`: đặt `FIELD_PROHIBITED_MODULE = true`, `FIELD_PROHIBITED_ROLE = null`, rồi **return**.
15. Ngược lại đặt `FIELD_PROHIBITED_MODULE = false`.
16. Nếu `empty($arrEffectiveUserRolesAtRoute)` là `true`: đặt `FIELD_PROHIBITED_ROLE = true`, rồi **return**.
17. Ngược lại đặt `FIELD_PROHIBITED_ROLE = false`, rồi **return**.

**Lưu ý quan trọng về comment:** Trong phiên bản dán, comment “Từ đây trở đi thì `$arrContextRouteInfo['mcao']` và `$arrContextRouteInfo['route_info']` mới khác null” xuất hiện **trước** dòng gán `FIELD_ROUTE_INFO`. Đây là comment không hoàn toàn chính xác về trạng thái array ngay tại vị trí đó. Nhận xét cuối cùng trong chat đề nghị sửa comment thành “`$arrMCAO` và `$arrRouteInfo` chắc chắn khác null” hoặc dời phép gán lên sau check; **chưa có bằng chứng người dùng đã sửa** — **PROPOSED**.

### D.5. Các nhánh và invariant khi `matchUri()` kết thúc

| Tình huống | Kết quả theo code được cung cấp | Trạng thái |
|---|---|---|
| Parser không nhận diện URI/prefix (`$arrMCAO === null`) | `throw HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về kết quả đường dẫn không hợp lệ')` | CONFIRMED |
| MCAO nhận diện được nhưng `getRouteInfo(...) === null` | `throw HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về dữ liệu null')` | CONFIRMED |
| Route tìm được, module bị cấm | Return đầy đủ key; `prohibited_module=true`, `prohibited_role=null` | CONFIRMED |
| Route tìm được, module không bị cấm, effective roles rỗng | Return; `prohibited_module=false`, `prohibited_role=true` | CONFIRMED |
| Route tìm được, module không bị cấm, effective roles không rỗng | Return; `prohibited_module=false`, `prohibited_role=false` | CONFIRMED |

- Nếu method **return bình thường**, `FIELD_MCAO` và `FIELD_ROUTE_INFO` đã được gán từ các giá trị khác `null` — **CONFIRMED**.
- Việc *module hoặc role bị cấm* **không** dẫn đến throw 403/404 ngay trong đoạn `matchUri()` được cung cấp: method trả flag cho bước sau. Nơi tiêu thụ flag/chặn request phải kiểm chứng — **OPEN**.
- `FIELD_CONTEXT_ACCEPTED_ROLES` và `FIELD_MIDDLEWARES` được tính **trước** kiểm tra `isModuleProhibited(...)`, nên vẫn được gán cả ở nhánh bị cấm module — **CONFIRMED**.
- `FIELD_PROHIBITED_ROLE` được quyết định từ `empty($arrEffectiveUserRolesAtRoute)`, **không phải** trực tiếp từ `FIELD_CONTEXT_ACCEPTED_ROLES` — **CONFIRMED**. Quan hệ chi tiết giữa hai bộ role chưa biết — **OPEN**.

---

## E. Confirmed Design Decisions

### E.1. Chuyển quyền phát hiện route không hợp lệ về `ContextRouter` — CONFIRMED

- **Quyết định:** `ContextRouter::matchUri()` ném `HttpException(404, ...)` khi parser trả `null` hoặc không tìm thấy `RouteInfo`. `HttpKernel::handler()` **không** kiểm tra lại `FIELD_MCAO`/`FIELD_ROUTE_INFO`.
- **Lý do:** `matchUri()` đảm bảo postcondition trên; kiểm tra thêm ở `HttpKernel` là trùng trách nhiệm.
- **Ảnh hưởng:** `updateRequestAuthContext()` chỉ được gọi sau khi `matchUri()` return thành công; không cần xử lý `RouteInfo === null` trong đường chạy bình thường.

### E.2. `ContextRouteInfo` dùng `context_accepted_roles` — CONFIRMED

- **Quyết định:** Bổ sung `FIELD_CONTEXT_ACCEPTED_ROLES = 'context_accepted_roles'` và gán từ `calculateContextAcceptedRoles($arrMCAO, $arrRouteInfo)`.
- **Lý do:** Luồng dữ liệu mới cần kết quả role được chấp nhận theo context, thay cho cách trước đó truyền `auth_policy` sang request context.
- **Ảnh hưởng:** Cập nhật đồng bộ contract `ContextRouteInfo`, `ContextRouter` và call site `RequestAuthContext::setRouteMatchResult()`; không dùng `ContextRouteInfo::FIELD_AUTH_POLICY` trong phương án mới.

### E.3. Cho phép `ContextRouter` trả cờ prohibited — CONFIRMED

- **Quyết định:** Module/role bị cấm được biểu diễn bằng `prohibited_module` và `prohibited_role` trong kết quả route, không bị biến thành 404 ngay trong code `matchUri()` đã được cung cấp.
- **Lý do:** Đây là cách biểu diễn trong thiết kế mới nhất; **chưa có lý giải chi tiết** về chính sách chặn ở middleware.
- **Ảnh hưởng:** Consumer của hai flag phải được review trong source để bảo đảm enforcement thực tế.

### E.4. Projection `RouteInfo` dùng allowlist ba field — CONFIRMED

- **Quyết định:** Khi chuyển sang `RequestAuthContext`, chỉ lấy `FIELD_ROLES`, `FIELD_FUNCTION` và `FIELD_ROUTE_TYPE` từ full `RouteInfo`.
- **Lý do:** Cung cấp tên function đã phân giải để chọn RequestSchema; vẫn giới hạn rõ các field được chuyển sang context.
- **Ảnh hưởng:** Consumer có thể lấy function qua `routeInfo()`. Những nơi cần `FQCN` hoặc `method` vẫn dùng full `RouteInfo` tại `HttpKernel`/router. MCAO giữ nguyên action; không đổi thành MCFO/MCAFO.

### E.5. Giữ global chain và route chain tách biệt — CONFIRMED

- **Quyết định:** `dispatch()` chạy global middleware chain; terminal gọi `handler()`, trong đó route middleware chain có terminal gọi controller action.
- **Lý do:** Phân tách giai đoạn trước routing với giai đoạn theo route.
- **Ảnh hưởng:** Những middleware cần route metadata phải nằm đúng giai đoạn; cơ chế điều khiển/short-circuit cụ thể còn cần kiểm chứng.

### E.6. Sử dụng `updateRequestAuthContext()` làm helper — CONFIRMED

- **Quyết định:** Không giữ `route()` vừa thực hiện match vừa cập nhật context vừa return kết quả; thao tác match đặt ngay trong `handler()`, projection và setter đặt trong helper `protected updateRequestAuthContext(array ...): void`.
- **Lý do:** Trách nhiệm và side effect minh bạch hơn.
- **Ảnh hưởng:** Luồng `HttpKernel::handler()` tuyến tính; context được cập nhật trước khi tạo controller/route middleware.

### E.7. Tên terminal method hiện tại là `handler()` — CONFIRMED theo phương án mới nhất

- **Quyết định:** Global chain truyền `[$this, 'handler']`; method công khai `public function handler(): void`.
- **Lý do:** Method giữ vai trò terminal handler của global chain.
- **Ảnh hưởng:** Nếu đổi tên sau này phải đồng bộ callback; các tên `buildHandler()`/`dispatchRoute()` không phải phương án đang áp dụng.

### E.8. Dùng `require` cho config array — CONFIRMED

- **Quyết định:** `$arrFQCN = require CONFIG_PATH . '/middleware.glb.php';`.
- **Lý do:** File config trả về array; `require_once` có thể trả một giá trị không phải array khi file đã được include trước đó.
- **Ảnh hưởng:** Không quay lại `require_once` nếu config dùng mẫu `return [...]`.

---

## F. Superseded / Rejected Designs

| Thiết kế trước | Trạng thái | Phương án mới nhất / lý do |
|---|---|---|
| `HttpKernel::route(): array` tự `matchUri()`, set context rồi return `ContextRouteInfo` | SUPERSEDED | `handler()` trực tiếp gọi `matchUri()`; `updateRequestAuthContext()` chỉ làm projection + setter; tránh hàm đa trách nhiệm |
| Giữ một method `__route()` không còn dùng | SUPERSEDED | Bỏ method cũ khỏi thiết kế; không giữ dead method để đánh dấu |
| `buildHandler()` | SUPERSEDED | Tên đang được người dùng dùng là `handler()`; `buildHandler()` gây ấn tượng build và return handler dù thực tế còn dispatch |
| Đề xuất tên `dispatchRoute()` | PROPOSED, **không được chọn** | Người dùng tiếp tục cung cấp phiên bản dùng `handler()`; không coi `dispatchRoute()` là quyết định hiện hành |
| `HttpKernel::handler()` tự check `FIELD_MCAO === null || FIELD_ROUTE_INFO === null` rồi throw 404 | SUPERSEDED | Bỏ kiểm tra lặp vì `ContextRouter::matchUri()` đã tự ném 404 trước khi return |
| Projection từ full `RouteInfo` gồm `route_type`, `authentication_path`, `default_business_path` | SUPERSEDED | Projection mới nhất gồm `roles`, `function`, `route_type`; không suy diễn rằng các constant cũ bị xóa khỏi `RouteInfo` |
| Projection chỉ gồm `roles`, `route_type` | SUPERSEDED | Ngày 2026-11-03 bổ sung `function` để context cung cấp tên method đã phân giải cho việc chọn RequestSchema |
| `ContextRouteInfo::FIELD_AUTH_POLICY` được chuyển tới `setRouteMatchResult()` | SUPERSEDED | Dùng `FIELD_CONTEXT_ACCEPTED_ROLES`; contract mới gồm sáu field đã nêu |
| Truyền nguyên full `RouteInfo` vào `RequestAuthContext` | REJECTED trong thảo luận | Giữ allowlist nhằm giới hạn coupling, nhất là khi `RouteInfo` có thể được mở rộng |
| Lọc kiểu blacklist (ví dụ `unset` bốn field `roles/fqcn/function/method` trong cách đặt vấn đề cũ) | REJECTED về nguyên tắc | Whitelist field cần thiết tốt hơn; lưu ý `roles` hiện là field **được giữ**, không phải field bỏ |
| `require_once CONFIG_PATH . '/middleware.glb.php'` | SUPERSEDED | Thay bằng `require` cho file config trả về array |

**Ghi chú:** Phương án thêm class/schema độc lập kiểu `RouteContextInfo`, hoặc đổi tên biến projection thành `$arrRouteContextInfo`, từng được nêu như hướng có thể cân nhắc nhưng **chưa được thống nhất**. Không tự tạo class mới khi tiếp quản.

---

## G. Implementation Status

### G.1. Những gì đã được cung cấp dưới dạng code trong hội thoại — CONFIRMED về bằng chứng trao đổi

1. Bản `ContextRouteInfo` với `FIELD_CONTEXT_ACCEPTED_ROLES` và `createEmpty()` có sáu field giá trị `null`.
2. Bản `HttpKernel` với constructor bốn dependency, `dispatch(): void`, `handler(): void`, `updateRequestAuthContext(array $arrContextRouteInfo): void`; code mới nhất dùng `require` và **không còn check 404** trong `handler()`.
3. Method `ContextRouter::matchUri(Request $request): array` thể hiện đầy đủ đường đi parse → static route → tính roles/middleware → context accepted roles → prohibited flags, cùng hai nhánh throw 404.

Các bằng chứng này đủ để xác định **thiết kế đang trao đổi**, nhưng **không đủ** để khẳng định code tương ứng đã được commit/lưu trong repository hoặc chạy thành công.

### G.2. Việc cần kiểm tra bằng source thật — OPEN

- File thực tế và nội dung hiện tại của `HttpKernel`, `ContextRouter`, `ContextRouteInfo`, `RouteInfo`.
- `RequestAuthContext::setRouteMatchResult()` hiện có bao nhiêu tham số, tên/type/nullable/validation, và các consumer của các field đã cập nhật ra sao.
- Giá trị và semantics của `RouteInfo::FIELD_ROLES`, `FIELD_ROUTE_TYPE`, `FIELD_METHOD`, `FIELD_FQCN`, `FIELD_FUNCTION`.
- `ContextRouter::effectiveUserRolesAtRoute()`, `calculateContextAcceptedRoles()`, `buildMiddlewares()`, `isModuleProhibited()` hoạt động thế nào.
- Nguồn và shape của file `middleware.glb.php`; `MiddlewareFactory::createList()` xử lý danh sách rỗng/null thế nào.
- `MiddlewareChain` có thực sự chấp nhận cả callable `[$this, 'handler']` và closure không tham số, dùng `RequestAuthContext` như thế nào, và quy tắc short-circuit/error propagation.
- Controller resolver, controller `doFunction()` và response lifecycle.
- Hiện có test tự động hay không; chưa có bằng chứng test đã chạy/đạt.

### G.3. Những thay đổi có thể cần áp dụng nếu source chưa đồng bộ — OPEN cho đến khi đối chiếu

Nếu working tree vẫn ở phiên bản cũ: thêm field `context_accepted_roles`; cập nhật `ContextRouter::matchUri()`; điều chỉnh danh sách đối số setter; đổi projection thành `roles` + `function` + `route_type`; bỏ check lặp 404; bỏ `route()`/`__route()` không dùng; đổi `require_once` thành `require`. Đây là **danh sách đối chiếu có điều kiện**, không khẳng định các thay đổi này hiện còn thiếu.

---

## H. Open Issues

1. **OPEN — Enforcement của `prohibited_module` / `prohibited_role`:** `ContextRouter` trả flag, còn `HttpKernel::handler()` được dán vẫn tiếp tục tạo controller và route chain. Cần tìm nơi quyết định từ chối truy cập, cách sinh status HTTP (ví dụ 403), và bảo đảm action không chạy trái phép. **Không tự giả định** middleware nào đang xử lý.
2. **OPEN — Quan hệ hai tập role:** `effectiveUserRolesAtRoute()` dùng để xây middleware và xác định `prohibited_role`; `calculateContextAcceptedRoles()` cung cấp field mới trong context. Chưa biết định nghĩa, điều kiện rỗng và invariant quan hệ giữa chúng.
3. **OPEN — Kiểu và biểu diễn rỗng của `context_accepted_roles`:** Có đề xuất AI trước đây dùng `null` khi chưa tính và `[]` khi đã tính nhưng không role nào được chấp nhận; **chưa được người dùng chốt**, không được ép thành invariant.
4. **OPEN — Kiểu và biểu diễn rỗng của `middlewares`:** `createEmpty()` đặt `null`; `matchUri()` gán kết quả `buildMiddlewares()`. Cần xác định contract với `MiddlewareFactory::createList()`.
5. **OPEN — Thứ tự resolve controller trước route middleware:** Đây là thứ tự code hiện tại. Cần xem constructor/controller resolver có side effect hay yêu cầu authorization phải được thực hiện trước hay không. Chưa có quyết định đổi thứ tự.
6. **OPEN — API thực của `RequestAuthContext::setRouteMatchResult()`:** Chưa thấy declaration và validation; cần bảo đảm tương thích đúng năm đối số và nullable behavior của `prohibited_role`.
7. **OPEN — Error/response boundary:** Chưa biết nơi catch `HttpException`, render 404, xử lý exception ở middleware/controller và trả response cuối.
8. **OPEN — Middleware callable contract:** Kiểm chứng signature terminal callback và cơ chế gọi `handleChain()`; không giả định các tiêu chuẩn bên ngoài.
9. **OPEN — Validation của `ContextRouteInfo`/`RouteInfo`:** Chưa có nội dung đầy đủ về `isValid`, field bắt buộc, schema và return type các helper.
10. **PROPOSED — Điều chỉnh comment trong `matchUri()`:** Đặt comment đúng vị trí hoặc sửa cách diễn đạt vì phép gán `FIELD_ROUTE_INFO` xảy ra sau check. Là cải tiến tính chính xác của comment, không thay đổi contract.
11. **PROPOSED — Đổi `$match` thành `$arrContextRouteInfo`:** Phù hợp convention tên array được dùng trong PBT, nhưng chưa phải yêu cầu bắt buộc.

---

## I. Compatibility and Dependencies

### I.1. Những điểm cần bảo toàn theo thiết kế — CONFIRMED

- Namespace/class đích: `Core\Foundation\HttpKernel`.
- Injection bốn dependency theo constructor đã nêu.
- `public dispatch(): void` là entry method đã được cung cấp.
- `public handler(): void` là callable của global middleware chain; việc đổi tên khi chưa đồng bộ chỗ tham chiếu sẽ làm hỏng dispatch.
- `protected updateRequestAuthContext(array $arrContextRouteInfo): void` làm projection + cập nhật context.
- `ContextRouter::matchUri(Request $request): array` chỉ return normally khi có MCAO và full RouteInfo; hai loại lỗi missing route hiện throw 404.
- `ContextRouteInfo::createEmpty()` có đúng sáu constant/key mới nhất.
- `RequestAuthContext::setRouteMatchResult()` được gọi với năm đối số theo đúng thứ tự của C.4.
- `RouteInfo` đầy đủ vẫn được dùng để lấy FQCN/function/method; chỉ projection bị thu hẹp ở ranh giới context.

### I.2. Những thành phần có thể chịu ảnh hưởng — cần kiểm chứng

`ContextRouter`, `StaticRouter`/`staticRouter`, `RouteInfo`, `ContextRouteInfo`, `UrlToMCAOParser`, `MCAOInfo`, `RequestAuthContext`, middleware đọc roles/context/prohibited flags, `MiddlewareFactory`, `MiddlewareChain`, `ControllerResolver`, controller `doFunction`, các module authentication/authorization và các đoạn code khác từng đọc `FIELD_AUTH_POLICY`.

Cần tìm mọi chỗ sử dụng `ContextRouteInfo::FIELD_AUTH_POLICY`, `FIELD_CONTEXT_ACCEPTED_ROLES`, `setRouteMatchResult()` và `prohibited_*` trước khi kết luận thay đổi đã đồng bộ.

### I.3. Ràng buộc chưa xác minh — OPEN

- Có API nào ngoài `HttpKernel` gọi trực tiếp `handler()` hay phụ thuộc vào tên `route()` cũ không?
- Có middleware global nào cần đọc kết quả routing **trước** terminal `handler()` không?
- Có module nào dựa vào `authentication_path` hoặc `default_business_path` trong `RequestAuthContext` từ contract cũ không?
- Có consumer nào cần nguyên `auth_policy` hoặc các thiết lập auth khác không? Nguồn thay thế chưa được chứng minh.
- HTTP lifecycle có cho phép nhiều lần gọi `dispatch()` trong cùng tiến trình không và config/middleware có đáp ứng không?

---

## J. Test Scenarios

**Trạng thái chung:** Chưa có test case nào được xác nhận là **đã triển khai hoặc đã chạy** trong cuộc hội thoại. Toàn bộ tình huống dưới đây mang nhãn **PROPOSED (đề xuất kiểm chứng contract)**, trừ những expected behavior trực tiếp rút từ code đã được dán và ghi rõ là **CONFIRMED behavior**.

| ID | Tình huống kiểm thử đề xuất | Kỳ vọng căn cứ từ trao đổi | Trạng thái |
|---|---|---|---|
| T01 | Request hợp lệ, route tồn tại, module không cấm, effective roles không rỗng | `matchUri()` trả đủ các field; `prohibited_module=false`, `prohibited_role=false`; `HttpKernel` chạy tiếp hai tầng chain nếu middleware cho qua | PROPOSED test / CONFIRMED nhánh route |
| T02 | `UrlToMCAOParser::parse()` trả `null` | `HttpException` mã 404 tại `matchUri()`; `HttpKernel` không cập nhật context/resolve controller | PROPOSED test / CONFIRMED nhánh throw |
| T03 | Có MCAO nhưng `StaticRouter::getRouteInfo()` trả `null` | `HttpException` mã 404; `handler()` không được nhận một route trả về thiếu dữ liệu | PROPOSED test / CONFIRMED nhánh throw |
| T04 | Route tồn tại nhưng `isModuleProhibited()` trả `true` | `matchUri()` trả `prohibited_module=true`, `prohibited_role=null`, không tự throw 403 | PROPOSED test / CONFIRMED nhánh return |
| T05 | Route tồn tại, module không cấm, effective roles rỗng | `prohibited_module=false`, `prohibited_role=true` | PROPOSED test / CONFIRMED nhánh return |
| T06 | Route tồn tại, module không cấm, effective roles không rỗng | `prohibited_module=false`, `prohibited_role=false` | PROPOSED test / CONFIRMED nhánh return |
| T07 | Module bị cấm **đồng thời** effective roles rỗng | Nhánh module có ưu tiên trong code được dán; `prohibited_role=null`, không đánh giá nhánh gán role flag | PROPOSED test / CONFIRMED thứ tự branch |
| T08 | Có route hợp lệ nhưng middleware route không có phần tử | Kiểm chứng `buildMiddlewares()`/`createList()` và chain hoạt động đúng với biểu diễn rỗng thực tế; **chưa chốt** `[]` hay `null` | PROPOSED test / OPEN expected representation |
| T09 | So sánh full RouteInfo và dữ liệu truyền vào `RequestAuthContext` | Setter nhận projection có đúng ba key `FIELD_ROLES`, `FIELD_FUNCTION`, `FIELD_ROUTE_TYPE`; function bằng tên method dùng để dispatch, kể cả khi khác action; không nhận `FQCN`, `METHOD` | PROPOSED test / CONFIRMED projection |
| T10 | `ContextRouter::matchUri()` return bình thường | `FIELD_MCAO` và `FIELD_ROUTE_INFO` khác `null`; `HttpKernel` không có check lặp 404 | PROPOSED test / CONFIRMED postcondition |
| T11 | `context_accepted_roles` được tính | Giá trị tại setter giống kết quả `calculateContextAcceptedRoles()`; **không** tự giả định kiểu/logic sâu hơn | PROPOSED test / CONFIRMED đường truyền |
| T12 | Middleware toàn cục dừng chain trước terminal | Xác minh routing/controller không chạy nếu đó là semantics thật của `MiddlewareChain` | PROPOSED test / OPEN expected behavior |
| T13 | Middleware theo route dừng chain trước terminal | Xác minh `controller->doFunction()` không chạy nếu đó là semantics thật của `MiddlewareChain` | PROPOSED test / OPEN expected behavior |
| T14 | `RequestAuthContext` nhận `prohibited_role=null` khi module bị cấm | Xác minh setter/consumer chấp nhận giá trị này, không lỗi type | PROPOSED test / CONFIRMED giá trị truyền từ router |
| T15 | Cấu hình middleware đã từng được include trong tiến trình | `require` vẫn lấy giá trị `return` của file; tránh lỗi do `require_once` | PROPOSED test / CONFIRMED lý do thiết kế |
| T16 | End-to-end khi module hoặc role bị cấm | Phải **xác định từ source** nơi chặn và bảo đảm action không thực thi trái phép; chưa có expected status/response đã chốt | PROPOSED test / OPEN enforcement |
| T17 | Constructor/controller resolution có side effect | Đánh giá hệ quả của việc resolve controller trước route middleware; không tự thay thứ tự | PROPOSED test/review / OPEN |
| T18 | `HttpException(404)` từ `matchUri()` | Xác minh tầng phía ngoài bắt và biến thành HTTP 404 như mong muốn; tầng catch chưa được cung cấp | PROPOSED test / OPEN response handling |

---

## K. Next Steps

### K.1. Công việc đầu tiên khi chuyển sang Codex — đủ rõ để thực hiện review

1. Mở source hiện hành của `HttpKernel`, `ContextRouter`, `ContextRouteInfo`, `RouteInfo`, `RequestAuthContext`, `MiddlewareFactory`, `MiddlewareChain`, `ControllerResolver`, và file config middleware; không suy đoán đường dẫn nếu chưa tìm thấy.
2. Lập bảng **khác biệt giữa source và bản thiết kế mới nhất trong tài liệu này**. Phân biệt sai khác code, API đang được consumer sử dụng và các quyết định chưa chốt.
3. Dò reference của `FIELD_AUTH_POLICY`, `FIELD_CONTEXT_ACCEPTED_ROLES`, `setRouteMatchResult()`, `prohibited_module`, `prohibited_role` và các chỗ đọc projection `RouteInfo`.
4. Xác nhận hai postcondition/exception branch của `ContextRouter::matchUri()`; nếu đúng như code đã dán, không bổ sung lại check 404 ở `HttpKernel`.
5. Rà soát đường đi authorization end-to-end, đặc biệt **những route bị đánh dấu prohibited có bị chặn trước `doFunction()` hay không**.
6. Rà soát chính xác contract callable của `MiddlewareChain` và return/exception propagation.

### K.2. Những việc đã đủ rõ về hướng thiết kế, nhưng chỉ sửa nếu source chưa đồng bộ

- Duy trì `ContextRouteInfo` sáu field gồm `context_accepted_roles`.
- Dùng projection `RouteInfo` chỉ gồm `FIELD_ROLES`, `FIELD_FUNCTION` và `FIELD_ROUTE_TYPE` khi cập nhật context.
- Duy trì hai tầng middleware, helper `updateRequestAuthContext()` và `require` cho config array.
- Bỏ dead method `route()`/`__route()` và check null lặp trong `HttpKernel::handler()` nếu source vẫn còn.

### K.3. Cần tiếp tục thảo luận trước khi quyết định sửa thiết kế

- Chính sách và nơi thực thi forbidden module/role (bao gồm thứ tự middleware/controller).
- Type/invariant chính xác của `context_accepted_roles`, `middlewares`, projection `RouteInfo` và setter `RequestAuthContext`.
- Tương thích với các consumer cũ của auth policy và route info.
- Cách phát HTTP response và xử lý exception; nguyên tắc callback `MiddlewareChain`.

### K.4. Chỉ dẫn tiếp quản dành cho AI/Codex

> Hãy coi tài liệu này là bản ghi **quyết định thiết kế** của cuộc thảo luận, không phải bản xác nhận trạng thái repository. Trước tiên kiểm chứng source hiện tại và báo cáo các khác biệt. Giữ nguyên các quyết định CONFIRMED, không tái lập các phương án SUPERSEDED, không tự biến PROPOSED/OPEN thành code. Khi gặp mâu thuẫn hoặc thiếu contract, nêu rõ vấn đề để tiếp tục thảo luận. **Chưa có yêu cầu viết lại subsystem trong nhiệm vụ chuyển giao này.**
