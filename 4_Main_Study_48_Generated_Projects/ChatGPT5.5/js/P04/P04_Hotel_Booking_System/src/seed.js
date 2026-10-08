import {hashPassword} from './security.js';
const now=()=>new Date().toISOString();
export function seedDatabase(db){
 const insertUser=db.prepare('INSERT INTO users(id,email,password_hash,name,role,status) VALUES(?,?,?,?,?,?)');
 for(const u of [[1,'guest@example.test','Guest','guest','active'],[2,'other@example.test','Other Guest','guest','active'],[3,'staff@example.test','Front Desk','staff','active'],[4,'admin@example.test','Hotel Admin','admin','active'],[5,'moderator@example.test','Moderator','moderator','active'],[6,'disabled@example.test','Disabled','guest','disabled']])insertUser.run(u[0],u[1],hashPassword('Password123!'),u[2],u[3],u[4]);
 db.exec(`
 INSERT INTO room_types VALUES(1,'Standard','Comfortable queen room',2,1,'["wifi","desk"]','active',1),(2,'Deluxe','King room with city view',3,2,'["wifi","desk","breakfast","view"]','active',1);
 INSERT INTO cancellation_policies VALUES(1,'Flexible',24,25,1),(2,'Strict',72,50,1);
 INSERT INTO rate_plans VALUES(1,1,1,'Standard Flexible',12000,'2029-01-01','2031-12-31',2,1,1),(2,2,2,'Deluxe Advance',20000,'2029-01-01','2031-12-31',3,2,1);
 INSERT INTO rooms VALUES(1,'101',1,'available',NULL,1),(2,'102',1,'maintenance','Plumbing',1),(3,'201',2,'occupied',NULL,1),(4,'202',2,'available',NULL,1);
 INSERT INTO bookings VALUES(1,'HTL-SEED01',1,1,2,NULL,'2030-03-01','2030-03-03',2,0,40000,'Strict',72,50,'confirmed',1,'2026-08-01T00:00:00.000Z','seed-booking-1');
 INSERT INTO payments VALUES(1,1,40000,'charge','succeeded','pm_seed','seed-payment-1','2026-08-01T00:00:00.000Z');
 INSERT INTO invoices VALUES(1,1,40000,0,0,40000,'open',NULL);
 INSERT INTO bookings VALUES(2,'HTL-SEED02',1,3,1,1,'2029-01-01','2029-01-02',1,0,12000,'Flexible',24,25,'checked_out',2,'2028-12-01T00:00:00.000Z','seed-booking-2');
 INSERT INTO stays VALUES(1,2,1,'2029-01-01T15:00:00.000Z','2029-01-02T11:00:00.000Z',2500);
 INSERT INTO payments VALUES(2,2,14500,'charge','succeeded','pm_seed','seed-payment-2','2029-01-02T11:00:00.000Z');
 INSERT INTO invoices VALUES(2,2,12000,2500,0,14500,'finalized','2029-01-02T11:00:00.000Z');
 INSERT INTO bookings VALUES(3,'HTL-SEED03',1,3,2,3,'2030-04-01','2030-04-02',1,0,20000,'Strict',72,50,'checked_in',2,'2026-08-01T00:00:00.000Z','seed-booking-3');
 INSERT INTO stays VALUES(2,3,3,'2030-04-01T15:00:00.000Z',NULL,0);
 INSERT INTO payments VALUES(3,3,20000,'charge','succeeded','pm_seed','seed-payment-3','2026-08-01T00:00:00.000Z');
 INSERT INTO invoices VALUES(3,3,20000,0,0,20000,'open',NULL);
 INSERT INTO bookings VALUES(4,'HTL-SEED04',2,2,1,NULL,'2030-05-01','2030-05-02',1,0,12000,'Flexible',24,25,'cancelled',2,'2026-08-01T00:00:00.000Z','seed-booking-4');
 INSERT INTO cancellations VALUES(1,4,'Plans changed',0,12000,'2026-08-02T00:00:00.000Z');
 INSERT INTO booking_messages VALUES(1,1,1,'Please prepare a quiet room.','2026-08-01T00:00:00.000Z','seed-message-1');
 INSERT INTO audit_events VALUES(1,3,'booking.checked_out','HTL-SEED02','2029-01-02T11:00:00.000Z');
 `);
}
