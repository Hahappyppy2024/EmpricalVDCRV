<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Repositories\TeamRepository;
use CloudFS\Repositories\UserRepository;
use CloudFS\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class TeamSpacesController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private TeamRepository $teams,
        private FileRepository $files,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $teams = $this->teams->listForUser((int) $user['id']);
        return $this->view->render($response, 'teams.php', ['current_user' => $user, 'teams' => $teams]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $teams = $this->teams->listForUser((int) $user['id']);
        foreach ($teams as &$team) {
            $team['members'] = $this->teams->members((int) $team['id']);
            $team['folders'] = $this->teams->folders((int) $team['id']);
            $folderIds = array_map(fn($f) => (int) $f['folder_id'], $team['folders']);
            $team['files'] = $this->teams->filesInFolders($folderIds);
        }
        unset($team);
        return $this->json($response, ['ok' => true, 'teams' => $teams]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $action = (string) ($data['action'] ?? 'create');
        if ($action === 'add_member') {
            if (($user['role'] ?? 'user') !== 'admin') {
                return $this->json($response, ['ok' => false, 'errors' => ['Admin privileges are required to add team members.']], 403);
            }
            $teamId = (int) ($data['team_id'] ?? 0);
            $memberName = trim((string) ($data['username'] ?? ''));
            $role = (string) ($data['role'] ?? 'member');
            $target = $this->users->findByUsername($memberName);
            if (!$target) {
                return $this->json($response, ['ok' => false, 'errors' => ['User not found.']], 404);
            }
            if (!in_array($role, ['owner', 'member'], true)) {
                return $this->json($response, ['ok' => false, 'errors' => ['Role must be owner or member.']], 422);
            }
            $result = $this->teams->addMember($teamId, (int) $target['id'], $role);
            if (!$result['ok']) {
                return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
            }
            $this->audit->log((int) $user['id'], 'team.member_added', 'team', (string) $teamId, ['member' => $target['username'], 'role' => $role]);
            return $this->json($response, ['ok' => true, 'message' => 'Team member added.']);
        }
        if ($action === 'add_folder') {
            $teamId = (int) ($data['team_id'] ?? 0);
            $folderId = (int) ($data['folder_id'] ?? 0);
            if (!$this->teams->membership($teamId, (int) $user['id'])) {
                return $this->json($response, ['ok' => false, 'errors' => ['You are not a member of this team.']], 403);
            }
            $result = $this->teams->addFolder($teamId, $folderId);
            $this->audit->log((int) $user['id'], 'team.folder_added', 'team', (string) $teamId, ['folder' => $folderId]);
            return $this->json($response, ['ok' => true, 'message' => 'Folder added to team space.'], $result['ok'] ? 201 : 422);
        }
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($name === '') {
            return $this->json($response, ['ok' => false, 'errors' => ['Team name is required.']], 422);
        }
        $result = $this->teams->create($name, $description, (int) $user['id']);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
        }
        $this->audit->log((int) $user['id'], 'team.created', 'team', (string) $result['id'], ['name' => $name]);
        return $this->json($response, ['ok' => true, 'id' => $result['id'], 'message' => 'Team space created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $teamId = (int) $args['id'];
        $membership = $this->teams->membership($teamId, (int) $user['id']);
        if (!$membership && ($user['role'] ?? 'user') !== 'admin') {
            return $this->json($response, ['ok' => false, 'errors' => ['Team not found or you are not a member.']], 403);
        }
        $data = $this->parseBody($request);
        $action = (string) ($data['action'] ?? 'update');
        if ($action === 'remove_member') {
            $memberName = trim((string) ($data['username'] ?? ''));
            $target = $this->users->findByUsername($memberName);
            if (!$target) {
                return $this->json($response, ['ok' => false, 'errors' => ['User not found.']], 404);
            }
            $result = $this->teams->removeMember($teamId, (int) $target['id']);
            if (!$result['ok']) {
                return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
            }
            $this->audit->log((int) $user['id'], 'team.member_removed', 'team', (string) $teamId, ['member' => $target['username']]);
            return $this->json($response, ['ok' => true, 'message' => 'Team member removed.']);
        }
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($name === '') {
            return $this->json($response, ['ok' => false, 'errors' => ['Team name is required.']], 422);
        }
        $result = $this->teams->update($teamId, $name, $description);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
        }
        $this->audit->log((int) $user['id'], 'team.updated', 'team', (string) $teamId, []);
        return $this->json($response, ['ok' => true, 'message' => 'Team space updated.']);
    }
}
