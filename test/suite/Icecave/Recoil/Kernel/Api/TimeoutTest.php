<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Kernel\Exception\StrandTerminatedException;
use Icecave\Recoil\Kernel\Exception\TimeoutException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class TimeoutTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    public function testTimeout()
    {
        $this->expectOutputString('1');

        $immediate = function () {
            yield Recoil::return_(1);
        };

        $coroutine = function () use ($immediate) {
            echo (yield new Timeout(0.01, $immediate()));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testTimeoutWithException()
    {
        $this->expectOutputString('1');

        $immediate = function () {
            yield Recoil::throw_(new Exception(1));
        };

        $coroutine = function () use ($immediate) {
            try {
                yield new Timeout(0.01, $immediate());
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testTimeoutExceeded()
    {
        $this->expectOutputString('1');

        $forever = function () {
            yield Recoil::suspend(function () {});
        };

        $coroutine = function () use ($forever) {
            try {
                yield new Timeout(0.01, $forever());
            } catch (TimeoutException $e) {
                echo 1;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testTimerIsStoppedWhenStrandIsTerminated()
    {
        $this->expectOutputString('');

        $immediate = function () {
            yield Recoil::terminate();
        };

        $coroutine = function () use ($immediate) {
            yield Recoil::timeout(1, $immediate());
            echo 'X';
        };

        $strand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $exception = null;
        $strand->then(
            null,
            function ($e) use (&$exception) {
                $exception = $e;
            }
        );

        $this->assertInstanceOf(StrandTerminatedException::CLASS, $exception);
    }
}
