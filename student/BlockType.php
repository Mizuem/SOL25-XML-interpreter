<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;

/**
 * Class representing code blocks
 */
class BlockType extends BaseObject
{
    /**
     * @var array{arity?: int|string|float, parameters?: array<int, string>, commands?: array<int, array<string, mixed>>}|null
     */
    private $blockDef;
    
    /**
     * @var array<string, mixed>|null
     */
    private $context;
    
    /**
     * @var Executor|null
     */
    private $executor;
    
    /**
     * Constructor
     * @param array{arity?: int|string|float, parameters?: array<int, string>, commands?: array<int, array<string, mixed>>}|null $blockDef Block definition from AST
     * @param array<string, mixed>|null $context Block execution context
     * @param Executor|null $executor Executor instance for block execution
     */
    public function __construct($blockDef = null, $context = null, $executor = null)
    {
        parent::__construct();
        $this->blockDef = $blockDef;
        $this->context = $context;
        $this->executor = $executor;
    }
    
    /**
     * Executes the block with the given arguments
     * @param array<int, BaseObject> $args Arguments for the block
     * @return BaseObject Block execution result
     * @throws \Exception When block is undefined or argument count is incorrect
     */
    public function execute($args = [])
    {
        if (!$this->blockDef || !$this->executor) {
            throw new \Exception("Cannot execute undefined block", 52);
        }
        
        /** @var int|string|float|null $rawArity */
        $rawArity = $this->blockDef['arity'] ?? null;
        $arity = isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0;
        if (count($args) !== $arity) {
            throw new \Exception("Block expects $arity arguments, " . count($args) . " given", 51);
        }
        
        // Pass execution to the executor
        return $this->executor->executeBlock($this->blockDef, $args, $this->context);
    }
    
    /**
     * @param string $selector Method selector
     * @param array<int, BaseObject> $args Method arguments
     * @return BaseObject
     * @throws \Exception When method requirements are not met
     */
    public function send($selector, $args = [])
    {
        switch ($selector) {
            case 'value':
                /** @var int|string|float|null $rawArity */
                $rawArity = $this->blockDef['arity'] ?? null;
                if ((isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) !== 0) {
                    /** @var int|string|float|null $displayArity */
                    $displayArity = $this->blockDef['arity'] ?? null;
                    throw new \Exception("Block expects " . (isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) . " arguments", 51);
                }
                return $this->execute([]);
                
            case 'value:':
                /** @var int|string|float|null $rawArity */
                $rawArity = $this->blockDef['arity'] ?? null;
                if ((isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) !== 1) {
                    /** @var int|string|float|null $displayArity */
                    $displayArity = $this->blockDef['arity'] ?? null;
                    throw new \Exception("Block expects " . (isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) . " arguments", 51);
                }
                if (count($args) != 1) {
                    throw new \Exception("Method value: expects 1 argument", 53);
                }
                return $this->execute([$args[0]]);
                
            case 'value:value:':
                /** @var int|string|float|null $rawArity */
                $rawArity = $this->blockDef['arity'] ?? null;
                if ((isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) !== 2) {
                    /** @var int|string|float|null $displayArity */
                    $displayArity = $this->blockDef['arity'] ?? null;
                    throw new \Exception("Block expects " . (isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) . " arguments", 51);
                }
                if (count($args) != 2) {
                    throw new \Exception("Method value:value: expects 2 arguments", 53);
                }
                return $this->execute([$args[0], $args[1]]);
                
            case 'value:value:value:':
                /** @var int|string|float|null $rawArity */
                $rawArity = $this->blockDef['arity'] ?? null;
                if ((isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) !== 3) {
                    /** @var int|string|float|null $displayArity */
                    $displayArity = $this->blockDef['arity'] ?? null;
                    throw new \Exception("Block expects " . (isset($this->blockDef['arity']) ? (int)$this->blockDef['arity'] : 0) . " arguments", 51);
                }
                if (count($args) != 3) {
                    throw new \Exception("Method value:value:value: expects 3 arguments", 53);
                }
                return $this->execute([$args[0], $args[1], $args[2]]);
                
            case 'whileTrue:':
                if (count($args) != 1) {
                    throw new \Exception("Method whileTrue: expects 1 argument", 53);
                }
                
                $result = Nil::getInstance();
                
                while (true) {
                    $condition = $this->execute([]);
                    if (!($condition instanceof TrueType)) {
                        break;
                    }
                    /** @var BaseObject $blockArg */
                    $blockArg = $args[0];
                    $result = $blockArg->send('value', []);
                }
                
                return $result;
                
            case 'isBlock':
                return TrueType::getInstance();
                
            case 'from:':
            case 'new':
                throw new \Exception("Cannot instantiate Block directly", 53);
                
            default:
                /** @var array<int, BaseObject> $typedArgs */
                $typedArgs = $args;
                return parent::send($selector, $typedArgs);
        }
    }
}