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
        //tùy theo action hoặc mà return LoginPageController hoặc LoginController
        if($requestAuthContext->request()->isHtmlResponse()){
            return $this->container->get(LoginPageController::class);
        }
        else{
            return $this->container->get(LoginController::class);
        }
        
    } 
}
