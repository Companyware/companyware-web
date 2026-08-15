<?php
namespace Plugins\Documents;
use App\Plugin\SlotProviderInterface;
final class DocumentsSlots implements SlotProviderInterface
{
    public function pluginName(): string{return 'documents';}
    public function supports(string $slot): bool{return $slot==='document.stylesheets';}
    public function render(string $slot,array $context=[]): string{return '<link rel="stylesheet" href="/plugin-assets/documents/documents.css">';}
}
