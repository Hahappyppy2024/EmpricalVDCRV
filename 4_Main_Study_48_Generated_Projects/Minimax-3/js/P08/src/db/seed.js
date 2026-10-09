import crypto from 'node:crypto';

export function hashPassword(password) {
  return crypto.createHash('sha256').update(`exp-salt::${password}`).digest('hex');
}

export function seed(target) {
  console.log('Seeding deterministic fixtures...');

  const tx = target.transaction(() => {
    target.exec('DELETE FROM admin_configurations');
    target.exec('DELETE FROM reimbursement_exports');
    target.exec('DELETE FROM audit_events');
    target.exec('DELETE FROM activity_logs');
    target.exec('DELETE FROM comments');
    target.exec('DELETE FROM finance_reviews');
    target.exec('DELETE FROM manager_approvals');
    target.exec('DELETE FROM policy_rules');
    target.exec('DELETE FROM stored_files');
    target.exec('DELETE FROM expense_lines');
    target.exec('DELETE FROM expense_reports');
    target.exec('DELETE FROM categories');
    target.exec('DELETE FROM sessions');
    target.exec('DELETE FROM users');
    target.exec('DELETE FROM departments');

    const insertDept = target.prepare(
      'INSERT INTO departments (name, code, cost_center) VALUES (?, ?, ?)'
    );
    const deptIds = {};
    const departments = [
      { name: 'Engineering', code: 'ENG', cost_center: 'CC-100' },
      { name: 'Sales', code: 'SAL', cost_center: 'CC-200' },
      { name: 'Marketing', code: 'MKT', cost_center: 'CC-300' },
      { name: 'Operations', code: 'OPS', cost_center: 'CC-400' },
      { name: 'Finance', code: 'FIN', cost_center: 'CC-500' }
    ];
    for (const d of departments) {
      const info = insertDept.run(d.name, d.code, d.cost_center);
      deptIds[d.code] = info.lastInsertRowid;
    }

    const insertUser = target.prepare(
      'INSERT INTO users (username, email, full_name, password_hash, role, department_id, manager_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    const password = 'Passw0rd!';
    const hash = hashPassword(password);

    const adminInfo = insertUser.run('admin', 'admin@example.com', 'Avery Admin', hash, 'admin', deptIds.OPS, null);
    insertUser.run('fin_lead', 'finlead@example.com', 'Fiona Finance', hash, 'finance', deptIds.FIN, null);
    insertUser.run('fin_officer', 'finofficer@example.com', 'Frank Funds', hash, 'finance', deptIds.FIN, adminInfo.lastInsertRowid);

    const engMgr = insertUser.run('eng_manager', 'engmgr@example.com', 'Megan Manager-Eng', hash, 'manager', deptIds.ENG, null);
    const salMgr = insertUser.run('sales_manager', 'salemgr@example.com', 'Sam Manager-Sales', hash, 'manager', deptIds.SAL, null);

    insertUser.run('emp_eng1', 'eng1@example.com', 'Ethan Engineer-1', hash, 'employee', deptIds.ENG, engMgr.lastInsertRowid);
    insertUser.run('emp_eng2', 'eng2@example.com', 'Eva Engineer-2', hash, 'employee', deptIds.ENG, engMgr.lastInsertRowid);
    insertUser.run('emp_sal1', 'sal1@example.com', 'Sofia Sales-1', hash, 'employee', deptIds.SAL, salMgr.lastInsertRowid);
    insertUser.run('emp_sal2', 'sal2@example.com', 'Steve Sales-2', hash, 'employee', deptIds.SAL, salMgr.lastInsertRowid);

    const insertCat = target.prepare(
      'INSERT INTO categories (name, code, description) VALUES (?, ?, ?)'
    );
    const categories = [
      { name: 'Travel', code: 'TRV', description: 'Air, rail, taxi, lodging' },
      { name: 'Meals', code: 'MLS', description: 'Business meals and entertainment' },
      { name: 'Supplies', code: 'SUP', description: 'Office and project supplies' },
      { name: 'Software', code: 'SFT', description: 'Software and SaaS subscriptions' },
      { name: 'Training', code: 'TRN', description: 'Conferences and training' }
    ];
    const catIds = {};
    for (const c of categories) {
      const info = insertCat.run(c.name, c.code, c.description);
      catIds[c.code] = info.lastInsertRowid;
    }

    const insertPolicy = target.prepare(
      'INSERT INTO policy_rules (name, description, category_id, max_amount, require_receipt_above, block_when_exceeded, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    insertPolicy.run(
      'Travel cap',
      'Travel category cap per line',
      catIds.TRV,
      5000,
      50,
      1,
      1,
      adminInfo.lastInsertRowid
    );
    insertPolicy.run(
      'Receipt required for meals over 25',
      'Receipt required for meal expenses above $25',
      catIds.MLS,
      null,
      25,
      0,
      1,
      adminInfo.lastInsertRowid
    );
    insertPolicy.run(
      'Software approval threshold',
      'Software expenses above $1000 require admin approval',
      catIds.SFT,
      1000,
      0,
      0,
      1,
      adminInfo.lastInsertRowid
    );

    const insertConfig = target.prepare(
      'INSERT INTO admin_configurations (config_key, config_value, description, updated_by) VALUES (?, ?, ?, ?)'
    );
    const configs = [
      ['company_name', 'Acme Corporation', 'Company display name', adminInfo.lastInsertRowid],
      ['default_currency', 'USD', 'Default currency for new reports', adminInfo.lastInsertRowid],
      ['fiscal_year_start', '04-01', 'Fiscal year start MM-DD', adminInfo.lastInsertRowid],
      ['allow_self_approval', 'false', 'Managers cannot approve their own reports', adminInfo.lastInsertRowid],
      ['max_report_amount', '25000', 'Maximum allowed report total', adminInfo.lastInsertRowid]
    ];
    for (const c of configs) {
      insertConfig.run(...c);
    }
  });

  tx();

  console.log('Seed complete.');
  console.log('Default password for every seeded user: Passw0rd!');
  console.log('Seeded users: admin, fin_lead, fin_officer, eng_manager, sales_manager, emp_eng1, emp_eng2, emp_sal1, emp_sal2');
}

