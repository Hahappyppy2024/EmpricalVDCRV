<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\DomainException;
use Shop\Service\CartService;
use Shop\Service\CatalogService;
use Shop\Service\ReviewService;
use Shop\View;

final class PageController extends Controller
{
    public function __construct(
        private View $view,
        private CatalogService $catalog,
        private CartService $cart,
        private ReviewService $reviews
    ) {
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user($request);
        return $this->html($response, $this->view->render('home', [
            'products' => $this->catalog->featured(),
            'categories' => $this->catalog->categories(),
            'cartCount' => $user !== null ? $this->cart->count($user) : 0,
        ]));
    }

    public function catalog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $filters = $this->query($request);
        $user = $this->user($request);
        return $this->html($response, $this->view->render('catalog', [
            'products' => $this->catalog->search($filters),
            'categories' => $this->catalog->categories(),
            'filters' => $filters,
            'cartCount' => $user !== null ? $this->cart->count($user) : 0,
        ]));
    }

    public function product(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->param($args, 'id');
        $product = $this->catalog->byId($id);
        if ($product === null) {
            throw new DomainException('Product not found or unavailable.', 404);
        }
        $user = $this->user($request);
        return $this->html($response, $this->view->render('product', [
            'product' => $product,
            'reviews' => $this->catalog->productReviews($id),
            'reviewSummary' => $this->catalog->reviewSummary($id),
            'categories' => $this->catalog->categories(),
            'cartCount' => $user !== null ? $this->cart->count($user) : 0,
        ]));
    }

    public function submitReview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireUser($request);
        $data = $this->body($request);
        try {
            $this->reviews->create($user, (int) ($data['product_id'] ?? 0), $data);
            return $this->redirect($response, '/products/' . (int) ($data['product_id'] ?? 0) . flash_query(null, 'Review submitted for moderation.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/products/' . (int) ($data['product_id'] ?? 0) . flash_query($e->getMessage()));
        }
    }
}
