<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace Core\Controller\Login;
use Core\Controller\BaseControllerFactory;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
/**
 * Description of LoginControllerFactory
 *
 * @author admin
 */
class LoginControllerFactory extends BaseControllerFactory{
    public function create(RequestAuthContext $requestAuthContext): BaseController{
        //sau này sẽ tùy theo action mà return LoginPageController hoặc LoginController
        return $this->container->get(LoginPageController::class);
    } 
}
