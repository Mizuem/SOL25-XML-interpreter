<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;

/**
 * @property-read RuntimeInterface $runtime Runtime object with hasClass and getClass methods
 * @method bool hasRuntime() Returns whether runtime is available
 * @method void setRuntime(RuntimeInterface $runtime) Sets the runtime object
 * @property array<string, BaseObject> $attributes
 */
class UserObject extends BaseObject
{
    /**
     * @var string
     */
    private string $className;
    /**
     * @var array<string, mixed>
     */
    private array $classDef;
    /**
     * @var ExecutorInterface
     */
    private object $executor;
    /**
     * @var mixed
     */
    private mixed $value; 
    /**
     * @var bool
     */
    private bool $isSuperReceiver = false;

    /**
     * @param string $className
     * @param array<string, mixed> $classDef
     * @param ExecutorInterface $executor
     */
    public function __construct($className, $classDef, $executor)
    {
        parent::__construct();
        $this->className = $className;
        $this->classDef = $classDef;
        $this->executor = $executor;
    }
    
    /**
     * @param mixed $value
     * @return void
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
    
    // Add this method to get the internal value
    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
    
    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }
    
    /**
     * @param bool $isSuperReceiver
     * @return void
     */
    public function markAsSuperReceiver($isSuperReceiver)
    {
        $this->isSuperReceiver = $isSuperReceiver;
    }
    
    /**
     * Gets the parent class for the current class
     * @return string|null The parent class name or null if there's no parent
     * @psalm-return string|null
     */
    public function getParentClassName()
    {
        /** @var string|null */
        return $this->classDef['parent'] ?? null;
    }
    
    /**
     * Checks whether the current class inherits from the specified one
     * @param string $ancestorName Name of the presumed ancestor
     * @return bool True if the current class inherits from the specified one
     */
    public function isDescendantOf($ancestorName)
    {
        $currentClass = $this->className;
        $currentParent = $this->getParentClassName();
        
        // Check direct parent
        if ($currentParent === $ancestorName) {
            return true;
        }
        
        // Check the entire chain of parents
        while ($currentParent !== null) {
            if ($currentParent === $ancestorName) {
                return true;
            }
            
            // Get parent class definition
            /** @var RuntimeInterface $runtime */
            if ($this->hasRuntime()) {
                $runtime = $this->runtime;
                if (is_string($currentParent) && $runtime->hasClass($currentParent)) {
                    $parentDef = $runtime->getClass($currentParent);
                    $currentParent = $parentDef['parent'] ?? null;
                } else {
                    break;
                }
            } else {
                break;
            }
        }
        
        return false;
    }
    
    /**
     * Creates an instance of the parent class with current data
     * @return BaseObject|null Instance of the parent class or null if not possible
     * @phpstan-return ?BaseObject
     */
    private function createParentInstance()
    {
        $parentClassName = $this->getParentClassName();
        
        if (!$parentClassName || !$this->hasRuntime()) {
            return null;
        }
        
        // Process standard classes
        switch ($parentClassName) {
            case 'Integer':
                /** @var int $typedValue */
                $typedValue = $this->value;
                $instance = new IntegerType($typedValue);
                break;
            case 'String':
                /** @var string $stringValue */
                $stringValue = $this->getValue();
                $instance = new StringType($stringValue);
                break;
            case 'Object':
                // Object is the base class, return BaseObject
                $instance = new BaseObject();
                break;
            default:
                // For other user classes
                
                if ($this->runtime->hasClass($parentClassName)) {
                    
                    $parentDef = $this->runtime->getClass($parentClassName);
                    // Ensure parentDef is not null
                    /** @var array<string, mixed> $safeParentDef */
                    $safeParentDef = $parentDef ?? [];
                    $instance = new UserObject($parentClassName, $safeParentDef, $this->executor);
                    
                    // Copy value if it exists
                    if (isset($this->value)) {
                        $instance->setValue($this->value);
                    }
                } else {
                    return null;
                }
        }
        
        // Set runtime
        $instance->setRuntime($this->runtime);

        
        return $instance;
    }
    
