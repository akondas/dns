<?php

use React\Dns\Query\CoopExecutor;
use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Promise\Promise;
use React\Tests\Dns\TestCase;
use React\Promise\Deferred;

class CoopExecutorTest extends TestCase
{
    public function testQueryOnceWillPassExactQueryToBaseExecutor()
    {
        $pending = new Promise(function () { });
        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->with('8.8.8.8', $query)->willReturn($pending);
        $connector = new CoopExecutor($base);

        $connector->query('8.8.8.8', $query);
    }

    public function testQueryOnceWillResolveWhenBaseExecutorResolves()
    {
        $message = new Message();

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->willReturn(\React\Promise\resolve($message));
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $promise = $connector->query('8.8.8.8', $query);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then($this->expectCallableOnceWith($message));
    }

    public function testQueryOnceWillRejectWhenBaseExecutorRejects()
    {
        $exception = new RuntimeException();

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->willReturn(\React\Promise\reject($exception));
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $promise = $connector->query('8.8.8.8', $query);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $promise->then(null, $this->expectCallableOnceWith($exception));
    }

    public function testQueryTwoDifferentQueriesWillPassExactQueryToBaseExecutorTwice()
    {
        $pending = new Promise(function () { });
        $query1 = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $query2 = new Query('reactphp.org', Message::TYPE_AAAA, Message::CLASS_IN);
        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->exactly(2))->method('query')->withConsecutive(
            array('8.8.8.8', $query1),
            array('8.8.8.8', $query2)
        )->willReturn($pending);
        $connector = new CoopExecutor($base);

        $connector->query('8.8.8.8', $query1);
        $connector->query('8.8.8.8', $query2);
    }

    public function testQueryTwiceWillPassExactQueryToBaseExecutorOnceWhenQueryIsStillPending()
    {
        $pending = new Promise(function () { });
        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->with('8.8.8.8', $query)->willReturn($pending);
        $connector = new CoopExecutor($base);

        $connector->query('8.8.8.8', $query);
        $connector->query('8.8.8.8', $query);
    }

    public function testQueryTwiceWillPassExactQueryToBaseExecutorTwiceWhenFirstQueryIsAlreadyResolved()
    {
        $deferred = new Deferred();
        $pending = new Promise(function () { });
        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->exactly(2))->method('query')->with('8.8.8.8', $query)->willReturnOnConsecutiveCalls($deferred->promise(), $pending);

        $connector = new CoopExecutor($base);

        $connector->query('8.8.8.8', $query);

        $deferred->resolve(new Message());

        $connector->query('8.8.8.8', $query);
    }

    public function testQueryTwiceWillPassExactQueryToBaseExecutorTwiceWhenFirstQueryIsAlreadyRejected()
    {
        $deferred = new Deferred();
        $pending = new Promise(function () { });
        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->exactly(2))->method('query')->with('8.8.8.8', $query)->willReturnOnConsecutiveCalls($deferred->promise(), $pending);

        $connector = new CoopExecutor($base);

        $connector->query('8.8.8.8', $query);

        $deferred->reject(new RuntimeException());

        $connector->query('8.8.8.8', $query);
    }

    public function testCancelQueryWillCancelPromiseFromBaseExecutorAndReject()
    {
        $promise = new Promise(function () { }, $this->expectCallableOnce());

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->willReturn($promise);
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $promise = $connector->query('8.8.8.8', $query);

        $promise->cancel();

        $promise->then(null, $this->expectCallableOnce());
    }

    public function testCancelOneQueryWhenOtherQueryIsStillPendingWillNotCancelPromiseFromBaseExecutorAndRejectCancelled()
    {
        $promise = new Promise(function () { }, $this->expectCallableNever());

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->willReturn($promise);
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $promise1 = $connector->query('8.8.8.8', $query);
        $promise2 = $connector->query('8.8.8.8', $query);

        $promise1->cancel();

        $promise1->then(null, $this->expectCallableOnce());
        $promise2->then(null, $this->expectCallableNever());
    }

    public function testCancelSecondQueryWhenFirstQueryIsStillPendingWillNotCancelPromiseFromBaseExecutorAndRejectCancelled()
    {
        $promise = new Promise(function () { }, $this->expectCallableNever());

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->willReturn($promise);
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $promise1 = $connector->query('8.8.8.8', $query);
        $promise2 = $connector->query('8.8.8.8', $query);

        $promise2->cancel();

        $promise2->then(null, $this->expectCallableOnce());
        $promise1->then(null, $this->expectCallableNever());
    }

    public function testCancelAllPendingQueriesWillCancelPromiseFromBaseExecutorAndRejectCancelled()
    {
        $promise = new Promise(function () { }, $this->expectCallableOnce());

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->once())->method('query')->willReturn($promise);
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);
        $promise1 = $connector->query('8.8.8.8', $query);
        $promise2 = $connector->query('8.8.8.8', $query);

        $promise1->cancel();
        $promise2->cancel();

        $promise1->then(null, $this->expectCallableOnce());
        $promise2->then(null, $this->expectCallableOnce());
    }

    public function testQueryTwiceWillQueryBaseExecutorTwiceIfFirstQueryHasAlreadyBeenCancelledWhenSecondIsStarted()
    {
        $promise = new Promise(function () { }, $this->expectCallableOnce());
        $pending = new Promise(function () { });

        $base = $this->getMockBuilder('React\Dns\Query\ExecutorInterface')->getMock();
        $base->expects($this->exactly(2))->method('query')->willReturnOnConsecutiveCalls($promise, $pending);
        $connector = new CoopExecutor($base);

        $query = new Query('reactphp.org', Message::TYPE_A, Message::CLASS_IN);

        $promise1 = $connector->query('8.8.8.8', $query);
        $promise1->cancel();

        $promise2 = $connector->query('8.8.8.8', $query);

        $promise1->then(null, $this->expectCallableOnce());

        $promise2->then(null, $this->expectCallableNever());
    }
}
