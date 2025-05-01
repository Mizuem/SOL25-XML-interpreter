<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
/**
 * Class representing the logical value true (singleton instance)
 */
class TrueType extends BaseObject
{
    private static ?self $instance = null;
    
    private function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Returns the singleton instance of the TrueType class
     * @return TrueType
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new TrueType();
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
                return FalseType::getInstance();
                
            case 'and:':
                if (count($args) != 1) {
                    throw new \Exception("Method and: expects 1 argument", 53);
                }
                return $args[0]->send('value', []);
                
            case 'or:':
                return self::getInstance();
                
            case 'ifTrue:ifFalse:':
                if (count($args) < 2) {
                    throw new \Exception("Method ifTrue:ifFalse: expects 2 arguments", 53);
                }
                
                // First argument - block for ifTrue:
                // Can be BlockType or UserObject with value method
                if ($args[0] instanceof BlockType) {
                    /** @var BaseObject $result */
                    $result = $args[0]->execute([]);
                    return $result;
                } else if ($args[0] instanceof UserObject) {
                    // For UserObject we call the value method
                    /** @var BaseObject $result */
                    $result = $args[0]->send('value', []);
                    return $result;
                } else {
                    // Since $args is already typed as BaseObject[], we can return directly
                    return $args[0];
                }
                
            case 'asString':
                $strObj = StringType::fromString('true');
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