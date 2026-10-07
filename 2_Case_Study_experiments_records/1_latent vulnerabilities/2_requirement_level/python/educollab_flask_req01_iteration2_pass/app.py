import os
import secrets

from flask import Flask

from controllers.auth_controller import auth_bp
from controllers.course_controller import course_bp
from controllers.page_controller import page_bp
from models.db import init_db, seed_admin
from utils.auth import get_csrf_token


def _load_secret_key():
    configured_secret = os.environ.get('EDUCOLLAB_SECRET_KEY', '').strip()
    if configured_secret and configured_secret != 'educollab-dev-secret-key':
        return configured_secret
    return secrets.token_hex(32)


def create_app():
    app = Flask(__name__)
    app.config['SECRET_KEY'] = _load_secret_key()
    app.config['DATABASE'] = 'data/app.db'

    @app.context_processor
    def inject_template_security_helpers():
        return {'csrf_token': get_csrf_token}

    init_db(app)
    seed_admin(app)

    app.register_blueprint(page_bp)
    app.register_blueprint(auth_bp)
    app.register_blueprint(course_bp)
    return app


app = create_app()


if __name__ == '__main__':
    app.run(debug=True)