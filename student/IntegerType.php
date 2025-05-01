<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
/**
 * Class representing integers
 */
class IntegerType extends BaseObject
{
    private int $value;
    
    /**
     * Constructor
     * @param int $value Numeric value (default 0)
     */
    public function __construct($value = 0)
    {
        parent::__construct();
        $this->value = (int)$value;
    }
    
    /**
     * Creates an instance of IntegerType with the specified value
     * @param int $value Value
     * @return IntegerType
     */
    public static function fromInt($value)
    {
        return new IntegerType($value);
    }
    
    /**
     * Gets the integer value
     * @return int
     */
    public function getValue()
    {
        return $this->value;
    }
    
    public function equalTo($obj): bool
    {
        if ($obj instanceof IntegerType) {
            return $this->value == $obj->getValue();
        } else if ($obj instanceof UserObject && $obj->isDescendantOf('Integer')) {
            return $this->value == $obj->getValue();
        }
        return false;
    }
    
    public function send($selector, $args = [])
    {
        switch ($selector) {
            case 'equalTo:':
                if (count($args) != 1) {
                    throw new \Exception("Method equalTo: expects 1 argument", 53);
                }
                return $this->equalTo($args[0]) ? TrueType::getInstance() : FalseType::getInstance();
            
                case 'greaterThan:':
                    if (count($args) != 1 || !($args[0] instanceof IntegerType)) {
                        throw new \Exception("Method greaterThan: expects 1 Integer argument", 53);
                    }
                    return $this->value > $args[0]->getValue() ? TrueType::getInstance() : FalseType::getInstance();
            
            case 'plus:':
                if (count($args) != 1 || !($args[0] instanceof IntegerType)) {
                    throw new \Exception("Method plus: expects 1 Integer argument", 53);
                }
                return new IntegerType($this->value + $args[0]->getValue());
            
            case 'minus:':
                if (count($args) != 1 || !($args[0] instanceof IntegerType)) {
                    throw new \Exception("Method minus: expects 1 Integer argument", 53);
                }
                return new IntegerType($this->value - $args[0]->getValue());
            
            case 'multiplyBy:':
                if (count($args) != 1 || !($args[0] instanceof IntegerType)) {
                    throw new \Exception("Method multiplyBy: expects 1 Integer argument", 53);
                }
                return new IntegerType($this->value * $args[0]->getValue());
            
            case 'divBy:':
                if (count($args) != 1 || !($args[0] instanceof IntegerType)) {
                    throw new \Exception("Method divBy: expects 1 Integer argument", 53);
                }
                if ($args[0]->getValue() == 0) {
                    throw new \Exception("Division by zero", 53);
                }
                return new IntegerType(intdiv($this->value, $args[0]->getValue()));
            
            case 'asString':
                $strObj = StringType::fromString((string)$this->value);
                if ($this->runtime) {
                    $strObj->setRuntime($this->runtime);
                }
                return $strObj;
            
            case 'asInteger':
                return $this;
            
            case 'isNumber':
                return TrueType::getInstance();
            
            case 'timesRepeat:':
                if (count($args) != 1) {
                    throw new \Exception("Method timesRepeat: expects 1 block argument", 53);
                }
                
                $result = Nil::getInstance();
                if ($this->value > 0) {
                    for ($i = 1; $i <= $this->value; $i++) {
                        $result = $args[0]->send('value:', [new IntegerType($i)]);
                    }
                }
                return $result;
                
            case 'from:':
                if (count($args) != 1) {
                    throw new \Exception("Method from: expects 1 argument", 53);
                }
                
                if ($args[0] instanceof IntegerType) {
                    return new IntegerType($args[0]->getValue());
                }
                
                throw new \Exception("Invalid argument type for from:", 53);
                
            case 'new':
                return new IntegerType(0);
                
            default:
                return parent::send($selector, $args);
        }
    }
}