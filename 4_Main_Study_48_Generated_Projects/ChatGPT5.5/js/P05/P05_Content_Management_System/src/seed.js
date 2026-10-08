import {hashPassword} from './security.js';
export function seedDatabase(db){
 const roles=[['visitor','[]'],['author','["content:create","content:own"]'],['editor','["content:edit","content:publish","media:manage"]'],['admin','["site:admin"]'],['moderator','["comments:moderate"]']];
 const ir=db.prepare('INSERT INTO roles(name,capabilities) VALUES(?,?)');for(const r of roles)ir.run(...r);
 const iu=db.prepare('INSERT INTO users(id,email,password_hash,name,role,status) VALUES(?,?,?,?,?,?)');for(const u of [[1,'author@example.test','Author One','author','active'],[2,'other@example.test','Other Author','author','active'],[3,'editor@example.test','Editor','editor','active'],[4,'admin@example.test','Administrator','admin','active'],[5,'moderator@example.test','Moderator','moderator','active'],[6,'disabled@example.test','Disabled','author','disabled']])iu.run(u[0],u[1],hashPassword('Password123!'),u[2],u[3],u[4]);
 db.exec(`
 INSERT INTO templates VALUES(1,'Article','post','<article>{{body}}</article>',1),(2,'Landing','page','<main>{{body}}</main>',1);
 INSERT INTO content VALUES(1,1,'post','Draft Guide','draft-guide','A draft','Guides','["draft"]','[{"type":"paragraph","text":"Draft body"}]',1,'draft',NULL,NULL,1,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:00.000Z');
 INSERT INTO content VALUES(2,1,'post','Welcome','welcome','Published welcome','News','["welcome","news"]','[{"type":"heading","text":"Welcome"},{"type":"paragraph","text":"Our public article."}]',1,'published',NULL,'2026-08-01T00:00:00.000Z',1,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:00.000Z');
 INSERT INTO content VALUES(3,1,'post','Needs Review','needs-review','Review me','Guides','["review"]','[{"type":"paragraph","text":"Review body"}]',1,'submitted',NULL,NULL,1,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:00.000Z');
 INSERT INTO content VALUES(4,2,'page','About','about','About us','Company','["company"]','[{"type":"paragraph","text":"About the site"}]',2,'published',NULL,'2026-08-02T00:00:00.000Z',1,'2026-08-01T00:00:00.000Z','2026-08-02T00:00:00.000Z');
 INSERT INTO media_assets VALUES(1,1,'seed.txt','text/plain','Seed file','Fixture',X'73656564',1,'2026-08-01T00:00:00.000Z');
 INSERT INTO comments VALUES(1,2,'Reader','reader@example.test','Useful article.','published','','2026-08-02T00:00:00.000Z'),(2,2,'Pending','pending@example.test','Please review.','pending','','2026-08-03T00:00:00.000Z');
 INSERT INTO settings VALUES('site_name','Demo CMS',1),('tagline','Deterministic publishing',1),('comments_enabled','true',1);
 INSERT INTO integrations VALUES(1,'analytics',0,'{"tracking_id":""}',1),(2,'email',1,'{"sender":"noreply@example.test"}',1);
 INSERT INTO navigation VALUES(1,'[{"label":"Home","path":"/"},{"label":"About","path":"/about"}]',1);
 INSERT INTO redirects VALUES(1,'/old-welcome','/welcome',301,'2026-08-01T00:00:00.000Z');
 `);
}
