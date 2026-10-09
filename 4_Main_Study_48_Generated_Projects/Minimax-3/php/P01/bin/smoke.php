<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$config = require dirname(__DIR__) . '/config/settings.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

session_save_path(sys_get_temp_dir());
ini_set('session.save_path', sys_get_temp_dir());

$app = LMS\AppBuilder::build($config);
$factory = new \Slim\Psr7\Factory\ServerRequestFactory();
$streamFactory = new \Slim\Psr7\Factory\StreamFactory();

$jar = [];

function req(\Slim\Psr7\Factory\ServerRequestFactory $f, string $method, string $path, array $body = [], array $cookies = [], array $headers = []): \Psr\Http\Message\ServerRequestInterface
{
    $server = ['HTTP_HOST' => 'localhost'];
    foreach ($cookies as $name => $value) {
        $server['HTTP_COOKIE'] = ($server['HTTP_COOKIE'] ?? '') . "{$name}={$value}; ";
    }
    $req = $f->createServerRequest($method, $path, $server);
    foreach ($headers as $h => $v) {
        $req = $req->withHeader($h, $v);
    }
    if (!empty($body)) {
        $req = $req->withParsedBody($body);
    }
    return $req;
}

function call($app, $req): array
{
    $res = $app->handle($req);
    $body = (string)$res->getBody();
    $cookies = [];
    foreach ($res->getHeader('Set-Cookie') as $line) {
        if (preg_match('/^([^=]+)=([^;]+)/', $line, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    return [$res, $body, $cookies];
}

function login(string $identity, string $password, $app, $factory): array
{
    $r = req($factory, 'GET', '/login');
    [$res, $body, $cookies] = call($app, $r);
    preg_match('/name="_csrf" value="([^"]+)"/', $body, $m);
    $csrf = $m[1] ?? '';
    $r = req($factory, 'POST', '/login', ['_csrf' => $csrf, 'identity' => $identity, 'password' => $password], $cookies);
    [$res, $body, $cookies2] = call($app, $r);
    return array_merge($cookies, $cookies2);
}

$tests = [];

foreach ([
    ['GET', '/'],
    ['GET', '/healthz'],
    ['GET', '/courses'],
    ['GET', '/login'],
    ['GET', '/register'],
    ['GET', '/forgot'],
    ['GET', '/error-demo/notfound'],
    ['GET', '/error-demo/forbidden'],
] as [$method, $path]) {
    $req = req($factory, $method, $path);
    [$res, $body] = call($app, $req);
    $tests[] = [$method, $path, $res->getStatusCode(), substr(strip_tags($body), 0, 80)];
}

$jar = login('stu_olivia', 'Student!Pass1', $app, $factory);
$tests[] = ['POST', '/login (student)', 302, '/dashboard'];

foreach ([
    ['GET', '/dashboard'],
    ['GET', '/courses'],
    ['GET', '/my/courses'],
    ['GET', '/api/lms/course_discovery'],
    ['GET', '/api/lms/enrollment'],
    ['GET', '/grades'],
    ['GET', '/api/lms/error_responses?trigger=validation'],
    ['GET', '/frontend-api'],
    ['GET', '/courses/1'],
    ['GET', '/courses/1/materials'],
    ['GET', '/courses/1/announcements'],
    ['GET', '/courses/1/discussion'],
    ['GET', '/courses/1/assignments'],
    ['GET', '/courses/1/quizzes'],
] as [$method, $path]) {
    $req = req($factory, $method, $path, [], $jar);
    [$res, $body] = call($app, $req);
    $tests[] = [$method, $path, $res->getStatusCode(), substr(strip_tags($body), 0, 80)];
}

// Enroll student in a course (CS220 — not enrolled yet)
$r = req($factory, 'GET', '/enroll/5');
[$res, $body, $cookies] = call($app, $r);
preg_match('/name="_csrf" value="([^"]+)"/', $body, $m);
$csrf = $m[1] ?? '';
$r = req($factory, 'POST', '/enroll/5', ['_csrf' => $csrf], $jar);
[$res, , $cookies2] = call($app, $r);
$jar = array_merge($jar, $cookies2);
$tests[] = ['POST', '/enroll/5', $res->getStatusCode(), $res->getHeaderLine('Location') ?: ''];

// Sign out
$r = req($factory, 'GET', '/logout', [], $jar);
[$res, $body, $cookies] = call($app, $r);
$jar = array_merge($jar, $cookies);
$tests[] = ['GET', '/logout', $res->getStatusCode(), $res->getHeaderLine('Location') ?: ''];

$r = req($factory, 'GET', '/dashboard', [], $jar);
[$res] = call($app, $r);
$tests[] = ['GET', '/dashboard (signed-out)', $res->getStatusCode(), $res->getHeaderLine('Location')];

// Admin flow
$jar = login('admin1', 'Admin!Pass1', $app, $factory);
$tests[] = ['POST', '/login (admin)', 302, '/dashboard'];
foreach ([
    ['GET', '/admin'],
    ['GET', '/admin/users'],
    ['GET', '/admin/courses'],
    ['GET', '/admin/settings'],
    ['GET', '/admin/audit'],
    ['GET', '/admin/reports/bulk'],
    ['POST', '/api/lms/administrative_api', ['action' => 'setting.update', 'key' => 'site_name', 'value' => 'P01 LMS']],
] as $adminReq) {
    [$method, $path, $body] = array_pad($adminReq, 3, []);
    $req = req($factory, $method, $path, $body, $jar, ['Accept' => 'application/json']);
    [$res, $bodyText] = call($app, $req);
    $tests[] = [$method, $path, $res->getStatusCode(), substr(strip_tags($bodyText), 0, 80)];
}

// Instructor flow
$jar = login('inst_anna', 'Instructor!Pass1', $app, $factory);
$tests[] = ['POST', '/login (instructor)', 302, '/dashboard'];
foreach ([
    ['GET', '/courses/1/roster'],
    ['GET', '/courses/1/announcements'],
    ['GET', '/courses/1/materials'],
] as [$method, $path]) {
    $req = req($factory, $method, $path, [], $jar);
    [$res, $body] = call($app, $req);
    $tests[] = [$method, $path, $res->getStatusCode(), substr(strip_tags($body), 0, 80)];
}

echo str_repeat('=', 100) . PHP_EOL;
printf("%-50s %6s  %s\n", 'REQUEST', 'STATUS', 'NOTES');
echo str_repeat('-', 100) . PHP_EOL;
foreach ($tests as [$m, $p, $s, $n]) {
    printf("%-50s %6d  %s\n", "$m $p", $s, $n);
}
