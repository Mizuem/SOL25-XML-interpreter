<?php

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Core\Exception\XMLException;

$globalCnt = 0;
class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
        try {
            // Парсинг XML в AST
            $dom = $this->source->getDOMDocument();
            $xmlParser = new XMLParser($dom);
            $program = $xmlParser->parse();
            // Создаем Runtime и Executor
            $runtime = new Runtime();
            $runtime->setIO($this->input, $this->stdout, $this->stderr);
            $executor = new Executor($runtime);
            
            // Выполняем программу
            $result = $executor->executeProgram($program);
            
            // В случае успешного выполнения программы
            return 0;
        } catch (\Exception $e) {
            // Обрабатываем ошибки и выводим сообщение об ошибке
            $this->stderr->writeString("Error " . $e->getCode() . ": " . $e->getMessage() . "\n");
            
            // Правильно возвращаем код ошибки - важная строка!
            $errorCode = $e->getCode();
            exit($errorCode);
        }
    }
}



















