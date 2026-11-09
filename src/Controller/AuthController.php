<?php

namespace App\Controller;

use App\Infrastructure\AdminRepository;
use App\Plugin\FrontendRegistry;
use App\Security\AccountRecoveryService;
use App\Security\PasswordPolicy;
use App\Security\AccountEmailVerificationService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use Twig\Environment;

final class AuthController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(Request $request, FrontendRegistry $frontends): Response
    {
        return $frontends->render($request) ?? new RedirectResponse('/admin');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AdminRepository $admins, Environment $view, TranslatorInterface $translator): Response
    {
        if ($request->getSession()->has('admin')) {
            return new RedirectResponse('/admin');
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
                        return new RedirectResponse('/admin');
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

    #[Route('/account-recovery', name: 'app_account_recovery', methods: ['GET', 'POST'])]
    public function recovery(Request $request, AccountRecoveryService $recovery, Environment $view): Response
    {
        if ($request->getSession()->has('admin')) return new RedirectResponse('/admin');
        $completed = null;
        $submitted = false;
        $mailRequested = false;
        if ($request->isMethod('POST')) {
            $submitted = true;
            $validToken = hash_equals((string) $request->getSession()->get('recovery_token'), (string) $request->request->get('_token'));
            if ($validToken && $request->request->get('action') === 'request_email') {
                try { $recovery->requestByEmail((string) $request->request->get('email'), $request->getSchemeAndHttpHost().'/account-recovery', $this->fingerprint($request)); } catch (Throwable) {}
                $mailRequested = true;
                $submitted = false;
            }
            $password = (string) $request->request->get('password');
            if (!$mailRequested && $validToken && hash_equals($password, (string) $request->request->get('password_confirmation'))) {
                try {
                    $completed = $recovery->consume((string) $request->request->get('recovery_key'), $password, $this->fingerprint($request));
                } catch (Throwable) {
                    $completed = null;
                }
            }
        }
        $token = bin2hex(random_bytes(24));
        $request->getSession()->set('recovery_token', $token);
        return new Response($view->render('login/recovery.html.twig', ['token' => $token, 'submitted' => $submitted, 'mailRequested' => $mailRequested, 'completed' => $completed, 'recoveryKey' => (string) $request->query->get('key'), 'minimumPasswordLength' => PasswordPolicy::MIN_LENGTH]));
    }

    #[Route('/api/v1/account-recovery', name: 'api_account_recovery', methods: ['POST'])]
    public function recoveryApi(Request $request, AccountRecoveryService $recovery): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];
        try {
            $completed = $recovery->consume((string) ($payload['recoveryKey'] ?? ''), (string) ($payload['newPassword'] ?? ''), $this->fingerprint($request));
        } catch (Throwable) {
            $completed = null;
        }
        if ($completed === null) return new JsonResponse(['message' => 'Recovery could not be completed.'], 400);
        return new JsonResponse(['message' => 'Recovery completed.', 'username' => $completed['username']]);
    }

    #[Route('/email-verification', name: 'app_email_verification', methods: ['GET'])]
    public function verifyEmail(Request $request, AccountEmailVerificationService $verification): Response
    {
        try { $verified = $verification->verify((string) $request->query->get('token')); } catch (Throwable) { $verified = false; }
        $request->getSession()->set('email_verification_result', $verified);
        return new RedirectResponse($request->getSession()->has('admin') ? '/admin/profile' : '/login');
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', (string) $request->getClientIp().'|'.substr((string) $request->headers->get('User-Agent'), 0, 255));
    }
}
