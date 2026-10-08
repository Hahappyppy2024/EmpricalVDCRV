import {hashPassword,hashToken} from './security.js';export function seedDatabase(db){const user=db.prepare('INSERT INTO users(id,email,password_hash,name,role,status) VALUES(?,?,?,?,?,?)');for(const u of [[1,'user@example.test','Primary User','user','active'],[2,'other@example.test','Other User','user','active'],[3,'admin@example.test','Administrator','admin','active'],[4,'disabled@example.test','Disabled User','user','disabled']])user.run(u[0],u[1],hashPassword('Password123!'),u[2],u[3],u[4]);db.exec(`
 INSERT INTO models VALUES(1,'local-small','local','Deterministic local assistant',1,1,1),(2,'local-large','local','Larger deterministic context',1,0.8,1),(3,'disabled-model','local','Unavailable fixture',0,1,1);
 INSERT INTO user_model_settings VALUES(1,1,0.2,500,1),(2,2,0.3,700,1),(3,1,0.1,500,1);
 INSERT INTO prompt_templates VALUES(1,NULL,'Summarize','Summarize the following: {{input}}',1,1),(2,1,'My template','Answer clearly: {{input}}',0,1),(3,2,'Private other','Private: {{input}}',0,1);
 INSERT INTO knowledge_collections VALUES(1,1,'Product Docs','Seed documentation',1,'2026-08-01T00:00:00.000Z'),(2,2,'Other Docs','Private collection',1,'2026-08-01T00:00:00.000Z');
 INSERT INTO conversations VALUES(1,1,'Project questions','active',1,1,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:00.000Z'),(2,1,'Archived chat','archived',NULL,1,'2026-08-01T00:00:00.000Z','2026-08-02T00:00:00.000Z'),(3,2,'Other private chat','active',2,1,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:00.000Z');
 INSERT INTO knowledge_files VALUES(1,1,'guide.txt','text/plain',X'4C6564676572466C6F7720737570706F727473206F66666C696E652064657465726D696E697374696320616E73776572732E205468652070726F6475637420686173206369746174696F6E732E','ready','2026-08-01T00:00:00.000Z'),(2,2,'private.txt','text/plain',X'50726976617465206F74686572207573657220646174612E','ready','2026-08-01T00:00:00.000Z');
 INSERT INTO ingestion_jobs VALUES(1,1,'completed',2,NULL,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:01.000Z'),(2,2,'completed',1,NULL,'2026-08-01T00:00:00.000Z','2026-08-01T00:00:01.000Z');
 INSERT INTO knowledge_chunks VALUES(1,1,0,'LedgerFlow supports offline deterministic answers.'),(2,1,1,'The product has citations.'),(3,2,0,'Private other user data.');
 INSERT INTO tools VALUES(1,'calculator','Deterministic arithmetic helper',1,1),(2,'clock','Fixed demonstration clock',1,1),(3,'disabled-tool','Unavailable fixture',0,1);
 INSERT INTO conversation_tools VALUES(1,1);
 INSERT INTO provider_credentials VALUES(1,1,'demo','Local key','${hashToken('secret-key-1234')}','1234',1,'2026-08-01T00:00:00.000Z');
 INSERT INTO messages VALUES(1,1,'user','What is supported?','visible','2026-08-01T00:00:00.000Z'),(2,1,'assistant','Offline deterministic answers are supported.','visible','2026-08-01T00:00:01.000Z'),(3,3,'user','Private question','visible','2026-08-01T00:00:00.000Z'),(4,1,'user','Flagged seed text','flagged','2026-08-02T00:00:00.000Z');
 INSERT INTO chat_runs VALUES(1,1,1,2,1,'completed','Offline deterministic answers are supported.',4,6,'seed-run','2026-08-01T00:00:00.000Z','2026-08-01T00:00:01.000Z'),(2,1,1,NULL,1,'running','',4,0,'seed-running','2026-08-03T00:00:00.000Z',NULL);
 INSERT INTO chat_events VALUES(1,1,'message.delta','{"text":"Offline deterministic answers are supported."}',1),(2,1,'run.completed','{"status":"completed"}',2),(3,2,'run.started','{"status":"running"}',1);
 INSERT INTO citations VALUES(1,1,1,1,'LedgerFlow supports offline deterministic answers.');
 INSERT INTO usage_records VALUES(1,1,1,1,4,6,'2026-08-01T00:00:01.000Z');
 INSERT INTO audit_events VALUES(1,1,'chat.completed','run:1','2026-08-01T00:00:01.000Z');
 INSERT INTO platform_settings VALUES('registration_enabled','true',1),('max_input_chars','4000',1),('moderation_enabled','true',1);
 INSERT INTO moderation_cases VALUES(1,4,'Seed review','open','',NULL,NULL,'2026-08-02T00:00:00.000Z');
 `);}
