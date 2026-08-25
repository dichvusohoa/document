<?php
namespace Core\User;
abstract class BaseUserInfoExtProvider
{
    abstract public function userInfoExt(): array;
}