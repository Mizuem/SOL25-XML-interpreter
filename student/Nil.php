<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
/**
 * Class representing nil (singleton instance)
 */
class Nil extends BaseObject
{
    private static ?self $instance = null;
    
    private function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Returns the singleton instance of the Nil class
     * @return Nil
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Nil();
        }
        return self::$instance;
    }
    
    public function send($selector, $args = [])
    {
        switch ($selector) {
            case 'asString':
                $strObj = StringType::fromString('nil');
                if ($this->hasRuntime()) {
                    $strObj->setRuntime($this->runtime);
                }
                return $strObj;
            
            case 'isNil':
                return TrueType::getInstance();
                
            case 'new':
            case 'from:':
                return self::getInstance();
            case 'greaterThan:':
                return FalseType::getInstance();
                
            default:
                return parent::send($selector, $args);
        }
    }
}