    /**
     * @param string $selector
     * @param array<int, BaseObject> $args
     * @param bool $isSelfReceiver
     * @return mixed
     */
    public function send($selector, $args = [], $isSelfReceiver = false)
    {
        // If this is a call via super, delegate to parent class
        if ($this->isSuperReceiver) {
            // Reset flag after use
            $this->isSuperReceiver = false;
            
            // Create parent class instance and pass the call to it
            $parentInstance = $this->createParentInstance();
            if ($parentInstance) {
                /** @var array<int, BaseObject> $args */
                return $parentInstance->send($selector, $args);
            }
        }
        if ($selector === 'equalTo:' && count($args) === 1 && $this->isDescendantOf('Integer')) {
            // When a MyInt is comparing with an Integer
            if ($args[0] instanceof IntegerType) {
                return $this->getValue() == $args[0]->getValue() ? 
                       TrueType::getInstance() : FalseType::getInstance();
            }
        }
        // 1. First check class methods (regardless of receiver)
        
        if (isset($this->classDef['methods']) && is_array($this->classDef['methods']) && 
            isset($this->classDef['methods'][$selector])) {
            /** @var ExecutorInterface $executor */
            $executor = $this->executor;
            $executor->pushExecutionContext([
                'self' => $this
            ]);
            
            /** @var array<string, mixed> $methodDef */
            $methodDef = $this->classDef['methods'][$selector];
            $result = $executor->executeMethod($methodDef, $args);
            
            $executor->popExecutionContext();
            
            return $result;
        }
        
        // 2. Process attributes only if receiver is self
        if (isset($this->attributes[$selector])) {
            return $this->attributes[$selector];
        }
        
        // Check for attribute setter (selector ending with :)
        if (substr($selector, -1) === ':' && count($args) === 1) {
            $attributeName = substr($selector, 0, -1);
                
            /** @var BaseObject $arg */
            $arg = $args[0];
            $this->attributes[$attributeName] = $arg;
            return $this;
        }
        
        // 3. Special handling for asString
        if ($selector === 'asString') {
            // Generate string representation of the object
            $stringValue = $this->getObjectStringRepresentation();
            $strObj = StringType::fromString($stringValue);
            
            
            if ($this->hasRuntime()) {
                
                $strObj->setRuntime($this->runtime);
            }
            return $strObj;
        }
        
        // 4. Pass the call to parent class
        $parentInstance = $this->createParentInstance();
        if ($parentInstance) {
            // Convert UserObject arguments to appropriate types if necessary
            $preparedArgs = [];
            foreach ($args as $arg) {
                if ($arg instanceof UserObject && $arg->isDescendantOf('Integer') && 
                    $parentInstance instanceof IntegerType && 
                    ($selector === 'plus:' || $selector === 'minus:' || $selector === 'multiplyBy:' || $selector === 'divBy:' || $selector === 'equalTo:')) {
                    // Convert UserObject to IntegerType for arithmetic operations
                    /** @var int $intValue */
                    $intValue = $arg->getValue();
                    $intArg = new IntegerType($intValue);
                    
                    if ($this->hasRuntime()) {
                        
                        $intArg->setRuntime($this->runtime);
                    }
                    $preparedArgs[] = $intArg;
                } else if ($arg instanceof UserObject && $arg->isDescendantOf('String') && 
                    $parentInstance instanceof StringType && 
                    ($selector === 'equalTo:' || $selector === 'concatenateWith:')) {
                    // Convert UserObject to StringType for string operations and equality comparison
                    /** @var string $strValue */
                    $strValue = $arg->getValue();
                    $strArg = new StringType($strValue);
                    
                    if ($this->hasRuntime()) {
                       
                        $strArg->setRuntime($this->runtime);
                    }
                    $preparedArgs[] = $strArg;
                } else {
                    $preparedArgs[] = $arg;
                }
            }
            
            // Delegate call to parent class with prepared arguments
            
            return $parentInstance->send($selector, $preparedArgs);
        }
        
        // 5. For all other cases call the base send
        /** @var array<int, BaseObject> $args */
        return parent::send($selector, $args);
    }
    
