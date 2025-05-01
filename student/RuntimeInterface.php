<?php

namespace IPP\Student;

interface RuntimeInterface
{
    /**
     * Set program
     * @param array<string, mixed> $program Program AST
     * @return void
     */
    public function setProgram($program);
    
    /**
     * Get nil object
     * @return BaseObject Nil object
     */
    public function getNil();
    
    /**
     * Set variable in current context
     * @param string $name Variable name
     * @param BaseObject $value Variable value
     * @return void
     */
    public function setVariable($name, $value);
    
    /**
     * Get variable from current context
     * @param string $name Variable name
     * @return BaseObject|null Variable value or null if not found
     */
    public function getVariable($name);
    
    /**
     * Set executor
     * @param ExecutorInterface $executor Executor instance
     * @return void
     */
    public function setExecutor(ExecutorInterface $executor);
    
    /**
     * Get current context
     * @return array<string, mixed> Current context
     */
    public function getCurrentContext();
    
    /**
     * Push new context to stack
     * @param array<string, mixed> $context Context to push
     * @return void
     */
    public function pushContext($context);
    
    /**
     * Pop context from stack
     * @return void
     */
    public function popContext();
    
    /**
     * Add class to runtime
     * @param string $className Class name
     * @param array<string, mixed> $classDefinition Class definition
     * @return void
     */
    public function addClass($className, $classDefinition);
    
    /**
     * Check if class exists
     * @param string $className Class name
     * @return bool True if class exists
     */
    public function hasClass($className);
    
    /**
     * Get class definition
     * @param string $className Class name
     * @return array<string, mixed>|null Class definition or null if not found
     */
    public function getClass($className);
} 