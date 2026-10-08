<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Container\AppContainer;
use App\Services\WorkflowException;
use App\Services\Workflows\WorkflowInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class WorkflowController
{
    /**
     * @param array<string, array{service: class-string<WorkflowInterface>}> $workflows
     */
    public function __construct(
        private readonly AppContainer $container,
        private readonly array $workflows
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->run($request, $response, function (WorkflowInterface $service) use ($request) {
            return $service->listFor($request->getAttribute('user'), $request->getQueryParams());
        }, (string) $args['module']);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->run($request, $response, function (WorkflowInterface $service) use ($request, $args) {
            return $service->show($request->getAttribute('user'), (int) $args['id']);
        }, (string) $args['module']);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->run($request, $response, function (WorkflowInterface $service) use ($request) {
            $input = $request->getParsedBody() ?: [];
            if (is_array($input) && $request->getUploadedFiles() !== []) {
                $input['uploaded_files'] = $request->getUploadedFiles();
            }
            if (is_array($input)) {
                return $service->create($request->getAttribute('user'), $input);
            }
            throw new WorkflowException('validation_error', 422, [], 'Invalid request body.');
        }, (string) $args['module'], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->run($request, $response, function (WorkflowInterface $service) use ($request, $args) {
            $input = $request->getParsedBody() ?: [];
            if (!is_array($input)) {
                throw new WorkflowException('validation_error', 422, [], 'Invalid request body.');
            }
            return $service->update($request->getAttribute('user'), (int) $args['id'], $input);
        }, (string) $args['module']);
    }

    public function downloadManuscript(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $user = $request->getAttribute('user');
            $service = $this->service('manuscript_access');
            $result = $service->create($user, [
                'submission_id' => (int) $args['id'],
                'access_type' => 'download',
            ]);
            return $this->streamFile($response, $result['file']['path'], $result['file']['name'], $result['file']['mime']);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    public function downloadExport(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $user = $request->getAttribute('user');
            $service = $this->service('bulk_exports');
            $file = $service->download($user, (int) $args['id']);
            return $this->streamFile($response, $file['path'], $file['name'], $file['mime']);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    private function run(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $fn,
        string $module,
        int $successStatus = 200
    ): ResponseInterface {
        try {
            $data = $fn($this->service($module));
            return $this->json($response, $successStatus, ['ok' => true, 'data' => $data]);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    private function service(string $module): WorkflowInterface
    {
        if (!isset($this->workflows[$module])) {
            throw new WorkflowException('not_found', 404, [], 'Unknown API module.');
        }
        $service = $this->container->get($this->workflows[$module]['service']);
        if (!$service instanceof WorkflowInterface) {
            throw new WorkflowException('internal_error', 500, [], 'Module service is misconfigured.');
        }
        return $service;
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response = $response->withStatus($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($payload));
        return $response;
    }

    private function error(ResponseInterface $response, WorkflowException $e): ResponseInterface
    {
        return $this->json($response, $e->status, [
            'ok' => false,
            'error' => [
                'code' => $e->errorCode,
                'status' => $e->status,
                'message' => $e->getMessage(),
                'details' => $e->details,
            ],
        ]);
    }

    private function streamFile(ResponseInterface $response, string $path, string $name, string $mime): ResponseInterface
    {
        $response = $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($name) . '"');
        $body = file_get_contents($path);
        if ($body === false) {
            throw new WorkflowException('not_found', 404, [], 'File is not available.');
        }
        $response->getBody()->write($body);
        return $response;
    }
}
