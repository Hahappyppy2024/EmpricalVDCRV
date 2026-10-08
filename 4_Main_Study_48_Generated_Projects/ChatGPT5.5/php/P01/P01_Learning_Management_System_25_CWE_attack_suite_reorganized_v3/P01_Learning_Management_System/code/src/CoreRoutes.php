<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

final class CoreRoutes
{
    use RouteSupport;

    public static function register(App $app, PDO $db, Auth $auth, array $config): void
    {
        $app->get('/', fn(Request $r, Response $s) => $s->withHeader('Location','/app.html')->withStatus(302));
        $app->get('/health', fn(Request $r, Response $s) => Http::json($s,['status'=>'ok','database'=>(bool)$db->query('SELECT 1')->fetchColumn()]));

        $app->post('/api/auth/register', function(Request $r, Response $s) use($db,$auth,$config) {
            $d=self::body($r); self::required($d,['email','password','displayName']);
            if(!filter_var($d['email'],FILTER_VALIDATE_EMAIL)) throw new ApiException(422,'validation_failed','Invalid email.',['email'=>'Enter a valid email.']);
            if(strlen((string)$d['password'])<10) throw new ApiException(422,'validation_failed','Password is too short.',['password'=>'Use at least 10 characters.']);
            try { $db->beginTransaction(); $db->prepare("INSERT INTO users(email,password_hash,display_name,role) VALUES(?,?,?,'student')")->execute([strtolower(trim($d['email'])),password_hash($d['password'],PASSWORD_DEFAULT),trim($d['displayName'])]); $id=(int)$db->lastInsertId(); $db->prepare('INSERT INTO user_preferences(user_id) VALUES(?)')->execute([$id]); $db->commit(); }
            catch(\PDOException $e){if($db->inTransaction())$db->rollBack(); if($e->getCode()==='23000')throw new ApiException(409,'email_exists','An account already uses this email.'); throw $e;}
            [$token]=$auth->createSession($id); $u=self::one($db,'SELECT id,email,display_name,role,bio,timezone FROM users WHERE id=?',[$id]);
            return Http::json($s,['user'=>self::publicUser($u)],201)->withAddedHeader('Set-Cookie',$auth->cookie($token,$config['sessionTtl']));
        });
        $app->post('/api/auth/login', function(Request $r, Response $s) use($db,$auth,$config) {
            $d=self::body($r); self::required($d,['email','password']); $stmt=$db->prepare('SELECT * FROM users WHERE email=? COLLATE NOCASE'); $stmt->execute([trim($d['email'])]); $u=$stmt->fetch();
            if(!$u||!password_verify((string)$d['password'],$u['password_hash'])) throw new ApiException(401,'invalid_credentials','Email or password is incorrect.');
            [$token]=$auth->createSession((int)$u['id']); return Http::json($s,['user'=>self::publicUser($u)])->withAddedHeader('Set-Cookie',$auth->cookie($token,$config['sessionTtl']));
        });
        $app->post('/api/auth/logout', function(Request $r, Response $s)use($auth){$auth->destroy($r);return Http::json($s,['message'=>'Signed out.'])->withAddedHeader('Set-Cookie',$auth->cookie('',0));});
        $app->post('/api/auth/password-reset-requests', function(Request $r, Response $s)use($db,$config){$d=self::body($r);self::required($d,['email']);$stmt=$db->prepare('SELECT id FROM users WHERE email=? COLLATE NOCASE');$stmt->execute([trim($d['email'])]);$id=$stmt->fetchColumn();$response=['message'=>'If that account exists, a reset instruction has been created.'];if($id){$plain=bin2hex(random_bytes(24));$db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$id]);$db->prepare("INSERT INTO password_reset_tokens(token_hash,user_id,expires_at) VALUES(?,?,datetime('now',?))")->execute([hash('sha256',$plain),$id,'+'.(int)$config['resetTtl'].' seconds']);if(($_ENV['APP_ENV']??'development')==='development')$response['developmentResetToken']=$plain;}return Http::json($s,$response);});
        $app->post('/api/auth/password-resets', function(Request $r, Response $s)use($db){$d=self::body($r);self::required($d,['resetToken','newPassword']);if(strlen($d['newPassword'])<10)throw new ApiException(422,'validation_failed','Password is too short.',['newPassword'=>'Use at least 10 characters.']);$row=self::one($db,"SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>datetime('now')",[hash('sha256',$d['resetToken'])],'reset_token_invalid');$db->beginTransaction();$db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($d['newPassword'],PASSWORD_DEFAULT),$row['user_id']]);$db->prepare("UPDATE password_reset_tokens SET used_at=datetime('now') WHERE token_hash=?")->execute([$row['token_hash']]);$db->prepare('DELETE FROM sessions WHERE user_id=?')->execute([$row['user_id']]);$db->commit();return Http::json($s,['message'=>'Password reset completed.']);});

        $app->get('/api/profile',function(Request $r,Response $s)use($auth){return Http::json($s,['user'=>self::publicUser($auth->requireUser($r))]);});
        $app->patch('/api/profile',function(Request $r,Response $s)use($auth,$db){$u=$auth->requireUser($r);$d=self::body($r);$allowed=['displayName'=>'display_name','bio'=>'bio','timezone'=>'timezone'];$sets=[];$p=[];foreach($allowed as $in=>$col)if(array_key_exists($in,$d)){$sets[]="$col=?";$p[]=trim((string)$d[$in]);}if(!$sets)throw new ApiException(422,'validation_failed','No supported profile fields supplied.');$p[]=$u['id'];$db->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?')->execute($p);return Http::json($s,['user'=>self::publicUser(self::one($db,'SELECT id,email,display_name,role,bio,timezone FROM users WHERE id=?',[$u['id']]))]);});
        $app->get('/api/profile/preferences',function(Request $r,Response $s)use($auth,$db){$u=$auth->requireUser($r);return Http::json($s,['preferences'=>self::one($db,'SELECT email_announcements,email_assignments,browser_notifications FROM user_preferences WHERE user_id=?',[$u['id']])]);});
        $app->patch('/api/profile/preferences',function(Request $r,Response $s)use($auth,$db){$u=$auth->requireUser($r);$d=self::body($r);$map=['emailAnnouncements'=>'email_announcements','emailAssignments'=>'email_assignments','browserNotifications'=>'browser_notifications'];$sets=[];$p=[];foreach($map as $in=>$col)if(array_key_exists($in,$d)){$sets[]="$col=?";$p[]=(int)(bool)$d[$in];}if(!$sets)throw new ApiException(422,'validation_failed','No supported preferences supplied.');$p[]=$u['id'];$db->prepare('UPDATE user_preferences SET '.implode(',',$sets).' WHERE user_id=?')->execute($p);return Http::json($s,['preferences'=>self::one($db,'SELECT email_announcements,email_assignments,browser_notifications FROM user_preferences WHERE user_id=?',[$u['id']])]);});
        $app->get('/api/notifications',function(Request $r,Response $s)use($auth,$db){$u=$auth->requireUser($r,['student','instructor']);[$limit,$offset,$page]=self::page($r);$unread=filter_var($r->getQueryParams()['unread']??false,FILTER_VALIDATE_BOOLEAN);$sql='SELECT * FROM notifications WHERE user_id=?'.($unread?' AND read_at IS NULL':'').' ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?';return Http::json($s,['items'=>self::all($db,$sql,[$u['id'],$limit,$offset]),'page'=>$page,'limit'=>$limit]);});
        $app->post('/api/notifications/{notificationId}/read',function(Request $r,Response $s,array $a)use($auth,$db){$u=$auth->requireUser($r,['student','instructor']);$n=self::one($db,'SELECT * FROM notifications WHERE id=? AND user_id=?',[(int)$a['notificationId'],$u['id']],'notification_not_found');$db->prepare("UPDATE notifications SET read_at=COALESCE(read_at,datetime('now')) WHERE id=?")->execute([$n['id']]);return Http::json($s,['notification'=>self::one($db,'SELECT * FROM notifications WHERE id=?',[$n['id']])]);});
        $app->get('/api/dashboard',function(Request $r,Response $s)use($auth,$db){$u=$auth->requireUser($r,['student','instructor']);if($u['role']==='student'){$courses=self::all($db,"SELECT c.id,c.title,c.status FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=? AND e.status IN ('active','completed') ORDER BY c.title",[$u['id']]);$upcoming=self::all($db,"SELECT a.id,a.title,a.due_at,c.title course_title FROM assignments a JOIN courses c ON c.id=a.course_id JOIN enrollments e ON e.course_id=c.id WHERE e.student_id=? AND e.status='active' AND a.due_at>datetime('now') ORDER BY a.due_at LIMIT 10",[$u['id']]);}else{$courses=self::all($db,'SELECT c.id,c.title,c.status FROM course_instructors ci JOIN courses c ON c.id=ci.course_id WHERE ci.user_id=? ORDER BY c.title',[$u['id']]);$upcoming=[];} $stmt=$db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');$stmt->execute([$u['id']]);return Http::json($s,['user'=>self::publicUser($u),'courses'=>$courses,'upcomingAssignments'=>$upcoming,'unreadNotifications'=>(int)$stmt->fetchColumn()]);});
    }
}
