<?php

namespace Illuminate\Tests;

use Illuminate\Console\Exceptions\ProcessFailedException;
use Illuminate\Console\Exceptions\ProcessNotStartedException;
use Illuminate\Console\Exceptions\ProcessTimedOutException;
use Illuminate\Console\Process\Factory;
use Illuminate\Console\Process\FakeProcessResult;
use Illuminate\Console\Process\ProcessResult;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProcessTest extends TestCase
{
    /**
     * @var \Illuminate\Console\Process\Factory
     */
    protected $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new Factory;
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testResultOk()
    {
        $this->factory->fake();

        $result = $this->factory->run('ls');

        $this->assertTrue($result->ok());
    }

    public function testResultFailed()
    {
        $this->factory->fake(function () {
            return $this->factory::result('my-process-output', 1);
        });

        $result = $this->factory->run('ls');

        $this->assertTrue($result->failed());
    }

    public function testResultOutput()
    {
        $this->factory->fake(function ($process) {
            if (str($process->getCommandLine())->contains('fooo')) {
                return $this->factory::result('some failure', 1);
            }
        });

        $this->factory->fake([
            'ls -la' => 'drwxr-xr-x   25 nunomaduro',
            'ls -laaaaa' => 'drwxr-xr-x   25 taylorotwell',
            '*' => 'drwxr-xr-x   25 root',
        ]);

        $result = $this->factory->run('fooo');
        $this->assertSame('some failure', $result->output());
        $this->assertTrue($result->failed());

        $result = $this->factory->run(['ls', '-la']);
        $this->assertSame('drwxr-xr-x   25 nunomaduro', $result->output());

        $result = $this->factory->run('ls -laaaaa');
        $this->assertSame('drwxr-xr-x   25 taylorotwell', $result->output());

        $result = $this->factory->run('ls');
        $this->assertSame('drwxr-xr-x   25 root', $result->output());
    }

    public function testResultOutputWhenFakeDoesNotExist()
    {
        $this->factory->fake([
            'ls -la ' => 'drwxr-xr-x   25 nunomaduro',
        ]);

        $result = $this->factory->run(['ls', '-la']);
        $this->assertSame('drwxr-xr-x   25 nunomaduro', $result->output());

        $result = $this->factory->path(__DIR__)->run('ls');
        $this->assertStringContainsString('ProcessTest', $result->output());
        $this->assertTrue($result->ok());

        $this->factory->fake();

        $result = $this->factory->run(['ls', '-la']);
        $this->assertSame('drwxr-xr-x   25 nunomaduro', $result->output());

        $result = $this->factory->path(__DIR__)->run('ls');
        $this->assertStringContainsString('', $result->output());
        $this->assertTrue($result->ok());
    }

    public function testProcessExceptionIsThrownIfTheProcessFails()
    {
        $exception = null;
        $result = $this->factory::result('my-failure', 1);

        $this->factory->fake(fn () => $result);

        try {
            $this->factory->run('ls')->throw();
        } catch (ProcessFailedException $exception) {
            // ..
        }

        $this->assertInstanceOf(ProcessFailedException::class, $exception);

        $this->assertSame($result, $exception->result());
        $this->assertStringContainsString('ls', $exception->process()->getCommandLine());
        $this->assertNull($exception->getPrevious());
    }

    public function testResultEnsuresTheProcessStarts()
    {
        $this->expectException(ProcessNotStartedException::class);
        $this->expectExceptionMessage("The process \"'ls'\" failed to start.");

        new ProcessResult(new Process(['ls']));
    }

    public function testFakeResultEnsuresTheProcessStarts()
    {
        $this->expectException(ProcessNotStartedException::class);
        $this->expectExceptionMessage('The process failed to start.');

        tap(new FakeProcessResult('foo', 0))->wait();
    }

    public function testWait()
    {
        $result = $this->factory->path(__DIR__)->run('ls');
        $this->assertStringContainsString('ProcessTest', $result->wait()->output());
        $this->assertTrue($result->ok());

        $this->factory->fake();

        $result = $this->factory->path(__DIR__)->run('ls');
        $this->assertStringContainsString('', $result->wait()->output());
        $this->assertTrue($result->ok());
    }

    public function testDoesNotThrow()
    {
        $result = $this->factory->path(__DIR__)->run('ls')->throw();
        $this->assertStringContainsString('ProcessTest', $result->wait()->output());
        $this->assertTrue($result->ok());

        $this->factory->fake();

        $result = $this->factory->path(__DIR__)->run('ls')->throw();
        $this->assertStringContainsString('', $result->wait()->output());
        $this->assertTrue($result->ok());
    }

    public function testTimeout()
    {
        $exception = null;

        $result = $this->factory->path(__DIR__)->timeout(0.1)->run('sleep 1');

        $this->assertSame(0.1, $result->process()->getTimeout());

        try {
            $result->wait();
        } catch (ProcessTimedOutException $exception) {
            // ..
        }

        $this->assertInstanceOf(ProcessTimedOutException::class, $exception);
        $this->assertTrue($exception->result()->failed());
        $this->assertSame(143, $exception->result()->exitCode());
    }

    public function testSignals()
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('Test requires pcntl extension.');
        }

        $result = $this->factory->path(__DIR__)->run('sleep 5');
        $result->process()->signal(SIGKILL);

        $this->assertTrue($result->failed());
        $this->assertSame(128 + SIGKILL, $result->exitCode());
    }

    public function testPath()
    {
        $this->factory->fake();

        $result = $this->factory->path(__DIR__)->run('ls');

        $this->assertSame(__DIR__, $result->process()->getWorkingDirectory());
    }

    public function testForever()
    {
        $this->factory->fake();

        $result = $this->factory->forever()->run('ls');

        $this->assertNull($result->process()->getTimeout());
    }
}