<?php

declare(strict_types=1);

namespace App\Theme;

use App\Infrastructure\Database;
use App\Infrastructure\Schema;
use Companyware\Core\Framework\Theme\ThemeConfigurationInterface;
use Companyware\Core\Framework\Theme\ThemeDefinition;

final class ThemeConfiguration implements ThemeConfigurationInterface
{
    public function __construct(private readonly Database $database, private readonly Schema $schema) {}

    public function values(ThemeDefinition $theme): array
    {
        $values=[];
        foreach($theme->settings as $field)$values[$field['key']]=$this->get($theme,$field['key'],$field['default']??null);
        return $values;
    }

    public function save(ThemeDefinition $theme,array $input):void
    {
        $this->schema->ensure();
        $statement=$this->database->connection()->prepare('INSERT INTO system_config (config_key,config_value) VALUES (:key,:value) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)');
        foreach($theme->settings as $field)$statement->execute(['key'=>$this->key($theme,$field['key']),'value'=>json_encode($this->normalize($field,$input[$field['key']]??null),JSON_THROW_ON_ERROR)]);
    }

    private function get(ThemeDefinition $theme,string $key,mixed $default):mixed
    {
        $this->schema->ensure();$statement=$this->database->connection()->prepare('SELECT config_value FROM system_config WHERE config_key=:key');$statement->execute(['key'=>$this->key($theme,$key)]);$value=$statement->fetchColumn();
        if(!is_string($value)&&$theme->id==='core-default'&&$key==='color_scheme'){$statement->execute(['key'=>'theme.color_scheme']);$value=$statement->fetchColumn();}
        return is_string($value)?json_decode($value,true,512,JSON_THROW_ON_ERROR):$default;
    }
    private function key(ThemeDefinition $theme,string $key):string{return 'theme.'.$theme->id.'.'.$key;}
    private function normalize(array $field,mixed $value):mixed{return match($field['type']){'checkbox'=>in_array($value,['1',1,true,'true','on'],true),'number'=>$this->number($field,$value),'select'=>$this->selection($field,$value),default=>substr(trim((string)$value),0,(int)($field['maxLength']??4000))};}
    private function number(array $field,mixed $value):int|float{$number=is_numeric($value)?(float)$value:(float)($field['default']??0);if(isset($field['min']))$number=max($number,(float)$field['min']);if(isset($field['max']))$number=min($number,(float)$field['max']);return floor($number)===$number?(int)$number:$number;}
    private function selection(array $field,mixed $value):string{$allowed=array_map(static fn(array $option):string=>(string)$option['value'],$field['options']);return in_array((string)$value,$allowed,true)?(string)$value:(string)($field['default']??$allowed[0]);}
}
