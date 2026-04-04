Sơ đồ cây thư mục của ví dụ dự án quản lý trường mầm non ( ví dụ 2 module thương mại nutrition và pupil)
core
    models
    controllers
    views
        login.phtml
        errors
            500.php
            503.php
application
    models (các model tự viết của Bud project)    
        -Các model không thuộc module thương mại nào
        nutrition
        pupil
        _shared
    controllers (các controller tự viết của Bud project)    
        - Các controller không thuộc module thương mại ví dụ
        - province_controller.php
        - food_provider_controller.php
        nutrition (chứa các controller của module dinh dưỡng)
            -food_nutrition_controller.php
            -food_price_controller.php
            ...
        pupil
            -school_nutrition_controller.php
            -class_nutrition_controller.php
        _shared  (chứa các controller thuộc nhiều module- chú ý cần phân biệt controller thuộc @mixed và controller không có module )
    views
        login.phtml     các view không thuộc  module nào
        errors
            500.php
            503.php
        layouts (các dàn trang cho cả trang web)   
            layout.phtml
        nutrition (các dàn trang cục bộ cho module nutrition)    
        pupil (các dàn trang cục bộ cho module pupil)  
public
    index.php
    .htacces
    lib_scripts
    lib_styles
    scripts
    styles
    images
config
    config.php
    deploy.php
logs


