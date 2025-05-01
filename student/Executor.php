<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;

class Executor implements ExecutorInterface
{
    /** @var Runtime */
    private $runtime;

    /**
     * Executor constructor
     * @param Runtime $runtime Runtime object for state storage
     */
    public function __construct(Runtime $runtime)
    {
        $this->runtime = $runtime;
        $this->runtime->setExecutor($this);
    }

    /**
     * Run program
     * @param array{classes: array<string, array{methods?: array<string, mixed>}>} $program Program AST
     * @return BaseObject Program execution result
     * @throws \Exception On execution errors
     */
    public function executeProgram($program)
    {
        $this->runtime->setProgram($program);
        
        // Load all classes from the program
        foreach ($program['classes'] as $className => $classDefinition) {
            /** @var string $className */
            /** @var array<string, mixed> $classDefinition */
            $this->runtime->addClass($className, $classDefinition);
        }
        
        // Check for Main class
        if (!$this->runtime->hasClass('Main')) {
            throw new \Exception("Class Main not found", 31);
        }
        
        // Create Main class instance
        /** @var array{methods: array<string, array{block: array<string, mixed>}>} $mainDefinition */
        $mainDefinition = $this->runtime->getClass('Main');
        $mainObject = $this->createInstance('Main', $mainDefinition);
        
        // Check for run method
        if (!isset($mainDefinition['methods']['run'])) {
            throw new \Exception("Method run not found in class Main", 31);
        }
        
        // Create new context for run method execution
        $this->runtime->pushContext([
            'variables' => [
                'self' => $mainObject
            ]
        ]);
        
        // Execute run method
        /** @var array{block: array<string, mixed>} $runMethod */
        $runMethod = $mainDefinition['methods']['run'];
        $result = $this->executeMethod($runMethod, []);
        
        // Remove context after execution
        $this->runtime->popContext();
        
        return $result;
    }

    /**
     * Creates class instance
     * @param string $className Class name
     * @param array<string, mixed> $classDefinition Class definition
     * @return BaseObject Created instance
     */
    private function createInstance($className, $classDefinition)
    {
        switch ($className) {
            case 'Integer':
                $obj = new IntegerType();
                $obj->setRuntime($this->runtime);
                return $obj;
            case 'String':
                $obj = new StringType();
                $obj->setRuntime($this->runtime);
                return $obj;
            case 'Block':
                $obj = new BlockType();
                $obj->setRuntime($this->runtime);
                return $obj;
            case 'Nil':
                $obj = Nil::getInstance();
                $obj->setRuntime($this->runtime);
                return $obj;
            case 'True':
                $obj = TrueType::getInstance();
                $obj->setRuntime($this->runtime);
                return $obj;
            case 'False':
                $obj = FalseType::getInstance();
                $obj->setRuntime($this->runtime);
                return $obj;
            default:
                // For custom classes create UserObject
                $obj = new UserObject($className, $classDefinition, $this);
                $obj->setRuntime($this->runtime);
                return $obj;
        }
    }

    /**
     * Execute class
     * @param array{name: string} $class Class definition
     * @return BaseObject Class execution result
     */
    public function executeClass($class)
    {
        // In basic implementation just return class object
        return $this->createInstance($class['name'], $class);
    }

