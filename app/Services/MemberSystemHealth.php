<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/** Read-only deployment checks: never alters schema during a web request. */
class MemberSystemHealth
{
    public const REQUIRED = [
        'members' => ['id','uuid','user_id','member_no','id_card','prefix','first_name','last_name','department','phone','email','address','status','updated_at'],
        'users' => ['id','uuid','name','username','email','password','status','deleted_at','updated_at'],
        'roles' => ['id','slug'],
        'user_roles' => ['id','user_id','role_id'],
        'member_dividends' => ['id','member_id','year','details_json','imported_at'],
        'member_import_runs' => ['id','user_id','year','source_name','record_count','created_count','updated_count','unchanged_count','net_total','created_at'],
        'member_data_changes' => ['id','member_id','user_id','action','reason','before_json','after_json','created_at'],
    ];

    public static function columns(): array
    {
        $rows = Database::query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
        $columns=[];
        foreach($rows as $row) $columns[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
        return $columns;
    }

    public static function inspect(): array
    {
        $columns=self::columns();$missing=[];
        foreach(self::REQUIRED as $table=>$required) {
            if(!isset($columns[$table])) { $missing[]=$table;continue; }
            foreach(array_diff($required,$columns[$table]) as $field) $missing[]=$table.'.'.$field;
        }
        $extensions=['pdo_mysql'=>extension_loaded('pdo_mysql'),'mbstring'=>extension_loaded('mbstring'),'zip'=>class_exists(\ZipArchive::class),'simplexml'=>function_exists('simplexml_load_string')];
        $role=isset($columns['roles']) && in_array('slug',$columns['roles'],true) && (bool)Database::value("SELECT COUNT(*) FROM roles WHERE slug='member_admin'");
        return ['ready'=>!$missing && !in_array(false,$extensions,true) && $role,'missing'=>$missing,'extensions'=>$extensions,'member_admin_role'=>$role];
    }

    public static function requireColumns(array $required, ?array $columns=null): void
    {
        $columns??=self::columns();$missing=[];
        foreach($required as $table=>$fields) {
            if(!isset($columns[$table])) { $missing[]=$table;continue; }
            foreach(array_diff($fields,$columns[$table]) as $field)$missing[]=$table.'.'.$field;
        }
        if($missing)throw new MemberSchemaException($missing);
    }

    public static function requireWrites(): void
    {
        self::requireColumns(self::REQUIRED);
    }
}
