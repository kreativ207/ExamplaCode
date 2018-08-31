<?php

class PrintingFactory
{
    public static function build($printingType, $customer)
    {
        $class = "PrintContract" . ucfirst($printingType);

        if(file_exists($class.".php")) {
            include_once $class.".php";
            if(class_exists($class)) {
                return new $class($customer);
            }
        } else {
            throw new \Exception("Printing Class Not found");
        }
    }
}