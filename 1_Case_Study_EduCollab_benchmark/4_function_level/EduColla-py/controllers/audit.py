from flask import Blueprint, render_template, jsonify, session

from decorators import admin_required
from models.audit import AuditRepository
from models.user import UserRepository


audit_bp = Blueprint('audit', __name__)


@audit_bp.get('/admin/audit')
@admin_required
def admin_audit_page():
    user_id = session.get('user_id')
    u = UserRepository.get_by_id(user_id)
    try:
        AuditRepository.create(user_id, u['username'] if u else None, 'audit_view', meta={'via': 'web'})
    except Exception:
        # Do not crash the page if audit writing fails
        pass

    rows = AuditRepository.list_recent(200)
    return render_template('admin_audit.html', rows=rows)


@audit_bp.get('/api/admin/audit')
@admin_required
def admin_audit_api():
    user_id = session.get('user_id')
    u = UserRepository.get_by_id(user_id)
    try:
        AuditRepository.create(user_id, u['username'] if u else None, 'audit_view', meta={'via': 'api'})
    except Exception:
        pass

    rows = AuditRepository.list_recent(200)
    out = []
    for r in rows:
        out.append({
            'audit_id': r['audit_id'],
            'actor_user_id': r['actor_user_id'],
            'actor_username': r['actor_username'],
            'action': r['action'],
            'target_type': r['target_type'],
            'target_id': r['target_id'],
            'meta_json': r['meta_json'],
            'created_at': r['created_at'],
        })
    return jsonify({'audit': out})
