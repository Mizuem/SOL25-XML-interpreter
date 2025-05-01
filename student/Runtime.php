<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;

class Runtime implements RuntimeInterface
{
    /** 
     * @var array<int, array<string, mixed>> Stack of local contexts for code blocks 
     */
    private $stack;           
    /** 
     * @var array{nil: \IPP\Student\Nil, true: \IPP\Student\TrueType, false: \IPP\Student\FalseType, classes: array<string, array<string, mixed>>} Storage for global objects (classes, nil, true, false) 
     */
    private $globalStack;     
    /** 
     * @var array<int, array<string, mixed>> Method call stack 
     */
    private $callStack;       
    /** 
     * @var array<string, mixed>|null Program AST 
     */
    private $program;         
    /** 
     * @var resource|\IPP\Core\Interface\InputReader|null 
     */
    private $input;
    /** 
     * @var resource|\IPP\Core\Interface\OutputWriter|null 
     */
    private $stdout;
    /** 
     * @var resource|\IPP\Core\Interface\OutputWriter|null 
     */
    private $stderr;
    /** 
     * @var \IPP\Student\ExecutorInterface|null Reference to the Executor instance 
     */
    private $executor;        

    /**
     * Runtime Constructor
     */
    public function __construct()
    {
        $this->stack = [];
        // Initialize with required structure instead of empty array
        $this->globalStack = [
            'nil' => Nil::getInstance(),
            'true' => TrueType::getInstance(),
            'false' => FalseType::getInstance(),
            'classes' => []
        ];
        $this->callStack = [];
        $this->program = null;
        
        // Classes initialization moved here
        $this->globalStack['classes'] = [
            'Integer' => ['parent' => 'Object', 'methods' => []],
            'String' => ['parent' => 'Object', 'methods' => []],
            'Block' => ['parent' => 'Object', 'methods' => []],
            'Nil' => ['parent' => 'Object', 'methods' => []],
            'True' => ['parent' => 'Object', 'methods' => []],
            'False' => ['parent' => 'Object', 'methods' => []],
            'Object' => ['parent' => null, 'methods' => []]
        ];
    }

