<?php

namespace IPP\Student;

interface ExecutorInterface
{
    /**
     * Execute program
     * @param array<string, mixed> $program Program AST
     * @return BaseObject Program execution result
     */
    public function executeProgram($program);
    
    /**
     * Execute method
     * @param array<string, mixed> $method Method definition
     * @param array<int, BaseObject> $args Method arguments
     * @return BaseObject Method execution result
     */
    public function executeMethod($method, $args);
    
    /**
     * Execute code block
     * @param array<string, mixed> $block Block definition
     * @param array<int, BaseObject> $args Arguments
     * @param array<string, mixed>|null $parentContext Parent context
     * @return BaseObject Block execution result
     */
    public function executeBlock($block, $args, $parentContext);
    
    /**
     * Execute expression
     * @param array<string, mixed> $expr AST expression
     * @return BaseObject Expression execution result
     */
    public function executeExpression($expr);
    
    /**
     * Execute assignment
     * @param array<string, mixed> $assignmentNode AST node with assignment
     * @return BaseObject Assignment execution result
     */
    public function executeAssignment($assignmentNode);
    
    /**
     * Create new execution context with given variables
     * @param array<string, BaseObject> $variables Variables for new context
     * @return void
     */
    public function pushExecutionContext($variables);
    
    /**
     * Remove top context from stack
     * @return void
     */
    public function popExecutionContext();
} 