    /**
     * Execute method
     * @param array{block: array<string, mixed>} $method Method definition
     * @param array<int, BaseObject> $args Method arguments
     * @return BaseObject Method execution result
     * @throws \Exception When argument is missing
     */
    public function executeMethod($method, $args)
    {
        // Get the block method
        $block = $method['block'];
        
        // Create a new context for executing the block
        $context = ['variables' => []];
        
        // Add parameters to the context
        if (isset($block['parameters'])) {
            /** @var array<int, string> $parameters */
            $parameters = $block['parameters'];
            foreach ($parameters as $order => $paramName) {
                /** @var int $order */
                /** @var string $paramName */
                if (isset($args[$order - 1])) {
                    $context['variables'][$paramName] = $args[$order - 1];
                } else {
                    throw new \Exception("Missing argument for parameter $paramName", 51);
                }
            }
        }
        
        // Add the context to the stack
        $this->runtime->pushContext($context);
        
        // Execute each command in the block
        $lastResult = null;
        if (isset($block['commands'])) {
            /** @var array<int, array{type: string, var: array{name: string}, expr: array<string, mixed>}> $commands */
            $commands = $block['commands'];
            foreach ($commands as $command) {
                $lastResult = $this->executeAssignment($command);
            }
        }
        
        // Get the name of the last variable assigned
        $lastVar = $this->getLastAssignedVariable($block);
        
        // Prioritize the runtime variable value if available
        $result = null;
        if ($lastVar !== null) {
            $result = $this->runtime->getVariable($lastVar);
        }
        
        // If variable retrieval failed, use the last result directly
        if ($result === null && $lastResult !== null) {
            $result = $lastResult;
        } else if ($result === null) {
            $result = $this->runtime->getNil();
        }
        
        // Pop the context
        $this->runtime->popContext();
        
        return $result;
    }

    /**
     * Gets name of the last assigned variable in block
     * @param array<string, mixed> $block Block definition
     * @return string|null Variable name or null if no commands
     */
    private function getLastAssignedVariable($block)
    {
        if (!isset($block['commands']) || empty($block['commands'])) {
            return null;
        }
        
        // Find the last command in the block
        /* @var mixed $commands */
        $commands = $block['commands'];
        // @phpstan-ignore-next-line
        ksort($commands);
        $lastCommand = end($commands);
        
        /** @var array{type?: string, var?: array{name?: string}} $lastCommand */
        if (isset($lastCommand['type']) && $lastCommand['type'] === 'assign' && 
            isset($lastCommand['var']) && isset($lastCommand['var']['name'])) {
            /** @var string $varName */
            $varName = $lastCommand['var']['name'];
            return $varName;
        }
        
        return null;
    }

    /**
     * Execute code block
     * @param array{arity?: float|int|string, parameters?: array<int, string>, commands?: array<int, array<string, mixed>>} $block Block definition
     * @param array<int, BaseObject> $args Arguments
     * @param array<string, mixed>|null $parentContext Parent context
     * @return BaseObject Block execution result
     */
    public function executeBlock($block, $args = [], $parentContext = null)
    {
        // Set up execution context
        $context = ['variables' => []];
        
        // Add variables from parent context (lexical scope)
        if ($parentContext !== null && array_key_exists('variables', $parentContext)) {
            /** @var array<string, mixed> $parentVars */
            $parentVars = $parentContext['variables'];
            $context['variables'] = array_merge($context['variables'], $parentVars);
        }
        
        // Add parameters
        if (isset($block['parameters']) && !empty($args)) {
            /** @var array<int, string> $parameters */
            $parameters = $block['parameters'];
            foreach ($parameters as $order => $paramName) {
                /** @var int $order */
                /** @var string $paramName */
                if (isset($args[$order - 1])) {
                    $context['variables'][$paramName] = $args[$order - 1];
                }
            }
        }
        
        // Add context to stack
        $this->runtime->pushContext($context);
        
        // Execute commands
        $result = $this->runtime->getNil();
        if (isset($block['commands'])) {
            /** @var array<int, array{type: string, var: array{name: string}, expr: array<string, mixed>}> $commands */
            $commands = $block['commands'];
            foreach ($commands as $command) {
                $this->executeAssignment($command);
            }
            
            // Get the last assigned variable from the block
            $lastVar = $this->getLastAssignedVariable($block);
            if ($lastVar !== null) {
                $varResult = $this->runtime->getVariable($lastVar);
                if ($varResult !== null) {
                    $result = $varResult;
                }
            }
        }
        
        // Remove context
        $this->runtime->popContext();
        
        return $result;
    }