    /**
     * Sets the Executor instance
     * @param \IPP\Student\ExecutorInterface $executor Executor instance
     * @return void
     */
    public function setExecutor(ExecutorInterface $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Returns the Executor instance
     * @return \IPP\Student\ExecutorInterface|null Executor instance
     */
    public function getExecutor()
    {
        return $this->executor;
    }

    /**
     * Sets input and output streams
     * @param resource|\IPP\Core\Interface\InputReader|null $input Input stream or reader
     * @param resource|\IPP\Core\Interface\OutputWriter|null $stdout Output stream or writer
     * @param resource|\IPP\Core\Interface\OutputWriter|null $stderr Error stream or writer
     * @return void
     */
    public function setIO($input, $stdout, $stderr) {
        $this->input = $input;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }
    
    /**
     * Returns the input stream
     * @return resource|\IPP\Core\Interface\InputReader|null
     */
    public function getInput() {
        return $this->input;
    }
    
    /**
     * Returns the output stream
     * @return resource|\IPP\Core\Interface\OutputWriter|null
     */
    public function getStdout() {
        return $this->stdout;
    }
    
    /**
     * Returns the error stream
     * @return resource|\IPP\Core\Interface\OutputWriter|null
     */
    public function getStderr() {
        return $this->stderr;
    }

    /**
     * Sets the program AST
     * @param array<string, mixed> $program Program AST
     * @return void
     */
    public function setProgram($program)
    {
        $this->program = $program;
    }

    /**
     * Returns the program AST
     * @return array<string, mixed>|null Program AST
     */
    public function getProgram()
    {
        return $this->program;
    }

    /**
     * Adds a new execution context to the stack
     * @param array<string, mixed> $context New execution context
     * @return void
     */
    public function pushContext($context)
    {
        array_push($this->stack, $context);
    }

    /**
     * Removes the top execution context from the stack
     * @return array<string, mixed> Removed context
     */
    public function popContext()
    {
        if (empty($this->stack)) {
            throw new \Exception("Stack is empty, cannot pop context");
        }
        return array_pop($this->stack);
    }

    /**
     * Returns the current execution context
     * @return array<string, mixed> Current context
     */
    public function getCurrentContext()
    {
        if (empty($this->stack)) {
            throw new \Exception("Stack is empty, no current context");
        }
        return end($this->stack);
    }

    /**
     * Returns the nil singleton object
     * @return \IPP\Student\Nil
     * @psalm-return \IPP\Student\Nil
     */
    public function getNil()
    {
        return $this->globalStack['nil'];
    }
    /**
     * Adds a new method call to the call stack
     * @param array<string, mixed> $call Call information
     * @return void
     */
    public function pushCall($call)
    {
        array_push($this->callStack, $call);
    }

    /**
     * Removes the top method call from the call stack
     * @return array<string, mixed> Information about the removed call
     */
    public function popCall()
    {
        if (empty($this->callStack)) {
            throw new \Exception("Call stack is empty, cannot pop call");
        }
        return array_pop($this->callStack);
    }

    /**
     * Returns the current method call
     * @return array<string, mixed> Information about the current call
     */
    public function getCurrentCall()
    {
        if (empty($this->callStack)) {
            throw new \Exception("Call stack is empty, no current call");
        }
        return end($this->callStack);
    }

    /**
     * Adds a class to the global storage
     * @param string $className Class name
     * @param array<string, mixed> $classDefinition Class definition
     * @return void
     */
    public function addClass($className, $classDefinition)
    {
        $this->globalStack['classes'][$className] = $classDefinition;
    }

    /**
     * Returns the class definition
     * @param string $className Class name
     * @return array<string, mixed>|null Class definition or null if the class is not found
     * @psalm-return array<string, mixed>|null
     */
    public function getClass($className)
    {
        /** @var array<string, array<string, mixed>> */
        $classes = $this->globalStack['classes'];
        return $classes[$className] ?? null;
    }

    /**
     * Checks if a class exists
     * @param string $className Class name
     * @return bool True if the class exists
     */
    public function hasClass($className)
    {
        return isset($this->globalStack['classes'][$className]);
    }

    /**
     * Gets the value of a variable in the current context or in the global storage
     * @param string $name Variable name
     * @return \IPP\Student\BaseObject|null Variable value or null if the variable is not found
     */
    public function getVariable($name)
    {
        // Check global variables (nil, true, false)
        if (isset($this->globalStack[$name])) {
            /** @var \IPP\Student\BaseObject */
            return $this->globalStack[$name];
        }
        
        // Search in current and outer contexts
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (isset($this->stack[$i]['variables']) && 
                is_array($this->stack[$i]['variables']) && 
                isset($this->stack[$i]['variables'][$name])) {
                /** @var \IPP\Student\BaseObject */
                return $this->stack[$i]['variables'][$name];
            }
        }
        
        return null;
    }

    /**
     * Sets the value of a variable in the current context
     * @param string $name Variable name
     * @param \IPP\Student\BaseObject $value Variable value
     * @return void
     */
    public function setVariable($name, $value)
    {
        if (empty($this->stack)) {
            throw new \Exception("Stack is empty, cannot set variable");
        }
        
        $index = count($this->stack) - 1;
        if (!isset($this->stack[$index]['variables'])) {
            $this->stack[$index]['variables'] = [];
        }
         /** @var array<string, \IPP\Student\BaseObject> $variables */
        $variables = &$this->stack[$index]['variables'];
        $variables[$name] = $value;
    }

    /**
     * Checks if a variable is set in the current context
     * @param string $name Variable name
     * @return bool True if the variable is set
     */
    public function hasVariable($name)
    {
        // Check global variables
        if (isset($this->globalStack[$name])) {
            return true;
        }
        
        // Search in current and outer contexts
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (isset($this->stack[$i]['variables']) && 
                is_array($this->stack[$i]['variables']) && 
                isset($this->stack[$i]['variables'][$name])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Returns all class definitions
     * @return array<string, array<string, mixed>> All class definitions
     */
    public function getClasses()
    {
        return $this->globalStack['classes'];
    }
}