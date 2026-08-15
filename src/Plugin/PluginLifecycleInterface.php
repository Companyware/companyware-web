<?php
namespace App\Plugin;
interface PluginLifecycleInterface { public function install(): void; public function activate(): void; public function deactivate(): void; public function uninstall(): void; }