    /**
     * Execute expression
     * @param array<string, mixed> $expr AST expression
     * @return BaseObject Expression execution result
     * @throws \Exception When expression type is unknown
     */
    public function executeExpression($expr)
    {
        /** @var string $exprType */
        $exprType = $expr['type'];
        
        switch ($exprType) {
            case 'literal':
                /** @var array{class: string, value?: float|int|string} $expr */
                return $this->evaluateLiteral($expr);
            case 'var':
                /** @var array{name: string} $expr */
                return $this->evaluateVar($expr);
            case 'send':
                /** @var array{selector: string, receiver: array<string, mixed>, arguments?: array<int, array<string, mixed>>} $expr */
                return $this->executeSend($expr);
            case 'block':
                // Create and initialize block object
                /** @var array{block: array{arity?: float|int|string, parameters?: array<int, string>, commands?: array<int, array<string, mixed>>}} $expr */
                $blockDef = $expr['block'];
                $blockObj = new BlockType(
                    $blockDef,  // Block definition from AST
                    $this->runtime->getCurrentContext(),  // Current execution context
                    $this  // Current Executor instance
                );
                $blockObj->setRuntime($this->runtime);
                return $blockObj;
            default:
                throw new \Exception("Unknown expression type: {$exprType}", 52);
        }
    }

    /**
     * Execute message send
     * @param array{selector: string, receiver: array<string, mixed>, arguments?: array<int, array<string, mixed>>} $sendNode AST node with message send
     * @return BaseObject Message send result
     */
    public function executeSend($sendNode)
    {
        $selector = $sendNode['selector'];
        
        // Get message receiver
        /** @var array<string, mixed> $receiverExpr */
        $receiverExpr = $sendNode['receiver'];
        $receiver = $this->executeExpression($receiverExpr);
        
        // Evaluate arguments - COMBINED PROCESSING
        $args = [];
        if (isset($sendNode['arguments'])) {
            /** @var array<int, array<string, mixed>> $arguments */
            $arguments = $sendNode['arguments'];
            ksort($arguments);
            foreach ($arguments as $arg) {
                // Special processing for ifTrue:ifFalse:
                /** @var array{type?: string, block?: array{arity?: float|int|string, parameters?: array<int, string>, commands?: array<int, array<string, mixed>>}} $arg */
                if ($sendNode['selector'] === 'ifTrue:ifFalse:' && isset($arg['type']) && $arg['type'] === 'block') {
                    // Explicitly create block from definition
                    /** @var array{type: string, block: array{arity?: float|int|string, parameters?: array<int, string>, commands?: array<int, array<string, mixed>>}} $arg */
                    $blockDef = $arg['block'];
                    $block = new BlockType($blockDef, $this->runtime->getCurrentContext(), $this);
                    $block->setRuntime($this->runtime);
                    $args[] = $block;
                } else {
                    // Standard argument processing
                    /** @var array<string, mixed> $arg */
                    $args[] = $this->executeExpression($arg);
                }
            }
        }
        
        // Check if receiver is self
        $isSelfReceiver = false;
        if (array_key_exists('type', $receiverExpr)) {
            /** @var string $type */
            $type = $receiverExpr['type'];
            if ($type === 'var' && isset($receiverExpr['name'])) {
                /** @var string $name */
                $name = $receiverExpr['name'];
                $isSelfReceiver = $name === 'self';
            }
        }
        
        // Send message to receiver
        $result = $receiver->send($selector, $args);
        
        return $result;
    }

    /**
     * Execute assignment
     * @param array{var: array{name: string}, expr: array<string, mixed>} $assignmentNode AST node with assignment
     * @return BaseObject Assignment execution result
     * @throws \Exception When trying to assign to block parameter
     */
    public function executeAssignment($assignmentNode)
    {
        // Get variable name
        /** @var string $varName */
        $varName = $assignmentNode['var']['name'];
        
        // Check if variable is a block parameter
        if ($this->isBlockParameter($varName)) {
            throw new \Exception("Cannot assign to block parameter '$varName'", 34);
        }
        
        // Evaluate expression
        /** @var array<string, mixed> $expr */
        $expr = $assignmentNode['expr'];
        $value = $this->executeExpression($expr);
        
        // Check if value has runtime and set it if needed
        /** @phpstan-ignore-next-line */
        if (!$value->hasRuntime() && $this->runtime) {
            $value->setRuntime($this->runtime);
        }
        
        // Save value in current context
        $this->runtime->setVariable($varName, $value);
        
        // Always return expression value, regardless of variable name
        return $value;
    }

