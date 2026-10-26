<?php

namespace App\Controller;

use App\Infrastructure\AdminRepository;
use App\Infrastructure\Database;
use App\Infrastructure\PluginRegistry;
use App\Infrastructure\Schema;
use App\Localization\LocaleRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use App\Plugin\PluginLifecycleManager;
use App\Plugin\PluginPackageException;
use App\Plugin\PluginPackageInstaller;
use App\Plugin\Compatibility\PluginCompatibilityResolver;
use App\Plugin\PluginConfiguration;
use App\Plugin\Migration\MigrationManager;
use Companyware\Core\Framework\Plugin\Event\PluginLifecycleOperation;
use App\Plugin\PluginEventBus;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminController
{
    public function __construct(private readonly Environment $view, private readonly TranslatorInterface $translator)
    {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, PluginRegistry $registry): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        $plugins = $registry->all();

        return $this->page($request, 'dashboard', 'dashboard.title', [
            'plugins' => $plugins,
            'activePluginCount' => count(array_filter($plugins, static fn (array $plugin): bool => (bool) $plugin['active'])),
        ]);
    }

    #[Route('/admin/plugins', name: 'admin_plugins', methods: ['GET', 'POST'])]
    public function plugins(Request $request, PluginRegistry $registry, PluginLifecycleManager $lifecycle, PluginPackageInstaller $installer, MigrationManager $migrations, PluginCompatibilityResolver $compatibility, PluginEventBus $events): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        if ($request->isMethod('POST')) {
            if (!$this->validToken($request)) { $request->getSession()->set('plugin_error',$this->translator->trans('plugins.message.expired')); return new RedirectResponse('/admin/plugins'); }
            try {
                $action=(string)$request->request->get('action','toggle');
                if ($action==='install') {
                    $file=$request->files->get('plugin_package'); if(!$file) throw new PluginPackageException($this->translator->trans('plugins.message.choose_zip'));
                    $plugin=$installer->install($file); $request->getSession()->set('plugin_message',$this->translator->trans('plugins.message.installed',['%plugin%'=>$this->translator->trans($plugin->label)]));
                } else {
                    $name=(string)$request->request->get('plugin'); $plugin=$registry->manifest($name); if(!$plugin) throw new PluginPackageException($this->translator->trans('plugins.message.not_found'));
                    if ($action==='uninstall') { $installer->uninstall($plugin); $request->getSession()->set('plugin_message',$this->translator->trans('plugins.message.uninstalled',['%plugin%'=>$this->translator->trans($plugin->label)])); }
                    elseif ($name!=='core-administration') {
                        $active=!$registry->isActive($name);
                        if($active){$compatibility->assertActivatable($plugin,$registry->manifests(),$registry->activeManifests());$events->before($name,PluginLifecycleOperation::Activate);$migrations->migrate($plugin);$lifecycle->activate($name);$registry->setActive($name,true);$events->after($name,PluginLifecycleOperation::Activate);}
                        else{$compatibility->assertDeactivatable($plugin,$registry->activeManifests());$events->before($name,PluginLifecycleOperation::Deactivate);$lifecycle->deactivate($name);$registry->setActive($name,false);$events->after($name,PluginLifecycleOperation::Deactivate);}
                        $request->getSession()->set('plugin_message',$this->translator->trans($active?'plugins.message.activated':'plugins.message.deactivated',['%plugin%'=>$this->translator->trans($plugin->label)]));
                    }
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $request->getSession()->set('plugin_error', str_starts_with($message, 'plugins.error.') ? $this->translator->trans($message) : $message);
            }
            return new RedirectResponse('/admin/plugins');
        }
        $message=$request->getSession()->remove('plugin_message'); $error=$request->getSession()->remove('plugin_error');
        return $this->page($request, 'plugins', 'plugins.title', ['plugins' => $registry->all(),'message'=>$message,'error'=>$error]);
    }

    #[Route('/admin/plugins/{name}/settings', name: 'admin_plugin_settings', requirements: ['name' => '[a-z][a-z0-9-]+'], methods: ['GET', 'POST'])]
    public function pluginSettings(string $name, Request $request, PluginRegistry $registry, PluginConfiguration $configuration): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        $plugin = $registry->manifest($name);
        if (!$plugin || $plugin->settings === []) return new Response($this->translator->trans('plugins.message.settings_unavailable'), 404);

        if ($request->isMethod('POST')) {
            if (!$this->validToken($request)) {
                $request->getSession()->set('plugin_settings_error', $this->translator->trans('plugins.message.expired'));
            } else {
                $configuration->save($plugin, $request->request->all('settings'));
                $request->getSession()->set('plugin_settings_message', $this->translator->trans('plugins.settings_saved'));
            }

            return new RedirectResponse('/admin/plugins/'.$plugin->name.'/settings');
        }

        return $this->page($request, 'plugin-settings', $plugin->label, [
            'plugin' => $plugin,
            'values' => $configuration->values($plugin),
            'message' => $request->getSession()->remove('plugin_settings_message'),
            'error' => $request->getSession()->remove('plugin_settings_error'),
        ]);
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
        return $this->page($request, 'profile', 'profile.title', ['saved' => $saved]);
    }

    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, Database $database, Schema $schema, LocaleRegistry $locales): Response
    {
        if ($redirect = $this->requireLogin($request)) return $redirect;
        $schema->ensure();
        $saved = false;
        if ($request->isMethod('POST') && $this->validToken($request)) {
            $statement = $database->connection()->prepare('INSERT INTO system_config (config_key, config_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
            $statement->execute(['key' => 'app.name', 'value' => trim((string) $request->request->get('app_name'))]);
            $locales->save((string) $request->request->get('locale'));
            $request->getSession()->set('settings_saved', true);
            return new RedirectResponse('/admin/settings');
        }
        $saved = (bool) $request->getSession()->remove('settings_saved');
        $statement = $database->connection()->query("SELECT config_value FROM system_config WHERE config_key = 'app.name'");
        return $this->page($request, 'settings', 'settings.title', ['appName' => $statement->fetchColumn() ?: 'Companyware', 'saved' => $saved, 'locales' => $locales->available(), 'currentLocale' => $locales->current()]);
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
