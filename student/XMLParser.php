<?php

namespace IPP\Student;
use IPP\Core\Exception\XMLException;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMNode;

/**
 * @phpstan-type Program array{language: string, description?: string, classes: array<string, ClassDef>}
 * @phpstan-type ClassDef array{parent: string, methods: array<string, MethodDef>}
 * @phpstan-type MethodDef array{block?: BlockDef|null}
 * @phpstan-type BlockDef array{arity: int, parameters: array<int, string>, commands: array<int, CommandDef>}
 * @phpstan-type CommandDef array{type: string, var: array<string, mixed>|null, expr: array<string, mixed>|null}
 */
class XMLParser
{
    protected DOMDocument $dom;

    public function __construct(DOMDocument $dom)
    {
        $this->dom = $dom;
    }

    /**
     * @return Program
     */
    public function parse(): array
    {
        try
        {
            /** @var Program $program */
            $program = [
                'classes' => []
            ];

            $this->parseProgram($program);

            return $program;
        }
        catch (XMLException $e)
        {
            throw $e;
        }
    }

    /**
     * @param Program $program
     * @return void
     */
    private function parseProgram(array &$program): void
    {
        $programElement = $this->dom->documentElement;
        if (!($programElement instanceof DOMElement) || $programElement->nodeName !== 'program') {
            throw new XMLException("Invalid root element: " . ($programElement ? $programElement->nodeName : 'null'));
        }

        if (!$programElement->hasAttribute('language') ||
            $programElement->getAttribute('language') !== 'SOL25') {
            throw new XMLException("Invalid or missing language attribute");
        }

        // Add program information
        $program['language'] = $programElement->getAttribute('language');
        if ($programElement->hasAttribute('description')) {
            $program['description'] = $programElement->getAttribute('description');
        }

        // Parse classes and save them in the structure
        $this->parseClasses($programElement, $program);
    }

    /**
     * @param DOMElement $programElement
     * @param Program $program
     * @param-out Program $program
     * @return void
     */
    private function parseClasses(DOMElement $programElement, array &$program): void
    {
        $classElements = $programElement->getElementsByTagName('class');
        foreach ($classElements as $classElement) {
            if ($classElement->nodeName !== 'class' || !$classElement->hasAttribute('name') || !$classElement->hasAttribute('parent')) {
                throw new XMLException("Invalid class element: missing name or parent attribute");
            }

            $className = $classElement->getAttribute('name');
            $parentName = $classElement->getAttribute('parent');

            // Create the class structure
            $program['classes'][$className] = [
                'parent' => $parentName,
                'methods' => []
            ];

            // Parse class methods
            $this->parseMethods($classElement, $program['classes'][$className]);
        }
    }

    /**
     * @param DOMElement $classElement
     * @param array{parent: string, methods: array<string, array{}>} $class
     * @param-out array{parent: string, methods: array<string, array{block?: array{arity: int, parameters: array<int, string>, commands: array<int, array{type: string, var: array<string, mixed>|null, expr: array<string, mixed>|null}>}|null}>} $class
     * @return void
     */
    private function parseMethods(DOMElement $classElement, array &$class): void
    {
        $methodElements = $classElement->getElementsByTagName('method');
        foreach ($methodElements as $methodElement) {
            if ($methodElement->nodeName !== 'method' || !$methodElement->hasAttribute('selector')) {
                throw new XMLException("Invalid method element: missing selector attribute");
            }

            $selector = $methodElement->getAttribute('selector');

            // Create the method structure
            $class['methods'][$selector] = [];

            // Parse the method block
            $blockElements = $methodElement->getElementsByTagName('block');
            if ($blockElements->length < 1) {
                throw new XMLException("Method must contain at least one block");
            }

            $class['methods'][$selector]['block'] = $this->parseBlocks($blockElements);
        }
    }

    /**
     * @param DOMNodeList<DOMElement> $blockElements
     * @return BlockDef|null
     */
    private function parseBlocks(DOMNodeList $blockElements): ?array
    {
        $blocks = [];

        foreach ($blockElements as $blockElement) {
            if (!$blockElement->hasAttribute('arity')) {
                throw new XMLException("Block must be an element and have arity attribute");
            }

            $block = [
                'arity' => (int)$blockElement->getAttribute('arity'),
                'parameters' => [],
                'commands' => []
            ];

            // Parse parameters and commands - ONLY DIRECT child elements
            $parameterElements = [];
            foreach ($blockElement->childNodes as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->nodeName === 'parameter') {
                    $parameterElements[] = $childNode;
                }
            }

            if (count($parameterElements) !== (int)$blockElement->getAttribute('arity')) {
                throw new XMLException("Invalid block element: arity does not match number of parameters");
            }

            $block['parameters'] = $this->parseParameters($parameterElements, $blockElement);

            // Get ONLY direct child assign elements
            $assignElements = [];
            foreach ($blockElement->childNodes as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->nodeName === 'assign') {
                    $assignElements[] = $childNode;
                }
            }

