<?php
namespace App\Plugin;
interface SlotProviderInterface { public function pluginName(): string; public function supports(string $slot): bool; public function render(string $slot, array $context = []): string; }
