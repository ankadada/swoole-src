--TEST--
swoole_server: task ipc mode
--SKIPIF--
<?php require __DIR__ . '/../include/skipif.inc'; ?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';
$pm = new ProcessManager;
$pm->parentFunc = function ($pid) use ($pm) {
    echo curlGet("http://127.0.0.1:{$pm->getFreePort()}");
    $pm->kill();
};
$pm->childFunc = function () use ($pm) {
    $server = new swoole_http_server('127.0.0.1', $pm->getFreePort(), mt_rand(0, 1) ? SWOOLE_BASE : SWOOLE_PROCESS);
    $server->set([
        'log_file' => '/dev/null',
        'open_tcp_nodelay' => true,
        'task_worker_num' => 4,
        'task_ipc_mode' => 2,
        'dispatch_mode' => 2
    ]);
    $server->on('workerStart', function () use ($pm) {
        $pm->wakeup();
    });
    $server->on('request', function (swoole_http_request $request, swoole_http_response $response) use ($server) {
        $response->detach();
        $server->task($response->fd);
    });
    $server->on('task', function ($server, $task_id, $worker_id, string $fd) {
        $response = swoole_http_response::create($fd);
        $response->end("Hello Swoole!\n");
    });
    $server->on('finish', function () { });
    $server->on('close', function () { });
    $server->start();
};
$pm->childFirst();
$pm->run();
?>
--EXPECT--
Hello Swoole!
