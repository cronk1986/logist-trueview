<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use Logis\SharedContext\Domain\QueryResponse\QueryResponse;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<InClassNode>
 */
final class HandlerSignatureRule implements Rule
{
    private const COMMAND_NAMESPACE = 'Logis\\LogisContext\\Application\\Command\\';
    private const QUERY_NAMESPACE = 'Logis\\LogisContext\\Application\\Query\\';

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $handlerClass = $class->getName();

        if (! str_ends_with(haystack: $handlerClass, needle: 'Handler') || $class->isInterface() || $class->isAbstract()) {
            return [];
        }

        $isCommand = str_starts_with(haystack: $handlerClass, needle: self::COMMAND_NAMESPACE);
        $isQuery = str_starts_with(haystack: $handlerClass, needle: self::QUERY_NAMESPACE);

        if (! $isCommand && ! $isQuery) {
            return [];
        }

        $kind = $isCommand ? 'Command' : 'Query';
        $shortName = $class->getNativeReflection()->getShortName();

        if (! $class->hasNativeMethod(methodName: 'handle') || ! $class->getNativeMethod(methodName: 'handle')->isPublic()) {
            return [$this->error(message: "{$kind} handler {$shortName} must have a public handle() method.", identifier: 'missingHandle')];
        }

        $line = $class->getNativeReflection()->getMethod(name: 'handle')->getStartLine() ?: null;
        $variant = $class->getNativeMethod(methodName: 'handle')->getOnlyVariant();
        $parameters = $variant->getParameters();
        $expectedMessage = substr(string: $handlerClass, offset: 0, length: -strlen(string: 'Handler'));
        $errors = [];

        if (count(value: $parameters) !== 1 || $parameters[0]->getNativeType()->getObjectClassNames() !== [$expectedMessage]) {
            $errors[] = $this->error(
                message: "{$kind} handler {$shortName}::handle() must receive exactly one parameter typed {$expectedMessage}.",
                identifier: 'invalidParameter',
                line: $line,
            );
        }

        $returnType = $variant->getNativeReturnType();

        if ($isCommand && ! $returnType->isVoid()->yes()) {
            $errors[] = $this->error(
                message: "Command handler {$shortName}::handle() must return void, {$this->describe(type: $returnType)} declared.",
                identifier: 'invalidReturn',
                line: $line,
            );
        }

        if ($isQuery && ! (new ObjectType(className: QueryResponse::class))->isSuperTypeOf(type: $returnType)->yes()) {
            $errors[] = $this->error(
                message: "Query handler {$shortName}::handle() must return " . QueryResponse::class . ", {$this->describe(type: $returnType)} declared.",
                identifier: 'invalidReturn',
                line: $line,
            );
        }

        return $errors;
    }

    private function describe(Type $type): string
    {
        return $type->describe(level: VerbosityLevel::typeOnly());
    }

    private function error(string $message, string $identifier, ?int $line = null): IdentifierRuleError
    {
        $builder = RuleErrorBuilder::message(message: $message)->identifier(identifier: 'logis.handler.' . $identifier);

        if ($line !== null) {
            $builder->line(line: $line);
        }

        return $builder->build();
    }
}
