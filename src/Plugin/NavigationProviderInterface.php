<?php
namespace App\Plugin;
interface NavigationProviderInterface { public function pluginName(): string; public function items(): iterable; }
