from flask import Flask

from controllers.assignment_controller import assignment_bp
from controllers.auth_controller import auth_bp
from controllers.course_controller import course_bp
from controllers.membership_controller import membership_bp
from controllers.page_controller import page_bp
from controllers.post_controller import post_bp
from controllers.quiz_controller import quiz_bp
from models.db import init_db, seed_admin


def create_app():
    app = Flask(__name__)
    app.config['SECRET_KEY'] = 'educollab-dev-secret-key'
    app.config['DATABASE'] = 'data/app.db'
    app.config['UPLOAD_ROOT'] = 'storage/uploads'

    init_db(app)
    seed_admin(app)

    app.register_blueprint(page_bp)
    app.register_blueprint(auth_bp)
    app.register_blueprint(course_bp)
    app.register_blueprint(membership_bp)
    app.register_blueprint(post_bp)
    app.register_blueprint(assignment_bp)
    app.register_blueprint(quiz_bp)
    return app


app = create_app()


if __name__ == '__main__':
    app.run(debug=True)
