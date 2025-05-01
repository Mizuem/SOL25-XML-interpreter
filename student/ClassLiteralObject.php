<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;

/**
 * Represents a class literal in the language runtime
 */
class ClassLiteralObject extends BaseObject
{
    /** @var string */
    private $className;
    
    /**
     * Constructor for ClassLiteralObject
     * 
     * @param string $className The name of the class this object represents
     */
    public function __construct($className)
    {
        parent::__construct();
        $this->className = $className;
    }
    
    /**
     * Handles method invocations on class literals
     *
     * @param string $selector The name of the method to invoke
     * @param array<int, BaseObject> $args Arguments to pass to the method
     * @return BaseObject The result of the method invocation
     * @throws \Exception When the method is not supported or arguments are invalid
     */
    public function send($selector, $args = [])
    {
        // Handle the static 'read' method for String class
        if ($selector === 'read' && $this->className === 'String') {
            if ($this->hasRuntime()) {
                /** @var Runtime $runtime */
                $runtime = $this->runtime;
                $input = $runtime->getInput();
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
                    $result = new StringType($inputString);
                    $result->setRuntime($this->runtime);
                    return $result;
                }
            }
            $result = new StringType("");
            if ($this->hasRuntime()) {
                $result->setRuntime($this->runtime);
            }
            return $result;
        }
        
        // Handle other static methods (new, from:)
        if ($selector === 'new') {
            // Variable will be initialized in the switch block
            $instance = null;
            
            switch ($this->className) {
                case 'String':
                    $instance = new StringType();
                    break;
                case 'Integer':
                    $instance = new IntegerType();
                    break;
                case 'Nil':
                    $instance = Nil::getInstance(); // Return singleton instance
                    break;
                case 'True':
                    $instance = TrueType::getInstance(); // Return singleton instance
                    break;
                case 'False':
                    $instance = FalseType::getInstance(); // Return singleton instance
                    break;
                default:
                    // For user-defined classes
                    if ($this->hasRuntime()) {
                        /** @var Runtime $runtime */
                        $runtime = $this->runtime;
                        if ($runtime->hasClass($this->className)) {
                            $classDef = $runtime->getClass($this->className);
                            $executor = $runtime->getExecutor();
                            
                            // Ensure classDef and executor are not null
                            if ($classDef === null) {
                                throw new \Exception("Class definition for {$this->className} is null", 51);
                            }
                            
                            if ($executor === null) {
                                throw new \Exception("Executor is null", 51);
                            }
                            
                            $instance = new UserObject($this->className, $classDef, $executor);
                        } else {
                            throw new \Exception("Cannot create instance of {$this->className}", 51);
                        }
                    } else {
                        throw new \Exception("Cannot create instance of {$this->className}", 51);
                    }
            }
            
            if ($this->hasRuntime()) {
                $instance->setRuntime($this->runtime);
            }
            return $instance;
        } else if ($selector === 'from:') {
            if (count($args) != 1) {
                throw new \Exception("Method from: expects 1 argument", 53);
            }
            
            $arg = $args[0];
            
            // Process built-in classes
            switch ($this->className) {
                case 'String':
                    if ($arg instanceof StringType) {
                        /** @var StringType $instance */
                        $instance = new StringType($arg->getValue());
                    } else {
                        throw new \Exception("Invalid argument type for String from:", 53);
                    }
                    break;
                case 'Integer':
                    if ($arg instanceof IntegerType) {
                        /** @var IntegerType $instance */
                        $instance = new IntegerType($arg->getValue());
                    } else {
                        throw new \Exception("Invalid argument type for Integer from:", 53);
                    }
                    break;
                case 'Nil':
                    /** @var Nil $instance */
                    $instance = Nil::getInstance();
                    break;
                case 'True':
                    /** @var TrueType $instance */
                    $instance = TrueType::getInstance();
                    break;
                case 'False':
                    /** @var FalseType $instance */
                    $instance = FalseType::getInstance();
                    break;
                default:
                    // For user-defined classes
                    if ($this->hasRuntime()) {
                        /** @var Runtime $runtime */
                        $runtime = $this->runtime;
                        if ($runtime->hasClass($this->className)) {
                            /** @var array{parent: string} $classDef */
                            $classDef = $runtime->getClass($this->className);
                            $executor = $runtime->getExecutor();
                            
                            // Ensure executor is not null
                            if ($executor === null) {
                                throw new \Exception("Executor is null", 51);
                            }
                            
                            // Check type compatibility
                            $parentClass = $classDef['parent'];
                            
                            // For MyInt class which inherits from Integer
                            if ($parentClass === 'Integer' && $arg instanceof IntegerType) {
                                /** @var UserObject $instance */
                                $instance = new UserObject($this->className, $classDef, $executor);
                                
                                // Copy the value from the Integer argument to maintain the internal value
                                $instance->setValue($arg->getValue());
                            } else if ($parentClass === 'String' && $arg instanceof StringType) {
                                /** @var UserObject $instance */
                                $instance = new UserObject($this->className, $classDef, $executor);
                                $instance->setValue($arg->getValue()); 
                            } else {
                                throw new \Exception("Invalid argument type for {$this->className} from:", 53);
                            }
                        } else {
                            throw new \Exception("Class {$this->className} not found", 32);
                        }
                    }
                    
                    if (!isset($instance)) {
                        throw new \Exception("Could not create instance of {$this->className}", 51);
                    }
                }
            
            if ($this->hasRuntime()) {
                $instance->setRuntime($this->runtime);
            }
            
            return $instance;
        }
        
        throw new \Exception("Method $selector not implemented for class {$this->className}", 51);
    }
}