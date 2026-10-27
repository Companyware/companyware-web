<?php

namespace Plugins\Documents;

use Companyware\Core\Framework\Plugin\Contract\SlotProviderInterface;
use Companyware\Core\Framework\Security\PrivilegeAwareInterface;
use Twig\Environment;

final class DocumentsDashboardProvider implements SlotProviderInterface, PrivilegeAwareInterface
{
    public function __construct(
        private readonly Environment $view,
        private readonly DocumentStorage $storage,
    ) {
    }

    public function pluginName(): string
    {
        return 'documents';
    }

    public function requiredPrivilege(string $extensionPoint): string { return $extensionPoint === 'dashboard.quick_actions' ? 'documents.create' : 'documents.read'; }

    public function supports(string $slot): bool
    {
        return in_array($slot, ['dashboard.quick_actions', 'dashboard.widgets', 'dashboard.continue', 'dashboard.activity'], true);
    }

    public function render(string $slot, array $context = []): string
    {
        if ($slot === 'dashboard.quick_actions') {
            return $this->view->render('@Documents/dashboard/quick-action.html.twig');
        }

        $documents = $this->storage->all();

        if ($slot === 'dashboard.continue') {
            return $documents === [] ? '' : $this->view->render('@Documents/dashboard/continue.html.twig', [
                'document' => $documents[0],
            ]);
        }

        if ($slot === 'dashboard.activity') {
            return $documents === [] ? '' : $this->view->render('@Documents/dashboard/activity.html.twig', [
                'documents' => array_slice($documents, 0, 5),
            ]);
        }

        return $this->view->render('@Documents/dashboard/widget.html.twig', [
            'documentCount' => count($documents),
        ]);
    }
}
