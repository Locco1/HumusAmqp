<?php
/**
 * Copyright (c) 2016-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

declare(strict_types=1);

namespace HumusTest\Amqp;

use Humus\Amqp\Channel;
use Humus\Amqp\Constants;
use Humus\Amqp\Envelope;
use Humus\Amqp\Exchange;
use Humus\Amqp\PlainProducer;
use Humus\Amqp\Queue;
use HumusTest\Amqp\Helper\CanCreateConnection;
use HumusTest\Amqp\Helper\DeleteOnTearDownTrait;
use PHPUnit\Framework\TestCase;

abstract class AbstractPlainProducerTest extends TestCase implements CanCreateConnection
{
    use DeleteOnTearDownTrait;

    protected Channel $channel;
    protected Exchange $exchange;
    protected Queue $queue;
    protected PlainProducer $producer;
    /**
     * @var callable
     */
    protected $callback;
    protected array $results = [];

    protected function setUp(): void
    {
        $this->callback = function (Envelope $envelope): void {
            $this->results[] = $envelope->getBody();
        };

        $connection = $this->createConnection();
        $channel = $connection->newChannel();

        $exchange = $channel->newExchange();
        $exchange->setType('topic');
        $exchange->setName('test-exchange');
        $exchange->declareExchange();

        $queue = $channel->newQueue();
        $queue->setName('test-queue');
        $queue->declareQueue();
        $queue->bind('test-exchange', '#');

        $this->channel = $channel;
        $this->exchange = $exchange;
        $this->queue = $queue;

        $this->addToCleanUp($queue);
        $this->addToCleanUp($exchange);
    }

    /**
     * @test
     */
    public function it_produces_and_get_messages_from_queue(): void
    {
        $producer = new PlainProducer($this->exchange);
        $producer->publish('foo');
        $producer->publish('bar');

        $msg1 = $this->queue->get(Constants::AMQP_AUTOACK);
        $msg2 = $this->queue->get(Constants::AMQP_AUTOACK);

        $this->assertSame('foo', $msg1->getBody());
        $this->assertSame('bar', $msg2->getBody());
    }

    /**
     * @test
     */
    public function it_produces_transactional_and_get_messages_from_queue(): void
    {
        $producer = new PlainProducer($this->exchange);
        $producer->startTransaction();
        $producer->publish('foo');
        $producer->publish('bar');
        $producer->commitTransaction();

        $msg1 = $this->queue->get(Constants::AMQP_AUTOACK);
        $msg2 = $this->queue->get(Constants::AMQP_AUTOACK);

        $this->assertSame('foo', $msg1->getBody());
        $this->assertSame('bar', $msg2->getBody());
    }

    /**
     * @test
     */
    public function it_rolls_back_transaction(): void
    {
        $producer = new PlainProducer($this->exchange);
        $producer->startTransaction();
        $producer->publish('foo');
        $producer->publish('bar');
        $producer->rollbackTransaction();

        $msg = $this->queue->get(Constants::AMQP_NOPARAM);
        $this->assertNull($msg);
    }

    /**
     * @test
     */
    public function it_produces_in_confirm_mode(): void
    {
        $this->exchange->getChannel()->setConfirmCallback(
            function (): bool {
                return false;
            },
            function (int $delivery_tag, bool $multiple, bool $requeue): void {
                throw new \Exception('Could not confirm message publishing');
            }
        );

        $connection = $this->createConnection();
        $channel = $connection->newChannel();
        $queue = $channel->newQueue();
        $queue->setName('text-queue2');
        $queue->declareQueue();
        $queue->bind('test-exchange');

        $this->addToCleanUp($queue);

        $producer = new PlainProducer($this->exchange);
        $producer->confirmSelect();

        $producer->publish('foo');
        $producer->publish('bar');

        $producer->waitForConfirm();

        $msg1 = $queue->get(Constants::AMQP_NOPARAM);
        $msg2 = $queue->get(Constants::AMQP_AUTOACK);

        $this->assertSame('foo', $msg1->getBody());
        $this->assertSame('bar', $msg2->getBody());

        $queue->delete();
    }
}
