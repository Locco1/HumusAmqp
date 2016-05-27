<?php
/**
 * Copyright (c) 2016. Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 *  "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 *  LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 *  A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 *  OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 *  LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 *  DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 *  THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 *  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 *  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *  This software consists of voluntary contributions made by many individuals
 *  and is licensed under the MIT license.
 */

declare (strict_types=1);

namespace Humus\Amqp\Container;

use Humus\Amqp\Connection;
use Humus\Amqp\Driver\Driver;
use Humus\Amqp\Driver\PhpAmqpLib\ConnectionType;
use Humus\Amqp\Driver\PhpAmqpLib\LazyConnection;
use Humus\Amqp\Driver\PhpAmqpLib\SocketConnection;
use Humus\Amqp\Driver\PhpAmqpLib\SslConnection;
use Humus\Amqp\Driver\PhpAmqpLib\StreamConnection;
use Humus\Amqp\Exception;
use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfigId;
use Interop\Config\RequiresMandatoryOptions;
use Interop\Container\ContainerInterface;

/**
 * Class ConnectionFactory
 * @package Humus\Amqp\Container
 */
final class ConnectionFactory implements ProvidesDefaultOptions, RequiresConfigId, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    /**
     * @var string
     */
    private $connectionName;

    /**
     * ConnectionFactory constructor.
     * @param string $connectionName
     */
    public function __construct(string $connectionName)
    {
        $this->connectionName = $connectionName;
    }

    /**
     * @param ContainerInterface $container
     * @return Connection
     */
    public function __invoke(ContainerInterface $container) : Connection
    {
        if (! $container->has(Driver::class)) {
            throw new Exception\RuntimeException('No driver factory registered in container');
        }

        $driver = $container->get(Driver::class);
        $config = $container->get('config');
        $options = $this->options($config, $this->connectionName);
        
        switch ($driver) {
            case Driver::AMQP_EXTENSION():
                $connection = new \Humus\Amqp\Driver\AmqpExtension\Connection($options);
                $connection->connect();
                break;
            case Driver::PHP_AMQP_LIB():
                if (!isset($options['type'])) {
                    throw new Exception\InvalidArgumentException('For php-amqplib driver a connection type is required');
                }
                if (false === defined(ConnectionType::class . '::' . $options['type'])) {
                    throw new Exception\InvalidArgumentException('Invalid connection type for php-amqplib driver given');
                }
                switch (ConnectionType::get($options['type'])) {
                    case ConnectionType::LAZY():
                        $connection = new LazyConnection($options);
                        break;
                    case ConnectionType::SOCKET():
                        $connection = new SocketConnection($options);
                        break;
                    case ConnectionType::SSL():
                        $connection = new SslConnection($options);
                        break;
                    case ConnectionType::STREAM():
                        $connection = new StreamConnection($options);
                        break;
                    default:
                        throw new Exception\RuntimeException('Unknown connection type');
                        break;
                }
                break;
            default:
                throw new Exception\RuntimeException('Invalid driver factory configured in the container');
        }

        return $connection;
    }

    /**
     * @return array
     */
    public function dimensions()
    {
        return ['humus', 'amqp', 'connection'];
    }

    /**
     * @return array
     */
    public function defaultOptions()
    {
        return [
            'host' => 'localhost',
            'port' => 5672,
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'persistent' => false,
            'read_timeout' => 1, //sec, float allowed
            'write_timeout' => 1, //sec, float allowed,
            'heartbeat' => 0,
            'cacert' => null,
            'cert' => null,
            'key' => null,
            'verify' => null,
        ];
    }

    /**
     * return array
     */
    public function mandatoryOptions()
    {
        return [
            'host',
            'port',
            'login',
            'password',
            'vhost',
            'persistent',
            'read_timeout',
            'write_timeout',
            'heartbeat',
            'cacert',
            'cert',
            'key',
            'verify',
        ];
    }
}
