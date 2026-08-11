<?php declare(strict_types = 1);

// osfsl-C:/websites/Sat7a_App/apps/backend/vendor/composer/../laravel/framework/src/Illuminate/Redis/Connections/Connection.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Redis\Connections\Connection
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-35a8abc24b36cb774568f0232a420cba8af3ee02fd7699975f080a49bdd25b9a-8.3.30-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Redis\\Connections\\Connection',
        'filename' => 'C:/websites/Sat7a_App/apps/backend/vendor/composer/../laravel/framework/src/Illuminate/Redis/Connections/Connection.php',
      ),
    ),
    'namespace' => 'Illuminate\\Redis\\Connections',
    'name' => 'Illuminate\\Redis\\Connections\\Connection',
    'shortName' => 'Connection',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => '/**
 * @mixin \\Redis
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 286,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'client' => 
      array (
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'name' => 'client',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Redis client.
 *
 * @var \\Redis
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'name' => 
      array (
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'name' => 'name',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Redis connection name.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'events' => 
      array (
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'name' => 'events',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher instance.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'createSubscription' => 
      array (
        'name' => 'createSubscription',
        'parameters' => 
        array (
          'channels' => 
          array (
            'name' => 'channels',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 49,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 60,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => 
            array (
              'code' => '\'subscribe\'',
              'attributes' => 
              array (
                'startLine' => 52,
                'endLine' => 52,
                'startTokenPos' => 115,
                'startFilePos' => 1100,
                'endTokenPos' => 115,
                'endFilePos' => 1110,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 79,
            'endColumn' => 99,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Subscribe to a set of given channels for messages.
 *
 * @param  array|string  $channels
 * @param  \\Closure  $callback
 * @param  string  $method
 * @return void
 */',
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 101,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 65,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'funnel' => 
      array (
        'name' => 'funnel',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 28,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Funnel a callback for a maximum number of simultaneous executions.
 *
 * @param  string  $name
 * @return \\Illuminate\\Redis\\Limiters\\ConcurrencyLimiterBuilder
 */',
        'startLine' => 60,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'throttle' => 
      array (
        'name' => 'throttle',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Throttle a callback for a maximum number of executions over a given duration.
 *
 * @param  string  $name
 * @return \\Illuminate\\Redis\\Limiters\\DurationLimiterBuilder
 */',
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'client' => 
      array (
        'name' => 'client',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the underlying Redis client.
 *
 * @return mixed
 */',
        'startLine' => 81,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'subscribe' => 
      array (
        'name' => 'subscribe',
        'parameters' => 
        array (
          'channels' => 
          array (
            'name' => 'channels',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 31,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 42,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Subscribe to a set of given channels for messages.
 *
 * @param  array|string  $channels
 * @param  \\Closure  $callback
 * @return void
 */',
        'startLine' => 93,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'psubscribe' => 
      array (
        'name' => 'psubscribe',
        'parameters' => 
        array (
          'channels' => 
          array (
            'name' => 'channels',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 32,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 43,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Subscribe to a set of given channels with wildcards.
 *
 * @param  array|string  $channels
 * @param  \\Closure  $callback
 * @return void
 */',
        'startLine' => 105,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'command' => 
      array (
        'name' => 'command',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 119,
                'endLine' => 119,
                'startTokenPos' => 281,
                'startFilePos' => 2769,
                'endTokenPos' => 282,
                'endFilePos' => 2770,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 38,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a command against the Redis database.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 *
 * @throws \\Throwable
 */',
        'startLine' => 119,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'parseParametersForEvent' => 
      array (
        'name' => 'parseParametersForEvent',
        'parameters' => 
        array (
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 148,
            'endLine' => 148,
            'startColumn' => 48,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the command\'s parameters for event dispatching.
 *
 * @param  array  $parameters
 * @return array
 */',
        'startLine' => 148,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'event' => 
      array (
        'name' => 'event',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 161,
            'endLine' => 161,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Fire the given event if possible.
 *
 * @param  mixed  $event
 * @return void
 *
 * @deprecated since Laravel 11.x
 */',
        'startLine' => 161,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'listen' => 
      array (
        'name' => 'listen',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 28,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a Redis command listener with the connection.
 *
 * @param  \\Closure  $callback
 * @return void
 */',
        'startLine' => 172,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'listenForFailures' => 
      array (
        'name' => 'listenForFailures',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 183,
            'endLine' => 183,
            'startColumn' => 39,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a Redis command failure listener with the connection.
 *
 * @param  \\Closure  $callback
 * @return void
 */',
        'startLine' => 183,
        'endLine' => 186,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'isCluster' => 
      array (
        'name' => 'isCluster',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the connection is a cluster connection.
 *
 * @return bool
 */',
        'startLine' => 193,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'getName' => 
      array (
        'name' => 'getName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the connection name.
 *
 * @return string|null
 */',
        'startLine' => 203,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'setName' => 
      array (
        'name' => 'setName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 214,
            'endLine' => 214,
            'startColumn' => 29,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the connection\'s name.
 *
 * @param  string  $name
 * @return $this
 */',
        'startLine' => 214,
        'endLine' => 219,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'getEventDispatcher' => 
      array (
        'name' => 'getEventDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the event dispatcher used by the connection.
 *
 * @return \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'startLine' => 226,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'setEventDispatcher' => 
      array (
        'name' => 'setEventDispatcher',
        'parameters' => 
        array (
          'events' => 
          array (
            'name' => 'events',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Events\\Dispatcher',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 40,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the event dispatcher instance on the connection.
 *
 * @param  \\Illuminate\\Contracts\\Events\\Dispatcher  $events
 * @return void
 */',
        'startLine' => 237,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'unsetEventDispatcher' => 
      array (
        'name' => 'unsetEventDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Unset the event dispatcher instance on the connection.
 *
 * @return void
 */',
        'startLine' => 247,
        'endLine' => 250,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      'hasHashTag' => 
      array (
        'name' => 'hasHashTag',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 258,
            'endLine' => 258,
            'startColumn' => 39,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given key contains a Redis Cluster hash tag.
 *
 * @param  string  $key
 * @return bool
 */',
        'startLine' => 258,
        'endLine' => 269,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 278,
            'endLine' => 278,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 278,
            'endLine' => 278,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Pass other method calls down to the underlying client.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 278,
        'endLine' => 285,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Redis\\Connections',
        'declaringClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'implementingClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'currentClassName' => 'Illuminate\\Redis\\Connections\\Connection',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));