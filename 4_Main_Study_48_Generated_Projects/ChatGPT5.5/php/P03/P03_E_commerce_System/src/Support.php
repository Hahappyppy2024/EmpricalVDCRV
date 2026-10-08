<?php
declare(strict_types=1);
namespace App;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
trait Support
{
    private static function body(ServerRequestInterface $r): array { $v=$r->getParsedBody(); return is_array($v)?$v:[]; }
    private static function required(array $data,array $keys): void { $f=[]; foreach($keys as $k) if(!array_key_exists($k,$data)||$data[$k]===''||$data[$k]===null)$f[$k]='Required.'; if($f)throw new ApiException(422,'validation_failed','Required fields are missing.',$f); }
    private static function one(PDO $db,string $sql,array $params=[],string $code='resource_not_found'): array { $s=$db->prepare($sql);$s->execute($params);$x=$s->fetch();if(!$x)throw new ApiException(404,$code,'The requested resource was not found.');return$x; }
    private static function all(PDO $db,string $sql,array $params=[]): array { $s=$db->prepare($sql);$s->execute($params);return$s->fetchAll(); }
    private static function page(ServerRequestInterface $r,int $default=20): array { $q=$r->getQueryParams();$page=max(1,(int)($q['page']??1));$limit=max(1,min(100,(int)($q['limit']??$default)));return[$limit,($page-1)*$limit,$page]; }
    private static function cents(mixed $value,string $field='price'): int { if(!is_numeric($value)||(float)$value<0)throw new ApiException(422,'validation_failed','Money value must be non-negative.',[$field=>'Invalid amount.']);return(int)round((float)$value*100); }
}
