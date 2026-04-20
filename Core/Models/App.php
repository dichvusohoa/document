<?php
namespace Core\Models;
use Psr\Container\ContainerInterface;
use \RuntimeException;
use \ReflectionClass;

class App implements ContainerInterface {

    protected array $bindings = [];//chứa key => closure hoặc class name
    protected array $instances = [];//chứa chứa key =>instance của object

    // đăng ký service
    public function set(string $id, $concrete) {
        $this->bindings[$id] = $concrete;
    }

    public function has(string $id): bool {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id) {
        // đã có instance → return luôn
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {//đã binding
            $concrete = $this->bindings[$id];

            if ($concrete instanceof \Closure) {
                $object = $concrete($this);//chú ý truyền this chính là App vào
            } elseif (is_string($concrete)) {
                $object = $this->make($concrete);
            } else {
                $object = $concrete;
            }

            return $this->instances[$id] = $object;
        }
        // 🔥 AUTO RESOLVE nếu là class
        if (class_exists($id)) { 
            //chưa binding nhưng phát hiện class này có tồn tại, tạo ngay
            return $this->instances[$id] = $this->make($id);
        }
        //chưa binding và class cũng không tồn tại
        throw new RuntimeException("Service '$id' not found");
        

        
    }

    // auto resolve dependency (DI)
    public function make(string $class) {
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Class $class not instantiable");
        }

        $ctor = $ref->getConstructor();

        if (!$ctor) {
            return new $class;
        }

        $deps = [];

        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if (!$type) {
                throw new RuntimeException("Cannot resolve param \${$param->getName()}");
            }
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $depClass = $type->getName();
                // 🔥 CHỖ QUAN TRỌNG NHẤT
                if ($this->has($depClass)) {
                    $deps[] = $this->get($depClass);
                } else {
                    $deps[] = $this->make($depClass);
                }
            } else {
                throw new RuntimeException("Unsupported param \${$param->getName()}");
            }
        }

        return $ref->newInstanceArgs($deps);
    }
}