    /**
     * Check if variable is a block parameter
     * @param string $varName Variable name
     * @return bool True if variable is a block parameter
     */
    private function isBlockParameter($varName)
    {
        $context = $this->runtime->getCurrentContext();
        return isset($context['parameters']) && is_array($context['parameters']) && in_array($varName, $context['parameters']);
    }

    /**
     * Evaluate literal
     * @param array{class: string, value?: string|int|float} $literalNode AST node with literal
     * @return BaseObject Literal evaluation result
     * @throws \Exception When literal class is unknown
     */
    public function evaluateLiteral($literalNode)
    {
        /** @var string $literalClass */
        $literalClass = $literalNode['class'];
        
        switch ($literalClass) {
            case 'Integer':
                $intValue = isset($literalNode['value']) ? (int)$literalNode['value'] : 0;
                $intObj = new IntegerType($intValue);
                $intObj->setRuntime($this->runtime);
                return $intObj;
                
            case 'String':
                $strValue = isset($literalNode['value']) ? (string)$literalNode['value'] : '';
                $strObj = new StringType($strValue);
                $strObj->setRuntime($this->runtime);
                return $strObj;
                
            case 'True':
                return TrueType::getInstance();
                
            case 'False':
                return FalseType::getInstance();
                
            case 'Nil':
                return Nil::getInstance();
                
            case 'class':
                // Process class literal
                /** @var string $className */
                $className = $literalNode['value'] ?? '';
                if ($className !== '' && !$this->runtime->hasClass($className)) {
                    throw new \Exception("Class $className not found", 32);
                }
                // Create object to represent class
                $classObj = new ClassLiteralObject($className);
                $classObj->setRuntime($this->runtime);
                return $classObj;
                
            default:
                throw new \Exception("Unknown literal class: {$literalClass}", 52);
        }
    }

    /**
     * Evaluate variable
     * @param array{name: string} $varNode AST node with variable
     * @return BaseObject Variable evaluation result
     * @throws \Exception When variable is not found
     */
    public function evaluateVar($varNode)
    {
        /** @var string $name */
        $name = $varNode['name'];
        
        // Process special variables
        if ($name === 'nil') {
            return Nil::getInstance();
        } else if ($name === 'true') {
            return TrueType::getInstance();
        } else if ($name === 'false') {
            return FalseType::getInstance();
        } else if ($name === 'self') {
            $self = $this->runtime->getVariable('self');
            if ($self === null) {
                throw new \Exception("Variable 'self' not found in current context", 32);
            }
            return $self;
        } else if ($name === 'super') {
            // Super processing requires class inheritance information
            $self = $this->runtime->getVariable('self');
            if ($self === null) {
                throw new \Exception("Variable 'self' not found for 'super'", 32);
            }
            
            // Mark object as super-receiver
            if ($self instanceof UserObject) {
                $self->markAsSuperReceiver(true);
            }
            
            return $self;
        }
        
        // Look for variable in current context
        $value = $this->runtime->getVariable($name);
        if ($value === null) {
            // Changed error code to 52 (Runtime Error) for unknown variables
            throw new \Exception("Variable '$name' not found", 52);
        }
        
        return $value;
    }

    /**
     * Create new execution context with given variables
     * @param array<string, BaseObject> $variables Variables for new context
     * @return void
     */
    public function pushExecutionContext($variables)
    {
        $this->runtime->pushContext([
            'variables' => $variables
        ]);
    }

    /**
     * Remove top context from stack
     * @return void
     */
    public function popExecutionContext()
    {
        $this->runtime->popContext();
    }
}