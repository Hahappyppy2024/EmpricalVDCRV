<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HomeController extends BaseController
{
    public function index(Request $request, Response $response): Response
    {
        $products = \Shop\Models\ProductRepository::search([
            'q' => '',
            'category' => null,
            'in_stock' => '1',
        ]);
        $categories = \Shop\Models\ProductRepository::categories();
        return $this->render($response, 'home.php', [
            'page_title' => 'Home',
            'products' => array_slice($products, 0, 8),
            'categories' => $categories,
        ]);
    }
}