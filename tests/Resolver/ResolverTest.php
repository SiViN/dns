<?php

namespace React\Tests\Dns\Resolver;

use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Dns\Query\ExecutorInterface;
use React\Dns\Query\Query;
use React\Dns\RecordNotFoundException;
use React\Dns\Resolver\Resolver;
use React\Tests\Dns\TestCase;
use function React\Promise\resolve;

class ResolverTest extends TestCase
{
    /** @test */
    public function resolveShouldQueryARecords()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query($query->name, $query->type, $query->class);
                $response->answers[] = new Record($query->name, $query->type, $query->class, 3600, '178.79.169.131');

                return resolve($response);
            }));

        $resolver = new Resolver($executor);
        $resolver->resolve('igor.io')->then($this->expectCallableOnceWith('178.79.169.131'));
    }

    /** @test */
    public function resolveAllShouldQueryGivenRecords()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query($query->name, $query->type, $query->class);
                $response->answers[] = new Record($query->name, $query->type, $query->class, 3600, '::1');

                return resolve($response);
            }));

        $resolver = new Resolver($executor);
        $resolver->resolveAll('reactphp.org', Message::TYPE_AAAA)->then($this->expectCallableOnceWith(['::1']));
    }

    /** @test */
    public function resolveAllShouldIgnoreRecordsWithOtherTypes()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query($query->name, $query->type, $query->class);
                $response->answers[] = new Record($query->name, Message::TYPE_TXT, $query->class, 3600, ['ignored']);
                $response->answers[] = new Record($query->name, $query->type, $query->class, 3600, '::1');

                return resolve($response);
            }));

        $resolver = new Resolver($executor);
        $resolver->resolveAll('reactphp.org', Message::TYPE_AAAA)->then($this->expectCallableOnceWith(['::1']));
    }

    /** @test */
    public function resolveAllShouldReturnMultipleValuesForAlias()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query($query->name, $query->type, $query->class);
                $response->answers[] = new Record($query->name, Message::TYPE_CNAME, $query->class, 3600, 'example.com');
                $response->answers[] = new Record('example.com', $query->type, $query->class, 3600, '::1');
                $response->answers[] = new Record('example.com', $query->type, $query->class, 3600, '::2');

                return resolve($response);
            }));

        $resolver = new Resolver($executor);
        $resolver->resolveAll('reactphp.org', Message::TYPE_AAAA)->then(
            $this->expectCallableOnceWith($this->equalTo(['::1', '::2']))
        );
    }

    /** @test */
    public function resolveShouldQueryARecordsAndIgnoreCase()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query('Blog.wyrihaximus.net', $query->type, $query->class);
                $response->answers[] = new Record('Blog.wyrihaximus.net', $query->type, $query->class, 3600, '178.79.169.131');

                return resolve($response);
            }));

        $resolver = new Resolver($executor);
        $resolver->resolve('blog.wyrihaximus.net')->then($this->expectCallableOnceWith('178.79.169.131'));
    }

    /** @test */
    public function resolveShouldFilterByName()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query($query->name, $query->type, $query->class);
                $response->answers[] = new Record('foo.bar', $query->type, $query->class, 3600, '178.79.169.131');

                return resolve($response);
            }));

        $errback = $this->expectCallableOnceWith($this->isInstanceOf(RecordNotFoundException::class));

        $resolver = new Resolver($executor);
        $resolver->resolve('igor.io')->then($this->expectCallableNever(), $errback);
    }

    /**
     * @test
     */
    public function resolveWithNoAnswersShouldCallErrbackIfGiven()
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) {
                $response = new Message();
                $response->qr = true;
                $response->questions[] = new Query($query->name, $query->type, $query->class);

                return resolve($response);
            }));

        $errback = $this->expectCallableOnceWith($this->callback(function ($param) {
            return ($param instanceof RecordNotFoundException && $param->getCode() === 0 && $param->getMessage() === 'DNS query for igor.io (A) did not return a valid answer (NOERROR / NODATA)');
        }));

        $resolver = new Resolver($executor);
        $resolver->resolve('igor.io')->then($this->expectCallableNever(), $errback);
    }

    public function provideRcodeErrors()
    {
        yield  [
            Message::RCODE_FORMAT_ERROR,
            'DNS query for example.com (A) returned an error response (Format Error)',
        ];
        yield [
            Message::RCODE_SERVER_FAILURE,
            'DNS query for example.com (A) returned an error response (Server Failure)',
        ];
        yield [
            Message::RCODE_NAME_ERROR,
            'DNS query for example.com (A) returned an error response (Non-Existent Domain / NXDOMAIN)'
        ];
        yield [
            Message::RCODE_NOT_IMPLEMENTED,
            'DNS query for example.com (A) returned an error response (Not Implemented)'
        ];
        yield [
            Message::RCODE_REFUSED,
            'DNS query for example.com (A) returned an error response (Refused)'
        ];
        yield [
            99,
            'DNS query for example.com (A) returned an error response (Unknown error response code 99)'
        ];
    }

    /**
     * @test
     * @dataProvider provideRcodeErrors
     */
    public function resolveWithRcodeErrorShouldCallErrbackIfGiven($code, $expectedMessage)
    {
        $executor = $this->createExecutorMock();
        $executor
            ->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(Query::class))
            ->will($this->returnCallback(function ($query) use ($code) {
                $response = new Message();
                $response->qr = true;
                $response->rcode = $code;
                $response->questions[] = new Query($query->name, $query->type, $query->class);

                return resolve($response);
            }));

        $errback = $this->expectCallableOnceWith($this->callback(function ($param) use ($code, $expectedMessage) {
            return ($param instanceof RecordNotFoundException && $param->getCode() === $code && $param->getMessage() === $expectedMessage);
        }));

        $resolver = new Resolver($executor);
        $resolver->resolve('example.com')->then($this->expectCallableNever(), $errback);
    }

    private function createExecutorMock()
    {
        return $this->createMock(ExecutorInterface::class);
    }
}
