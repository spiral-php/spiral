<?php

declare(strict_types=1);

namespace Spiral\Http\Request;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exception\ScopeException;
use Spiral\Http\Exception\InputException;
use Spiral\Http\Header\AcceptHeader;

/**
 * Provides simplistic way to access request input data in controllers and can also be used to
 * populate RequestFilters.
 *
 * Attention, this class is singleton based, it reads request from current active container scope!
 *
 * Technically this class can be made as middleware, but due spiral provides container scoping
 * such functionality may be replaces with simple container request routing.
 *
 * @property-read HeadersBag $headers
 * @property-read InputBag   $data
 * @property-read InputBag   $query
 * @property-read InputBag   $cookies
 * @property-read FilesBag   $files
 * @property-read ServerBag  $server
 * @property-read InputBag   $attributes
 */
final class InputManager implements SingletonInterface
{
    /**
     * Associations between bags and representing class/request method.
     *
     * @invisible
     */
    protected array $bagAssociations = [
        'headers'    => [
            'class'  => HeadersBag::class,
            'source' => 'getHeaders',
        ],
        'data'       => [
            'class'  => InputBag::class,
            'source' => 'getParsedBody',
        ],
        'query'      => [
            'class'  => InputBag::class,
            'source' => 'getQueryParams',
        ],
        'cookies'    => [
            'class'  => InputBag::class,
            'source' => 'getCookieParams',
        ],
        'files'      => [
            'class'  => FilesBag::class,
            'source' => 'getUploadedFiles',
        ],
        'server'     => [
            'class'  => ServerBag::class,
            'source' => 'getServerParams',
        ],
        'attributes' => [
            'class'  => InputBag::class,
            'source' => 'getAttributes',
        ],
    ];
    
    /**
     * @invisible
     */
    protected ?Request $request = null;

    /** @var InputBag[] */
    private array $bags = [];

    /**
     * Prefix to add for each input request.
     *
     * @see self::withPrefix();
     */
    private string $prefix = '';

    /**
     * List of content types that must be considered as JSON.
     */
    private array $jsonTypes = [
        'application/json',
    ];

    public function __construct(
        /** @invisible */
        private ContainerInterface $container
    ) {
    }

    public function __get(string $name): InputBag
    {
        return $this->bag($name);
    }

    /**
     * Flushing bag instances when cloned.
     */
    public function __clone()
    {
        $this->bags = [];
    }

    /**
     * Creates new input slice associated with request sub-tree.
     */
    public function withPrefix(string $prefix, bool $add = true): self
    {
        $input = clone $this;

        if ($add) {
            $input->prefix .= '.' . $prefix;
            $input->prefix = \trim($input->prefix, '.');
        } else {
            $input->prefix = $prefix;
        }

        return $input;
    }

    /**
     * Get page path (including leading slash) associated with active request.
     */
    public function path(): string
    {
        $path = $this->uri()->getPath();

        return match (true) {
            empty($path) => '/',
            $path[0] !== '/' => '/' . $path,
            default => $path
        };
    }

    /**
     * Get UriInterface associated with active request.
     */
    public function uri(): UriInterface
    {
        return $this->request()->getUri();
    }

    /**
     * Get active instance of ServerRequestInterface and reset all bags if instance changed.
     *
     * @throws ScopeException
     */
    public function request(): Request
    {
        try {
            $request = $this->container->get(Request::class);
        } catch (ContainerExceptionInterface $e) {
            throw new ScopeException(
                'Unable to get `ServerRequestInterface` in active container scope',
                $e->getCode(),
                $e
            );
        }

        //Flushing input state
        if ($this->request !== $request) {
            $this->bags = [];
            $this->request = $request;
        }

        return $this->request;
    }

    /**
     * Http method. Always uppercase.
     */
    public function method(): string
    {
        return \strtoupper($this->request()->getMethod());
    }

    /**
     * Check if request was made over http protocol.
     */
    public function isSecure(): bool
    {
        //Double check though attributes?
        return $this->request()->getUri()->getScheme() === 'https';
    }

    /**
     * Check if request was via AJAX.
     * Legacy-support alias for isXmlHttpRequest()
     * @see isXmlHttpRequest()
     */
    public function isAjax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Check if request was made using XmlHttpRequest.
     */
    public function isXmlHttpRequest(): bool
    {
        return \mb_strtolower(
            $this->request()->getHeaderLine('X-Requested-With')
        ) === 'xmlhttprequest';
    }

    /**
     * Client requesting json response by Accept header.
     */
    public function isJsonExpected(bool $softMatch = false): bool
    {
        $acceptHeader = AcceptHeader::fromString($this->request()->getHeaderLine('Accept'));
        foreach ($this->jsonTypes as $jsonType) {
            if ($acceptHeader->has($jsonType)) {
                return true;
            }
        }

        if ($softMatch) {
            foreach ($acceptHeader->getAll() as $item) {
                $itemValue = \strtolower($item->getValue());
                if (\str_ends_with($itemValue, '/json') || \str_ends_with($itemValue, '+json')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add new content type that will be considered as JSON.
     */
    public function withJsonType(string $type): self
    {
        $input = clone $this;
        $input->jsonTypes[] = $type;

        return $input;
    }

    /**
     * Get remove addr resolved from $_SERVER['REMOTE_ADDR']. Will return null if nothing if key not
     * exists. Consider using psr-15 middlewares to customize configuration.
     */
    public function remoteAddress(): ?string
    {
        $serverParams = $this->request()->getServerParams();

        return $serverParams['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get bag instance or create new one on demand.
     */
    public function bag(string $name): InputBag
    {
        // ensure proper request association
        $this->request();

        if (isset($this->bags[$name])) {
            return $this->bags[$name];
        }

        if (!isset($this->bagAssociations[$name])) {
            throw new InputException(\sprintf("Undefined input bag '%s'", $name));
        }

        $class = $this->bagAssociations[$name]['class'];
        $data = \call_user_func([$this->request(), $this->bagAssociations[$name]['source']]);

        if (!\is_array($data)) {
            $data = (array)$data;
        }

        return $this->bags[$name] = new $class($data, $this->prefix);
    }

    /**
     * @param mixed       $default
     * @param bool|string $implode Implode header lines, false to return header as array.
     */
    public function header(string $name, $default = null, bool|string $implode = ','): mixed
    {
        return $this->headers->get($name, $default, $implode);
    }

    /**
     * @see data()
     */
    public function post(string $name, mixed $default = null): mixed
    {
        return $this->data($name, $default);
    }

    public function data(string $name, mixed $default = null): mixed
    {
        return $this->data->get($name, $default);
    }

    /**
     * Reads data from data array, if not found query array will be used as fallback.
     */
    public function input(string $name, mixed $default = null): mixed
    {
        return $this->data($name, $this->query($name, $default));
    }

    public function query(string $name, mixed $default = null): mixed
    {
        return $this->query->get($name, $default);
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies->get($name, $default);
    }

    public function file(string $name, mixed $default = null): ?UploadedFileInterface
    {
        return $this->files->get($name, $default);
    }

    public function server(string $name, mixed $default = null): mixed
    {
        return $this->server->get($name, $default);
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes->get($name, $default);
    }
}
