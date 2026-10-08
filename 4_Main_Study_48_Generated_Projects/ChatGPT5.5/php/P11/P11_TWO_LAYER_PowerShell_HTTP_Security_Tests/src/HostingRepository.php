<?php
declare(strict_types=1);
namespace App;

use PDO;

final class HostingRepository
{
    public function __construct(public readonly PDO $db) {}
    public function account(array $user): array
    {
        $s=$this->db->prepare('SELECT * FROM accounts WHERE user_id=?'); $s->execute([$user['id']]); $a=$s->fetch();
        if (!$a) throw new ApiException(403,'account_required','A hosting account is required.');
        return $a;
    }
    public function owned(string $table, int $id, array $user, string $via='account_id'): array
    {
        $a=$this->account($user); $s=$this->db->prepare("SELECT * FROM {$table} WHERE id=? AND {$via}=?"); $s->execute([$id,$a['id']]); $row=$s->fetch();
        if (!$row) throw new ApiException(404,'not_found','Resource not found.');
        return $row;
    }
    public function site(int $id,array $user,bool $operator=false): array
    {
        if ($operator && $user['role']==='operator') { $s=$this->db->prepare('SELECT * FROM sites WHERE id=?'); $s->execute([$id]); $row=$s->fetch(); }
        else $row=$this->owned('sites',$id,$user);
        if (!$row) throw new ApiException(404,'not_found','Resource not found.');
        return $row;
    }
    public function child(string $table,int $id,array $user,string $parent,string $fk): array
    {
        $s=$this->db->prepare("SELECT c.* FROM {$table} c JOIN {$parent} p ON p.id=c.{$fk} JOIN accounts a ON a.id=p.account_id WHERE c.id=? AND a.user_id=?"); $s->execute([$id,$user['id']]); $row=$s->fetch();
        if (!$row) throw new ApiException(404,'not_found','Resource not found.');
        return $row;
    }
    public function rows(string $sql,array $args=[]): array { $s=$this->db->prepare($sql); $s->execute($args); return $s->fetchAll(); }
    public function audit(int $actor,?int $site,string $action,string $details): void { $s=$this->db->prepare('INSERT INTO audit_events(actor_id,site_id,action,details,created_at) VALUES(?,?,?,?,?)'); $s->execute([$actor,$site,$action,$details,gmdate(DATE_ATOM)]); }
}
