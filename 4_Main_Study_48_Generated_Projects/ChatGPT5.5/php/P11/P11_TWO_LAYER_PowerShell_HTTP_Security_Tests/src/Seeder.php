<?php
declare(strict_types=1);
namespace App;

use PDO;

final class Seeder
{
    public static function reset(string $root, string $dbPath): void
    {
        if (is_file($dbPath)) unlink($dbPath);
        $db = Database::connect($dbPath);
        $db->exec((string)file_get_contents($root . '/database/schema.sql'));
        self::seed($db);
    }
    private static function seed(PDO $db): void
    {
        $hash = password_hash('Password123!', PASSWORD_DEFAULT);
        $insert = $db->prepare('INSERT INTO users(id,email,password_hash,role,status) VALUES(?,?,?,?,?)');
        foreach ([[1,'alice@example.test','customer','active'],[2,'bob@example.test','customer','active'],[3,'operator@example.test','operator','active'],[4,'admin@example.test','admin','active'],[5,'disabled@example.test','customer','disabled']] as $u) $insert->execute([$u[0],$u[1],$hash,$u[2],$u[3]]);
        $db->exec("INSERT INTO plans VALUES (1,'Starter',1024,3,900),(2,'Pro',10240,20,2900);");
        $db->exec("INSERT INTO accounts VALUES (1,1,2,'active',10240,1),(2,2,1,'active',1024,1),(3,5,1,'suspended',1024,1);");
        $db->exec("INSERT INTO domains VALUES (1,1,'alice.test','active',1),(2,2,'bob.test','active',1);");
        $db->exec("INSERT INTO dns_records VALUES (1,1,'A','@','192.0.2.10',3600),(2,2,'A','@','192.0.2.20',3600);");
        $db->exec("INSERT INTO sites VALUES (1,1,1,'Alice App','php83','active',1),(2,2,2,'Bob App','php83','active',1);");
        $db->exec("INSERT INTO deployments VALUES (1,1,'succeeded','2026-01-01T00:00:00Z');");
        $file = $db->prepare('INSERT INTO site_files VALUES (?,?,?,?,?,?)');
        $file->execute([1,1,'/index.html','<h1>Alice</h1>',14,'2026-01-01T00:00:00Z']);
        $file->execute([2,2,'/index.html','<h1>Bob</h1>',12,'2026-01-01T00:00:00Z']);
        $db->exec("INSERT INTO hosted_databases VALUES (1,1,'alice_app','sqlite','ready');");
        $db->exec("INSERT INTO backups VALUES (1,1,'ready','{\"site\":\"Alice App\",\"files\":1}','2026-01-02T00:00:00Z');");
        $db->exec("INSERT INTO certificates VALUES (1,1,'active','2026-12-31T00:00:00Z');");
        $db->exec("INSERT INTO scheduled_tasks VALUES (1,1,'backup','0 2 * * *',1,1);");
        $db->exec("INSERT INTO metric_samples VALUES (1,1,'2026-01-01T00:00:00Z',12.5,128,256,100),(2,1,'2026-01-01T01:00:00Z',18,140,258,125),(3,2,'2026-01-01T00:00:00Z',8,64,100,40);");
        $db->exec("INSERT INTO support_tickets VALUES (1,1,'Seed ticket','open','2026-01-01T00:00:00Z'),(2,2,'Bob ticket','open','2026-01-01T00:00:00Z');");
        $db->exec("INSERT INTO ticket_replies VALUES (1,1,1,'Please investigate','2026-01-01T00:00:00Z');");
        $db->exec("INSERT INTO audit_events VALUES (1,1,1,'site.seeded','Seed site created','2026-01-01T00:00:00Z'),(2,4,NULL,'platform.seeded','Platform initialized','2026-01-01T00:00:00Z');");
        $db->exec("INSERT INTO settings VALUES ('maintenance','off');");
    }
}
