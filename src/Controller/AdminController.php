<?php

namespace App\Controller;

use App\Infrastructure\AdminRepository;
use App\Infrastructure\Database;
use App\Infrastructure\PluginRegistry;
use App\Infrastructure\Schema;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use App\Plugin\PluginLifecycleManager;
use App\Plugin\PluginPackageException;
use App\Plugin\PluginPackageInstaller;
use App\Plugin\Migration\MigrationManager;

final class AdminController
{
    public function __construct(private readonly Environment $view)
    {
    }

    #[Route('/admin/plugins', name: 'admin_plugins', methods: ['GET', 'POST'])]
    public function plugins(Request $request, PluginRegistry $registry, PluginLifecycleManager $lifecycle, PluginPackageInstaller $installer, MigrationManager $migrations): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        if ($request->isMethod('POST')) {
            if (!$this->validToken($request)) { $request->getSession()->set('plugin_error','Die Sitzung ist abgelaufen.'); return new RedirectResponse('/admin/plugins'); }
            try {
                $action=(string)$request->request->get('action','toggle');
                if ($action==='install') {
                    $file=$request->files->get('plugin_package'); if(!$file) throw new PluginPackageException('Bitte wähle eine ZIP-Datei aus.');
                    $plugin=$installer->install($file); $request->getSession()->set('plugin_message',$plugin->label.' wurde installiert und kann jetzt aktiviert werden.');
                } else {
                    $name=(string)$request->request->get('plugin'); $plugin=$registry->manifest($name); if(!$plugin) throw new PluginPackageException('Plugin wurde nicht gefunden.');
                    if ($action==='uninstall') { $installer->uninstall($plugin); $request->getSession()->set('plugin_message',$plugin->label.' wurde vollständig deinstalliert.'); }
                    elseif ($name!=='core-administration') { $active=$registry->toggle($name); if($active){$migrations->migrate($plugin);$lifecycle->activate($name);}else{$lifecycle->deactivate($name);} $request->getSession()->set('plugin_message',$plugin->label.($active?' wurde aktiviert.':' wurde deaktiviert.')); }
                }
            } catch (\Throwable $e) { $request->getSession()->set('plugin_error',$e->getMessage()); }
            return new RedirectResponse('/admin/plugins');
        }
        $message=$request->getSession()->remove('plugin_message'); $error=$request->getSession()->remove('plugin_error');
        return $this->page($request, 'plugins', 'Pluginmanager', ['plugins' => $registry->all(),'message'=>$message,'error'=>$error]);
    }

    #[Route('/admin/profile', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, AdminRepository $admins): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        $admin = $request->getSession()->get('admin');
        $saved = false;
        if ($request->isMethod('POST') && $this->validToken($request)) {
            $admin['display_name'] = trim((string) $request->request->get('display_name'));
            $admin['email'] = trim((string) $request->request->get('email')) ?: null;
            $admins->updateProfile((int) $admin['id'], $admin['display_name'], $admin['email']);
            $request->getSession()->set('admin', $admin);
            $saved = true;
        }
        return $this->page($request, 'profile', 'Benutzerprofil', ['saved' => $saved]);
    }

    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, Database $database, Schema $schema): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        $schema->ensure();
        $saved = false;
        if ($request->isMethod('POST') && $this->validToken($request)) {
            $statement = $database->connection()->prepare('INSERT INTO system_config (config_key, config_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
            $statement->execute(['key' => 'app.name', 'value' => trim((string) $request->request->get('app_name'))]);
            $saved = true;
        }
        $statement = $database->connection()->query("SELECT config_value FROM system_config WHERE config_key = 'app.name'");
        return $this->page($request, 'settings', 'Einstellungen', ['appName' => $statement->fetchColumn() ?: 'Companyware', 'saved' => $saved]);
    }

    private function page(Request $request, string $template, string $title, array $context = []): Response
    {
        $token = bin2hex(random_bytes(24));
        $request->getSession()->set('admin_token', $token);
        $request->getSession()->set('logout_token', $token);
        return new Response($this->view->render('admin/'.$template.'/index.html.twig', array_merge($context, ['title' => $title, 'active' => $template, 'admin' => $request->getSession()->get('admin'), 'token' => $token])));
    }

    private function requireLogin(Request $request): ?Response
    {
        return $request->getSession()->has('admin') ? null : new RedirectResponse('/login');
    }

    private function validToken(Request $request): bool
    {
        return hash_equals((string) $request->getSession()->get('admin_token'), (string) $request->request->get('_token'));
    }
}