            /** @var array<int, CommandDef> $commands */
            $commands = $this->parseAssigns($assignElements);
            $block['commands'] = $commands;

            $blocks[] = $block;
        }

        // Usually return the first block, as there is always one block in a method
        return !empty($blocks) ? $blocks[0] : null;
    }

    /**
     * @param array<int, DOMElement> $parameterElements
     * @param DOMElement $blockElement
     * @return array<int, string>
     */
    private function parseParameters(array $parameterElements, DOMElement $blockElement): array
    {
        $parameters = [];

        foreach ($parameterElements as $parameterElement) {
            if (!$parameterElement->hasAttribute('order') || !$parameterElement->hasAttribute('name')) {
                throw new XMLException("Invalid parameter element: missing order or name attribute");
            }

            $order = (int)$parameterElement->getAttribute('order');
            $name = $parameterElement->getAttribute('name');

            $parameters[$order] = $name;
        }

        // Sort parameters by order
        ksort($parameters);

        return $parameters;
    }

    /**
     * @param array<int, DOMElement> $assignElements
     * @return array<int, CommandDef>
     */
    private function parseAssigns(array $assignElements): array
    {
        $commands = [];

        foreach ($assignElements as $assignElement) {
            if (!$assignElement->hasAttribute('order')) {
                throw new XMLException("Invalid assign element: missing order attribute");
            }

            $order = (int)$assignElement->getAttribute('order');

            $command = [
                'type' => 'assign',
                'var' => null,
                'expr' => null
            ];

            // Parse var and expr
            $varElements = $assignElement->getElementsByTagName('var');
            $parsedVars = $this->parseVars($varElements);
            if (empty($parsedVars)) {
                throw new XMLException("Invalid assign element: missing var element");
            }
            $command['var'] = $parsedVars[0]; // Take the first variable

            $exprElements = $assignElement->getElementsByTagName('expr');
            $parsedExprs = $this->parseExprs($this->filterDirectChildren($assignElement, 'expr'));
            if (empty($parsedExprs)) {
                throw new XMLException("Invalid assign element: missing expr element");
            }
            $command['expr'] = $parsedExprs[0]; // Take the first expression

            $commands[$order] = $command;
        }

        // Sort commands by order
        ksort($commands);

        return $commands;
    }

    /**
     * @param DOMNodeList<DOMElement> $varElements
     * @return array<int, array<string, mixed>>
     */
    private function parseVars(DOMNodeList $varElements): array
    {
        if ($varElements->length <= 0)
        {
            return [];
        }

        $vars = [];

        foreach ($varElements as $varElement)
        {
            if (!$varElement->hasAttribute('name'))
            {
                throw new XMLException("Invalid var element: missing name attribute");
            }

            $vars[] = [
                'type' => 'var',
                'name' => $varElement->getAttribute('name')
            ];
        }

        return $vars;
    }

    /**
     * @param array<int, DOMElement> $exprElements
     * @return array<int, array<string, mixed>>
     */
    private function parseExprs(array $exprElements): array
    {
        if (count($exprElements) <= 0) {
            return [];
        }

        $expressions = [];

        foreach ($exprElements as $exprElement) {
            // Collect all direct child XML_ELEMENT_NODE elements
            $childElements = $this->filterDirectChildren($exprElement);

            if (count($childElements) <= 0) {
                throw new XMLException("Invalid expr element: missing child elements");
            }

            $expr = null;
            // An expr should contain exactly one child element representing the expression type
            if (count($childElements) > 1) {
                throw new XMLException("Invalid expr element: must contain exactly one child element");
            }

            $childElement = $childElements[0];

            switch ($childElement->nodeName) {
                case 'literal':
                    $expr = $this->parseLiteral($childElement);
                    break;
                case 'var':
                    $expr = $this->parseVar($childElement);
                    break;
                case 'block':
                    $expr = [
                        'type' => 'block',
                        'block' => $this->parseBlock($childElement)
                    ];
                    break;
                case 'send':
                    $expr = $this->parseSend($childElement);
                    break;
                default:
                    throw new XMLException("Invalid expr element: unknown child element " . $childElement->nodeName);
            }

            $expressions[] = $expr;
        }

        return $expressions;
    }

    /**
     * @param DOMElement $literalElement
     * @return array{type: string, class: string, value: string}
     */
    private function parseLiteral(DOMElement $literalElement): array
    {
        if (!$literalElement->hasAttribute('class') || !$literalElement->hasAttribute('value')) {
            throw new XMLException("Invalid literal element: missing class or value attribute");
        }

        return [
            'type' => 'literal',
            'class' => $literalElement->getAttribute('class'),
            'value' => $literalElement->getAttribute('value')
        ];
    }

    /**
     * @param DOMElement $varElement
     * @return array{type: string, name: string}
     */
    private function parseVar(DOMElement $varElement): array
    {
        if (!$varElement->hasAttribute('name')) {
            throw new XMLException("Invalid var element: missing name attribute");
        }

        return [
            'type' => 'var',
            'name' => $varElement->getAttribute('name')
        ];
    }

    /**
     * @param DOMElement $blockElement
     * @return BlockDef
     */
    private function parseBlock(DOMElement $blockElement): array
    {
        if (!$blockElement->hasAttribute('arity')) {
            throw new XMLException("Block must have arity attribute");
        }

        $block = [
            'arity' => (int)$blockElement->getAttribute('arity'),
            'parameters' => [],
            'commands' => []
        ];

        // Parse parameters - only direct child elements
        $parameterElements = $this->filterDirectChildren($blockElement, 'parameter');

        if (count($parameterElements) !== (int)$blockElement->getAttribute('arity')) {
            throw new XMLException("Invalid block element: arity does not match number of parameters");
        }

        $block['parameters'] = $this->parseParameters($parameterElements, $blockElement);

        // Parse commands - only direct child elements
        $assignElements = $this->filterDirectChildren($blockElement, 'assign');

        /** @var array<int, CommandDef> $commands */
        $commands = $this->parseAssigns($assignElements);
        $block['commands'] = $commands;

        return $block;
    }

    /**
     * @param DOMElement $sendElement
     * @return array<string, mixed>
     */
    private function parseSend(DOMElement $sendElement): array
    {
        if (!$sendElement->hasAttribute('selector')) {
            throw new XMLException("Invalid send element: missing selector attribute");
        }

        $send = [
            'type' => 'send',
            'selector' => $sendElement->getAttribute('selector'),
            'receiver' => null,
            'arguments' => []
        ];

        // Parse receiver (the last direct child 'expr' element)
        $childExprElements = $this->filterDirectChildren($sendElement, 'expr');

        if (!empty($childExprElements)) {
            $receiverExprElement = end($childExprElements);

            // Parse the receiver expression
            $receiverExprs = $this->parseExprs([$receiverExprElement]);
            if (!empty($receiverExprs)) {
                $send['receiver'] = $receiverExprs[0];
            } else {
                throw new XMLException("Invalid send element: receiver expression could not be parsed");
            }
        } else {
            throw new XMLException("Invalid send element: missing receiver expression");
        }

        // Only process DIRECT ARG CHILDREN
        $argsElements = $this->filterDirectChildren($sendElement, 'arg');

        if (!empty($argsElements)) {
            $args = [];
            $isIfTrueIfFalse = ($sendElement->getAttribute('selector') === 'ifTrue:ifFalse:');

            foreach ($argsElements as $argElement) {
                if (!$argElement->hasAttribute('order')) {
                    throw new XMLException("Invalid arg element: missing order attribute");
                }

                $order = (int)$argElement->getAttribute('order');
                $argContent = null;

                // Find direct child blocks or expressions within the arg element
                $directBlockElements = $this->filterDirectChildren($argElement, 'block');
                $directExprElements = $this->filterDirectChildren($argElement, 'expr');

                if (!empty($directBlockElements) && !empty($directExprElements)) {
                    throw new XMLException("Invalid arg element: cannot contain both block and expr direct children");
                }

                if (!empty($directBlockElements)) {
                    $argContent = [
                        'type' => 'block',
                        'block' => $this->parseBlock($directBlockElements[0])
                    ];
                } elseif (!empty($directExprElements)) {
                    $argExprs = $this->parseExprs($directExprElements);
                    if (!empty($argExprs)) {
                        $argContent = $argExprs[0];
                    } else {
                        throw new XMLException("Invalid arg element: expression could not be parsed");
                    }
                } else {
                    throw new XMLException("Invalid arg element: must contain either a block or an expr child");
                }

                // Add to args array regardless of null status
                $args[$order] = $argContent;
            }

            // Sort arguments by order
            ksort($args);
            $send['arguments'] = array_values($args);
        }

        return $send;
    }

    /**
     * Find direct child elements of a specific type (or all element children if nodeName is null)
     * @param DOMElement $parentElement
     * @param ?string $nodeName
     * @return array<int, DOMElement>
     */
    private function filterDirectChildren(DOMElement $parentElement, ?string $nodeName = null): array
    {
        $elements = [];
        foreach ($parentElement->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($nodeName === null || $child->nodeName === $nodeName) {
                    $elements[] = $child;
                }
            }
        }
        return $elements;
    }
}