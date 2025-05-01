<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
/**
 * Class representing the logical value false (singleton instance)
 */
class FalseType extends BaseObject
{
    private static ?self $instance = null;
    
    private function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Returns the singleton instance of the FalseType class
     * @return FalseType
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new FalseType();
        }
        return self::$instance;
    }
    
    /**
     * Send a message to this object
     * @param string $selector Message selector (method name)
     * @param array<int, BaseObject> $args Message arguments
     * @return BaseObject Result of the message send
     * @throws \Exception When method is not found or arguments are invalid
     */
    public function send($selector, $args = []): BaseObject
    {
        switch ($selector) {
            case 'not':
                return TrueType::getInstance();
                
            case 'and:':
                return self::getInstance();
                
            case 'or:':
                if (count($args) != 1) {
                    throw new \Exception("Method or: expects 1 argument", 53);
                }
                /** @var BaseObject $result */
                $result = $args[0]->send('value', []);
                return $result;
                
            case 'ifTrue:ifFalse:':
                if (count($args) < 2) {
                    throw new \Exception("Method ifTrue:ifFalse: expects 2 arguments", 53);
                }
                    
                // Second argument - block for ifFalse:
                // Can be BlockType or UserObject with value method
                if ($args[1] instanceof BlockType) {
                    /** @var BaseObject $result */
                    $result = $args[1]->execute([]);
                    return $result;  
                } else if ($args[1] instanceof UserObject) {
                    // For UserObject we call the value method
                    /** @var BaseObject $result */
                    $result = $args[1]->send('value', []);
                    return $result;
                }
                // Since $args is already typed as BaseObject[], we can return directly
                return $args[1];
                
            case 'asString':
                $strObj = StringType::fromString('false');
                if ($this->hasRuntime()) {
                    $strObj->setRuntime($this->runtime);
                }
                return $strObj;
                
            case 'new':
            case 'from:':
                return self::getInstance();
                
            default:
                return parent::send($selector, $args);
        }
    }
}