    /**
     * Returns the string representation of the object
     * Using the toString attribute or method
     * @return string
     */
    private function getObjectStringRepresentation()
    {
        // Check if toString attribute is defined for this class
        
        if ($this->hasRuntime()) {
            /** @var RuntimeInterface $runtime */
            $runtime = $this->runtime;
            
            // Look for toString in current class
            $toStringSelector = null;
            if (isset($this->classDef['toString'])) {
                $toStringSelector = $this->classDef['toString'];
            }
            
            // If not found, look in parent classes
            if ($toStringSelector === null) {
                $currentClassName = $this->getParentClassName();
                while ($currentClassName !== null) {
                    if (is_string($currentClassName)) {
                        $currentClassDef = $runtime->getClass($currentClassName);
                        if (isset($currentClassDef['toString'])) {
                            $toStringSelector = $currentClassDef['toString'];
                            break;
                        }
                        $currentClassName = $currentClassDef['parent'] ?? null;
                    } else {
                        break;
                    }
                }
            }
            
            // If toString method is found, call it
            if ($toStringSelector !== null) {
                // Try to call the toString method
                try {
                    // Check if method exists in current class
                    /** @var ExecutorInterface $executor */
                    $executor = $this->executor;
                    
                    if (is_string($toStringSelector) && isset($this->classDef['methods']) && is_array($this->classDef['methods']) && isset($this->classDef['methods'][$toStringSelector])) {
                        $executor->pushExecutionContext(['self' => $this]);
                        /** @var array<string, mixed> $methodDef */
                        $methodDef = $this->classDef['methods'][$toStringSelector];
                        $result = $executor->executeMethod($methodDef, []);
                        $executor->popExecutionContext();
                        
                        // If method returned a string, use it
                        if ($result instanceof StringType) {
                            return $result->getValue();
                        }
                    } else {
                        // Method not in current class, try to find in inheritance chain
                        $currentClassName = $this->getParentClassName();
                        while ($currentClassName !== null) {
                            if (is_string($currentClassName)) {
                                $currentClassDef = $runtime->getClass($currentClassName);
                                if (is_string($toStringSelector) && isset($currentClassDef['methods']) && is_array($currentClassDef['methods']) && isset($currentClassDef['methods'][$toStringSelector])) {
                                    // Create an instance of this class and call the method
                                    // At this point, $currentClassDef is guaranteed to be a valid array
                                    $parentObj = new UserObject($currentClassName, $currentClassDef, $this->executor);
                                    $parentObj->setValue($this->value);;
                                    
                                    $parentObj->setRuntime($this->runtime);
                                    
                                    /** @var ExecutorInterface $parentExecutor */
                                    $parentExecutor = $parentObj->executor;
                                    $parentExecutor->pushExecutionContext(['self' => $parentObj]);
                                    
                                    /** @var array<string, mixed> $methodDef */
                                    $methodDef = $currentClassDef['methods'][$toStringSelector];
                                    $result = $parentExecutor->executeMethod($methodDef, []);
                                    
                                    $parentExecutor->popExecutionContext();
                                    
                                    if ($result instanceof StringType) {
                                        return $result->getValue();
                                    }
                                    break;
                                }
                                $currentClassName = $currentClassDef['parent'] ?? null;
                            } else {
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // In case of error when calling the method, use standard representation
                }
            }
        }
        
        // Check if we're inheriting from Integer or String and have a value
        if (isset($this->value)) {
            if ($this->isDescendantOf('Integer')) {
                /** @var int|string $castedValue */
                $castedValue = $this->value;
                return (string)$castedValue; // Return the numeric value as string
            } else if ($this->isDescendantOf('String')) {
                /** @var string $stringValue */
                $stringValue = $this->value;
                return $stringValue; // Return the string value
            }
        }
        
        // By default return class name
        return $this->className;
    }
}