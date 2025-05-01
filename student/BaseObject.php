<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
/**
 * Base class for all SOL25 objects
 */
class BaseObject
{
    /** @var int */
    private $id;
    /** @var int */
    private static $globalCnt = 0;
    /** @var array<string, BaseObject> Instance attributes */
    protected $attributes = [];
    /** @var mixed Runtime reference */
    protected $runtime;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = self::$globalCnt++;
    }

    /**
     * Checks if this object is identical to another object
     * @param BaseObject $obj Object to compare with
     * @return bool True if objects are identical
     */
    public function identicalTo($obj): bool
    {
        return $this->id == $obj->getId();
    }
    
    /**
     * Sets the runtime
     * @param mixed $runtime The runtime instance
     * @return void
     */
    public function setRuntime($runtime)
    {
        $this->runtime = $runtime;
    }
    
    /**
     * Checks if this object is equal to another object
     * @param BaseObject $obj Object to compare with
     * @return bool True if objects are equal
     */
    public function equalTo($obj): bool
    {
        return $this->identicalTo($obj);
    }

    /**
     * Gets object ID
     * @return int Object ID
     */
    public function getId(): int
    {
        return $this->id;
    }
    
    /**
     * Checks if runtime object is set
     * @return bool True if runtime is set
     */
    public function hasRuntime(): bool
    {
        return isset($this->runtime) && $this->runtime !== null;
    }
    
    /**
     * Handles message sending to the object
     * @param string $selector Message selector
     * @param array<int, BaseObject> $args Message arguments
     * @return BaseObject Result of the message execution
     * @throws \Exception When method is not implemented or receives invalid arguments
     */
    public function send($selector, $args = [])
    {
        switch ($selector) {
            case 'identicalTo:':
                if (count($args) != 1) {
                    throw new \Exception("Method identicalTo: expects 1 argument", 53);
                }
                return $this->identicalTo($args[0]) ? TrueType::getInstance() : FalseType::getInstance();
            
            case 'equalTo:':
                if (count($args) != 1) {
                    throw new \Exception("Method equalTo: expects 1 argument", 53);
                }
                return $this->equalTo($args[0]) ? TrueType::getInstance() : FalseType::getInstance();
            
            case 'asString':
                return StringType::fromString('');
            
            case 'isNumber':
                return FalseType::getInstance();
            
            case 'isString':
                return FalseType::getInstance();
            
            case 'isBlock':
                return FalseType::getInstance();
            
            case 'isNil':
                return FalseType::getInstance();
                
            case 'ifTrue:ifFalse:':
                // When called on a non-boolean object, behave like false
                if (count($args) < 2) {
                    throw new \Exception("Method ifTrue:ifFalse: expects 2 arguments", 53);
                }
                
                if ($args[1] instanceof BlockType) {
                    return $args[1]->execute([]);
                }
                return $args[1];
        }
        
        // Check if this is an attribute
        if (substr($selector, -1) === ':' && count($args) === 1) {
            // Setting an attribute (attribute name is selector without colon)
            $attributeName = substr($selector, 0, -1);
            $this->attributes[$attributeName] = $args[0];
            return $this; // Return self
        } elseif (isset($this->attributes[$selector])) {
            // Reading an attribute
            return $this->attributes[$selector];
        }
        
        throw new \Exception("Method $selector not implemented", 51);
    }
    
    /**
     * Sets an object attribute
     * @param string $name Attribute name
     * @param BaseObject $value Attribute value
     * @return self For method chaining
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }
    
    /**
     * Gets an object attribute
     * @param string $name Attribute name
     * @return BaseObject|null Attribute value or null if attribute is not found
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name] ?? null;
    }
    
    /**
     * Checks if object has an attribute
     * @param string $name Attribute name
     * @return bool True if the attribute exists
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }
}