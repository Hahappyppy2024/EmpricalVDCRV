const { makeAppWithFreshDb, apiRegister, apiLogin, apiCreateCourse } = require('../_helpers');
const { zipBuffer, getUserIdByUsername } = require('./_req_utils');

describe('Requirement-level FR-CSV3', () => {
  test('export assignment grades csv', async () => {
    const { app, agent } = makeAppWithFreshDb('csv3.db');
    await apiLogin(agent, { username: 'admin', password: 'admin123' });

    const c = await apiCreateCourse(agent, { title: 'C4', description: 'D4' });
    expect(c.status).toBe(201);
    const course_id = c.body.course.course_id;

    const csv = Buffer.from("username,display_name,role_in_course\ncarol,Carol,student\n");
    await agent.post(`/api/courses/${course_id}/members/import`).attach('csv', csv, { filename: 'm.csv' });

    const asg = await agent.post(`/api/courses/${course_id}/assignments`).send({
      title: 'A1', description: 'Asg', due_at: new Date().toISOString()
    });
    expect(asg.status).toBe(201);
    const assignment_id = asg.body.assignment.assignment_id;

    const request = require('supertest');
    const student = request.agent(app);
    await apiLogin(student, { username: 'carol', password: 'password' });

    const sub = await student.post(`/api/courses/${course_id}/assignments/${assignment_id}/submissions`).send({
      content_text: 'my work'
    });
    expect(sub.status).toBe(201);

    const exp = await agent.get(`/api/courses/${course_id}/assignments/${assignment_id}/grades/export.csv`);
    expect(exp.status).toBe(200);
    expect(exp.text).toMatch(/submission_id,student_id,username,score,feedback/);
    expect(exp.text).toMatch(/carol/);
  });
});
