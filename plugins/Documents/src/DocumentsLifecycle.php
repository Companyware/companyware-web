<?php
namespace Plugins\Documents;
use Companyware\Core\Framework\Plugin\Plugin;
use Symfony\Component\Filesystem\Filesystem;
final class DocumentsLifecycle extends Plugin
{
    public function __construct(private readonly string $projectDir){}
    public function install(): void{} public function activate(): void{} public function deactivate(): void{}
    public function uninstall(): void{(new Filesystem())->remove($this->projectDir.'/var/documents');}
}
