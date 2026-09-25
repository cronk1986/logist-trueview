<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * @implements Rule<CallLike>
 */
final class NamedArgumentsRule implements Rule
{
    private const CHECKED_NAMESPACES = '/^(Logis|Tests|Tools|Database)(\\\\|$)/';

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isChecked(node: $node, scope: $scope)) {
            return [];
        }

        $callable = $this->resolveCallable(node: $node, scope: $scope);

        if ($callable !== null && !$this->acceptsNamedArguments(callable: $callable)) {
            return $this->namedArgumentErrors(node: $node, callable: $callable);
        }

        $positionalArguments = $this->positionalArguments(node: $node);

        if ($positionalArguments === [] || $this->unpacksArguments(node: $node)) {
            return [];
        }

        if ($callable === null) {
            return [$this->unresolvedError(node: $node)];
        }

        $parameters = ParametersAcceptorSelector::selectFromArgs(scope: $scope, args: $node->getArgs(), parametersAcceptors: $callable->getVariants())
            ->getParameters();

        if ($this->passesVariadicArguments(parameters: $parameters, positionalArguments: $positionalArguments)) {
            return [];
        }

        $errors = [];

        foreach ($positionalArguments as $position => $argument) {
            $parameter = $parameters[$position] ?? null;

            if ($parameter === null) {
                continue;
            }

            $errors[] = $this->positionalError(callable: $callable, parameter: $parameter, argument: $argument);
        }

        return $errors;
    }

    private function acceptsNamedArguments(FunctionReflection|ExtendedMethodReflection $callable): bool
    {
        if ($callable->acceptsNamedArguments()->no()) {
            return false;
        }

        if (!$callable instanceof ExtendedMethodReflection) {
            return true;
        }

        return $callable->getDeclaringClass()->getNativeReflection()->hasMethod(name: $callable->getName());
    }

    private function isChecked(CallLike $node, Scope $scope): bool
    {
        if ($node->isFirstClassCallable()) {
            return false;
        }

        if ($node instanceof FuncCall && !$node->name instanceof Name) {
            return false;
        }

        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && !$node->name instanceof Identifier) {
            return false;
        }

        return preg_match(pattern: self::CHECKED_NAMESPACES, subject: $scope->getNamespace() ?? '') === 1;
    }

    private function unpacksArguments(CallLike $node): bool
    {
        foreach ($node->getArgs() as $argument) {
            if ($argument->unpack) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, Arg>
     */
    private function positionalArguments(CallLike $node): array
    {
        $positional = [];

        foreach ($node->getArgs() as $position => $argument) {
            if ($argument->name === null && !$argument->unpack) {
                $positional[$position] = $argument;
            }
        }

        return $positional;
    }

    private function resolveCallable(CallLike $node, Scope $scope): FunctionReflection|ExtendedMethodReflection|null
    {
        return match (true) {
            $node instanceof FuncCall => $this->resolveFunction(node: $node, scope: $scope),
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $this->resolveMethod(caller: $node->var, name: $node->name, scope: $scope),
            $node instanceof StaticCall => $this->resolveStaticMethod(node: $node, scope: $scope),
            $node instanceof New_ => $this->resolveConstructor(node: $node, scope: $scope),
            default => null,
        };
    }

    private function resolveFunction(FuncCall $node, Scope $scope): ?FunctionReflection
    {
        if (!$node->name instanceof Name || !$this->reflectionProvider->hasFunction(nameNode: $node->name, namespaceAnswerer: $scope)) {
            return null;
        }

        return $this->reflectionProvider->getFunction(nameNode: $node->name, namespaceAnswerer: $scope);
    }

    private function resolveMethod(Expr $caller, Identifier|Expr $name, Scope $scope): ?ExtendedMethodReflection
    {
        if (!$name instanceof Identifier) {
            return null;
        }

        $callerType = TypeCombinator::removeNull(type: $scope->getType(node: $caller));

        return $scope->getMethodReflection(typeWithMethod: $callerType, methodName: $name->toString());
    }

    private function resolveStaticMethod(StaticCall $node, Scope $scope): ?ExtendedMethodReflection
    {
        if (!$node->name instanceof Identifier) {
            return null;
        }

        if (!$node->class instanceof Name) {
            return $this->resolveMethod(caller: $node->class, name: $node->name, scope: $scope);
        }

        $className = $scope->resolveName(name: $node->class);

        if (!$this->reflectionProvider->hasClass(className: $className)) {
            return null;
        }

        $class = $this->reflectionProvider->getClass(className: $className);
        $methodName = $node->name->toString();

        if (!$class->hasMethod(methodName: $methodName)) {
            return null;
        }

        return $class->getMethod(methodName: $methodName, scope: $scope);
    }

    private function resolveConstructor(New_ $node, Scope $scope): ?ExtendedMethodReflection
    {
        $classes = $node->class instanceof Class_ || $node->class instanceof Expr
            ? $scope->getType(node: $node)->getObjectClassReflections()
            : $this->classReflectionsByName(name: $node->class, scope: $scope);

        $class = $classes[0] ?? null;

        if ($class === null || !$class->hasConstructor()) {
            return null;
        }

        return $class->getConstructor();
    }

    /**
     * @return list<\PHPStan\Reflection\ClassReflection>
     */
    private function classReflectionsByName(Name $name, Scope $scope): array
    {
        $className = $scope->resolveName(name: $name);

        if (!$this->reflectionProvider->hasClass(className: $className)) {
            return [];
        }

        return [$this->reflectionProvider->getClass(className: $className)];
    }

    /**
     * @param list<ParameterReflection> $parameters
     * @param array<int, Arg> $positionalArguments
     */
    private function passesVariadicArguments(array $parameters, array $positionalArguments): bool
    {
        foreach ($parameters as $index => $parameter) {
            if (!$parameter->isVariadic()) {
                continue;
            }

            foreach (array_keys(array: $positionalArguments) as $position) {
                if ($position >= $index) {
                    return true;
                }
            }
        }

        return false;
    }

    private function positionalError(
        FunctionReflection|ExtendedMethodReflection $callable,
        ParameterReflection $parameter,
        Arg $argument,
    ): IdentifierRuleError {
        return RuleErrorBuilder::message(message: sprintf(
            'Argument $%s of %s() must be passed by name.',
            $parameter->getName(),
            $this->describe(callable: $callable),
        ))
            ->identifier(identifier: 'logis.arguments.positional')
            ->line(line: $argument->getStartLine())
            ->build();
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function namedArgumentErrors(CallLike $node, FunctionReflection|ExtendedMethodReflection $callable): array
    {
        if ($callable->acceptsNamedArguments()->no()) {
            return [];
        }

        $errors = [];

        foreach ($node->getArgs() as $argument) {
            if ($argument->name === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(message: sprintf(
                '%s() is resolved magically, so argument $%s must be passed by position.',
                $this->describe(callable: $callable),
                $argument->name->toString(),
            ))
                ->identifier(identifier: 'logis.arguments.magicNamed')
                ->line(line: $argument->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function unresolvedError(CallLike $node): IdentifierRuleError
    {
        return RuleErrorBuilder::message(message: 'Arguments must be passed by name, and the called function could not be resolved.')
            ->identifier(identifier: 'logis.arguments.unresolved')
            ->line(line: $node->getStartLine())
            ->build();
    }

    private function describe(FunctionReflection|ExtendedMethodReflection $callable): string
    {
        if ($callable instanceof ExtendedMethodReflection) {
            return $callable->getDeclaringClass()->getName() . '::' . $callable->getName();
        }

        return $callable->getName();
    }
}
