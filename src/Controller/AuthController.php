<?php

namespace App\Controller;

use App\Infrastructure\AdminRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use Twig\Environment;

final class AuthController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        return new RedirectResponse($request->getSession()->has('admin') ? '/admin/plugins' : '/login');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AdminRepository $admins, Environment $view, TranslatorInterface $translator): Response
    {
        if ($request->getSession()->has('admin')) {
            return new RedirectResponse('/admin/plugins');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            if (!hash_equals((string) $request->getSession()->get('login_token'), (string) $request->request->get('_token'))) {
                $error = $translator->trans('login.error.expired');
            } else {
                try {
                    $admin = $admins->findByUsername(trim((string) $request->request->get('username')));
                    if ($admin && password_verify((string) $request->request->get('password'), $admin['password_hash'])) {
                        unset($admin['password_hash']);
                        $request->getSession()->migrate(true);
                        $request->getSession()->set('admin', $admin);
                        return new RedirectResponse('/admin/plugins');
                    }
                    $error = $translator->trans('login.error.invalid');
                } catch (Throwable) {
                    $error = $translator->trans('login.error.database');
                }
            }
        }

        $token = bin2hex(random_bytes(24));
        $request->getSession()->set('login_token', $token);
        return new Response($view->render('login/index.html.twig', ['error' => $error, 'token' => $token]));
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (hash_equals((string) $request->getSession()->get('logout_token'), (string) $request->request->get('_token'))) {
            $request->getSession()->invalidate();
        }
        return new RedirectResponse('/login');
    }
}
