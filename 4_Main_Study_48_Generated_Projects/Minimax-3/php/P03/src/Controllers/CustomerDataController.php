<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\CustomerDataRepository;
use Shop\Models\UserRepository;
use Shop\Models\AuditRepository;

final class CustomerDataController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        return $this->render($response, 'customer_data.php', [
            'page_title' => 'Customer data',
            'profile_user' => $user,
            'addresses' => CustomerDataRepository::addresses((int)$user['id']),
            'preferences' => CustomerDataRepository::ensurePreferences((int)$user['id']),
        ]);
    }

    public function preferences(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/customer/data');
        }
        CustomerDataRepository::upsertPreferences((int)$user['id'], $this->allInput($request));
        $name = trim((string)$this->input($request, 'display_name'));
        if ($name !== '' && $name !== $user['display_name']) {
            UserRepository::updateProfile((int)$user['id'], $name);
        }
        AuditRepository::log((int)$user['id'], 'customer_preferences', 'customer', (string)$user['id']);
        SessionManager::flash('success', 'Profile saved.');
        return $this->redirect($response, '/customer/data');
    }

    public function addAddress(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/customer/data');
        }
        foreach (['full_name', 'line1', 'city', 'postal_code'] as $f) {
            if (trim((string)$this->input($request, $f)) === '') {
                SessionManager::flash('error', 'Address is missing required fields.');
                return $this->redirect($response, '/customer/data');
            }
        }
        CustomerDataRepository::addAddress((int)$user['id'], $this->allInput($request));
        AuditRepository::log((int)$user['id'], 'customer_address_add', 'customer', (string)$user['id']);
        SessionManager::flash('success', 'Address added.');
        return $this->redirect($response, '/customer/data');
    }

    public function deleteAddress(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/customer/data');
        }
        $id = (int)($args['id'] ?? 0);
        if (!CustomerDataRepository::deleteAddress((int)$user['id'], $id)) {
            SessionManager::flash('error', 'Address not found.');
            return $this->redirect($response, '/customer/data');
        }
        AuditRepository::log((int)$user['id'], 'customer_address_delete', 'address', (string)$id);
        SessionManager::flash('success', 'Address deleted.');
        return $this->redirect($response, '/customer/data');
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        return $this->json($response, [
            'addresses' => CustomerDataRepository::addresses((int)$user['id']),
            'preferences' => CustomerDataRepository::ensurePreferences((int)$user['id']),
        ]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        $data = $this->jsonBody($request);
        foreach (['full_name', 'line1', 'city', 'postal_code'] as $f) {
            if (empty($data[$f])) {
                return $this->json($response, ['error' => 'missing_field', 'field' => $f], 400);
            }
        }
        $id = CustomerDataRepository::addAddress((int)$user['id'], $data);
        return $this->json($response, ['address_id' => $id], 201);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        CustomerDataRepository::upsertPreferences((int)$user['id'], $this->jsonBody($request));
        return $this->json($response, ['preferences' => CustomerDataRepository::ensurePreferences((int)$user['id'])]);
    }
}