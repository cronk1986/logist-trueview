<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Throw_>
 */
final class SemanticExceptionRule implements Rule
{
    private const RESTRICTED_LAYERS = '/^Logis\\\\[A-Za-z]+Context\\\\(Domain|Application)(\\\\|$)/';

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Throw_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->expr instanceof New_ || preg_match(pattern: self::RESTRICTED_LAYERS, subject: $scope->getNamespace() ?? '') !== 1) {
            return [];
        }

        $errors = [];

        foreach ($scope->getType(node: $node->expr)->getObjectClassNames() as $className) {
            if (!$this->reflectionProvider->hasClass(className: $className)) {
                continue;
            }

            if ($this->reflectionProvider->getClass(className: $className)->isBuiltin()) {
                $errors[] = RuleErrorBuilder::message(
                    message: "Generic exception {$className} is not allowed in Domain or Application, throw a semantic exception instead.",
                )->identifier(identifier: 'logis.exception.generic')->build();
            }
        }

        return $errors;
    }
}
