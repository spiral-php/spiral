<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveDoubleAssignRector;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Expression\RemoveDeadStmtRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php70\Rector\StmtsAwareInterface\IfIssetToCoalescingRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\ClassMethod\NewInInitializerRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src/*/src',
        __DIR__ . '/src/*/tests',
        __DIR__ . '/tests',
    ])
    ->withParallel()
    ->withSkip([
        IfIssetToCoalescingRector::class,
        RemoveUnusedPrivatePropertyRector::class => [
            __DIR__ . '/src/Scaffolder/src/Command/BootloaderCommand.php',
            __DIR__ . '/src/Scaffolder/src/Command/CommandCommand.php',
            __DIR__ . '/src/Scaffolder/src/Command/ConfigCommand.php',
            __DIR__ . '/src/Scaffolder/src/Command/ControllerCommand.php',
            __DIR__ . '/src/Scaffolder/src/Command/FilterCommand.php',
            __DIR__ . '/src/Scaffolder/src/Command/JobHandlerCommand.php',
            __DIR__ . '/src/Scaffolder/src/Command/MiddlewareCommand.php',
            __DIR__ . '/src/Console/tests/PromptArgumentsTest.php',
        ],
        RemoveUnusedPrivateMethodRector::class => [
            __DIR__ . '/src/Boot/src/Bootloader/ConfigurationBootloader.php',
            __DIR__ . '/src/Broadcasting/src/Bootloader/BroadcastingBootloader.php',
            __DIR__ . '/src/Cache/src/Bootloader/CacheBootloader.php',
            __DIR__ . '/src/Serializer/src/Bootloader/SerializerBootloader.php',
            __DIR__ . '/src/Validation/src/Bootloader/ValidationBootloader.php',
            __DIR__ . '/src/Translator/tests/IndexerTest.php',
            __DIR__ . '/src/Tokenizer/tests/ReflectionFileTest.php',
            __DIR__ . '/src/Core/tests/SingletonsTest.php',
        ],
        RemoveUselessVarTagRector::class => [
            __DIR__ . '/src/Console/src/Traits/HelpersTrait.php',
        ],
        RemoveAlwaysTrueIfConditionRector::class => [
            __DIR__ . '/src/Boot/src/BootloadManager/Initializer.php',
            __DIR__ . '/src/Stempler/src/Traverser.php',
            __DIR__ . '/src/Prototype/src/NodeVisitors/LocateProperties.php',
            __DIR__ . '/src/Prototype/src/NodeVisitors/RemoveTrait.php',
            __DIR__ . '/src/Logger/src/ListenerRegistry.php',
            __DIR__ . '/src/Stempler/src/Transform/Merge/ExtendsParent.php',
        ],
        RemoveExtraParametersRector::class => [
            __DIR__ . '/src/Boot/src/BootloadManager/AbstractBootloadManager.php',
        ],
        RemoveUnusedPrivateMethodParameterRector::class => [
            __DIR__ . '/src/Core/src/Internal/Factory.php',
            __DIR__ . '/src/Core/tests/InjectableTest.php',
        ],
        RemoveDoubleAssignRector::class => [
            __DIR__ . '/src/Core/tests/Scope/FinalizeAttributeTest.php',
        ],
        RemoveUnusedVariableAssignRector::class => [
            __DIR__ . '/src/Core/tests/ExceptionsTest.php',
        ],
        RemoveDeadStmtRector::class => [
            __DIR__ . '/src/Core/tests/ExceptionsTest.php',
        ],

        // to be enabled later for bc break 4.x
        RemoveUnusedPublicMethodParameterRector::class,
        RemoveEmptyClassMethodRector::class,
        RemoveUnusedPromotedPropertyRector::class,
        NewInInitializerRector::class,

        // start with short open tag
        __DIR__ . '/src/Views/tests/fixtures/other/var.php',
        __DIR__ . '/tests/app/views/native.php',

        // example code for test
        '*/Fixture/*',
        '*/Fixtures/*',
        '*/fixtures/*',
        '*/Stub/*',
        '*/Stubs/*',
        '*/tests/Classes/*',
        '*/tests/Internal/*',
        __DIR__ . '/src/Console/tests/Configurator',

        // cache
        '*/runtime/cache/*',

        ReadOnlyPropertyRector::class => [
            // used by Configurator
            __DIR__ . '/src/Scaffolder/src/Command',
        ],

        \Rector\PHPUnit\PHPUnit100\Rector\MethodCall\AssertIssetToAssertObjectHasPropertyRector::class => [
            // ArrayAccess usage
            __DIR__ . '/src/Session/tests/SessionTest.php',
        ],

        // nullable @template usage, see https://github.com/rectorphp/rector-src/pull/6409
        // can be re-enabled on next rector release
        RemoveUselessParamTagRector::class => [
            __DIR__ . '/src/Interceptors/src/Context/Target.php',
        ],

        RemoveUselessReturnTagRector::class => [
            __DIR__ . '/src/Interceptors/src/Context/TargetInterface.php',
        ],
    ])
    ->withPhpSets(php81: true)
    ->withPreparedSets(deadCode: true, phpunit: true)
    ->withConfiguredRule(ClassPropertyAssignToConstructorPromotionRector::class, [
        ClassPropertyAssignToConstructorPromotionRector::RENAME_PROPERTY => false,
    ]);
