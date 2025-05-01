<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
/**
 * Class representing strings
 */
class StringType extends BaseObject
{
    /** @var string */
    private $value;
    /** @var \IPP\Student\Runtime|null */
    protected $runtime = null;
    
    /**
     * Constructor
     * @param string $value String value (default "")
     */
    public function __construct($value = "", ?\IPP\Student\Runtime $runtime = null)
    {
        parent::__construct();
        $this->value = (string)$value;
        $this->runtime = $runtime;
    }
    
    /**
     * Creates a StringType instance with the specified value
     * @param string $value Value
     * @param \IPP\Student\Runtime|null $runtime Runtime environment
     * @return StringType
     */
    public static function fromString($value, ?\IPP\Student\Runtime $runtime = null)
    {
        $obj = new StringType($value);
        if ($runtime !== null) {
            $obj->setRuntime($runtime);
        }
        return $obj;
    }
    
    /**
     * Gets the string value
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
    
    public function equalTo($obj): bool
    {
        if ($obj instanceof StringType) {
            return $this->value == $obj->getValue();
        } else if ($obj instanceof UserObject && $obj->isDescendantOf('String')) {
            // Add special handling for UserObject instances of String subclasses
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
            
            case 'print':
                echo $this->value;
                return $this;
            
            case 'asString':
                return $this;
            
            case 'isString':
                return TrueType::getInstance();
            
            case 'asInteger':
                if (is_numeric($this->value)) {
                    return new IntegerType((int)$this->value);
                }
                return Nil::getInstance();
            
            case 'concatenateWith:':
                if (count($args) != 1) {
                    throw new \Exception("Method concatenateWith: expects 1 argument", 53);
                }
                    
                // Get string value of the argument
                $argValue = "";
                if ($args[0] instanceof StringType) {
                    $argValue = $args[0]->getValue();
                } else if ($args[0] instanceof IntegerType) {
                    // Convert Integer to String
                    $argValue = (string)$args[0]->getValue();
                } else {
                    // Try to get string representation of other types
                    $asString = $args[0]->send('asString', []);
                    if ($asString instanceof StringType) {
                        $argValue = $asString->getValue();
                    } else {
                        throw new \Exception("Method concatenateWith: expects argument convertible to String", 53);
                    }
                }
                    
                $resultStr = new StringType($this->value . $argValue);
                if ($this->runtime) {
                    $resultStr->setRuntime($this->runtime);
                }
                return $resultStr;
            
            case 'startsWith:endsBefore:':
                if (count($args) != 2 || !($args[0] instanceof IntegerType) || !($args[1] instanceof IntegerType)) {
                    throw new \Exception("Method startsWith:endsBefore: expects 2 Integer arguments", 53);
                }
                
                $start = $args[0]->getValue();
                $end = $args[1]->getValue();
                
                if ($start <= 0 || $end <= 0) {
                    return Nil::getInstance();
                }
                
                if ($end <= $start) {
                    return new StringType("");
                }
                
                $substring = mb_substr($this->value, $start - 1, $end - $start);
                $resultStr = new StringType($substring);
                if ($this->runtime) {
                    $resultStr->setRuntime($this->runtime);
                }
                return $resultStr;
            
            case 'read':
                if ($this->runtime) {
                    $input = $this->runtime->getInput();
                    if ($input) {
                        // Handle both resource and InputReader interface
                        $inputString = "";
                        if (is_resource($input)) {
                            /** @var resource $resourceInput */
                            $resourceInput = $input;
                            $inputString = fgets($resourceInput) ?: "";
                        } else {
                            /** @var \IPP\Core\Interface\InputReader $readerInput */
                            $readerInput = $input;
                            // InputReader interface guarantees readString method
                            $inputString = $readerInput->readString() ?? "";
                        }
                        $inputString = rtrim($inputString, "\r\n");

                        return new StringType($inputString);
                    }
                }
                // Fallback if no input available
                return new StringType("");
                
            case 'from:':
                if (count($args) != 1) {
                    throw new \Exception("Method from: expects 1 argument", 53);
                }
                
                if ($args[0] instanceof StringType) {
                    return new StringType($args[0]->getValue());
                }
                
                throw new \Exception("Invalid argument type for from:", 53);
                
            case 'new':
                return new StringType("");
                
            default:
                return parent::send($selector, $args);
        }
    }
}