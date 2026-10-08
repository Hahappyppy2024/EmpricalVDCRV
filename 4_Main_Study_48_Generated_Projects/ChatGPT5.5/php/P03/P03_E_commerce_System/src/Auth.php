<?php
declare(strict_types=1);
namespace App;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
final class Auth
{
    public function __construct(private PDO $db,private string $cookie,private int $ttl){}
    public function current(ServerRequestInterface $r):?array{$plain=$r->getCookieParams()[$this->cookie]??'';if(!is_string($plain)||$plain==='')return null;$s=$this->db->prepare("SELECT users.id,users.email,users.name,users.role,users.phone FROM sessions JOIN users ON users.id=sessions.user_id WHERE sessions.id=? AND sessions.expires_at>datetime('now')");$s->execute([hash('sha256',$plain)]);return$s->fetch()?:null;}
    public function requireUser(ServerRequestInterface $r,array $roles=[]):array{$u=$this->current($r);if(!$u)throw new ApiException(401,'authentication_required','Sign in is required.');if($roles&&!in_array($u['role'],$roles,true))throw new ApiException(403,'forbidden','Your account cannot perform this operation.');return$u;}
    public function create(int $uid):string{$plain=bin2hex(random_bytes(32));$this->db->prepare("INSERT INTO sessions(id,user_id,expires_at) VALUES(?,?,datetime('now',?))")->execute([hash('sha256',$plain),$uid,'+'.$this->ttl.' seconds']);return$plain;}
    public function destroy(ServerRequestInterface $r):void{$plain=$r->getCookieParams()[$this->cookie]??'';if(is_string($plain)&&$plain!=='')$this->db->prepare('DELETE FROM sessions WHERE id=?')->execute([hash('sha256',$plain)]);}
    public function cookie(string $token,int $maxAge):string{return sprintf('%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax',$this->cookie,rawurlencode($token),$maxAge);}
}
