from flask import Flask

from controllers.auth_controller import auth_bp
from controllers.course_controller import course_bp
from controllers.page_controller import page_bp
from models.db import init_db, seed_admin


def create_app():
    app = Flask(__name__)
    app.config['SECRET_KEY'] = 'educollab-dev-secret-key'
    app.config['DATABASE'] = 'data/app.db'

    init_db(app)
    seed_admin(app)

    app.register_blueprint(page_bp)
    app.register_blueprint(auth_bp)
    app.register_blueprint(course_bp)
    return app


app = create_app()


if __name__ == '__main__':
    app.run(debug=True)
