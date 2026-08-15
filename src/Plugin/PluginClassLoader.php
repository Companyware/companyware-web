<?php
namespace App\Plugin;
final class PluginClassLoader
{
    private static bool $registered=false;
    public static function register(string $root): void
    {
        if (self::$registered) return;
        spl_autoload_register(static function(string $class) use ($root): void {
            if (!str_starts_with($class,'Plugins\\')) return;
            $relative=substr($class,strlen('Plugins\\'));
            [$plugin,$classPath]=array_pad(explode('\\',$relative,2),2,'');
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/',$plugin) || $classPath==='') return;
            $file=$root.'/'.$plugin.'/src/'.str_replace('\\','/',$classPath).'.php';
            if (is_file($file)) require $file;
        }, true, true);
        self::$registered=true;
    }
}
