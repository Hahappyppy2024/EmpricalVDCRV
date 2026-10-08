<?php
declare(strict_types=1);
namespace App;

use PDO;
use Psr\Http\Message\ServerRequestInterface;

final class Auth
{
    public function __construct(private readonly PDO $db, public readonly string $cookie, private readonly int $ttl) {}
    public function login(string $email, string $password): array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE email=?'); $s->execute([strtolower(trim($email))]); $u = $s->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) throw new ApiException(401,'invalid_credentials','Invalid email or password.');
        if ($u['status'] !== 'active') throw new ApiException(403,'account_disabled','Account is disabled.');
        $token = bin2hex(random_bytes(24));
        $s = $this->db->prepare('INSERT INTO sessions(token_hash,user_id,expires_at) VALUES(?,?,?)'); $s->execute([hash('sha256',$token),$u['id'],gmdate(DATE_ATOM,time()+$this->ttl)]);
        return [$this->publicUser($u),$token];
    }
    public function user(ServerRequestInterface $r, array $roles = []): array
    {
        $token = $r->getCookieParams()[$this->cookie] ?? '';
        if ($token === '') throw new ApiException(401,'unauthenticated','Authentication required.');
        $s=$this->db->prepare('SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>?'); $s->execute([hash('sha256',$token),gmdate(DATE_ATOM)]); $u=$s->fetch();
        if (!$u) throw new ApiException(401,'unauthenticated','Authentication required.');
        if ($roles && !in_array($u['role'],$roles,true)) throw new ApiException(403,'forbidden','This role is not permitted.');
        return $u;
    }
    public function logout(ServerRequestInterface $r): void
    {
        $token=$r->getCookieParams()[$this->cookie]??'';
        if ($token!=='') { $s=$this->db->prepare('DELETE FROM sessions WHERE token_hash=?'); $s->execute([hash('sha256',$token)]); }
    }
    public function publicUser(array $u): array { return ['id'=>(int)$u['id'],'email'=>$u['email'],'role'=>$u['role'],'status'=>$u['status']]; }
}
